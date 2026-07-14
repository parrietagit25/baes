<?php
/**
 * CRUD Ferias + asignación de vendedores (ejecutivos_ventas).
 * Solo Admin y Gestor.
 */
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

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
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

if ($action === 'vendedores') {
    switch ($method) {
        case 'GET':
            listarVendedores();
            break;
        case 'POST':
            agregarVendedor();
            break;
        case 'DELETE':
            quitarVendedor();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    exit();
}

if ($action === 'catalogo_vendedores' && $method === 'GET') {
    catalogoVendedores();
    exit();
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            obtenerUna((int) $_GET['id']);
        } else {
            obtenerLista();
        }
        break;
    case 'POST':
        crear();
        break;
    case 'PUT':
        actualizar();
        break;
    case 'DELETE':
        eliminar();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerLista(): void
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT f.*,
                   (SELECT COUNT(*) FROM feria_vendedores fv WHERE fv.feria_id = f.id) AS vendedores_count
            FROM ferias f
            ORDER BY f.fecha_inicio DESC, f.id DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('ferias list: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos. ¿Ejecutó migracion_ferias.sql?']);
    }
}

function obtenerUna(int $id): void
{
    global $pdo;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM ferias WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Feria no encontrada']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $row]);
    } catch (PDOException $e) {
        error_log('ferias get: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function validarFechas(string $inicio, string $fin): ?string
{
    if ($inicio === '' || $fin === '') {
        return 'Fecha de inicio y fecha de fin son obligatorias';
    }
    $di = DateTime::createFromFormat('Y-m-d', $inicio);
    $df = DateTime::createFromFormat('Y-m-d', $fin);
    if (!$di || $di->format('Y-m-d') !== $inicio) {
        return 'Fecha de inicio inválida';
    }
    if (!$df || $df->format('Y-m-d') !== $fin) {
        return 'Fecha de fin inválida';
    }
    if ($df < $di) {
        return 'La fecha de fin no puede ser anterior a la de inicio';
    }
    return null;
}

function crear(): void
{
    global $pdo;
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? ''));
    $fechaFin = trim((string) ($_POST['fecha_fin'] ?? ''));
    $lugar = trim((string) ($_POST['lugar'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $activo = isset($_POST['activo']) ? ((int) $_POST['activo'] ? 1 : 0) : 1;

    if ($nombre === '') {
        echo json_encode(['success' => false, 'message' => 'El nombre de la feria es obligatorio']);
        return;
    }
    $errFechas = validarFechas($fechaInicio, $fechaFin);
    if ($errFechas !== null) {
        echo json_encode(['success' => false, 'message' => $errFechas]);
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO ferias (nombre, fecha_inicio, fecha_fin, lugar, descripcion, activo)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $nombre,
            $fechaInicio,
            $fechaFin,
            $lugar === '' ? null : $lugar,
            $descripcion === '' ? null : $descripcion,
            $activo,
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Feria registrada correctamente',
            'data' => ['id' => (int) $pdo->lastInsertId()],
        ]);
    } catch (PDOException $e) {
        error_log('ferias crear: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear la feria']);
    }
}

function actualizar(): void
{
    global $pdo;
    parse_str((string) file_get_contents('php://input'), $put);
    $id = isset($put['id']) ? (int) $put['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }

    $nombre = trim((string) ($put['nombre'] ?? ''));
    $fechaInicio = trim((string) ($put['fecha_inicio'] ?? ''));
    $fechaFin = trim((string) ($put['fecha_fin'] ?? ''));
    $lugar = trim((string) ($put['lugar'] ?? ''));
    $descripcion = trim((string) ($put['descripcion'] ?? ''));
    $activo = isset($put['activo']) ? ((int) $put['activo'] ? 1 : 0) : 1;

    if ($nombre === '') {
        echo json_encode(['success' => false, 'message' => 'El nombre de la feria es obligatorio']);
        return;
    }
    $errFechas = validarFechas($fechaInicio, $fechaFin);
    if ($errFechas !== null) {
        echo json_encode(['success' => false, 'message' => $errFechas]);
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM ferias WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Feria no encontrada']);
            return;
        }
        $stmt = $pdo->prepare('
            UPDATE ferias
            SET nombre = ?, fecha_inicio = ?, fecha_fin = ?, lugar = ?, descripcion = ?, activo = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $nombre,
            $fechaInicio,
            $fechaFin,
            $lugar === '' ? null : $lugar,
            $descripcion === '' ? null : $descripcion,
            $activo,
            $id,
        ]);
        echo json_encode(['success' => true, 'message' => 'Feria actualizada correctamente']);
    } catch (PDOException $e) {
        error_log('ferias actualizar: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la feria']);
    }
}

function eliminar(): void
{
    global $pdo;
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        parse_str((string) file_get_contents('php://input'), $del);
        $id = isset($del['id']) ? (int) $del['id'] : 0;
    }
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM ferias WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Feria no encontrada']);
            return;
        }
        echo json_encode(['success' => true, 'message' => 'Feria eliminada']);
    } catch (PDOException $e) {
        error_log('ferias eliminar: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
    }
}

function listarVendedores(): void
{
    global $pdo;
    $feriaId = isset($_GET['feria_id']) ? (int) $_GET['feria_id'] : 0;
    if ($feriaId <= 0) {
        echo json_encode(['success' => false, 'message' => 'feria_id requerido']);
        return;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT ev.id, ev.nombre, ev.sucursal, ev.email, ev.activo, fv.fecha_asignacion
            FROM feria_vendedores fv
            INNER JOIN ejecutivos_ventas ev ON ev.id = fv.ejecutivo_ventas_id
            WHERE fv.feria_id = ?
            ORDER BY ev.nombre ASC
        ");
        $stmt->execute([$feriaId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('ferias vendedores list: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function catalogoVendedores(): void
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT id, nombre, sucursal, email, activo
            FROM ejecutivos_ventas
            WHERE activo = 1
            ORDER BY nombre ASC, sucursal ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log('ferias catalogo vendedores: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function agregarVendedor(): void
{
    global $pdo;
    $feriaId = isset($_POST['feria_id']) ? (int) $_POST['feria_id'] : 0;
    $ejecutivoId = isset($_POST['ejecutivo_ventas_id']) ? (int) $_POST['ejecutivo_ventas_id'] : 0;
    if ($feriaId <= 0 || $ejecutivoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'feria_id y ejecutivo_ventas_id son requeridos']);
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT id FROM ferias WHERE id = ?');
        $stmt->execute([$feriaId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Feria no encontrada']);
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM ejecutivos_ventas WHERE id = ?');
        $stmt->execute([$ejecutivoId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Vendedor no encontrado']);
            return;
        }
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO feria_vendedores (feria_id, ejecutivo_ventas_id)
            VALUES (?, ?)
        ');
        $stmt->execute([$feriaId, $ejecutivoId]);
        echo json_encode(['success' => true, 'message' => 'Vendedor añadido a la feria']);
    } catch (PDOException $e) {
        error_log('ferias agregar vendedor: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al añadir vendedor']);
    }
}

function quitarVendedor(): void
{
    global $pdo;
    $feriaId = isset($_GET['feria_id']) ? (int) $_GET['feria_id'] : 0;
    $ejecutivoId = isset($_GET['ejecutivo_ventas_id']) ? (int) $_GET['ejecutivo_ventas_id'] : 0;
    if ($feriaId <= 0 || $ejecutivoId <= 0) {
        parse_str((string) file_get_contents('php://input'), $del);
        $feriaId = isset($del['feria_id']) ? (int) $del['feria_id'] : $feriaId;
        $ejecutivoId = isset($del['ejecutivo_ventas_id']) ? (int) $del['ejecutivo_ventas_id'] : $ejecutivoId;
    }
    if ($feriaId <= 0 || $ejecutivoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'feria_id y ejecutivo_ventas_id son requeridos']);
        return;
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM feria_vendedores WHERE feria_id = ? AND ejecutivo_ventas_id = ?');
        $stmt->execute([$feriaId, $ejecutivoId]);
        echo json_encode(['success' => true, 'message' => 'Vendedor quitado de la feria']);
    } catch (PDOException $e) {
        error_log('ferias quitar vendedor: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al quitar vendedor']);
    }
}
