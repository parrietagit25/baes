<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ferias - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .btn-action { margin: 0 1px; border-radius: 6px; padding: 0.25rem 0.45rem; font-size: 0.8rem; }
        .modal-header { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; border-radius: 15px 15px 0 0; }
        .badge-estado { font-size: 0.85em; padding: 6px 10px; }
        .estado-activo { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); }
        .estado-inactivo { background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%); }
        #listaVendedoresFeria .list-group-item { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-store me-2"></i>Ferias</h2>
                        <p class="text-muted mb-0">Registre ferias, asigne vendedores y abra el panel en vivo de solicitudes.</p>
                    </div>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#feriaModal" onclick="limpiarFormularioFeria()">
                        <i class="fas fa-plus me-2"></i>Registrar feria
                    </button>
                </div>

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Si es la primera vez, ejecute en la base de datos <code>database/migracion_ferias.sql</code>.
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="feriasTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre feria</th>
                                        <th>Fecha</th>
                                        <th>Lugar</th>
                                        <th>Vendedores</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feriaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feriaModalLabel"><i class="fas fa-store me-2"></i>Registrar feria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="feriaForm">
                <div class="modal-body">
                    <input type="hidden" id="feria_id_hidden" value="">
                    <div class="mb-3">
                        <label for="feria_nombre" class="form-label">Nombre de la feria <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="feria_nombre" required maxlength="255">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="feria_fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="feria_fecha_inicio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="feria_fecha_fin" class="form-label">Fecha de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="feria_fecha_fin" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="feria_lugar" class="form-label">Lugar</label>
                        <input type="text" class="form-control" id="feria_lugar" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="feria_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="feria_descripcion" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="feria_activo" checked>
                        <label class="form-check-label" for="feria_activo">Activa</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="vendedoresModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vendedoresModalLabel"><i class="fas fa-users me-2"></i>Vendedores de la feria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vendedores_feria_id" value="">
                <p class="text-muted small" id="vendedoresFeriaNombre"></p>
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-9">
                        <label for="selectVendedorAdd" class="form-label">Añadir vendedor</label>
                        <select class="form-select" id="selectVendedorAdd">
                            <option value="">Seleccione un ejecutivo de ventas...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-success w-100" id="btnAddVendedor">
                            <i class="fas fa-user-plus me-1"></i>Añadir
                        </button>
                    </div>
                </div>
                <ul class="list-group" id="listaVendedoresFeria">
                    <li class="list-group-item text-muted">Cargando...</li>
                </ul>
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
<script src="js/ferias.js?v=<?php echo file_exists(__DIR__ . '/js/ferias.js') ? filemtime(__DIR__ . '/js/ferias.js') : time(); ?>"></script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
