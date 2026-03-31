<?php
/**
 * Diagnóstico de API Pipedrive (respuesta en bruto).
 *
 * Uso:
 *  - /test_pipedrive_api.php
 *  - /test_pipedrive_api.php?limit=20
 *  - /test_pipedrive_api.php?lead_id=123
 *  - /test_pipedrive_api.php?person_id=456
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    // En navegador, comportarse como el resto del sistema (redirigir a login)
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
        header('Location: /index.php');
        exit;
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles, true);
$isGestor = in_array('ROLE_GESTOR', $userRoles, true);
if (!$isAdmin && !$isGestor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para este diagnóstico']);
    exit;
}

function loadDotEnvIfNeeded(): void {
    if ((getenv('PIPEDRIVE_API_KEY') !== false && getenv('PIPEDRIVE_API_KEY') !== '') || (getenv('PIPEDRIVE_BASE_URL') !== false && getenv('PIPEDRIVE_BASE_URL') !== '')) {
        return;
    }
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) continue;
        $key = trim($m[1]);
        $val = trim($m[2]);
        if (strpos($val, '"') === 0 && substr($val, -1) === '"') {
            $val = substr($val, 1, -1);
        } elseif (strpos($val, "'") === 0 && substr($val, -1) === "'") {
            $val = substr($val, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

function pdRequest(string $baseUrl, string $token, string $path, array $query = []): array {
    $query['api_token'] = $token;
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $url .= '?' . http_build_query($query);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'url' => $url, 'http_code' => $http, 'error' => $err];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok' => $http === 200 && is_array($decoded) && !empty($decoded['success']),
        'url' => $url,
        'http_code' => $http,
        'response' => $decoded,
    ];
}

function extractPersonIdFromLead(array $lead): ?int {
    if (!isset($lead['person_id'])) return null;
    $p = $lead['person_id'];
    if (is_numeric($p)) return (int) $p;
    if (is_array($p) && isset($p['value']) && is_numeric($p['value'])) return (int) $p['value'];
    if (is_array($p) && isset($p['id']) && is_numeric($p['id'])) return (int) $p['id'];
    return null;
}

loadDotEnvIfNeeded();
$token = trim((string) (getenv('PIPEDRIVE_API_KEY') ?: ($_ENV['PIPEDRIVE_API_KEY'] ?? '')));
$baseUrl = rtrim((string) (getenv('PIPEDRIVE_BASE_URL') ?: ($_ENV['PIPEDRIVE_BASE_URL'] ?? 'https://grupopcr.pipedrive.com/api/v1')), '/');

if ($token === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falta PIPEDRIVE_API_KEY en .env o variables de entorno'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 10;
$leadId = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
$personId = isset($_GET['person_id']) ? (int) $_GET['person_id'] : 0;

$out = [
    'success' => true,
    'config' => [
        'base_url' => $baseUrl,
        'token_present' => true,
        'token_last4' => substr($token, -4),
        'limit' => $limit,
    ],
    'requests' => [],
];

$out['requests']['users_me'] = pdRequest($baseUrl, $token, '/users/me');
$out['requests']['leads'] = pdRequest($baseUrl, $token, '/leads', [
    'limit' => $limit,
    'sort' => 'add_time',
    'sort_direction' => 'desc',
]);

if ($leadId > 0) {
    $out['requests']['lead_by_id'] = pdRequest($baseUrl, $token, '/leads/' . $leadId);
}

if ($personId > 0) {
    $out['requests']['person_by_id'] = pdRequest($baseUrl, $token, '/persons/' . $personId);
} else {
    $leadData = $out['requests']['leads']['response']['data'] ?? [];
    if (is_array($leadData) && !empty($leadData[0]) && is_array($leadData[0])) {
        $autoPersonId = extractPersonIdFromLead($leadData[0]);
        if ($autoPersonId) {
            $out['requests']['person_from_first_lead'] = pdRequest($baseUrl, $token, '/persons/' . $autoPersonId);
            $out['auto_person_id_from_first_lead'] = $autoPersonId;
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
