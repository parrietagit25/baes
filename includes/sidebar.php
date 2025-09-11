<?php
// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener la página actual para marcar el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
$isAdmin = in_array('ROLE_ADMIN', $_SESSION['user_roles']);
?>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 px-0 sidebar">
    <div class="text-center py-4">
        <h4 class="text-white"><i class="fas fa-users me-2"></i>FaroV2</h4>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        
        <?php if ($isAdmin): ?>
        <a class="nav-link <?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>" href="usuarios.php">
            <i class="fas fa-users me-2"></i>Gestión de Usuarios
        </a>
        
        <a class="nav-link <?php echo ($current_page == 'roles.php') ? 'active' : ''; ?>" href="roles.php">
            <i class="fas fa-user-shield me-2"></i>Gestión de Roles
        </a>
        <?php endif; ?>
        
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
        </a>
    </nav>
</div>
