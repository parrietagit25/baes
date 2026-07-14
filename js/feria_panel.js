var feriaId = window.MOTUS_FERIA_ID || 0;
var fingerprint = '';
var pollTimer = null;
var dataTable = null;
var primeraCarga = true;

$(document).ready(function () {
    if (!feriaId) {
        window.location.href = 'ferias.php';
        return;
    }
    cargarPanel(true);
    pollTimer = setInterval(function () {
        cargarPanel(false);
    }, 3000);
});

function escHtml(s) {
    if (s == null || s === '') return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function getEstadoClass(estado) {
    var clases = {
        'Nueva': 'estado-nueva',
        'En Revisión Banco': 'estado-revision',
        'Aprobada': 'estado-aprobada',
        'Rechazada': 'estado-rechazada',
        'Completada': 'estado-completada',
        'Sin solicitud asociada': 'estado-sin-asoc'
    };
    return clases[estado] || 'estado-nueva';
}

function decisionLabel(dec) {
    var map = {
        preaprobado: 'Preaprobado',
        aprobado: 'Aprobado',
        aprobado_condicional: 'Aprobado condicional',
        rechazado: 'Rechazado'
    };
    var d = String(dec || '');
    // Si viene "Banco: decision"
    var parts = d.split(': ');
    if (parts.length >= 2) {
        var key = parts[parts.length - 1].toLowerCase().trim();
        if (map[key]) {
            parts[parts.length - 1] = map[key];
            return parts.join(': ');
        }
    }
    return map[d.toLowerCase()] || d;
}

function cargarPanel(forzarCompleto) {
    var params = { feria_id: feriaId };
    if (!forzarCompleto && fingerprint) {
        params.since = fingerprint;
    }
    $.ajax({
        url: 'api/feria_panel.php',
        type: 'GET',
        data: params,
        dataType: 'json'
    }).done(function (res) {
        if (!res.success) {
            if (primeraCarga) {
                $('#panelTable tbody').html(
                    '<tr><td colspan="9" class="text-center text-danger py-4">' + escHtml(res.message || 'Error') + '</td></tr>'
                );
            }
            return;
        }
        if (res.fingerprint) {
            fingerprint = res.fingerprint;
        }
        if (!res.changed && !forzarCompleto) {
            $('#panelLastUpdate').text('Sin cambios · ' + horaAhora());
            return;
        }
        renderStats(res.stats || {});
        renderTabla(res.data || []);
        if (res.feria) {
            var meta = [];
            if (res.feria.fecha_inicio && res.feria.fecha_fin) {
                meta.push(res.feria.fecha_inicio + ' — ' + res.feria.fecha_fin);
            }
            if (res.feria.lugar) meta.push(res.feria.lugar);
            if (meta.length) $('#feriaMeta').text(meta.join(' · '));
        }
        $('#panelLastUpdate').text('Actualizado ' + horaAhora());
        primeraCarga = false;
    }).fail(function () {
        if (primeraCarga) {
            $('#panelTable tbody').html(
                '<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión</td></tr>'
            );
        }
    });
}

function horaAhora() {
    try {
        return new Date().toLocaleTimeString('es-PA');
    } catch (e) {
        return '';
    }
}

function renderStats(stats) {
    $('#stat_total').text(stats.total || 0);
    $('#stat_sin_solicitud').text(stats.sin_solicitud || 0);
    $('#stat_con_solicitud').text(stats.con_solicitud || 0);
    $('#stat_en_revision_banco').text(stats.en_revision_banco || 0);
    $('#stat_aprobadas').text(stats.aprobadas || 0);
    $('#stat_completadas').text(stats.completadas || 0);
}

function renderTabla(rows) {
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
    var tbody = $('#panelTable tbody');
    tbody.empty();
    if (!rows.length) {
        tbody.html('<tr><td colspan="9" class="text-center text-muted py-4">No hay registros de vendedores de esta feria en el rango de fechas.</td></tr>');
        return;
    }
    rows.forEach(function (row) {
        var siNo = row.tiene_solicitud_credito
            ? '<span class="badge bg-success">Sí</span>'
            : '<span class="badge bg-secondary">No</span>';
        var cliente = '<strong>' + escHtml(row.cliente_nombre || '—') + '</strong>';
        if (row.cliente_telefono) {
            cliente += '<div class="cliente-tel"><i class="fas fa-phone me-1"></i>' + escHtml(row.cliente_telefono) + '</div>';
        }
        var bancos = (row.bancos_asignados && row.bancos_asignados.length)
            ? row.bancos_asignados.map(function (b) {
                return '<span class="badge bg-info text-dark me-1 mb-1">' + escHtml(b) + '</span>';
            }).join('')
            : '<span class="text-muted">—</span>';
        var resp = (row.respuestas_bancos && row.respuestas_bancos.length)
            ? row.respuestas_bancos.map(function (r) {
                return '<div class="small">' + escHtml(decisionLabel(r)) + '</div>';
            }).join('')
            : '<span class="text-muted">—</span>';
        var estado = row.estado || '—';
        var estadoClass = getEstadoClass(estado);
        var href = row.tiene_solicitud_credito && row.sc_id
            ? ('solicitudes.php?abrir_solicitud=' + row.sc_id)
            : ('sol_financiamiento.php?abrir_registro=' + row.fr_id);
        var ojo = '<a class="btn btn-sm btn-outline-primary btn-ojo" href="' + href + '" target="_blank" rel="noopener" title="Ver en MOTUS"><i class="fas fa-eye"></i></a>';

        tbody.append(
            '<tr>' +
            '<td>' + siNo + '</td>' +
            '<td>' + cliente + '</td>' +
            '<td>' + escHtml(row.vehiculo || '—') + '</td>' +
            '<td>' + escHtml(row.vendedor || '—') + '</td>' +
            '<td>' + escHtml(row.gestor || '—') + '</td>' +
            '<td>' + bancos + '</td>' +
            '<td>' + resp + '</td>' +
            '<td><span class="badge badge-estado ' + estadoClass + '">' + escHtml(estado) + '</span></td>' +
            '<td class="text-nowrap">' + ojo + '</td>' +
            '</tr>'
        );
    });

    dataTable = $('#panelTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        pageLength: 25,
        order: [],
        columnDefs: [{ orderable: false, targets: [8] }],
        autoWidth: false,
        destroy: true
    });
}
