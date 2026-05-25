/**
 * Seguimiento — formulario público vs solicitud Motus.
 */
(function () {
    let chartVinculo = null;

    function qs() {
        var d = document.getElementById('segFinDesde').value || '';
        var h = document.getElementById('segFinHasta').value || '';
        var v = document.getElementById('segFinVinculo').value || '';
        return 'desde=' + encodeURIComponent(d) + '&hasta=' + encodeURIComponent(h) + '&vinculo=' + encodeURIComponent(v);
    }

    function escapeHtml(t) {
        if (t == null) return '';
        var el = document.createElement('div');
        el.textContent = String(t);
        return el.innerHTML;
    }

    function destroyChart() {
        if (chartVinculo) {
            chartVinculo.destroy();
            chartVinculo = null;
        }
    }

    function renderPie(pie) {
        destroyChart();
        var ctx = document.getElementById('segFinChartVinculo');
        if (!ctx || !pie || !pie.length) return;
        var total = pie.reduce(function (s, x) { return s + (x.total || 0); }, 0);
        if (total <= 0) return;
        chartVinculo = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: pie.map(function (x) { return x.label; }),
                datasets: [{
                    data: pie.map(function (x) { return x.total; }),
                    backgroundColor: ['#198754', '#6c757d'],
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.raw || 0;
                                var pct = total > 0 ? Math.round(100 * v / total * 10) / 10 : 0;
                                return ctx.label + ': ' + v + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    function renderTabla(filas) {
        var tbody = document.querySelector('#tablaSegFin tbody');
        if (!tbody) return;
        if (!filas || !filas.length) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted">Sin registros en el rango</td></tr>';
            return;
        }
        var html = '';
        filas.forEach(function (r) {
            var badge = r.tiene_solicitud_motus
                ? '<span class="badge bg-success">Con Motus</span>'
                : '<span class="badge bg-secondary">Sin Motus</span>';
            html += '<tr>';
            html += '<td>' + escapeHtml(r.id_sol_digital) + '</td>';
            html += '<td>' + escapeHtml(r.fecha_creacion || '') + '</td>';
            html += '<td>' + escapeHtml(r.cliente_nombre) + '</td>';
            html += '<td>' + escapeHtml(r.cliente_email || '—') + '</td>';
            html += '<td>' + badge + '</td>';
            html += '<td>' + escapeHtml(r.id_sol_motus || '—') + '</td>';
            html += '<td>' + escapeHtml(r.solicitud_estado || '—') + '</td>';
            html += '<td>' + escapeHtml(r.vendedor || '—') + '</td>';
            html += '<td>' + escapeHtml(r.telefono || '—') + '</td>';
            html += '<td>' + escapeHtml(r.unidad_vehiculo || '—') + '</td>';
            html += '<td>' + escapeHtml(r.rango_edad || '—') + '</td>';
            html += '<td>' + escapeHtml(r.rango_salario_usd || '—') + '</td>';
            html += '<td>' + escapeHtml(r.perfil_coincide_txt || '—') + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    function renderDetalleTabla(filas) {
        var wrap = document.getElementById('segFinDetalleWrap');
        var tbody = document.querySelector('#tablaSegFinDetalle tbody');
        if (!wrap || !tbody) return;
        if (!filas || !filas.length) {
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = 'block';
        var html = '';
        filas.forEach(function (r) {
            html += '<tr>';
            html += '<td>' + escapeHtml(r.id) + '</td>';
            html += '<td>' + escapeHtml(r.fecha_creacion) + '</td>';
            html += '<td>' + escapeHtml(r.cliente_nombre) + '</td>';
            html += '<td>' + escapeHtml(r.cliente_email || '—') + '</td>';
            html += '<td>' + escapeHtml(r.cliente_sexo) + '</td>';
            html += '<td>' + escapeHtml(r.genero_label) + '</td>';
            html += '<td>' + escapeHtml(r.edad_calculada) + '</td>';
            html += '<td>' + escapeHtml(r.rango_edad) + '</td>';
            html += '<td>' + escapeHtml(r.empresa_salario) + '</td>';
            html += '<td>' + escapeHtml(r.rango_salario_usd) + '</td>';
            html += '<td>' + escapeHtml(r.perfil_estimado) + '</td>';
            html += '<td>' + escapeHtml(r.sector_estimado) + '</td>';
            html += '<td>' + escapeHtml(r.solicitud_id || '—') + '</td>';
            html += '<td>' + escapeHtml(r.solicitud_estado || '—') + '</td>';
            html += '<td>' + escapeHtml(r.perfil_motus || '—') + '</td>';
            html += '<td>' + escapeHtml(r.ingreso_motus) + '</td>';
            html += '<td>' + escapeHtml(r.genero_motus || '—') + '</td>';
            html += '<td>' + escapeHtml(r.edad_motus) + '</td>';
            html += '<td>' + escapeHtml(r.nombre_motus || '—') + '</td>';
            html += '<td>' + escapeHtml(r.cedula_motus || '—') + '</td>';
            html += '<td>' + escapeHtml(r.perfil_coincide_txt) + '</td>';
            html += '<td>' + escapeHtml(r.genero_coincide_txt) + '</td>';
            html += '<td>' + escapeHtml(r.vendedor) + '</td>';
            html += '<td>' + escapeHtml(r.telefono) + '</td>';
            html += '<td>' + escapeHtml(r.unidad_vehiculo) + '</td>';
            html += '<td>' + escapeHtml(r.id_sol_digital) + '</td>';
            html += '<td>' + escapeHtml(r.id_sol_motus || '—') + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    window.cargarSeguimientoFin = function () {
        document.getElementById('segFinKpiTotal').textContent = '…';
        document.getElementById('segFinKpiCon').textContent = '…';
        document.getElementById('segFinKpiSin').textContent = '…';
        document.getElementById('segFinExportXlsx').href = 'api/seguimiento_financiamiento.php?action=exportar_xlsx&' + qs();

        fetch('api/seguimiento_financiamiento.php?action=reporte&' + qs())
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    document.querySelector('#tablaSegFin tbody').innerHTML =
                        '<tr><td colspan="13" class="text-center text-danger">' + escapeHtml(data.message || 'Error') + '</td></tr>';
                    destroyChart();
                    return;
                }
                var k = data.kpis || {};
                document.getElementById('segFinKpiTotal').textContent = k.total ?? 0;
                document.getElementById('segFinKpiCon').textContent = k.con_motus ?? 0;
                document.getElementById('segFinKpiSin').textContent = k.sin_motus ?? 0;
                var f = data.filtros || {};
                document.getElementById('segFinRangoFechas').textContent =
                    (f.fecha_desde || '') + ' — ' + (f.fecha_hasta || '');
                renderPie(data.pie_vinculo || []);
                renderTabla(data.filas || []);
                renderDetalleTabla(data.filas || []);
            })
            .catch(function () {
                document.querySelector('#tablaSegFin tbody').innerHTML =
                    '<tr><td colspan="12" class="text-center text-danger">Error de conexión</td></tr>';
                destroyChart();
            });
    };

    document.getElementById('btnSegFinFiltrar')?.addEventListener('click', window.cargarSeguimientoFin);
    window.cargarSeguimientoFin();
})();
