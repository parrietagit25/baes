<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/banco_scope_helper.php';
require_once __DIR__ . '/includes/reportes_banco_usuarios_data.php';

$userRoles = $_SESSION['user_roles'] ?? [];
if (!motus_es_admin_banco($userRoles)) {
    header('Location: dashboard.php');
    exit();
}

$estadosCol = REPORTES_BANCO_USUARIOS_COLUMNAS;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rep. Usuarios Banco - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .reportes-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 24px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .total-click { cursor: pointer; font-weight: 600; }
        .total-click:hover { text-decoration: underline; }
        .modal-header.reportes { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0; }
        .table-reportes { font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="reportes-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <h2 class="mb-1"><i class="fas fa-users me-2"></i>Rep. Usuarios Banco</h2>
                            <p class="mb-0 opacity-90">Total de solicitudes asignadas por usuario de su entidad y estado</p>
                        </div>
                        <a href="api/reportes_banco.php?action=exportar_excel_usuarios" class="btn btn-light btn-sm">
                            <i class="fas fa-file-excel me-1"></i>Descargar Excel
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Solicitudes por usuario y estado</h5>
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <input type="text" id="filtroUsuariosTexto" class="form-control form-control-sm" placeholder="Filtrar por nombre o email...">
                            </div>
                            <div class="col-md-3">
                                <select id="filtroUsuariosEstado" class="form-select form-select-sm">
                                    <option value="">Todas las columnas</option>
                                    <?php foreach ($estadosCol as $e): ?>
                                    <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" min="0" id="filtroUsuariosMinTotal" class="form-control form-control-sm" placeholder="Total mín.">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btnFiltrarUsuarios">
                                    <i class="fas fa-filter me-1"></i>Filtrar
                                </button>
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
        </div>
    </div>
</div>

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
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Estado</th>
                                <th>Fecha creación</th>
                                <th>Última actualización</th>
                            </tr>
                        </thead>
                        <tbody id="modalSolicitudesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const estados = <?php echo json_encode($estadosCol, JSON_UNESCAPED_UNICODE); ?>;
    const colspan = estados.length + 2;

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function loadReporteUsuarios() {
        fetch('api/reportes_banco.php?action=reporte_usuarios')
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#tabla-usuarios tbody');
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted">Sin datos</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(row => {
                    html += '<tr><td>' + escapeHtml(row.nombre) + '<br><small class="text-muted">' + escapeHtml(row.email) + '</small></td>';
                    estados.forEach(est => {
                        const n = row[est] != null ? row[est] : 0;
                        html += '<td class="text-center"><span class="total-click" data-usuario-id="' + row.usuario_id + '" data-columna="' + escapeHtml(est) + '" data-usuario-nombre="' + escapeHtml(row.nombre) + '">' + n + '</span></td>';
                    });
                    html += '<td class="text-center fw-bold">' + (row.total || 0) + '</td></tr>';
                });
                if (!html) {
                    html = '<tr><td colspan="' + colspan + '" class="text-center text-muted">Sin datos</td></tr>';
                }
                tbody.innerHTML = html;
                document.querySelectorAll('.total-click').forEach(el => {
                    el.addEventListener('click', function() {
                        abrirModalSolicitudes(
                            this.getAttribute('data-usuario-id'),
                            this.getAttribute('data-columna'),
                            this.getAttribute('data-usuario-nombre')
                        );
                    });
                });
            })
            .catch(() => {
                document.querySelector('#tabla-usuarios tbody').innerHTML =
                    '<tr><td colspan="' + colspan + '" class="text-center text-danger">Error al cargar</td></tr>';
            });
    }

    function abrirModalSolicitudes(usuarioId, columna, usuarioNombre) {
        document.getElementById('modalSolicitudesTitulo').textContent =
            'Usuario: ' + usuarioNombre + ' — ' + columna;
        document.getElementById('modalSolicitudesBody').innerHTML =
            '<tr><td colspan="6" class="text-center">Cargando…</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('modalSolicitudesUsuario'));
        modal.show();

        fetch('api/reportes_banco.php?action=solicitudes_usuario_columna&usuario_id=' +
            encodeURIComponent(usuarioId) + '&columna=' + encodeURIComponent(columna))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) {
                    document.getElementById('modalSolicitudesBody').innerHTML =
                        '<tr><td colspan="6" class="text-center text-muted">Sin solicitudes</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(s => {
                    html += '<tr>' +
                        '<td>' + escapeHtml(s.id) + '</td>' +
                        '<td>' + escapeHtml(s.nombre_cliente) + '</td>' +
                        '<td>' + escapeHtml(s.cedula) + '</td>' +
                        '<td>' + escapeHtml(s.estado) + '</td>' +
                        '<td>' + escapeHtml(s.fecha_creacion) + '</td>' +
                        '<td>' + escapeHtml(s.fecha_actualizacion) + '</td>' +
                        '</tr>';
                });
                document.getElementById('modalSolicitudesBody').innerHTML = html;
            });
    }

    function aplicarFiltroUsuarios() {
        const txt = ((document.getElementById('filtroUsuariosTexto') || {}).value || '').toLowerCase().trim();
        const columna = ((document.getElementById('filtroUsuariosEstado') || {}).value || '').trim();
        const minRaw = ((document.getElementById('filtroUsuariosMinTotal') || {}).value || '').trim();
        const minTotal = minRaw === '' ? null : parseInt(minRaw, 10);
        const mapEstado = {};
        estados.forEach((e, i) => { mapEstado[e] = i + 1; });

        document.querySelectorAll('#tabla-usuarios tbody tr').forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            const celdas = tr.querySelectorAll('td');
            const ref = (celdas[0]?.innerText || '').toLowerCase();
            const total = parseInt((celdas[estados.length + 1]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
            let visible = true;
            if (txt && ref.indexOf(txt) === -1) visible = false;
            if (columna && mapEstado[columna] != null) {
                const idx = mapEstado[columna];
                const valEstado = parseInt((celdas[idx]?.innerText || '0').replace(/[^\d]/g, ''), 10) || 0;
                if (valEstado <= 0) visible = false;
            }
            if (minTotal !== null && !isNaN(minTotal) && total < minTotal) visible = false;
            tr.style.display = visible ? '' : 'none';
        });
    }

    document.getElementById('btnFiltrarUsuarios').addEventListener('click', aplicarFiltroUsuarios);
    loadReporteUsuarios();
})();
</script>
</body>
</html>
