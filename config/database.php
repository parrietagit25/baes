<?php
// Configuración de la base de datos
// Dentro de Docker: conectar al servicio MySQL por nombre (motus_db)
// Fuera de Docker (XAMPP): localhost con credenciales locales
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
