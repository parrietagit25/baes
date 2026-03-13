<?php
/**
 * API que recibe datos de inventario desde el proceso Python.
 * Escribe en Automarket_Invs_web_temp (misma BD del proyecto: motus_baes / solicitud_credito).
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$logFile = @fopen(INVENTARIO_WEB_LOG, 'a');
if (!$logFile) {
    $logFile = @fopen(sys_get_temp_dir() . '/inventario_web_log.txt', 'a');
}
if ($logFile) {
    fwrite($logFile, date('Y-m-d H:i:s') . " Iniciando script...\n");
}

if (!isset($_SERVER['HTTP_X_AUTH_TOKEN']) || $_SERVER['HTTP_X_AUTH_TOKEN'] !== INVENTARIO_WEB_TOKEN) {
    if ($logFile) fwrite($logFile, "Token inválido o no enviado\n");
    if ($logFile) fclose($logFile);
    header('HTTP/1.0 401 Unauthorized');
    echo 'Token inválido';
    exit;
}

try {
    $jsonContent = file_get_contents('php://input');
    if ($logFile) fwrite($logFile, "Contenido JSON (primeros 500 chars): " . substr($jsonContent, 0, 500) . "\n");
    $decoded = json_decode($jsonContent);
} catch (Throwable $e) {
    if ($logFile) fwrite($logFile, "Error JSON: " . $e->getMessage() . "\n");
    if ($logFile) fclose($logFile);
    echo 'No se recibieron datos JSON válidos';
    exit;
}

$items = is_array($decoded) ? $decoded : (isset($decoded) && is_object($decoded) ? [$decoded] : []);

if (count($items) === 0) {
    if ($logFile) fclose($logFile);
    echo 'No se recibieron datos JSON válidos';
    exit;
}

$processed = 0;
$errors = 0;
$vinsReceived = [];

foreach ($items as $data) {
    if (!is_object($data)) continue;

    $VIN = isset($data->VIN) ? trim((string) $data->VIN) : '';
    if ($VIN === '') continue;

    $vinsReceived[] = $VIN;

    $Year            = $data->Year ?? '';
    $Transmission    = $data->Transmission ?? '';
    $Color           = $data->Color ?? '';
    $Make            = $data->Make ?? '';
    $Km              = $data->Km ?? '';
    $Code            = $data->Code ?? '';
    $LicensePlate    = $data->LicensePlate ?? '';
    $Model           = $data->Model ?? '';
    $Chasis          = $data->Chasis ?? '';
    $Unit            = $data->Unit ?? '';
    $Engine          = $data->Engine ?? '';
    $Fuel            = $data->Fuel ?? '';
    $Price           = $data->Price !== null && $data->Price !== '' ? (float) $data->Price : null;
    $PriceTax        = $data->PriceTax !== null && $data->PriceTax !== '' ? (float) $data->PriceTax : null;
    $Doors           = $data->Doors ?? '';
    $CarType         = $data->CarType ?? '';
    $CC              = $data->CC ?? null;
    $LocationCode    = $data->LocationCode ?? '';
    $LocationName    = $data->LocationName ?? '';
    $Interior        = $data->Interior ?? '';
    $Headline        = $data->Headline ?? null;
    $Description     = $data->Description ?? null;
    $Photo           = $data->Photo ?? '';
    $Status          = $data->Status ?? '';
    $Marked          = (isset($data->Marked) && $data->Marked) ? 1 : 0;
    $Promo           = (isset($data->Promo) && $data->Promo) ? 1 : 0;
    $PromoPrice      = $data->PromoPrice !== null && $data->PromoPrice !== '' ? (float) $data->PromoPrice : null;
    $PromoPriceTax   = $data->PromoPriceTax !== null && $data->PromoPriceTax !== '' ? (float) $data->PromoPriceTax : null;
    $LoadDate        = $data->LoadDate ?? null;
    $Prefijo         = $data->Prefijo ?? '';
    $trg_updatefechaWeb = $data->trg_updatefechaWeb ?? '';
    $update_stat     = $data->update_stat ?? null;
    $Internacional   = $data->Internacional ?? '';
    $tipo_compra     = $data->tipo_compra ?? '';

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM Automarket_Invs_web_temp WHERE VIN = ?");
        $stmt->execute([$VIN]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            if ($update_stat == 1) {
                $sql = "UPDATE Automarket_Invs_web_temp SET
                    Year = ?, Transmission = ?, Color = ?, Make = ?, Km = ?, Code = ?, LicensePlate = ?, Model = ?,
                    Chasis = ?, Unit = ?, Engine = ?, Fuel = ?, Price = ?, PriceTax = ?, Doors = ?, CarType = ?, CC = ?,
                    LocationCode = ?, LocationName = ?, Interior = ?, Headline = ?, Description = ?, Photo = ?,
                    Status = ?, Marked = ?, Promo = ?, PromoPrice = ?, PromoPriceTax = ?, LoadDate = ?, Prefijo = ?,
                    trg_updatefechaWeb = ?, update_stat = ?, stat_master = 1, Internacional = ?, tipo_compra = ?, prioridad = 0, foto_impel = ''
                    WHERE VIN = ?";
                $pdo->prepare($sql)->execute([
                    $Year, $Transmission, $Color, $Make, $Km, $Code, $LicensePlate, $Model,
                    $Chasis, $Unit, $Engine, $Fuel, $Price, $PriceTax, $Doors, $CarType, $CC,
                    $LocationCode, $LocationName, $Interior, $Headline, $Description, $Photo,
                    $Status, $Marked, $Promo, $PromoPrice, $PromoPriceTax, $LoadDate, $Prefijo,
                    $trg_updatefechaWeb, $update_stat, $Internacional, $tipo_compra, $VIN
                ]);
                $processed++;
            }
        } else {
            $sql = "INSERT INTO Automarket_Invs_web_temp
                (Year, Transmission, Color, Make, Km, Code, LicensePlate, Model, Chasis, Unit, Engine, Fuel,
                 Price, PriceTax, Doors, CarType, CC, LocationCode, LocationName, Interior, Headline, Description, Photo,
                 Status, Marked, Promo, PromoPrice, PromoPriceTax, LoadDate, Prefijo, VIN, trg_updatefechaWeb, update_stat, stat_master, Internacional, tipo_compra, prioridad, foto_impel)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 0, '')";
            $pdo->prepare($sql)->execute([
                $Year, $Transmission, $Color, $Make, $Km, $Code, $LicensePlate, $Model, $Chasis, $Unit, $Engine, $Fuel,
                $Price, $PriceTax, $Doors, $CarType, $CC, $LocationCode, $LocationName, $Interior, $Headline, $Description, $Photo,
                $Status, $Marked, $Promo, $PromoPrice, $PromoPriceTax, $LoadDate, $Prefijo, $VIN, $trg_updatefechaWeb, $update_stat, $Internacional, $tipo_compra
            ]);
            $processed++;
        }
    } catch (PDOException $e) {
        $errors++;
        if ($logFile) fwrite($logFile, "VIN=$VIN error: " . $e->getMessage() . "\n");
    }
}

// Marcar en _temp los que no vinieron en este envío: stat_master = 2
if (count($vinsReceived) > 0) {
    try {
        $placeholders = implode(',', array_fill(0, count($vinsReceived), '?'));
        $sql = "UPDATE Automarket_Invs_web_temp SET stat_master = 2 WHERE VIN NOT IN ($placeholders)";
        $pdo->prepare($sql)->execute($vinsReceived);
        if ($logFile) fwrite($logFile, "stat_master=2 actualizado para VINs no recibidos.\n");
    } catch (PDOException $e) {
        if ($logFile) fwrite($logFile, "Error UPDATE stat_master: " . $e->getMessage() . "\n");
    }
}

if ($logFile) {
    fwrite($logFile, "Procesados: $processed, Errores: $errors\n");
    fclose($logFile);
}

echo 'Éxito: Se han procesado ' . $processed . ' registros' . ($errors ? ", $errors errores." : '.');
