function urlReporteReservasApi() {
    return window.MOTUS_REPORTE_RESERVAS_API || 'reporte_reservas_servicio.php';
}

function ajaxReporteReservas(options) {
    var opts = $.extend({}, options);
    opts.url = opts.url || urlReporteReservasApi();
    opts.headers = $.extend({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
    return $.ajax(opts);
}

$(document).ready(function () {
    var $input = $('#archivo_reporte');
    var $zone = $('#uploadZone');
    var $nombre = $('#nombreArchivoElegido');
    var $btnSubir = $('#btnSubirReporte');
    var dtLineas = null;

    function abrirSelectorArchivo() {
        var el = $input[0];
        if (el) {
            el.click();
        }
    }

    $('#btnElegirArchivo').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirSelectorArchivo();
    });

    $input.on('click', function (e) {
        e.stopPropagation();
    });

    $zone.on('click', function (e) {
        if ($(e.target).closest('#btnElegirArchivo, #archivo_reporte').length) {
            return;
        }
        abrirSelectorArchivo();
    });

    $input.on('change', function () {
        if (this.files && this.files[0]) {
            $nombre.text(this.files[0].name);
            $btnSubir.prop('disabled', false);
        } else {
            $nombre.text('');
            $btnSubir.prop('disabled', true);
        }
    });

    $zone.on('dragover', function (e) {
        e.preventDefault();
        $zone.addClass('dragover');
    }).on('dragleave drop', function (e) {
        e.preventDefault();
        $zone.removeClass('dragover');
        if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length) {
            $input[0].files = e.originalEvent.dataTransfer.files;
            $input.trigger('change');
        }
    });

    $('#formReporteReservas').on('submit', function (e) {
        e.preventDefault();
        if (!$input[0].files || !$input[0].files[0]) {
            mostrarAlerta('Seleccione un archivo', 'warning');
            return;
        }
        var fd = new FormData();
        fd.append('archivo', $input[0].files[0]);
        var original = $btnSubir.html();
        $btnSubir.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Subiendo...');
        ajaxReporteReservas({
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 120000
        }).done(function (r) {
            if (r.success) {
                var reporteId = r.data && r.data.id;
                if (reporteId && r.data.needs_import) {
                    $btnSubir.html('<i class="fas fa-spinner fa-spin me-2"></i>Importando filas...');
                    importarReporteId(reporteId, function (imp) {
                        $btnSubir.prop('disabled', false).html(original);
                        if (imp.success) {
                            mostrarAlerta((imp.message || 'Importación lista') + ' Pulse Procesar para aplicar.', 'success');
                        } else {
                            mostrarAlerta('Archivo guardado, pero: ' + (imp.message || 'falló la importación'), 'warning');
                        }
                        $input.val('');
                        $nombre.text('');
                        $btnSubir.prop('disabled', true);
                        cargarReportes();
                    }, function (xhr) {
                        $btnSubir.prop('disabled', false).html(original);
                        mostrarAlertaAjax(xhr, 'Archivo guardado, pero falló importar las filas del Excel');
                        cargarReportes();
                    });
                } else {
                    $btnSubir.prop('disabled', false).html(original);
                    mostrarAlerta(r.message || 'Reporte subido', 'success');
                    $input.val('');
                    $nombre.text('');
                    $btnSubir.prop('disabled', true);
                    cargarReportes();
                }
            } else {
                $btnSubir.prop('disabled', false).html(original);
                mostrarAlerta(r.message || 'Error al subir', 'danger');
            }
        }).fail(function (xhr) {
            $btnSubir.prop('disabled', false).html(original);
            mostrarAlertaAjax(xhr, 'Error al subir el reporte');
        });
    });

    $('#btnCerrarDetalle').on('click', function () {
        $('#cardDetalleLineas').addClass('d-none');
    });

    cargarReportes();
});

function mostrarAlertaAjax(xhr, titulo) {
    var msg = titulo || 'Error de conexión';
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
    } else if (xhr && xhr.responseText) {
        var t = String(xhr.responseText).trim();
        if (t.indexOf('{') === 0) {
            try {
                var j = JSON.parse(t);
                if (j.message) msg = j.message;
            } catch (e) { /* ignore */ }
        } else if (xhr.status === 413) {
            msg = 'El archivo es demasiado grande para el servidor (413)';
        } else if (/just a moment|cloudflare|cf-browser-verification|enable javascript and cookies/i.test(t)) {
            msg = 'Cloudflare bloqueó la petición. Suba de nuevo tras desplegar reporte_reservas_servicio.php o pida una regla WAF que permita /api/ en motus.automarket.com.pa';
        } else if (xhr.status === 404) {
            msg = 'No se encontró el servicio de reportes en el servidor';
        } else if (xhr.status === 403) {
            msg = 'Acceso denegado (403). Si ve "Just a moment", es Cloudflare: configure el firewall o use reporte_reservas_servicio.php';
        } else if (xhr.status) {
            msg = 'Error HTTP ' + xhr.status + (t ? ': ' + t.substring(0, 120) : '');
        }
    }
    mostrarAlerta(msg, 'danger');
}

function importarReporteId(reporteId, onOk, onFail) {
    ajaxReporteReservas({
        type: 'POST',
        data: { action: 'importar', reporte_id: reporteId },
        dataType: 'json',
        timeout: 300000
    }).done(function (r) {
        if (typeof onOk === 'function') onOk(r);
    }).fail(function (xhr) {
        if (typeof onFail === 'function') onFail(xhr);
    });
}

function mostrarAlerta(mensaje, tipo) {
    var cls = 'alert-' + (tipo || 'info');
    var $a = $('<div class="alert ' + cls + ' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">' +
        $('<div>').text(mensaje).html() +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append($a);
    setTimeout(function () { $a.alert('close'); }, 6000);
}

function formatearTamano(bytes) {
    var n = parseInt(bytes, 10) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
}

function formatearFecha(f) {
    if (!f) return '-';
    try {
        return new Date(String(f).replace(' ', 'T')).toLocaleString('es-PA');
    } catch (e) {
        return f;
    }
}

function badgeEstadoReporte(estado) {
    var map = {
        pendiente: 'secondary',
        procesando: 'warning',
        completado: 'success',
        error: 'danger'
    };
    var e = (estado || 'pendiente').toLowerCase();
    return '<span class="badge bg-' + (map[e] || 'secondary') + '">' + e + '</span>';
}

function badgeEstadoLinea(estado) {
    var map = {
        pendiente: 'secondary',
        aplicado: 'success',
        sin_coincidencia: 'warning',
        error: 'danger'
    };
    var e = (estado || 'pendiente').toLowerCase();
    return '<span class="badge bg-' + (map[e] || 'secondary') + '">' + e.replace(/_/g, ' ') + '</span>';
}

function cargarReportes() {
    ajaxReporteReservas({
        type: 'GET',
        dataType: 'json'
    }).done(function (r) {
        if (r.warning) {
            mostrarAlerta(r.warning, 'warning');
        }
        if (!r.success) {
            mostrarAlerta(r.message || 'Error al cargar', 'danger');
            return;
        }
        var tbody = $('#tablaReportesReservas tbody');
        tbody.empty();
        (r.data || []).forEach(function (row) {
            var acciones = '<a class="btn btn-sm btn-outline-primary me-1" href="' + urlReporteReservasApi() + '?download=' + row.id + '" title="Descargar"><i class="fas fa-download"></i></a>';
            acciones += '<button type="button" class="btn btn-sm btn-outline-info me-1 btn-ver-lineas" data-id="' + row.id + '" title="Ver detalle"><i class="fas fa-list"></i></button>';
            acciones += '<button type="button" class="btn btn-sm btn-success me-1 btn-procesar-reporte" data-id="' + row.id + '" title="Procesar coincidencias"><i class="fas fa-cogs"></i></button>';
            if (window.MOTUS_REPORTE_RESERVAS_ADMIN) {
                acciones += '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-reporte" data-id="' + row.id + '" title="Eliminar"><i class="fas fa-trash"></i></button>';
            }
            var subidoPor = ((row.usuario_nombre || '') + ' ' + (row.usuario_apellido || '')).trim() || '-';
            var filasInfo = '-';
            if (row.filas_total != null && row.filas_total !== '') {
                filasInfo = row.filas_total;
                if (row.filas_aplicadas != null) {
                    filasInfo += ' <small class="text-muted">(' + row.filas_aplicadas + ' ok)</small>';
                }
            }
            tbody.append(
                '<tr>' +
                '<td data-order="' + row.id + '">' + row.id + '</td>' +
                '<td>' + $('<div>').text(row.nombre_original || '').html() + '</td>' +
                '<td>' + formatearTamano(row.tamano_bytes) + '</td>' +
                '<td>' + $('<div>').text(subidoPor).html() + '</td>' +
                '<td>' + (row.estado ? badgeEstadoReporte(row.estado) : '<span class="text-muted">—</span>') + '</td>' +
                '<td>' + filasInfo + '</td>' +
                '<td>' + formatearFecha(row.fecha_subida) + '</td>' +
                '<td>' + acciones + '</td>' +
                '</tr>'
            );
        });
        if ($.fn.DataTable.isDataTable('#tablaReportesReservas')) {
            $('#tablaReportesReservas').DataTable().destroy();
        }
        $('#tablaReportesReservas').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 10,
            order: [[0, 'desc']],
            columnDefs: [
                { type: 'num', targets: 0 },
                { orderable: false, targets: 7 }
            ]
        });
    }).fail(function (xhr) {
        mostrarAlertaAjax(xhr, 'Error al cargar reportes');
    });
}

function cargarLineas(reporteId) {
    $('#detalleReporteId').text(reporteId);
    $('#cardDetalleLineas').removeClass('d-none');
    $('html, body').animate({ scrollTop: $('#cardDetalleLineas').offset().top - 80 }, 300);

    ajaxReporteReservas({
        type: 'GET',
        data: { action: 'lineas', reporte_id: reporteId },
        dataType: 'json'
    }).done(function (r) {
        if (!r.success) {
            mostrarAlerta(r.message || 'Error al cargar líneas', 'danger');
            return;
        }
        var tbody = $('#tablaLineasReporte tbody');
        tbody.empty();
        (r.data || []).forEach(function (ln) {
            var veh = [ln.marca, ln.modelo, ln.anio].filter(Boolean).join(' ');
            var sol = ln.solicitud_id ? ('#' + ln.solicitud_id) : '—';
            tbody.append(
                '<tr>' +
                '<td>' + (ln.fila_excel || '') + '</td>' +
                '<td>' + $('<div>').text(ln.nombre_cliente || '').html() + '</td>' +
                '<td>' + $('<div>').text(ln.cedula || '').html() + '</td>' +
                '<td>' + $('<div>').text(veh).html() + '</td>' +
                '<td>' + sol + '</td>' +
                '<td>' + (ln.match_por && ln.match_por !== 'ninguno' ? ln.match_por : '—') + '</td>' +
                '<td>' + badgeEstadoLinea(ln.estado) + '</td>' +
                '<td class="small">' + $('<div>').text(ln.mensaje || '').html() + '</td>' +
                '</tr>'
            );
        });
        if ($.fn.DataTable.isDataTable('#tablaLineasReporte')) {
            $('#tablaLineasReporte').DataTable().destroy();
        }
        $('#tablaLineasReporte').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'asc']]
        });
    }).fail(function (xhr) {
        mostrarAlertaAjax(xhr, 'Error al cargar detalle');
    });
}

$(document).on('click', '.btn-ver-lineas', function () {
    cargarLineas($(this).data('id'));
});

$(document).on('click', '.btn-procesar-reporte', function () {
    var id = $(this).data('id');
    if (!confirm('¿Procesar este reporte? Se buscarán solicitudes por cédula, correo o nombre y se apartarán los vehículos.')) {
        return;
    }
    var $btn = $(this);
    var original = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    ajaxReporteReservas({
        type: 'POST',
        data: { action: 'procesar', reporte_id: id },
        dataType: 'json',
        timeout: 300000
    }).done(function (r) {
        $btn.prop('disabled', false).html(original);
        if (r.success) {
            var msg = r.message || 'Procesado';
            if (r.stats) {
                msg += ' — Aplicadas: ' + (r.stats.filas_aplicadas || 0) +
                    ', Sin coincidencia: ' + (r.stats.filas_sin_coincidencia || 0) +
                    ', Errores: ' + (r.stats.filas_error || 0);
            }
            mostrarAlerta(msg, 'success');
            cargarReportes();
            cargarLineas(id);
        } else {
            mostrarAlerta(r.message || 'Error al procesar', 'danger');
        }
    }).fail(function (xhr) {
        $btn.prop('disabled', false).html(original);
        mostrarAlertaAjax(xhr, 'Error al procesar');
    });
});

$(document).on('click', '.btn-eliminar-reporte', function () {
    var id = $(this).data('id');
    if (!confirm('¿Eliminar este reporte? Esta acción no se puede deshacer.')) {
        return;
    }
    ajaxReporteReservas({
        type: 'DELETE',
        data: { id: id },
        dataType: 'json'
    }).done(function (r) {
        if (r.success) {
            mostrarAlerta(r.message || 'Eliminado', 'success');
            $('#cardDetalleLineas').addClass('d-none');
            cargarReportes();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function (xhr) {
        mostrarAlertaAjax(xhr, 'Error al eliminar');
    });
});
