<?php
/**
 * Devuelve el inventario en JSON para que Motus lo descargue por cron.
 * Usa la misma BD que api_web.php.
 */

$secret_token = 'SI5dGxz/2/AqWkOYuz6t4r3KYGbqGxOj3MhT3T/hp!J6Du9ko=6ITrMBNJU5WzUj?ep3VWb8gwxGv9RPgq?r0y=A8gdF2cJ!fWil1G??6voWqJvRdip1M?0u/sol-ON?';

if (!isset($_SERVER['HTTP_X_AUTH_TOKEN']) || $_SERVER['HTTP_X_AUTH_TOKEN'] !== $secret_token) {
    header('HTTP/1.0 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$host = 'localhost';
$usuario = 'autopedro';
$contraseña = 'Chicho1787$$$';
$dbname = 'automarketdev';

$conn = new mysqli($host, $usuario, $contraseña, $dbname);
if ($conn->connect_error) {
    header('HTTP/1.0 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Conexión fallida']);
    exit;
}

$conn->set_charset('utf8mb4');

$table = isset($_GET['table']) && $_GET['table'] === 'web' ? 'Automarket_Invs_web' : 'Automarket_Invs_web_temp';
$sql = "SELECT Year, Transmission, Color, Make, Km, Code, LicensePlate, Model, Chasis, Unit, Engine, Fuel,
        Price, PriceTax, Doors, CarType, CC, LocationCode, LocationName, Interior, Headline, Description, Photo,
        Status, Marked, Promo, PromoPrice, PromoPriceTax, LoadDate, Prefijo, VIN, trg_updatefechaWeb, update_stat,
        stat_master, Internacional, tipo_compra, prioridad
        FROM $table";
$result = $conn->query($sql);
if (!$result) {
    header('Content-Type: application/json');
    echo json_encode([]);
    $conn->close();
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows);