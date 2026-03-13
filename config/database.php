<?php
// Configuración de la base de datos
// En producción (Digital Ocean, etc.) defina APP_DEBUG en el entorno o póngalo aquí a true para ver errores SQL en la respuesta.
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true');
}
// Dentro de Docker: conectar al servicio MySQL por nombre (motus_db)
// Fuera de Docker (XAMPP / Digital Ocean): usar localhost o el host del managed DB
$isDocker = file_exists('/.dockerenv');
if ($isDocker) {
    $host = 'motus_db';
    $port = '3306';
    $dbname = 'motus_baes';
    $username = 'motus_user';
    $password = 'motus_pass_2024';
} else {
    $host = 'localhost';
    $port = '3306';
    $dbname = 'solicitud_credito';
    $username = 'root';
    $password = '';
}
$charset = 'utf8mb4';

// DSN: usar TCP (host:port) para evitar socket "No such file or directory"
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

// Opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}
?>
