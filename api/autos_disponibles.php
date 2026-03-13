<?php
/**
 * API: listado de autos disponibles desde tabla Automarket_Invs_web.
 * Campos: Year, Transmission, Make, Price, Model, Photo.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'data' => []]);
    exit;
}

require_once __DIR__ . '../config/database.php';

set_time_limit(15);

$debug = defined('APP_DEBUG') && APP_DEBUG;

try {
    // Solo los 6 campos de Automarket_Invs_web que usa el modal
    $sql = "SELECT Year, Transmission, Make, Price, Model, Photo
            FROM `Automarket_Invs_web`
            ORDER BY Make, Model
            LIMIT 500";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['Price'] = $r['Price'] !== null ? (float) $r['Price'] : null;
        $r['Photo'] = isset($r['Photo']) ? (string) $r['Photo'] : '';
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    if ($debug) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
        ]);
    } else {
        echo json_encode(['success' => true, 'data' => []]);
    }
}
