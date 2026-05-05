<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

$finCamposAdminMeta = require __DIR__ . '/includes/financiamiento_registro_admin_campos.php';

$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles);
$isGestor = in_array('ROLE_GESTOR', $userRoles);
$puedeRefirma = $isAdmin || $isGestor;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sol Financiamiento - Motus</title>
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
        .detalle-seccion { margin-bottom: 1rem; }
        .detalle-seccion h6 { color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 0.25rem; }
        .firma-wrap { margin-top: 0.5rem; padding: 10px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; display: inline-block; }
        .img-firma {
            max-width: 320px;
            max-height: 140px;
            display: block;
            background: #fff;
            border: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Sol Financiamiento</h2>
                            <p class="text-muted mb-0">Registros del formulario público de financiamiento</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="tablaSolFinanciamiento" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Cédula</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Vendedor</th>
                                        <th>Vehículo</th>
                                        <th></th>
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

    <div class="modal fade" id="detalleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="modal-title text-white"><i class="fas fa-file-invoice-dollar me-2"></i>Detalle del registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleContent">
                    <p class="text-muted">Cargando...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adjuntosModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);">
                    <h5 class="modal-title text-white"><i class="fas fa-paperclip me-2"></i>Adjuntos del registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="adjuntosContent">
                    <p class="text-muted">Cargando...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarRegistroModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                    <h5 class="modal-title text-white"><i class="fas fa-pen me-2"></i>Editar datos del registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Solo datos del formulario. La firma y firmantes adicionales no se pueden editar aquí. Cada guardado queda registrado en auditoría.</p>
                    <div id="editarRegistroAlert" class="alert d-none" role="alert"></div>
                    <div id="editarRegistroFormWrap"><p class="text-muted">Cargando...</p></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnGuardarEdicionRegistro"><i class="fas fa-save me-1"></i>Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="refirmaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b45309 0%, #c2410c 100%);">
                    <h5 class="modal-title text-white"><i class="fas fa-signature me-2"></i>Refirma</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Se enviará al <strong>cliente</strong> (correo del formulario) y al <strong>vendedor asociado</strong> (si hay correo distinto) un enlace para que el cliente <strong>vuelva a firmar</strong> únicamente.</p>
                    <p class="mb-0">El enlace <strong>caduca a los 30 minutos</strong> y solo puede usarse <strong>una vez</strong>. Ese tiempo suele ser suficiente para que el cliente complete la firma.</p>
                    <div id="refirmaAlert" class="alert d-none mt-3" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning text-dark" id="btnConfirmarRefirma"><i class="fas fa-paper-plane me-1"></i>Enviar enlaces</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    var FIN_CAMPOS_EDITABLES = <?php echo json_encode(array_map(function ($k) use ($finCamposAdminMeta) {
        $type = 'text';
        if (in_array($k, $finCamposAdminMeta['textarea'] ?? [], true)) {
            $type = 'textarea';
        } elseif (in_array($k, $finCamposAdminMeta['number'] ?? [], true)) {
            $type = 'number';
        } elseif (in_array($k, $finCamposAdminMeta['date'] ?? [], true)) {
            $type = 'date';
        }
        return [
            'key' => $k,
            'label' => $finCamposAdminMeta['labels'][$k] ?? $k,
            'type' => $type,
        ];
    }, $finCamposAdminMeta['columnas_editables']), JSON_UNESCAPED_UNICODE); ?>;

    $(function() {
        var esAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        var puedeRefirma = <?php echo $puedeRefirma ? 'true' : 'false'; ?>;
        var editarRegistroId = null;
        var refirmaRegistroId = null;

        function enhanceSignatureForView(imgEl, base64) {
            try {
                if (!imgEl || !base64 || String(base64).length < 50) return;
                var img = new Image();
                img.onload = function() {
                    var canvas = document.createElement('canvas');
                    canvas.width = img.width || 500;
                    canvas.height = img.height || 180;
                    var ctx = canvas.getContext('2d');
                    // Importante: no pre-rellenar blanco para conservar alpha original.
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    var id = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    var d = id.data;
                    for (var i = 0; i < d.length; i += 4) {
                        var a = d[i + 3];
                        // Fondo transparente -> blanco sólido.
                        if (a === 0) {
                            d[i] = 255;
                            d[i + 1] = 255;
                            d[i + 2] = 255;
                            d[i + 3] = 255;
                            continue;
                        }
                        var lum = (0.299 * d[i]) + (0.587 * d[i + 1]) + (0.114 * d[i + 2]);
                        // Cualquier trazo visible se oscurece para mayor legibilidad.
                        var v = Math.max(12, Math.min(255, lum * 0.35));
                        d[i] = v;
                        d[i + 1] = v;
                        d[i + 2] = v;
                        d[i + 3] = 255;
                    }
                    ctx.putImageData(id, 0, 0);
                    imgEl.src = canvas.toDataURL('image/png');
                };
                img.src = 'data:image/png;base64,' + base64;
            } catch (e) {}
        }

        var table = $('#tablaSolFinanciamiento').DataTable({
            ajax: { url: 'api/sol_financiamiento.php', dataSrc: 'data' },
            columns: [
                { data: 'id' },
                { data: 'fecha_creacion', render: function(d) { return d ? d.replace(' ', '<br>') : '—'; } },
                { data: 'cliente_nombre', defaultContent: '—' },
                { data: 'cliente_id', defaultContent: '—' },
                { data: 'cliente_correo', defaultContent: '—' },
                { data: 'celular_cliente', defaultContent: '—' },
                { data: 'email_vendedor', defaultContent: '—' },
                { data: null, orderable: false, render: function(row) {
                    var v = [];
                    if (row.marca_auto) v.push(row.marca_auto);
                    if (row.modelo_auto) v.push(row.modelo_auto);
                    if (row.anio_auto) v.push(row.anio_auto);
                    return v.length ? v.join(' ') : '—';
                }},
                { data: null, orderable: false, render: function(row) {
                    var html = '<a href="api/sol_financiamiento_pdf.php?id=' + row.id + '" class="btn btn-sm btn-success me-1" target="_blank" title="Descargar PDF"><i class="fas fa-file-pdf"></i> PDF</a>' +
                               '<button type="button" class="btn btn-sm btn-info btn-ver-detalle me-1" data-id="' + row.id + '"><i class="fas fa-eye"></i> Ver detalle</button>' +
                               '<button type="button" class="btn btn-sm btn-primary btn-ver-adjuntos me-1" data-id="' + row.id + '"><i class="fas fa-paperclip"></i> Adjuntos</button>';
                    if (esAdmin) {
                        html += '<button type="button" class="btn btn-sm btn-secondary btn-editar-registro me-1" data-id="' + row.id + '"><i class="fas fa-pen"></i> Editar</button>';
                    }
                    if (puedeRefirma) {
                        html += '<button type="button" class="btn btn-sm btn-warning text-dark btn-refirma-registro me-1" data-id="' + row.id + '"><i class="fas fa-signature"></i> Refirma</button>';
                    }
                    if (esAdmin) {
                        html += '<button type="button" class="btn btn-sm btn-danger btn-borrar-registro" data-id="' + row.id + '"><i class="fas fa-trash"></i> Borrar</button>';
                    }
                    return html;
                }}
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json', emptyTable: 'No hay registros.' }
        });

        $(document).on('click', '.btn-ver-detalle', function() {
            var id = $(this).data('id');
            var $content = $('#detalleContent');
            $content.html('<p class="text-muted">Cargando...</p>');
            $('#detalleModal').modal('show');
            $.get('api/sol_financiamiento.php?id=' + id)
                .done(function(res) {
                    if (!res.success || !res.data) {
                        $content.html('<p class="text-danger">No se pudo cargar el registro.</p>');
                        return;
                    }
                    var d = res.data;
                    var sections = {
                        'Cliente': ['cliente_nombre','cliente_estado_civil','cliente_sexo','cliente_id','cliente_nacimiento','cliente_edad','cliente_nacionalidad','cliente_dependientes','cliente_correo','cliente_peso','cliente_estatura'],
                        'Dirección': ['vivienda','vivienda_monto','prov_dist_corr','tel_residencia','barriada_calle_casa','celular_cliente','edificio_apto','correo_residencial'],
                        'Laboral': ['empresa_nombre','empresa_ocupacion','empresa_anios','empresa_telefono','empresa_salario','empresa_direccion','otros_ingresos','ocupacion_otros','trabajo_anterior'],
                        'Cónyuge': ['tiene_conyuge','con_nombre','con_id','con_empresa','con_ocupacion','con_salario','con_correo'],
                        'Referencias': ['refp1_nombre','refp1_cel','refp2_nombre','refp2_cel','reff1_nombre','reff1_cel','reff2_nombre','reff2_cel'],
                        'Vehículo': ['marca_auto','modelo_auto','anio_auto','kms_cod_auto','precio_venta','abono'],
                        'Otros': ['sucursal','nombre_gestor','comentarios_gestor','fecha_creacion','ip']
                    };
                    // Excluir firma de "Otros" (se muestra como imagen abajo)
                    var html = '';
                    var labels = { cliente_nombre:'Nombre', cliente_estado_civil:'Estado civil', cliente_sexo:'Sexo', cliente_id:'Cédula', cliente_nacimiento:'Nacimiento', cliente_edad:'Edad', cliente_nacionalidad:'Nacionalidad', cliente_dependientes:'Dependientes', cliente_correo:'Correo', cliente_peso:'Peso', cliente_estatura:'Estatura', vivienda:'Vivienda', vivienda_monto:'Monto', prov_dist_corr:'Prov/Dist/Corr', tel_residencia:'Tel residencia', barriada_calle_casa:'Dirección', celular_cliente:'Celular', edificio_apto:'Edificio/Apto', correo_residencial:'Correo residencial', empresa_nombre:'Empresa', empresa_ocupacion:'Ocupación', empresa_anios:'Años', empresa_telefono:'Tel', empresa_salario:'Salario', empresa_direccion:'Dirección', otros_ingresos:'Otros ingresos', ocupacion_otros:'Ocupación otros', trabajo_anterior:'Trabajo anterior', tiene_conyuge:'Tiene cónyuge', con_nombre:'Nombre', con_id:'Cédula', con_empresa:'Empresa', con_ocupacion:'Ocupación', con_salario:'Salario', con_correo:'Correo', refp1_nombre:'Ref personal 1', refp1_cel:'Cel', refp2_nombre:'Ref personal 2', refp2_cel:'Cel', reff1_nombre:'Ref familiar 1', reff1_cel:'Cel', reff2_nombre:'Ref familiar 2', reff2_cel:'Cel', marca_auto:'Marca', modelo_auto:'Modelo', anio_auto:'Año', kms_cod_auto:'Km', precio_venta:'Precio', abono:'Abono', sucursal:'Sucursal', nombre_gestor:'Gestor', comentarios_gestor:'Comentarios', fecha_creacion:'Fecha registro', ip:'IP' };
                    for (var sec in sections) {
                        var filas = [];
                        for (var i = 0; i < sections[sec].length; i++) {
                            var key = sections[sec][i];
                            var val = d[key];
                            if (val === null || val === undefined || val === '') continue;
                            if (key === 'tiene_conyuge') val = val == 1 ? 'Sí' : 'No';
                            filas.push('<tr><td class="text-muted">' + (labels[key] || key) + '</td><td>' + $('<div>').text(String(val)).html() + '</td></tr>');
                        }
                        if (filas.length) {
                            html += '<div class="detalle-seccion"><h6>' + sec + '</h6><table class="table table-sm table-borderless"><tbody>' + filas.join('') + '</tbody></table></div>';
                        }
                    }
                    // Sección Firma(s): imagen principal + firmantes adicionales
                    if (d.firma && String(d.firma).length > 50) {
                        html += '<div class="detalle-seccion"><h6>Firma del solicitante</h6><div class="firma-wrap"><img class="img-firma js-firma-enhance" data-firma-b64="' + d.firma + '" src="data:image/png;base64,' + d.firma + '" alt="Firma" /></div></div>';
                    }
                    var fa = null;
                    try { fa = d.firmantes_adicionales ? JSON.parse(d.firmantes_adicionales) : null; } catch (e) {}
                    if (fa && Array.isArray(fa) && fa.length) {
                        fa.forEach(function(item, idx) {
                            if (item.firma && String(item.firma).length > 50) {
                                html += '<div class="detalle-seccion"><h6>Firma adicional' + (fa.length > 1 ? ' ' + (idx + 1) : '') + (item.nombre ? ': ' + $('<div>').text(item.nombre).html() : '') + '</h6><div class="firma-wrap"><img class="img-firma js-firma-enhance" data-firma-b64="' + item.firma + '" src="data:image/png;base64,' + item.firma + '" alt="Firma" /></div></div>';
                            }
                        });
                    }
                    $content.html(html || '<p class="text-muted">Sin datos.</p>');
                    $content.find('.js-firma-enhance').each(function() {
                        enhanceSignatureForView(this, this.getAttribute('data-firma-b64'));
                    });
                })
                .fail(function() {
                    $content.html('<p class="text-danger">Error al cargar.</p>');
                });
        });

        $(document).on('click', '.btn-borrar-registro', function() {
            if (!esAdmin) {
                return;
            }
            var id = $(this).data('id');
            if (!id) {
                return;
            }
            if (!confirm('¿Seguro que deseas borrar el registro #' + id + '? Esta acción no se puede deshacer.')) {
                return;
            }
            $.post('api/sol_financiamiento.php', { action: 'delete', id: id })
                .done(function(res) {
                    if (res && res.success) {
                        alert(res.message || 'Registro eliminado.');
                        table.ajax.reload(null, false);
                    } else {
                        alert((res && res.message) ? res.message : 'No se pudo borrar el registro.');
                    }
                })
                .fail(function(xhr) {
                    var msg = 'Error al borrar el registro.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                });
        });

        $(document).on('click', '.btn-ver-adjuntos', function() {
            var id = $(this).data('id');
            var $content = $('#adjuntosContent');
            $content.html('<p class="text-muted">Cargando adjuntos...</p>');
            $('#adjuntosModal').modal('show');

            $.get('api/sol_financiamiento.php?adjuntos_id=' + id)
                .done(function(res) {
                    if (!res || !res.success) {
                        $content.html('<p class="text-danger">No se pudieron consultar los adjuntos.</p>');
                        return;
                    }
                    var rows = Array.isArray(res.data) ? res.data : [];
                    if (!rows.length) {
                        $content.html('<p class="text-muted mb-0">Este registro no tiene adjuntos vinculados.</p>');
                        return;
                    }
                    var html = '<div class="list-group">';
                    rows.forEach(function(a) {
                        var nombre = a.nombre_original || a.ruta_archivo || ('Adjunto #' + a.id);
                        var ruta = String(a.ruta_archivo || '');
                        var href = ruta ? ruta.replace(/^\/+/, '') : '#';
                        var tipo = a.tipo_archivo || '—';
                        var fecha = a.fecha_subida || '—';
                        var solicitud = a.solicitud_id || '—';

                        html += '<a href="' + href + '" target="_blank" rel="noopener" class="list-group-item list-group-item-action">';
                        html +=   '<div class="d-flex w-100 justify-content-between">';
                        html +=     '<h6 class="mb-1"><i class="fas fa-file me-2 text-primary"></i>' + $('<div>').text(nombre).html() + '</h6>';
                        html +=     '<small class="text-muted">Solicitud #' + $('<div>').text(String(solicitud)).html() + '</small>';
                        html +=   '</div>';
                        html +=   '<p class="mb-1"><code>' + $('<div>').text(ruta || '—').html() + '</code></p>';
                        html +=   '<small class="text-muted">Tipo: ' + $('<div>').text(tipo).html() + ' · Fecha: ' + $('<div>').text(String(fecha)).html() + '</small>';
                        html += '</a>';
                    });
                    html += '</div>';
                    $content.html(html);
                })
                .fail(function() {
                    $content.html('<p class="text-danger">Error al cargar adjuntos.</p>');
                });
        });

        function finEscAttr(s) {
            return String(s).replace(/"/g, '&quot;');
        }

        function buildEditarRegistroForm(d) {
            var html = '<div class="row g-2">';
            FIN_CAMPOS_EDITABLES.forEach(function(f) {
                var val = d[f.key];
                if (val === null || val === undefined) val = '';
                var safeVal = $('<div>').text(String(val)).html();
                var id = 'ef_' + f.key;
                var lab = $('<div>').text(f.label).html();
                html += '<div class="col-md-6"><label class="form-label small mb-0" for="' + id + '">' + lab + '</label>';
                if (f.key === 'tiene_conyuge') {
                    var sel0 = String(val) === '0' || val === 0 || val === false ? ' selected' : '';
                    var sel1 = String(val) === '1' || val === 1 || val === true ? ' selected' : '';
                    html += '<select class="form-select form-select-sm" id="' + id + '" name="' + finEscAttr(f.key) + '">' +
                        '<option value="0"' + sel0 + '>No</option><option value="1"' + sel1 + '>Sí</option></select>';
                } else if (f.type === 'textarea') {
                    html += '<textarea class="form-control form-control-sm" id="' + id + '" name="' + finEscAttr(f.key) + '" rows="2">' + safeVal + '</textarea>';
                } else {
                    var step = f.type === 'number' ? ' step="any"' : '';
                    html += '<input type="' + f.type + '" class="form-control form-control-sm" id="' + id + '" name="' + finEscAttr(f.key) + '" value="' + safeVal + '"' + step + '>';
                }
                html += '</div>';
            });
            html += '</div>';
            return html;
        }

        $(document).on('click', '.btn-editar-registro', function() {
            if (!esAdmin) return;
            var id = $(this).data('id');
            editarRegistroId = id;
            var $wrap = $('#editarRegistroFormWrap');
            var $al = $('#editarRegistroAlert');
            $al.addClass('d-none').removeClass('alert-danger alert-success').text('');
            $wrap.html('<p class="text-muted">Cargando...</p>');
            $('#editarRegistroModal').modal('show');
            $.get('api/sol_financiamiento.php?id=' + id)
                .done(function(res) {
                    if (!res.success || !res.data) {
                        $wrap.html('<p class="text-danger">No se pudo cargar el registro.</p>');
                        return;
                    }
                    $wrap.html(buildEditarRegistroForm(res.data));
                })
                .fail(function() {
                    $wrap.html('<p class="text-danger">Error al cargar.</p>');
                });
        });

        $('#btnGuardarEdicionRegistro').on('click', function() {
            if (!esAdmin || !editarRegistroId) return;
            var datos = {};
            FIN_CAMPOS_EDITABLES.forEach(function(f) {
                var $el = $('#ef_' + f.key);
                if (!$el.length) return;
                datos[f.key] = $el.val();
            });
            var $al = $('#editarRegistroAlert');
            $al.addClass('d-none');
            $.ajax({
                url: 'api/sol_financiamiento.php',
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify({ action: 'update_registro', id: String(editarRegistroId), datos: datos })
            }).done(function(res) {
                if (res && res.success) {
                    $al.removeClass('d-none alert-danger').addClass('alert-success').text(res.message || 'Guardado.');
                    table.ajax.reload(null, false);
                } else {
                    $al.removeClass('d-none alert-success').addClass('alert-danger').text((res && res.message) ? res.message : 'No se pudo guardar.');
                }
            }).fail(function(xhr) {
                var msg = 'Error al guardar.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $al.removeClass('d-none alert-success').addClass('alert-danger').text(msg);
            });
        });

        $(document).on('click', '.btn-refirma-registro', function() {
            if (!puedeRefirma) return;
            refirmaRegistroId = $(this).data('id');
            $('#refirmaAlert').addClass('d-none').removeClass('alert-danger alert-success').text('');
            $('#refirmaModal').modal('show');
        });

        $('#btnConfirmarRefirma').on('click', function() {
            if (!puedeRefirma || !refirmaRegistroId) return;
            var $al = $('#refirmaAlert');
            $al.addClass('d-none');
            $.ajax({
                url: 'api/sol_financiamiento.php',
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify({ action: 'generar_refirma', id: String(refirmaRegistroId) })
            }).done(function(res) {
                if (res && res.success) {
                    $al.removeClass('d-none alert-danger').addClass('alert-success').text(res.message || 'Listo.');
                } else {
                    $al.removeClass('d-none alert-success').addClass('alert-danger').text((res && res.message) ? res.message : 'No se pudo generar el enlace.');
                }
            }).fail(function(xhr) {
                var msg = 'Error en la solicitud.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $al.removeClass('d-none alert-success').addClass('alert-danger').text(msg);
            });
        });
    });
    </script>
    <?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
