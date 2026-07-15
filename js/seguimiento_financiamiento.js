/**
 * Seguimiento — formulario público vs solicitud Motus.
 */
(function () {
    var chartVinculo = null;
    var chartEnviada = null;

    function qs() {
        var d = document.getElementById('segFinDesde');
        var h = document.getElementById('segFinHasta');
        var v = document.getElementById('segFinVinculo');
        var p = new URLSearchParams();
        p.set('desde', d && d.value ? d.value : '');
        p.set('hasta', h && h.value ? h.value : '');
        p.set('vinculo', v && v.value ? v.value : '');
        p.set('_ts', String(Date.now()));
        return p.toString();
    }

    function vinculoLabel(v) {
        if (v === 'con') return 'Con solicitud Motus';
        if (v === 'sin') return 'Sin solicitud Motus';
        return 'Todos';
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
        if (chartVinculo) {
            chartVinculo.destroy();
            chartVinculo = null;
        }
        if (chartEnviada) {
            chartEnviada.destroy();
            chartEnviada = null;
        }
    }

    function renderPie(canvasId, pie, colors) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !pie || !pie.length) return null;
        var total = pie.reduce(function (s, x) { return s + (x.total || 0); }, 0);
        if (total <= 0) return null;
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: pie.map(function (x) { return x.label; }),
                datasets: [{
                    data: pie.map(function (x) { return x.total; }),
                    backgroundColor: colors || ['#198754', '#6c757d'],
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                var val = c.raw || 0;
                                var pct = total > 0 ? Math.round(100 * val / total * 10) / 10 : 0;
                                return c.label + ': ' + val + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    function renderDetalleTabla(filas) {
        var wrap = document.getElementById('segFinDetalleWrap');
        var tbody = document.querySelector('#tablaSegFinDetalle tbody');
        if (!wrap || !tbody) return;
        wrap.style.display = 'block';
        if (!filas || !filas.length) {
            tbody.innerHTML = '<tr><td colspan="42" class="text-center text-muted">Sin registros con los filtros aplicados</td></tr>';
            return;
        }
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
            html += '<td>' + escapeHtml(r.enviada_a_banco_txt || '—') + '</td>';
            html += '<td>' + escapeHtml(r.banco_nombre || '—') + '</td>';
            html += '<td>' + escapeHtml(r.banco_agente || '—') + '</td>';
            html += '<td>' + escapeHtml(r.banco_decision || '—') + '</td>';
            html += '<td>' + escapeHtml(r.banco_razon || '—') + '</td>';
            html += '<td>' + escapeHtml(fmtPct(r.banco_tasa)) + '</td>';
            html += '<td>' + escapeHtml(fmtMoney(r.banco_valor_financiar)) + '</td>';
            html += '<td>' + escapeHtml(fmtMoney(r.banco_abono)) + '</td>';
            html += '<td>' + escapeHtml(r.banco_plazo != null && r.banco_plazo !== '' ? (r.banco_plazo + ' meses') : '—') + '</td>';
            html += '<td>' + escapeHtml(fmtMoney(r.banco_letra)) + '</td>';
            html += '<td>' + escapeHtml(fmtMoney(r.banco_letra_quincenal)) + '</td>';
            html += '<td>' + escapeHtml(r.banco_promocion || '—') + '</td>';
            html += '<td>' + escapeHtml(fmtMoney(r.banco_cuantia)) + '</td>';
            html += '<td>' + escapeHtml(r.banco_comentarios || '—') + '</td>';
            html += '<td>' + escapeHtml(r.banco_fecha_evaluacion || '—') + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    window.cargarSeguimientoFin = function () {
        var elTotal = document.getElementById('segFinKpiTotal');
        var elCon = document.getElementById('segFinKpiCon');
        var elSin = document.getElementById('segFinKpiSin');
        var elEnv = document.getElementById('segFinKpiEnviada');
        var elNoEnv = document.getElementById('segFinKpiNoEnviada');
        var elRango = document.getElementById('segFinRangoFechas');
        var elFiltrosTxt = document.getElementById('segFinFiltrosAplicados');
        var elExport = document.getElementById('segFinExportXlsx');
        var tbody = document.querySelector('#tablaSegFinDetalle tbody');

        if (elTotal) elTotal.textContent = '…';
        if (elCon) elCon.textContent = '…';
        if (elSin) elSin.textContent = '…';
        if (elEnv) elEnv.textContent = '…';
        if (elNoEnv) elNoEnv.textContent = '…';
        if (tbody) tbody.innerHTML = '<tr><td colspan="42" class="text-center text-muted">Filtrando…</td></tr>';

        var query = qs();
        if (elExport) {
            elExport.href = 'api/seguimiento_financiamiento.php?action=exportar_xlsx&' + query;
        }

        fetch('api/seguimiento_financiamiento.php?action=reporte&' + query, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="42" class="text-center text-danger">'
                            + escapeHtml((data && data.message) || 'Error al filtrar') + '</td></tr>';
                    }
                    destroyCharts();
                    return;
                }
                var k = data.kpis || {};
                if (elTotal) elTotal.textContent = k.total ?? 0;
                if (elCon) elCon.textContent = k.con_motus ?? 0;
                if (elSin) elSin.textContent = k.sin_motus ?? 0;
                if (elEnv) elEnv.textContent = k.enviada_banco ?? 0;
                if (elNoEnv) elNoEnv.textContent = k.no_enviada_banco ?? 0;

                var f = data.filtros || {};
                if (elRango) {
                    elRango.textContent = (f.fecha_desde || '') + ' — ' + (f.fecha_hasta || '');
                }
                if (elFiltrosTxt) {
                    elFiltrosTxt.textContent = 'Filtros aplicados: '
                        + (f.fecha_desde || '—') + ' a ' + (f.fecha_hasta || '—')
                        + ' · Vínculo: ' + vinculoLabel(f.vinculo || '')
                        + ' · Resultados: ' + (k.total ?? 0);
                }

                destroyCharts();
                chartVinculo = renderPie('segFinChartVinculo', data.pie_vinculo || [], ['#198754', '#6c757d']);
                chartEnviada = renderPie('segFinChartEnviadaBanco', data.pie_enviada_banco || [], ['#0d6efd', '#ffc107']);
                renderDetalleTabla(data.filas || []);
            })
            .catch(function () {
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="42" class="text-center text-danger">Error de conexión al filtrar</td></tr>';
                }
                destroyCharts();
            });
    };

    var form = document.getElementById('segFinFiltrosForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            window.cargarSeguimientoFin();
        });
    }
    var btn = document.getElementById('btnSegFinFiltrar');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.cargarSeguimientoFin();
        });
    }

    window.cargarSeguimientoFin();
})();
