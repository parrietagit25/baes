<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';

$userRoles = $_SESSION['user_roles'] ?? [];
if (!in_array('ROLE_BANCO', $userRoles, true)) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis propuestas - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
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
                    <h2 class="mb-1"><i class="fas fa-file-contract me-2"></i>Mis propuestas</h2>
                    <p class="mb-0 opacity-90">Todas las evaluaciones que has registrado como usuario banco, en cualquier solicitud asignada.</p>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tablaMisPropuestas" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Solicitud</th>
                                        <th>Cliente</th>
                                        <th>Cédula</th>
                                        <th>Estado</th>
                                        <th>Vehículo</th>
                                        <th>Decisión</th>
                                        <th>Tasa %</th>
                                        <th>Valor financiar</th>
                                        <th>Abono</th>
                                        <th>Plazo</th>
                                        <th>Letra</th>
                                        <th>Promoción</th>
                                        <th>Seleccionada</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-0 mt-2">«Seleccionada» indica si el gestor/administrador eligió esa propuesta para la solicitud.</p>
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
<script>
(function () {
    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtMoney(v) {
        if (v == null || v === '') return '-';
        return '$' + parseFloat(v).toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtPct(v) {
        if (v == null || v === '') return '-';
        return parseFloat(v).toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    }
    $.ajax({
        url: 'api/evaluaciones_banco.php',
        type: 'GET',
        data: { mis_propuestas: 1 },
        dataType: 'json'
    }).done(function (res) {
        if (!res.success) {
            $('#tablaMisPropuestas tbody').html('<tr><td colspan="15" class="text-danger">' + esc(res.message || 'Error') + '</td></tr>');
            return;
        }
        const rows = res.data || [];
        $('#tablaMisPropuestas').DataTable({
            data: rows,
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            columns: [
                {
                    data: 'fecha_evaluacion',
                    render: function (d) {
                        return d ? new Date(d).toLocaleString('es-PA') : '-';
                    }
                },
                {
                    data: 'solicitud_id',
                    render: function (d) {
                        return '#' + parseInt(d, 10);
                    }
                },
                { data: 'nombre_cliente', defaultContent: '-', render: function (d) { return esc(d || '-'); } },
                { data: 'cedula', defaultContent: '-', render: function (d) { return esc(d || '-'); } },
                { data: 'solicitud_estado', defaultContent: '-', render: function (d) { return esc(d || '-'); } },
                {
                    data: null,
                    render: function (data, type, row) {
                        if (!row || !row.vehiculo_marca) return '-';
                        return esc([row.vehiculo_marca, row.vehiculo_modelo, row.vehiculo_anio].filter(Boolean).join(' '));
                    }
                },
                {
                    data: 'decision',
                    render: function (d) {
                        return esc((d || '').toUpperCase().replace(/_/g, ' '));
                    }
                },
                { data: 'tasa_bancaria', render: function (d) { return fmtPct(d); } },
                { data: 'valor_financiar', render: function (d) { return fmtMoney(d); } },
                { data: 'abono', render: function (d) { return fmtMoney(d); } },
                {
                    data: 'plazo',
                    render: function (d) {
                        return d ? (parseInt(d, 10) + ' meses') : '-';
                    }
                },
                { data: 'letra', render: function (d) { return fmtMoney(d); } },
                { data: 'promocion', defaultContent: '-', render: function (d) { return esc(d || '-'); } },
                {
                    data: 'es_propuesta_seleccionada',
                    render: function (d) {
                        return d ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>';
                    }
                },
                {
                    data: 'solicitud_id',
                    orderable: false,
                    searchable: false,
                    render: function (d) {
                        const sid = parseInt(d, 10);
                        return '<a class="btn btn-sm btn-outline-primary" href="solicitudes.php?abrir_solicitud=' + sid + '" title="Abrir solicitud"><i class="fas fa-external-link-alt"></i></a>';
                    }
                }
            ]
        });
    }).fail(function () {
        $('#tablaMisPropuestas tbody').html('<tr><td colspan="15" class="text-danger">Error de conexión</td></tr>');
    });
})();
</script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
