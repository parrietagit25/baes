<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set('America/Panama');

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getApiUrl(): string {
    if (defined('FINANCIAMIENTO_API_URL') && FINANCIAMIENTO_API_URL !== '') {
        return FINANCIAMIENTO_API_URL;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/financiamiento')), '/');
    $base = preg_replace('#/financiamiento$#', '', $dir) ?: '';
    return $scheme . '://' . $host . $base . '/api/solicitud_publica.php';
}

/**
 * Candidatos para llamar la API evitando Cloudflare cuando sea posible.
 *
 * @return string[]
 */
function getApiUrlCandidates(?string $selected = null): array {
    $out = [];
    $baseSelected = trim((string)$selected);
    if ($baseSelected !== '') {
        $out[] = $baseSelected;
        if (stripos($baseSelected, 'http://') === 0) {
            $out[] = 'https://' . substr($baseSelected, 7);
        }
    } else {
        $out[] = getApiUrl();
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/financiamiento/debug_adjuntos_publico.php');
    $dir = rtrim(dirname($scriptName), '/');
    $base = preg_replace('#/financiamiento$#', '', $dir) ?: '';
    $path = $base . '/api/solicitud_publica.php';

    $loopbacks = [
        'http://127.0.0.1' . $path,
        'http://localhost' . $path,
    ];
    $serverAddr = trim((string)($_SERVER['SERVER_ADDR'] ?? ''));
    if ($serverAddr !== '' && filter_var($serverAddr, FILTER_VALIDATE_IP)) {
        $loopbacks[] = 'http://' . $serverAddr . $path;
    }
    foreach ($loopbacks as $u) {
        $out[] = $u;
    }

    $uniq = [];
    $final = [];
    foreach ($out as $u) {
        $k = strtolower(trim($u));
        if ($k === '' || isset($uniq[$k])) continue;
        $uniq[$k] = true;
        $final[] = trim($u);
    }
    return $final;
}

function looksLikeCloudflareChallenge(string $body): bool {
    $b = strtolower($body);
    return strpos($b, 'just a moment') !== false
        || strpos($b, 'challenges.cloudflare.com') !== false
        || strpos($b, '__cf_chl') !== false
        || strpos($b, 'enable javascript and cookies to continue') !== false;
}

function normalizeUploadRows(string $field): array {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return [[
            'name' => (string)$f['name'],
            'type' => (string)($f['type'] ?? ''),
            'tmp_name' => (string)$f['tmp_name'],
            'error' => (int)$f['error'],
            'size' => (int)$f['size'],
        ]];
    }
    $out = [];
    $n = count($f['name']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = [
            'name' => (string)$f['name'][$i],
            'type' => (string)($f['type'][$i] ?? ''),
            'tmp_name' => (string)$f['tmp_name'][$i],
            'error' => (int)$f['error'][$i],
            'size' => (int)$f['size'][$i],
        ];
    }
    return $out;
}

function iniBytes(string $value): int {
    $v = trim($value);
    if ($v === '') return 0;
    $unit = strtolower(substr($v, -1));
    $num = (float)$v;
    switch ($unit) {
        case 'g': return (int)($num * 1024 * 1024 * 1024);
        case 'm': return (int)($num * 1024 * 1024);
        case 'k': return (int)($num * 1024);
        default: return (int)$num;
    }
}

function findAdjuntosByMarker(string $markerCedula): array {
    $res = [
        'ok' => false,
        'error' => null,
        'solicitudes' => [],
        'adjuntos' => [],
    ];
    $dbPath = __DIR__ . '/../config/database.php';
    if (!is_file($dbPath)) {
        $res['error'] = 'No existe config/database.php';
        return $res;
    }
    try {
        require $dbPath;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $res['error'] = 'No se pudo obtener PDO desde config/database.php';
            return $res;
        }
        $stmt = $pdo->prepare("
            SELECT id, fecha_creacion, nombre_cliente, cedula, email
            FROM solicitudes_credito
            WHERE REPLACE(REPLACE(UPPER(COALESCE(cedula,'')),'-',''),' ','') = REPLACE(REPLACE(UPPER(?),'-',''),' ','')
            ORDER BY id DESC
            LIMIT 5
        ");
        $stmt->execute([$markerCedula]);
        $sols = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $res['solicitudes'] = $sols;

        $adj = [];
        foreach ($sols as $s) {
            $sid = (int)($s['id'] ?? 0);
            if ($sid <= 0) continue;
            $sa = $pdo->prepare("
                SELECT id, solicitud_id, nombre_original, ruta_archivo, tipo_archivo, fecha_subida
                FROM adjuntos_solicitud
                WHERE solicitud_id = ?
                ORDER BY id DESC
            ");
            $sa->execute([$sid]);
            $rows = $sa->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $adj[] = $r;
            }
        }
        $res['adjuntos'] = $adj;
        $res['ok'] = true;
    } catch (Throwable $e) {
        $res['error'] = $e->getMessage();
    }
    return $res;
}

$apiUrl = getApiUrl();
$result = null;
$uploadRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEmail = trim((string)($_POST['token_email'] ?? ''));
    $clienteEmail = trim((string)($_POST['cliente_email'] ?? ''));
    $apiUrl = trim((string)($_POST['api_url'] ?? $apiUrl));
    $uploadRows = normalizeUploadRows('adjuntos_extra');
    $ts = time();
    $markerCedula = 'DBG-' . $ts;
    $markerNombre = 'Debug Cliente ' . $ts;
    $token = '';
    if ($tokenEmail !== '') {
        $token = rtrim(strtr(base64_encode($tokenEmail), '+/', '-_'), '=');
    }

    $payload = [
        'cliente_nombre' => $markerNombre,
        'cliente_estado_civil' => 'Soltero/a',
        'cliente_sexo' => 'M',
        'cliente_id' => $markerCedula,
        'cliente_nacimiento' => '01/01/1990',
        'cliente_edad' => 35,
        'cliente_nacionalidad' => 'Panamena',
        'cliente_dependientes' => 0,
        'cliente_correo' => $clienteEmail !== '' ? $clienteEmail : ('debug+' . $ts . '@example.com'),
        'cliente_peso' => 170,
        'cliente_estatura' => 1.75,
        'vivienda' => 'Familiar',
        'vivienda_monto' => 0,
        'provincia' => 'Panama',
        'distrito' => 'Panama',
        'corregimiento' => 'Bella Vista',
        'tel_residencia' => '2233-4455',
        'barriada' => 'Debug',
        'celular_cliente' => '6123-4567',
        'casa_edif' => 'Casa',
        'numero_casa_apto' => 'DBG-1',
        'direccion' => 'Direccion debug',
        'correo_residencial' => $clienteEmail !== '' ? $clienteEmail : null,
        'empresa_nombre' => 'Empresa Debug',
        'empresa_ocupacion' => 'QA',
        'empresa_anios' => '3',
        'empresa_telefono' => '3000-1111',
        'empresa_salario' => 1200,
        'empresa_direccion' => 'Direccion laboral debug',
        'otros_ingresos' => 'No',
        'ocupacion_otros' => '',
        'trabajo_anterior' => '',
        'tiene_conyuge' => 0,
        'refp1_nombre' => 'Ref P1',
        'refp1_cel' => '6333-1000',
        'refp2_nombre' => 'Ref P2',
        'refp2_cel' => '6333-2000',
        'reff1_nombre' => 'Ref F1',
        'reff1_cel' => '6444-1000',
        'reff2_nombre' => 'Ref F2',
        'reff2_cel' => '6444-2000',
        'marca_auto' => 'Toyota',
        'modelo_auto' => 'Corolla',
        'anio_auto' => 2020,
        'kms_cod_auto' => 20000,
        'precio_venta' => 15000,
        'abono' => 2000,
        'sucursal' => 'Debug',
        'nombre_gestor' => 'Debug',
        'comentarios_gestor' => 'DEBUG adjuntos desde debug_adjuntos_publico.php',
        'firma' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAusB9WnR0mQAAAAASUVORK5CYII=',
    ];
    if ($token !== '') {
        $payload['token'] = $token;
    }

    $diag = [
        'api_url' => $apiUrl,
        'php_upload_max_filesize' => ini_get('upload_max_filesize'),
        'php_post_max_size' => ini_get('post_max_size'),
        'php_max_file_uploads' => ini_get('max_file_uploads'),
        'php_upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'php_file_uploads' => ini_get('file_uploads'),
        'php_curl' => function_exists('curl_init') ? 'yes' : 'no',
        'adjuntos_dir' => realpath(__DIR__ . '/../adjuntos/solicitudes') ?: (__DIR__ . '/../adjuntos/solicitudes'),
        'adjuntos_dir_writable' => is_writable(__DIR__ . '/../adjuntos/solicitudes') ? 'yes' : 'no',
    ];

    $totalUploadSize = 0;
    foreach ($uploadRows as $row) {
        if ($row['error'] === UPLOAD_ERR_OK) {
            $totalUploadSize += (int)$row['size'];
        }
    }
    $diag['incoming_files_ok'] = count(array_filter($uploadRows, static function ($r) {
        return (int)$r['error'] === UPLOAD_ERR_OK;
    }));
    $diag['incoming_files_total'] = count($uploadRows);
    $diag['incoming_total_size_bytes'] = $totalUploadSize;
    $diag['incoming_total_size_vs_post_max'] = $totalUploadSize <= iniBytes((string)ini_get('post_max_size')) ? 'ok' : 'excede_post_max_size';

    $apiHttpCode = 0;
    $apiRaw = '';
    $apiErr = null;
    $apiJson = null;
    $sentFileNames = [];
    $apiTriedUrls = [];
    $apiFinalUrl = $apiUrl;

    if (function_exists('curl_init')) {
        $apiCandidates = getApiUrlCandidates($apiUrl);
        foreach ($apiCandidates as $tryUrl) {
            $apiTriedUrls[] = $tryUrl;
            $postFields = [
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ];
            foreach ($uploadRows as $idx => $row) {
                if ($row['error'] !== UPLOAD_ERR_OK || !is_file($row['tmp_name'])) {
                    continue;
                }
                $postFields['adjuntos_extra[' . $idx . ']'] = curl_file_create(
                    $row['tmp_name'],
                    $row['type'] ?: 'application/octet-stream',
                    $row['name'] ?: ('debug_' . $idx)
                );
                $sentFileNames[] = $row['name'];
            }

            $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'DebugAdjuntos/1.0'));
            $cookie = trim((string)($_SERVER['HTTP_COOKIE'] ?? ''));
            $headers = [
                'Accept: application/json, text/plain, */*',
                'User-Agent: ' . ($ua !== '' ? $ua : 'DebugAdjuntos/1.0'),
            ];
            if ($cookie !== '') {
                $headers[] = 'Cookie: ' . $cookie;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $tryUrl,
                CURLOPT_POST => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POSTREDIR => 7, // CURL_REDIR_POST_ALL: mantiene POST en redirecciones
                CURLOPT_MAXREDIRS => 8,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $apiRaw = (string)curl_exec($ch);
            $apiHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $apiFinalUrl = $tryUrl;
            if ($apiRaw === '' && curl_errno($ch)) {
                $apiErr = 'cURL error #' . curl_errno($ch) . ': ' . curl_error($ch);
            } else {
                $apiErr = null;
            }
            curl_close($ch);

            $blockedByCf = ($apiHttpCode === 403 && looksLikeCloudflareChallenge($apiRaw));
            $okHttp = ($apiHttpCode >= 200 && $apiHttpCode < 300);
            if ($okHttp || !$blockedByCf) {
                break;
            }
        }
    } else {
        $apiErr = 'cURL no disponible en PHP.';
    }

    if ($apiRaw !== '') {
        $tmp = json_decode($apiRaw, true);
        if (is_array($tmp)) {
            $apiJson = $tmp;
        }
    }

    $dbLookup = findAdjuntosByMarker($markerCedula);

    $result = [
        'marker' => [
            'cliente_nombre' => $markerNombre,
            'cliente_id' => $markerCedula,
            'cliente_correo' => $payload['cliente_correo'],
        ],
        'diag' => $diag,
        'api_tried_urls' => $apiTriedUrls,
        'api_final_url' => $apiFinalUrl,
        'sent_files' => $sentFileNames,
        'api_http_code' => $apiHttpCode,
        'api_error' => $apiErr,
        'api_json' => $apiJson,
        'api_raw' => $apiRaw,
        'db_lookup' => $dbLookup,
        'upload_rows' => $uploadRows,
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Debug adjuntos formulario público</title>
  <style>
    body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 18px; }
    .wrap { max-width: 1000px; margin: 0 auto; }
    .card { background: #111827; border: 1px solid #334155; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
    h1 { margin: 0 0 8px; font-size: 22px; }
    h2 { margin: 0 0 8px; font-size: 16px; color: #93c5fd; }
    label { display: block; margin-bottom: 4px; font-size: 13px; color: #cbd5e1; }
    input, button { width: 100%; padding: 9px; border: 1px solid #475569; border-radius: 8px; background: #0b1220; color: #e2e8f0; }
    button { background: #1d4ed8; border-color: #1d4ed8; cursor: pointer; font-weight: 700; }
    .grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0,1fr)); }
    .full { grid-column: 1 / -1; }
    pre { white-space: pre-wrap; word-break: break-word; background: #020617; border: 1px solid #334155; border-radius: 8px; padding: 10px; font-size: 12px; }
    .ok { color: #86efac; }
    .bad { color: #fda4af; }
    .muted { color: #94a3b8; font-size: 13px; }
    @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Debug adjuntos (formulario público)</h1>
      <p class="muted">Envía al mismo endpoint `solicitud_publica.php`, muestra errores y verifica en BD dónde quedaron guardados los adjuntos.</p>
    </div>

    <form method="post" enctype="multipart/form-data" class="card">
      <h2>Datos de envío</h2>
      <div class="grid">
        <div class="full">
          <label for="api_url">API URL</label>
          <input id="api_url" name="api_url" value="<?php echo h($apiUrl); ?>" />
        </div>
        <div>
          <label for="token_email">Email vendedor (token)</label>
          <input id="token_email" name="token_email" type="email" placeholder="vendedor@dominio.com" />
        </div>
        <div>
          <label for="cliente_email">Email cliente</label>
          <input id="cliente_email" name="cliente_email" type="email" placeholder="cliente@dominio.com" />
        </div>
        <div class="full">
          <label for="adjuntos_extra">Adjuntos extra (múltiple)</label>
          <input id="adjuntos_extra" name="adjuntos_extra[]" type="file" multiple />
        </div>
        <div class="full">
          <button type="submit">Enviar prueba debug</button>
        </div>
      </div>
    </form>

    <?php if ($result !== null): ?>
      <div class="card">
        <h2>Resultado API</h2>
        <p>HTTP: <strong class="<?php echo ($result['api_http_code'] >= 200 && $result['api_http_code'] < 300) ? 'ok' : 'bad'; ?>"><?php echo h((string)$result['api_http_code']); ?></strong></p>
        <?php if (!empty($result['api_error'])): ?>
          <p class="bad"><strong>Error:</strong> <?php echo h((string)$result['api_error']); ?></p>
        <?php endif; ?>
        <pre><?php echo h(json_encode($result['api_json'] ?? ['raw' => $result['api_raw']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
      </div>

      <div class="card">
        <h2>Diagnóstico técnico</h2>
        <pre><?php echo h(json_encode($result['diag'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        <pre><?php echo h(json_encode([
          'marker' => $result['marker'],
          'sent_files' => $result['sent_files'],
          'upload_rows' => $result['upload_rows'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
      </div>

      <div class="card">
        <h2>Verificación en BD y ruta de guardado</h2>
        <pre><?php echo h(json_encode($result['db_lookup'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>