<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';

// Verificar si el usuario es administrador
$isAdmin = in_array('ROLE_ADMIN', $_SESSION['user_roles']);

// Obtener estadísticas del sistema
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
$totalUsuarios = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
$usuariosActivos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
$totalRoles = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE primer_acceso = 1");
$primerAcceso = $stmt->fetch()['total'];

// Obtener usuarios recientes
$stmt = $pdo->query("
    SELECT u.nombre, u.apellido, u.email, u.fecha_creacion, GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles
    FROM usuarios u
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    GROUP BY u.id
    ORDER BY u.fecha_creacion DESC
    LIMIT 5
");
$usuariosRecientes = $stmt->fetchAll();

// Obtener estadísticas por rol
$stmt = $pdo->query("
    SELECT r.nombre, COUNT(ur.usuario_id) as cantidad
    FROM roles r
    LEFT JOIN usuario_roles ur ON r.id = ur.rol_id
    GROUP BY r.id, r.nombre
    ORDER BY cantidad DESC
");
$statsPorRol = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Solicitud de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: #3498db;
            color: #fff;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .btn-action {
            margin: 0 2px;
            border-radius: 8px;
        }
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                                            <div>
                        <h2 class="mb-1">Dashboard de Solicitud de Crédito</h2>
                        <p class="text-muted mb-0">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($isAdmin): ?>
                        <a href="usuarios.php" class="btn btn-primary">
                            <i class="fas fa-users me-2"></i>Gestionar Usuarios
                        </a>
                        <a href="roles.php" class="btn btn-success">
                            <i class="fas fa-user-shield me-2"></i>Gestionar Roles
                        </a>
                        <?php endif; ?>
                    </div>
                    </div>

                    <!-- Estadísticas Principales -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-users fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $totalUsuarios; ?></h3>
                                    <p class="mb-0">Total Usuarios</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-user-check fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $usuariosActivos; ?></h3>
                                    <p class="mb-0">Usuarios Activos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-user-shield fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $totalRoles; ?></h3>
                                    <p class="mb-0">Roles Disponibles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-key fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $primerAcceso; ?></h3>
                                    <p class="mb-0">Primer Acceso</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido del Dashboard -->
                    <div class="row">
                        <!-- Usuarios Recientes -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Usuarios Recientes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Usuario</th>
                                                    <th>Email</th>
                                                    <th>Roles</th>
                                                    <th>Fecha Registro</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usuariosRecientes as $usuario): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($usuario['roles'] ?? 'Sin roles'); ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estadísticas por Rol -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribución por Rol</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($statsPorRol as $stat): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($stat['nombre']); ?></span>
                                        <span class="fw-bold"><?php echo $stat['cantidad']; ?> usuarios</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Acciones Rápidas -->
                            <?php if ($isAdmin): ?>
                            <div class="card mt-3">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="usuarios.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-plus me-2"></i>Nuevo Usuario
                                        </a>
                                        <a href="roles.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-plus me-2"></i>Nuevo Rol
                                        </a>
                                        <a href="usuarios.php" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-list me-2"></i>Ver Todos los Usuarios
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

</body>
</html>
