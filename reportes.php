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
if (!in_array($submenu, ['usuarios', 'tiempo', 'banco', 'emails', 'encuestas'], true)) {
    $submenu = 'usuarios';
}
$estadosCol = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
$titulosReporte = ['usuarios' => 'Rep. Usuarios', 'tiempo' => 'Rep. Tiempo', 'banco' => 'Rep. Banco', 'emails' => 'Rep. Correos', 'encuestas' => 'Rep. Encuestas'];
$exportActionPorSubmenu = [
    'usuarios' => ['action' => 'exportar_excel_usuarios', 'label' => 'Descargar Rep. Usuarios'],
    'tiempo' => ['action' => 'exportar_excel_tiempo', 'label' => 'Descargar Rep. Tiempo'],
    'banco' => ['action' => 'exportar_excel_banco', 'label' => 'Descargar Rep. Banco'],
    'emails' => ['action' => 'exportar_excel_correos', 'label' => 'Descargar Rep. Correos'],
    // En encuestas se prioriza vendedores; se deja botón adicional dentro del panel para gestores.
    'encuestas' => ['action' => 'exportar_excel_encuestas_vendedores', 'label' => 'Descargar Enc. Vendedores'],
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
                            elseif ($submenu === 'tiempo') echo 'Tiempo entre cambios de estado por solicitud';
                            elseif ($submenu === 'banco') echo 'Tiempo que tardan los bancos en dar respuesta a las solicitudes asignadas';
                            elseif ($submenu === 'encuestas') echo 'Promedios, totales y detalle de respuestas a las encuestas públicas (vendedores y gestores)';
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
    <script>
(function() {
    const submenu = '<?php echo $submenu; ?>';

    if (submenu === 'usuarios') {
        loadReporteUsuarios();
    } else if (submenu === 'tiempo') {
        loadReporteTiempo();
    } else if (submenu === 'banco') {
        loadReporteBanco();
    } else if (submenu === 'emails') {
        loadReporteEmails();
    } else if (submenu === 'encuestas') {
        loadReporteEncuestas();
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
                    html += '<tr><td>' + row.solicitud_id + '</td><td>' + escapeHtml(row.nombre_cliente || '') + '</td><td>' + escapeHtml(row.banco_nombre || '-') + '</td><td>' + (row.fecha_asignacion || '-') + '</td><td>' + fechaResp + '</td><td>' + tiempo + '</td></tr>';
                });
                if (!html) html = '<tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>';
                tbody.innerHTML = html;
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

    function numFmt(v) {
        if (v == null) return '—';
        return String(v).replace('.', ',');
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
})();
    </script>
    <?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
