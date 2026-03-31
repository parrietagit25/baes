<?php
/**
 * Diagnóstico de API Pipedrive (respuesta en bruto).
 *
 * Uso:
 *  - /test_pipedrive_api.php
 *  - /test_pipedrive_api.php?limit=20
 *  - /test_pipedrive_api.php?from_year=2026
 *  - /test_pipedrive_api.php?from_year=2026&max_pages=15
 *  - /test_pipedrive_api.php?include_extra=1
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

function isLeadFromYearOrLater(array $lead, int $fromYear): bool {
    $addTime = $lead['add_time'] ?? null;
    if (!$addTime) return false;
    try {
        $dt = new DateTime((string) $addTime);
        return (int) $dt->format('Y') >= $fromYear;
    } catch (Throwable $e) {
        return false;
    }
}

function fetchAllLeads(string $baseUrl, string $token, int $limit, int $start = 0, int $maxPages = 0): array {
    $all = [];
    $pagesScanned = 0;
    $nextStart = $start;
    $more = true;
    while ($more) {
        if ($maxPages > 0 && $pagesScanned >= $maxPages) {
            break;
        }
        $page = pdRequest($baseUrl, $token, '/leads', [
            'limit' => $limit,
            'start' => $nextStart,
            'sort' => 'add_time',
            'sort_direction' => 'desc',
        ]);
        $pagesScanned++;
        if (empty($page['ok'])) {
            return [
                'ok' => false,
                'pages_scanned' => $pagesScanned,
                'error_page' => $page,
                'data' => $all,
            ];
        }
        $leadsPage = $page['response']['data'] ?? [];
        if (is_array($leadsPage) && !empty($leadsPage)) {
            $all = array_merge($all, $leadsPage);
        }
        $pagination = $page['response']['additional_data']['pagination'] ?? [];
        $more = !empty($pagination['more_items_in_collection']);
        $nextStart = isset($pagination['next_start']) ? (int) $pagination['next_start'] : ($nextStart + $limit);
    }
    return [
        'ok' => true,
        'pages_scanned' => $pagesScanned,
        'data' => $all,
    ];
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

$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 100;
$leadId = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
$personId = isset($_GET['person_id']) ? (int) $_GET['person_id'] : 0;
$fromYear = isset($_GET['from_year']) ? (int) $_GET['from_year'] : 0;
$maxPages = isset($_GET['max_pages']) ? max(0, (int) $_GET['max_pages']) : 0;
$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
$allPages = !isset($_GET['all_pages']) || $_GET['all_pages'] !== '0';
$includeExtra = isset($_GET['include_extra']) && $_GET['include_extra'] === '1';

$out = [
    'success' => true,
    'config' => [
        'base_url' => $baseUrl,
        'token_present' => true,
        'token_last4' => substr($token, -4),
        'limit' => $limit,
        'start' => $start,
        'from_year' => $fromYear > 0 ? $fromYear : null,
        'all_pages' => $allPages,
        'max_pages' => $maxPages > 0 ? $maxPages : null,
        'include_extra' => $includeExtra,
    ],
    'requests' => [],
];

$out['requests']['users_me'] = pdRequest($baseUrl, $token, '/users/me');
$out['requests']['leads'] = $allPages
    ? fetchAllLeads($baseUrl, $token, $limit, $start, $maxPages)
    : pdRequest($baseUrl, $token, '/leads', [
        'limit' => $limit,
        'start' => $start,
        'sort' => 'add_time',
        'sort_direction' => 'desc',
    ]);

if ($fromYear > 0 && !empty($out['requests']['leads']['ok'])) {
    $filtered = [];
    $sourceLeads = $allPages
        ? ($out['requests']['leads']['data'] ?? [])
        : ($out['requests']['leads']['response']['data'] ?? []);
    if (is_array($sourceLeads)) {
        foreach ($sourceLeads as $lead) {
            if (is_array($lead) && isLeadFromYearOrLater($lead, $fromYear)) {
                $filtered[] = $lead;
            }
        }
    }

    $out['requests']['leads_from_year'] = [
        'from_year' => $fromYear,
        'pages_scanned' => $out['requests']['leads']['pages_scanned'] ?? 1,
        'max_pages' => $maxPages > 0 ? $maxPages : null,
        'result_count' => count($filtered),
        'data' => $filtered,
    ];
}

if ($leadId > 0) {
    $out['requests']['lead_by_id'] = pdRequest($baseUrl, $token, '/leads/' . $leadId);
}

if ($personId > 0) {
    $out['requests']['person_by_id'] = pdRequest($baseUrl, $token, '/persons/' . $personId);
} else {
    $leadData = $out['requests']['leads_from_year']['data'] ?? ($out['requests']['leads']['response']['data'] ?? []);
    if (is_array($leadData) && !empty($leadData[0]) && is_array($leadData[0])) {
        $autoPersonId = extractPersonIdFromLead($leadData[0]);
        if ($autoPersonId) {
            $out['requests']['person_from_first_lead'] = pdRequest($baseUrl, $token, '/persons/' . $autoPersonId);
            $out['auto_person_id_from_first_lead'] = $autoPersonId;
        }
    }
}

if ($includeExtra) {
    // Endpoints útiles para analizar más contexto de negocio en Pipedrive
    $out['requests']['deals'] = pdRequest($baseUrl, $token, '/deals', [
        'limit' => $limit,
        'start' => $start,
        'sort' => 'update_time',
        'sort_direction' => 'desc',
        'status' => 'open',
    ]);
    $out['requests']['activities'] = pdRequest($baseUrl, $token, '/activities', [
        'limit' => $limit,
        'start' => $start,
        'done' => 0,
    ]);
    $out['requests']['organizations'] = pdRequest($baseUrl, $token, '/organizations', [
        'limit' => $limit,
        'start' => $start,
        'sort' => 'update_time',
    ]);
    $out['requests']['pipelines'] = pdRequest($baseUrl, $token, '/pipelines');
    $out['requests']['stages'] = pdRequest($baseUrl, $token, '/stages');
    $out['requests']['notes'] = pdRequest($baseUrl, $token, '/notes', [
        'limit' => $limit,
        'start' => $start,
        'sort' => 'update_time',
        'sort_direction' => 'desc',
    ]);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
