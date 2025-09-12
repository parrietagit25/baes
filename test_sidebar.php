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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Sidebar - Solicitud de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                            <h2 class="mb-1">Test de Sidebar Centralizado</h2>
                            <p class="text-muted mb-0">Verificando que el menú lateral funcione correctamente</p>
                        </div>
                    </div>

                    <!-- Contenido de Prueba -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Verificación del Sidebar</h5>
                                </div>
                                <div class="card-body">
                                    <h6>✅ Sidebar Centralizado Implementado</h6>
                                    <p>El menú lateral ahora está centralizado en <code>includes/sidebar.php</code></p>
                                    
                                    <h6>🔗 Opciones del Menú Disponibles:</h6>
                                    <ul>
                                        <li><strong>Dashboard:</strong> Vista principal con estadísticas</li>
                                        <?php if ($isAdmin): ?>
                                        <li><strong>Gestión de Usuarios:</strong> Administrar usuarios del sistema</li>
                                        <li><strong>Gestión de Roles:</strong> Administrar roles y permisos</li>
                                        <?php endif; ?>
                                        <li><strong>Cerrar Sesión:</strong> Salir del sistema</li>
                                    </ul>

                                    <h6>🎯 Beneficios de la Centralización:</h6>
                                    <ul>
                                        <li><strong>Consistencia:</strong> Mismo menú en todas las páginas</li>
                                        <li><strong>Mantenimiento:</strong> Cambios en un solo archivo</li>
                                        <li><strong>Navegación:</strong> Todas las opciones siempre visibles</li>
                                        <li><strong>Indicador Activo:</strong> Muestra la página actual</li>
                                    </ul>

                                    <hr>
                                    <h6>🧪 Pruebas Recomendadas:</h6>
                                    <div class="d-grid gap-2 d-md-flex">
                                        <a href="dashboard.php" class="btn btn-primary">
                                            <i class="fas fa-tachometer-alt me-2"></i>Ir al Dashboard
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <a href="usuarios.php" class="btn btn-success">
                                            <i class="fas fa-users me-2"></i>Ir a Usuarios
                                        </a>
                                        <a href="roles.php" class="btn btn-info">
                                            <i class="fas fa-user-shield me-2"></i>Ir a Roles
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Sistema -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Usuario</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                    <p><strong>Roles:</strong> <?php echo implode(', ', $_SESSION['user_roles']); ?></p>
                                    <p><strong>Es Admin:</strong> <?php echo $isAdmin ? 'Sí' : 'No'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Estado del Sidebar</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Archivo:</strong> includes/sidebar.php</p>
                                    <p><strong>Página Actual:</strong> <?php echo basename($_SERVER['PHP_SELF']); ?></p>
                                    <p><strong>Menú Activo:</strong> <?php echo basename($_SERVER['PHP_SELF'], '.php'); ?></p>
                                    <p><strong>Acceso Admin:</strong> <?php echo $isAdmin ? 'Permitido' : 'Denegado'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
