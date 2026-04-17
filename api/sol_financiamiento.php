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

    $limite = isset($_GET['limite']) && ctype_digit((string) $_GET['limite']) ? (int) $_GET['limite'] : 0;
    $solicitudExcluir = isset($_GET['solicitud_id']) && ctype_digit((string) $_GET['solicitud_id'])
        ? (int) $_GET['solicitud_id'] : 0;

    $rows = [];
    if ($limite > 0) {
        $limiteSql = min($limite, 1000);
        $sqlFiltrado = "
            SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                   fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta
            FROM financiamiento_registros fr
            WHERE NOT EXISTS (
                SELECT 1 FROM solicitudes_credito sc
                WHERE sc.financiamiento_registro_id IS NOT NULL
                  AND sc.financiamiento_registro_id = fr.id
                  AND (? = 0 OR sc.id <> ?)
            )
            ORDER BY fr.fecha_creacion DESC
            LIMIT {$limiteSql}
        ";
        try {
            $stmt = $pdo->prepare($sqlFiltrado);
            $stmt->execute([$solicitudExcluir, $solicitudExcluir]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Columna no migrada, tablas en otras bases, etc.: devolver listado sin filtrar.
            error_log('sol_financiamiento listado (fallback sin filtro vínculo solicitud): ' . $e->getMessage());
            $sqlSimple = "
                SELECT id, fecha_creacion, cliente_nombre, cliente_id, cliente_correo, celular_cliente,
                       empresa_nombre, empresa_salario, marca_auto, modelo_auto, anio_auto, precio_venta
                FROM financiamiento_registros
                ORDER BY fecha_creacion DESC
                LIMIT {$limiteSql}
            ";
            $stmt = $pdo->query($sqlSimple);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $sql = "
            SELECT id, fecha_creacion, cliente_nombre, cliente_id, cliente_correo, celular_cliente,
                   empresa_nombre, empresa_salario, marca_auto, modelo_auto, anio_auto, precio_venta
            FROM financiamiento_registros
            ORDER BY fecha_creacion DESC
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('sol_financiamiento: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos', 'data' => []]);
}
