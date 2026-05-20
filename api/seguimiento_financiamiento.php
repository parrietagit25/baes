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

$action = $_GET['action'] ?? 'reporte';

if ($action === 'exportar_csv') {
    $filt = rep_segfin_parse_filtros();
    $exp = rep_segfin_export_pack($pdo, $filt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seguimiento_financiamiento.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, $exp['headers'], ';');
        foreach ($exp['rows'] as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
    }
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
