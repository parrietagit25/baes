<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bancos");
$totalBancos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM bancos WHERE activo = 1");
$bancosActivos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM bancos WHERE activo = 0");
$bancosInactivos = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Bancos - Solicitud de Crédito</title>
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
        .estado-activo { 
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); 
        }
        .estado-inactivo { 
            background: linear-gradient(135deg, #e84393 0%, #fd79a8 100%); 
        }
        .bank-card {
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }
        .bank-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
                            <h2 class="mb-1">Gestión de Bancos</h2>
                            <p class="text-muted mb-0">Administración de bancos del sistema</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bancoModal" onclick="limpiarFormularioBanco()">
                                <i class="fas fa-plus me-2"></i>Nuevo Banco
                            </button>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#infoModal">
                                <i class="fas fa-info-circle me-2"></i>Info
                            </button>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $totalBancos; ?></div>
                                <div class="stats-label">Total Bancos</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <div class="stats-number"><?php echo $bancosActivos; ?></div>
                                <div class="stats-label">Activos</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <div class="stats-number"><?php echo $bancosInactivos; ?></div>
                                <div class="stats-label">Inactivos</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Bancos -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="bancosTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Código</th>
                                            <th>Contacto Principal</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Usuarios Asignados</th>
                                            <th>Estado</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Se carga via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Banco -->
    <div class="modal fade" id="bancoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bancoModalLabel">
                        <i class="fas fa-university me-2"></i>Nuevo Banco
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="bancoForm" method="POST" action="api/bancos.php">
                    <div class="modal-body">
                        <input type="hidden" id="banco_id" name="id">
                        
                        <!-- Información Básica -->
                        <h6 class="mb-3">Información Básica</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre del Banco *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código *</label>
                                    <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="20">
                                    <div class="form-text">Código único para identificar el banco</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Breve descripción del banco"></textarea>
                        </div>
                        
                        <!-- Información de Contacto -->
                        <h6 class="mb-3 mt-4">Información de Contacto</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <textarea class="form-control" id="direccion" name="direccion" rows="2" placeholder="Dirección principal del banco"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono Principal</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" placeholder="+507 1234-5678">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Principal</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="info@banco.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sitio_web" class="form-label">Sitio Web</label>
                                    <input type="url" class="form-control" id="sitio_web" name="sitio_web" placeholder="https://www.banco.com">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contacto Específico -->
                        <h6 class="mb-3 mt-4">Contacto Específico</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="contacto_principal" class="form-label">Nombre del Contacto</label>
                                    <input type="text" class="form-control" id="contacto_principal" name="contacto_principal" placeholder="Nombre del contacto principal">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="telefono_contacto" class="form-label">Teléfono del Contacto</label>
                                    <input type="text" class="form-control" id="telefono_contacto" name="telefono_contacto" placeholder="+507 1234-5678">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="email_contacto" class="form-label">Email del Contacto</label>
                                    <input type="email" class="form-control" id="email_contacto" name="email_contacto" placeholder="contacto@banco.com">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                <label class="form-check-label" for="activo">
                                    Banco Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Guardar Banco
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
                        <i class="fas fa-info-circle me-2"></i>Información sobre Gestión de Bancos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-university me-2"></i>Funcionalidades
                            </h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-plus-circle text-success me-2"></i>
                                    <strong>Crear Bancos</strong>
                                    <small class="text-muted ms-2">Registrar nuevos bancos</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-edit text-primary me-2"></i>
                                    <strong>Editar Bancos</strong>
                                    <small class="text-muted ms-2">Modificar información existente</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-eye text-info me-2"></i>
                                    <strong>Ver Detalles</strong>
                                    <small class="text-muted ms-2">Información completa del banco</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-trash text-danger me-2"></i>
                                    <strong>Eliminar Bancos</strong>
                                    <small class="text-muted ms-2">Eliminar bancos sin solicitudes</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-info mb-3">
                                <i class="fas fa-lightbulb me-2"></i>Campos Importantes
                            </h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-tag text-info me-2"></i>
                                    <strong>Nombre y Código</strong>
                                    <small class="text-muted ms-2">Identificación única</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-phone text-info me-2"></i>
                                    <strong>Información de Contacto</strong>
                                    <small class="text-muted ms-2">Teléfonos y emails</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-user text-info me-2"></i>
                                    <strong>Contacto Principal</strong>
                                    <small class="text-muted ms-2">Persona de contacto</small>
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-toggle-on text-info me-2"></i>
                                    <strong>Estado Activo/Inactivo</strong>
                                    <small class="text-muted ms-2">Control de disponibilidad</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>Importante
                        </h6>
                        <p class="mb-0">
                            Solo los administradores pueden gestionar bancos. Los bancos con solicitudes asociadas no pueden ser eliminados.
                        </p>
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
    <script src="js/bancos.js"></script>
</body>
</html>
