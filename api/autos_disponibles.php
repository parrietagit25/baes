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

require_once __DIR__ . '/../config/database.php'; // misma base que solicitudes

set_time_limit(15);

$debug = defined('APP_DEBUG') && APP_DEBUG;

try {
    // Campos para el modal (incl. Unit para el link Impel por unidad/placa)
    $sql = "SELECT Year, Transmission, Make, Price, Model, Photo, Unit, LicensePlate
            FROM `Automarket_Invs_web`
            ORDER BY Make, Model
            LIMIT 500";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['Price'] = $r['Price'] !== null ? (float) $r['Price'] : null;
        $r['Photo'] = isset($r['Photo']) ? (string) $r['Photo'] : '';
        $r['Unit'] = isset($r['Unit']) ? (string) $r['Unit'] : '';
        $r['LicensePlate'] = isset($r['LicensePlate']) ? (string) $r['LicensePlate'] : '';
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
