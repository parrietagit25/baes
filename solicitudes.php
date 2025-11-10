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

// Obtener estadísticas (filtrar por usuario banco si aplica)
if ($isBanco && !$isAdmin) {
    // Usuario banco solo ve sus solicitudes asignadas
    $filtroBanco = "AND EXISTS (
        SELECT 1 FROM usuarios_banco_solicitudes ubs 
        WHERE ubs.solicitud_id = solicitudes_credito.id 
        AND ubs.usuario_banco_id = " . $_SESSION['user_id'] . "
        AND ubs.estado = 'activo'
    )";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE 1=1 $filtroBanco");
    $totalSolicitudes = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE estado = 'Nueva' $filtroBanco");
    $solicitudesNuevas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Aprobado' $filtroBanco");
    $solicitudesAprobadas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Rechazado' $filtroBanco");
    $solicitudesRechazadas = $stmt->fetch()['total'];
} elseif ($isGestor && !$isAdmin) {
    // Gestor solo ve sus solicitudes asignadas
    $filtroGestor = "AND gestor_id = " . $_SESSION['user_id'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE 1=1 $filtroGestor");
    $totalSolicitudes = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE estado = 'Nueva' $filtroGestor");
    $solicitudesNuevas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Aprobado' $filtroGestor");
    $solicitudesAprobadas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Rechazado' $filtroGestor");
    $solicitudesRechazadas = $stmt->fetch()['total'];
} else {
    // Admin ve todas las solicitudes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito");
    $totalSolicitudes = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE estado = 'Nueva'");
    $solicitudesNuevas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Aprobado'");
    $solicitudesAprobadas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_credito WHERE respuesta_banco = 'Rechazado'");
    $solicitudesRechazadas = $stmt->fetch()['total'];
}
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
                                            <?php if ($isBanco): ?>
                                            <th>Cédula</th>
                                            <?php else: ?>
                                            <th>Respuestas del Banco</th>
                                            <?php endif; ?>
                                            <th>Vehículo</th>
                                            <th>Gestor</th>
                                            <?php if (!$isBanco): ?>
                                            <th>Banco Asignado</th>
                                            <?php endif; ?>
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
                                                     (SELECT COUNT(*) FROM usuarios_banco_solicitudes WHERE solicitud_id = s.id AND estado = 'activo') as total_usuarios_banco,
                                                     s.evaluacion_seleccionada,
                                                     (SELECT ubs.usuario_banco_id FROM evaluaciones_banco e 
                                                      INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id 
                                                      WHERE e.id = s.evaluacion_seleccionada) as usuario_banco_id_seleccionado
                                              FROM solicitudes_credito s
                                              LEFT JOIN usuarios u ON s.gestor_id = u.id
                                          ";
                                        
                                                                                  // Aplicar filtro según el rol del usuario
                                          if (in_array('ROLE_BANCO', $userRoles) && !in_array('ROLE_ADMIN', $userRoles)) {
                                              // Usuario banco solo ve sus solicitudes asignadas
                                              $sql .= " WHERE EXISTS (
                                                  SELECT 1 FROM usuarios_banco_solicitudes ubs 
                                                  WHERE ubs.solicitud_id = s.id 
                                                  AND ubs.usuario_banco_id = " . $_SESSION['user_id'] . "
                                                  AND ubs.estado = 'activo'
                                              )";
                                          } elseif (in_array('ROLE_GESTOR', $userRoles) && !in_array('ROLE_ADMIN', $userRoles)) {
                                              // Gestor solo ve sus solicitudes asignadas
                                              $sql .= " WHERE s.gestor_id = " . $_SESSION['user_id'];
                                          }
                                        
                                        $sql .= " ORDER BY s.fecha_creacion DESC";
                                        
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
                                            <?php if ($isBanco): ?>
                                            <td><?php echo htmlspecialchars($solicitud['cedula']); ?></td>
                                            <?php else: ?>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="verRespuestasBancoAdmin(<?php echo $solicitud['id']; ?>)" title="Ver Respuestas del Banco">
                                                    <i class="fas fa-clipboard-list me-1"></i>Ver Respuestas
                                                </button>
                                            </td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($solicitud['marca_auto'] ?? '-'); ?> <?php echo htmlspecialchars($solicitud['modelo_auto'] ?? ''); ?> <?php echo $solicitud['año_auto'] ?? ''; ?></td>
                                            <td>
                                                <?php if ($isAdmin): ?>
                                                    <?php 
                                                    $gestorNombre = htmlspecialchars($solicitud['gestor_nombre'] . ' ' . $solicitud['gestor_apellido'], ENT_QUOTES, 'UTF-8');
                                                    $gestorId = $solicitud['gestor_id'] ?? null;
                                                    ?>
                                                    <a href="javascript:void(0);" 
                                                       class="text-primary text-decoration-underline cambiar-gestor-link" 
                                                       data-solicitud-id="<?php echo $solicitud['id']; ?>"
                                                       data-gestor-nombre="<?php echo htmlspecialchars($gestorNombre, ENT_QUOTES, 'UTF-8'); ?>"
                                                       data-gestor-id="<?php echo $gestorId ? $gestorId : ''; ?>"
                                                       title="Click para cambiar gestor">
                                                        <?php echo $gestorNombre ?: 'Sin asignar'; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($solicitud['gestor_nombre'] . ' ' . $solicitud['gestor_apellido']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$isBanco): ?>
                                            <td>
                                                <?php 
                                                $totalBancos = $solicitud['total_usuarios_banco'] ?? 0;
                                                if ($totalBancos > 0): ?>
                                                    <button class="btn btn-sm btn-info" onclick="verUsuariosBanco(<?php echo $solicitud['id']; ?>)" title="Ver Usuarios Banco Asignados">
                                                        <i class="fas fa-users me-1"></i><?php echo $totalBancos; ?> Usuario<?php echo $totalBancos > 1 ? 's' : ''; ?>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin asignar</span>
                                                <?php endif; ?>
                                            </td>
                                                                                          <?php endif; ?>
                                              <td><span class="badge badge-estado <?php echo $estadoClass; ?>"><?php echo htmlspecialchars($solicitud['estado'] ?? 'N/A'); ?></span></td>
                                              <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?></td>
                                              <td>
                                                  <?php
                                                  // Para usuarios banco: verificar si hay una evaluación seleccionada y si el usuario actual es el dueño
                                                  $mostrarBotonesAcciones = true;
                                                  if ($isBanco && !$isAdmin) {
                                                      $evaluacionSeleccionada = $solicitud['evaluacion_seleccionada'] ?? null;
                                                      if ($evaluacionSeleccionada) {
                                                          // Obtener el usuario_banco_id del usuario actual para esta solicitud
                                                          $stmtUsuarioBanco = $pdo->prepare("
                                                              SELECT usuario_banco_id 
                                                              FROM usuarios_banco_solicitudes 
                                                              WHERE solicitud_id = ? AND usuario_banco_id = ? AND estado = 'activo'
                                                              LIMIT 1
                                                          ");
                                                          $stmtUsuarioBanco->execute([$solicitud['id'], $_SESSION['user_id']]);
                                                          $asignacionUsuario = $stmtUsuarioBanco->fetch();
                                                          
                                                          $usuarioBancoIdActual = $asignacionUsuario['usuario_banco_id'] ?? null;
                                                          $usuarioBancoIdSeleccionado = $solicitud['usuario_banco_id_seleccionado'] ?? null;
                                                          
                                                          // Solo mostrar botones si el usuario actual es el dueño de la evaluación seleccionada
                                                          if ($usuarioBancoIdActual != $usuarioBancoIdSeleccionado) {
                                                              $mostrarBotonesAcciones = false;
                                                          }
                                                      }
                                                  }
                                                  ?>
                                                  <?php if ($mostrarBotonesAcciones): ?>
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
                                                  <?php else: ?>
                                                  <span class="text-muted"><small>Propuesta seleccionada</small></span>
                                                  <?php endif; ?>
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
                <form id="solicitudForm" method="POST" action="javascript:void(0);">
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
                            <li class="nav-item" role="presentation" id="cita-firma-tab-li" style="display: none;">
                                <button class="nav-link" id="cita-firma-tab" data-bs-toggle="tab" data-bs-target="#cita-firma" type="button" role="tab">
                                    <i class="fas fa-calendar-check me-2"></i>Cita y Firma
                                </button>
                            </li>
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
                                        <div class="mb-3">
                                            <label for="email_pipedrive" class="form-label">Email PipeDrive</label>
                                            <input type="email" class="form-control" id="email_pipedrive" name="email_pipedrive">
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
                                          <div class="mb-3">
                                              <label for="profesion" class="form-label">Profesión</label>
                                              <input type="text" class="form-control" id="profesion" name="profesion">
                                          </div>
                                          <div class="mb-3">
                                              <label for="ocupacion" class="form-label">Ocupación</label>
                                              <input type="text" class="form-control" id="ocupacion" name="ocupacion">
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
                                          <div class="mb-3">
                                              <label for="continuidad_laboral" class="form-label">Continuidad Laboral</label>
                                              <input type="text" class="form-control" id="continuidad_laboral" name="continuidad_laboral">
                                          </div>
                                      </div>
                                </div>
                            </div>
                            
                            <!-- Datos del Auto -->
                            <div class="tab-pane fade" id="datos-auto" role="tabpanel">
                                <div class="mt-3">
                                    <!-- Botón para agregar vehículo (solo para admin y gestor) -->
                                    <?php if ($isAdmin || $isGestor): ?>
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-success" onclick="agregarVehiculo()">
                                            <i class="fas fa-plus me-2"></i>Agregar Vehículo
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Lista de vehículos -->
                                    <div id="listaVehiculos"></div>
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
                                    <!-- Agregar Usuario Banco -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user-plus me-2"></i>Asignar Usuario Banco
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <label for="buscar_usuario_banco" class="form-label">Buscar Usuario Banco</label>
                                                    <input type="text" class="form-control" id="buscar_usuario_banco" placeholder="Escriba el nombre del banco o usuario...">
                                                    <div id="sugerencias_usuarios" class="list-group mt-2" style="display: none;"></div>
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    <button type="button" class="btn btn-success btn-sm" id="agregar_usuario_banco" disabled>
                                                        <i class="fas fa-plus me-1"></i>Agregar Usuario
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
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Cita y Firma -->
                            <div class="tab-pane fade" id="cita-firma" role="tabpanel">
                                <div class="mt-3">
                                    <!-- Formulario para crear cita -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-calendar-plus me-2"></i>Agendar Cita
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <form id="citaForm">
                                                <div class="row">
                                                                                                          <div class="col-md-4">
                                                          <div class="mb-3">
                                                             <label for="fecha_cita" class="form-label">Fecha de la Cita *</label>
                                                             <input type="date" class="form-control" id="fecha_cita" name="fecha_cita">
                                                          </div>
                                                      </div>
                                                      <div class="col-md-4">
                                                          <div class="mb-3">
                                                             <label for="hora_cita" class="form-label">Hora de la Cita *</label>
                                                             <input type="time" class="form-control" id="hora_cita" name="hora_cita">
                                                          </div>
                                                      </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <label for="comentarios_cita" class="form-label">Comentarios</label>
                                                            <textarea class="form-control" id="comentarios_cita" name="comentarios" rows="3" placeholder="Ingrese comentarios sobre la cita..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <button type="button" class="btn btn-success" onclick="guardarCita()">
                                                            <i class="fas fa-save me-2"></i>Guardar Cita
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabla de Citas -->
                                    <div class="mt-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-list me-2"></i>Registro de Citas
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm" id="citasTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Fecha</th>
                                                                <th>Hora</th>
                                                                <th>Comentarios</th>
                                                                <th>Estado Asistencia</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="citasTableBody">
                                                            <!-- Se llenará dinámicamente -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-info" id="btnCerrarDespuesAdjuntos" style="display: none;" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>Finalizar
                        </button>
                                                  <?php if (!$isBanco): ?>
                          <button type="submit" class="btn btn-success" id="btnGuardarSolicitud" onclick="event.preventDefault(); event.stopPropagation(); guardarSolicitud(); return false;">
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
                    
                    <!-- Pestañas de Vehículos -->
                    <ul class="nav nav-tabs" id="vehiculosTabs" role="tablist">
                        <!-- Se generan dinámicamente -->
                    </ul>
                    
                    <!-- Contenido de las pestañas -->
                    <div class="tab-content" id="vehiculosTabContent">
                        <!-- Se generan dinámicamente -->
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
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="archivo_adjunto" class="form-label">Seleccionar Archivos *</label>
                                            <input type="file" class="form-control" id="archivo_adjunto" name="archivo[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt" required>
                                            <div class="form-text">
                                                Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, TXT (Máximo 10MB por archivo)
                                                <br><strong>Puedes seleccionar múltiples archivos a la vez</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="descripcion_adjunto" class="form-label">Descripción (aplicará a todos los archivos)</label>
                                            <input type="text" class="form-control" id="descripcion_adjunto" name="descripcion" placeholder="Breve descripción de los archivos">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary" onclick="subirAdjunto()">
                                        <i class="fas fa-upload me-2"></i>Subir Archivos
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
                        <input type="hidden" id="aprobacion_solicitud_id" name="solicitud_id">
                        
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
                        
                        <!-- Selección de Vehículo -->
                        <div class="mb-4">
                            <label for="vehiculo_evaluacion" class="form-label">Vehículo a Evaluar *</label>
                            <select class="form-select" id="vehiculo_evaluacion" name="vehiculo_evaluacion" required>
                                <option value="">Seleccione un vehículo</option>
                            </select>
                        </div>
                        
                        <!-- Tipo de Decisión -->
                        <div class="mb-4">
                            <label for="decision_banco" class="form-label">Decisión *</label>
                            <select class="form-select" id="decision_banco" name="decision" required onchange="mostrarCamposDecision(this.value)">
                                <option value="">Seleccione una opción</option>
                                <option value="preaprobado">Preaprobado</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="aprobado_condicional">Aprobado Condicional</option>
                                <option value="rechazado">Rechazado</option>
                            </select>
                        </div>
                        
                        <!-- Campos Condicionales -->
                        <div id="camposDecision" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clipboard-list me-2"></i>Detalles de la Evaluación
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="valor_financiar" class="form-label">Valor a Financiar</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="valor_financiar" name="valor_financiar" step="0.01" min="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="abono_evaluacion" class="form-label">Abono</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="abono_evaluacion" name="abono_evaluacion" step="0.01" min="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="plazo_evaluacion" class="form-label">Plazo (Meses)</label>
                                                <input type="number" class="form-control" id="plazo_evaluacion" name="plazo_evaluacion" min="1" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="letra_evaluacion" class="form-label">Letra (Monto)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="letra_evaluacion" name="letra_evaluacion" step="0.01" min="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="promocion_evaluacion" class="form-label">Promoción</label>
                                                <input type="text" class="form-control" id="promocion_evaluacion" name="promocion_evaluacion" placeholder="Ej: Tasa especial, descuento, etc." disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- Columna vacía para mantener el layout -->
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comentarios_evaluacion" class="form-label">Comentarios</label>
                                        <textarea class="form-control" id="comentarios_evaluacion" name="comentarios_evaluacion" rows="3" placeholder="Comentarios adicionales..." disabled></textarea>
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
                        
                        <!-- Nota para estado Completada -->
                        <div id="nota_completada" class="alert alert-info d-none" role="alert">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Importante
                            </h6>
                            <p class="mb-0">
                                <strong>Los datos viajarán a OCTO para su facturación</strong> y el proceso en MOTUS para esta solicitud será culminado.
                            </p>
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
          // Pasar ID del usuario a JavaScript
          window.userId = <?php echo $_SESSION['user_id']; ?>;

        // Función para abrir modal de aprobación
        function abrirModalAprobacion(solicitudId) {
            // Limpiar formulario
            $('#aprobacionForm')[0].reset();
            $('#camposDecision').hide();
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
                        
                        // Cargar vehículos
                        cargarVehiculosParaEvaluacion(solicitudId);
                    } else {
                        alert('Error al cargar la solicitud: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al cargar la información de la solicitud');
                }
            });
        }

        // Función para cargar vehículos para evaluación
        function cargarVehiculosParaEvaluacion(solicitudId) {
            $.ajax({
                url: 'api/vehiculos_solicitud.php',
                type: 'GET',
                data: { solicitud_id: solicitudId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#vehiculo_evaluacion');
                        $select.empty().append('<option value="">Seleccione un vehículo</option>');
                        
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(vehiculo) {
                                const text = `${vehiculo.marca || 'N/A'} ${vehiculo.modelo || ''}`;
                                $select.append(`<option value="${vehiculo.id}">${text}</option>`);
                            });
                        } else {
                            $select.append('<option value="">No hay vehículos registrados</option>');
                        }
                        
                        // Mostrar modal después de cargar vehículos
                        $('#aprobacionModal').modal('show');
                    } else {
                        $('#vehiculo_evaluacion').empty().append('<option value="">Error al cargar vehículos</option>');
                        $('#aprobacionModal').modal('show');
                    }
                },
                error: function() {
                    $('#vehiculo_evaluacion').empty().append('<option value="">Error al cargar vehículos</option>');
                    $('#aprobacionModal').modal('show');
                }
            });
        }

        // Función para mostrar/ocultar campos según la decisión
        window.mostrarCamposDecision = function(decision) {
            // Ocultar todos los campos primero
            const campos = ['#valor_financiar', '#abono_evaluacion', '#plazo_evaluacion', '#letra_evaluacion', '#promocion_evaluacion', '#comentarios_evaluacion'];
            campos.forEach(function(campo) {
                $(campo).prop('disabled', true);
                $(campo).prop('required', false);
            });
            
            // Mostrar contenedor
            $('#camposDecision').show();
            
            // Habilitar campos según la decisión
            if (decision === 'rechazado') {
                // Solo habilitar comentarios
                $('#comentarios_evaluacion').prop('disabled', false);
                $('#comentarios_evaluacion').prop('required', true);
            } else if (decision === 'preaprobado' || decision === 'aprobado' || decision === 'aprobado_condicional') {
                // Habilitar todos los campos
                campos.forEach(function(campo) {
                    $(campo).prop('disabled', false);
                });
                $('#comentarios_evaluacion').prop('required', false);
            } else {
                // Ocultar contenedor si no hay selección
                $('#camposDecision').hide();
            }
        };

        // Función para procesar la aprobación
        function procesarAprobacion() {
            const formData = new FormData($('#aprobacionForm')[0]);
            
            // Validar formulario
            if (!$('#aprobacionForm')[0].checkValidity()) {
                $('#aprobacionForm')[0].reportValidity();
                return;
            }
            
            const decision = $('#decision_banco').val();
            if (!decision) {
                alert('Por favor seleccione una decisión');
                return;
            }
            
            // Confirmar acción
            const mensaje = '¿Está seguro de procesar esta decisión: "' + decision.replace('_', ' ').toUpperCase() + '"?';
                
            if (!confirm(mensaje)) {
                return;
            }
            
            // Enviar datos a la API de evaluaciones del banco
            $.ajax({
                url: 'api/evaluaciones_banco.php',
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
                error: function(xhr) {
                    let errorMsg = 'Error al procesar la decisión';
                    if (xhr.responseText) {
                        try {
                            const error = JSON.parse(xhr.responseText);
                            errorMsg = error.message || errorMsg;
                        } catch(e) {
                            errorMsg = xhr.responseText;
                        }
                    }
                    alert(errorMsg);
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

    <!-- Modal para ver Usuarios Banco Asignados -->
    <div class="modal fade" id="modalUsuariosBanco" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>Usuarios Banco Asignados
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="usuariosBancoContent">
                        <div class="text-center">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Cargando...</span>
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

      <!-- Modal para Cambiar Gestor (Solo Admin) -->
      <div class="modal fade" id="modalCambiarGestor" tabindex="-1">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header bg-primary text-white">
                      <h5 class="modal-title">
                          <i class="fas fa-user-edit me-2"></i>Cambiar Gestor
                      </h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <input type="hidden" id="gestor_solicitud_id">
                      <div class="mb-3">
                          <label class="form-label">Gestor Actual</label>
                          <input type="text" class="form-control" id="gestor_actual" readonly>
                      </div>
                      <div class="mb-3">
                          <label for="nuevo_gestor_id" class="form-label">Nuevo Gestor *</label>
                          <select class="form-select" id="nuevo_gestor_id" required>
                              <option value="">Seleccionar gestor...</option>
                          </select>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                          <i class="fas fa-times me-2"></i>Cancelar
                      </button>
                      <button type="button" class="btn btn-primary" onclick="guardarCambioGestor()">
                          <i class="fas fa-save me-2"></i>Guardar Cambio
                      </button>
                  </div>
              </div>
          </div>
      </div>

      <script>
        // Función para ver usuarios banco asignados
        function verUsuariosBanco(solicitudId) {
            $('#usuariosBancoContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            `);
            
            $.ajax({
                url: 'api/usuarios_banco_solicitudes.php',
                type: 'GET',
                data: { solicitud_id: solicitudId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Usuario</th><th>Banco</th><th>Email</th><th>Teléfono</th><th>Estado</th></tr></thead><tbody>';
                        
                        response.data.forEach(function(usuario) {
                            const estadoClass = usuario.estado === 'activo' ? 'success' : 'secondary';
                            const estadoText = usuario.estado === 'activo' ? 'Activo' : 'Inactivo';
                            
                            html += `
                                <tr>
                                    <td>${usuario.usuario_nombre} ${usuario.usuario_apellido}</td>
                                    <td>${usuario.banco_nombre || 'N/A'}</td>
                                    <td>${usuario.usuario_email || 'N/A'}</td>
                                    <td>${usuario.usuario_telefono || 'N/A'}</td>
                                    <td><span class="badge bg-${estadoClass}">${estadoText}</span></td>
                                </tr>
                            `;
                        });
                        
                        html += '</tbody></table></div>';
                        $('#usuariosBancoContent').html(html);
                    } else {
                        $('#usuariosBancoContent').html('<div class="alert alert-info">No hay usuarios banco asignados a esta solicitud.</div>');
                    }
                    
                    $('#modalUsuariosBanco').modal('show');
                },
                error: function() {
                    $('#usuariosBancoContent').html('<div class="alert alert-danger">Error al cargar los usuarios banco asignados.</div>');
                    $('#modalUsuariosBanco').modal('show');
                }
                          });
          }

          // Event delegation para links de cambiar gestor
          $(document).on('click', '.cambiar-gestor-link', function(e) {
              e.preventDefault();
              const solicitudId = $(this).data('solicitud-id');
              const gestorActual = $(this).data('gestor-nombre') || 'Sin asignar';
              const gestorIdActual = $(this).data('gestor-id');
              
              abrirModalCambiarGestor(solicitudId, gestorActual, gestorIdActual);
          });

          // Función para abrir modal de cambiar gestor
          function abrirModalCambiarGestor(solicitudId, gestorActual, gestorIdActual) {
              // Configurar valores del modal
              $('#gestor_solicitud_id').val(solicitudId);
              $('#gestor_actual').val(gestorActual || 'Sin asignar');
              $('#nuevo_gestor_id').val('').html('<option value="">Seleccionar gestor...</option>');
              
              // Cargar lista de gestores
              $.ajax({
                  url: 'api/solicitudes.php',
                  type: 'GET',
                  data: { gestores: true },
                  dataType: 'json',
                  success: function(response) {
                      if (response.success && response.data.length > 0) {
                          response.data.forEach(function(gestor) {
                              const selected = (gestor.id == gestorIdActual) ? 'selected' : '';
                              const option = `<option value="${gestor.id}" ${selected}>${gestor.nombre} ${gestor.apellido}</option>`;
                              $('#nuevo_gestor_id').append(option);
                          });
                      } else {
                          $('#nuevo_gestor_id').html('<option value="">No hay gestores disponibles</option>');
                      }
                      
                      // Mostrar modal
                      $('#modalCambiarGestor').modal('show');
                  },
                  error: function() {
                      alert('Error al cargar la lista de gestores');
                  }
              });
          }

          // Función para guardar el cambio de gestor
          function guardarCambioGestor() {
              const solicitudId = $('#gestor_solicitud_id').val();
              const nuevoGestorId = $('#nuevo_gestor_id').val();
              
              if (!nuevoGestorId) {
                  alert('Por favor seleccione un gestor');
                  return;
              }
              
              // Mostrar indicador de carga
              const btnGuardar = $('#modalCambiarGestor').find('button[onclick="guardarCambioGestor()"]');
              const textoOriginal = btnGuardar.html();
              btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
              
              $.ajax({
                  url: 'api/solicitudes.php',
                  type: 'POST',
                  data: {
                      action: 'cambiar_gestor',
                      solicitud_id: solicitudId,
                      nuevo_gestor_id: nuevoGestorId
                  },
                  dataType: 'json',
                  success: function(response) {
                      btnGuardar.prop('disabled', false).html(textoOriginal);
                      
                      if (response.success) {
                          $('#modalCambiarGestor').modal('hide');
                          alert('Gestor actualizado correctamente');
                          // Recargar la página para actualizar la tabla
                          location.reload();
                      } else {
                          alert('Error: ' + (response.message || 'Error al actualizar el gestor'));
                      }
                  },
                  error: function(xhr) {
                      btnGuardar.prop('disabled', false).html(textoOriginal);
                      let mensaje = 'Error de conexión';
                      try {
                          const errorResponse = JSON.parse(xhr.responseText);
                          if (errorResponse.message) {
                              mensaje = errorResponse.message;
                          }
                      } catch (e) {
                          // Usar mensaje por defecto
                      }
                      alert('Error: ' + mensaje);
                  }
              });
          }

          // Función para ver respuestas del banco
        function verRespuestasBanco(solicitudId) {
            $('#respuestasBancoContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-warning" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            `);
            
            // Obtener las respuestas del banco
            $.ajax({
                url: 'api/evaluaciones_banco.php',
                type: 'GET',
                data: {
                    solicitud_id: solicitudId,
                    usuario_banco_id: <?php echo $_SESSION['user_id']; ?>
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-striped">';
                        html += '<thead><tr><th>Fecha</th><th>Vehículo</th><th>Decisión</th><th>Valor a Financiar</th><th>Abono</th><th>Plazo</th><th>Letra</th><th>Promoción</th><th>Comentarios</th></tr></thead>';
                        html += '<tbody>';
                        
                        response.data.forEach(function(evaluacion) {
                            html += '<tr>';
                            html += '<td>' + new Date(evaluacion.fecha_evaluacion).toLocaleString('es-PA') + '</td>';
                            html += '<td>' + (evaluacion.vehiculo_marca ? `${evaluacion.vehiculo_marca} ${evaluacion.vehiculo_modelo || ''}`.trim() : '-') + '</td>';
                            html += '<td><span class="badge badge-estado estado-revision">' + evaluacion.decision.toUpperCase().replace('_', ' ') + '</span></td>';
                            html += '<td>' + (evaluacion.valor_financiar ? '$' + parseFloat(evaluacion.valor_financiar).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                            html += '<td>' + (evaluacion.abono ? '$' + parseFloat(evaluacion.abono).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                            html += '<td>' + (evaluacion.plazo ? evaluacion.plazo + ' meses' : '-') + '</td>';
                            html += '<td>' + (evaluacion.letra ? '$' + parseFloat(evaluacion.letra).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                            html += '<td>' + (evaluacion.promocion || '-') + '</td>';
                            html += '<td>' + (evaluacion.comentarios || '-') + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                        $('#respuestasBancoContent').html(html);
                    } else {
                        $('#respuestasBancoContent').html('<div class="alert alert-info">No hay respuestas registradas para esta solicitud.</div>');
                    }
                    
                    $('#modalRespuestasBanco').modal('show');
                },
                error: function() {
                    $('#respuestasBancoContent').html('<div class="alert alert-danger">Error al cargar las respuestas del banco.</div>');
                    $('#modalRespuestasBanco').modal('show');
                }
            });
        }

        // Función para ver respuestas de todos los bancos (para Admin/Gestor)
        function verRespuestasBancoAdmin(solicitudId) {
            $('#respuestasBancoAdminContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            `);
            
            // Obtener las respuestas de todos los bancos
            $.ajax({
                url: 'api/evaluaciones_banco.php',
                type: 'GET',
                data: {
                    solicitud_id: solicitudId
                },
                                  success: function(response) {
                      if (response.success && response.data.length > 0) {
                          const evaluacionSeleccionada = response.evaluacion_seleccionada;
                          const mostrarAcciones = !evaluacionSeleccionada; // No mostrar acciones si hay una evaluación seleccionada
                          
                          let html = '<div class="table-responsive"><table class="table table-striped">';
                          html += '<thead><tr><th>Fecha</th><th>Banco</th><th>Vehículo</th><th>Decisión</th><th>Valor a Financiar</th><th>Abono</th><th>Plazo</th><th>Letra</th><th>Promoción</th><th>Comentarios</th>';
                          if (mostrarAcciones) {
                              html += '<th>Acciones</th>';
                          }
                          html += '</tr></thead>';
                          html += '<tbody>';
                          
                          response.data.forEach(function(evaluacion) {
                              html += '<tr>';
                              html += '<td>' + new Date(evaluacion.fecha_evaluacion).toLocaleString('es-PA') + '</td>';
                              html += '<td>' + (evaluacion.nombre ? `${evaluacion.nombre} ${evaluacion.apellido || ''}`.trim() : '-') + '</td>';
                              html += '<td>' + (evaluacion.vehiculo_marca ? `${evaluacion.vehiculo_marca} ${evaluacion.vehiculo_modelo || ''}`.trim() : '-') + '</td>';
                              html += '<td><span class="badge badge-estado estado-revision">' + evaluacion.decision.toUpperCase().replace('_', ' ') + '</span></td>';
                              html += '<td>' + (evaluacion.valor_financiar ? '$' + parseFloat(evaluacion.valor_financiar).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                              html += '<td>' + (evaluacion.abono ? '$' + parseFloat(evaluacion.abono).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                              html += '<td>' + (evaluacion.plazo ? evaluacion.plazo + ' meses' : '-') + '</td>';
                              html += '<td>' + (evaluacion.letra ? '$' + parseFloat(evaluacion.letra).toLocaleString('es-PA', {minimumFractionDigits: 2}) : '-') + '</td>';
                              html += '<td>' + (evaluacion.promocion || '-') + '</td>';
                              html += '<td>' + (evaluacion.comentarios || '-') + '</td>';
                              if (mostrarAcciones) {
                                  html += '<td><div class="btn-group-vertical btn-group-sm">';
                                  html += '<button class="btn btn-success btn-sm mb-1" onclick="seleccionarPropuesta(' + evaluacion.id + ', ' + solicitudId + ', \'' + evaluacion.usuario_banco_id + '\')" title="Seleccionar Propuesta"><i class="fas fa-check me-1"></i>Seleccionar</button>';
                                  html += '<button class="btn btn-warning btn-sm" onclick="solicitarReevaluacion(' + evaluacion.id + ', ' + solicitudId + ', \'' + evaluacion.usuario_banco_id + '\')" title="Solicitar Reevaluación"><i class="fas fa-redo me-1"></i>Reevaluar</button>';
                                  html += '</div></td>';
                              } else if (evaluacion.id == evaluacionSeleccionada) {
                                  html += '<td><span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Propuesta Seleccionada</span></td>';
                              }
                              html += '</tr>';
                          });
                          
                          html += '</tbody></table></div>';
                          $('#respuestasBancoAdminContent').html(html);
                    } else {
                        $('#respuestasBancoAdminContent').html('<div class="alert alert-info">No hay respuestas registradas para esta solicitud.</div>');
                    }
                    
                    $('#modalRespuestasBancoAdmin').modal('show');
                },
                error: function() {
                    $('#respuestasBancoAdminContent').html('<div class="alert alert-danger">Error al cargar las respuestas del banco.</div>');
                    $('#modalRespuestasBancoAdmin').modal('show');
                }
            });
        }

        // Función para seleccionar una propuesta
        function seleccionarPropuesta(evaluacionId, solicitudId, usuarioBancoId) {
            $('#seleccionPropuesta_id').val(evaluacionId);
            $('#seleccionPropuesta_solicitud_id').val(solicitudId);
            $('#seleccionPropuesta_usuario_banco_id').val(usuarioBancoId);
            $('#comentarioSeleccion').val('');
            $('#modalSeleccionPropuesta').modal('show');
        }

        // Función para solicitar reevaluación
        function solicitarReevaluacion(evaluacionId, solicitudId, usuarioBancoId) {
            $('#reevaluacion_id').val(evaluacionId);
            $('#reevaluacion_solicitud_id').val(solicitudId);
            $('#reevaluacion_usuario_banco_id').val(usuarioBancoId);
            $('#comentarioReevaluacion').val('');
            $('#modalSolicitarReevaluacion').modal('show');
        }

        // Función para procesar la selección de propuesta
        function procesarSeleccionPropuesta() {
            const evaluacionId = $('#seleccionPropuesta_id').val();
            const solicitudId = $('#seleccionPropuesta_solicitud_id').val();
            const comentario = $('#comentarioSeleccion').val();

            if (!comentario.trim()) {
                alert('Por favor ingrese un comentario');
                return;
            }

            $.ajax({
                url: 'api/evaluaciones_banco.php',
                type: 'POST',
                data: {
                    action: 'seleccionar_propuesta',
                    evaluacion_id: evaluacionId,
                    solicitud_id: solicitudId,
                    comentario: comentario
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#modalSeleccionPropuesta').modal('hide');
                        // Recargar modal de respuestas
                        verRespuestasBancoAdmin(solicitudId);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la selección de la propuesta');
                }
            });
        }

        // Función para procesar la solicitud de reevaluación
        function procesarReevaluacion() {
            const evaluacionId = $('#reevaluacion_id').val();
            const solicitudId = $('#reevaluacion_solicitud_id').val();
            const comentario = $('#comentarioReevaluacion').val();

            if (!comentario.trim()) {
                alert('Por favor ingrese un comentario');
                return;
            }

            $.ajax({
                url: 'api/evaluaciones_banco.php',
                type: 'POST',
                data: {
                    action: 'solicitar_reevaluacion',
                    evaluacion_id: evaluacionId,
                    solicitud_id: solicitudId,
                    comentario: comentario
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#modalSolicitarReevaluacion').modal('hide');
                        // Recargar modal de respuestas
                        verRespuestasBancoAdmin(solicitudId);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud de reevaluación');
                }
            });
        }
    </script>

    <!-- Modal para ver Respuestas del Banco -->
    <div class="modal fade" id="modalRespuestasBanco" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>Respuestas del Banco
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="respuestasBancoContent">
                        <div class="text-center">
                            <div class="spinner-border text-warning" role="status">
                                <span class="visually-hidden">Cargando...</span>
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

    <!-- Modal para ver Respuestas del Banco (Admin/Gestor) -->
    <div class="modal fade" id="modalRespuestasBancoAdmin" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>Respuestas de los Bancos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="respuestasBancoAdminContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
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

    <!-- Modal para Seleccionar Propuesta -->
    <div class="modal fade" id="modalSeleccionPropuesta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Seleccionar Propuesta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="seleccionPropuesta_id">
                    <input type="hidden" id="seleccionPropuesta_solicitud_id">
                    <input type="hidden" id="seleccionPropuesta_usuario_banco_id">
                    
                    <div class="mb-3">
                        <label for="comentarioSeleccion" class="form-label">Comentario *</label>
                        <textarea class="form-control" id="comentarioSeleccion" rows="4" required placeholder="Ingrese un comentario sobre la selección de esta propuesta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="procesarSeleccionPropuesta()">
                        <i class="fas fa-check me-2"></i>Confirmar Selección
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Solicitar Reevaluación -->
    <div class="modal fade" id="modalSolicitarReevaluacion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-redo me-2"></i>Solicitar Reevaluación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reevaluacion_id">
                    <input type="hidden" id="reevaluacion_solicitud_id">
                    <input type="hidden" id="reevaluacion_usuario_banco_id">
                    
                    <div class="mb-3">
                        <label for="comentarioReevaluacion" class="form-label">Comentario *</label>
                        <textarea class="form-control" id="comentarioReevaluacion" rows="4" required placeholder="Ingrese el motivo por el cual solicita la reevaluación..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="procesarReevaluacion()">
                        <i class="fas fa-redo me-2"></i>Solicitar Reevaluación
                    </button>
                </div>
            </div>
        </div>
          </div>
  
      <!-- Modal para Apartar Vehículo -->
      <div class="modal fade" id="modalApartarVehiculo" tabindex="-1">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header bg-warning text-dark">
                      <h5 class="modal-title">
                          <i class="fas fa-bookmark me-2"></i>Apartar Vehículo
                      </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <input type="hidden" id="vehiculo_index_apartar">
                      <div class="alert alert-warning">
                          <i class="fas fa-exclamation-triangle me-2"></i>
                          ¿Está seguro de apartar el vehículo seleccionado?
                      </div>
                      <div class="mb-3">
                          <strong>Vehículo:</strong> <span id="vehiculo_info_apartar"></span>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                          <i class="fas fa-times me-2"></i>Cancelar
                      </button>
                      <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                          <i class="fas fa-check me-2"></i>Confirmar
                      </button>
                  </div>
              </div>
          </div>
      </div>
  
      <script src="js/solicitudes.js"></script>
      <script src="js/adjuntos.js"></script>
  </body>
  </html>
