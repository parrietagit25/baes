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
            margin-bottom: 20px;
        }
        .metric-card {
            transition: transform 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-5px);
        }
        .badge-estado-nueva { background: #ffc107; }
        .badge-estado-revision { background: #17a2b8; }
        .badge-estado-aprobada { background: #28a745; }
        .badge-estado-rechazada { background: #dc3545; }
        .badge-estado-completada { background: #007bff; }
        .badge-estado-desistimiento { background: #6c757d; }
        .tiempo-critico { color: #dc3545; font-weight: bold; }
        .tiempo-normal { color: #28a745; }
        .tiempo-advertencia { color: #ffc107; }
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

                    <!-- Estadísticas por Estado -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-file-alt fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $totalSolicitudes; ?></h3>
                                    <p class="mb-0 small">Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-star fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $estadoNueva; ?></h3>
                                    <p class="mb-0 small">Nuevas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-clock fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $estadoRevision; ?></h3>
                                    <p class="mb-0 small">En Revisión</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-check-circle fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $estadoAprobada; ?></h3>
                                    <p class="mb-0 small">Aprobadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-times-circle fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $estadoRechazada; ?></h3>
                                    <p class="mb-0 small">Rechazadas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card metric-card text-center border-0" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                                <div class="card-body py-3">
                                    <i class="fas fa-flag-checkered fa-2x mb-2 opacity-75"></i>
                                    <h3 class="mb-1"><?php echo $estadoCompletada; ?></h3>
                                    <p class="mb-0 small">Completadas</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Métricas de Tiempo -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-hourglass-half text-warning fa-3x mb-3"></i>
                                    <h3 class="mb-1"><?php echo $tiempoPromedioNueva; ?>h</h3>
                                    <p class="text-muted mb-0">Tiempo Promedio Estado "Nueva"</p>
                                    <small class="text-muted">Hasta primer cambio</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-stopwatch text-info fa-3x mb-3"></i>
                                    <h3 class="mb-1"><?php echo $tiempoPromedioRevision; ?>h</h3>
                                    <p class="text-muted mb-0">Tiempo Promedio en Revisión</p>
                                    <small class="text-muted">En Revisión Banco</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check text-success fa-3x mb-3"></i>
                                    <h3 class="mb-1"><?php echo $tiempoPromedioAprobacion; ?> días</h3>
                                    <p class="text-muted mb-0">Tiempo Promedio Aprobación</p>
                                    <small class="text-muted">Desde creación hasta aprobada</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                                    <h3 class="mb-1"><?php echo $solicitudesCriticas; ?></h3>
                                    <p class="text-muted mb-0">Solicitudes Críticas</p>
                                    <small class="text-muted">> 7 días sin cambios</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tablas de Información -->
                    <div class="row">
                        <!-- Solicitudes que Requieren Atención -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-circle me-2"></i>Solicitudes que Requieren Atención
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Cliente</th>
                                                    <th>Gestor</th>
                                                    <th>Estado</th>
                                                    <th>Tiempo sin Cambios</th>
                                                    <th>Creada Hace</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($solicitudesRecientes)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">
                                                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                                                        ¡Excelente! No hay solicitudes pendientes de atención
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($solicitudesRecientes as $sol): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($sol['nombre_cliente']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($sol['cedula']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($sol['gestor_nombre'] . ' ' . $sol['gestor_apellido']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = 'badge-estado-' . strtolower(str_replace(' ', '-', str_replace(' Banco', '', $sol['estado'])));
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $sol['estado']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $horas = $sol['horas_sin_cambios'];
                                                        $dias = floor($horas / 24);
                                                        $horasRestantes = $horas % 24;
                                                        
                                                        $clase = 'tiempo-normal';
                                                        if ($horas > 168) { // > 7 días
                                                            $clase = 'tiempo-critico';
                                                        } elseif ($horas > 72) { // > 3 días
                                                            $clase = 'tiempo-advertencia';
                                                        }
                                                        
                                                        echo "<span class='$clase'>";
                                                        if ($dias > 0) {
                                                            echo "$dias días, $horasRestantes hrs";
                                                        } else {
                                                            echo "$horasRestantes horas";
                                                        }
                                                        echo "</span>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $horasCreacion = $sol['horas_desde_creacion'];
                                                        $diasCreacion = floor($horasCreacion / 24);
                                                        echo $diasCreacion > 0 ? "$diasCreacion días" : round($horasCreacion) . " hrs";
                                                        ?>
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

                        <!-- Tiempos por Estado -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>Tiempos por Estado
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($tiemposPorEstado as $tiempo): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold"><?php echo $tiempo['estado']; ?></span>
                                            <span class="badge bg-secondary"><?php echo $tiempo['cantidad']; ?> solicitudes</span>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-chart-line me-1"></i>
                                            Promedio: <strong><?php echo round($tiempo['promedio_horas'], 1); ?>h</strong> |
                                            Min: <?php echo round($tiempo['min_horas'], 1); ?>h |
                                            Max: <?php echo round($tiempo['max_horas'], 1); ?>h
                                        </div>
                                        <hr class="my-2">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
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


