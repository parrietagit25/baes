<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

if (!$isAdmin && !$isGestor) {
    header('Location: dashboard.php');
    exit();
}

$segDesde = (new DateTimeImmutable('-365 days'))->format('Y-m-d');
$segHasta = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento - Motus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; overflow-x: hidden; max-width: 100%; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .seg-chart-wrap { min-height: 280px; max-width: 420px; margin: 0 auto; }
        .seg-dt-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <div class="mb-4">
                        <h2 class="mb-1"><i class="fas fa-chart-line me-2"></i>Seguimiento</h2>
                        <p class="text-muted mb-0">Reporte de Sol. Financiamiento (formulario por enlace): con y sin solicitud de crédito Motus.</p>
                    </div>

                    <div class="alert alert-info small">
                        <strong>ID Sol Digital</strong> = registro del formulario público (<code>financiamiento_registros</code>).
                        <strong>ID Sol MOTUS</strong> = solicitud de crédito vinculada, si existe.
                        Los datos de <strong>banco</strong> corresponden a la <strong>respuesta/propuesta seleccionada</strong>.
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <form id="segFinFiltrosForm" class="row g-2 align-items-end flex-wrap">
                                <div class="col-md-2">
                                    <label class="form-label small mb-0" for="segFinDesde">Desde</label>
                                    <input type="date" id="segFinDesde" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segDesde); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0" for="segFinHasta">Hasta</label>
                                    <input type="date" id="segFinHasta" name="hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segHasta); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-0" for="segFinVinculo">Vínculo Motus</label>
                                    <select id="segFinVinculo" name="vinculo" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        <option value="con">Con solicitud Motus</option>
                                        <option value="sin">Sin solicitud Motus</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100" id="btnSegFinFiltrar">
                                        <i class="fas fa-filter me-1"></i>Filtrar
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="#" class="btn btn-outline-success btn-sm w-100" id="segFinExportXlsx">
                                        <i class="fas fa-file-excel me-1"></i>Exportar Excel
                                    </a>
                                </div>
                            </form>
                            <p class="small text-muted mb-0 mt-2" id="segFinFiltrosAplicados">Filtros: —</p>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Total envíos</div>
                                    <div class="h4 mb-0" id="segFinKpiTotal">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Con solicitud Motus</div>
                                    <div class="h4 mb-0 text-success" id="segFinKpiCon">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Sin solicitud Motus</div>
                                    <div class="h4 mb-0 text-secondary" id="segFinKpiSin">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Enviadas a banco</div>
                                    <div class="h4 mb-0 text-primary" id="segFinKpiEnviada">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">No enviadas a banco</div>
                                    <div class="h4 mb-0 text-warning" id="segFinKpiNoEnviada">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Rango fechas</div>
                                    <div class="h6 mb-0 text-secondary" id="segFinRangoFechas">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="text-center mb-3">Formulario público vs solicitud Motus</h6>
                                    <div class="seg-chart-wrap">
                                        <canvas id="segFinChartVinculo"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="text-center mb-3">Enviadas a banco vs no enviadas</h6>
                                    <div class="seg-chart-wrap">
                                        <canvas id="segFinChartEnviadaBanco"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" id="segFinDetalleWrap">
                        <div class="card-body">
                            <h6 class="mb-2">Detalle completo (todos los campos del reporte)</h6>
                            <div class="seg-dt-wrap">
                                <table class="table table-sm table-bordered table-striped" id="tablaSegFinDetalle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID financ.</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Email cliente</th>
                                            <th>Sexo form.</th>
                                            <th>Género agr.</th>
                                            <th>Edad calc.</th>
                                            <th>Rango edad</th>
                                            <th>Salario USD</th>
                                            <th>Rango salario</th>
                                            <th>Perfil est.</th>
                                            <th>Sector est.</th>
                                            <th>ID sol.</th>
                                            <th>Estado Motus</th>
                                            <th>Perfil Motus</th>
                                            <th>Ingreso Motus</th>
                                            <th>Género Motus</th>
                                            <th>Edad Motus</th>
                                            <th>Nombre Motus</th>
                                            <th>Cédula Motus</th>
                                            <th>¿Perfil?</th>
                                            <th>¿Género?</th>
                                            <th>Vendedor</th>
                                            <th>Teléfono</th>
                                            <th>Unidad/Veh.</th>
                                            <th>ID Sol Digital</th>
                                            <th>ID Sol MOTUS</th>
                                            <th>Enviada a banco</th>
                                            <th>Banco</th>
                                            <th>Agente banco</th>
                                            <th>Decisión sel.</th>
                                            <th>Razón</th>
                                            <th>Tasa %</th>
                                            <th>Precio/valor</th>
                                            <th>Abono</th>
                                            <th>Plazo</th>
                                            <th>Letra mens.</th>
                                            <th>Letra quinc.</th>
                                            <th>Promoción</th>
                                            <th>Cuantía</th>
                                            <th>Comentarios banco</th>
                                            <th>Fecha eval. sel.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="42" class="text-center text-muted">Cargando…</td></tr>
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
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="js/seguimiento_financiamiento.js?v=<?php echo file_exists(__DIR__ . '/js/seguimiento_financiamiento.js') ? filemtime(__DIR__ . '/js/seguimiento_financiamiento.js') : time(); ?>"></script>
    <?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
