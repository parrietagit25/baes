<?php
/**
 * Realtime API (voz): interfaz unificada.
 * Recibe la SDP de oferta WebRTC del navegador, la envía a OpenAI /v1/realtime/calls
 * con la API key y la configuración de sesión (instrucciones MOTUS), devuelve la SDP de respuesta.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/chatbot.php';

$apiKey = defined('CHATBOT_OPENAI_API_KEY') ? CHATBOT_OPENAI_API_KEY : '';
if ($apiKey === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Realtime no configurado (OPENAI_API_KEY)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$sdp = file_get_contents('php://input');
if ($sdp === false || trim($sdp) === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'SDP requerida']);
    exit;
}

$sessionConfig = [
    'type' => 'realtime',
    'model' => 'gpt-realtime',
    'instructions' => getRealtimeInstructions(),
    'audio' => [
        'output' => [
            'voice' => 'alloy'
        ]
    ]
];

if (!function_exists('curl_init')) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL no disponible']);
    exit;
}

$post = [
    'sdp' => $sdp,
    'session' => json_encode($sessionConfig)
];

$ch = curl_init('https://api.openai.com/v1/realtime/calls');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey
    ]
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err !== '' || $response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No se pudo conectar con el servicio de voz']);
    exit;
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    $decoded = json_decode($response, true);
    echo json_encode(['error' => $decoded['error']['message'] ?? $response]);
    exit;
}

header('Content-Type: application/sdp');
echo $response;

function getRealtimeInstructions(): string {
    return <<<TEXT
Eres el asistente de voz de MOTUS, el sistema de Solicitudes de Crédito de Motus/AutoMarket. Hablas en español, de forma clara y breve.
Ayudas con: uso del sistema, solicitudes de crédito, autos disponibles, adjuntos y Pipedrive. Responde de forma concisa para una conversación por voz.
TEXT;
}
