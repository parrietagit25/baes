<?php
/**
 * Sincronización Motus: descarga el inventario desde GoDaddy e inserta en esta BD.
 * Ejecutar por CRON (ej. cada 15 min): php cron_pull_from_godaddy.php
 * Así Python solo envía a GoDaddy; Motus hace la petición (servidor a servidor, sin Cloudflare de por medio).
 *
 * Requiere que en GoDaddy exista api_web_export.php que devuelva el JSON (ver README_PULL.md).
 */

// Para ejecutar por CLI
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_X_AUTH_TOKEN'] = $argv[1] ?? getenv('INVENTARIO_WEB_TOKEN') ?: '';
}

require_once __DIR__ . '/config.php';

// URL del endpoint de exportación en GoDaddy (debe devolver JSON array de vehículos)
$godaddy_export_url = getenv('GODADDY_EXPORT_URL') ?: 'https://automarketpanama.com/api/api_web_export.php';
$export_token = getenv('GODADDY_EXPORT_TOKEN') ?: INVENTARIO_WEB_TOKEN;

$logFile = @fopen(INVENTARIO_WEB_LOG, 'a');
if ($logFile) fwrite($logFile, date('Y-m-d H:i:s') . " Cron pull: iniciando\n");

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "X-Auth-Token: $export_token\r\n",
        'timeout' => 60
    ]
]);

$json = @file_get_contents($godaddy_export_url, false, $ctx);
if ($json === false) {
    $err = "No se pudo conectar a $godaddy_export_url";
    if ($logFile) fwrite($logFile, $err . "\n");
    if ($logFile) fclose($logFile);
    if (php_sapi_name() === 'cli') echo $err . "\n";
    exit(1);
}

$items = json_decode($json);
if (!is_array($items)) {
    $err = "Respuesta no es un array JSON válido";
    if ($logFile) fwrite($logFile, $err . "\n");
    if ($logFile) fclose($logFile);
    if (php_sapi_name() === 'cli') echo $err . "\n";
    exit(1);
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

if (count($vinsReceived) > 0) {
    try {
        $placeholders = implode(',', array_fill(0, count($vinsReceived), '?'));
        $pdo->prepare("UPDATE Automarket_Invs_web_temp SET stat_master = 2 WHERE VIN NOT IN ($placeholders)")->execute($vinsReceived);
    } catch (PDOException $e) {
        if ($logFile) fwrite($logFile, "Error UPDATE stat_master: " . $e->getMessage() . "\n");
    }
}

if ($logFile) {
    fwrite($logFile, "Pull: $processed procesados, $errors errores. Total items: " . count($items) . "\n");
    fclose($logFile);
}

if (php_sapi_name() === 'cli') {
    echo "OK: $processed registros sincronizados desde GoDaddy.\n";
}
