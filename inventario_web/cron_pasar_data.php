<?php
/**
 * Pase temp → web para ejecutar por CRON (misma lógica que api_web_pasar_data.php).
 * Uso: docker exec motus_php php /var/www/html/inventario_web/cron_pasar_data.php
 * Así no depende de HTTP/nginx y evita 404.
 */

require_once __DIR__ . '/config.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Automarket_Invs_web_temp");
    $total_autos = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}

if ($total_autos < 50) {
    echo "Menos de 50 vehículos ($total_autos). No se realiza el pase.\n";
    exit(0);
}

$columnas = 'Year, Transmission, Color, Make, Km, Code, LicensePlate, Model, Chasis, Unit, Engine, Fuel, Price, PriceTax, Doors, CarType, CC, LocationCode, LocationName, Interior, Headline, Description, Photo, Status, Marked, Promo, PromoPrice, PromoPriceTax, LoadDate, Prefijo, VIN, trg_updatefechaWeb, update_stat, stat_master, Internacional, tipo_compra, prioridad, foto_impel';

try {
    $pdo->exec("INSERT INTO Automarket_Invs_web ($columnas) SELECT $columnas FROM Automarket_Invs_web_temp WHERE VIN NOT IN (SELECT VIN FROM Automarket_Invs_web)");
    $pdo->exec("UPDATE Automarket_Invs_web AS target INNER JOIN Automarket_Invs_web_temp AS source ON target.VIN = source.VIN SET target.Year = source.Year, target.Transmission = source.Transmission, target.Color = source.Color, target.Make = source.Make, target.Km = source.Km, target.Code = source.Code, target.LicensePlate = source.LicensePlate, target.Model = source.Model, target.Chasis = source.Chasis, target.Unit = source.Unit, target.Engine = source.Engine, target.Fuel = source.Fuel, target.Price = source.Price, target.PriceTax = source.PriceTax, target.Doors = source.Doors, target.CarType = source.CarType, target.CC = source.CC, target.LocationCode = source.LocationCode, target.LocationName = source.LocationName, target.Interior = source.Interior, target.Headline = source.Headline, target.Description = source.Description, target.Photo = source.Photo, target.Status = source.Status, target.Marked = source.Marked, target.Promo = source.Promo, target.PromoPrice = source.PromoPrice, target.PromoPriceTax = source.PromoPriceTax, target.LoadDate = source.LoadDate, target.Prefijo = source.Prefijo, target.date_update = NOW(), target.trg_updatefechaWeb = source.trg_updatefechaWeb, target.update_stat = source.update_stat, target.stat_master = source.stat_master, target.prioridad = source.prioridad, target.Internacional = source.Internacional, target.tipo_compra = source.tipo_compra");
    $pdo->exec("DELETE FROM Automarket_Invs_web WHERE VIN NOT IN (SELECT VIN FROM Automarket_Invs_web_temp)");
    $pdo->exec("DELETE FROM Automarket_Invs_web_temp");
    echo "Pase completado. Registros actualizados.\n";
} catch (PDOException $e) {
    echo 'Error en el pase: ' . $e->getMessage() . "\n";
    exit(1);
}
