<?php
/**
 * Ejecuta tools de la Realtime API: query_autos_disponibles, create_credit_request y add_vehicles_to_request.
 * Consulta inventario (Automarket_Invs_web_temp), crea solicitudes y agrega vehículos usando los endpoints existentes.
 * Requiere sesión activa y permisos de gestor/admin para crear solicitudes.
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (getenv('REALTIME_INTERNAL_BASE_URL') === false || getenv('REALTIME_INTERNAL_BASE_URL') === '') {
    $envFile = __DIR__ . '/../.env';
    if (is_file($envFile) && is_readable($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue;
                if (preg_match('/^REALTIME_INTERNAL_BASE_URL=(.*)$/', $line, $m)) {
                    $val = trim($m[1]);
                    if (preg_match('/^["\'](.+)["\']$/', $val, $q)) $val = $q[1];
                    if ($val !== '' && getenv('REALTIME_INTERNAL_BASE_URL') === false) {
                        putenv('REALTIME_INTERNAL_BASE_URL=' . $val);
                    }
                    break;
                }
            }
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!motus_chatbot_habilitado()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'El asistente de IA está deshabilitado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$name = isset($input['name']) ? trim((string) $input['name']) : '';
$arguments = isset($input['arguments']) ? $input['arguments'] : [];

if (is_string($arguments)) {
    $arguments = json_decode($arguments, true);
    if (!is_array($arguments)) {
        $arguments = [];
    }
}

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Nombre de herramienta requerido']);
    exit;
}

$allowedTools = ['query_autos_disponibles', 'create_credit_request', 'add_vehicles_to_request'];
if (!in_array($name, $allowedTools, true)) {
    echo json_encode(['success' => false, 'message' => 'Herramienta no permitida']);
    exit;
}

try {
    if ($name === 'query_autos_disponibles') {
        $result = executeQueryAutosDisponibles($arguments);
    } elseif ($name === 'create_credit_request') {
        $result = executeCreateCreditRequest($arguments);
    } else {
        $result = executeAddVehiclesToRequest($arguments);
    }
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('realtime_execute_tool: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar la acción. Intenta de nuevo.'
    ]);
}

/**
 * Consulta inventario de autos (Automarket_Invs_web_temp). Devuelve total, opcionalmente filtrado por marca, y listado breve.
 */
function executeQueryAutosDisponibles(array $args): array {
    global $pdo;

    $marca = isset($args['marca']) ? trim((string) $args['marca']) : '';
    $soloCantidad = !empty($args['solo_cantidad']);
    $limite = isset($args['limite']) && is_numeric($args['limite']) ? max(1, min(50, (int) $args['limite'])) : 15;

    $table = 'Automarket_Invs_web_temp';
    try {
        if ($marca !== '') {
            $countSql = "SELECT COUNT(*) AS total FROM `$table` WHERE Make = ?";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute([$marca]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `$table`");
        }
        $total = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('query_autos_disponibles: ' . $e->getMessage());
        return ['success' => false, 'message' => 'No se pudo consultar el inventario.', 'total' => 0];
    }

    $out = [
        'success' => true,
        'total' => $total,
    ];
    if ($marca !== '') {
        $out['marca'] = $marca;
    }

    if ($soloCantidad) {
        return $out;
    }

    $cols = 'Make, Model, Year, Price';
    if ($marca !== '') {
        $sql = "SELECT $cols FROM `$table` WHERE Make = ? ORDER BY Model, Year DESC LIMIT " . $limite;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$marca]);
    } else {
        $sql = "SELECT $cols FROM `$table` ORDER BY Make, Model, Year DESC LIMIT " . $limite;
        $stmt = $pdo->query($sql);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unidades = [];
    foreach ($rows as $r) {
        $precio = isset($r['Price']) && $r['Price'] !== null ? (float) $r['Price'] : null;
        $unidades[] = [
            'marca' => $r['Make'] ?? '',
            'modelo' => $r['Model'] ?? '',
            'anio' => isset($r['Year']) ? (int) $r['Year'] : null,
            'precio' => $precio
        ];
    }
    $out['unidades'] = $unidades;
    return $out;
}

/**
 * Llama a api/solicitudes.php (POST) para crear la solicitud.
 * Retorna objeto con success, message y opcionalmente solicitud_id.
 */
function executeCreateCreditRequest(array $args): array {
    if (!isset($_SESSION['user_roles']) || (
        !in_array('ROLE_GESTOR', $_SESSION['user_roles']) &&
        !in_array('ROLE_ADMIN', $_SESSION['user_roles'])
    )) {
        return [
            'success' => false,
            'message' => 'Solo gestores y administradores pueden crear solicitudes.'
        ];
    }

    $tipoPersona = sanitizeEnum($args['tipo_persona'] ?? '', ['Natural', 'Juridica']);
    $nombreCliente = sanitizeString($args['nombre_cliente'] ?? '', 255);
    $cedula = sanitizeString($args['cedula'] ?? '', 50);
    $perfilFinanciero = sanitizeEnum($args['perfil_financiero'] ?? '', ['Asalariado', 'Jubilado', 'Independiente']);

    if ($tipoPersona === '' || $nombreCliente === '' || $cedula === '' || $perfilFinanciero === '') {
        return [
            'success' => false,
            'message' => 'Faltan datos obligatorios: tipo_persona, nombre_cliente, cedula, perfil_financiero.'
        ];
    }

    $postData = [
        'tipo_persona' => $tipoPersona,
        'nombre_cliente' => $nombreCliente,
        'cedula' => $cedula,
        'perfil_financiero' => $perfilFinanciero
    ];

    $url = getBaseUrl() . '/api/solicitudes.php';
    $response = httpPostWithSession($url, $postData);

    $body = json_decode($response['body'], true);
    $code = $response['code'];
    $rawBody = $response['body'];

    if ($code !== 200) {
        $backendMsg = buildBackendErrorMessage($code, $body, $rawBody, $url, 'create_credit_request');
        return ['success' => false, 'message' => $backendMsg];
    }

    if (!is_array($body) || empty($body['success']) || empty($body['data']['id'])) {
        $backendMsg = isCloudflareChallenge($rawBody)
            ? getCloudflareBypassMessage()
            : (is_array($body) && isset($body['message']) ? $body['message'] : 'Respuesta inválida del servidor (no se recibió ID de solicitud).');
        return ['success' => false, 'message' => $backendMsg];
    }

    return [
        'success' => true,
        'message' => $body['message'] ?? 'Solicitud creada correctamente',
        'solicitud_id' => (int) $body['data']['id']
    ];
}

/**
 * Llama a api/vehiculos_solicitud.php (POST) para agregar vehículos.
 */
function executeAddVehiclesToRequest(array $args): array {
    $solicitudId = isset($args['solicitud_id']) ? (int) $args['solicitud_id'] : 0;
    if ($solicitudId <= 0) {
        return ['success' => false, 'message' => 'solicitud_id es requerido y debe ser mayor a 0.'];
    }

    $vehiculos = isset($args['vehiculos']) && is_array($args['vehiculos']) ? $args['vehiculos'] : [];

    $normalized = [];
    foreach ($vehiculos as $v) {
        $normalized[] = [
            'marca' => sanitizeString($v['marca'] ?? '', 100),
            'modelo' => sanitizeString($v['modelo'] ?? '', 100),
            'anio' => is_numeric($v['anio'] ?? null) ? (int) $v['anio'] : null,
            'kilometraje' => is_numeric($v['kilometraje'] ?? null) ? (int) $v['kilometraje'] : null,
            'precio' => is_numeric($v['precio'] ?? null) ? (float) $v['precio'] : null,
            'abono_porcentaje' => is_numeric($v['abono_porcentaje'] ?? null) ? (float) $v['abono_porcentaje'] : null,
            'abono_monto' => is_numeric($v['abono_monto'] ?? null) ? (float) $v['abono_monto'] : null
        ];
    }

    $postData = [
        'solicitud_id' => $solicitudId,
        'vehiculos' => json_encode($normalized)
    ];

    $url = getBaseUrl() . '/api/vehiculos_solicitud.php';
    $response = httpPostWithSession($url, $postData);

    $body = json_decode($response['body'], true);
    $rawBody = $response['body'];
    if ($response['code'] !== 200) {
        $msg = buildBackendErrorMessage($response['code'], $body, $rawBody, $url, 'add_vehicles');
        return ['success' => false, 'message' => $msg];
    }

    if (!is_array($body) || empty($body['success'])) {
        $msg = isCloudflareChallenge($rawBody) ? getCloudflareBypassMessage() : (is_array($body) && isset($body['message']) ? $body['message'] : 'Respuesta inválida del servidor');
        return ['success' => false, 'message' => $msg];
    }

    return [
        'success' => true,
        'message' => $body['message'] ?? 'Vehículos guardados correctamente'
    ];
}

function isCloudflareChallenge(string $body): bool {
    return strpos($body, 'Just a moment') !== false
        || strpos($body, 'cf_chl_') !== false
        || strpos($body, 'challenge-platform') !== false;
}

function getCloudflareBypassMessage(): string {
    return 'Cloudflare está interceptando la petición interna. Configura REALTIME_INTERNAL_BASE_URL con la URL directa de tu servidor (sin pasar por Cloudflare), por ejemplo la IP interna o localhost.';
}

function buildBackendErrorMessage(int $code, $body, string $rawBody, string $url, string $context): string {
    if (isCloudflareChallenge($rawBody)) {
        return getCloudflareBypassMessage();
    }
    $backendMsg = is_array($body) && isset($body['message']) ? $body['message'] : trim($rawBody);
    if ($backendMsg === '' && $code === 401) {
        $backendMsg = 'Sesión no válida en la petición interna. Comprueba que la aplicación esté accesible con la misma URL y que las cookies de sesión se envíen correctamente.';
    } elseif ($backendMsg === '' || strlen($backendMsg) > 500) {
        $backendMsg = 'El servidor respondió con código HTTP ' . $code . '. Revisa los logs del servidor.';
    }
    error_log('realtime_execute_tool ' . $context . ': HTTP ' . $code . ' URL=' . $url . ' body=' . substr($rawBody, 0, 300));
    return $backendMsg;
}

function sanitizeString(string $v, int $maxLen): string {
    $v = trim(preg_replace('/\s+/', ' ', $v));
    return mb_substr($v, 0, $maxLen);
}

function sanitizeEnum(string $v, array $allowed): string {
    $v = trim($v);
    return in_array($v, $allowed, true) ? $v : '';
}

function getBaseUrl(): string {
    // Si está definida, usar URL interna (útil en Docker/proxy para que el POST vaya al mismo backend y conserve la sesión)
    $env = getenv('REALTIME_INTERNAL_BASE_URL') ?: (defined('REALTIME_INTERNAL_BASE_URL') ? REALTIME_INTERNAL_BASE_URL : '');
    if ($env !== '') {
        return rtrim($env, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = dirname($script, 2);
    $base = str_replace('\\', '/', $base);
    if ($base === '/' || $base === '') {
        $base = '';
    }
    return $scheme . '://' . $host . $base;
}

/**
 * POST a una URL interna con la sesión PHP actual (cookie).
 * Devuelve ['code' => int, 'body' => string].
 */
function httpPostWithSession(string $url, array $postData): array {
    $cookie = session_name() . '=' . session_id();
    $body = http_build_query($postData);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Cookie: ' . $cookie
        ],
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $responseBody !== false ? $responseBody : ''];
}
