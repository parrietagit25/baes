<?php
/**
 * Ejecuta tools de la Realtime API: create_credit_request y add_vehicles_to_request.
 * Llama a los endpoints existentes api/solicitudes.php y api/vehiculos_solicitud.php.
 * Requiere sesión activa y permisos de gestor/admin para crear solicitudes.
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
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

$allowedTools = ['create_credit_request', 'add_vehicles_to_request'];
if (!in_array($name, $allowedTools, true)) {
    echo json_encode(['success' => false, 'message' => 'Herramienta no permitida']);
    exit;
}

try {
    if ($name === 'create_credit_request') {
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
    if ($response['code'] !== 200) {
        $msg = is_array($body) && isset($body['message']) ? $body['message'] : 'Error al crear solicitud';
        return ['success' => false, 'message' => $msg];
    }

    if (!is_array($body) || empty($body['success']) || empty($body['data']['id'])) {
        return [
            'success' => false,
            'message' => is_array($body) && isset($body['message']) ? $body['message'] : 'Respuesta inválida del servidor'
        ];
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
    if ($response['code'] !== 200) {
        $msg = is_array($body) && isset($body['message']) ? $body['message'] : 'Error al guardar vehículos';
        return ['success' => false, 'message' => $msg];
    }

    if (!is_array($body) || empty($body['success'])) {
        return [
            'success' => false,
            'message' => is_array($body) && isset($body['message']) ? $body['message'] : 'Respuesta inválida del servidor'
        ];
    }

    return [
        'success' => true,
        'message' => $body['message'] ?? 'Vehículos guardados correctamente'
    ];
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
