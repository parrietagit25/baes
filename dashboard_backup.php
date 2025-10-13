<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

// Verificar roles del usuario
$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles);
$isBanco = in_array('ROLE_BANCO', $userRoles);
$isGestor = in_array('ROLE_GESTOR', $userRoles);
$isVendedor = in_array('ROLE_VENDEDOR', $userRoles);
$usuarioId = $_SESSION['user_id'];

// ========================================
// ESTADÍSTICAS DE SOLICITUDES POR ESTADO
// ========================================

// Total de solicitudes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito");
$totalSolicitudes = $stmt->fetch()['total'];

// Solicitudes por estado
$stmt = $pdo->query("
    SELECT estado, COUNT(*) as total 
    FROM solicitudes_credito 
    GROUP BY estado
");
$solicitudesPorEstado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$estadoNueva = $solicitudesPorEstado['Nueva'] ?? 0;
$estadoRevision = $solicitudesPorEstado['En Revisión Banco'] ?? 0;
$estadoAprobada = $solicitudesPorEstado['Aprobada'] ?? 0;
$estadoRechazada = $solicitudesPorEstado['Rechazada'] ?? 0;
$estadoCompletada = $solicitudesPorEstado['Completada'] ?? 0;
$estadoDesistimiento = $solicitudesPorEstado['Desistimiento'] ?? 0;

// ========================================
// MÉTRICAS DE TIEMPO PROMEDIO POR ESTADO
// ========================================

// Tiempo promedio en estado "Nueva" (desde creación hasta primer cambio de estado)
$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_actualizacion)) as promedio_horas
    FROM solicitudes_credito
    WHERE estado != 'Nueva' AND fecha_actualizacion > fecha_creacion
");
$tiempoPromedioNueva = round($stmt->fetch()['promedio_horas'] ?? 0, 1);

// Tiempo promedio en "En Revisión Banco"
$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_actualizacion)) as promedio_horas
    FROM solicitudes_credito
    WHERE estado = 'En Revisión Banco'
");
$tiempoPromedioRevision = round($stmt->fetch()['promedio_horas'] ?? 0, 1);

// Tiempo promedio total desde creación hasta completada/aprobada
$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(DAY, fecha_creacion, fecha_actualizacion)) as promedio_dias
    FROM solicitudes_credito
    WHERE estado IN ('Completada', 'Aprobada')
");
$tiempoPromedioTotal = round($stmt->fetch()['promedio_dias'] ?? 0, 1);

// Tiempo promedio hasta aprobación
$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(DAY, fecha_creacion, fecha_actualizacion)) as promedio_dias
    FROM solicitudes_credito
    WHERE estado = 'Aprobada'
");
$tiempoPromedioAprobacion = round($stmt->fetch()['promedio_dias'] ?? 0, 1);

// ========================================
// SOLICITUDES CRÍTICAS (Más de 7 días sin cambios)
// ========================================

$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM solicitudes_credito
    WHERE estado NOT IN ('Completada', 'Rechazada', 'Desistimiento')
    AND TIMESTAMPDIFF(DAY, fecha_actualizacion, NOW()) > 7
");
$solicitudesCriticas = $stmt->fetch()['total'];

// ========================================
// SOLICITUDES RECIENTES CON TIEMPOS
// ========================================

$stmt = $pdo->query("
    SELECT s.*, 
           u.nombre as gestor_nombre, 
           u.apellido as gestor_apellido,
           TIMESTAMPDIFF(HOUR, s.fecha_creacion, NOW()) as horas_desde_creacion,
           TIMESTAMPDIFF(HOUR, s.fecha_actualizacion, NOW()) as horas_sin_cambios
    FROM solicitudes_credito s
    LEFT JOIN usuarios u ON s.gestor_id = u.id
    WHERE s.estado NOT IN ('Completada', 'Rechazada', 'Desistimiento')
    ORDER BY s.fecha_actualizacion ASC
    LIMIT 10
");
$solicitudesRecientes = $stmt->fetchAll();

// ========================================
// DISTRIBUCIÓN DE TIEMPOS POR ESTADO
// ========================================

$stmt = $pdo->query("
    SELECT 
        estado,
        COUNT(*) as cantidad,
        AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_actualizacion)) as promedio_horas,
        MIN(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_actualizacion)) as min_horas,
        MAX(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_actualizacion)) as max_horas
    FROM solicitudes_credito
    WHERE fecha_actualizacion > fecha_creacion
    GROUP BY estado
");
$tiemposPorEstado = $stmt->fetchAll();
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
                            <h2 class="mb-1">
                                <i class="fas fa-chart-line me-2"></i>Dashboard de Solicitudes de Crédito
                            </h2>
                            <p class="text-muted mb-0">
                                Métricas de rendimiento y tiempos - <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="solicitudes.php" class="btn btn-primary">
                                <i class="fas fa-file-alt me-2"></i>Ver Solicitudes
                            </a>
                            <?php if ($isAdmin): ?>
                            <a href="usuarios.php" class="btn btn-success">
                                <i class="fas fa-users me-2"></i>Gestionar Usuarios
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Estadísticas Principales -->
                    <div class="row mb-4">
                        <?php if ($isBanco): ?>
                        <!-- Estadísticas para usuario banco -->
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-file-alt fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $totalSolicitudesAsignadas; ?></h3>
                                    <p class="mb-0">Total Asignadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-clock fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $solicitudesPendientes; ?></h3>
                                    <p class="mb-0">Pendientes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-check-circle fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $solicitudesAprobadas; ?></h3>
                                    <p class="mb-0">Aprobadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center border-0" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white;">
                                <div class="card-body">
                                    <i class="fas fa-times-circle fa-3x mb-3 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $solicitudesRechazadas; ?></h3>
                                    <p class="mb-0">Rechazadas</p>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Estadísticas para administradores y otros roles -->
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
                        <?php endif; ?>
                    </div>

                    <!-- Contenido del Dashboard -->
                    <div class="row">
                        <?php if ($isBanco): ?>
                        <!-- Solicitudes Asignadas para usuario banco -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Solicitudes Asignadas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Cliente</th>
                                                    <th>Gestor</th>
                                                    <th>Estado</th>
                                                    <th>Respuesta Banco</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($solicitudesAsignadas)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                        No hay solicitudes asignadas
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($solicitudesAsignadas as $solicitud): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($solicitud['nombre_cliente']); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($solicitud['cedula']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($solicitud['gestor_nombre'] . ' ' . $solicitud['gestor_apellido']); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $estadoClass = '';
                                                        switch($solicitud['estado']) {
                                                            case 'Nueva': $estadoClass = 'bg-warning'; break;
                                                            case 'En Revisión Banco': $estadoClass = 'bg-info'; break;
                                                            case 'Aprobada': $estadoClass = 'bg-success'; break;
                                                            case 'Rechazada': $estadoClass = 'bg-danger'; break;
                                                            case 'Completada': $estadoClass = 'bg-primary'; break;
                                                            default: $estadoClass = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $estadoClass; ?>"><?php echo htmlspecialchars($solicitud['estado']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $respuestaClass = '';
                                                        switch($solicitud['respuesta_banco']) {
                                                            case 'Pendiente': $respuestaClass = 'bg-warning'; break;
                                                            case 'Aprobado': $respuestaClass = 'bg-success'; break;
                                                            case 'Pre Aprobado': $respuestaClass = 'bg-info'; break;
                                                            case 'Rechazado': $respuestaClass = 'bg-danger'; break;
                                                            default: $respuestaClass = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $respuestaClass; ?>"><?php echo htmlspecialchars($solicitud['respuesta_banco']); ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y', strtotime($solicitud['fecha_creacion'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <a href="solicitudes.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Usuarios Recientes para otros roles -->
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
                        <?php endif; ?>

                        <!-- Panel lateral -->
                        <div class="col-md-4">
                            <?php if ($isBanco): ?>
                            <!-- Panel de acciones para usuario banco -->
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Acciones Rápidas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="solicitudes.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-list me-2"></i>Ver Todas las Solicitudes
                                        </a>
                                        <a href="solicitudes.php?estado=Nueva" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-clock me-2"></i>Pendientes de Revisión
                                        </a>
                                        <a href="solicitudes.php?respuesta=Pendiente" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-edit me-2"></i>Requieren Respuesta
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumen de trabajo -->
                            <div class="card mt-3">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Resumen de Trabajo</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Asignadas:</span>
                                            <strong><?php echo $totalSolicitudesAsignadas; ?></strong>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Pendientes:</span>
                                            <strong class="text-warning"><?php echo $solicitudesPendientes; ?></strong>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Aprobadas:</span>
                                            <strong class="text-success"><?php echo $solicitudesAprobadas; ?></strong>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Rechazadas:</span>
                                            <strong class="text-danger"><?php echo $solicitudesRechazadas; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Estadísticas por Rol para otros usuarios -->
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
