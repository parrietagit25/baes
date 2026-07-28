/**
 * Seguimiento admin banco — asignadas / respuestas / seleccionadas.
 */
(function () {
    var chartAsignadas = null;
    var chartSeleccionadas = null;
    var dt = null;

    function qs() {
        var d = document.getElementById('segBancoDesde');
        var h = document.getElementById('segBancoHasta');
        var p = new URLSearchParams();
        p.set('desde', d && d.value ? d.value : '');
        p.set('hasta', h && h.value ? h.value : '');
        p.set('_ts', String(Date.now()));
        return p.toString();
    }

    function escapeHtml(t) {
        if (t == null) return '';
        var el = document.createElement('div');
        el.textContent = String(t);
        return el.innerHTML;
    }

    function fmtMoney(v) {
        if (v == null || v === '') return '—';
        var n = parseFloat(v);
        if (!isFinite(n)) return '—';
        return '$' + n.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtPct(v) {
        if (v == null || v === '') return '—';
        var n = parseFloat(v);
        if (!isFinite(n)) return '—';
        return n.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    }

    function destroyCharts() {
        if (chartAsignadas) {
            chartAsignadas.destroy();
            chartAsignadas = null;
        }
        if (chartSeleccionadas) {
            chartSeleccionadas.destroy();
            chartSeleccionadas = null;
        }
    }

    function renderCompareBars(canvasId, pie, colors) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;
        var data = pie || [];
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(function (x) { return x.label; }),
                datasets: [{
                    label: 'Cantidad',
                    data: data.map(function (x) { return Number(x.total || 0); }),
                    backgroundColor: colors || ['#0d6efd', '#20c997']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function renderDetalle(filas) {
        var tbody = document.querySelector('#tablaSegBancoDetalle tbody');
        if (!tbody) return;

        if (dt) {
            dt.destroy();
            dt = null;
        }

        if (!filas || !filas.length) {
            tbody.innerHTML = '<tr><td colspan="19" class="text-center text-muted">Sin solicitudes con estos filtros</td></tr>';
            return;
        }

        var html = '';
        filas.forEach(function (r) {
            var sid = parseInt(r.solicitud_id, 10) || 0;
            html += '<tr>'
                + '<td>#' + sid + '</td>'
                + '<td class="text-nowrap"><small>' + escapeHtml(r.fecha_asignacion || '—') + '</small></td>'
                + '<td>' + escapeHtml(r.nombre_cliente || '—') + '</td>'
                + '<td>' + escapeHtml(r.cedula || '—') + '</td>'
                + '<td>' + escapeHtml(r.solicitud_estado || '—') + '</td>'
                + '<td><small>' + escapeHtml(r.encargados || '—') + '</small></td>'
                + '<td class="text-center">' + escapeHtml(String(r.total_respuestas != null ? r.total_respuestas : 0)) + '</td>'
                + '<td>' + escapeHtml((r.ultima_decision || '').toString().toUpperCase().replace(/_/g, ' ') || '—') + '</td>'
                + '<td>' + (r.es_seleccionada ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>') + '</td>'
                + '<td>' + escapeHtml((r.decision_seleccionada || '').toString().toUpperCase().replace(/_/g, ' ') || '—') + '</td>'
                + '<td>' + fmtPct(r.tasa_seleccionada) + '</td>'
                + '<td>' + fmtMoney(r.valor_financiar_seleccionada) + '</td>'
                + '<td>' + fmtMoney(r.abono_seleccionada) + '</td>'
                + '<td>' + (r.plazo_seleccionada != null && r.plazo_seleccionada !== '' ? escapeHtml(String(r.plazo_seleccionada)) + ' m' : '—') + '</td>'
                + '<td>' + fmtMoney(r.letra_seleccionada) + '</td>'
                + '<td><small>' + escapeHtml(r.promocion_seleccionada || '—') + '</small></td>'
                + '<td>' + fmtMoney(r.cuantia_seleccionada) + '</td>'
                + '<td><small>' + escapeHtml(r.vehiculo_label || '—') + '</small></td>'
                + '<td><a class="btn btn-sm btn-outline-primary" href="solicitudes.php?abrir_solicitud=' + sid + '" title="Abrir"><i class="fas fa-external-link-alt"></i></a></td>'
                + '</tr>';
        });
        tbody.innerHTML = html;

        if (window.jQuery && $.fn.DataTable) {
            dt = $('#tablaSegBancoDetalle').DataTable({
                order: [[1, 'desc']],
                pageLength: 25,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                scrollX: true
            });
        }
    }

    function loadReporte(ev) {
        if (ev && typeof ev.preventDefault === 'function') {
            ev.preventDefault();
            ev.stopPropagation();
        }

        var query = qs();
        var btn = document.getElementById('btnSegBancoFiltrar');
        var nota = document.getElementById('segBancoNotaApi');
        var aplicados = document.getElementById('segBancoFiltrosAplicados');
        var ex = document.getElementById('segBancoExportXlsx');
        if (ex) ex.href = 'api/reportes_banco.php?action=exportar_excel_seguimiento&' + query;
        if (nota) nota.textContent = '';
        if (aplicados) aplicados.textContent = 'Aplicando filtros…';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Filtrando…';
        }

        fetch('api/reportes_banco.php?action=reporte_seguimiento&' + query)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    if (nota) nota.textContent = data.message || 'No se pudo cargar';
                    if (aplicados) aplicados.textContent = 'No se pudieron aplicar los filtros.';
                    destroyCharts();
                    renderDetalle([]);
                    return;
                }

                var k = data.kpis || {};
                document.getElementById('segBancoKpiAsignadas').textContent = String(k.total_solicitudes != null ? k.total_solicitudes : 0);
                document.getElementById('segBancoKpiRespuestas').textContent = String(k.total_respuestas != null ? k.total_respuestas : 0);
                document.getElementById('segBancoKpiSeleccionadas').textContent = String(k.total_seleccionadas != null ? k.total_seleccionadas : 0);

                var f = data.filtros || {};
                if (aplicados) {
                    aplicados.textContent = 'Filtros aplicados: ' + (f.fecha_desde || '') + ' → ' + (f.fecha_hasta || '')
                        + ' · ' + String(k.total_solicitudes != null ? k.total_solicitudes : 0) + ' solicitudes';
                }
                if (nota && data.nota) nota.textContent = data.nota;

                destroyCharts();
                chartAsignadas = renderCompareBars('segBancoChartAsignadas', data.chart_asignadas_vs_enviadas, ['#0d6efd', '#20c997']);
                chartSeleccionadas = renderCompareBars('segBancoChartSeleccionadas', data.chart_enviadas_vs_seleccionadas, ['#20c997', '#198754']);
                renderDetalle(data.filas || []);
            })
            .catch(function () {
                if (nota) nota.textContent = 'Error de red o servidor';
                if (aplicados) aplicados.textContent = 'Error al filtrar. Intente de nuevo.';
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-filter me-1"></i>Filtrar';
                }
            });
    }

    var form = document.getElementById('segBancoFiltrosForm');
    if (form) form.addEventListener('submit', loadReporte);
    loadReporte();
})();
