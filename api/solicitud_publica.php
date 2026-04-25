<?php
/**
 * API pública para crear solicitudes desde el formulario externo (sin login).
 * Solo acepta POST para crear una solicitud.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido',
        'error_detail' => 'Se recibió ' . ($_SERVER['REQUEST_METHOD'] ?? 'vacío') . '. Este endpoint solo acepta POST.'
    ]);
    exit();
}

$logFile = '/tmp/solicitud_publica_baes_log.txt';
$debugMode = (getenv('SOLICITUD_PUBLICA_DEBUG') === '1' || getenv('APP_DEBUG') === '1');
function logSolPub($msg) {
    @file_put_contents($GLOBALS['logFile'], date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function sendError500($message, $detail = null) {
    $payload = ['success' => false, 'message' => $message];
    if ($detail !== null) {
        $payload['error_detail'] = $detail;
    }
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode($payload);
    exit();
}

set_exception_handler(function (Throwable $e) {
    logSolPub('UNCAUGHT: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sendError500(
        'Error inesperado en el servidor.',
        $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
    );
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

logSolPub('start');

// JSON en body, o multipart con campo "payload" (JSON) + archivos adjuntos_extra[]
$input = $_POST;
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'multipart/form-data') !== false && isset($_POST['payload']) && is_string($_POST['payload'])) {
    $decoded = json_decode($_POST['payload'], true);
    $input = is_array($decoded) ? $decoded : [];
} elseif (empty($input) || !is_array($input)) {
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    $input = is_array($decoded) ? $decoded : [];
}

// Quitar __meta del payload si existe
unset($input['__meta']);
$cedulaImagenDataUrl = isset($input['imagen_cedula']) ? trim((string)$input['imagen_cedula']) : '';
if ($cedulaImagenDataUrl === '') {
    $cedulaImagenDataUrl = null;
}
unset($input['imagen_cedula']);
$token = isset($input['token']) ? trim($input['token']) : '';
$token = $token !== '' ? urldecode($token) : '';
$firmaBase64 = isset($input['firma']) ? $input['firma'] : '';
unset($input['token'], $input['firma']);

// Validación mínima (no depende de base de datos)
$nombre = trim($input['cliente_nombre'] ?? $input['nombre_cliente'] ?? '');
$cedula = trim($input['cliente_id'] ?? $input['cedula'] ?? '');
if ($nombre === '' || $cedula === '') {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Nombre del cliente y cédula son obligatorios']);
    exit();
}

// Compatibilidad: si llegan campos de dirección estilo Motus, consolidarlos al formato legacy.
if (empty($input['prov_dist_corr'])) {
    $provincia = trim((string)($input['provincia'] ?? ''));
    $distrito = trim((string)($input['distrito'] ?? ''));
    $corregimiento = trim((string)($input['corregimiento'] ?? ''));
    $input['prov_dist_corr'] = implode(', ', array_values(array_filter([$provincia, $distrito, $corregimiento], function($x){ return $x !== ''; })));
}
if (empty($input['barriada_calle_casa'])) {
    $barriada = trim((string)($input['barriada'] ?? ''));
    $calle = trim((string)($input['calle'] ?? ''));
    $direccion = trim((string)($input['direccion'] ?? ''));
    $input['barriada_calle_casa'] = implode(' - ', array_values(array_filter([$barriada, $calle, $direccion], function($x){ return $x !== ''; })));
}
if (empty($input['edificio_apto'])) {
    $casaEdif = trim((string)($input['casa_edif'] ?? ''));
    $numeroCasaApto = trim((string)($input['numero_casa_apto'] ?? ''));
    $input['edificio_apto'] = implode(', ', array_values(array_filter([$casaEdif, $numeroCasaApto], function($x){ return $x !== ''; })));
}

function getDefaultGestorId($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.activo = 1 AND r.nombre IN ('ROLE_ADMIN', 'ROLE_GESTOR')
            ORDER BY r.nombre = 'ROLE_ADMIN' DESC, u.id ASC
            LIMIT 1
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) return (int)$row['id'];
    } catch (PDOException $e) {
        // Si no existe tabla usuario_roles, usar fallback
    }
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE activo = 1 ORDER BY id ASC LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id'] : null;
}

function mapGenero($v) {
    if (empty($v)) return null;
    if (strtoupper($v) === 'M') return 'Masculino';
    if (strtoupper($v) === 'F') return 'Femenino';
    return 'Otro';
}

function toNum($v, $default = null) {
    if ($v === '' || $v === null) return $default;
    return is_numeric($v) ? (float)$v : $default;
}

function toInt($v, $default = null) {
    if ($v === '' || $v === null) return $default;
    return is_numeric($v) ? (int)$v : $default;
}

/**
 * Normaliza fechas de entrada a Y-m-d.
 * Acepta:
 * - Y-m-d
 * - m/d/Y (formulario público actual)
 * - d/m/Y (fallback)
 */
function normalizeDateToSql($v) {
    $x = trim((string)$v);
    if ($x === '') return null;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $x)) {
        $dt = DateTime::createFromFormat('Y-m-d', $x);
        return ($dt && $dt->format('Y-m-d') === $x) ? $x : null;
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $x)) {
        $m = DateTime::createFromFormat('m/d/Y', $x);
        if ($m && $m->format('m/d/Y') === $x) return $m->format('Y-m-d');

        $d = DateTime::createFromFormat('d/m/Y', $x);
        if ($d && $d->format('d/m/Y') === $x) return $d->format('Y-m-d');
    }

    return null;
}

/** Columna de tamaño en adjuntos_solicitud (nombre puede variar por instalación). */
function solPub_adjuntos_tamano_column(PDO $pdo): ?string {
    static $col = '__unset__';
    if ($col !== '__unset__') {
        return $col ?: null;
    }
    $col = null;
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'adjuntos_solicitud'
              AND COLUMN_NAME IN ('tamaño_archivo','tamano_archivo','tamao_archivo')
            LIMIT 1
        ");
        $stmt->execute([$dbName]);
        $found = $stmt->fetchColumn();
        $col = $found ?: null;
    } catch (Throwable $e) {
        $col = null;
    }
    return $col;
}

function solPub_finfo_mime(string $path): string {
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

/** @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}> */
function solPub_normalize_uploaded_files(string $field): array {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    // Con un solo archivo, PHP usa name/type/tmp_name como escalares; con varios, como arrays.
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

/** Filas de subida: prueba adjuntos_extra y cualquier clave FILES que empiece por adjuntos_extra. */
function solPub_upload_rows_from_request(): array {
    foreach (['adjuntos_extra', 'adjuntos_extra[]'] as $field) {
        $rows = solPub_normalize_uploaded_files($field);
        if ($rows !== []) {
            return $rows;
        }
    }
    foreach (array_keys($_FILES) as $k) {
        if (preg_match('/^adjuntos_extra/', $k)) {
            $rows = solPub_normalize_uploaded_files($k);
            if ($rows !== []) {
                logSolPub('adjuntos FILES campo: ' . $k);
                return $rows;
            }
        }
    }
    return [];
}

/** @return list<string> */
function solPub_allowed_mime_list(): array {
    return [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];
}

function solPub_mime_effective_for_upload(string $detectedMime, string $clientType, string $origName, array $allowed): ?string {
    $detectedMime = strtolower(trim($detectedMime));
    $clientType = strtolower(trim($clientType));
    foreach ([$detectedMime, $clientType] as $mime) {
        if ($mime !== '' && in_array($mime, $allowed, true)) {
            return $mime;
        }
    }
    $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
    $fromExt = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
    ];
    if ($ext !== '' && isset($fromExt[$ext]) && in_array($fromExt[$ext], $allowed, true)) {
        if (in_array($detectedMime, ['application/octet-stream', 'binary/octet-stream', ''], true)
            || in_array($clientType, ['application/octet-stream', 'binary/octet-stream'], true)) {
            return $fromExt[$ext];
        }
    }
    return null;
}

/**
 * Guarda imagen de cédula (data URL) en disco; ruta sin extensión → se añade .jpg/.png/.webp.
 *
 * @return string|null ruta absoluta del archivo creado
 */
function solPub_save_cedula_from_data_url(?string $dataUrl, string $absPathNoExt): ?string {
    if (!is_string($dataUrl) || trim($dataUrl) === '') {
        return null;
    }
    $dataUrl = trim($dataUrl);
    if (!preg_match('#^data:image/([^;]+);base64,(.+)$#is', $dataUrl, $m)) {
        return null;
    }
    $subtype = strtolower(trim($m[1]));
    $b64 = preg_replace('/\s+/', '', $m[2]);
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 200 || strlen($raw) > 15 * 1024 * 1024) {
        return null;
    }
    $ext = 'jpg';
    if (strpos($subtype, 'png') !== false) {
        $ext = 'png';
    } elseif (strpos($subtype, 'webp') !== false) {
        $ext = 'webp';
    } elseif (strpos($subtype, 'jpeg') !== false || $subtype === 'jpg') {
        $ext = 'jpg';
    }
    $abs = $absPathNoExt . '.' . $ext;
    if (file_put_contents($abs, $raw) === false) {
        return null;
    }
    return $abs;
}

/**
 * Solo lectura: id de ejecutivos_ventas por email (vendedor ya registrado). No inserta filas.
 */
function solPub_buscar_id_ejecutivo_por_email(PDO $pdo, ?string $email): ?int
{
    if ($email === null || $email === '') {
        return null;
    }
    $em = trim(strtolower($email));
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT id FROM ejecutivos_ventas WHERE LOWER(TRIM(COALESCE(email, \'\'))) = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$em]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (int) $r['id'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Solo lectura: nombre de ejecutivos_ventas por email.
 */
function solPub_buscar_nombre_ejecutivo_por_email(PDO $pdo, ?string $email): ?string
{
    if ($email === null || $email === '') {
        return null;
    }
    $em = trim(strtolower($email));
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT nombre FROM ejecutivos_ventas WHERE LOWER(TRIM(COALESCE(email, \'\'))) = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$em]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $nombre = trim((string)($r['nombre'] ?? ''));
        return $nombre !== '' ? $nombre : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Vincula el correo del enlace generado (vendedor) con el catálogo `ejecutivos_ventas` para rellenar
 * "Ejecutivo de Ventas" en Motus. Busca por email; si no existe, crea un registro mínimo.
 */
function solPub_resolver_ejecutivo_ventas_id(PDO $pdo, string $email): ?int
{
    $email = trim(strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT id FROM ejecutivos_ventas WHERE LOWER(TRIM(COALESCE(email, \'\'))) = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            return (int) $r['id'];
        }
    } catch (Throwable $e) {
        return null;
    }
    $local = explode('@', $email, 2)[0] ?? 'vendedor';
    if (function_exists('mb_strlen') && mb_strlen($local) > 255) {
        $local = mb_substr($local, 0, 255);
    } elseif (strlen($local) > 255) {
        $local = substr($local, 0, 255);
    }
    try {
        $ins = $pdo->prepare('INSERT INTO ejecutivos_ventas (nombre, sucursal, email, activo) VALUES (?, NULL, ?, 1)');
        $ins->execute([$local, $email]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        try {
            $st2 = $pdo->prepare('SELECT id FROM ejecutivos_ventas WHERE LOWER(TRIM(COALESCE(email, \'\'))) = ? ORDER BY id ASC LIMIT 1');
            $st2->execute([$email]);
            $r2 = $st2->fetch(PDO::FETCH_ASSOC);
            return $r2 ? (int) $r2['id'] : null;
        } catch (Throwable $e2) {
            return null;
        }
    }
}

/**
 * Cuando no hay solicitud en BD: materializa cédula + subidas en /tmp para adjuntar al correo (luego se borran).
 *
 * @return string[] rutas absolutas
 */
function solPub_materialize_adjuntos_temporales_para_correo(?string $cedulaDataUrl): array {
    $allowed = solPub_allowed_mime_list();
    $paths = [];
    $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $cedAbs = solPub_save_cedula_from_data_url($cedulaDataUrl, $tmpBase . 'solpub_ced_' . uniqid('', true));
    if ($cedAbs !== null && is_file($cedAbs)) {
        $paths[] = $cedAbs;
    }

    $maxFiles = 15;
    $c = 0;
    foreach (solPub_upload_rows_from_request() as $row) {
        if ($c >= $maxFiles) {
            break;
        }
        if ($row['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        $size = (int)$row['size'];
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            continue;
        }
        $tmp = $row['tmp_name'];
        if ($tmp === '') {
            continue;
        }
        if (!is_uploaded_file($tmp) && !is_readable($tmp)) {
            continue;
        }
        $orig = $row['name'] !== '' ? basename($row['name']) : 'adjunto.bin';
        $orig = preg_replace('/[^A-Za-z0-9._\\- ]/', '_', $orig);
        if ($orig === '' || strlen($orig) > 200) {
            $orig = 'adjunto.bin';
        }
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $dest = $tmpBase . 'solpub_up_' . uniqid('', true) . $ext;
        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
        if (!$moved || !is_file($dest)) {
            continue;
        }
        $mime = solPub_mime_effective_for_upload(solPub_finfo_mime($dest), $row['type'], $orig, $allowed);
        if ($mime === null) {
            @unlink($dest);
            continue;
        }
        $paths[] = $dest;
        $c++;
    }

    return $paths;
}

/**
 * Guarda adjuntos del formulario público en adjuntos/solicitudes/ y en adjuntos_solicitud.
 *
 * @return string[] rutas absolutas de archivos para adjuntar al correo
 */
function solPub_guardar_adjuntos_formulario_publico(PDO $pdo, int $solicitudId, int $gestorId, ?string $cedulaDataUrl): array {
    $allowed = solPub_allowed_mime_list();
    $pathsOut = [];
    $dirRel = 'adjuntos/solicitudes/';
    $dirAbs = __DIR__ . '/../' . $dirRel;
    if (!is_dir($dirAbs)) {
        @mkdir($dirAbs, 0755, true);
    }
    $dirAbs = realpath($dirAbs) ?: $dirAbs;
    $tamCol = solPub_adjuntos_tamano_column($pdo);

    $insertRow = static function (
        PDO $pdo,
        int $solicitudId,
        int $gestorId,
        ?string $tamCol,
        string $nombreArchivo,
        string $nombreOriginal,
        string $rutaDb,
        string $mime,
        int $size,
        string $descripcion
    ): void {
        if ($tamCol) {
            $stmt = $pdo->prepare("INSERT INTO adjuntos_solicitud (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, `$tamCol`, descripcion) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$solicitudId, $gestorId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $size, $descripcion !== '' ? $descripcion : null]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO adjuntos_solicitud (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, descripcion) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$solicitudId, $gestorId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $descripcion !== '' ? $descripcion : null]);
        }
    };

    $cedBase = rtrim($dirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'finpub_' . $solicitudId . '_cedula_' . uniqid('', true);
    $cedAbs = solPub_save_cedula_from_data_url($cedulaDataUrl, $cedBase);
    if ($cedAbs !== null && is_file($cedAbs)) {
        $ext = strtolower(pathinfo($cedAbs, PATHINFO_EXTENSION));
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $cedMime = $mimeMap[$ext] ?? 'image/jpeg';
        $nombreArchivo = basename($cedAbs);
        $size = (int)filesize($cedAbs);
        $rutaDb = $dirRel . $nombreArchivo;
        try {
            $insertRow($pdo, $solicitudId, $gestorId, $tamCol, $nombreArchivo, 'Identificacion-cedula.' . $ext, $rutaDb, $cedMime, $size, 'Identificación (formulario público)');
            $pathsOut[] = $cedAbs;
        } catch (Throwable $e) {
            @unlink($cedAbs);
            logSolPub('adj cedula insert: ' . $e->getMessage());
        }
    }

    $maxFiles = 15;
    $n = 0;
    foreach (solPub_upload_rows_from_request() as $row) {
        if ($n >= $maxFiles) {
            break;
        }
        if ($row['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        $size = (int)$row['size'];
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            continue;
        }
        $tmp = $row['tmp_name'];
        if ($tmp === '') {
            continue;
        }
        if (!is_uploaded_file($tmp)) {
            continue;
        }
        $orig = $row['name'] !== '' ? basename($row['name']) : 'adjunto.bin';
        $orig = preg_replace('/[^A-Za-z0-9._\\- ]/', '_', $orig);
        if ($orig === '' || strlen($orig) > 200) {
            $orig = 'adjunto.bin';
        }
        $mime = solPub_mime_effective_for_upload(solPub_finfo_mime($tmp), $row['type'], $orig, $allowed);
        if ($mime === null) {
            logSolPub('adjunto rechazado mime: ' . $orig . ' finfo=' . solPub_finfo_mime($tmp));
            continue;
        }
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $nombreArchivo = 'finpub_' . $solicitudId . '_' . uniqid('', true) . $ext;
        $abs = rtrim($dirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (!move_uploaded_file($tmp, $abs)) {
            continue;
        }
        $rutaDb = $dirRel . $nombreArchivo;
        try {
            $insertRow($pdo, $solicitudId, $gestorId, $tamCol, $nombreArchivo, $orig, $rutaDb, $mime, $size, 'Adjunto formulario público');
            $pathsOut[] = $abs;
            $n++;
        } catch (Throwable $e) {
            @unlink($abs);
            logSolPub('adj upload insert: ' . $e->getMessage());
        }
    }

    return $pathsOut;
}

require_once __DIR__ . '/../includes/pdf_financiamiento.php';

$solicitudId = 0;
$emailEnviado = false;
$pdoMain = null;
$gestorId = null;
$adjuntosParaCorreo = [];
$adjuntosCorreoSonTemporales = false;

$emailDestinoVendedor = null;
if ($token !== '') {
    $decoded = @base64_decode(str_replace(['-', '_'], ['+', '/'], $token), true);
    if ($decoded === false) {
        $decoded = @base64_decode($token, true);
    }
    if (is_string($decoded) && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
        $emailDestinoVendedor = $decoded;
    }
}
$emailCliente = isset($input['cliente_correo']) && filter_var(trim($input['cliente_correo']), FILTER_VALIDATE_EMAIL) ? trim($input['cliente_correo']) : null;

// 2a) Guardar en financiamiento_registros. Usa financiamiento/config_db.php o, si no existe, config/database.php (misma base motus_baes).
$pdoReg = null;
$configDbPath = __DIR__ . '/../financiamiento/config_db.php';
if (is_file($configDbPath)) {
    try {
        require_once $configDbPath;
        $pdoReg = isset($pdo_financiamiento) && $pdo_financiamiento instanceof PDO ? $pdo_financiamiento : null;
    } catch (Throwable $e) {
        logSolPub('financiamiento config_db: ' . $e->getMessage());
    }
}
if (!$pdoReg && is_file(__DIR__ . '/../config/database.php')) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdoReg = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
    } catch (Throwable $e) {
        logSolPub('financiamiento database: ' . $e->getMessage());
    }
}
/** PDO principal (motus_baes): catálogo ejecutivos_ventas; puede ser distinto de pdoReg en GoDaddy */
$pdoEjecutivos = null;
if (is_file(__DIR__ . '/../config/database.php')) {
    try {
        require_once __DIR__ . '/../config/database.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdoEjecutivos = $pdo;
        }
    } catch (Throwable $e) {
        logSolPub('config database (ejecutivos vendedor): ' . $e->getMessage());
    }
}
$emailVendedorFr = $emailDestinoVendedor;
$idVendedorFr = null;
if ($emailVendedorFr !== null && $pdoEjecutivos) {
    $idVendedorFr = solPub_buscar_id_ejecutivo_por_email($pdoEjecutivos, $emailVendedorFr);
}

$finRegistroInsertId = 0;
if ($pdoReg) {
    try {
        $v = function($key, $trim = true) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return $trim ? trim((string)$x) : $x;
        };
        $vNum = function($key) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return is_numeric($x) ? (float)$x : null;
        };
        $vInt = function($key) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return is_numeric($x) ? (int)$x : null;
        };
        $vDate = function($key) use ($input) {
            $x = trim((string)($input[$key] ?? ''));
            if ($x === '') return null;
            return normalizeDateToSql($x);
        };
        $stmtReg = $pdoReg->prepare("
            INSERT INTO financiamiento_registros (
                token_email, ip, email_vendedor, id_vendedor,
                cliente_nombre, cliente_estado_civil, cliente_sexo, cliente_id, cliente_nacimiento, cliente_edad,
                cliente_nacionalidad, cliente_dependientes, cliente_correo, cliente_peso, cliente_estatura,
                vivienda, vivienda_monto, prov_dist_corr, tel_residencia, barriada_calle_casa, calle, celular_cliente,
                edificio_apto, correo_residencial,
                empresa_nombre, empresa_ocupacion, empresa_anios, empresa_telefono, empresa_salario, empresa_direccion,
                otros_ingresos, ocupacion_otros, trabajo_anterior,
                tiene_conyuge, con_nombre, con_estado_civil, con_sexo, con_id, con_nacimiento, con_edad,
                con_nacionalidad, con_dependientes, con_correo, con_empresa, con_ocupacion, con_anios, con_tel,
                con_salario, con_direccion, con_otros_ingresos, con_trabajo_anterior,
                refp1_nombre, refp1_cel, refp1_dir_res, refp1_dir_lab,
                refp2_nombre, refp2_cel, refp2_dir_res, refp2_dir_lab,
                reff1_nombre, reff1_cel, reff1_dir_res, reff1_dir_lab,
                reff2_nombre, reff2_cel, reff2_dir_res, reff2_dir_lab,
                marca_auto, modelo_auto, anio_auto, kms_cod_auto, precio_venta, abono,
                sucursal, nombre_gestor, comentarios_gestor, firma, firmantes_adicionales
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");
        $stmtReg->execute([
            $token ?: null, $_SERVER['REMOTE_ADDR'] ?? null, $emailVendedorFr, $idVendedorFr,
            $v('cliente_nombre'), $v('cliente_estado_civil'), $v('cliente_sexo'), $v('cliente_id'), $vDate('cliente_nacimiento'), $vInt('cliente_edad'),
            $v('cliente_nacionalidad'), $vInt('cliente_dependientes'), $v('cliente_correo'), $vNum('cliente_peso'), $vNum('cliente_estatura'),
            $v('vivienda'), $vNum('vivienda_monto'), $v('prov_dist_corr'), $v('tel_residencia'), $v('barriada_calle_casa'), $v('calle'), $v('celular_cliente'),
            $v('edificio_apto'), $v('correo_residencial'),
            $v('empresa_nombre'), $v('empresa_ocupacion'), $v('empresa_anios'), $v('empresa_telefono'), $vNum('empresa_salario'), $v('empresa_direccion'),
            $v('otros_ingresos'), $v('ocupacion_otros'), $v('trabajo_anterior'),
            !empty($input['tiene_conyuge']) ? 1 : 0,
            $v('con_nombre'), $v('con_estado_civil'), $v('con_sexo'), $v('con_id'), $vDate('con_nacimiento'), $vInt('con_edad'),
            $v('con_nacionalidad'), $vInt('con_dependientes'), $v('con_correo'), $v('con_empresa'), $v('con_ocupacion'), $v('con_anios'), $v('con_tel'),
            $vNum('con_salario'), $v('con_direccion'), $v('con_otros_ingresos'), $v('con_trabajo_anterior'),
            $v('refp1_nombre'), $v('refp1_cel'), $v('refp1_dir_res'), $v('refp1_dir_lab'),
            $v('refp2_nombre'), $v('refp2_cel'), $v('refp2_dir_res'), $v('refp2_dir_lab'),
            $v('reff1_nombre'), $v('reff1_cel'), $v('reff1_dir_res'), $v('reff1_dir_lab'),
            $v('reff2_nombre'), $v('reff2_cel'), $v('reff2_dir_res'), $v('reff2_dir_lab'),
            $v('marca_auto'), $v('modelo_auto'), $vInt('anio_auto'), $vInt('kms_cod_auto'), $vNum('precio_venta'), $vNum('abono'),
            $v('sucursal'), $v('nombre_gestor'), $v('comentarios_gestor'), $firmaBase64 ?: null, isset($input['firmantes_adicionales']) ? $input['firmantes_adicionales'] : null
        ]);
        $finRegistroInsertId = (int) $pdoReg->lastInsertId();
    } catch (PDOException $e) {
        logSolPub('financiamiento_registros: ' . $e->getMessage());
    } catch (Throwable $e) {
        logSolPub('financiamiento_registros: ' . $e->getMessage());
    }
}

// 2b) Opcional: guardar en solicitudes_credito (panel Motus). Requiere config/database.php e historial_helper
$configPath = __DIR__ . '/../config/database.php';
$historialPath = __DIR__ . '/../includes/historial_helper.php';
if (is_file($configPath) && is_file($historialPath)) {
    try {
        require_once $configPath;
        require_once $historialPath;
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdoMain = $pdo;
            $gestorId = getDefaultGestorId($pdo);
            if ($gestorId) {
                // Construir comentarios_gestor con datos extra del wizard
                $extras = [];
                if (!empty($input['sucursal'])) $extras[] = 'Sucursal: ' . $input['sucursal'];
                if (!empty($input['nombre_gestor'])) $extras[] = 'Gestor indicado: ' . $input['nombre_gestor'];
                if (!empty($input['vivienda'])) $extras[] = 'Vivienda: ' . $input['vivienda'] . (isset($input['vivienda_monto']) && $input['vivienda_monto'] !== '' ? ' (Monto: ' . $input['vivienda_monto'] . ')' : '');
                if (!empty($input['cliente_nacionalidad'])) $extras[] = 'Nacionalidad: ' . $input['cliente_nacionalidad'];
                if (!empty($input['prov_dist_corr'])) $extras[] = 'Prov/Dist/Corr: ' . $input['prov_dist_corr'];
                if (!empty($input['calle'])) $extras[] = 'Calle: ' . $input['calle'];
                if (!empty($input['empresa_direccion'])) $extras[] = 'Dirección laboral: ' . $input['empresa_direccion'];
                if (!empty($input['otros_ingresos'])) $extras[] = 'Otros ingresos: ' . $input['otros_ingresos'];
                if (!empty($input['trabajo_anterior'])) $extras[] = 'Trabajo anterior: ' . $input['trabajo_anterior'];
                if (!empty($input['tiene_conyuge']) && !empty($input['con_nombre'])) {
                    $extras[] = 'Cónyuge: ' . $input['con_nombre'] . ' | Cédula: ' . ($input['con_id'] ?? '') . ' | Empresa: ' . ($input['con_empresa'] ?? '') . ' | Salario: ' . ($input['con_salario'] ?? '');
                }
                $refs = [];
                if (!empty($input['refp1_nombre'])) $refs[] = 'Ref. Personal 1: ' . $input['refp1_nombre'] . ' ' . ($input['refp1_cel'] ?? '');
                if (!empty($input['refp2_nombre'])) $refs[] = 'Ref. Personal 2: ' . $input['refp2_nombre'] . ' ' . ($input['refp2_cel'] ?? '');
                if (!empty($input['reff1_nombre'])) $refs[] = 'Ref. Familiar 1: ' . $input['reff1_nombre'] . ' ' . ($input['reff1_cel'] ?? '');
                if (!empty($input['reff2_nombre'])) $refs[] = 'Ref. Familiar 2: ' . $input['reff2_nombre'] . ' ' . ($input['reff2_cel'] ?? '');
                if (!empty($refs)) $extras[] = 'Referencias: ' . implode('; ', $refs);
                $comentariosGestor = trim(($input['comentarios_gestor'] ?? '') . "\n\n[Solicitud desde formulario público]\n" . implode("\n", $extras));

                $ejecutivoVentasId = null;
                if ($emailDestinoVendedor !== null) {
                    $ejecutivoVentasId = solPub_resolver_ejecutivo_ventas_id($pdo, $emailDestinoVendedor);
                }

                $casado = 0;
                if (!empty($input['cliente_estado_civil'])) {
                    $ec = $input['cliente_estado_civil'];
                    if (stripos($ec, 'Casado') !== false || stripos($ec, 'Unión') !== false) $casado = 1;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_credito (
                        gestor_id, ejecutivo_ventas_id, tipo_persona, nombre_cliente, cedula, edad, genero,
                        telefono, telefono_principal, email, direccion, provincia, distrito, corregimiento,
                        barriada, casa_edif, numero_casa_apto, casado, hijos, perfil_financiero,
                        ingreso, tiempo_laborar, ocupacion, nombre_empresa_negocio,
                        marca_auto, modelo_auto, ao_auto, kilometraje, precio_especial, abono_monto,
                        comentarios_gestor
                    ) VALUES (?, ?, 'Natural', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $gestorId, $ejecutivoVentasId, $nombre, $cedula, toInt($input['cliente_edad'] ?? null), mapGenero($input['cliente_sexo'] ?? null),
                    $input['tel_residencia'] ?? null, $input['celular_cliente'] ?? null, $input['cliente_correo'] ?? $input['correo_residencial'] ?? null,
                    $input['barriada_calle_casa'] ?? null, $input['prov_dist_corr'] ?? null, null, null, null,
                    $input['edificio_apto'] ?? null, $casado, toInt($input['cliente_dependientes'] ?? null, 0), 'Asalariado',
                    toNum($input['empresa_salario'] ?? null), isset($input['empresa_anios']) ? $input['empresa_anios'] . ' años' : null,
                    $input['empresa_ocupacion'] ?? null, $input['empresa_nombre'] ?? null,
                    $input['marca_auto'] ?? null, $input['modelo_auto'] ?? null, toInt($input['anio_auto'] ?? null), toInt($input['kms_cod_auto'] ?? null),
                    toNum($input['precio_venta'] ?? null), toNum($input['abono'] ?? null), $comentariosGestor
                ]);
                $solicitudId = (int) $pdo->lastInsertId();
                if ($finRegistroInsertId > 0 && $pdoReg instanceof PDO && $solicitudId > 0) {
                    try {
                        $uFr = $pdoReg->prepare('UPDATE financiamiento_registros SET solicitud_credito_id = ? WHERE id = ?');
                        $uFr->execute([$solicitudId, $finRegistroInsertId]);
                    } catch (Throwable $e) {
                        logSolPub('financiamiento solicitud_credito_id: ' . $e->getMessage());
                    }
                }
                try {
                    $stmtNota = $pdo->prepare("INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido) VALUES (?, ?, 'Comentario', 'Solicitud desde formulario público', 'Solicitud enviada desde el formulario de financiamiento (sin login).')");
                    $stmtNota->execute([$solicitudId, $gestorId]);
                } catch (PDOException $e) { /* ignorar */ }
                try {
                    registrarHistorialSolicitud($pdo, $solicitudId, $gestorId, 'creacion', 'Solicitud enviada desde formulario público de financiamiento', null, 'Nueva');
                } catch (Throwable $e) { /* ignorar */ }
            }
        }
    } catch (Throwable $e) {
        logSolPub('DB optional fail: ' . $e->getMessage());
        error_log('solicitud_publica DB: ' . $e->getMessage());
    }
}

if ($solicitudId > 0 && $pdoMain instanceof PDO && $gestorId) {
    try {
        $adjuntosParaCorreo = solPub_guardar_adjuntos_formulario_publico($pdoMain, $solicitudId, (int)$gestorId, $cedulaImagenDataUrl);
    } catch (Throwable $e) {
        logSolPub('adjuntos form pub: ' . $e->getMessage());
    }
} elseif (($cedulaImagenDataUrl !== null && $cedulaImagenDataUrl !== '') || solPub_upload_rows_from_request() !== []) {
    try {
        $adjuntosParaCorreo = solPub_materialize_adjuntos_temporales_para_correo($cedulaImagenDataUrl);
        $adjuntosCorreoSonTemporales = true;
        logSolPub('adjuntos correo (temp, sin solicitud en BD o sin gestor): ' . count($adjuntosParaCorreo) . ' archivo(s)');
    } catch (Throwable $e) {
        logSolPub('adjuntos temp correo: ' . $e->getMessage());
    }
}

// Correo después de crear solicitud y guardar adjuntos (PDF + identificación + archivos extra)
if ($emailDestinoVendedor !== null || $emailCliente !== null) {
    try {
        $pdfPath = null;
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            if (class_exists('Dompdf\Dompdf')) {
                $html = buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombre);
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sol_fin_' . uniqid('', true) . '.pdf';
                file_put_contents($pdfPath, $dompdf->output());
            }
        }
        if ($pdfPath && file_exists($pdfPath)) {
            require_once __DIR__ . '/../includes/EmailService.php';
            $emailService = new EmailService();
            $nombreClienteAsunto = trim((string)$nombre);
            if ($nombreClienteAsunto === '') {
                $nombreClienteAsunto = 'Cliente sin nombre';
            }
            $nombreVendedorAsunto = null;
            if ($pdoEjecutivos instanceof PDO) {
                $nombreVendedorAsunto = solPub_buscar_nombre_ejecutivo_por_email($pdoEjecutivos, $emailDestinoVendedor);
            }
            if ($nombreVendedorAsunto === null && $emailDestinoVendedor !== null) {
                $local = explode('@', $emailDestinoVendedor, 2)[0] ?? '';
                $local = str_replace(['.', '_', '-'], ' ', trim($local));
                $nombreVendedorAsunto = trim($local);
            }
            if ($nombreVendedorAsunto === null || $nombreVendedorAsunto === '') {
                $nombreVendedorAsunto = 'Vendedor';
            }
            $numeroSolicitudAsunto = $solicitudId > 0 ? (string)$solicitudId : 'N/A';
            $asuntoVendedor = 'MOTUS #' . $numeroSolicitudAsunto . ' Cliente ' . $nombreClienteAsunto . ' - ' . $nombreVendedorAsunto;
            $txtAdj = count($adjuntosParaCorreo) > 0
                ? 'Adjuntos: PDF de la solicitud, identificación y/o otros documentos enviados por el cliente.'
                : 'Adjunto: PDF con todos los datos y la firma.';
            $cuerpoVendedor = '<p>Se ha recibido una solicitud de financiamiento completada.</p><p><strong>Cliente:</strong> ' . htmlspecialchars($nombre) . '</p><p>' . htmlspecialchars($txtAdj) . '</p>';
            $asuntoCliente = 'Recibimos su Solicitud de Financiamiento - AutoMarket';
            $cuerpoCliente = '<p>Estimado/a ' . htmlspecialchars($nombre) . ',</p><p>Hemos recibido correctamente su solicitud de financiamiento. Adjunto encontrará el PDF y los documentos que envió.</p><p>Nos pondremos en contacto a la brevedad.</p><p>— AutoMarket</p>';

            $attachments = array_merge([$pdfPath], $adjuntosParaCorreo);

            if ($emailDestinoVendedor !== null) {
                $result = $emailService->enviarCorreo(
                    $emailDestinoVendedor,
                    $asuntoVendedor,
                    $cuerpoVendedor,
                    '',
                    strip_tags($cuerpoVendedor),
                    $attachments,
                    ['fyi@automarketpan.com']
                );
                $emailEnviado = !empty($result['success']);
            }
            if ($emailCliente !== null && $emailCliente !== $emailDestinoVendedor) {
                $resultCliente = $emailService->enviarCorreo(
                    $emailCliente,
                    $asuntoCliente,
                    $cuerpoCliente,
                    '',
                    strip_tags($cuerpoCliente),
                    $attachments,
                    ['fyi@automarketpan.com']
                );
                if (!empty($resultCliente['success'])) {
                    $emailEnviado = true;
                }
            }
            @unlink($pdfPath);
        }
    } catch (Exception $e) {
        error_log('solicitud_publica email: ' . $e->getMessage());
        logSolPub('email error: ' . $e->getMessage());
    }
}

if (!empty($adjuntosCorreoSonTemporales) && is_array($adjuntosParaCorreo)) {
    foreach ($adjuntosParaCorreo as $ap) {
        if (!is_string($ap) || $ap === '' || !is_file($ap)) {
            continue;
        }
        $bn = basename($ap);
        if (preg_match('/^(solpub_ced_|solpub_up_)/', $bn)) {
            @unlink($ap);
        }
    }
}

if (ob_get_level()) ob_end_clean();
echo json_encode([
    'success' => true,
    'message' => $emailEnviado
        ? 'Solicitud enviada correctamente. Se ha enviado una copia por correo.'
        : 'Solicitud enviada correctamente. Nos pondremos en contacto contigo.',
    'data' => ['id' => $solicitudId]
]);
exit();