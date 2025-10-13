<?php
// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener la página actual para marcar el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
$isAdmin = in_array('ROLE_ADMIN', $_SESSION['user_roles']);
$isGestor = in_array('ROLE_GESTOR', $_SESSION['user_roles']);
$isBanco = in_array('ROLE_BANCO', $_SESSION['user_roles']);
$isVendedor = in_array('ROLE_VENDEDOR', $_SESSION['user_roles']);
?>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 px-0 sidebar">
    <div class="text-center py-4">
        <h4 class="text-white"><i class="fas fa-users me-2"></i>Solicitud de Crédito</h4>
    </div>
    <nav class="nav flex-column">
        <!-- Dashboard - Visible para todos -->
        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        
        <!-- Solicitudes - Visible para Admin, Gestor, Banco y Vendedor -->
        <?php if ($isAdmin || $isGestor || $isBanco || $isVendedor): ?>
        <a class="nav-link <?php echo ($current_page == 'solicitudes.php') ? 'active' : ''; ?>" href="solicitudes.php">
            <i class="fas fa-file-alt me-2"></i>Solicitudes de Crédito
        </a>
        <?php endif; ?>
        
        <!-- Gestión de Usuarios - Solo Admin -->
        <?php if ($isAdmin): ?>
        <a class="nav-link <?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>" href="usuarios.php">
            <i class="fas fa-users me-2"></i>Gestión de Usuarios
        </a>
        <?php endif; ?>
        
        <!-- Gestión de Roles - Solo Admin -->
        <?php if ($isAdmin): ?>
        <a class="nav-link <?php echo ($current_page == 'roles.php') ? 'active' : ''; ?>" href="roles.php">
            <i class="fas fa-user-shield me-2"></i>Gestión de Roles
        </a>
        <?php endif; ?>

        <!-- Gestión de Bancos - Solo Admin -->
        <?php if ($isAdmin): ?>
        <a class="nav-link <?php echo ($current_page == 'bancos.php') ? 'active' : ''; ?>" href="bancos.php">
            <i class="fas fa-university me-2"></i>Gestión de Bancos
        </a>
        <?php endif; ?>
        
        <!-- Integración Pipedrive - Admin y Gestor -->
        <?php if ($isAdmin || $isGestor): ?>
        <a class="nav-link <?php echo ($current_page == 'pipedrive.php') ? 'active' : ''; ?>" href="pipedrive.php">
            <i class="fas fa-plug me-2"></i>Integración Pipedrive
        </a>
        <?php endif; ?>
        
        <!-- Cerrar Sesión - Visible para todos -->
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
        </a>
    </nav>
</div>
