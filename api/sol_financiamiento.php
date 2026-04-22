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

/**
 * Columna opcional: vincula el envío público con solicitudes_credito sin usar financiamiento_registro_id en esa solicitud.
 */
function sol_fin_fr_tiene_solicitud_credito_id(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            $cache = false;
            return false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'financiamiento_registros'
              AND COLUMN_NAME = 'solicitud_credito_id'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/** Expresión SQL correlacionada: cantidad de adjuntos vinculados al registro fr. */
function sol_fin_sql_adjuntos_count(PDO $pdo, string $frIdRef): string {
    if (sol_fin_fr_tiene_solicitud_credito_id($pdo)) {
        return "(SELECT COUNT(*) FROM adjuntos_solicitud a WHERE a.solicitud_id IN (
            SELECT s.sol_id FROM (
                SELECT frx.solicitud_credito_id AS sol_id FROM financiamiento_registros frx WHERE frx.id = {$frIdRef} AND frx.solicitud_credito_id IS NOT NULL
                UNION
                SELECT sc.id AS sol_id FROM solicitudes_credito sc WHERE sc.financiamiento_registro_id = {$frIdRef}
            ) s WHERE s.sol_id IS NOT NULL
        ))";
    }
    return "(SELECT COUNT(*) FROM adjuntos_solicitud a INNER JOIN solicitudes_credito sc ON sc.id = a.solicitud_id WHERE sc.financiamiento_registro_id = {$frIdRef})";
}

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
        $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
        $stmt = $pdo->prepare("
            SELECT fr.id, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                   {$adjCount} AS adjuntos_count
            FROM financiamiento_registros fr
            WHERE fr.cliente_nombre LIKE ? OR fr.cliente_id LIKE ? OR fr.cliente_correo LIKE ? OR fr.celular_cliente LIKE ?
            ORDER BY fr.fecha_creacion DESC
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
        $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
        $sqlFiltrado = "
            SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                   fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
                   {$adjCount} AS adjuntos_count
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
            $adjCountFb = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
            $sqlSimple = "
                SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                       fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
                       {$adjCountFb} AS adjuntos_count
                FROM financiamiento_registros fr
                ORDER BY fr.fecha_creacion DESC
                LIMIT {$limiteSql}
            ";
            $stmt = $pdo->query($sqlSimple);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $adjCountAll = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
        $sql = "
            SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                   fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
                   {$adjCountAll} AS adjuntos_count
            FROM financiamiento_registros fr
            ORDER BY fr.fecha_creacion DESC
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
