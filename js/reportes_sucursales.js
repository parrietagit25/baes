/**
 * Rep. Sucursales — gráficas y tablas (Chart.js 4).
 */
(function () {
    const ESTADOS = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
    const COLORES_SUC = {
        CH: '#e74c3c',
        CV: '#3498db',
        TBM: '#2ecc71',
        VIS: '#9b59b6',
        BDC: '#f39c12',
    };
    const COLORES_ESTADO = ['#6c757d', '#0dcaf0', '#198754', '#dc3545', '#20c997', '#fd7e14'];

    let charts = {};
    let datosCache = null;

    function escapeHtml(t) {
        if (t == null) return '';
        const d = document.createElement('div');
        d.textContent = String(t);
        return d.innerHTML;
    }

    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            charts[key] = null;
        }
    }

    function filaEstadosHtml(row) {
        let h = '';
        ESTADOS.forEach(function (est) {
            h += '<td class="text-center">' + (row[est] != null ? row[est] : 0) + '</td>';
        });
        h += '<td class="text-center fw-bold">' + (row.total || 0) + '</td>';
        return h;
    }

    function renderTablas(d) {
        let html = '';
        (d.por_sucursal || []).forEach(function (row) {
            html += '<tr><td><span class="badge bg-secondary">' + escapeHtml(row.codigo) + '</span></td>';
            html += '<td>' + escapeHtml(row.nombre) + '</td>' + filaEstadosHtml(row) + '</tr>';
        });
        document.querySelector('#tabla-sucursal-resumen tbody').innerHTML = html || '<tr><td colspan="9" class="text-center text-muted">Sin datos</td></tr>';

        html = '';
        (d.por_agente || []).forEach(function (row) {
            html += '<tr><td>' + escapeHtml(row.nombre) + '<br><small class="text-muted">' + escapeHtml(row.sigla) + ' · ' + escapeHtml(row.nombre_sucursal) + '</small></td>';
            html += filaEstadosHtml(row) + '</tr>';
        });
        document.querySelector('#tabla-sucursal-agentes tbody').innerHTML = html || '<tr><td colspan="8" class="text-center text-muted">Sin agentes con solicitudes</td></tr>';

        html = '';
        (d.por_supervisor || []).forEach(function (row) {
            html += '<tr><td>' + escapeHtml(row.nombre) + '<br><small class="text-muted">' + escapeHtml(row.sigla) + '</small></td>';
            html += '<td>' + escapeHtml(row.nombre_sucursal) + '<br><small class="text-muted">' + (row.agentes_en_sucursal || 0) + ' agente(s)</small></td>';
            html += filaEstadosHtml(row) + '</tr>';
        });
        document.querySelector('#tabla-sucursal-supervisores tbody').innerHTML = html || '<tr><td colspan="9" class="text-center text-muted">Sin supervisores</td></tr>';
    }

    function renderKpis(k) {
        document.getElementById('sucKpiTotal').textContent = k.total_solicitudes ?? 0;
        document.getElementById('sucKpiAnio').textContent = k.total_anio ?? 0;
        document.getElementById('sucKpiAgentes').textContent = k.total_agentes ?? 0;
        document.getElementById('sucKpiTasa').textContent = (k.tasa_aprobacion != null ? k.tasa_aprobacion + '%' : '—');
        const l = k.sucursal_lider;
        document.getElementById('sucKpiLider').textContent = l ? (l.nombre + ' (' + l.total + ')') : '—';
        document.getElementById('sucKpiSinEv').textContent = k.sin_ejecutivo ?? 0;
    }

    function renderCharts(d) {
        destroyChart('pieSucursal');
        destroyChart('pieEstado');
        destroyChart('barTop');
        destroyChart('lineMes');

        const ctxSuc = document.getElementById('sucChartPieSucursal');
        if (ctxSuc && d.por_sucursal && d.por_sucursal.length) {
            charts.pieSucursal = new Chart(ctxSuc, {
                type: 'pie',
                data: {
                    labels: d.por_sucursal.map(function (r) { return r.nombre; }),
                    datasets: [{
                        data: d.por_sucursal.map(function (r) { return r.total; }),
                        backgroundColor: d.por_sucursal.map(function (r) { return COLORES_SUC[r.codigo] || '#95a5a6'; }),
                    }],
                },
                options: { plugins: { legend: { position: 'bottom' } } },
            });
        }

        const ctxEst = document.getElementById('sucChartPieEstado');
        if (ctxEst && d.por_estado) {
            charts.pieEstado = new Chart(ctxEst, {
                type: 'doughnut',
                data: {
                    labels: d.por_estado.map(function (r) { return r.estado; }),
                    datasets: [{
                        data: d.por_estado.map(function (r) { return r.total; }),
                        backgroundColor: COLORES_ESTADO,
                    }],
                },
                options: { plugins: { legend: { position: 'bottom' } } },
            });
        }

        const ctxBar = document.getElementById('sucChartBarAgentes');
        const top = d.top_agentes || [];
        if (ctxBar && top.length) {
            charts.barTop = new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: top.map(function (r) { return r.nombre; }),
                    datasets: [{
                        label: 'Solicitudes',
                        data: top.map(function (r) { return r.total; }),
                        backgroundColor: top.map(function (r) { return COLORES_SUC[r.codigo_sucursal] || '#667eea'; }),
                    }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } },
                },
            });
        }

        const ctxLine = document.getElementById('sucChartLineMes');
        const sm = d.serie_mensual || {};
        if (ctxLine && sm.series && sm.series.length) {
            charts.lineMes = new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: sm.meses || [],
                    datasets: sm.series.map(function (s) {
                        return {
                            label: s.nombre,
                            data: s.datos,
                            borderColor: COLORES_SUC[s.codigo] || '#333',
                            backgroundColor: 'transparent',
                            tension: 0.25,
                        };
                    }),
                },
                options: {
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        }

        const ctxBarSuc = document.getElementById('sucChartBarSucursal');
        if (ctxBarSuc && d.por_sucursal && d.por_sucursal.length) {
            destroyChart('barSuc');
            charts.barSuc = new Chart(ctxBarSuc, {
                type: 'bar',
                data: {
                    labels: d.por_sucursal.map(function (r) { return r.codigo; }),
                    datasets: ESTADOS.map(function (est, i) {
                        return {
                            label: est,
                            data: d.por_sucursal.map(function (r) { return r[est] || 0; }),
                            backgroundColor: COLORES_ESTADO[i],
                        };
                    }),
                },
                options: {
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        }
    }

    window.loadReporteSucursales = function () {
        const anio = document.getElementById('sucFiltroAnio').value || new Date().getFullYear();
        document.getElementById('sucAnioLabel').textContent = anio;
        var el2 = document.getElementById('sucAnioLabel2');
        if (el2) el2.textContent = anio;
        fetch('api/reportes.php?action=reporte_sucursales&anio=' + encodeURIComponent(anio))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data) {
                    document.querySelector('#tabla-sucursal-resumen tbody').innerHTML =
                        '<tr><td colspan="9" class="text-center text-danger">Error al cargar</td></tr>';
                    return;
                }
                datosCache = res.data;
                renderKpis(res.data.kpis || {});
                renderTablas(res.data);
                renderCharts(res.data);
            })
            .catch(function () {
                document.querySelector('#tabla-sucursal-resumen tbody').innerHTML =
                    '<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>';
            });
    };

    document.getElementById('btnSucFiltrar')?.addEventListener('click', window.loadReporteSucursales);
})();
