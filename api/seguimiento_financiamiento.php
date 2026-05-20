<?php
/**
 * API Seguimiento — formulario público vs solicitud Motus.
 */

session_start();

$roles = $_SESSION['user_roles'] ?? [];
$esAdmin = in_array('ROLE_ADMIN', $roles, true);
$esGestor = in_array('ROLE_GESTOR', $roles, true);

if (!isset($_SESSION['user_id']) || (!$esAdmin && !$esGestor)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/reportes_seguimiento_fin_data.php';
require_once __DIR__ . '/../includes/xlsx_export.php';

$action = $_GET['action'] ?? 'reporte';

if ($action === 'exportar_xlsx' || $action === 'exportar_csv') {
    $filt = rep_segfin_parse_filtros();
    $exp = rep_segfin_export_pack($pdo, $filt);
    motus_output_xlsx_download('seguimiento_financiamiento.xlsx', 'Seguimiento', $exp['headers'], $exp['rows']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

if ($action !== 'reporte') {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit();
}

try {
    $data = rep_segfin_build_reporte($pdo);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('seguimiento_financiamiento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al generar el reporte de seguimiento']);
}
