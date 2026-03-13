<?php
/**
 * Pasa datos de Automarket_Invs_web_temp a Automarket_Invs_web.
 * Se ejecuta después de que Python haya subido datos a api_web.php (tabla temp).
 * Requiere al menos 50 registros en _temp para hacer el pase.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

// Opcional: exigir token (descomentar si lo llamas desde cron con header)
// if (!isset($_SERVER['HTTP_X_AUTH_TOKEN']) || $_SERVER['HTTP_X_AUTH_TOKEN'] !== INVENTARIO_WEB_TOKEN) {
//     header('HTTP/1.0 401 Unauthorized');
//     echo 'Token inválido';
//     exit;
// }

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Automarket_Invs_web_temp");
    $total_autos = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    echo 'Error de base de datos: ' . $e->getMessage();
    exit;
}

if ($total_autos < 50) {
    $msg = "ALERTA: hay menos de 50 vehículos ($total_autos). No se realiza el pase.";
    $logDir = __DIR__;
    @file_put_contents($logDir . '/Error_subida.txt', date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    echo $msg;
    exit;
}

$columnas = 'Year, Transmission, Color, Make, Km, Code, LicensePlate, Model, Chasis, Unit, Engine, Fuel, Price, PriceTax, Doors, CarType, CC, LocationCode, LocationName, Interior, Headline, Description, Photo, Status, Marked, Promo, PromoPrice, PromoPriceTax, LoadDate, Prefijo, VIN, trg_updatefechaWeb, update_stat, stat_master, Internacional, tipo_compra, prioridad, foto_impel';

try {
    // Insertar en _web los que no existen
    $sqlInsert = "INSERT INTO Automarket_Invs_web ($columnas)
                  SELECT $columnas FROM Automarket_Invs_web_temp
                  WHERE VIN NOT IN (SELECT VIN FROM Automarket_Invs_web)";
    $pdo->exec($sqlInsert);

    // Actualizar en _web los que ya existen (desde _temp)
    $sqlUpdate = "UPDATE Automarket_Invs_web AS target
                  INNER JOIN Automarket_Invs_web_temp AS source ON target.VIN = source.VIN
                  SET target.Year = source.Year,
                      target.Transmission = source.Transmission,
                      target.Color = source.Color,
                      target.Make = source.Make,
                      target.Km = source.Km,
                      target.Code = source.Code,
                      target.LicensePlate = source.LicensePlate,
                      target.Model = source.Model,
                      target.Chasis = source.Chasis,
                      target.Unit = source.Unit,
                      target.Engine = source.Engine,
                      target.Fuel = source.Fuel,
                      target.Price = source.Price,
                      target.PriceTax = source.PriceTax,
                      target.Doors = source.Doors,
                      target.CarType = source.CarType,
                      target.CC = source.CC,
                      target.LocationCode = source.LocationCode,
                      target.LocationName = source.LocationName,
                      target.Interior = source.Interior,
                      target.Headline = source.Headline,
                      target.Description = source.Description,
                      target.Photo = source.Photo,
                      target.Status = source.Status,
                      target.Marked = source.Marked,
                      target.Promo = source.Promo,
                      target.PromoPrice = source.PromoPrice,
                      target.PromoPriceTax = source.PromoPriceTax,
                      target.LoadDate = source.LoadDate,
                      target.Prefijo = source.Prefijo,
                      target.date_update = NOW(),
                      target.trg_updatefechaWeb = source.trg_updatefechaWeb,
                      target.update_stat = source.update_stat,
                      target.stat_master = source.stat_master,
                      target.prioridad = source.prioridad,
                      target.Internacional = source.Internacional,
                      target.tipo_compra = source.tipo_compra";
    $pdo->exec($sqlUpdate);

    // Eliminar de _web los que ya no están en _temp
    $pdo->exec("DELETE FROM Automarket_Invs_web WHERE VIN NOT IN (SELECT VIN FROM Automarket_Invs_web_temp)");

    // Vaciar tabla temporal
    $pdo->exec("DELETE FROM Automarket_Invs_web_temp");

    echo 'El pase se ha completado, todos los registros se han actualizado.';
} catch (PDOException $e) {
    echo 'Ocurrió un error en el pase: ' . $e->getMessage();
}
