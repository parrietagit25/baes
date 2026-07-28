<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/banco_scope_helper.php';

$userRoles = $_SESSION['user_roles'] ?? [];
if (!motus_es_admin_banco($userRoles)) {
    header('Location: dashboard.php');
    exit();
}

$finRepDesde = (new DateTimeImmutable('-365 days'))->format('Y-m-d');
$finRepHasta = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sol. Fin. + Motus (banco) - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .reportes-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 24px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .fin-chart-wrap { min-height: 280px; }
        .table-reportes { font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="reportes-header">
                    <h2 class="mb-1"><i class="fas fa-link me-2"></i>Sol. Pública + Motus enlazada</h2>
                    <p class="mb-0 opacity-90">Formulario público vinculado a solicitudes Motus asignadas a su banco</p>
                </div>

                <div class="alert alert-info small mb-3">
                    <i class="fas fa-university me-1"></i>
                    Solo solicitudes con asignación activa a usuarios de su entidad bancaria.
                    <span id="finEnlNotaApi" class="d-block mt-1 text-muted"></span>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end flex-wrap">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Desde</label>
                                <input type="date" id="finEnlDesde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepDesde); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Hasta</label>
                                <input type="date" id="finEnlHasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($finRepHasta); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Género (formulario)</label>
                                <div class="d-flex flex-wrap gap-2 small">
                                    <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Femenino" checked> F</label>
                                    <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Masculino" checked> M</label>
                                    <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Otro" checked> Otro</label>
                                    <label class="mb-0"><input type="checkbox" class="form-check-input fin-enl-gen" value="Sin dato" checked> Sin dato</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Perfil estimado</label>
                                <select id="finEnlPerfil" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="asalariado">Asalariado</option>
                                    <option value="independiente">Independiente</option>
                                    <option value="jubilado">Jubilado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Sector estimado</label>
                                <select id="finEnlSector" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="gobierno">Público estimado</option>
                                    <option value="privado">Privado estimado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-sm w-100 mt-2" id="btnFinEnlFiltrar">
                                    <i class="fas fa-sync me-1"></i>Actualizar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="#" class="btn btn-outline-success btn-sm w-100 mt-1" id="finEnlExportXlsx">
                                    <i class="fas fa-file-excel me-1"></i>Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card"><div class="card-body text-center">
                            <div class="text-muted small">Parejas público–Motus</div>
                            <div class="h4 mb-0" id="finEnlKpiN">0</div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body text-center">
                            <div class="text-muted small">Perfil coincide (est. vs Motus)</div>
                            <div class="h4 mb-0 text-success" id="finEnlKpiPerfilOk">—</div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body text-center">
                            <div class="text-muted small">Género coincide</div>
                            <div class="h4 mb-0 text-primary" id="finEnlKpiGenOk">—</div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body text-center">
                            <div class="text-muted small">Abono promedio</div>
                            <div class="h4 mb-0 text-warning" id="finEnlKpiAbono">—</div>
                        </div></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Porcentaje de abono</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartAbono"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Perfil financiero (Motus)</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartPerfilMotus"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Género (formulario público)</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartGen"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Distribución por edad</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartEdad"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Perfil estimado (público)</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartPerfilPub"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Rango salario (USD) — público</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartSal"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Salario × género (apilado)</h6>
                                <div class="fin-chart-wrap"><canvas id="finEnlChartCruceSal"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">Muestra</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-reportes" id="tabla-fin-enl">
                                <thead class="table-light">
                                    <tr>
                                        <th>Id FR</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Id sol.</th>
                                        <th>Estado</th>
                                        <th>Abono %</th>
                                        <th>Perfil pub. est.</th>
                                        <th>Perfil Motus</th>
                                        <th>Gén. pub.</th>
                                        <th>Gén. Motus</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const finEnlCharts = {};

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function finCheckedGeneros(sel) {
        const out = [];
        document.querySelectorAll(sel).forEach(function(el) {
            if (el.checked) out.push(el.value);
        });
        return out;
    }

    function finEnlQueryString() {
        const p = new URLSearchParams();
        p.set('desde', (document.getElementById('finEnlDesde') || {}).value || '');
        p.set('hasta', (document.getElementById('finEnlHasta') || {}).value || '');
        const gens = finCheckedGeneros('.fin-enl-gen');
        if (gens.length && gens.length < 4) p.set('generos', gens.join(','));
        const perf = (document.getElementById('finEnlPerfil') || {}).value || '';
        if (perf) p.set('perfil', perf);
        const sec = (document.getElementById('finEnlSector') || {}).value || '';
        if (sec) p.set('sector', sec);
        return p.toString();
    }

    function finDestroyCharts(store) {
        Object.keys(store).forEach(function(k) {
            if (store[k]) {
                store[k].destroy();
                store[k] = null;
            }
        });
    }

    function finObjToLabelsValues(obj, ordenPref) {
        const labels = [];
        const values = [];
        const keys = ordenPref ? ordenPref.filter(function(k) { return obj && (obj[k] || 0) > 0; }) : Object.keys(obj || {});
        const rest = Object.keys(obj || {}).filter(function(k) { return keys.indexOf(k) === -1; }).sort();
        keys.concat(rest).forEach(function(k) {
            const v = (obj && obj[k]) ? Number(obj[k]) : 0;
            if (v > 0 || !ordenPref) {
                labels.push(k);
                values.push(v);
            }
        });
        return { labels: labels, values: values };
    }

    function renderPieChart(canvasId, labels, values, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const filtered = [];
        for (let i = 0; i < labels.length; i++) {
            const val = Number(values[i] || 0);
            if (val > 0) filtered.push({ label: String(labels[i]), value: val });
        }
        if (!filtered.length) filtered.push({ label: 'Sin datos', value: 1 });
        const total = filtered.reduce(function(s, x) { return s + x.value; }, 0);
        store[key] = new Chart(el, {
            type: 'pie',
            data: {
                labels: filtered.map(function(x) { return x.label; }),
                datasets: [{
                    data: filtered.map(function(x) { return x.value; }),
                    backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6610f2', '#fd7e14', '#198754', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const v = Number(ctx.raw || 0);
                                const pct = total > 0 ? ((v / total) * 100).toFixed(1) : '0';
                                return ctx.label + ': ' + v + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function finRenderBarH(canvasId, labels, data, color, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const filteredL = [];
        const filteredD = [];
        for (let i = 0; i < labels.length; i++) {
            const v = Number(data[i] || 0);
            if (v > 0) {
                filteredL.push(labels[i]);
                filteredD.push(v);
            }
        }
        if (!filteredL.length) {
            filteredL.push('Sin datos');
            filteredD.push(0);
        }
        store[key] = new Chart(el, {
            type: 'bar',
            data: {
                labels: filteredL,
                datasets: [{ label: 'Cantidad', data: filteredD, backgroundColor: color || '#667eea' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function finRenderStackedBar(canvasId, payload, store, key) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (store[key]) store[key].destroy();
        const labels = payload.labels || [];
        const colors = ['#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6610f2', '#fd7e14'];
        const datasets = (payload.datasets || []).map(function(ds, idx) {
            return {
                label: ds.label,
                data: ds.data,
                backgroundColor: colors[idx % colors.length]
            };
        });
        store[key] = new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: false },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function loadFinEnlazadaDemografia() {
        const qs = finEnlQueryString();
        const ex = document.getElementById('finEnlExportXlsx');
        if (ex) ex.href = 'api/reportes_banco.php?action=exportar_excel_fin_enlazada&' + qs;
        const nota = document.getElementById('finEnlNotaApi');
        if (nota) nota.textContent = '';

        fetch('api/reportes_banco.php?action=reporte_fin_enlazada&' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    if (nota) nota.textContent = data.message || 'No se pudo cargar';
                    finDestroyCharts(finEnlCharts);
                    return;
                }

                const st = data.stats || {};
                document.getElementById('finEnlKpiN').textContent = String(st.n != null ? st.n : 0);
                const comp = (data.enlazada && data.enlazada.comparacion) ? data.enlazada.comparacion : {};
                document.getElementById('finEnlKpiPerfilOk').textContent =
                    String(comp.perfil_coincide != null ? comp.perfil_coincide : '—') +
                    ' / ' + String(comp.perfil_distinto != null ? comp.perfil_distinto : '—');
                document.getElementById('finEnlKpiGenOk').textContent =
                    String(comp.genero_coincide != null ? comp.genero_coincide : '—') +
                    ' / ' + String(comp.genero_distinto != null ? comp.genero_distinto : '—');
                const abonoProm = (data.abono && data.abono.abono_pct_promedio != null)
                    ? data.abono.abono_pct_promedio + '%'
                    : '—';
                document.getElementById('finEnlKpiAbono').textContent = abonoProm;

                finDestroyCharts(finEnlCharts);

                const ab = data.abono || {};
                const av = finObjToLabelsValues(ab.por_rango_abono_pct, ab.orden_rangos_abono || null);
                renderPieChart('finEnlChartAbono', av.labels, av.values, finEnlCharts, 'abono');

                const enl = data.enlazada || {};
                const pm = finObjToLabelsValues(enl.por_perfil_motus, ['Asalariado', 'Jubilado', 'Independiente']);
                renderPieChart('finEnlChartPerfilMotus', pm.labels, pm.values, finEnlCharts, 'pm');

                const og = data.orden_generos || ['Femenino', 'Masculino', 'Otro', 'Sin dato'];
                const gv = finObjToLabelsValues(data.por_genero, og);
                renderPieChart('finEnlChartGen', gv.labels, gv.values, finEnlCharts, 'gen');

                const oe = data.orden_rangos_edad || [];
                const ev = finObjToLabelsValues(data.por_rango_edad, oe);
                finRenderBarH('finEnlChartEdad', ev.labels, ev.values, '#6f42c1', finEnlCharts, 'edad');

                const ppub = finObjToLabelsValues(data.por_perfil_estimado, null);
                renderPieChart('finEnlChartPerfilPub', ppub.labels, ppub.values, finEnlCharts, 'ppub');

                const os = data.orden_rangos_salario || [];
                const sv = finObjToLabelsValues(data.por_rango_salario, os);
                finRenderBarH('finEnlChartSal', sv.labels, sv.values, '#198754', finEnlCharts, 'sal');

                finRenderStackedBar('finEnlChartCruceSal', data.cruce_salario_genero || { labels: [], datasets: [] }, finEnlCharts, 'cruceSal');

                const tbody = document.querySelector('#tabla-fin-enl tbody');
                const muestra = data.muestra || [];
                if (!tbody) return;
                if (!muestra.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Sin registros enlazados con estos filtros</td></tr>';
                    return;
                }
                let html = '';
                muestra.forEach(function(m) {
                    html += '<tr><td>' + escapeHtml(String(m.id || '')) + '</td>'
                        + '<td class="text-nowrap"><small>' + escapeHtml(String(m.fecha_creacion || '')) + '</small></td>'
                        + '<td>' + escapeHtml(String(m.cliente_nombre || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.solicitud_id || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.solicitud_estado || '')) + '</td>'
                        + '<td>' + escapeHtml(m.abono_pct_resuelto != null ? String(m.abono_pct_resuelto) + '%' : '—') + '</td>'
                        + '<td><small>' + escapeHtml(String(m.perfil_estimado || '')) + '</small></td>'
                        + '<td><small>' + escapeHtml(String(m.perfil_motus || '')) + '</small></td>'
                        + '<td>' + escapeHtml(String(m.genero_label || '')) + '</td>'
                        + '<td>' + escapeHtml(String(m.genero_motus || '')) + '</td></tr>';
                });
                tbody.innerHTML = html;
            })
            .catch(function() {
                const n2 = document.getElementById('finEnlNotaApi');
                if (n2) n2.textContent = 'Error de red o servidor';
            });
    }

    document.getElementById('btnFinEnlFiltrar').addEventListener('click', loadFinEnlazadaDemografia);
    loadFinEnlazadaDemografia();
})();
</script>
</body>
</html>
