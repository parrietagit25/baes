$(document).ready(function () {
    var $input = $('#archivo_reporte');
    var $zone = $('#uploadZone');
    var $nombre = $('#nombreArchivoElegido');
    var $btnSubir = $('#btnSubirReporte');

    $('#btnElegirArchivo').on('click', function () {
        $input.trigger('click');
    });

    $zone.on('click', function (e) {
        if (e.target.id === 'btnElegirArchivo' || $(e.target).closest('#btnElegirArchivo').length) {
            return;
        }
        $input.trigger('click');
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
        $.ajax({
            url: 'api/reporte_reservas.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (r) {
            $btnSubir.prop('disabled', false).html(original);
            if (r.success) {
                mostrarAlerta(r.message || 'Reporte subido', 'success');
                $input.val('');
                $nombre.text('');
                $btnSubir.prop('disabled', true);
                cargarReportes();
            } else {
                mostrarAlerta(r.message || 'Error al subir', 'danger');
            }
        }).fail(function () {
            $btnSubir.prop('disabled', false).html(original);
            mostrarAlerta('Error de conexión', 'danger');
        });
    });

    cargarReportes();
});

function mostrarAlerta(mensaje, tipo) {
    var cls = 'alert-' + (tipo || 'info');
    var $a = $('<div class="alert ' + cls + ' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">' +
        $('<div>').text(mensaje).html() +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append($a);
    setTimeout(function () { $a.alert('close'); }, 5000);
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

function cargarReportes() {
    $.ajax({
        url: 'api/reporte_reservas.php',
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
            var acciones = '<a class="btn btn-sm btn-outline-primary me-1" href="api/reporte_reservas.php?download=' + row.id + '" title="Descargar"><i class="fas fa-download"></i></a>';
            if (window.MOTUS_REPORTE_RESERVAS_ADMIN) {
                acciones += '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-reporte" data-id="' + row.id + '" title="Eliminar"><i class="fas fa-trash"></i></button>';
            }
            var subidoPor = ((row.usuario_nombre || '') + ' ' + (row.usuario_apellido || '')).trim() || '-';
            tbody.append(
                '<tr>' +
                '<td data-order="' + row.id + '">' + row.id + '</td>' +
                '<td>' + $('<div>').text(row.nombre_original || '').html() + '</td>' +
                '<td>' + formatearTamano(row.tamano_bytes) + '</td>' +
                '<td>' + $('<div>').text(subidoPor).html() + '</td>' +
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
                { orderable: false, targets: 5 }
            ]
        });
    }).fail(function () {
        mostrarAlerta('Error de conexión al cargar reportes', 'danger');
    });
}

$(document).on('click', '.btn-eliminar-reporte', function () {
    var id = $(this).data('id');
    if (!confirm('¿Eliminar este reporte? Esta acción no se puede deshacer.')) {
        return;
    }
    $.ajax({
        url: 'api/reporte_reservas.php',
        type: 'DELETE',
        data: { id: id },
        dataType: 'json'
    }).done(function (r) {
        if (r.success) {
            mostrarAlerta(r.message || 'Eliminado', 'success');
            cargarReportes();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
});
