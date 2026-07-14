<?php
/**
 * API pública: validar token de solicitud de adjuntos, listar cargados y subir nuevos.
 * Sin sesión. Token válido 24 h, reutilizable hasta que caduque o se revoque.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => motus_mantenimiento_mensaje(), 'maintenance' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function finAdjTok_tablaExiste(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            return $cache = false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = ? AND table_name = 'financiamiento_adjuntos_token'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function finAdjTok_tablaAdjuntosExiste(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            return $cache = false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = ? AND table_name = 'adjuntos_financiamiento_registros'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * @return array{token_id:int,fr_id:int,cliente_nombre:string,expires_at:string}|null
 */
function finAdjTok_resolver(PDO $pdo, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }
    $st = $pdo->prepare("
        SELECT t.id AS token_id, t.expires_at, t.revoked_at,
               fr.id AS fr_id, fr.cliente_nombre
        FROM financiamiento_adjuntos_token t
        INNER JOIN financiamiento_registros fr ON fr.id = t.financiamiento_registro_id
        WHERE t.token = ?
        LIMIT 1
    ");
    $st->execute([strtolower($token)]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (!empty($row['revoked_at'])) {
        return null;
    }
    $exp = strtotime((string) $row['expires_at']);
    if ($exp === false || $exp < time()) {
        return null;
    }
    return [
        'token_id' => (int) $row['token_id'],
        'fr_id' => (int) $row['fr_id'],
        'cliente_nombre' => (string) ($row['cliente_nombre'] ?? ''),
        'expires_at' => (string) $row['expires_at'],
    ];
}

/**
 * @return list<array{id:int,nombre_original:string,tipo_archivo:string,tamano_archivo:?int,fecha_subida:string,descripcion:?string}>
 */
function finAdjTok_listarAdjuntos(PDO $pdo, int $frId): array
{
    if ($frId <= 0 || !finAdjTok_tablaAdjuntosExiste($pdo)) {
        return [];
    }
    $st = $pdo->prepare("
        SELECT id, nombre_original, tipo_archivo, tamano_archivo, fecha_subida, descripcion
        FROM adjuntos_financiamiento_registros
        WHERE financiamiento_registro_id = ?
        ORDER BY fecha_subida DESC, id DESC
    ");
    $st->execute([$frId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'nombre_original' => (string) ($r['nombre_original'] ?? ''),
            'tipo_archivo' => (string) ($r['tipo_archivo'] ?? ''),
            'tamano_archivo' => isset($r['tamano_archivo']) ? (int) $r['tamano_archivo'] : null,
            'fecha_subida' => (string) ($r['fecha_subida'] ?? ''),
            'descripcion' => isset($r['descripcion']) ? (string) $r['descripcion'] : null,
        ];
    }
    return $out;
}

function finAdjTok_finfoMime(string $path): string
{
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') {
            return $m;
        }
    }
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $m = $fi->file($path);
        if (is_string($m) && $m !== '') {
            return $m;
        }
    }
    return 'application/octet-stream';
}

/** @return list<string> */
function finAdjTok_allowedMimes(): array
{
    return [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
}

/**
 * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function finAdjTok_normalizeFiles(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return [[
            'name' => (string) $f['name'],
            'type' => (string) ($f['type'] ?? ''),
            'tmp_name' => (string) $f['tmp_name'],
            'error' => (int) $f['error'],
            'size' => (int) $f['size'],
        ]];
    }
    $out = [];
    $n = count($f['name']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = [
            'name' => (string) $f['name'][$i],
            'type' => (string) ($f['type'][$i] ?? ''),
            'tmp_name' => (string) $f['tmp_name'][$i],
            'error' => (int) $f['error'][$i],
            'size' => (int) $f['size'][$i],
        ];
    }
    return $out;
}

/** @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}> */
function finAdjTok_filesFromRequest(): array
{
    foreach (['adjuntos', 'adjuntos[]', 'files', 'files[]'] as $field) {
        $rows = finAdjTok_normalizeFiles($field);
        if ($rows !== []) {
            return $rows;
        }
    }
    return [];
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Base de datos no disponible.']);
        exit;
    }
    if (!finAdjTok_tablaExiste($pdo)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Función no disponible: ejecute database/migracion_financiamiento_adjuntos_token.sql']);
        exit;
    }

    $token = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
    } else {
        $token = isset($_POST['t']) ? trim((string) $_POST['t']) : '';
        if ($token === '' && isset($_GET['t'])) {
            $token = trim((string) $_GET['t']);
        }
    }

    $ctx = finAdjTok_resolver($pdo, $token);
    if ($ctx === null) {
        http_response_code(410);
        echo json_encode(['success' => false, 'message' => 'El enlace no es válido, fue reemplazado o ya caducó.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => [
                'cliente_nombre' => $ctx['cliente_nombre'],
                'expires_at' => $ctx['expires_at'],
                'adjuntos' => finAdjTok_listarAdjuntos($pdo, $ctx['fr_id']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    if (!finAdjTok_tablaAdjuntosExiste($pdo)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Tabla de adjuntos no disponible.']);
        exit;
    }

    $files = finAdjTok_filesFromRequest();
    if ($files === []) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Seleccione al menos un archivo.']);
        exit;
    }

    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $dirRel = 'adjuntos/solicitudes/';
    $dirAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel);
    if (!is_dir($dirAbs)) {
        @mkdir($dirAbs, 0755, true);
    }

    $allowed = finAdjTok_allowedMimes();
    $frId = $ctx['fr_id'];
    $maxFiles = 15;
    $maxSize = 10 * 1024 * 1024;
    $guardados = 0;
    $rechazados = [];
    $n = 0;

    $ins = $pdo->prepare("
        INSERT INTO adjuntos_financiamiento_registros
        (financiamiento_registro_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, tamano_archivo, descripcion)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($files as $row) {
        if ($n >= $maxFiles) {
            $rechazados[] = ($row['name'] ?: 'archivo') . ' (límite de archivos)';
            continue;
        }
        if ($row['error'] !== UPLOAD_ERR_OK) {
            $rechazados[] = ($row['name'] ?: 'archivo') . ' (error de subida)';
            continue;
        }
        $size = (int) $row['size'];
        if ($size <= 0 || $size > $maxSize) {
            $rechazados[] = ($row['name'] ?: 'archivo') . ' (tamaño no permitido, máx. 10 MB)';
            continue;
        }
        $tmp = $row['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $rechazados[] = ($row['name'] ?: 'archivo') . ' (archivo inválido)';
            continue;
        }
        $orig = $row['name'] !== '' ? basename($row['name']) : 'adjunto.bin';
        $orig = preg_replace('/[^A-Za-z0-9._\\- ]/', '_', $orig);
        if ($orig === '' || strlen($orig) > 200) {
            $orig = 'adjunto.bin';
        }
        $mime = finAdjTok_finfoMime($tmp);
        if (!in_array($mime, $allowed, true)) {
            // fallback por extensión común
            $extLow = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $extMap = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            if (isset($extMap[$extLow]) && in_array($extMap[$extLow], $allowed, true)) {
                $mime = $extMap[$extLow];
            } else {
                $rechazados[] = $orig . ' (tipo no permitido)';
                continue;
            }
        }
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $nombreArchivo = 'finreg_' . $frId . '_' . uniqid('', true) . $ext;
        $abs = rtrim($dirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (!move_uploaded_file($tmp, $abs)) {
            $rechazados[] = $orig . ' (no se pudo guardar)';
            continue;
        }
        $rutaDb = $dirRel . $nombreArchivo;
        try {
            $ins->execute([
                $frId,
                $nombreArchivo,
                $orig,
                $rutaDb,
                $mime,
                $size,
                'Subido por cliente (enlace solicitar adjuntos)',
            ]);
            $guardados++;
            $n++;
        } catch (Throwable $e) {
            @unlink($abs);
            error_log('financiamiento_adjuntos_public insert: ' . $e->getMessage());
            $rechazados[] = $orig . ' (error al registrar)';
        }
    }

    if ($guardados < 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo subir ningún archivo.',
            'rechazados' => $rechazados,
            'data' => ['adjuntos' => finAdjTok_listarAdjuntos($pdo, $frId)],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $msg = $guardados === 1
        ? '1 archivo subido correctamente.'
        : $guardados . ' archivos subidos correctamente.';
    if ($rechazados !== []) {
        $msg .= ' Algunos no se aceptaron.';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'guardados' => $guardados,
        'rechazados' => $rechazados,
        'data' => [
            'cliente_nombre' => $ctx['cliente_nombre'],
            'expires_at' => $ctx['expires_at'],
            'adjuntos' => finAdjTok_listarAdjuntos($pdo, $frId),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('financiamiento_adjuntos_public: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
