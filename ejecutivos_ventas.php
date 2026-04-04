<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';

$totalEj = 0;
$activosEj = 0;
$inactivosEj = 0;
try {
    $totalEj = (int) $pdo->query('SELECT COUNT(*) FROM ejecutivos_ventas')->fetchColumn();
    $activosEj = (int) $pdo->query('SELECT COUNT(*) FROM ejecutivos_ventas WHERE activo = 1')->fetchColumn();
    $inactivosEj = (int) $pdo->query('SELECT COUNT(*) FROM ejecutivos_ventas WHERE activo = 0')->fetchColumn();
} catch (PDOException $e) {
    try {
        $totalEj = (int) $pdo->query('SELECT COUNT(*) FROM ejecutivos_ventas')->fetchColumn();
        $activosEj = $totalEj;
        $inactivosEj = 0;
    } catch (PDOException $e2) {
        error_log('ejecutivos_ventas.php stats: ' . $e2->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejecutivos de Ventas - MOTUS</title>
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
        .btn-action { margin: 0 2px; border-radius: 8px; }
        .stats-card { background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .stats-number { font-size: 2.2rem; font-weight: bold; margin-bottom: 8px; }
        .stats-label { font-size: 0.95rem; opacity: 0.95; }
        .badge-estado { font-size: 0.85em; padding: 6px 10px; }
        .estado-activo { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); }
        .estado-inactivo { background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%); }
        .modal-header { background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); color: white; border-radius: 15px 15px 0 0; }
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
                        <h2 class="mb-1">Ejecutivos de Ventas</h2>
                        <p class="text-muted mb-0">Catálogo usado en <strong>Datos generales</strong> de las solicitudes y en copia de correos al banco.</p>
                    </div>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ejecutivoVentasModal" onclick="limpiarFormularioEjecutivo()">
                        <i class="fas fa-plus me-2"></i>Nuevo ejecutivo
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card text-center">
                            <div class="stats-number"><?php echo (int) $totalEj; ?></div>
                            <div class="stats-label">Total</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="stats-number"><?php echo (int) $activosEj; ?></div>
                            <div class="stats-label">Activos (visibles al crear solicitud)</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #434343 0%, #666 100%);">
                            <div class="stats-number"><?php echo (int) $inactivosEj; ?></div>
                            <div class="stats-label">Inactivos</div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Ejecute en la base de datos <code>database/migracion_ejecutivos_ventas.sql</code> si la tabla no existe. Si ya tenía la tabla sin <code>activo</code>, use <code>database/migracion_ejecutivos_ventas_columnas_extra.sql</code>.
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="ejecutivosVentasTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Sucursal</th>
                                        <th>Email</th>
                                        <th>Solicitudes</th>
                                        <th>Estado</th>
                                        <th>Alta</th>
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

<div class="modal fade" id="ejecutivoVentasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ejecutivoVentasModalLabel"><i class="fas fa-user-tie me-2"></i>Nuevo ejecutivo de ventas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="ejecutivoVentasForm">
                <div class="modal-body">
                    <input type="hidden" id="ejecutivo_ventas_id_hidden" value="">
                    <div class="mb-3">
                        <label for="ev_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ev_nombre" required maxlength="255" placeholder="Nombre completo">
                    </div>
                    <div class="mb-3">
                        <label for="ev_sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="ev_sucursal" maxlength="255" placeholder="Opcional">
                    </div>
                    <div class="mb-3">
                        <label for="ev_email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="ev_email" maxlength="255" placeholder="Para copia en resumen al banco">
                        <div class="form-text">Opcional; si está vacío no recibirá CC en el envío de resumen.</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ev_activo" checked>
                        <label class="form-check-label" for="ev_activo">Activo (aparece al elegir ejecutivo en solicitudes)</label>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/ejecutivos_ventas.js?v=<?php echo file_exists(__DIR__ . '/js/ejecutivos_ventas.js') ? filemtime(__DIR__ . '/js/ejecutivos_ventas.js') : time(); ?>"></script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
