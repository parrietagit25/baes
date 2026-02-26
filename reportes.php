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
if (!in_array($submenu, ['usuarios', 'tiempo', 'banco'], true)) {
    $submenu = 'usuarios';
}
$estadosCol = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
$titulosReporte = ['usuarios' => 'Rep. Usuarios', 'tiempo' => 'Rep. Tiempo', 'banco' => 'Rep. Banco'];
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <div class="reportes-header">
                        <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($titulosReporte[$submenu]); ?></h2>
                        <p class="mb-0 opacity-90"><?php
                            if ($submenu === 'usuarios') echo 'Total de solicitudes por usuario y estado';
                            elseif ($submenu === 'tiempo') echo 'Tiempo entre cambios de estado por solicitud';
                            else echo 'Tiempo que tardan los bancos en dar respuesta a las solicitudes asignadas';
                        ?></p>
                    </div>

                    <!-- Rep. Usuarios -->
                    <div id="panel-usuarios" class="report-panel" style="display: <?php echo $submenu === 'usuarios' ? 'block' : 'none'; ?>">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Total de solicitudes por usuario y estado</h5>
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
})();
    </script>
</body>
</html>
