$(document).ready(function () {
    var $input = $('#archivo_reporte');
    var $zone = $('#uploadZone');
    var $nombre = $('#nombreArchivoElegido');
    var $btnSubir = $('#btnSubirReporte');
    var $form = $('#formReporteReservas');

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

    // Envío por formulario HTML (evita bloqueo de Cloudflare a peticiones AJAX)
    $form.on('submit', function (e) {
        if (!$input[0].files || !$input[0].files[0]) {
            e.preventDefault();
            mostrarAlerta('Seleccione un archivo', 'warning');
            return;
        }
        $btnSubir.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Subiendo...');
    });

    if ($('#tablaReportesReservas tbody tr').length && $.fn.DataTable) {
        $('#tablaReportesReservas').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 10,
            order: [[0, 'desc']],
            columnDefs: [
                { type: 'num', targets: 0 },
                { orderable: false, targets: 7 }
            ]
        });
    }

    if ($('#tablaLineasReporte tbody tr').length && $.fn.DataTable) {
        $('#tablaLineasReporte').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'asc']]
        });
    }
});

function mostrarAlerta(mensaje, tipo) {
    var cls = 'alert-' + (tipo || 'info');
    var $a = $('<div class="alert ' + cls + ' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">' +
        $('<div>').text(mensaje).html() +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append($a);
    setTimeout(function () { $a.alert('close'); }, 6000);
}
