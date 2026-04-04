<?php
/**
 * CRUD ejecutivos de ventas (solo administrador).
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

if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden gestionar ejecutivos de ventas']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            obtenerUno((int) $_GET['id']);
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
            SELECT ev.*,
                   (SELECT COUNT(*) FROM solicitudes_credito s WHERE s.ejecutivo_ventas_id = ev.id) AS solicitudes_count
            FROM ejecutivos_ventas ev
            ORDER BY ev.nombre ASC, ev.sucursal ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        error_log('ejecutivos_ventas list: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerUno(int $id): void
{
    global $pdo;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM ejecutivos_ventas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        }
    } catch (PDOException $e) {
        error_log('ejecutivos_ventas get: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function crear(): void
{
    global $pdo;
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    if ($nombre === '') {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }
    $sucursal = trim((string) ($_POST['sucursal'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido']);
        return;
    }
    $activo = !empty($_POST['activo']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare('
            INSERT INTO ejecutivos_ventas (nombre, sucursal, email, activo)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $nombre,
            $sucursal === '' ? null : $sucursal,
            $email === '' ? null : $email,
            $activo ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Ejecutivo creado correctamente', 'data' => ['id' => $id]]);
    } catch (PDOException $e) {
        error_log('ejecutivos_ventas crear: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el registro']);
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
    if ($nombre === '') {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }
    $sucursal = trim((string) ($put['sucursal'] ?? ''));
    $email = trim((string) ($put['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido']);
        return;
    }
    $activo = isset($put['activo']) ? ((int) $put['activo'] ? 1 : 0) : 1;

    try {
        $stmt = $pdo->prepare('SELECT id FROM ejecutivos_ventas WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
            return;
        }
        $stmt = $pdo->prepare('
            UPDATE ejecutivos_ventas
            SET nombre = ?, sucursal = ?, email = ?, activo = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $nombre,
            $sucursal === '' ? null : $sucursal,
            $email === '' ? null : $email,
            $activo,
            $id,
        ]);
        echo json_encode(['success' => true, 'message' => 'Ejecutivo actualizado correctamente']);
    } catch (PDOException $e) {
        error_log('ejecutivos_ventas actualizar: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el registro']);
    }
}
