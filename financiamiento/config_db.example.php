<?php
/**
 * Configuración de la base de datos del módulo financiamiento.
 * Copie este archivo como config_db.php y ajuste las credenciales.
 * En GoDaddy: base de datos motus_financiamiento, usuario y contraseña del cPanel.
 */

$host     = 'localhost';
$dbname   = 'motus_financiamiento';
$username = 'financiamiento';
$password = 'SU_CONTRASEÑA';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo_financiamiento = new PDO($dsn, $username, $password, $options);
