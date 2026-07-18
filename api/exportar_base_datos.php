<?php
/**
 * Exporta la base de datos completa a .sql (estilo phpMyAdmin).
 * Solo permitido para el administrador con user_id = 1.
 */
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
set_time_limit(0);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ((int)$_SESSION['user_id'] !== 1) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Solo el administrador principal (id 1) puede exportar la base de datos']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

/**
 * Escapa un valor para INSERT estilo mysqldump/phpMyAdmin.
 */
function sql_escape_value(PDO $pdo, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    // Detectar binario (BLOB con bytes nulos u octets inválidos UTF-8): 0x...
    if (is_string($value) && (strpos($value, "\0") !== false
        || (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')))) {
        return '0x' . bin2hex($value);
    }

    return $pdo->quote((string)$value);
}

/**
 * Escribe una línea al stream de salida.
 */
function dump_out(string $line): void
{
    echo $line;
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbName) {
        throw new RuntimeException('No se pudo determinar el nombre de la base de datos');
    }

    $fecha = date('Y-m-d H:i:s');
    $archivo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $dbName) . '_' . date('Ymd_His') . '.sql';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    dump_out("-- MOTUS SQL Dump\n");
    dump_out("-- Generado: {$fecha}\n");
    dump_out("-- Host: " . ($host ?? 'localhost') . "\n");
    dump_out("-- Base de datos: `{$dbName}`\n");
    dump_out("-- ------------------------------------------------------\n");
    dump_out("-- Estilo compatible con phpMyAdmin / mysqldump\n\n");

    dump_out("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    dump_out("START TRANSACTION;\n");
    dump_out("SET time_zone = \"+00:00\";\n\n");

    dump_out("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
    dump_out("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
    dump_out("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
    dump_out("/*!40101 SET NAMES utf8mb4 */;\n");
    dump_out("/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
    dump_out("/*!40103 SET TIME_ZONE='+00:00' */;\n");
    dump_out("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
    dump_out("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
    dump_out("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
    dump_out("/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");

    dump_out("--\n-- Base de datos: `{$dbName}`\n--\n\n");

    // Tablas base (sin vistas)
    $tables = $pdo->query(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $tableSafe = str_replace('`', '``', $table);

        dump_out("-- --------------------------------------------------------\n\n");
        dump_out("--\n-- Estructura de tabla para la tabla `{$table}`\n--\n\n");
        dump_out("DROP TABLE IF EXISTS `{$tableSafe}`;\n");

        $createRow = $pdo->query("SHOW CREATE TABLE `{$tableSafe}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? null;
        if (!$createSql) {
            throw new RuntimeException("No se pudo obtener CREATE TABLE de `{$table}`");
        }
        dump_out($createSql . ";\n\n");

        dump_out("--\n-- Volcado de datos para la tabla `{$table}`\n--\n\n");

        $stmt = $pdo->query("SELECT * FROM `{$tableSafe}`", PDO::FETCH_ASSOC);
        $batch = [];
        $columns = null;
        $rowCount = 0;
        $batchSize = 100;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
                $colsQuoted = '`' . implode('`, `', array_map(static function ($c) {
                    return str_replace('`', '``', $c);
                }, $columns)) . '`';
                dump_out("LOCK TABLES `{$tableSafe}` WRITE;\n");
                dump_out("/*!40000 ALTER TABLE `{$tableSafe}` DISABLE KEYS */;\n");
            }

            $vals = [];
            foreach ($columns as $col) {
                $vals[] = sql_escape_value($pdo, $row[$col]);
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            $rowCount++;

            if (count($batch) >= $batchSize) {
                dump_out("INSERT INTO `{$tableSafe}` ({$colsQuoted}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }

        if ($columns !== null) {
            if (!empty($batch)) {
                dump_out("INSERT INTO `{$tableSafe}` ({$colsQuoted}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            dump_out("/*!40000 ALTER TABLE `{$tableSafe}` ENABLE KEYS */;\n");
            dump_out("UNLOCK TABLES;\n\n");
        } elseif ($rowCount === 0) {
            dump_out("-- (sin datos)\n\n");
        }
    }

    // Vistas
    $views = $pdo->query(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
           AND TABLE_TYPE = 'VIEW'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($views as $view) {
        $viewSafe = str_replace('`', '``', $view);
        dump_out("-- --------------------------------------------------------\n\n");
        dump_out("--\n-- Estructura para la vista `{$view}`\n--\n\n");
        dump_out("DROP VIEW IF EXISTS `{$viewSafe}`;\n");
        $createView = $pdo->query("SHOW CREATE VIEW `{$viewSafe}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createView['Create View'] ?? null;
        if ($createSql) {
            dump_out($createSql . ";\n\n");
        }
    }

    dump_out("/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
    dump_out("/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
    dump_out("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
    dump_out("/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
    dump_out("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
    dump_out("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
    dump_out("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
    dump_out("/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n\n");
    dump_out("COMMIT;\n\n");
    dump_out("-- Fin del volcado\n");

    exit;
} catch (Throwable $e) {
    error_log('exportar_base_datos: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error al exportar la base de datos: ' . $e->getMessage(),
        ]);
    } else {
        echo "\n-- ERROR: " . $e->getMessage() . "\n";
    }
    exit;
}
