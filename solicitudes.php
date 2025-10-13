<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

// Obtener roles del usuario para restricciones
$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles);
$isGestor = in_array('ROLE_GESTOR', $userRoles);
$isBanco = in_array('ROLE_BANCO', $userRoles);

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito");
$totalSolicitudes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE estado = 'Nueva'");
$solicitudesNuevas = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Aprobado'");
$solicitudesAprobadas = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Rechazado'");
$solicitudesRechazadas = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Crédito - Solicitud de Crédito</title>
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        .badge-estado {
            font-size: 0.85em;
            padding: 6px 10px;
        }
        .estado-nueva { background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); }
        .estado-revision { background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%); }
        .estado-aprobada { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); }
        .estado-rechazada { background: linear-gradient(135deg, #e84393 0%, #fd79a8 100%); }
        .estado-completada { background: linear-gradient(135deg, #2d3436 0%, #636e72 100%); }
        
        /* Estilos para adjuntos */
        .adjunto-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .adjunto-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .adjunto-icon {
            font-size: 2rem;
            margin-right: 15px;
        }
        .adjunto-info {
            flex: 1;
        }
        .adjunto-acciones {
            display: flex;
            gap: 5px;
        }
        .tipo-archivo {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .tamaño-archivo {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .fecha-adjunto {
            font-size: 0.8rem;
            color: #6c757d;
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
                            <h2 class="mb-1">Solicitudes de Crédito</h2>
                            <p class="text-muted mb-0">Gestión de solicitudes de crédito vehicular</p>
                        </div>
                        <?php if (in_array('ROLE_GESTOR', $userRoles) || in_array('ROLE_ADMIN', $userRoles)): ?>
                        <div class="btn-group">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#solicitudModal" onclick="limpiarFormularioSolicitud()">
                                <i class="fas fa-plus me-2"></i>Nueva Solicitud
                            </button>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#infoModal">
                                <i class="fas fa-info-circle me-2"></i>Info
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $totalSolicitudes; ?></div>
                                <div class="stats-label">Total Solicitudes</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <div class="stats-number"><?php echo $solicitudesNuevas; ?></div>
                                <div class="stats-label">Nuevas</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <div class="stats-number"><?php echo $solicitudesAprobadas; ?></div>
                                <div class="stats-label">Aprobadas</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <div class="stats-number"><?php echo $solicitudesRechazadas; ?></div>
                                <div class="stats-label">Rechazadas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Solicitudes -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="solicitudesTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Cédula</th>
                                            <th>Vehículo</th>
                                            <th>Gestor</th>
                                            <th>Banco Asignado</th>
                                            <th>Estado</th>
                                            <!-- <th>Respuesta Banco</th> -->
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Cargar solicitudes directamente desde PHP
                                        // Construir consulta con filtro según el rol del usuario
                                        $sql = "
                                            SELECT s.*, 
                                                   u.nombre as gestor_nombre, u.apellido as gestor_apellido,
                                                   ub.nombre as banco_nombre, ub.apellido as banco_apellido,
                                                   b.nombre as banco_institucion,
                                                   COUNT(DISTINCT n.id) as total_notas
                                            FROM solicitudes_credito s
                                            LEFT JOIN usuarios u ON s.gestor_id = u.id
                                            LEFT JOIN usuarios_banco_solicitudes ubs ON s.id = ubs.solicitud_id AND ubs.estado = 'activo'
                                            LEFT JOIN usuarios ub ON ubs.usuario_banco_id = ub.id
                                            LEFT JOIN bancos b ON ub.banco_id = b.id
                                            LEFT JOIN notas_solicitud n ON s.id = n.solicitud_id
                                        ";
                                        
                                        // Aplicar filtro según el rol del usuario
                                        if (in_array('ROLE_BANCO', $userRoles) && !in_array('ROLE_ADMIN', $userRoles)) {
                                            // Usuario banco solo ve sus solicitudes asignadas
                                            $sql .= " WHERE ubs.usuario_banco_id = " . $_SESSION['user_id'];
                                        }
                                        
                                        $sql .= " GROUP BY s.id, u.nombre, u.apellido, ub.nombre, ub.apellido, b.nombre ORDER BY s.fecha_creacion DESC";
                                        
                                        $stmt = $pdo->query($sql);
                                        $solicitudes = $stmt->fetchAll();
                                        
                                        foreach ($solicitudes as $solicitud):
                                            $estadoClass = '';
                                            switch($solicitud['estado']) {
                                                case 'Nueva': $estadoClass = 'estado-nueva'; break;
                                                case 'En Revisión Banco': $estadoClass = 'estado-revision'; break;
                                                case 'Aprobada': $estadoClass = 'estado-aprobada'; break;
                                                case 'Rechazada': $estadoClass = 'estado-rechazada'; break;
                                                case 'Completada': $estadoClass = 'estado-completada'; break;
                                            }
                                            
                                            $respuestaClass = '';
                                            switch($solicitud['respuesta_banco']) {
                                                case 'Pendiente': $respuestaClass = 'estado-revision'; break;
                                                case 'Aprobado': $respuestaClass = 'estado-aprobada'; break;
                                                case 'Pre Aprobado': $respuestaClass = 'estado-revision'; break;
                                                case 'Rechazado': $respuestaClass = 'estado-rechazada'; break;
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $solicitud['id']; ?></td>
                                            <td><?php echo htmlspecialchars($solicitud['nombre_cliente']); ?></td>
                                            <td><?php echo htmlspecialchars($solicitud['cedula']); ?></td>
                                            <td><?php echo htmlspecialchars($solicitud['marca_auto'] ?? '-'); ?> <?php echo htmlspecialchars($solicitud['modelo_auto'] ?? ''); ?> <?php echo $solicitud['año_auto'] ?? ''; ?></td>
                                            <td><?php echo htmlspecialchars($solicitud['gestor_nombre'] . ' ' . $solicitud['gestor_apellido']); ?></td>
                                            <td>
                                                <?php if ($solicitud['banco_nombre']): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($solicitud['banco_nombre'] . ' ' . $solicitud['banco_apellido']); ?>
                                                        <?php if ($solicitud['banco_institucion']): ?>
                                                            <br><small>(<?php echo htmlspecialchars($solicitud['banco_institucion']); ?>)</small>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin asignar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge badge-estado <?php echo $estadoClass; ?>"><?php echo $solicitud['estado']; ?></span></td>
                                            <!-- <td><span class="badge badge-estado <?php echo $respuestaClass; ?>"><?php echo $solicitud['respuesta_banco']; ?></span></td> -->
                                            <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?></td>
                                            <td>
                                                <div class="btn-group-vertical btn-group-sm" role="group">
                                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                                        <button class="btn btn-info btn-action" onclick="verDetalles(<?php echo $solicitud['id']; ?>)" title="Ver Detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-primary btn-action" onclick="editarSolicitud(<?php echo $solicitud['id']; ?>)" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                                        <button class="btn btn-success btn-action" onclick="verMuro(<?php echo $solicitud['id']; ?>)" title="Ver Muro">
                                                            <i class="fas fa-comments"></i>
                                                        </button>
                                                        <button class="btn btn-warning btn-action" onclick="abrirModalAdjuntosDesdeTabla(<?php echo $solicitud['id']; ?>)" title="Gestionar Adjuntos">
                                                            <i class="fas fa-paperclip"></i>
                                                        </button>
                                                    </div>
                                                    <?php if (in_array('ROLE_BANCO', $userRoles) && $solicitud['estado'] === 'En Revisión Banco'): ?>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-success btn-action" onclick="abrirModalAprobacion(<?php echo $solicitud['id']; ?>)" title="Aprobar/Rechazar Solicitud">
                                                            <i class="fas fa-gavel"></i>
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (in_array('ROLE_ADMIN', $userRoles)): ?>
                                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                                        <button class="btn btn-warning btn-action" onclick="abrirModalCambioEstado(<?php echo $solicitud['id']; ?>)" title="Cambiar Estado (Solo Admin)">
                                                            <i class="fas fa-cogs"></i>
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (in_array('ROLE_ADMIN', $userRoles)): ?>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-danger btn-action" onclick="eliminarSolicitud(<?php echo $solicitud['id']; ?>)" title="Eliminar Solicitud (Solo Admin)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Solicitud -->
    <div class="modal fade" id="solicitudModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="solicitudModalLabel">
                        <i class="fas fa-file-alt me-2"></i>Nueva Solicitud de Crédito
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="solicitudForm" method="POST" action="api/solicitudes.php">
                    <div class="modal-body">
                        <input type="hidden" id="solicitud_id" name="id">
                        
                        <!-- Pestañas -->
                        <ul class="nav nav-tabs" id="solicitudTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="datos-generales-tab" data-bs-toggle="tab" data-bs-target="#datos-generales" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Datos Generales
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="perfil-financiero-tab" data-bs-toggle="tab" data-bs-target="#perfil-financiero" type="button" role="tab">
                                    <i class="fas fa-chart-line me-2"></i>Perfil Financiero
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="datos-auto-tab" data-bs-toggle="tab" data-bs-target="#datos-auto" type="button" role="tab">
                                    <i class="fas fa-car me-2"></i>Datos del Auto
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analisis-tab" data-bs-toggle="tab" data-bs-target="#analisis" type="button" role="tab">
                                    <i class="fas fa-comments me-2"></i>Análisis
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="adjuntos-tab" data-bs-toggle="tab" data-bs-target="#adjuntos" type="button" role="tab">
                                    <i class="fas fa-paperclip me-2"></i>Adjuntos
                                </button>
                            </li>
                            <?php if (!$isBanco): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="usuarios-banco-tab" data-bs-toggle="tab" data-bs-target="#usuarios-banco" type="button" role="tab">
                                    <i class="fas fa-university me-2"></i>Usuarios Banco
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="tab-content" id="solicitudTabContent">
                            <!-- Datos Generales -->
                            <div class="tab-pane fade show active" id="datos-generales" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Persona *</label>
                                            <select class="form-select" id="tipo_persona" name="tipo_persona" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Natural">Persona Natural</option>
                                                <option value="Juridica">Persona Jurídica</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="nombre_cliente" class="form-label">Nombre del Cliente *</label>
                                            <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="cedula" class="form-label"># de Cédula *</label>
                                            <input type="text" class="form-control" id="cedula" name="cedula" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="edad" class="form-label">Edad</label>
                                                    <input type="number" class="form-control" id="edad" name="edad">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="genero" class="form-label">Género</label>
                                                    <select class="form-select" id="genero" name="genero">
                                                        <option value="">Seleccionar...</option>
                                                        <option value="Masculino">Masculino</option>
                                                        <option value="Femenino">Femenino</option>
                                                        <option value="Otro">Otro</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" id="telefono" name="telefono">
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">E-mail</label>
                                            <input type="email" class="form-control" id="email" name="email">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="casado" name="casado" value="1">
                                                    <label class="form-check-label" for="casado">Casado</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="hijos" class="form-label">Hijos</label>
                                                    <input type="number" class="form-control" id="hijos" name="hijos" value="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Asignación de Banco -->
                                <h6 class="mb-3 mt-4">Asignación de Banco</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="banco_id" class="form-label">Usuario Banco Asignado</label>
                                            <select class="form-select" id="banco_id" name="banco_id">
                                                <option value="">Seleccionar usuario banco...</option>
                                                <?php
                                                // Obtener usuarios con rol ROLE_BANCO
                                                $stmt = $pdo->query("
                                                    SELECT u.id, u.nombre, u.apellido, b.nombre as banco_nombre
                                                    FROM usuarios u
                                                    LEFT JOIN bancos b ON u.banco_id = b.id
                                                    INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
                                                    INNER JOIN roles r ON ur.rol_id = r.id
                                                    WHERE r.nombre = 'ROLE_BANCO' AND u.activo = 1
                                                    ORDER BY u.nombre ASC
                                                ");
                                                $usuariosBanco = $stmt->fetchAll();
                                                foreach ($usuariosBanco as $usuario) {
                                                    $bancoInfo = $usuario['banco_nombre'] ? " ({$usuario['banco_nombre']})" : "";
                                                    echo "<option value=\"{$usuario['id']}\">{$usuario['nombre']} {$usuario['apellido']}{$bancoInfo}</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="form-text">Asignar un usuario banco para revisar esta solicitud</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="vendedor_id" class="form-label">Vendedor Asignado</label>
                                            <select class="form-select" id="vendedor_id" name="vendedor_id">
                                                <option value="">Seleccionar vendedor...</option>
                                                <?php
                                                // Obtener usuarios con rol ROLE_VENDEDOR
                                                $stmt = $pdo->query("
                                                    SELECT u.id, u.nombre, u.apellido
                                                    FROM usuarios u
                                                    INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
                                                    INNER JOIN roles r ON ur.rol_id = r.id
                                                    WHERE r.nombre = 'ROLE_VENDEDOR' AND u.activo = 1
                                                    ORDER BY u.nombre ASC
                                                ");
                                                $usuariosVendedor = $stmt->fetchAll();
                                                foreach ($usuariosVendedor as $usuario) {
                                                    echo "<option value=\"{$usuario['id']}\">{$usuario['nombre']} {$usuario['apellido']}</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="form-text">Asignar un vendedor para gestionar esta solicitud</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Dirección -->
                                <h6 class="mt-4 mb-3">Dirección</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="provincia" class="form-label">Provincia</label>
                                            <input type="text" class="form-control" id="provincia" name="provincia">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="distrito" class="form-label">Distrito</label>
                                            <input type="text" class="form-control" id="distrito" name="distrito">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="corregimiento" class="form-label">Corregimiento</label>
                                            <input type="text" class="form-control" id="corregimiento" name="corregimiento">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="barriada" class="form-label">Barriada</label>
                                            <input type="text" class="form-control" id="barriada" name="barriada">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="casa_edif" class="form-label">Casa / Edif</label>
                                            <input type="text" class="form-control" id="casa_edif" name="casa_edif">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="numero_casa_apto" class="form-label"># de casa / Apto</label>
                                            <input type="text" class="form-control" id="numero_casa_apto" name="numero_casa_apto">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección Completa</label>
                                    <textarea class="form-control" id="direccion" name="direccion" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <!-- Perfil Financiero -->
                            <div class="tab-pane fade" id="perfil-financiero" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="perfil_financiero" class="form-label">Perfil Financiero *</label>
                                            <select class="form-select" id="perfil_financiero" name="perfil_financiero" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Asalariado">Asalariado</option>
                                                <option value="Jubilado">Jubilado</option>
                                                <option value="Independiente">Independiente</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="ingreso" class="form-label">Ingreso $</label>
                                            <input type="number" class="form-control" id="ingreso" name="ingreso" step="0.01">
                                        </div>
                                        <div class="mb-3">
                                            <label for="tiempo_laborar" class="form-label">Tiempo de Laborar / Actividad Comercial</label>
                                            <input type="text" class="form-control" id="tiempo_laborar" name="tiempo_laborar">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nombre_empresa_negocio" class="form-label">Nombre de la Empresa / Negocio</label>
                                            <input type="text" class="form-control" id="nombre_empresa_negocio" name="nombre_empresa_negocio">
                                        </div>
                                        <div class="mb-3">
                                            <label for="estabilidad_laboral" class="form-label">Estabilidad Laboral</label>
                                            <input type="date" class="form-control" id="estabilidad_laboral" name="estabilidad_laboral">
                                        </div>
                                        <div class="mb-3">
                                            <label for="fecha_constitucion" class="form-label">Fecha de Constitución</label>
                                            <input type="date" class="form-control" id="fecha_constitucion" name="fecha_constitucion">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Datos del Auto -->
                            <div class="tab-pane fade" id="datos-auto" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="marca_auto" class="form-label">Marca</label>
                                            <input type="text" class="form-control" id="marca_auto" name="marca_auto">
                                        </div>
                                        <div class="mb-3">
                                            <label for="modelo_auto" class="form-label">Modelo</label>
                                            <input type="text" class="form-control" id="modelo_auto" name="modelo_auto">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="año_auto" class="form-label">Año</label>
                                                    <input type="number" class="form-control" id="año_auto" name="año_auto">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="kilometraje" class="form-label">Kilometraje</label>
                                                    <input type="number" class="form-control" id="kilometraje" name="kilometraje">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="precio_especial" class="form-label">Precio Especial</label>
                                            <input type="number" class="form-control" id="precio_especial" name="precio_especial" step="0.01">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="abono_porcentaje" class="form-label">Abono (%)</label>
                                                    <input type="number" class="form-control" id="abono_porcentaje" name="abono_porcentaje" step="0.01">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="abono_monto" class="form-label">Abono (Monto)</label>
                                                    <input type="number" class="form-control" id="abono_monto" name="abono_monto" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Análisis -->
                            <div class="tab-pane fade" id="analisis" role="tabpanel">
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label for="comentarios_gestor" class="form-label">Comentarios del Gestor</label>
                                        <textarea class="form-control" id="comentarios_gestor" name="comentarios_gestor" rows="4" maxlength="1000" placeholder="Escriba un breve análisis del perfil del cliente (máximo 1000 caracteres)"></textarea>
                                        <div class="form-text">Caracteres restantes: <span id="contador_comentarios">1000</span></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Adjuntos -->
                            <div class="tab-pane fade" id="adjuntos" role="tabpanel">
                                <div class="mt-3 text-center">
                                    <div class="card">
                                        <div class="card-body">
                                            <i class="fas fa-paperclip fa-3x text-muted mb-3"></i>
                                            <h5 class="card-title">Gestión de Adjuntos</h5>
                                            <p class="card-text text-muted">
                                                Gestiona los archivos adjuntos de esta solicitud de forma independiente.
                                            </p>
                                            <button type="button" class="btn btn-primary" onclick="abrirModalAdjuntos()">
                                                <i class="fas fa-folder-open me-2"></i>Abrir Gestor de Adjuntos
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Usuarios Banco -->
                            <?php if (!$isBanco): ?>
                            <div class="tab-pane fade" id="usuarios-banco" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <!-- Agregar Usuario Banco -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-user-plus me-2"></i>Asignar Usuario Banco
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label for="buscar_usuario_banco" class="form-label">Buscar Usuario Banco</label>
                                                        <input type="text" class="form-control" id="buscar_usuario_banco" placeholder="Escriba el nombre del banco o usuario...">
                                                        <div id="sugerencias_usuarios" class="list-group mt-2" style="display: none;"></div>
                                                    </div>
                                                    <button type="button" class="btn btn-success btn-sm" id="agregar_usuario_banco" disabled>
                                                        <i class="fas fa-plus me-1"></i>Agregar Usuario
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Muro de Información -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-comments me-2"></i>Muro de Información
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <textarea class="form-control" id="mensaje_muro" rows="3" placeholder="Escriba un mensaje para los usuarios banco asignados..."></textarea>
                                                    </div>
                                                    <button type="button" class="btn btn-primary btn-sm" id="enviar_mensaje">
                                                        <i class="fas fa-paper-plane me-1"></i>Enviar Mensaje
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabla de Usuarios Asignados -->
                                    <div class="mt-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-users me-2"></i>Usuarios Banco Asignados
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm" id="usuariosAsignadosTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Usuario</th>
                                                                <th>Banco</th>
                                                                <th>Estado</th>
                                                                <th>Fecha Asignación</th>
                                                                <th>Último Mensaje</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="usuariosAsignadosBody">
                                                            <!-- Se llenará dinámicamente -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Historial de Mensajes -->
                                    <div class="mt-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-history me-2"></i>Historial de Mensajes
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div id="historialMensajes" style="max-height: 300px; overflow-y: auto;">
                                                    <!-- Se llenará dinámicamente -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-info" id="btnCerrarDespuesAdjuntos" style="display: none;" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>Finalizar
                        </button>
                        <?php if (!$isBanco): ?>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Guardar Solicitud
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Información -->
    <div class="modal fade" id="infoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Información de Campos Requeridos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-danger mb-3">
                                <i class="fas fa-exclamation-circle me-2"></i>Campos Obligatorios
                            </h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-danger me-2"></i>
                                    <strong>Tipo de Persona</strong>
                                    <small class="text-muted ms-2">(Natural o Jurídica)</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-danger me-2"></i>
                                    <strong>Nombre del Cliente</strong>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-danger me-2"></i>
                                    <strong># de Cédula</strong>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-check-circle text-danger me-2"></i>
                                    <strong>Perfil Financiero</strong>
                                    <small class="text-muted ms-2">(Asalariado, Jubilado o Independiente)</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-info mb-3">
                                <i class="fas fa-lightbulb me-2"></i>Campos Recomendados
                            </h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-phone text-info me-2"></i>
                                    <strong>Teléfono</strong>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-envelope text-info me-2"></i>
                                    <strong>Email</strong>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-dollar-sign text-info me-2"></i>
                                    <strong>Ingreso Mensual</strong>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-car text-info me-2"></i>
                                    <strong>Datos del Vehículo</strong>
                                    <small class="text-muted ms-2">(Marca, Modelo, Año)</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-comments text-info me-2"></i>
                                    <strong>Comentarios del Gestor</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>Importante
                        </h6>
                        <p class="mb-0">
                            Aunque solo 4 campos son obligatorios, se recomienda completar la mayor cantidad de información posible 
                            para que el banco pueda realizar un análisis más preciso de la solicitud de crédito.
                        </p>
                    </div>
                    
                    <div class="alert alert-success">
                        <h6 class="alert-heading">
                            <i class="fas fa-tips me-2"></i>Consejos
                        </h6>
                        <ul class="mb-0">
                            <li>Los campos obligatorios están marcados con <span class="text-danger">*</span></li>
                            <li>Puedes navegar entre las pestañas para organizar la información</li>
                            <li>El sistema guardará automáticamente una nota cuando crees la solicitud</li>
                            <li>El banco podrá ver y comentar en el muro de tiempo</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#solicitudModal" onclick="limpiarFormularioSolicitud()">
                        <i class="fas fa-plus me-2"></i>Crear Solicitud
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalles -->
    <div class="modal fade" id="detallesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Detalles de la Solicitud
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContent">
                    <!-- Contenido se carga via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal del Muro de Tiempo -->
    <div class="modal fade" id="muroModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);">
                    <h5 class="modal-title">
                        <i class="fas fa-comments me-2"></i>Muro de Tiempo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Información de la Solicitud -->
                    <div class="row mb-4" id="muroSolicitudInfo">
                        <!-- Se carga via AJAX -->
                    </div>
                    
                    <!-- Formulario para nueva nota -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Nota</h6>
                        </div>
                        <div class="card-body">
                            <form id="notaForm">
                                <input type="hidden" id="nota_solicitud_id" name="solicitud_id">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nota_tipo_nota" class="form-label">Tipo de Nota</label>
                                            <select class="form-select" id="nota_tipo_nota" name="tipo_nota" required>
                                                <option value="Comentario">Comentario</option>
                                                <option value="Actualización">Actualización</option>
                                                <option value="Documento">Documento</option>
                                                <?php if (in_array('ROLE_BANCO', $userRoles)): ?>
                                                <option value="Respuesta Banco">Respuesta Banco</option>
                                                <?php endif; ?>
                                                <option value="Respuesta Cliente">Respuesta Cliente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nota_titulo" class="form-label">Título</label>
                                            <input type="text" class="form-control" id="nota_titulo" name="titulo" placeholder="Título de la nota">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nota_contenido" class="form-label">Contenido *</label>
                                    <textarea class="form-control" id="nota_contenido" name="contenido" rows="3" required placeholder="Escriba su nota aquí..."></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Nota
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de notas -->
                    <div id="muroNotasContainer">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando notas...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Adjuntos -->
    <div class="modal fade" id="adjuntosModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h5 class="modal-title">
                        <i class="fas fa-paperclip me-2"></i>Gestión de Adjuntos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Información de la Solicitud -->
                    <div class="row mb-4" id="adjuntosSolicitudInfo">
                        <!-- Se carga via JavaScript -->
                    </div>
                    
                    <!-- Formulario para subir adjuntos -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-upload me-2"></i>Subir Adjunto
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="adjuntoForm" enctype="multipart/form-data" onsubmit="return false;">
                                <input type="hidden" id="adjunto_solicitud_id" name="solicitud_id">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="archivo_adjunto" class="form-label">Seleccionar Archivo *</label>
                                            <input type="file" class="form-control" id="archivo_adjunto" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt" required>
                                            <div class="form-text">
                                                Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, TXT (Máximo 10MB)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="descripcion_adjunto" class="form-label">Descripción</label>
                                            <input type="text" class="form-control" id="descripcion_adjunto" name="descripcion" placeholder="Breve descripción del archivo">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary" onclick="subirAdjunto()">
                                        <i class="fas fa-upload me-2"></i>Subir Archivo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lista de adjuntos -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-paperclip me-2"></i>Archivos Adjuntos
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="adjuntosContainer">
                                <div class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Cargando adjuntos...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Aprobación/Rechazo -->
    <div class="modal fade" id="aprobacionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h5 class="modal-title">
                        <i class="fas fa-gavel me-2"></i>Decisión de Solicitud
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="aprobacionForm" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" id="aprobacion_solicitud_id" name="id">
                        
                        <!-- Información de la Solicitud -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Información de la Solicitud
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="aprobacionSolicitudInfo">
                                    <!-- Se carga via JavaScript -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ejecutivo del Banco -->
                        <div class="mb-3">
                            <label for="ejecutivo_banco" class="form-label">Ejecutivo del Banco *</label>
                            <input type="text" class="form-control" id="ejecutivo_banco" name="ejecutivo_banco" required>
                        </div>
                        
                        <!-- Tipo de Decisión -->
                        <div class="mb-4">
                            <label class="form-label">Decisión *</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="accion" id="aprobar" value="aprobar" required>
                                        <label class="form-check-label text-success fw-bold" for="aprobar">
                                            <i class="fas fa-check-circle me-2"></i>Aprobar Solicitud
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="accion" id="rechazar" value="rechazar" required>
                                        <label class="form-check-label text-danger fw-bold" for="rechazar">
                                            <i class="fas fa-times-circle me-2"></i>Rechazar Solicitud
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Campos para Aprobación -->
                        <div id="camposAprobacion" style="display: none;">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-check-circle me-2"></i>Términos de Aprobación
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="letra" class="form-label">Letra (Monto) *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="letra" name="letra" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="plazo" class="form-label">Plazo (Meses) *</label>
                                                <input type="number" class="form-control" id="plazo" name="plazo" min="1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="abono_banco" class="form-label">Abono</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="abono_banco" name="abono_banco" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="promocion" class="form-label">Promoción</label>
                                                <input type="text" class="form-control" id="promocion" name="promocion" placeholder="Ej: Tasa especial, descuento, etc.">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Campos para Rechazo -->
                        <div id="camposRechazo" style="display: none;">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-times-circle me-2"></i>Motivos de Rechazo
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="comentarios_ejecutivo_banco" class="form-label">Comentarios del Ejecutivo *</label>
                                        <textarea class="form-control" id="comentarios_ejecutivo_banco" name="comentarios_ejecutivo_banco" rows="4" placeholder="Explicar los motivos del rechazo..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" onclick="procesarAprobacion()">
                            <i class="fas fa-gavel me-2"></i>Procesar Decisión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Cambio de Estado (Solo Admin) -->
    <div class="modal fade" id="cambioEstadoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);">
                    <h5 class="modal-title">
                        <i class="fas fa-cogs me-2"></i>Cambiar Estado de Solicitud
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="cambioEstadoForm" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" id="cambio_estado_solicitud_id" name="solicitud_id">
                        
                        <!-- Información de la Solicitud -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Información de la Solicitud
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="cambioEstadoSolicitudInfo">
                                    <!-- Se carga via JavaScript -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado Actual -->
                        <div class="mb-3">
                            <label class="form-label">Estado Actual</label>
                            <div id="estado_actual_display" class="form-control-plaintext">
                                <!-- Se carga via JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Nuevo Estado -->
                        <div class="mb-4">
                            <label for="nuevo_estado" class="form-label">Nuevo Estado *</label>
                            <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                                <option value="">Seleccionar nuevo estado...</option>
                                <option value="Aprobada">✅ Aprobada</option>
                                <option value="Rechazada">❌ Rechazada</option>
                                <option value="Completada">🎉 Completada</option>
                                <option value="Desistimiento">🚫 Desistimiento</option>
                            </select>
                            <div class="form-text">Seleccione el nuevo estado para esta solicitud</div>
                        </div>
                        
                        <!-- Campo de Nota -->
                        <div class="mb-3">
                            <label for="nota_cambio_estado" class="form-label">Nota del Cambio *</label>
                            <textarea class="form-control" id="nota_cambio_estado" name="nota_cambio_estado" rows="4" required placeholder="Explique el motivo del cambio de estado..."></textarea>
                            <div class="form-text">Esta nota se agregará automáticamente al muro de la solicitud</div>
                        </div>
                        
                        <!-- Campos adicionales para estados específicos -->
                        <div id="campos_adicionales_estado" style="display: none;">
                            <!-- Se mostrarán campos específicos según el estado seleccionado -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" onclick="procesarCambioEstado()">
                            <i class="fas fa-cogs me-2"></i>Cambiar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Pasar información del rol a JavaScript
        window.userRoles = {
            isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>,
            isGestor: <?php echo $isGestor ? 'true' : 'false'; ?>,
            isBanco: <?php echo $isBanco ? 'true' : 'false'; ?>
        };

        // Función para abrir modal de aprobación
        function abrirModalAprobacion(solicitudId) {
            // Limpiar formulario
            $('#aprobacionForm')[0].reset();
            $('#camposAprobacion').hide();
            $('#camposRechazo').hide();
            $('#aprobacion_solicitud_id').val(solicitudId);
            
            // Obtener información de la solicitud
            $.ajax({
                url: 'api/solicitudes.php',
                type: 'GET',
                data: { id: solicitudId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const solicitud = response.data;
                        
                        // Mostrar información de la solicitud
                        $('#aprobacionSolicitudInfo').html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Cliente:</strong> ${solicitud.nombre_cliente}<br>
                                    <strong>Cédula:</strong> ${solicitud.cedula}<br>
                                    <strong>Vehículo:</strong> ${solicitud.marca_auto || 'N/A'} ${solicitud.modelo_auto || ''}
                                </div>
                                <div class="col-md-6">
                                    <strong>Gestor:</strong> ${solicitud.gestor_nombre} ${solicitud.gestor_apellido}<br>
                                    <strong>Estado:</strong> <span class="badge bg-info">${solicitud.estado}</span><br>
                                    <strong>Precio:</strong> $${solicitud.precio_especial || 'N/A'}
                                </div>
                            </div>
                        `);
                        
                        // Mostrar modal
                        $('#aprobacionModal').modal('show');
                    } else {
                        alert('Error al cargar la solicitud: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al cargar la información de la solicitud');
                }
            });
        }

        // Función para mostrar/ocultar campos según la decisión
        $(document).ready(function() {
            $('input[name="accion"]').change(function() {
                const accion = $(this).val();
                
                if (accion === 'aprobar') {
                    $('#camposAprobacion').show();
                    $('#camposRechazo').hide();
                    $('#letra, #plazo').prop('required', true);
                    $('#comentarios_ejecutivo_banco').prop('required', false);
                } else if (accion === 'rechazar') {
                    $('#camposAprobacion').hide();
                    $('#camposRechazo').show();
                    $('#letra, #plazo').prop('required', false);
                    $('#comentarios_ejecutivo_banco').prop('required', true);
                } else {
                    $('#camposAprobacion').hide();
                    $('#camposRechazo').hide();
                }
            });
        });

        // Función para procesar la aprobación
        function procesarAprobacion() {
            const formData = new FormData($('#aprobacionForm')[0]);
            
            // Validar formulario
            if (!$('#aprobacionForm')[0].checkValidity()) {
                $('#aprobacionForm')[0].reportValidity();
                return;
            }
            
            const accion = $('input[name="accion"]:checked').val();
            if (!accion) {
                alert('Por favor seleccione una decisión (Aprobar o Rechazar)');
                return;
            }
            
            // Confirmar acción
            const mensaje = accion === 'aprobar' ? 
                '¿Está seguro de que desea APROBAR esta solicitud?' : 
                '¿Está seguro de que desea RECHAZAR esta solicitud?';
                
            if (!confirm(mensaje)) {
                return;
            }
            
            // Enviar datos
            $.ajax({
                url: 'api/solicitudes.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#aprobacionModal').modal('hide');
                        // Recargar la tabla
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la decisión');
                }
            });
        }

        // Función para abrir modal de cambio de estado (Solo Admin)
        function abrirModalCambioEstado(solicitudId) {
            // Limpiar formulario
            $('#cambioEstadoForm')[0].reset();
            $('#campos_adicionales_estado').hide();
            $('#cambio_estado_solicitud_id').val(solicitudId);
            
            // Obtener información de la solicitud
            $.ajax({
                url: 'api/solicitudes.php',
                type: 'GET',
                data: { id: solicitudId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const solicitud = response.data;
                        
                        // Mostrar información de la solicitud
                        $('#cambioEstadoSolicitudInfo').html(`
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Cliente:</strong> ${solicitud.nombre_cliente}<br>
                                    <strong>Cédula:</strong> ${solicitud.cedula}<br>
                                    <strong>Vehículo:</strong> ${solicitud.marca_auto || 'N/A'} ${solicitud.modelo_auto || ''}
                                </div>
                                <div class="col-md-6">
                                    <strong>Gestor:</strong> ${solicitud.gestor_nombre} ${solicitud.gestor_apellido}<br>
                                    <strong>Precio:</strong> $${solicitud.precio_especial || 'N/A'}<br>
                                    <strong>Fecha Creación:</strong> ${new Date(solicitud.fecha_creacion).toLocaleDateString('es-ES')}
                                </div>
                            </div>
                        `);
                        
                        // Mostrar estado actual
                        const estadoClass = getEstadoClass(solicitud.estado);
                        $('#estado_actual_display').html(`
                            <span class="badge badge-estado ${estadoClass}">${solicitud.estado}</span>
                        `);
                        
                        // Mostrar modal
                        $('#cambioEstadoModal').modal('show');
                    } else {
                        alert('Error al cargar la solicitud: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al cargar la información de la solicitud');
                }
            });
        }

        // Función para procesar cambio de estado
        function procesarCambioEstado() {
            const formData = new FormData($('#cambioEstadoForm')[0]);
            
            // Validar formulario
            if (!$('#cambioEstadoForm')[0].checkValidity()) {
                $('#cambioEstadoForm')[0].reportValidity();
                return;
            }
            
            const nuevoEstado = $('#nuevo_estado').val();
            const nota = $('#nota_cambio_estado').val();
            
            if (!nuevoEstado || !nota.trim()) {
                alert('Por favor complete todos los campos requeridos');
                return;
            }
            
            // Confirmar cambio
            if (!confirm(`¿Está seguro de cambiar el estado a "${nuevoEstado}"?`)) {
                return;
            }
            
            // Enviar datos
            $.ajax({
                url: 'api/solicitudes.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#cambioEstadoModal').modal('hide');
                        // Recargar la tabla
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar el cambio de estado');
                }
            });
        }
    </script>
    <script src="js/solicitudes.js"></script>
    <script src="js/adjuntos.js"></script>
</body>
</html>
