<?php
/**
 * @deprecated Redirige al reporte de encuestas (Reportes > Rep. Encuestas)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [], true)) {
    header('Location: dashboard.php');
    exit();
}
header('Location: reportes.php?submenu=encuestas', true, 302);
exit();
