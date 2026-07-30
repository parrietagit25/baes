<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/usuario_actividad_helper.php';

if (!motus_actividad_es_admin_principal()) {
    header('Location: dashboard.php');
    exit();
}

$segDesde = (new DateTimeImmutable('-7 days'))->format('Y-m-d');
$segHasta = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Usuarios - MOTUS</title>
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
        .page-header { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
        .ua-online-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#22c55e; margin-right:6px; }
        .ua-dt-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .ua-ua-cell { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .badge-evento { font-weight: 600; text-transform: uppercase; font-size: 0.7rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="page-header">
                    <h2 class="mb-1"><i class="fas fa-user-secret me-2"></i>Seguimiento de Usuarios</h2>
                    <p class="mb-0 opacity-90">Telemetría interna: IP, pantallas, login/logout y actividad en el sistema (solo admin principal)</p>
                </div>

                <div class="alert alert-warning small">
                    Visible únicamente para el administrador principal (<strong>id 1</strong>).
                    Los datos se generan desde el login, cada pantalla visitada y un beacon en el navegador (clics, presencia, heartbeat).
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form id="uaFiltrosForm" class="row g-2 align-items-end flex-wrap" autocomplete="off">
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="uaDesde">Desde</label>
                                <input type="date" id="uaDesde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segDesde); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="uaHasta">Hasta</label>
                                <input type="date" id="uaHasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($segHasta); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="uaUsuario">Usuario</label>
                                <select id="uaUsuario" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="uaEvento">Evento</label>
                                <select id="uaEvento" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="login">login</option>
                                    <option value="logout">logout</option>
                                    <option value="page_view">page_view</option>
                                    <option value="click">click</option>
                                    <option value="action">action</option>
                                    <option value="heartbeat">heartbeat</option>
                                    <option value="visibility">visibility</option>
                                    <option value="login_failed">login_failed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="uaQ">Buscar</label>
                                <input type="text" id="uaQ" class="form-control form-control-sm" placeholder="IP, email, página, detalle...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100" id="btnUaFiltrar">
                                    <i class="fas fa-filter me-1"></i>Filtrar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="#" class="btn btn-outline-success btn-sm w-100" id="uaExportCsv">
                                    <i class="fas fa-file-csv me-1"></i>CSV
                                </a>
                            </div>
                        </form>
                        <p class="small text-muted mb-0 mt-2" id="uaFiltrosAplicados">Filtros: —</p>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">En línea (5 min)</div><div class="h4 mb-0 text-success" id="uaKpiOnline">0</div></div></div></div>
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Logins</div><div class="h4 mb-0" id="uaKpiLogins">0</div></div></div></div>
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Logouts</div><div class="h4 mb-0" id="uaKpiLogouts">0</div></div></div></div>
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Pantallas</div><div class="h4 mb-0 text-primary" id="uaKpiViews">0</div></div></div></div>
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Usuarios</div><div class="h4 mb-0" id="uaKpiUsers">0</div></div></div></div>
                    <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Eventos</div><div class="h4 mb-0" id="uaKpiEvents">0</div></div></div></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-5">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3"><span class="ua-online-dot"></span>Usuarios en línea ahora</h6>
                                <div id="uaOnlineList" class="small text-muted">Cargando…</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3">Por tipo de evento</h6>
                                <div id="uaPorEvento" class="small">Cargando…</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3">Pantallas más visitadas</h6>
                                <div id="uaTopPaginas" class="small">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Detalle de actividad</h6>
                        <div class="ua-dt-wrap">
                            <table class="table table-sm table-bordered table-hover" id="tablaUaActividad" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Evento</th>
                                        <th>Sección</th>
                                        <th>Pantalla</th>
                                        <th>Detalle</th>
                                        <th>IP</th>
                                        <th>User-Agent</th>
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
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/seguimiento_usuarios.js"></script>
</body>
</html>
