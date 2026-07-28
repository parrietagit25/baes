<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/banco_scope_helper.php';

$userRoles = $_SESSION['user_roles'] ?? [];
if (!motus_es_admin_banco($userRoles)) {
    header('Location: dashboard.php');
    exit();
}

$segDesde = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$segHasta = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento banco - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; overflow-x: hidden; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .seg-chart-wrap { min-height: 280px; max-width: 420px; margin: 0 auto; }
        .seg-dt-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .page-header { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="page-header">
                    <h2 class="mb-1"><i class="fas fa-chart-line me-2"></i>Seguimiento</h2>
                    <p class="mb-0 opacity-90">Solicitudes asignadas a su banco: respuestas enviadas y propuestas seleccionadas</p>
                </div>

                <div class="alert alert-info small">
                    Solo solicitudes con asignación <strong>activa</strong> a usuarios de su entidad.
                    El filtro de fechas considera la <em>fecha de asignación</em> o la <em>fecha de creación Motus</em>.
                    <span id="segBancoNotaApi" class="d-block mt-1 text-muted"></span>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form id="segBancoFiltrosForm" action="#" method="get" autocomplete="off" class="row g-2 align-items-end flex-wrap">
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="segBancoDesde">Desde</label>
                                <input type="date" id="segBancoDesde" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segDesde); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="segBancoHasta">Hasta</label>
                                <input type="date" id="segBancoHasta" name="hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segHasta); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100" id="btnSegBancoFiltrar">
                                    <i class="fas fa-filter me-1"></i>Filtrar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="api/reportes_banco.php?action=exportar_excel_seguimiento" class="btn btn-outline-success btn-sm w-100" id="segBancoExportXlsx">
                                    <i class="fas fa-file-excel me-1"></i>Excel
                                </a>
                            </div>
                        </form>
                        <p class="small text-muted mb-0 mt-2" id="segBancoFiltrosAplicados">Filtros: —</p>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="text-muted small">Total de solicitudes</div>
                                <div class="h3 mb-0" id="segBancoKpiAsignadas">0</div>
                                <div class="small text-muted">Asignadas a su banco</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="text-muted small">Total de respuestas enviadas</div>
                                <div class="h3 mb-0 text-primary" id="segBancoKpiRespuestas">0</div>
                                <div class="small text-muted">Enviadas a evaluar</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="text-muted small">Total de seleccionadas</div>
                                <div class="h3 mb-0 text-success" id="segBancoKpiSeleccionadas">0</div>
                                <div class="small text-muted">Propuestas elegidas del banco</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-center mb-3">Asignadas vs enviadas a evaluar</h6>
                                <div class="seg-chart-wrap">
                                    <canvas id="segBancoChartAsignadas"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-center mb-3">Enviadas a evaluar vs seleccionadas</h6>
                                <div class="seg-chart-wrap">
                                    <canvas id="segBancoChartSeleccionadas"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">Detalle de solicitudes</h6>
                        <div class="seg-dt-wrap">
                            <table class="table table-sm table-bordered table-striped" id="tablaSegBancoDetalle" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID sol.</th>
                                        <th>Fecha asig.</th>
                                        <th>Cliente</th>
                                        <th>Cédula</th>
                                        <th>Estado</th>
                                        <th>Encargado(s)</th>
                                        <th>Respuestas</th>
                                        <th>Última decisión</th>
                                        <th>Seleccionada</th>
                                        <th>Decisión sel.</th>
                                        <th>Tasa %</th>
                                        <th>Valor financiar</th>
                                        <th>Abono</th>
                                        <th>Plazo</th>
                                        <th>Letra</th>
                                        <th>Promoción</th>
                                        <th>Cuantía</th>
                                        <th>Vehículo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="19" class="text-center text-muted">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/seguimiento_banco.js?v=<?php echo file_exists(__DIR__ . '/js/seguimiento_banco.js') ? filemtime(__DIR__ . '/js/seguimiento_banco.js') : time(); ?>"></script>
</body>
</html>
