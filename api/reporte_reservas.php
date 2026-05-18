<?php
/**
 * Subida y consulta de reportes de reservas (admin y gestor).
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$roles = $_SESSION['user_roles'] ?? [];
$esAdmin = in_array('ROLE_ADMIN', $roles, true);
$esGestor = in_array('ROLE_GESTOR', $roles, true);
if (!$esAdmin && !$esGestor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['download'])) {
    descargarReporte((int) $_GET['download']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

switch ($method) {
    case 'GET':
        listarReportes();
        break;
    case 'POST':
        subirReporte();
        break;
    case 'DELETE':
        if (!$esAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador puede eliminar reportes']);
            exit();
        }
        eliminarReporte();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function tablaReportesExiste(): bool
{
    global $pdo;
    try {
        $pdo->query('SELECT 1 FROM reportes_reservas LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function listarReportes(): void
{
    global $pdo;
    if (!tablaReportesExiste()) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'warning' => 'Ejecute database/migracion_reportes_reservas.sql en la base de datos.',
        ]);
        return;
    }
    try {
        $stmt = $pdo->query("
            SELECT r.id, r.nombre_original, r.tamano_bytes, r.mime_type, r.fecha_subida,
                   u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
            FROM reportes_reservas r
            INNER JOIN usuarios u ON u.id = r.usuario_id
            ORDER BY r.fecha_subida DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('reporte_reservas listar: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al listar reportes']);
    }
}

function subirReporte(): void
{
    global $pdo;
    if (!tablaReportesExiste()) {
        echo json_encode([
            'success' => false,
            'message' => 'La tabla reportes_reservas no existe. Ejecute database/migracion_reportes_reservas.sql',
        ]);
        return;
    }
    if (!isset($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un archivo válido']);
        return;
    }

    $f = $_FILES['archivo'];
    $nombreOriginal = (string) ($f['name'] ?? 'reporte');
    $size = (int) ($f['size'] ?? 0);
    $mime = (string) ($f['type'] ?? '');
    $ext = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extPermitidas = ['xlsx', 'xls', 'csv'];
    if (!in_array($ext, $extPermitidas, true)) {
        echo json_encode(['success' => false, 'message' => 'Formato no permitido. Use Excel (.xlsx, .xls) o CSV (.csv)']);
        return;
    }
    if ($size <= 0 || $size > 25 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'El archivo debe ser mayor a 0 y menor de 25 MB']);
        return;
    }

    $baseDir = realpath(__DIR__ . '/../adjuntos');
    if ($baseDir === false) {
        $baseDir = __DIR__ . '/../adjuntos';
    }
    $dir = $baseDir . DIRECTORY_SEPARATOR . 'reportes_reservas';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de almacenamiento']);
        return;
    }

    try {
        $token = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $token = uniqid('rep_', true);
    }
    $nombreGuardado = $token . '.' . $ext;
    $rutaCompleta = $dir . DIRECTORY_SEPARATOR . $nombreGuardado;
    if (!move_uploaded_file((string) $f['tmp_name'], $rutaCompleta)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo']);
        return;
    }

    $rutaRelativa = 'adjuntos/reportes_reservas/' . $nombreGuardado;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reportes_reservas (nombre_original, ruta_archivo, tamano_bytes, mime_type, usuario_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nombreOriginal,
            $rutaRelativa,
            $size,
            $mime !== '' ? $mime : null,
            (int) $_SESSION['user_id'],
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Reporte subido correctamente',
            'data' => ['id' => (int) $pdo->lastInsertId()],
        ]);
    } catch (PDOException $e) {
        @unlink($rutaCompleta);
        error_log('reporte_reservas subir: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al registrar el reporte en la base de datos']);
    }
}

function descargarReporte(int $id): void
{
    global $pdo;
    if ($id <= 0 || !tablaReportesExiste()) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Reporte no encontrado';
        return;
    }
    $stmt = $pdo->prepare('SELECT nombre_original, ruta_archivo, mime_type FROM reportes_reservas WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Reporte no encontrado';
        return;
    }
    $path = __DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['ruta_archivo']);
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Archivo no encontrado en el servidor';
        return;
    }
    $mime = $row['mime_type'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename((string) $row['nombre_original']) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit();
}

function eliminarReporte(): void
{
    global $pdo;
    parse_str((string) file_get_contents('php://input'), $del);
    $id = isset($del['id']) ? (int) $del['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT ruta_archivo FROM reportes_reservas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Reporte no encontrado']);
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM reportes_reservas WHERE id = ?');
        $stmt->execute([$id]);
        $path = __DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['ruta_archivo']);
        if (is_file($path)) {
            @unlink($path);
        }
        echo json_encode(['success' => true, 'message' => 'Reporte eliminado']);
    } catch (PDOException $e) {
        error_log('reporte_reservas eliminar: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
    }
}
