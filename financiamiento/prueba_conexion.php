<?php
/**
 * Prueba de conexiones a base de datos.
 * Prueba: config principal, config financiamiento, y conexión manual con parámetros.
 * Ejecutar desde: .../financiamiento/prueba_conexion.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

$base = dirname(__DIR__);
$resultados = [];

function probarConexion($nombre, $pdo, &$resultados) {
    $r = ['nombre' => $nombre, 'ok' => false, 'mensaje' => '', 'detalle' => ''];
    if (!$pdo instanceof PDO) {
        $r['mensaje'] = 'No se recibió PDO';
        $resultados[] = $r;
        return;
    }
    try {
        $pdo->query("SELECT 1");
        $r['ok'] = true;
        $r['mensaje'] = 'Conexión OK';
        // Crear tabla de prueba si no existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS prueba_conexion (
            id int(11) NOT NULL AUTO_INCREMENT,
            mensaje varchar(255) DEFAULT NULL,
            fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $r['detalle'] .= 'Tabla prueba_conexion: existe o se creó. ';
        // Insertar fila
        $stmt = $pdo->prepare("INSERT INTO prueba_conexion (mensaje) VALUES (?)");
        $stmt->execute([$nombre . ' - ' . date('Y-m-d H:i:s')]);
        $id = $pdo->lastInsertId();
        $r['detalle'] .= "INSERT id=$id. ";
        // Leer
        $stmt = $pdo->query("SELECT id, mensaje, fecha FROM prueba_conexion ORDER BY id DESC LIMIT 3");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $r['detalle'] .= 'Últimos registros: ' . count($filas);
    } catch (PDOException $e) {
        $r['mensaje'] = 'Error: ' . $e->getMessage();
        $r['detalle'] = 'Código: ' . $e->getCode();
    }
    $resultados[] = $r;
}

// 1) Conexión principal (config/database.php) — solicitud_credito
$resultados[] = ['nombre' => '--- 1) config/database.php (solicitud_credito) ---', 'ok' => null, 'mensaje' => '', 'detalle' => ''];
if (is_file($base . '/config/database.php')) {
    try {
        require_once $base . '/config/database.php';
        probarConexion('Principal (solicitud_credito)', isset($pdo) ? $pdo : null, $resultados);
    } catch (Throwable $e) {
        $resultados[] = ['nombre' => 'Principal', 'ok' => false, 'mensaje' => $e->getMessage(), 'detalle' => ''];
    }
} else {
    $resultados[] = ['nombre' => 'Principal', 'ok' => false, 'mensaje' => 'No existe config/database.php', 'detalle' => ''];
}

// 2) Conexión financiamiento (config_db.php) — motus_financiamiento
$resultados[] = ['nombre' => '--- 2) financiamiento/config_db.php (motus_financiamiento) ---', 'ok' => null, 'mensaje' => '', 'detalle' => ''];
if (is_file(__DIR__ . '/config_db.php')) {
    try {
        require_once __DIR__ . '/config_db.php';
        probarConexion('Financiamiento (motus_financiamiento)', isset($pdo_financiamiento) ? $pdo_financiamiento : null, $resultados);
    } catch (Throwable $e) {
        $resultados[] = ['nombre' => 'Financiamiento', 'ok' => false, 'mensaje' => $e->getMessage(), 'detalle' => ''];
    }
} else {
    $resultados[] = ['nombre' => 'Financiamiento', 'ok' => false, 'mensaje' => 'No existe financiamiento/config_db.php', 'detalle' => ''];
}

// 3) Conexión manual con parámetros (para probar distintos host/db/user/pass)
$resultados[] = ['nombre' => '--- 3) Conexión manual (editar en el código) ---', 'ok' => null, 'mensaje' => '', 'detalle' => ''];
$hostManual = 'localhost';
$dbManual   = 'motus_financiamiento';
$userManual = 'financiamiento';
$passManual = 'Chicho1787$$$chicho';
try {
    $dsn = "mysql:host=$hostManual;dbname=$dbManual;charset=utf8mb4";
    $pdoManual = new PDO($dsn, $userManual, $passManual, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    probarConexion('Manual (motus_financiamiento)', $pdoManual, $resultados);
} catch (Throwable $e) {
    $resultados[] = ['nombre' => 'Manual', 'ok' => false, 'mensaje' => $e->getMessage(), 'detalle' => ''];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de conexiones</title>
    <style>
        body { font-family: sans-serif; max-width: 720px; margin: 24px auto; padding: 0 16px; background: #1a1a2e; color: #eee; }
        h1 { font-size: 1.25rem; }
        .bloque { margin: 12px 0; padding: 12px; border-radius: 8px; border: 1px solid #333; }
        .bloque.sep { border-color: transparent; margin: 16px 0 8px 0; color: #888; font-size: 0.9rem; }
        .ok { background: #0d3322; border-color: #1a5c3a; }
        .error { background: #3d1a1a; border-color: #6b2a2a; }
        .detalle { font-size: 0.85rem; color: #aaa; margin-top: 6px; }
        a { color: #6eb5ff; }
    </style>
</head>
<body>
    <h1>Prueba de conexiones a base de datos</h1>
    <p>Se probaron las siguientes conexiones. Si alguna falla, revise credenciales y que la BD exista en el servidor.</p>

    <?php foreach ($resultados as $r): ?>
        <?php if ($r['ok'] === null): ?>
            <div class="bloque sep"><?php echo htmlspecialchars($r['nombre']); ?></div>
        <?php elseif ($r['ok']): ?>
            <div class="bloque ok">
                <strong><?php echo htmlspecialchars($r['nombre']); ?></strong> — <?php echo htmlspecialchars($r['mensaje']); ?>
                <?php if ($r['detalle']): ?><div class="detalle"><?php echo htmlspecialchars($r['detalle']); ?></div><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bloque error">
                <strong><?php echo htmlspecialchars($r['nombre']); ?></strong> — <?php echo htmlspecialchars($r['mensaje']); ?>
                <?php if ($r['detalle']): ?><div class="detalle"><?php echo htmlspecialchars($r['detalle']); ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <p style="margin-top: 24px;"><a href="index.php">← Volver al formulario</a> &nbsp; <a href="ver_registros.php">Ver registros</a></p>
</body>
</html>
