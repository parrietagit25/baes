<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';

// Verificar que el usuario tenga permisos para ver solicitudes
$userRoles = $_SESSION['user_roles'];
$puedeVerSolicitudes = in_array('ROLE_GESTOR', $userRoles) || 
                      in_array('ROLE_BANCO', $userRoles) || 
                      in_array('ROLE_ADMIN', $userRoles);

if (!$puedeVerSolicitudes) {
    header('Location: dashboard.php');
    exit();
}

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
                                            <th>Estado</th>
                                            <th>Respuesta Banco</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Cargar solicitudes directamente desde PHP
                                        $stmt = $pdo->query("
                                            SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido,
                                                   COUNT(n.id) as total_notas
                                            FROM solicitudes_credito s
                                            LEFT JOIN usuarios u ON s.gestor_id = u.id
                                            LEFT JOIN notas_solicitud n ON s.id = n.solicitud_id
                                            GROUP BY s.id
                                            ORDER BY s.fecha_creacion DESC
                                        ");
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
                                            <td><span class="badge badge-estado <?php echo $estadoClass; ?>"><?php echo $solicitud['estado']; ?></span></td>
                                            <td><span class="badge badge-estado <?php echo $respuestaClass; ?>"><?php echo $solicitud['respuesta_banco']; ?></span></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info btn-action" onclick="verDetalles(<?php echo $solicitud['id']; ?>)" title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="editarSolicitud(<?php echo $solicitud['id']; ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success btn-action" onclick="verMuro(<?php echo $solicitud['id']; ?>)" title="Ver Muro">
                                                    <i class="fas fa-comments"></i>
                                                </button>
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
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Guardar Solicitud
                        </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/solicitudes.js"></script>
</body>
</html>
