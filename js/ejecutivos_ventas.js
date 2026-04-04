$(document).ready(function () {
    cargarEjecutivos();

    $('#ejecutivoVentasForm').on('submit', function (e) {
        e.preventDefault();
        guardarEjecutivo();
    });
});

function escHtml(s) {
    if (s == null || s === '') return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatearFecha(f) {
    if (!f || f === '0000-00-00 00:00:00') return '-';
    try {
        return new Date(f.replace(' ', 'T')).toLocaleString('es-PA');
    } catch (e) {
        return f;
    }
}

function mostrarAlerta(mensaje, tipo) {
    var cls = 'alert-' + (tipo || 'info');
    var $a = $('<div class="alert ' + cls + ' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">' +
        $('<div>').text(mensaje).html() +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append($a);
    setTimeout(function () { $a.alert('close'); }, 4000);
}

function cargarEjecutivos() {
    $.ajax({
        url: 'api/ejecutivos_ventas.php',
        type: 'GET',
        dataType: 'json'
    }).done(function (response) {
        if (!response.success) {
            mostrarAlerta(response.message || 'Error al cargar', 'danger');
            return;
        }
        var tbody = $('#ejecutivosVentasTable tbody');
        tbody.empty();
        (response.data || []).forEach(function (row) {
            var activo = parseInt(row.activo, 10) === 1;
            var estadoClass = activo ? 'estado-activo' : 'estado-inactivo';
            var estadoTxt = activo ? 'Activo' : 'Inactivo';
            var nSol = parseInt(row.solicitudes_count, 10) || 0;
            var solBadge = nSol > 0
                ? '<span class="badge bg-info">' + nSol + ' solicitud' + (nSol !== 1 ? 'es' : '') + '</span>'
                : '<span class="text-muted">0</span>';
            var acciones = '<div class="btn-group btn-group-sm">' +
                '<button type="button" class="btn btn-primary btn-action" data-action="edit" data-id="' + row.id + '" title="Editar"><i class="fas fa-edit"></i></button>';
            if (activo) {
                acciones += '<button type="button" class="btn btn-warning btn-action" data-action="deactivate" data-id="' + row.id + '" title="Desactivar"><i class="fas fa-toggle-off"></i></button>';
            } else {
                acciones += '<button type="button" class="btn btn-success btn-action" data-action="activate" data-id="' + row.id + '" title="Activar"><i class="fas fa-toggle-on"></i></button>';
            }
            acciones += '</div>';
            var tr = '<tr>' +
                '<td>' + row.id + '</td>' +
                '<td><strong>' + escHtml(row.nombre) + '</strong></td>' +
                '<td>' + escHtml(row.sucursal || '-') + '</td>' +
                '<td>' + escHtml(row.email || '-') + '</td>' +
                '<td>' + solBadge + '</td>' +
                '<td><span class="badge badge-estado ' + estadoClass + '">' + estadoTxt + '</span></td>' +
                '<td>' + escHtml(formatearFecha(row.fecha_creacion)) + '</td>' +
                '<td>' + acciones + '</td>' +
                '</tr>';
            tbody.append(tr);
        });

        if ($.fn.DataTable.isDataTable('#ejecutivosVentasTable')) {
            $('#ejecutivosVentasTable').DataTable().destroy();
        }
        setTimeout(function () {
            $('#ejecutivosVentasTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                pageLength: 15,
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: [7] }],
                responsive: true,
                autoWidth: false
            });
        }, 100);
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function limpiarFormularioEjecutivo() {
    $('#ejecutivo_ventas_id_hidden').val('');
    $('#ev_nombre').val('');
    $('#ev_sucursal').val('');
    $('#ev_email').val('');
    $('#ev_activo').prop('checked', true);
    $('#ejecutivoVentasModalLabel').html('<i class="fas fa-user-tie me-2"></i>Nuevo ejecutivo de ventas');
}

function guardarEjecutivo() {
    var id = $('#ejecutivo_ventas_id_hidden').val();
    var payload = {
        nombre: $('#ev_nombre').val().trim(),
        sucursal: $('#ev_sucursal').val().trim(),
        email: $('#ev_email').val().trim(),
        activo: $('#ev_activo').is(':checked') ? 1 : 0
    };
    if (!payload.nombre) {
        mostrarAlerta('El nombre es obligatorio', 'warning');
        return;
    }
    if (id) {
        payload.id = id;
        $.ajax({
            url: 'api/ejecutivos_ventas.php',
            type: 'PUT',
            data: payload,
            dataType: 'json'
        }).done(function (r) {
            if (r.success) {
                var elM = document.getElementById('ejecutivoVentasModal');
                var mi = bootstrap.Modal.getInstance(elM);
                if (mi) mi.hide();
                mostrarAlerta(r.message || 'Guardado', 'success');
                cargarEjecutivos();
            } else {
                mostrarAlerta(r.message || 'Error', 'danger');
            }
        }).fail(function () {
            mostrarAlerta('Error de conexión', 'danger');
        });
    } else {
        $.ajax({
            url: 'api/ejecutivos_ventas.php',
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (r) {
            if (r.success) {
                var elM2 = document.getElementById('ejecutivoVentasModal');
                var mi2 = bootstrap.Modal.getInstance(elM2);
                if (mi2) mi2.hide();
                mostrarAlerta(r.message || 'Creado', 'success');
                cargarEjecutivos();
            } else {
                mostrarAlerta(r.message || 'Error', 'danger');
            }
        }).fail(function () {
            mostrarAlerta('Error de conexión', 'danger');
        });
    }
}

function editarEjecutivo(id) {
    $.ajax({
        url: 'api/ejecutivos_ventas.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json'
    }).done(function (r) {
        if (!r.success || !r.data) {
            mostrarAlerta(r.message || 'Error', 'danger');
            return;
        }
        var d = r.data;
        $('#ejecutivo_ventas_id_hidden').val(d.id);
        $('#ev_nombre').val(d.nombre || '');
        $('#ev_sucursal').val(d.sucursal || '');
        $('#ev_email').val(d.email || '');
        $('#ev_activo').prop('checked', parseInt(d.activo, 10) === 1);
        $('#ejecutivoVentasModalLabel').html('<i class="fas fa-user-tie me-2"></i>Editar ejecutivo');
        new bootstrap.Modal(document.getElementById('ejecutivoVentasModal')).show();
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function cambiarActivoEjecutivo(id, activo) {
    $.ajax({
        url: 'api/ejecutivos_ventas.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json'
    }).done(function (r) {
        if (!r.success || !r.data) {
            mostrarAlerta(r.message || 'Error', 'danger');
            return;
        }
        var d = r.data;
        var body = 'id=' + encodeURIComponent(id) +
            '&nombre=' + encodeURIComponent(d.nombre || '') +
            '&sucursal=' + encodeURIComponent(d.sucursal || '') +
            '&email=' + encodeURIComponent(d.email || '') +
            '&activo=' + (activo ? 1 : 0);
        $.ajax({
            url: 'api/ejecutivos_ventas.php',
            type: 'PUT',
            data: body,
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json'
        }).done(function (r2) {
            if (r2.success) {
                mostrarAlerta(r2.message || 'Estado actualizado', 'success');
                cargarEjecutivos();
            } else {
                mostrarAlerta(r2.message || 'Error', 'danger');
            }
        }).fail(function () {
            mostrarAlerta('Error de conexión', 'danger');
        });
    });
}

$(document).on('click', '[data-action="edit"]', function () {
    editarEjecutivo($(this).data('id'));
});
$(document).on('click', '[data-action="deactivate"]', function () {
    if (confirm('¿Desactivar este ejecutivo? No aparecerá en nuevas solicitudes; las ya asignadas conservan la referencia.')) {
        cambiarActivoEjecutivo($(this).data('id'), false);
    }
});
$(document).on('click', '[data-action="activate"]', function () {
    cambiarActivoEjecutivo($(this).data('id'), true);
});
