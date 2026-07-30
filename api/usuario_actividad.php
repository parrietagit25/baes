<?php
/**
 * Beacon de actividad del cliente (usuarios logueados).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/usuario_actividad_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'BD no disponible']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$eventos = $data['eventos'] ?? null;
if (!is_array($eventos)) {
    // Permitir un solo evento en la raíz
    if (!empty($data['evento'])) {
        $eventos = [$data];
    } else {
        $eventos = [];
    }
}

$permitidos = ['click', 'action', 'heartbeat', 'visibility', 'page_view'];
$guardados = 0;
$max = 25;
foreach ($eventos as $ev) {
    if ($guardados >= $max || !is_array($ev)) {
        break;
    }
    $tipo = strtolower(trim((string) ($ev['evento'] ?? '')));
    if (!in_array($tipo, $permitidos, true)) {
        continue;
    }
    // Heartbeat: como máximo 1 cada ~50s por sesión (anti-spam).
    if ($tipo === 'heartbeat') {
        $lastHb = (int) ($_SESSION['actividad_last_hb_at'] ?? 0);
        if ((time() - $lastHb) < 50) {
            continue;
        }
        $_SESSION['actividad_last_hb_at'] = time();
    }
    $pagina = isset($ev['pagina']) ? basename((string) $ev['pagina']) : basename((string) ($_SERVER['HTTP_REFERER'] ?? 'unknown'));
    if ($pagina === '' || $pagina === 'unknown') {
        $pagina = null;
    }
    motus_actividad_registrar($pdo, $tipo, [
        'pagina' => $pagina,
        'seccion' => isset($ev['seccion']) ? (string) $ev['seccion'] : null,
        'detalle' => isset($ev['detalle']) ? (string) $ev['detalle'] : null,
        'url_path' => isset($ev['url_path']) ? (string) $ev['url_path'] : null,
    ]);
    $guardados++;
}

echo json_encode(['success' => true, 'saved' => $guardados], JSON_UNESCAPED_UNICODE);
