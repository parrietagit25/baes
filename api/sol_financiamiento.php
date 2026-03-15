<?php
/**
 * API: listado y detalle de financiamiento_registros (formulario público).
 * Solo usuarios con sesión y rol Admin o Gestor.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userRoles = $_SESSION['user_roles'] ?? [];
$puedeAcceder = in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)
    || in_array('ROLE_BANCO', $userRoles) || in_array('ROLE_VENDEDOR', $userRoles);
if (!$puedeAcceder) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM financiamiento_registros WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // firma y firmantes_adicionales se envían completos para mostrar en el detalle
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        }
        exit;
    }

    if (isset($_GET['busqueda']) && trim((string)$_GET['busqueda']) !== '') {
        $term = '%' . trim($_GET['busqueda']) . '%';
        $stmt = $pdo->prepare("
            SELECT id, cliente_nombre, cliente_id, cliente_correo, celular_cliente
            FROM financiamiento_registros
            WHERE cliente_nombre LIKE ? OR cliente_id LIKE ? OR cliente_correo LIKE ? OR celular_cliente LIKE ?
            ORDER BY fecha_creacion DESC
            LIMIT 20
        ");
        $stmt->execute([$term, $term, $term, $term]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    $limite = isset($_GET['limite']) && ctype_digit($_GET['limite']) ? (int)$_GET['limite'] : 0;
    $sql = "
        SELECT id, fecha_creacion, cliente_nombre, cliente_id, cliente_correo, celular_cliente,
               empresa_nombre, empresa_salario, marca_auto, modelo_auto, anio_auto, precio_venta
        FROM financiamiento_registros
        ORDER BY fecha_creacion DESC
    ";
    if ($limite > 0) {
        $sql .= " LIMIT " . min($limite, 1000);
    }
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos', 'data' => []]);
}
