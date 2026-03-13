<?php
/**
 * Prueba de conexión y consulta a la tabla Automarket_Invs_web.
 * Uso: abrir en el navegador o ejecutar por CLI: php prueba_automarket_invs.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Prueba Automarket_Invs_web</title>";
echo "<style>table{border-collapse:collapse}th,td{border:1px solid #999;padding:6px 10px}th{background:#eee}img{height:50px;vertical-align:middle}</style>";
echo "</head><body><h1>Prueba: tabla Automarket_Invs_web</h1>\n";

try {
    require_once __DIR__ . '/config/database.php';
    $nombreBase = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "<p><strong>Conexión:</strong> OK (base: " . htmlspecialchars($nombreBase) . ")</p>\n";

    $sql = "SELECT Year, Transmission, Make, Price, Model, Photo
            FROM `Automarket_Invs_web`
            ORDER BY Make, Model
            LIMIT 50";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Registros encontrados:</strong> " . count($rows) . " (mostrando hasta 50)</p>\n";

    if (count($rows) === 0) {
        echo "<p>La tabla está vacía o no devolvió filas.</p>";
    } else {
        echo "<table><thead><tr>";
        echo "<th>Imagen</th><th>Year</th><th>Make</th><th>Model</th><th>Price</th><th>Transmission</th>";
        echo "</tr></thead><tbody>";
        foreach ($rows as $r) {
            $photo = isset($r['Photo']) && trim($r['Photo']) !== '' 
                ? '<img src="' . htmlspecialchars($r['Photo']) . '" alt="">' 
                : '-';
            $price = $r['Price'] !== null ? number_format((float)$r['Price'], 2) : 'N/D';
            echo "<tr>";
            echo "<td>" . $photo . "</td>";
            echo "<td>" . htmlspecialchars($r['Year'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($r['Make'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($r['Model'] ?? '') . "</td>";
            echo "<td>" . $price . "</td>";
            echo "<td>" . htmlspecialchars($r['Transmission'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
