<?php
/**
 * Validación de acceso por roles
 */

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener roles del usuario
$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles);
$isGestor = in_array('ROLE_GESTOR', $userRoles);
$isBanco = in_array('ROLE_BANCO', $userRoles);
$isVendedor = in_array('ROLE_VENDEDOR', $userRoles);

// Obtener la página actual
$current_page = basename($_SERVER['PHP_SELF']);

// Definir páginas permitidas por rol
$paginasAdmin = ['dashboard.php', 'usuarios.php', 'roles.php', 'bancos.php', 'solicitudes.php', 'pipedrive.php'];
$paginasGestor = ['dashboard.php', 'solicitudes.php', 'pipedrive.php'];
$paginasBanco = ['dashboard.php', 'solicitudes.php'];
$paginasVendedor = ['dashboard.php', 'solicitudes.php'];

// Función para verificar acceso
function verificarAcceso($pagina, $roles, $paginasPermitidas) {
    if (in_array($pagina, $paginasPermitidas)) {
        return true;
    }
    return false;
}

// Verificar acceso según el rol
$accesoPermitido = false;

if ($isAdmin) {
    $accesoPermitido = verificarAcceso($current_page, $userRoles, $paginasAdmin);
} elseif ($isGestor) {
    $accesoPermitido = verificarAcceso($current_page, $userRoles, $paginasGestor);
} elseif ($isBanco) {
    $accesoPermitido = verificarAcceso($current_page, $userRoles, $paginasBanco);
} elseif ($isVendedor) {
    $accesoPermitido = verificarAcceso($current_page, $userRoles, $paginasVendedor);
} else {
    // Usuario sin roles válidos - permitir acceso al dashboard al menos
    $accesoPermitido = ($current_page === 'dashboard.php');
}

// Si no tiene acceso, redirigir al dashboard (solo si no estamos ya en el dashboard)
if (!$accesoPermitido && $current_page !== 'dashboard.php') {
    header('Location: dashboard.php');
    exit();
}
?>
