<?php
/**
 * API reporte seguimiento de usuarios — solo administrador principal (id=1).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/usuario_actividad_helper.php';

if (!motus_actividad_es_admin_principal()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo el administrador principal.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seguimiento_usuarios_data.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'BD no disponible']);
    exit;
}

$action = $_GET['action'] ?? 'reporte';

if ($action === 'exportar_csv') {
    $f = seguimiento_usuarios_parse_filtros();
    $f['limit'] = 5000;
    $data = seguimiento_usuarios_data($pdo, $f);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seguimiento_usuarios_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Fecha', 'Usuario', 'Email', 'Evento', 'Sección', 'Página', 'Detalle', 'URL', 'IP', 'User-Agent', 'Sesión'], ';');
    foreach ($data['rows'] as $r) {
        $nombre = trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''));
        if ($nombre === '') {
            $nombre = $r['usuario_id'] ? ('#' . $r['usuario_id']) : '(sin usuario)';
        }
        fputcsv($out, [
            $r['created_at'] ?? '',
            $nombre,
            $r['email'] ?? '',
            $r['evento'] ?? '',
            $r['seccion'] ?? '',
            $r['pagina'] ?? '',
            $r['detalle'] ?? '',
            $r['url_path'] ?? '',
            $r['ip'] ?? '',
            $r['user_agent'] ?? '',
            $r['session_key'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

try {
    $f = seguimiento_usuarios_parse_filtros();
    $data = seguimiento_usuarios_data($pdo, $f);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/seguimiento_usuarios: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar el reporte']);
}
