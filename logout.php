<?php
session_start();

try {
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/includes/usuario_actividad_helper.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            motus_actividad_registrar($pdo, 'logout', [
                'usuario_id' => (int) $_SESSION['user_id'],
                'pagina' => 'logout.php',
                'detalle' => 'Cierre de sesión',
                'url_path' => '/logout.php',
            ]);
        }
    }
} catch (Throwable $e) {
    error_log('logout actividad: ' . $e->getMessage());
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: index.php');
exit();
