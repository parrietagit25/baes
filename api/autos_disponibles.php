<?php
/**
 * API: listado de autos disponibles (Automarket_Invs_web) para el modal en solicitudes.
 * Devuelve: Make, Model, Price, Transmission, Photo.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

set_time_limit(15);

try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sql = "SELECT Make, Model, Price, PriceTax, Transmission, Photo, VIN, Unit, Year
            FROM `Automarket_Invs_web`
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $term = '%' . $search . '%';
        $sql .= " AND (Make LIKE ? OR Model LIKE ? OR Unit LIKE ? OR VIN LIKE ? OR Year LIKE ?)";
        $params = array_fill(0, 5, $term);
    }
    $sql .= " ORDER BY Make, Model LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['Price'] = $r['Price'] !== null ? (float) $r['Price'] : null;
        $r['PriceTax'] = $r['PriceTax'] !== null ? (float) $r['PriceTax'] : null;
        $r['Photo'] = $r['Photo'] ?? '';
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'data' => []]);
}
