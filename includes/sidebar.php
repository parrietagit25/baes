<?php
// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener la página actual para marcar el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/banco_scope_helper.php';
$isAdmin = in_array('ROLE_ADMIN', $_SESSION['user_roles']);
$isGestor = in_array('ROLE_GESTOR', $_SESSION['user_roles']);
$isBanco = motus_es_vista_banco($_SESSION['user_roles'] ?? []);
$isAdminBanco = motus_es_admin_banco($_SESSION['user_roles'] ?? []);
$isVendedor = in_array('ROLE_VENDEDOR', $_SESSION['user_roles']);

$paginasSolicitudesMenu = [
    'solicitudes.php',
    'historico_solicitudes.php',
    'mis_propuestas_banco.php',
    'sol_financiamiento.php',
    'subir_reporte_reservas.php',
];
$paginasAdministracionMenu = [
    'usuarios.php',
    'roles.php',
    'bancos.php',
    'usuarios_banco.php',
    'ejecutivos_ventas.php',
    'configuracion.php',
];
$paginasFeriaMenu = ['ferias.php', 'feria_panel.php'];
$paginasReportesMenu = ['reportes.php', 'seguimiento_financiamiento.php', 'encuestas_resultados.php'];

$menuSolicitudesActivo = in_array($current_page, $paginasSolicitudesMenu, true);
$menuAdministracionActivo = in_array($current_page, $paginasAdministracionMenu, true);
$menuFeriaActivo = in_array($current_page, $paginasFeriaMenu, true);
$menuReportesActivo = in_array($current_page, $paginasReportesMenu, true);

$enReportes = ($current_page === 'reportes.php');
$reportSubmenu = $enReportes ? ($_GET['submenu'] ?? 'usuarios') : '';
?>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 px-0 sidebar">
    <div class="text-center py-4">
        <h4 class="text-white"><i class="fas fa-users me-2"></i>Solicitud de Crédito</h4>
    </div>
    <nav class="nav flex-column" id="sidebarAccordion">
        <!-- Dashboard - Visible para todos -->
        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        
        <!-- Solicitudes -->
        <?php if ($isAdmin || $isGestor || $isBanco || $isVendedor): ?>
        <button class="nav-link w-100 border-0 text-start d-flex align-items-center <?php echo $menuSolicitudesActivo ? 'active' : ''; ?>"
                type="button" data-bs-toggle="collapse" data-bs-target="#menuSolicitudes"
                aria-expanded="<?php echo $menuSolicitudesActivo ? 'true' : 'false'; ?>" aria-controls="menuSolicitudes">
            <i class="fas fa-folder-open me-2"></i>
            <strong>Solicitudes</strong>
            <i class="fas fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse <?php echo $menuSolicitudesActivo ? 'show' : ''; ?>" id="menuSolicitudes" data-bs-parent="#sidebarAccordion">
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'solicitudes.php') ? 'active' : ''; ?>" href="solicitudes.php">
                <i class="fas fa-file-alt me-2"></i>Solicitud de Crédito
            </a>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'historico_solicitudes.php') ? 'active' : ''; ?>" href="historico_solicitudes.php">
                <i class="fas fa-archive me-2"></i>Histórico de Solicitudes
            </a>
            <?php if ($isBanco): ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'mis_propuestas_banco.php') ? 'active' : ''; ?>" href="mis_propuestas_banco.php">
                <i class="fas fa-hand-holding-usd me-2"></i><?php echo $isAdminBanco ? 'Propuestas del banco' : 'Mis propuestas'; ?>
            </a>
            <?php endif; ?>
            <?php if ($isAdmin || $isGestor): ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'sol_financiamiento.php') ? 'active' : ''; ?>" href="sol_financiamiento.php">
                <i class="fas fa-file-invoice-dollar me-2"></i>Sol Financiamiento
            </a>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'subir_reporte_reservas.php') ? 'active' : ''; ?>" href="subir_reporte_reservas.php">
                <i class="fas fa-file-upload me-2"></i>Subir Reportes de Reservas
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Administración -->
        <?php if ($isAdmin || $isGestor): ?>
        <button class="nav-link w-100 border-0 text-start d-flex align-items-center <?php echo $menuAdministracionActivo ? 'active' : ''; ?>"
                type="button" data-bs-toggle="collapse" data-bs-target="#menuAdministracion"
                aria-expanded="<?php echo $menuAdministracionActivo ? 'true' : 'false'; ?>" aria-controls="menuAdministracion">
            <i class="fas fa-cogs me-2"></i>
            <strong>Administración</strong>
            <i class="fas fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse <?php echo $menuAdministracionActivo ? 'show' : ''; ?>" id="menuAdministracion" data-bs-parent="#sidebarAccordion">
            <?php if ($isAdmin): ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'usuarios.php') ? 'active' : ''; ?>" href="usuarios.php">
                <i class="fas fa-users me-2"></i>Gestión de Usuarios
            </a>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'roles.php') ? 'active' : ''; ?>" href="roles.php">
                <i class="fas fa-user-shield me-2"></i>Gestión de Roles
            </a>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'bancos.php') ? 'active' : ''; ?>" href="bancos.php">
                <i class="fas fa-university me-2"></i>Gestión de Bancos
            </a>
            <?php else: ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'usuarios_banco.php') ? 'active' : ''; ?>" href="usuarios_banco.php">
                <i class="fas fa-university me-2"></i>Usuarios Banco
            </a>
            <?php endif; ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'ejecutivos_ventas.php') ? 'active' : ''; ?>" href="ejecutivos_ventas.php">
                <i class="fas fa-user-tie me-2"></i>Ejecutivos de Ventas
            </a>
            <?php if ($isAdmin): ?>
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'configuracion.php') ? 'active' : ''; ?>" href="configuracion.php">
                <i class="fas fa-sliders-h me-2"></i>Configuración
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Feria -->
        <?php if ($isAdmin || $isGestor): ?>
        <button class="nav-link w-100 border-0 text-start d-flex align-items-center <?php echo $menuFeriaActivo ? 'active' : ''; ?>"
                type="button" data-bs-toggle="collapse" data-bs-target="#menuFeria"
                aria-expanded="<?php echo $menuFeriaActivo ? 'true' : 'false'; ?>" aria-controls="menuFeria">
            <i class="fas fa-store me-2"></i>
            <strong>Feria</strong>
            <i class="fas fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse <?php echo $menuFeriaActivo ? 'show' : ''; ?>" id="menuFeria" data-bs-parent="#sidebarAccordion">
            <a class="nav-link ps-4 py-2 small <?php echo $menuFeriaActivo ? 'active' : ''; ?>" href="ferias.php">
                <i class="fas fa-store-alt me-2"></i>Feria
            </a>
        </div>
        <?php endif; ?>

        <!-- Reportes -->
        <?php if ($isAdmin || $isGestor): ?>
        <button class="nav-link w-100 border-0 text-start d-flex align-items-center <?php echo $menuReportesActivo ? 'active' : ''; ?>"
                type="button" data-bs-toggle="collapse" data-bs-target="#menuReportes"
                aria-expanded="<?php echo $menuReportesActivo ? 'true' : 'false'; ?>" aria-controls="menuReportes">
            <i class="fas fa-chart-bar me-2"></i>
            <strong>Reportes</strong>
            <i class="fas fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse <?php echo $menuReportesActivo ? 'show' : ''; ?>" id="menuReportes" data-bs-parent="#sidebarAccordion">
            <a class="nav-link ps-4 py-2 small <?php echo ($current_page === 'seguimiento_financiamiento.php') ? 'active' : ''; ?>" href="seguimiento_financiamiento.php">
                <i class="fas fa-chart-line me-2"></i>Seguimiento
            </a>
            <?php if ($isAdmin): ?>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'usuarios') ? 'active' : ''; ?>" href="reportes.php?submenu=usuarios">
                <i class="fas fa-users me-1"></i> Rep. Usuarios
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'vendedores') ? 'active' : ''; ?>" href="reportes.php?submenu=vendedores">
                <i class="fas fa-user-tie me-1"></i> Rep. Vendedores
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'sucursales') ? 'active' : ''; ?>" href="reportes.php?submenu=sucursales">
                <i class="fas fa-store me-1"></i> Rep. Sucursales
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'tiempo') ? 'active' : ''; ?>" href="reportes.php?submenu=tiempo">
                <i class="fas fa-clock me-1"></i> Rep. Tiempo
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'banco') ? 'active' : ''; ?>" href="reportes.php?submenu=banco">
                <i class="fas fa-university me-1"></i> Rep. Banco
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'emails') ? 'active' : ''; ?>" href="reportes.php?submenu=emails">
                <i class="fas fa-envelope me-1"></i> Rep. Correos
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'encuestas') ? 'active' : ''; ?>" href="reportes.php?submenu=encuestas">
                <i class="fas fa-poll me-1"></i> Rep. Encuestas
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'telemetria') ? 'active' : ''; ?>" href="reportes.php?submenu=telemetria">
                <i class="fas fa-stopwatch me-1"></i> Rep. Telemetría
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'fin_publica') ? 'active' : ''; ?>" href="reportes.php?submenu=fin_publica">
                <i class="fas fa-file-invoice-dollar me-1"></i> Sol. Fin. (público)
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'fin_enlazada') ? 'active' : ''; ?>" href="reportes.php?submenu=fin_enlazada">
                <i class="fas fa-link me-1"></i> Sol. Fin. + Motus
            </a>
            <a class="nav-link ps-4 py-1 small <?php echo ($reportSubmenu === 'vehiculos') ? 'active' : ''; ?>" href="reportes.php?submenu=vehiculos">
                <i class="fas fa-car me-1"></i> Rep. Vehículo
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Cerrar Sesión - Visible para todos -->
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
        </a>
    </nav>
</div>
