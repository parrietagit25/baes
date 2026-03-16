<?php
/**
 * Chatbot integrado: recibe mensaje del usuario, consulta BD si aplica,
 * llama a OpenAI y devuelve la respuesta. Requiere sesión activa.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado. Inicia sesión para usar el asistente.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chatbot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$apiKey = defined('CHATBOT_OPENAI_API_KEY') ? CHATBOT_OPENAI_API_KEY : '';
if ($apiKey === '') {
    echo json_encode(['success' => false, 'message' => 'El asistente no está configurado (falta OPENAI_API_KEY).']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim((string) $input['message']) : '';
if ($userMessage === '') {
    echo json_encode(['success' => false, 'message' => 'Envía un mensaje de texto.']);
    exit;
}

// Límite de longitud
if (strlen($userMessage) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Mensaje demasiado largo.']);
    exit;
}

set_time_limit(45);

try {
// Contexto del sistema: descripción de la app y cómo usarla
$systemPrompt = getSystemPrompt($pdo, $userMessage);

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $userMessage]
];

$payload = [
    'model' => defined('CHATBOT_MODEL') ? CHATBOT_MODEL : 'gpt-4o-mini',
    'messages' => $messages,
    'max_tokens' => 1024,
    'temperature' => 0.7
];

$url = 'https://api.openai.com/v1/chat/completions';
$response = callOpenAI($url, $apiKey, $payload);

if ($response === null) {
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar con OpenAI. Revisa que el servidor pueda acceder a api.openai.com (firewall, SSL) o que la API key sea válida.']);
    exit;
}

$data = json_decode($response, true);
$reply = null;
if (isset($data['choices'][0]['message']['content'])) {
    $reply = trim($data['choices'][0]['message']['content']);
}
if (isset($data['error']['message'])) {
    echo json_encode(['success' => false, 'message' => 'Error del servicio: ' . $data['error']['message']]);
    exit;
}
if ($reply === null || $reply === '') {
    echo json_encode(['success' => false, 'message' => 'No se obtuvo respuesta del asistente.']);
    exit;
}

echo json_encode(['success' => true, 'reply' => $reply]);

} catch (Throwable $e) {
    error_log('Chatbot error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del asistente. Intenta más tarde.']);
}

/**
 * Llama a la API de OpenAI. Usa cURL si está disponible (más fiable con HTTPS/timeouts), sino file_get_contents.
 * Devuelve el body de la respuesta o null si falla.
 */
function callOpenAI(string $url, string $apiKey, array $payload): ?string {
    $json = json_encode($payload);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '' || $response === false) {
            error_log('Chatbot OpenAI cURL: ' . $err);
            return null;
        }
        return $response;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            'content' => $json,
            'timeout' => 30
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response !== false ? $response : null;
}

/**
 * Construye el prompt del sistema: instrucciones + datos de la app/BD si aplica.
 */
function getSystemPrompt(PDO $pdo, string $userMessage): string {
    $base = <<<TEXT
Eres el asistente virtual de BAES (sistema de Solicitudes de Crédito de Motus/AutoMarket). Respondes en español, de forma clara y breve.

Información sobre la aplicación:
- Solicitudes de crédito: desde "Solicitudes" se ven y gestionan las solicitudes. Puedes filtrar, ver detalle, adjuntar documentos, ver autos disponibles y el historial.
- Autos disponibles: en el detalle de una solicitud hay un botón "Autos disponibles" que abre un listado del inventario (marca, modelo, año, precio, etc.).
- Adjuntos: en cada solicitud se pueden subir documentos (PDF, imágenes); el sistema puede extraer texto (OCR) para su consulta.
- Roles: hay administradores, gestores y usuarios banco; cada uno ve lo que le corresponde.
- Integración Pipedrive: existe una pantalla para sincronizar leads con Pipedrive e importar solicitudes.

Si el usuario pregunta por autos en inventario, usa ÚNICAMENTE los datos que se te proporcionan en el siguiente bloque "DATOS_INVENTARIO". No inventes precios ni unidades.
Si no hay datos de inventario en el mensaje del sistema, di que puede ver el listado completo en "Autos disponibles" dentro de la solicitud.
Para dudas de uso (cómo subir un adjunto, cómo filtrar, etc.), explica los pasos de forma breve.
TEXT;

    // Si parece preguntar por inventario/autos, inyectar datos de la BD
    $lower = mb_strtolower($userMessage);
    if (preg_match('/\b(autos?|vehículos?|inventario|disponibles?|qué hay|cuántos)\b/u', $lower)) {
        $inventario = getResumenInventario($pdo);
        $base .= "\n\n--- DATOS_INVENTARIO (usa solo esto para responder sobre inventario) ---\n" . $inventario;
    }

    return $base;
}

/**
 * Obtiene un resumen del inventario desde Automarket_Invs_web_temp para inyectar en el contexto.
 */
function getResumenInventario(PDO $pdo): string {
    try {
        $sql = "SELECT Make, Model, Year, Price, LicensePlate
                FROM Automarket_Invs_web_temp
                ORDER BY Make, Model
                LIMIT 80";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return '(No se pudo consultar el inventario en este momento.)';
    }
    if (empty($rows)) {
        return 'No hay unidades en inventario en este momento.';
    }
    $lines = [];
    foreach ($rows as $r) {
        $precio = isset($r['Price']) && $r['Price'] !== null ? number_format((float)$r['Price'], 0, ',', '.') : 'N/A';
        $lines[] = sprintf('%s %s %s - $%s - Placa: %s',
            $r['Make'] ?? '',
            $r['Model'] ?? '',
            $r['Year'] ?? '',
            $precio,
            $r['LicensePlate'] ?? ''
        );
    }
    return implode("\n", $lines);
}
