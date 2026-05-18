<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';

$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles, true);
$isGestor = in_array('ROLE_GESTOR', $userRoles, true);
if (!$isAdmin && !$isGestor) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Reporte de Reservas - MOTUS</title>
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
        .upload-zone { border: 2px dashed #ced4da; border-radius: 12px; padding: 2rem; text-align: center; background: #fff; cursor: pointer; }
        .upload-zone.dragover { border-color: #0d6efd; background: #f0f7ff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="mb-4">
                    <h2 class="mb-1">Subir Reporte de Reservas</h2>
                    <p class="text-muted mb-0">Excel Proforma Autos (fila 4, columna C). Coincide solicitudes y aparta vehículos.</p>
                </div>
                <div class="alert alert-info py-2 small mb-3">
                    Cédula (L), Correo (M), Nombre (J), Marca (T), Modelo (U), Año (X), Km (Y), Total (AC), Abono (AL). Tras subir use <strong>Procesar</strong>.
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form id="formReporteReservas" enctype="multipart/form-data">
                            <div class="upload-zone mb-3" id="uploadZone">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <p class="mb-2">Arrastre el archivo aquí o haga clic para seleccionar</p>
                                <p class="small text-muted mb-3">Formato recomendado: .xlsx (máx. 25 MB)</p>
                                <input type="file" class="form-control d-none" id="archivo_reporte" name="archivo"
                                       accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnElegirArchivo">
                                    <i class="fas fa-folder-open me-1"></i>Elegir archivo
                                </button>
                                <div id="nombreArchivoElegido" class="small text-success mt-2 fw-semibold"></div>
                            </div>
                            <button type="submit" class="btn btn-success" id="btnSubirReporte" disabled>
                                <i class="fas fa-upload me-2"></i>Subir reporte
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Reportes subidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaReportesReservas" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Archivo</th>
                                        <th>Tamaño</th>
                                        <th>Subido por</th>
                                        <th>Estado</th>
                                        <th>Filas</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4 d-none" id="cardDetalleLineas">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between">
                        <h5 class="mb-0">Detalle reporte #<span id="detalleReporteId"></span></h5>
                        <button type="button" class="btn-close" id="btnCerrarDetalle"></button>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="tablaLineasReporte" class="table table-sm table-striped w-100">
                            <thead>
                                <tr>
                                    <th>Fila</th><th>Cliente</th><th>Cédula</th><th>Vehículo</th>
                                    <th>Sol.</th><th>Match</th><th>Estado</th><th>Mensaje</th>
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

<script>window.MOTUS_REPORTE_RESERVAS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/subir_reporte_reservas.js?v=<?php echo file_exists(__DIR__ . '/js/subir_reporte_reservas.js') ? filemtime(__DIR__ . '/js/subir_reporte_reservas.js') : time(); ?>"></script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
