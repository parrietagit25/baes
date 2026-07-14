$(document).ready(function () {
    cargarFerias();
    $('#feriaForm').on('submit', function (e) {
        e.preventDefault();
        guardarFeria();
    });
    $('#btnAddVendedor').on('click', function () {
        agregarVendedorFeria();
    });
    $(document).on('click', '#feriasTable [data-action]', function () {
        var action = $(this).data('action');
        var id = parseInt($(this).data('id'), 10);
        if (!id) return;
        if (action === 'edit') editarFeria(id);
        else if (action === 'vendors') abrirModalVendedores(id, $(this).data('nombre') || '');
        else if (action === 'panel') window.location.href = 'feria_panel.php?id=' + id;
        else if (action === 'delete') eliminarFeria(id);
    });
    $(document).on('click', '#listaVendedoresFeria [data-remove-ev]', function () {
        var evId = parseInt($(this).data('remove-ev'), 10);
        quitarVendedorFeria(evId);
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

function mostrarAlerta(mensaje, tipo) {
    var cls = 'alert-' + (tipo || 'info');
    var $a = $('<div class="alert ' + cls + ' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">' +
        $('<div>').text(mensaje).html() +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append($a);
    setTimeout(function () { $a.alert('close'); }, 4000);
}

function formatearRangoFecha(inicio, fin) {
    if (!inicio && !fin) return '—';
    function fmt(d) {
        if (!d) return '—';
        try {
            var p = String(d).substring(0, 10).split('-');
            if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
        } catch (e) {}
        return d;
    }
    return fmt(inicio) + ' – ' + fmt(fin);
}

function cargarFerias() {
    $.ajax({
        url: 'api/ferias.php',
        type: 'GET',
        dataType: 'json'
    }).done(function (response) {
        if (!response.success) {
            mostrarAlerta(response.message || 'Error al cargar', 'danger');
            return;
        }
        var tbody = $('#feriasTable tbody');
        tbody.empty();
        (response.data || []).forEach(function (row) {
            var activo = parseInt(row.activo, 10) === 1;
            var nVend = parseInt(row.vendedores_count, 10) || 0;
            var acciones =
                '<div class="btn-group btn-group-sm">' +
                '<button type="button" class="btn btn-outline-primary btn-action" data-action="vendors" data-id="' + row.id + '" data-nombre="' + escHtml(row.nombre) + '" title="Administrar vendedores"><i class="fas fa-users"></i></button>' +
                '<button type="button" class="btn btn-outline-success btn-action" data-action="panel" data-id="' + row.id + '" title="Panel en vivo"><i class="fas fa-th-large"></i></button>' +
                '<button type="button" class="btn btn-outline-secondary btn-action" data-action="edit" data-id="' + row.id + '" title="Editar"><i class="fas fa-edit"></i></button>' +
                '<button type="button" class="btn btn-outline-danger btn-action" data-action="delete" data-id="' + row.id + '" title="Eliminar"><i class="fas fa-trash"></i></button>' +
                '</div>';
            tbody.append(
                '<tr>' +
                '<td>' + row.id + '</td>' +
                '<td><strong>' + escHtml(row.nombre) + '</strong></td>' +
                '<td>' + escHtml(formatearRangoFecha(row.fecha_inicio, row.fecha_fin)) + '</td>' +
                '<td>' + escHtml(row.lugar || '—') + '</td>' +
                '<td><span class="badge bg-info">' + nVend + '</span></td>' +
                '<td><span class="badge badge-estado ' + (activo ? 'estado-activo' : 'estado-inactivo') + '">' + (activo ? 'Activa' : 'Inactiva') + '</span></td>' +
                '<td>' + acciones + '</td>' +
                '</tr>'
            );
        });

        if ($.fn.DataTable.isDataTable('#feriasTable')) {
            $('#feriasTable').DataTable().destroy();
        }
        setTimeout(function () {
            $('#feriasTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                pageLength: 15,
                order: [[0, 'desc']],
                columnDefs: [{ orderable: false, targets: [6] }],
                autoWidth: false
            });
        }, 100);
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function limpiarFormularioFeria() {
    $('#feria_id_hidden').val('');
    $('#feria_nombre').val('');
    $('#feria_fecha_inicio').val('');
    $('#feria_fecha_fin').val('');
    $('#feria_lugar').val('');
    $('#feria_descripcion').val('');
    $('#feria_activo').prop('checked', true);
    $('#feriaModalLabel').html('<i class="fas fa-store me-2"></i>Registrar feria');
}

function guardarFeria() {
    var id = $('#feria_id_hidden').val();
    var payload = {
        nombre: $('#feria_nombre').val().trim(),
        fecha_inicio: $('#feria_fecha_inicio').val(),
        fecha_fin: $('#feria_fecha_fin').val(),
        lugar: $('#feria_lugar').val().trim(),
        descripcion: $('#feria_descripcion').val().trim(),
        activo: $('#feria_activo').is(':checked') ? 1 : 0
    };
    if (!payload.nombre || !payload.fecha_inicio || !payload.fecha_fin) {
        mostrarAlerta('Complete los campos obligatorios', 'warning');
        return;
    }
    var req = id
        ? $.ajax({ url: 'api/ferias.php', type: 'PUT', data: $.extend({ id: id }, payload), dataType: 'json' })
        : $.ajax({ url: 'api/ferias.php', type: 'POST', data: payload, dataType: 'json' });

    req.done(function (r) {
        if (r.success) {
            var el = document.getElementById('feriaModal');
            var mi = bootstrap.Modal.getInstance(el);
            if (mi) mi.hide();
            mostrarAlerta(r.message || 'Guardado', 'success');
            cargarFerias();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function editarFeria(id) {
    $.ajax({
        url: 'api/ferias.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json'
    }).done(function (r) {
        if (!r.success || !r.data) {
            mostrarAlerta(r.message || 'Error', 'danger');
            return;
        }
        var d = r.data;
        $('#feria_id_hidden').val(d.id);
        $('#feria_nombre').val(d.nombre || '');
        $('#feria_fecha_inicio').val((d.fecha_inicio || '').substring(0, 10));
        $('#feria_fecha_fin').val((d.fecha_fin || '').substring(0, 10));
        $('#feria_lugar').val(d.lugar || '');
        $('#feria_descripcion').val(d.descripcion || '');
        $('#feria_activo').prop('checked', parseInt(d.activo, 10) === 1);
        $('#feriaModalLabel').html('<i class="fas fa-store me-2"></i>Editar feria');
        new bootstrap.Modal(document.getElementById('feriaModal')).show();
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function eliminarFeria(id) {
    if (!confirm('¿Eliminar esta feria? Se quitarán también los vendedores asignados (no afecta solicitudes).')) {
        return;
    }
    $.ajax({
        url: 'api/ferias.php?id=' + id,
        type: 'DELETE',
        dataType: 'json'
    }).done(function (r) {
        if (r.success) {
            mostrarAlerta(r.message || 'Eliminada', 'success');
            cargarFerias();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function abrirModalVendedores(feriaId, nombre) {
    $('#vendedores_feria_id').val(feriaId);
    $('#vendedoresFeriaNombre').text(nombre ? 'Feria: ' + nombre : '');
    $('#vendedoresModalLabel').html('<i class="fas fa-users me-2"></i>Vendedores de la feria');
    cargarCatalogoVendedores(feriaId);
    cargarVendedoresFeria(feriaId);
    new bootstrap.Modal(document.getElementById('vendedoresModal')).show();
}

function cargarCatalogoVendedores(feriaId) {
    $.when(
        $.ajax({ url: 'api/ferias.php?action=catalogo_vendedores', type: 'GET', dataType: 'json' }),
        $.ajax({ url: 'api/ferias.php?action=vendedores&feria_id=' + feriaId, type: 'GET', dataType: 'json' })
    ).done(function (catRes, asgRes) {
        var cat = catRes[0];
        var asg = asgRes[0];
        var asignados = {};
        if (asg && asg.success) {
            (asg.data || []).forEach(function (v) { asignados[String(v.id)] = true; });
        }
        var $sel = $('#selectVendedorAdd');
        $sel.empty().append('<option value="">Seleccione un ejecutivo de ventas...</option>');
        if (cat && cat.success) {
            (cat.data || []).forEach(function (ev) {
                if (asignados[String(ev.id)]) return;
                var label = ev.nombre + (ev.sucursal ? ' — ' + ev.sucursal : '') + (ev.email ? ' (' + ev.email + ')' : '');
                $sel.append('<option value="' + ev.id + '">' + escHtml(label) + '</option>');
            });
        }
    });
}

function cargarVendedoresFeria(feriaId) {
    var $lista = $('#listaVendedoresFeria');
    $lista.html('<li class="list-group-item text-muted">Cargando...</li>');
    $.ajax({
        url: 'api/ferias.php?action=vendedores&feria_id=' + feriaId,
        type: 'GET',
        dataType: 'json'
    }).done(function (r) {
        if (!r.success) {
            $lista.html('<li class="list-group-item text-danger">' + escHtml(r.message || 'Error') + '</li>');
            return;
        }
        var rows = r.data || [];
        if (!rows.length) {
            $lista.html('<li class="list-group-item text-muted">Ningún vendedor asignado aún.</li>');
            return;
        }
        $lista.empty();
        rows.forEach(function (v) {
            var meta = [v.sucursal, v.email].filter(Boolean).join(' · ');
            $lista.append(
                '<li class="list-group-item">' +
                '<div><strong>' + escHtml(v.nombre) + '</strong>' +
                (meta ? '<br><small class="text-muted">' + escHtml(meta) + '</small>' : '') +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-remove-ev="' + v.id + '" title="Quitar"><i class="fas fa-user-minus"></i></button>' +
                '</li>'
            );
        });
    }).fail(function () {
        $lista.html('<li class="list-group-item text-danger">Error de conexión</li>');
    });
}

function agregarVendedorFeria() {
    var feriaId = parseInt($('#vendedores_feria_id').val(), 10);
    var evId = parseInt($('#selectVendedorAdd').val(), 10);
    if (!feriaId || !evId) {
        mostrarAlerta('Seleccione un vendedor', 'warning');
        return;
    }
    $.ajax({
        url: 'api/ferias.php?action=vendedores',
        type: 'POST',
        data: { feria_id: feriaId, ejecutivo_ventas_id: evId },
        dataType: 'json'
    }).done(function (r) {
        if (r.success) {
            mostrarAlerta(r.message || 'Añadido', 'success');
            cargarCatalogoVendedores(feriaId);
            cargarVendedoresFeria(feriaId);
            cargarFerias();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}

function quitarVendedorFeria(evId) {
    var feriaId = parseInt($('#vendedores_feria_id').val(), 10);
    if (!feriaId || !evId) return;
    if (!confirm('¿Quitar este vendedor de la feria?')) return;
    $.ajax({
        url: 'api/ferias.php?action=vendedores&feria_id=' + feriaId + '&ejecutivo_ventas_id=' + evId,
        type: 'DELETE',
        dataType: 'json'
    }).done(function (r) {
        if (r.success) {
            mostrarAlerta(r.message || 'Quitado', 'success');
            cargarCatalogoVendedores(feriaId);
            cargarVendedoresFeria(feriaId);
            cargarFerias();
        } else {
            mostrarAlerta(r.message || 'Error', 'danger');
        }
    }).fail(function () {
        mostrarAlerta('Error de conexión', 'danger');
    });
}
