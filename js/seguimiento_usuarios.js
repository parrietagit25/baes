(function () {
    'use strict';

    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function badgeEvento(ev) {
        var map = {
            login: 'success',
            logout: 'secondary',
            page_view: 'primary',
            click: 'info',
            action: 'warning',
            heartbeat: 'light text-dark',
            visibility: 'dark',
            login_failed: 'danger'
        };
        var cls = map[ev] || 'secondary';
        return '<span class="badge bg-' + cls + ' badge-evento">' + esc(ev) + '</span>';
    }

    function qsParams() {
        var p = new URLSearchParams();
        p.set('action', 'reporte');
        p.set('desde', $('#uaDesde').val() || '');
        p.set('hasta', $('#uaHasta').val() || '');
        var uid = $('#uaUsuario').val();
        if (uid) p.set('usuario_id', uid);
        var ev = $('#uaEvento').val();
        if (ev) p.set('evento', ev);
        var q = ($('#uaQ').val() || '').trim();
        if (q) p.set('q', q);
        p.set('limit', '800');
        return p;
    }

    function updateExportLink() {
        var p = qsParams();
        p.set('action', 'exportar_csv');
        $('#uaExportCsv').attr('href', 'api/seguimiento_usuarios.php?' + p.toString());
    }

    var dt = null;

    function fillUsuarios(list, selected) {
        var $sel = $('#uaUsuario');
        var cur = selected != null ? String(selected) : $sel.val();
        $sel.find('option:not([value=""])').remove();
        (list || []).forEach(function (u) {
            var label = ((u.nombre || '') + ' ' + (u.apellido || '')).trim();
            if (!label) label = u.email || ('#' + u.id);
            else if (u.email) label += ' (' + u.email + ')';
            if (parseInt(u.activo, 10) !== 1) label += ' [inactivo]';
            $sel.append($('<option>').val(u.id).text(label));
        });
        if (cur) $sel.val(cur);
    }

    function renderOnline(rows) {
        if (!rows || !rows.length) {
            $('#uaOnlineList').html('<span class="text-muted">Nadie activo en los últimos 5 minutos.</span>');
            return;
        }
        var html = '<ul class="list-unstyled mb-0">';
        rows.forEach(function (r) {
            var nom = ((r.nombre || '') + ' ' + (r.apellido || '')).trim() || (r.email || ('#' + r.usuario_id));
            var meta = motusMetaPagina(r.pagina);
            html += '<li class="mb-2 border-bottom pb-2">'
                + '<strong>' + esc(nom) + '</strong>'
                + '<div class="text-muted">' + esc(r.seccion || meta.seccion) + ' · ' + esc(r.pagina || '—')
                + (r.ip ? (' · IP ' + esc(r.ip)) : '')
                + '</div>'
                + '<div class="text-muted">Última actividad: ' + esc(r.ultima_vez || '') + '</div>'
                + '</li>';
        });
        html += '</ul>';
        $('#uaOnlineList').html(html);
    }

    function motusMetaPagina(p) {
        return { seccion: p ? String(p).replace(/\.php$/i, '') : '—' };
    }

    function renderListaSimple(target, rows, labelFn, countFn) {
        if (!rows || !rows.length) {
            $(target).html('<span class="text-muted">Sin datos en el rango.</span>');
            return;
        }
        var html = '<ul class="list-unstyled mb-0">';
        rows.forEach(function (r) {
            html += '<li class="d-flex justify-content-between mb-1"><span>' + esc(labelFn(r)) + '</span><strong>' + esc(countFn(r)) + '</strong></li>';
        });
        html += '</ul>';
        $(target).html(html);
    }

    function load() {
        updateExportLink();
        var p = qsParams();
        $('#uaFiltrosAplicados').text('Filtros: ' + p.get('desde') + ' → ' + p.get('hasta')
            + (p.get('usuario_id') ? (' · usuario #' + p.get('usuario_id')) : '')
            + (p.get('evento') ? (' · ' + p.get('evento')) : '')
            + (p.get('q') ? (' · "' + p.get('q') + '"') : ''));

        $.getJSON('api/seguimiento_usuarios.php?' + p.toString())
            .done(function (res) {
                if (!res || !res.success) {
                    alert((res && res.message) || 'No se pudo cargar el reporte');
                    return;
                }
                var d = res.data || {};
                var k = d.kpi || {};
                $('#uaKpiOnline').text(k.online_ahora || 0);
                $('#uaKpiLogins').text(k.total_logins || 0);
                $('#uaKpiLogouts').text(k.total_logouts || 0);
                $('#uaKpiViews').text(k.total_page_views || 0);
                $('#uaKpiUsers').text(k.usuarios_unicos || 0);
                $('#uaKpiEvents').text(k.total_eventos || 0);

                fillUsuarios(d.usuarios || [], d.filtros && d.filtros.usuario_id);
                renderOnline(d.online || []);
                renderListaSimple('#uaPorEvento', d.por_evento || [], function (r) { return r.evento; }, function (r) { return r.total; });
                renderListaSimple('#uaTopPaginas', d.top_paginas || [], function (r) {
                    return (r.seccion ? r.seccion + ' · ' : '') + (r.pagina || '');
                }, function (r) { return r.visitas; });

                var rows = (d.rows || []).map(function (r) {
                    var nom = ((r.nombre || '') + ' ' + (r.apellido || '')).trim();
                    if (!nom) nom = r.email || (r.usuario_id ? ('#' + r.usuario_id) : '—');
                    else if (r.email) nom += '<div class="text-muted small">' + esc(r.email) + '</div>';
                    return [
                        esc(r.created_at || ''),
                        nom,
                        badgeEvento(r.evento || ''),
                        esc(r.seccion || '—'),
                        esc(r.pagina || '—'),
                        esc(r.detalle || (r.url_path || '—')),
                        esc(r.ip || '—'),
                        '<span class="ua-ua-cell" title="' + esc(r.user_agent || '') + '">' + esc(r.user_agent || '—') + '</span>'
                    ];
                });

                if (dt) {
                    dt.clear();
                    dt.rows.add(rows);
                    dt.draw();
                } else {
                    dt = $('#tablaUaActividad').DataTable({
                        data: rows,
                        order: [[0, 'desc']],
                        pageLength: 25,
                        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
                    });
                }
            })
            .fail(function (xhr) {
                var msg = 'Error al cargar';
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j && j.message) msg = j.message;
                } catch (e) {}
                alert(msg);
            });
    }

    $(function () {
        $('#uaFiltrosForm').on('submit', function (e) {
            e.preventDefault();
            load();
        });
        load();
        setInterval(load, 60000);
    });
})();
