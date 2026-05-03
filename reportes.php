<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    header('Location: dashboard.php');
    exit();
}

$submenu = $_GET['submenu'] ?? 'usuarios';
if (!in_array($submenu, ['usuarios', 'vendedores', 'tiempo', 'banco', 'emails', 'encuestas', 'telemetria', 'fin_publica', 'fin_enlazada'], true)) {
    $submenu = 'usuarios';
}
$finRepDesde = (new DateTimeImmutable('-365 days'))->format('Y-m-d');
$finRepHasta = (new DateTimeImmutable('today'))->format('Y-m-d');
$estadosCol = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
$titulosReporte = [
    'usuarios' => 'Rep. Usuarios',
    'vendedores' => 'Rep. Vendedores',
    'tiempo' => 'Rep. Tiempo',
    'banco' => 'Rep. Banco',
    'emails' => 'Rep. Correos',
    'encuestas' => 'Rep. Encuestas',
    'telemetria' => 'Rep. Telemetría',
    'fin_publica' => 'Sol. Financiamiento (público)',
    'fin_enlazada' => 'Sol. Pública + Motus enlazada',
];
$exportActionPorSubmenu = [
    'usuarios' => ['action' => 'exportar_excel_usuarios', 'label' => 'Descargar Rep. Usuarios'],
    'vendedores' => ['action' => 'exportar_excel_vendedores', 'label' => 'Descargar Rep. Vendedores'],
    'tiempo' => ['action' => 'exportar_excel_tiempo', 'label' => 'Descargar Rep. Tiempo'],
    'banco' => ['action' => 'exportar_excel_banco', 'label' => 'Descargar Rep. Banco'],
    'emails' => ['action' => 'exportar_excel_correos', 'label' => 'Descargar Rep. Correos'],
    // En encuestas se prioriza vendedores; se deja botón adicional dentro del panel para gestores.
    'encuestas' => ['action' => 'exportar_excel_encuestas_vendedores', 'label' => 'Descargar Enc. Vendedores'],
    'telemetria' => ['action' => 'exportar_excel_telemetria', 'label' => 'Descargar Rep. Telemetría'],
];
$exportActual = $exportActionPorSubmenu[$submenu] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Solicitud de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .reportes-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 24px; }
        .submenu-reportes .nav-link { color: #495057; border-radius: 8px; margin-right: 8px; }
        .submenu-reportes .nav-link.active { background: #667eea; color: white; }
        .total-click { cursor: pointer; font-weight: 600; }
        .total-click:hover { text-decoration: underline; }
        .modal-header.reportes { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0; }
        .table-reportes { font-size: 0.9rem; }
        .enc-kpi .card { border: none; border-radius: 12px; }
        .enc-bloque-title { color: #495057; font-weight: 600; font-size: 1.1rem; margin: 1.5rem 0 1rem; }
        .enc-bloque-title.v { border-left: 4px solid #0d6efd; padding-left: 0.5rem; }
        .enc-bloque-title.g { border-left: 4px solid #6f42c1; padding-left: 0.5rem; }
        .tel-chart-wrap { min-height: 260px; }
        .fin-chart-wrap { min-height: 280px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <div class="reportes-header">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($titulosReporte[$submenu]); ?></h2>
                            <?php if ($exportActual): ?>
                            <a href="api/reportes.php?action=<?php echo urlencode($exportActual['action']); ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-file-excel me-1"></i><?php echo htmlspecialchars($exportActual['label']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 opacity-90"><?php
                            if ($submenu === 'usuarios') echo 'Total de solicitudes por usuario y estado';
                            elseif ($submenu === 'vendedores') echo 'Total de solicitudes por vendedor y estado';
                            elseif ($submenu === 'tiempo') echo 'Tiempo entre cambios de estado por solicitud';
                            elseif ($submenu === 'banco') echo 'Tiempo que tardan los bancos en dar respuesta a las solicitudes asignadas';
                            elseif ($submenu === 'encuestas') echo 'Promedios, totales y detalle de respuestas a las encuestas públicas (vendedores y gestores)';
                            elseif ($submenu === 'telemetria') echo 'Tiempos del wizard, inicio/fin, dispositivo, IP y datos de contacto capturados en solicitud pública';
                            elseif ($submenu === 'fin_publica') echo 'Demografía y perfiles inferidos desde el formulario público de financiamiento (sin solicitud Motus obligatoria)';
                            elseif ($submenu === 'fin_enlazada') echo 'Mismo análisis del formulario público, limitado a envíos que ya tienen solicitud de crédito Motus vinculada';
                            else echo 'Cantidad de correos enviados/fallidos y detalle de destinatarios';
                        ?></p>
                    </div>

                    <!-- Rep. Usuarios -->
                    <div id="panel-usuarios" class="report-panel" style="display: <?php echo $submenu === 'usuarios' ? 'block' : 'none'; ?>">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Total de solicitudes por usuario y estado</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <input type="text" id="filtroUsuariosTexto" class="form-control form-control-sm" placeholder="Filtrar por nombre o email...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filtroUsuariosEstado" class="form-select form-select-sm">
                                            <option value="">Todos los estados</option>
                                            <?php foreach ($estadosCol as $e): ?>
                                            <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" min="0" id="filtroUsuariosMinTotal" class="form-control form-control-sm" placeholder="Total mín.">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarUsuarios"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-reportes" id="tabla-usuarios">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Usuario</th>
                                                <?php foreach ($estadosCol as $e): ?>
                                                    <th class="text-center"><?php echo htmlspecialchars($e); ?></th>
                                                <?php endforeach; ?>
                                                <th class="text-center">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rep. Vendedores -->
                    <div id="panel-vendedores" class="report-panel" style="display: <?php echo $submenu === 'vendedores' ? 'block' : 'none'; ?>">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Total de solicitudes por vendedor y estado</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <input type="text" id="filtroVendedoresTexto" class="form-control form-control-sm" placeholder="Filtrar por nombre o email...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filtroVendedoresEstado" class="form-select form-select-sm">
                                            <option value="">Todos los estados</option>
                                            <?php foreach ($estadosCol as $e): ?>
                                            <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" min="0" id="filtroVendedoresMinTotal" class="form-control form-control-sm" placeholder="Total mín.">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarVendedores"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-reportes" id="tabla-vendedores">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Vendedor</th>
                                                <?php foreach ($estadosCol as $e): ?>
                                                    <th class="text-center"><?php echo htmlspecialchars($e); ?></th>
                                                <?php endforeach; ?>
                                                <th class="text-center">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rep. Tiempo -->
                    <div id="panel-tiempo" class="report-panel" style="display: <?php echo $submenu === 'tiempo' ? 'block' : 'none'; ?>">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tiempo entre cambios de estado por solicitud</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <input type="text" id="filtroTiempoTexto" class="form-control form-control-sm" placeholder="Filtrar por cliente o cédula...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filtroTiempoEstado" class="form-select form-select-sm">
                                            <option value="">Todos los estados</option>
                                            <?php foreach ($estadosCol as $e): ?>
                                            <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" id="filtroTiempoDesde" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" id="filtroTiempoHasta" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarTiempo"><i class="fas fa-filter"></i></button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-reportes" id="tabla-tiempo">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Id</th>
                                                <th>Cliente</th>
                                                <th>Cédula</th>
                                                <th>Estado</th>
                                                <th>Última actualización</th>
                                                <th>Tiempo en estado actual</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rep. Banco -->
                    <div id="panel-banco" class="report-panel" style="display: <?php echo $submenu === 'banco' ? 'block' : 'none'; ?>">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="mb-2">Cantidad de solicitudes por banco</h6>
                                <div style="height: 320px;">
                                    <canvas id="chartSolicitudesPorBanco"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tiempo de respuesta del banco por solicitud</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <input type="text" id="filtroBancoTexto" class="form-control form-control-sm" placeholder="Filtrar por cliente o banco...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filtroBancoPendiente" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="si">Pendientes</option>
                                            <option value="no">Respondidos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarBanco"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-reportes" id="tabla-banco">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Id</th>
                                                <th>Cliente</th>
                                                <th>Banco</th>
                                                <th>Fecha asignación</th>
                                                <th>Fecha respuesta</th>
                                                <th>Tiempo de respuesta</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rep. Encuestas -->
                    <div id="panel-encuestas" class="report-panel" style="display: <?php echo $submenu === 'encuestas' ? 'block' : 'none'; ?>">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <a href="api/reportes.php?action=exportar_excel_encuestas_vendedores" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Descargar Enc. Vendedores
                            </a>
                            <a href="api/reportes.php?action=exportar_excel_encuestas_gestores" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Descargar Enc. Gestores
                            </a>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <input type="text" id="filtroEncuestasTexto" class="form-control form-control-sm" placeholder="Filtrar encuestas por nombre, cargo o recomendación...">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarEncuestas"><i class="fas fa-filter me-1"></i>Filtrar</button>
                            </div>
                        </div>
                        <div id="encuestas-contenido" class="py-3 text-muted text-center">Cargando encuestas…</div>
                    </div>

                    <!-- Rep. Telemetría -->
                    <div id="panel-telemetria" class="report-panel" style="display: <?php echo $submenu === 'telemetria' ? 'block' : 'none'; ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Registros</div><div class="h4 mb-0" id="telTotalRegistros">0</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Duración promedio</div><div class="h4 mb-0 text-primary" id="telDurProm">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Promedio de registros diarios</div><div class="h4 mb-0 text-success" id="telPromRegDia">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Duración promedio en minutos</div><div class="h4 mb-0 text-info" id="telDurPromMin">—</div></div></div></div>
                            <div class="col-md-6"><div class="card"><div class="card-body text-center"><div class="text-muted small">Promedio por paso (A/B/C/D/E)</div><div class="h6 mb-1" id="telPasoProm">—</div><div class="h6 mb-0 text-info" id="telPasoPromMin">—</div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-2">Dispositivo</h6>
                                        <div class="tel-chart-wrap"><canvas id="telChartDispositivo"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-2">Ubicación</h6>
                                        <div class="tel-chart-wrap"><canvas id="telChartUbicacion"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-2">Resolución</h6>
                                        <div class="tel-chart-wrap"><canvas id="telChartResolucion"></canvas></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <input type="text" id="filtroTelemetriaTexto" class="form-control form-control-sm" placeholder="Filtrar por cliente, cédula, celular, email o IP...">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" min="0" id="filtroTelemetriaDurMin" class="form-control form-control-sm" placeholder="Duración mín (seg)">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" id="filtroTelemetriaDesde" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" id="filtroTelemetriaHasta" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarTelemetria"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm table-reportes" id="tabla-telemetria">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Cliente</th>
                                                <th>Cédula</th>
                                                <th>Contacto</th>
                                                <th>IP</th>
                                                <th>Ubicación IP</th>
                                                <th>Duración total</th>
                                                <th>Paso A</th>
                                                <th>Paso B</th>
                                                <th>Paso C</th>
                                                <th>Paso D</th>
                                                <th>Paso E</th>
                                                <th>Dispositivo</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sol. Financiamiento — solo público -->
                    <div id="panel-fin-publica" class="report-panel" style="display: <?php echo $submenu === 'fin_publica' ? 'block' : 'none'; ?>">
                        <div class="alert alert-info small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Metodología:</strong> el perfil laboral y el sector (público/privado) se estiman con reglas sobre el texto del formulario; no sustituyen revisión humana.
                            <span id="finPubNotaApi" class="d-block mt-1 text-danger small"></span>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row g-2 align-items-end flex-wrap">
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Desde</label>
                                        <input type="date" id="finPubDesde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepDesde); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Hasta</label>
                                        <input type="date" id="finPubHasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepHasta); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">Género (formulario)</label>
                                        <div class="d-flex flex-wrap gap-2 small">
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-pub-gen" value="Femenino" checked> F</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-pub-gen" value="Masculino" checked> M</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-pub-gen" value="Otro" checked> Otro</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-pub-gen" value="Sin dato" checked> Sin dato</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Perfil estimado</label>
                                        <select id="finPubPerfil" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="asalariado">Asalariado</option>
                                            <option value="independiente">Independiente</option>
                                            <option value="jubilado">Jubilado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Sector (solo asalariado est.)</label>
                                        <select id="finPubSector" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="gobierno">Público estimado</option>
                                            <option value="privado">Privado estimado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100 mt-2 mt-md-0" id="btnFinPubFiltrar"><i class="fas fa-sync me-1"></i>Actualizar</button>
                                    </div>
                                    <div class="col-md-2">
                                        <a href="#" class="btn btn-outline-success btn-sm w-100 mt-1" id="finPubExportXlsx"><i class="fas fa-file-excel me-1"></i>Excel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Registros (filtrados)</div><div class="h4 mb-0" id="finPubKpiN">0</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Edad promedio</div><div class="h4 mb-0 text-primary" id="finPubKpiEdad">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Salario prom. (USD)</div><div class="h4 mb-0 text-success" id="finPubKpiSal">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Rango fechas</div><div class="h6 mb-0 text-secondary" id="finPubKpiFechas">—</div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Género</h6><div class="fin-chart-wrap"><canvas id="finPubChartGen"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Rango salario (USD)</h6><div class="fin-chart-wrap"><canvas id="finPubChartSal"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Rango de edad</h6><div class="fin-chart-wrap"><canvas id="finPubChartEdad"></canvas></div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Perfil laboral (estimado)</h6><div class="fin-chart-wrap"><canvas id="finPubChartPerfil"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Sector si asalariado (estimado)</h6><div class="fin-chart-wrap"><canvas id="finPubChartSector"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Salario × género (apilado)</h6><div class="fin-chart-wrap"><canvas id="finPubChartCruce"></canvas></div></div></div></div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-2">Muestra reciente</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-reportes" id="tabla-fin-pub">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Id</th><th>Fecha</th><th>Cliente</th><th>Género</th><th>Edad</th><th>Rango USD</th><th>Perfil est.</th><th>Sector est.</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sol. Pública + Motus -->
                    <div id="panel-fin-enlazada" class="report-panel" style="display: <?php echo $submenu === 'fin_enlazada' ? 'block' : 'none'; ?>">
                        <div class="alert alert-info small mb-3">
                            <i class="fas fa-link me-1"></i>
                            Solo filas con <code>solicitudes_credito.financiamiento_registro_id</code> apuntando al registro público.
                            <span id="finEnlNotaApi" class="d-block mt-1 text-muted"></span>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row g-2 align-items-end flex-wrap">
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Desde</label>
                                        <input type="date" id="finEnlDesde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepDesde); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Hasta</label>
                                        <input type="date" id="finEnlHasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepHasta); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-0">Estado solicitud Motus</label>
                                        <select id="finEnlEstado" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <?php foreach ($estadosCol as $e): ?>
                                            <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-0">Género (formulario)</label>
                                        <div class="d-flex flex-wrap gap-2 small">
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Femenino" checked> F</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Masculino" checked> M</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Otro" checked> Otro</label>
                                            <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Sin dato" checked> Sin dato</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Perfil estimado</label>
                                        <select id="finEnlPerfil" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="asalariado">Asalariado</option>
                                            <option value="independiente">Independiente</option>
                                            <option value="jubilado">Jubilado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Sector estimado</label>
                                        <select id="finEnlSector" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="gobierno">Público estimado</option>
                                            <option value="privado">Privado estimado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-primary btn-sm w-100 mt-2" id="btnFinEnlFiltrar"><i class="fas fa-sync me-1"></i>Actualizar</button>
                                    </div>
                                    <div class="col-md-2">
                                        <a href="#" class="btn btn-outline-success btn-sm w-100 mt-1" id="finEnlExportXlsx"><i class="fas fa-file-excel me-1"></i>Excel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Parejas público–Motus</div><div class="h4 mb-0" id="finEnlKpiN">0</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Perfil coincide (est. vs Motus)</div><div class="h4 mb-0 text-success" id="finEnlKpiPerfilOk">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Género coincide</div><div class="h4 mb-0 text-primary" id="finEnlKpiGenOk">—</div></div></div></div>
                            <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="text-muted small">Rango fechas</div><div class="h6 mb-0 text-secondary" id="finEnlKpiFechas">—</div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Estado solicitud Motus</h6><div class="fin-chart-wrap"><canvas id="finEnlChartEstado"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Perfil financiero (Motus)</h6><div class="fin-chart-wrap"><canvas id="finEnlChartPerfilMotus"></canvas></div></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Género (formulario público)</h6><div class="fin-chart-wrap"><canvas id="finEnlChartGen"></canvas></div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Estado Motus × perfil Motus</h6><div class="fin-chart-wrap"><canvas id="finEnlChartCruce"></canvas></div></div></div></div>
                            <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Perfil estimado (público)</h6><div class="fin-chart-wrap"><canvas id="finEnlChartPerfilPub"></canvas></div></div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Rango salario (USD) — público</h6><div class="fin-chart-wrap"><canvas id="finEnlChartSal"></canvas></div></div></div></div>
                            <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Salario × género (apilado)</h6><div class="fin-chart-wrap"><canvas id="finEnlChartCruceSal"></canvas></div></div></div></div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-2">Muestra</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-reportes" id="tabla-fin-enl">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Id FR</th><th>Fecha</th><th>Cliente</th><th>Id sol.</th><th>Estado</th><th>Perfil pub. est.</th><th>Perfil Motus</th><th>Gén. pub.</th><th>Gén. Motus</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rep. Correos -->
                    <div id="panel-emails" class="report-panel" style="display: <?php echo $submenu === 'emails' ? 'block' : 'none'; ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="card"><div class="card-body text-center">
                                    <div class="text-muted small">Enviados</div>
                                    <div class="h4 mb-0 text-success" id="emailsTotalEnviados">0</div>
                                </div></div>
                            </div>
                            <div class="col-md-3">
                                <div class="card"><div class="card-body text-center">
                                    <div class="text-muted small">No enviados</div>
                                    <div class="h4 mb-0 text-danger" id="emailsTotalFallidos">0</div>
                                </div></div>
                            </div>
                            <div class="col-md-3">
                                <div class="card"><div class="card-body text-center">
                                    <div class="text-muted small">Total</div>
                                    <div class="h4 mb-0" id="emailsTotalGeneral">0</div>
                                </div></div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                                    <div>
                                        <label class="form-label mb-1">Estado</label>
                                        <select id="filtroEstadoEmail" class="form-select form-select-sm">
                                            <option value="">Todos</option>
                                            <option value="enviado">Enviado</option>
                                            <option value="fallido">No enviado</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Desde</label>
                                        <input type="date" id="filtroDesdeEmail" class="form-control form-control-sm">
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Hasta</label>
                                        <input type="date" id="filtroHastaEmail" class="form-control form-control-sm">
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnFiltrarEmails">
                                        <i class="fas fa-filter me-1"></i>Filtrar
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-reportes" id="tabla-emails">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Estado</th>
                                                <th>Correo</th>
                                                <th>Tipo</th>
                                                <th>Solicitud</th>
                                                <th>Cliente</th>
                                                <th>Mensaje</th>
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
    </div>

    <!-- Modal: Solicitudes por usuario/estado -->
    <div class="modal fade" id="modalSolicitudesUsuario" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header reportes">
                    <h5 class="modal-title"><i class="fas fa-list me-2"></i>Solicitudes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2" id="modalSolicitudesTitulo"></p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Id</th><th>Cliente</th><th>Cédula</th><th>Estado</th><th>Fecha creación</th><th>Última actualización</th></tr></thead>
                            <tbody id="modalSolicitudesBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Historial de solicitud -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header reportes">
                    <h5 class="modal-title"><i class="fas fa-history me-2"></i>Historial de cambios</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2" id="modalHistorialTitulo"></p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Descripción</th><th>Estado anterior</th><th>Estado nuevo</th></tr></thead>
                            <tbody id="modalHistorialBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
(function() {
    const submenu = '<?php echo $submenu; ?>';
    let telChartDispositivo = null;
    let telChartUbicacion = null;
    let telChartResolucion = null;
    let bancoSolicitudesChart = null;
    const finPubCharts = {};
    const finEnlCharts = {};

    if (submenu === 'usuarios') {
        loadReporteUsuarios();
    } else if (submenu === 'vendedores') {
        loadReporteVendedores();
    } else if (submenu === 'tiempo') {
        loadReporteTiempo();
    } else if (submenu === 'banco') {
        loadReporteBanco();
    } else if (submenu === 'emails') {
        loadReporteEmails();
    } else if (submenu === 'encuestas') {
        loadReporteEncuestas();
    } else if (submenu === 'telemetria') {
        loadReporteTelemetria();
    } else if (submenu === 'fin_publica') {
        loadFinPublicaDemografia();
    } else if (submenu === 'fin_enlazada') {
        loadFinEnlazadaDemografia();
    }

    function loadReporteUsuarios() {
        fetch('api/reportes.php?action=reporte_usuarios')
            .then(r => r.json())
            .then(data => {
                if (!data.success) { document.querySelector('#tabla-usuarios tbody').innerHTML = '<tr><td colspan="8" class="text-center text-muted">Sin datos</td></tr>'; return; }
                const estados = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
                let html = '';
                data.data.forEach(row => {
                    html += '<tr><td>' + escapeHtml(row.nombre) + '<br><small class="text-muted">' + escapeHtml(row.email) + '</small></td>';
                    estados.forEach(est => {
                        const n = row[est] != null ? row[est] : 0;
                        html += '<td class="text-center"><span class="total-click" data-usuario-id="' + row.usuario_id + '" data-estado="' + escapeHtml(est) + '" data-usuario-nombre="' + escapeHtml(row.nombre) + '">' + n + '</span></td>';
                    });
                    html += '<td class="text-center fw-bold">' + (row.total || 0) + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="8" class="text-center text-muted">Sin datos</td></tr>';
                document.querySelector('#tabla-usuarios tbody').innerHTML = html;
                document.querySelectorAll('.total-click').forEach(el => {
                    el.addEventListener('click', function() {
                        const usuarioId = this.getAttribute('data-usuario-id');
                        const estado = this.getAttribute('data-estado');
                        const usuarioNombre = this.getAttribute('data-usuario-nombre');
                        abrirModalSolicitudes(usuarioId, estado, usuarioNombre);
                    });
                });
            })
            .catch(() => { document.querySelector('#tabla-usuarios tbody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar</td></tr>'; });
    }

    function loadReporteVendedores() {
        fetch('api/reportes.php?action=reporte_vendedores')
            .then(r => r.json())
            .then(data => {
                if (!data.success) { document.querySelector('#tabla-vendedores tbody').innerHTML = '<tr><td colspan="8" class="text-center text-muted">Sin datos</td></tr>'; return; }
                const estados = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
                let html = '';
                data.data.forEach(row => {
                    html += '<tr><td>' + escapeHtml(row.nombre || '') + '<br><small class="text-muted">' + escapeHtml(row.email || '') + '</small></td>';
                    estados.forEach(est => {
                        const n = row[est] != null ? row[est] : 0;
                        html += '<td class="text-center"><span class="total-click-vendedor" data-vendedor-id="' + row.vendedor_id + '" data-estado="' + escapeHtml(est) + '" data-vendedor-nombre="' + escapeHtml(row.nombre || '') + '">' + n + '</span></td>';
                    });
                    html += '<td class="text-center fw-bold">' + (row.total || 0) + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="8" class="text-center text-muted">Sin datos</td></tr>';
                document.querySelector('#tabla-vendedores tbody').innerHTML = html;
                document.querySelectorAll('.total-click-vendedor').forEach(el => {
                    el.addEventListener('click', function() {
                        const vendedorId = this.getAttribute('data-vendedor-id');
                        const estado = this.getAttribute('data-estado');
                        const vendedorNombre = this.getAttribute('data-vendedor-nombre');
                        abrirModalSolicitudesVendedor(vendedorId, estado, vendedorNombre);
                    });
                });
            })
            .catch(() => { document.querySelector('#tabla-vendedores tbody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar</td></tr>'; });
    }

    function abrirModalSolicitudes(usuarioId, estado, usuarioNombre) {
        document.getElementById('modalSolicitudesTitulo').textContent = 'Usuario: ' + usuarioNombre + ' — Estado: ' + estado;
        document.getElementById('modalSolicitudesBody').innerHTML = '<tr><td colspan="6" class="text-center">Cargando…</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('modalSolicitudesUsuario'));
        modal.show();
        fetch('api/reportes.php?action=solicitudes_usuario_estado&usuario_id=' + encodeURIComponent(usuarioId) + '&estado=' + encodeURIComponent(estado))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('modalSolicitudesBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin solicitudes</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(s => {
                    html += '<tr><td>' + s.id + '</td><td>' + escapeHtml(s.nombre_cliente || '') + '</td><td>' + escapeHtml(s.cedula || '') + '</td><td>' + escapeHtml(s.estado || '') + '</td><td>' + (s.fecha_creacion || '') + '</td><td>' + (s.fecha_actualizacion || '') + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="6" class="text-center text-muted">Sin solicitudes</td></tr>';
                document.getElementById('modalSolicitudesBody').innerHTML = html;
            });
    }

    function abrirModalSolicitudesVendedor(vendedorId, estado, vendedorNombre) {
        document.getElementById('modalSolicitudesTitulo').textContent = 'Vendedor: ' + vendedorNombre + ' — Estado: ' + estado;
        document.getElementById('modalSolicitudesBody').innerHTML = '<tr><td colspan="6" class="text-center">Cargando…</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('modalSolicitudesUsuario'));
        modal.show();
        fetch('api/reportes.php?action=solicitudes_vendedor_estado&vendedor_id=' + encodeURIComponent(vendedorId) + '&estado=' + encodeURIComponent(estado))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('modalSolicitudesBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin solicitudes</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(s => {
                    html += '<tr><td>' + s.id + '</td><td>' + escapeHtml(s.nombre_cliente || '') + '</td><td>' + escapeHtml(s.cedula || '') + '</td><td>' + escapeHtml(s.estado || '') + '</td><td>' + (s.fecha_creacion || '') + '</td><td>' + (s.fecha_actualizacion || '') + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="6" class="text-center text-muted">Sin solicitudes</td></tr>';
                document.getElementById('modalSolicitudesBody').innerHTML = html;
            });
    }

    function loadReporteTiempo() {
        fetch('api/reportes.php?action=reporte_tiempo')
            .then(r => r.json())
            .then(data => {
                if (!data.success) { document.querySelector('#tabla-tiempo tbody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Sin datos</td></tr>'; return; }
                let html = '';
                data.data.forEach(row => {
                    let tiempo = '';
                    if (row.dias_en_estado_actual != null) {
                        if (row.dias_en_estado_actual > 0) tiempo = row.dias_en_estado_actual + ' día(s)';
                        else tiempo = (row.horas_en_estado_actual || 0) + ' hora(s)';
                    } else { tiempo = '-'; }
                    html += '<tr><td>' + row.id + '</td><td>' + escapeHtml(row.nombre_cliente || '') + '</td><td>' + escapeHtml(row.cedula || '') + '</td><td>' + escapeHtml(row.estado || '') + '</td><td>' + (row.fecha_actualizacion || '-') + '</td><td>' + tiempo + '</td><td><button type="button" class="btn btn-sm btn-outline-primary btn-ver-historial" data-id="' + row.id + '" data-cliente="' + escapeHtml(row.nombre_cliente || '') + '" title="Ver historial"><i class="fas fa-eye"></i></button></td></tr>';
                });
                if (!html) html = '<tr><td colspan="7" class="text-center text-muted">Sin datos</td></tr>';
                document.querySelector('#tabla-tiempo tbody').innerHTML = html;
                document.querySelectorAll('.btn-ver-historial').forEach(btn => {
                    btn.addEventListener('click', function() {
                        abrirModalHistorial(this.getAttribute('data-id'), this.getAttribute('data-cliente'));
                    });
                });
            })
            .catch(() => { document.querySelector('#tabla-tiempo tbody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar</td></tr>'; });
    }

    function loadReporteBanco() {
        fetch('api/reportes.php?action=reporte_banco')
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#tabla-banco tbody');
                if (!data.success) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>'; return; }
                const porBanco = {};
                let html = '';
                data.data.forEach(row => {
                    let tiempo = '-';
                    if (row.pendiente) {
                        tiempo = '<span class="text-muted">Pendiente</span>';
                    } else if (row.dias_respuesta != null) {
                        if (row.dias_respuesta > 0) tiempo = row.dias_respuesta + ' día(s)';
                        else tiempo = (row.horas_respuesta || 0) + ' hora(s)';
                    }
                    const fechaResp = row.fecha_respuesta ? row.fecha_respuesta : '-';
                    const bancoNombre = (row.banco_nombre && String(row.banco_nombre).trim() !== '') ? String(row.banco_nombre) : 'Sin banco';
                    porBanco[bancoNombre] = (porBanco[bancoNombre] || 0) + 1;
                    html += '<tr><td>' + row.solicitud_id + '</td><td>' + escapeHtml(row.nombre_cliente || '') + '</td><td>' + escapeHtml(row.banco_nombre || '-') + '</td><td>' + (row.fecha_asignacion || '-') + '</td><td>' + fechaResp + '</td><td>' + tiempo + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>';
                tbody.innerHTML = html;

                if (bancoSolicitudesChart) {
                    bancoSolicitudesChart.destroy();
                    bancoSolicitudesChart = null;
                }
                const chartEl = document.getElementById('chartSolicitudesPorBanco');
                if (chartEl && typeof Chart !== 'undefined') {
                    const labels = Object.keys(porBanco);
                    const values = Object.values(porBanco);
                    bancoSolicitudesChart = new Chart(chartEl, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Solicitudes',
                                data: values,
                                backgroundColor: '#667eea'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 }
                                }
                            }
                        }
                    });
                }
            })
            .catch(() => { document.querySelector('#tabla-banco tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar</td></tr>'; });
    }

    function loadReporteEmails() {
        const estado = (document.getElementById('filtroEstadoEmail') || {}).value || '';
        const desde = (document.getElementById('filtroDesdeEmail') || {}).value || '';
        const hasta = (document.getElementById('filtroHastaEmail') || {}).value || '';
        const q = new URLSearchParams({ action: 'reporte_emails_resumen' });
        if (estado) q.set('estado', estado);
        if (desde) q.set('desde', desde);
        if (hasta) q.set('hasta', hasta);

        fetch('api/reportes.php?' + q.toString())
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#tabla-emails tbody');
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar</td></tr>';
                    return;
                }
                document.getElementById('emailsTotalEnviados').textContent = String(data.resumen?.enviados ?? 0);
                document.getElementById('emailsTotalFallidos').textContent = String(data.resumen?.fallidos ?? 0);
                document.getElementById('emailsTotalGeneral').textContent = String(data.resumen?.total ?? 0);

                let html = '';
                (data.data || []).forEach(row => {
                    const badge = row.estado === 'enviado'
                        ? '<span class="badge bg-success">Enviado</span>'
                        : '<span class="badge bg-danger">No enviado</span>';
                    html += '<tr>'
                        + '<td>' + escapeHtml(row.fecha_envio || '-') + '</td>'
                        + '<td>' + badge + '</td>'
                        + '<td>' + escapeHtml(row.destinatario_email || '-') + '</td>'
                        + '<td>' + escapeHtml(row.tipo_envio || '-') + '</td>'
                        + '<td>#' + escapeHtml(String(row.solicitud_id || '')) + '</td>'
                        + '<td>' + escapeHtml(row.nombre_cliente || '-') + '</td>'
                        + '<td>' + escapeHtml(row.mensaje || '-') + '</td>'
                        + '</tr>';
                });
                if (!html) html = '<tr><td colspan="7" class="text-center text-muted">Sin datos</td></tr>';
                tbody.innerHTML = html;
            })
            .catch(() => {
                document.querySelector('#tabla-emails tbody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar</td></tr>';
            });
    }

    function abrirModalHistorial(solicitudId, cliente) {
        document.getElementById('modalHistorialTitulo').textContent = 'Solicitud #' + solicitudId + ' — ' + cliente;
        document.getElementById('modalHistorialBody').innerHTML = '<tr><td colspan="6" class="text-center">Cargando…</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
        modal.show();
        fetch('api/reportes.php?action=historial_solicitud&solicitud_id=' + encodeURIComponent(solicitudId))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) {
                    document.getElementById('modalHistorialBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin historial registrado</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(h => {
                    html += '<tr><td>' + (h.fecha_creacion || '') + '</td><td>' + escapeHtml(h.usuario_nombre || 'Sistema') + '</td><td>' + escapeHtml(h.tipo_label || h.tipo_accion) + '</td><td>' + escapeHtml(h.descripcion || '') + '</td><td>' + escapeHtml(h.estado_anterior || '-') + '</td><td>' + escapeHtml(h.estado_nuevo || '-') + '</td></tr>';
                });
                document.getElementById('modalHistorialBody').innerHTML = html;
            });
    }

    function escapeHtml(s) {
        if (s == null) return '';
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    const btnFiltrarEmails = document.getElementById('btnFiltrarEmails');
    if (btnFiltrarEmails) {
        btnFiltrarEmails.addEventListener('click', loadReporteEmails);
    }
    const btnFiltrarUsuarios = document.getElementById('btnFiltrarUsuarios');
    if (btnFiltrarUsuarios) {
        btnFiltrarUsuarios.addEventListener('click', aplicarFiltroUsuarios);
    }
    const btnFiltrarVendedores = document.getElementById('btnFiltrarVendedores');
    if (btnFiltrarVendedores) {
        btnFiltrarVendedores.addEventListener('click', aplicarFiltroVendedores);
    }
    const btnFiltrarTiempo = document.getElementById('btnFiltrarTiempo');
    if (btnFiltrarTiempo) {
        btnFiltrarTiempo.addEventListener('click', aplicarFiltroTiempo);
    }
    const btnFiltrarBanco = document.getElementById('btnFiltrarBanco');
    if (btnFiltrarBanco) {
        btnFiltrarBanco.addEventListener('click', aplicarFiltroBanco);
    }
    const btnFiltrarEncuestas = document.getElementById('btnFiltrarEncuestas');
    if (btnFiltrarEncuestas) {
        btnFiltrarEncuestas.addEventListener('click', aplicarFiltroEncuestas);
    }
    const btnFiltrarTelemetria = document.getElementById('btnFiltrarTelemetria');
    if (btnFiltrarTelemetria) {
        btnFiltrarTelemetria.addEventListener('click', aplicarFiltroTelemetria);
    }
    const btnFinPubFiltrar = document.getElementById('btnFinPubFiltrar');
    if (btnFinPubFiltrar) btnFinPubFiltrar.addEventListener('click', loadFinPublicaDemografia);
    const btnFinEnlFiltrar = document.getElementById('btnFinEnlFiltrar');
    if (btnFinEnlFiltrar) btnFinEnlFiltrar.addEventListener('click', loadFinEnlazadaDemografia);

    function finDestroyCharts(map) {
        Object.keys(map).forEach(function(k) {
            if (map[k]) {
                map[k].destroy();
                map[k] = null;
            }
        });
    }

    function finCheckedGeneros(selClass) {
        const out = [];
        document.querySelectorAll(selClass).forEach(function(cb) {
            if (cb.checked) out.push(cb.value);
        });
        return out;
    }

    function finPubQueryString() {
        const p = new URLSearchParams();
        p.set('desde', (document.getElementById('finPubDesde') || {}).value || '');
        p.set('hasta', (document.getElementById('finPubHasta') || {}).value || '');
        const gens = finCheckedGeneros('.fin-pub-gen');
        if (gens.length && gens.length < 4) p.set('generos', gens.join(','));
        const perf = (document.getElementById('finPubPerfil') || {}).value || '';
        if (perf) p.set('perfil', perf);
        const sec = (document.getElementById('finPubSector') || {}).value || '';
        if (sec) p.set('sector', sec);
        return p.toString();
    }

    function finEnlQueryString() {
        const p = new URLSearchParams();
        p.set('desde', (document.getElementById('finEnlDesde') || {}).value || '');
        p.set('hasta', (document.getElementById('finEnlHasta') || {}).value || '');
        const gens = finCheckedGeneros('.fin-enl-gen');
        if (gens.length && gens.length < 4) p.set('generos', gens.join(','));
        const perf = (document.getElementById('finEnlPerfil') || {}).value || '';
        if (perf) p.set('perfil', perf);
        const sec = (document.getElementById('finEnlSector') || {}).value || '';
        if (sec) p.set('sector', sec);
        const est = (document.getElementById('finEnlEstado') || {}).value || '';
        if (est) p.set('estado_sc', est);
        return p.toString();
    }

    function finObjToLabelsValues(obj, ordenPref) {
        const labels = [];
        const values = [];
        const keys = ordenPref ? ordenPref.filter(function(k) { return obj && (obj[k] || 0) > 0; }) : Object.keys(obj || {});
        const rest = Object.keys(obj || {}).filter(function(k) { return keys.indexOf(k) === -1; }).sort();
        keys.concat(rest).forEach(function(k) {
            const v = (obj && obj[k]) ? Number(obj[k]) : 0;
            if (v > 0 || !ordenPref) {
                labels.push(k);
                values.push(v);
            }
        });
        return { labels: labels, values: values };
    }

    function finRenderBarH(canvasId, labels, data, color, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const filteredL = [];
        const filteredD = [];
        for (let i = 0; i < labels.length; i++) {
            const v = Number(data[i] || 0);
            if (v > 0) {
                filteredL.push(labels[i]);
                filteredD.push(v);
            }
        }
        if (!filteredL.length) {
            filteredL.push('Sin datos');
            filteredD.push(0);
        }
        store[key] = new Chart(el, {
            type: 'bar',
            data: {
                labels: filteredL,
                datasets: [{ label: 'Cantidad', data: filteredD, backgroundColor: color || '#667eea' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function finRenderStackedBar(canvasId, payload, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const labels = payload.labels || [];
        const colors = ['#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6610f2', '#fd7e14'];
        const datasets = (payload.datasets || []).map(function(ds, idx) {
            return {
                label: ds.label,
                data: ds.data,
                backgroundColor: colors[idx % colors.length]
            };
        });
        store[key] = new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: false },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function finRenderGroupedBar(canvasId, labelsEstado, labelsPerfil, matrix, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const colors = ['#0d6efd', '#198754', '#ffc107'];
        const datasets = labelsPerfil.map(function(pf, j) {
            return {
                label: pf,
                data: (matrix || []).map(function(row) { return Number(row[j] || 0); }),
                backgroundColor: colors[j % colors.length]
            };
        });
        store[key] = new Chart(el, {
            type: 'bar',
            data: { labels: labelsEstado, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { stacked: false }, y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function loadFinPublicaDemografia() {
        const qs = finPubQueryString();
        const ex = document.getElementById('finPubExportXlsx');
        if (ex) ex.href = 'api/reportes.php?action=exportar_excel_fin_publica&' + qs;
        const nota = document.getElementById('finPubNotaApi');
        if (nota) nota.textContent = '';
        fetch('api/reportes.php?action=reporte_fin_publica_demografia&' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    if (nota) nota.textContent = data.message || 'No se pudo cargar';
                    finDestroyCharts(finPubCharts);
                    return;
                }
                if (nota) nota.textContent = '';
                const st = data.stats || {};
                document.getElementById('finPubKpiN').textContent = String(st.n != null ? st.n : 0);
                document.getElementById('finPubKpiEdad').textContent = st.edad_promedio != null ? String(st.edad_promedio) : '—';
                document.getElementById('finPubKpiSal').textContent = st.salario_promedio_usd != null ? numFmt(st.salario_promedio_usd) : '—';
                const f = data.filtros || {};
                document.getElementById('finPubKpiFechas').textContent = (f.fecha_desde || '') + ' → ' + (f.fecha_hasta || '');

                finDestroyCharts(finPubCharts);
                const og = data.orden_generos || ['Femenino', 'Masculino', 'Otro', 'Sin dato'];
                const gv = finObjToLabelsValues(data.por_genero, og);
                finPubCharts.gen = renderPieChart('finPubChartGen', gv.labels, gv.values);

                const os = data.orden_rangos_salario || [];
                const sv = finObjToLabelsValues(data.por_rango_salario, os);
                finRenderBarH('finPubChartSal', sv.labels, sv.values, '#198754', finPubCharts, 'sal');

                const oe = data.orden_rangos_edad || [];
                const ev = finObjToLabelsValues(data.por_rango_edad, oe);
                finRenderBarH('finPubChartEdad', ev.labels, ev.values, '#6f42c1', finPubCharts, 'edad');

                const pv = finObjToLabelsValues(data.por_perfil_estimado, null);
                finPubCharts.perfil = renderPieChart('finPubChartPerfil', pv.labels, pv.values);

                const secv = finObjToLabelsValues(data.por_sector_asalariado_estimado, null);
                finPubCharts.sector = renderPieChart('finPubChartSector', secv.labels, secv.values);

                finRenderStackedBar('finPubChartCruce', data.cruce_salario_genero || { labels: [], datasets: [] }, finPubCharts, 'cruce');

                const tbody = document.querySelector('#tabla-fin-pub tbody');
                const muestra = data.muestra || [];
                if (!tbody) return;
                if (!muestra.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Sin registros con estos filtros</td></tr>';
                    return;
                }
                let html = '';
                muestra.forEach(function(m) {
                    html += '<tr><td>' + escapeHtml(String(m.id || '')) + '</td>'
                        + '<td class="text-nowrap"><small>' + escapeHtml(String(m.fecha_creacion || '')) + '</small></td>'
                        + '<td>' + escapeHtml(String(m.cliente_nombre || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.genero_label || '')) + '</td>'
                        + '<td>' + escapeHtml(m.edad_calculada != null ? String(m.edad_calculada) : '—') + '</td>'
                        + '<td><small>' + escapeHtml(String(m.rango_salario_usd || '')) + '</small></td>'
                        + '<td><small>' + escapeHtml(String(m.perfil_estimado || '')) + '</small></td>'
                        + '<td><small>' + escapeHtml(String(m.sector_estimado || '')) + '</small></td></tr>';
                });
                tbody.innerHTML = html;
            })
            .catch(function() {
                const n2 = document.getElementById('finPubNotaApi');
                if (n2) n2.textContent = 'Error de red o servidor';
            });
    }

    function loadFinEnlazadaDemografia() {
        const qs = finEnlQueryString();
        const ex = document.getElementById('finEnlExportXlsx');
        if (ex) ex.href = 'api/reportes.php?action=exportar_excel_fin_enlazada&' + qs;
        const nota = document.getElementById('finEnlNotaApi');
        if (nota) nota.textContent = '';
        fetch('api/reportes.php?action=reporte_fin_publica_enlazada&' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    if (nota) nota.textContent = data.message || 'No se pudo cargar';
                    finDestroyCharts(finEnlCharts);
                    return;
                }
                if (nota) nota.textContent = '';
                const st = data.stats || {};
                document.getElementById('finEnlKpiN').textContent = String(st.n != null ? st.n : 0);
                const comp = (data.enlazada && data.enlazada.comparacion) ? data.enlazada.comparacion : {};
                document.getElementById('finEnlKpiPerfilOk').textContent = String(comp.perfil_coincide != null ? comp.perfil_coincide : '—')
                    + ' / ' + String(comp.perfil_distinto != null ? comp.perfil_distinto : '—');
                document.getElementById('finEnlKpiGenOk').textContent = String(comp.genero_coincide != null ? comp.genero_coincide : '—')
                    + ' / ' + String(comp.genero_distinto != null ? comp.genero_distinto : '—');
                const f = data.filtros || {};
                document.getElementById('finEnlKpiFechas').textContent = (f.fecha_desde || '') + ' → ' + (f.fecha_hasta || '');

                finDestroyCharts(finEnlCharts);
                const enl = data.enlazada || {};
                const pe = finObjToLabelsValues(enl.por_estado_solicitud, null);
                finEnlCharts.estado = renderPieChart('finEnlChartEstado', pe.labels, pe.values);

                const pm = finObjToLabelsValues(enl.por_perfil_motus, ['Asalariado', 'Jubilado', 'Independiente']);
                finEnlCharts.pm = renderPieChart('finEnlChartPerfilMotus', pm.labels, pm.values);

                const og = data.orden_generos || ['Femenino', 'Masculino', 'Otro', 'Sin dato'];
                const gv = finObjToLabelsValues(data.por_genero, og);
                finEnlCharts.gen = renderPieChart('finEnlChartGen', gv.labels, gv.values);

                const cr = enl.cruce_estado_perfil_motus || {};
                finRenderGroupedBar('finEnlChartCruce', cr.labels_estado || [], cr.labels_perfil || [], cr.matrix || [], finEnlCharts, 'cruce');

                const ppub = finObjToLabelsValues(data.por_perfil_estimado, null);
                finEnlCharts.ppub = renderPieChart('finEnlChartPerfilPub', ppub.labels, ppub.values);

                const os = data.orden_rangos_salario || [];
                const sv = finObjToLabelsValues(data.por_rango_salario, os);
                finRenderBarH('finEnlChartSal', sv.labels, sv.values, '#198754', finEnlCharts, 'sal');

                finRenderStackedBar('finEnlChartCruceSal', data.cruce_salario_genero || { labels: [], datasets: [] }, finEnlCharts, 'cruceSal');

                const tbody = document.querySelector('#tabla-fin-enl tbody');
                const muestra = data.muestra || [];
                if (!tbody) return;
                if (!muestra.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Sin registros enlazados con estos filtros</td></tr>';
                    return;
                }
                let html = '';
                muestra.forEach(function(m) {
                    html += '<tr><td>' + escapeHtml(String(m.id || '')) + '</td>'
                        + '<td class="text-nowrap"><small>' + escapeHtml(String(m.fecha_creacion || '')) + '</small></td>'
                        + '<td>' + escapeHtml(String(m.cliente_nombre || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.solicitud_id || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.solicitud_estado || '')) + '</td>'
                        + '<td><small>' + escapeHtml(String(m.perfil_estimado || '')) + '</small></td>'
                        + '<td><small>' + escapeHtml(String(m.perfil_motus || '')) + '</small></td>'
                        + '<td>' + escapeHtml(String(m.genero_label || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.genero_motus || '')) + '</td></tr>';
                });
                tbody.innerHTML = html;
            })
            .catch(function() {
                const n2 = document.getElementById('finEnlNotaApi');
                if (n2) n2.textContent = 'Error de red o servidor';
            });
    }

    function numFmt(v) {
        if (v == null) return '—';
        return String(v).replace('.', ',');
    }

    function renderPieChart(canvasId, labels, values) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return null;
        const filtered = [];
        for (let i = 0; i < labels.length; i++) {
            const val = Number(values[i] || 0);
            if (val > 0) filtered.push({ label: String(labels[i]), value: val });
        }
        if (!filtered.length) {
            filtered.push({ label: 'Sin datos', value: 1 });
        }
        return new Chart(el, {
            type: 'pie',
            data: {
                labels: filtered.map(x => x.label),
                datasets: [{
                    data: filtered.map(x => x.value),
                    backgroundColor: [
                        '#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6610f2', '#fd7e14', '#198754', '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    function aplicarFiltroUsuarios() {
        const txt = ((document.getElementById('filtroUsuariosTexto') || {}).value || '').toLowerCase().trim();
        const estado = ((document.getElementById('filtroUsuariosEstado') || {}).value || '').trim();
        const minRaw = ((document.getElementById('filtroUsuariosMinTotal') || {}).value || '').trim();
        const minTotal = minRaw === '' ? null : parseInt(minRaw, 10);
        const filas = document.querySelectorAll('#tabla-usuarios tbody tr');
        const mapEstado = { 'Nueva': 1, 'En Revisión Banco': 2, 'Aprobada': 3, 'Rechazada': 4, 'Completada': 5, 'Desistimiento': 6 };
        filas.forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const celdas = tr.querySelectorAll('td');
            const ref = (celdas[0]?.innerText || '').toLowerCase();
            const total = parseInt((celdas[7]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (estado && mapEstado[estado] != null) {
                const idx = mapEstado[estado];
                const valEstado = parseInt((celdas[idx]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
                if (valEstado <= 0) visible = false;
            }
            if (minTotal !== null && !isNaN(minTotal) && total < minTotal) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    function aplicarFiltroVendedores() {
        const txt = ((document.getElementById('filtroVendedoresTexto') || {}).value || '').toLowerCase().trim();
        const estado = ((document.getElementById('filtroVendedoresEstado') || {}).value || '').trim();
        const minRaw = ((document.getElementById('filtroVendedoresMinTotal') || {}).value || '').trim();
        const minTotal = minRaw === '' ? null : parseInt(minRaw, 10);
        const filas = document.querySelectorAll('#tabla-vendedores tbody tr');
        const mapEstado = { 'Nueva': 1, 'En Revisión Banco': 2, 'Aprobada': 3, 'Rechazada': 4, 'Completada': 5, 'Desistimiento': 6 };
        filas.forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const celdas = tr.querySelectorAll('td');
            const ref = (celdas[0]?.innerText || '').toLowerCase();
            const total = parseInt((celdas[7]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (estado && mapEstado[estado] != null) {
                const idx = mapEstado[estado];
                const valEstado = parseInt((celdas[idx]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
                if (valEstado <= 0) visible = false;
            }
            if (minTotal !== null && !isNaN(minTotal) && total < minTotal) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    function aplicarFiltroTiempo() {
        const txt = ((document.getElementById('filtroTiempoTexto') || {}).value || '').toLowerCase().trim();
        const estado = ((document.getElementById('filtroTiempoEstado') || {}).value || '').trim();
        const desde = ((document.getElementById('filtroTiempoDesde') || {}).value || '').trim();
        const hasta = ((document.getElementById('filtroTiempoHasta') || {}).value || '').trim();
        const filas = document.querySelectorAll('#tabla-tiempo tbody tr');
        filas.forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const c = tr.querySelectorAll('td');
            const ref = ((c[1]?.innerText || '') + ' ' + (c[2]?.innerText || '')).toLowerCase();
            const est = (c[3]?.innerText || '').trim();
            const fecha = (c[4]?.innerText || '').slice(0, 10);
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (estado && est !== estado) visible = false;
            if (desde && fecha && fecha < desde) visible = false;
            if (hasta && fecha && fecha > hasta) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    function aplicarFiltroBanco() {
        const txt = ((document.getElementById('filtroBancoTexto') || {}).value || '').toLowerCase().trim();
        const pendiente = ((document.getElementById('filtroBancoPendiente') || {}).value || '').trim();
        const filas = document.querySelectorAll('#tabla-banco tbody tr');
        filas.forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const c = tr.querySelectorAll('td');
            const ref = ((c[1]?.innerText || '') + ' ' + (c[2]?.innerText || '')).toLowerCase();
            const tiempoTxt = (c[5]?.innerText || '').toLowerCase();
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (pendiente === 'si' && tiempoTxt.indexOf('pendiente') === -1) visible = false;
            if (pendiente === 'no' && tiempoTxt.indexOf('pendiente') !== -1) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    function aplicarFiltroEncuestas() {
        const txt = ((document.getElementById('filtroEncuestasTexto') || {}).value || '').toLowerCase().trim();
        const filas = document.querySelectorAll('#encuestas-contenido table tbody tr');
        filas.forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const ref = (tr.innerText || '').toLowerCase();
            tr.style.display = (!txt || ref.indexOf(txt) !== -1) ? '' : 'none';
        });
    }

    function aplicarFiltroTelemetria() {
        const txt = ((document.getElementById('filtroTelemetriaTexto') || {}).value || '').toLowerCase().trim();
        const durMinRaw = ((document.getElementById('filtroTelemetriaDurMin') || {}).value || '').trim();
        const durMin = durMinRaw === '' ? null : parseInt(durMinRaw, 10);
        const desde = ((document.getElementById('filtroTelemetriaDesde') || {}).value || '').trim();
        const hasta = ((document.getElementById('filtroTelemetriaHasta') || {}).value || '').trim();
        const filas = document.querySelectorAll('#tabla-telemetria tbody tr');
        filas.forEach(function(tr) {
            if (tr.querySelector('td[colspan]')) return;
            const c = tr.querySelectorAll('td');
            const ref = ((c[1]?.innerText || '') + ' ' + (c[2]?.innerText || '') + ' ' + (c[3]?.innerText || '') + ' ' + (c[4]?.innerText || '')).toLowerCase();
            const fecha = (c[0]?.getAttribute('data-date') || '');
            const durTxt = (c[6]?.innerText || '').replace(/[^\d]/g, '');
            const dur = durTxt ? parseInt(durTxt, 10) : 0;
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (durMin !== null && !isNaN(durMin) && dur < durMin) visible = false;
            if (desde && fecha && fecha < desde) visible = false;
            if (hasta && fecha && fecha > hasta) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    function buildBloqueEnc(titleClass, label, data) {
        if (data.error) {
            return '<div class="alert alert-warning">' + escapeHtml(data.error) + '</div>';
        }
        const res = data.resumen || {};
        const preg = data.preguntas || {};
        const tot = res.total != null ? res.total : 0;
        const pg = res.promedio_global;
        const cr = res.con_recomendacion != null ? res.con_recomendacion : 0;
        const desde = res.desde || '—';
        const hasta = res.hasta || '—';
        const pr = res.promedios || {};

        let kpi = '<div class="row g-3 mb-3 enc-kpi">'
            + '<div class="col-6 col-md-3"><div class="card shadow-sm p-3 text-center"><div class="text-muted small">Respuestas</div><div class="h4 mb-0 text-primary">' + tot + '</div></div></div>'
            + '<div class="col-6 col-md-3"><div class="card shadow-sm p-3 text-center"><div class="text-muted small">Promedio general (1–5)</div><div class="h4 mb-0 text-success">' + (pg == null ? '—' : numFmt(pg)) + '</div></div></div>'
            + '<div class="col-6 col-md-3"><div class="card shadow-sm p-3 text-center"><div class="text-muted small">Con recomendaciones</div><div class="h4 mb-0">' + cr + '</div></div></div>'
            + '<div class="col-6 col-md-3"><div class="card shadow-sm p-3 text-center"><div class="text-muted small">Período (primero → último)</div><div class="small"><strong>' + escapeHtml(String(desde)) + '</strong><br><span class="text-muted">↔</span> <strong>' + escapeHtml(String(hasta)) + '</strong></div></div></div>'
            + '</div>';

        let filasP = '<table class="table table-bordered table-sm table-reportes mb-4"><thead class="table-light"><tr><th>Ítem</th><th class="text-end">Promedio</th></tr></thead><tbody>';
        for (let n = 1; n <= 5; n++) {
            const prom = pr[n] != null ? pr[n] : null;
            filasP += '<tr><td><strong>P' + n + '</strong> ' + escapeHtml(preg[n] || '') + '</td><td class="text-end fw-bold">' + (prom == null ? '—' : numFmt(prom)) + '</td></tr>';
        }
        filasP += '</tbody></table>';

        const filas = data.filas || [];
        let tab = '<div class="table-responsive"><table class="table table-bordered table-sm table-hover table-reportes">'
            + '<thead class="table-light"><tr><th>Fecha</th><th>Nombre</th><th>Cargo</th><th class="text-center">P1</th><th class="text-center">P2</th><th class="text-center">P3</th><th class="text-center">P4</th><th class="text-center">P5</th><th class="text-end">Prom.</th><th>Recomendaciones</th></tr></thead><tbody>';
        if (!filas.length) {
            tab += '<tr><td colspan="10" class="text-center text-muted">Sin respuestas registradas</td></tr>';
        } else {
            filas.forEach(function(row) {
                const rec = (row.recomendaciones == null || String(row.recomendaciones).trim() === '') ? '—' : String(row.recomendaciones);
                const recHtml = rec === '—' ? '—' : ('<div class="small" style="max-width: 280px; white-space: pre-wrap;">' + escapeHtml(rec) + '</div>');
                tab += '<tr><td class="text-nowrap">' + escapeHtml(row.creado_en || '') + '</td>'
                    + '<td>' + escapeHtml(row.nombre_completo || '') + '</td>'
                    + '<td>' + escapeHtml(row.cargo || '') + '</td>'
                    + '<td class="text-center">' + (row.puntuacion_1 != null ? row.puntuacion_1 : '') + '</td>'
                    + '<td class="text-center">' + (row.puntuacion_2 != null ? row.puntuacion_2 : '') + '</td>'
                    + '<td class="text-center">' + (row.puntuacion_3 != null ? row.puntuacion_3 : '') + '</td>'
                    + '<td class="text-center">' + (row.puntuacion_4 != null ? row.puntuacion_4 : '') + '</td>'
                    + '<td class="text-center">' + (row.puntuacion_5 != null ? row.puntuacion_5 : '') + '</td>'
                    + '<td class="text-end fw-bold">' + (row.promedio_fila != null ? numFmt(row.promedio_fila) : '—') + '</td>'
                    + '<td>' + recHtml + '</td></tr>';
            });
        }
        tab += '</tbody></table></div>';
        if (filas.length >= 2000) {
            tab += '<p class="small text-muted">Mostrando las 2000 respuestas más recientes.</p>';
        }

        return '<div class="mb-5">'
            + '<div class="enc-bloque-title ' + titleClass + '"><i class="fas fa-clipboard-list me-2"></i>' + escapeHtml(label) + '</div>'
            + (tot === 0 && !data.error ? '<p class="text-muted small mb-2">Aún no hay encuestas enviadas o la tabla está vacía.</p>' : '')
            + kpi + '<h6 class="mb-2">Promedio por pregunta (escala 1 a 5)</h6>' + filasP
            + '<h6 class="mb-2">Detalle de respuestas (personas)</h6>' + tab
            + '</div>';
    }

    function loadReporteEncuestas() {
        const box = document.getElementById('encuestas-contenido');
        if (!box) return;
        box.innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
        fetch('api/reportes.php?action=reporte_encuestas')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    box.innerHTML = '<div class="alert alert-danger">No se pudo cargar el reporte.</div>';
                    return;
                }
                const v = data.vendedor;
                const g = data.gestor;
                box.innerHTML = buildBloqueEnc('v', 'Encuesta: formulario público (vendedores)', v) + buildBloqueEnc('g', 'Encuesta: proceso y sistema (gestores)', g);
                aplicarFiltroEncuestas();
            })
            .catch(function() {
                document.getElementById('encuestas-contenido').innerHTML = '<div class="alert alert-danger">Error de red o servidor al cargar encuestas.</div>';
            });
    }

    function loadReporteTelemetria() {
        const tbody = document.querySelector('#tabla-telemetria tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted">Cargando…</td></tr>';
        fetch('api/reportes.php?action=reporte_telemetria')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="13" class="text-center text-danger">' + escapeHtml(data.message || 'No se pudo cargar telemetría') + '</td></tr>';
                    return;
                }
                const res = data.resumen || {};
                document.getElementById('telTotalRegistros').textContent = String(res.total_registros ?? 0);
                document.getElementById('telDurProm').textContent = (res.duracion_promedio_seg != null ? String(res.duracion_promedio_seg) + ' seg' : '—');
                document.getElementById('telPromRegDia').textContent = (res.promedio_registros_diarios != null ? String(res.promedio_registros_diarios) : '—');
                document.getElementById('telDurPromMin').textContent = (res.duracion_promedio_min != null ? String(res.duracion_promedio_min) + ' min' : '—');
                const pp = res.paso_promedio_seg || {};
                const ppm = res.paso_promedio_min || {};
                document.getElementById('telPasoProm').textContent = ['0','1','2','3','4'].map(function(k){
                    const etiqueta = String.fromCharCode(65 + Number(k));
                    return etiqueta + ': ' + (pp[k] != null ? pp[k] : '—') + 's';
                }).join('  |  ');
                document.getElementById('telPasoPromMin').textContent = ['0','1','2','3','4'].map(function(k){
                    const etiqueta = String.fromCharCode(65 + Number(k));
                    return etiqueta + ': ' + (ppm[k] != null ? ppm[k] : '—') + 'm';
                }).join('  |  ');

                if (telChartDispositivo) telChartDispositivo.destroy();
                if (telChartUbicacion) telChartUbicacion.destroy();
                if (telChartResolucion) telChartResolucion.destroy();
                const distDisp = res.distribucion_dispositivo || {};
                telChartDispositivo = renderPieChart(
                    'telChartDispositivo',
                    Object.keys(distDisp),
                    Object.values(distDisp)
                );
                const distUbi = res.distribucion_ubicacion || {};
                telChartUbicacion = renderPieChart(
                    'telChartUbicacion',
                    Object.keys(distUbi),
                    Object.values(distUbi)
                );
                const distRes = res.distribucion_resolucion || {};
                telChartResolucion = renderPieChart(
                    'telChartResolucion',
                    Object.keys(distRes),
                    Object.values(distRes)
                );

                const rows = data.data || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted">Sin datos de telemetría</td></tr>';
                    return;
                }
                let html = '';
                rows.forEach(function(r) {
                    const f = String(r.fecha_creacion || '');
                    const fDate = f.length >= 10 ? f.slice(0, 10) : '';
                    const contacto = [r.celular_cliente || '', r.cliente_correo || ''].filter(Boolean).join('<br>');
                    const dispBase = (r.device_label || '').trim();
                    const disp = [dispBase, r.platform || '', r.viewport || '', r.timezone || ''].filter(Boolean).join(' | ');
                    const durSeg = (r.telemetria_duracion_segundos != null && !isNaN(Number(r.telemetria_duracion_segundos)))
                        ? Number(r.telemetria_duracion_segundos)
                        : null;
                    const durTxt = durSeg == null ? '—' : (durSeg + ' seg / ' + (durSeg / 60).toFixed(2).replace(/\.00$/, '') + ' min');
                    const geoTxt = [r.geo_city || '', r.geo_country || ''].filter(Boolean).join(', ');
                    html += '<tr>'
                        + '<td class="text-nowrap" data-date="' + escapeHtml(fDate) + '">' + escapeHtml(f) + '</td>'
                        + '<td>' + escapeHtml(r.cliente_nombre || '') + '</td>'
                        + '<td>' + escapeHtml(r.cliente_id || '') + '</td>'
                        + '<td>' + (contacto ? '<small>' + contacto + '</small>' : '—') + '</td>'
                        + '<td class="text-nowrap">' + escapeHtml(r.ip || '') + '</td>'
                        + '<td class="text-nowrap"><small>' + escapeHtml(geoTxt || '—') + '</small></td>'
                        + '<td class="text-end fw-bold">' + durTxt + '</td>'
                        + '<td class="text-end">' + (r.paso0_seg != null ? r.paso0_seg : 0) + '</td>'
                        + '<td class="text-end">' + (r.paso1_seg != null ? r.paso1_seg : 0) + '</td>'
                        + '<td class="text-end">' + (r.paso2_seg != null ? r.paso2_seg : 0) + '</td>'
                        + '<td class="text-end">' + (r.paso3_seg != null ? r.paso3_seg : 0) + '</td>'
                        + '<td class="text-end">' + (r.paso4_seg != null ? r.paso4_seg : 0) + '</td>'
                        + '<td><small>' + escapeHtml(disp || '—') + '</small></td>'
                        + '</tr>';
                });
                tbody.innerHTML = html;
                aplicarFiltroTelemetria();
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center text-danger">Error de red o servidor</td></tr>';
            });
    }
})();
    </script>
    <?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
