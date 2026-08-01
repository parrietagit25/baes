/**
 * UI del reporte Predicciones (estancamiento, cierre, SLA banco).
 */
(function () {
    'use strict';

    let cachePred = null;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function badgeNivel(nivel) {
        const n = String(nivel || 'bajo');
        let cls = 'secondary';
        if (n === 'alto') cls = 'danger';
        else if (n === 'medio') cls = 'warning text-dark';
        return '<span class="badge bg-' + cls + '">' + esc(n) + '</span>';
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function renderKpis(k) {
        k = k || {};
        setText('predKpiAbiertas', k.abiertas_analizadas ?? 0);
        setText('predKpiAlto', k.riesgo_alto ?? 0);
        setText('predKpiMedio', k.riesgo_medio ?? 0);
        setText('predKpiBajo', k.riesgo_bajo ?? 0);
        setText('predKpiProb', (k.prob_cierre_promedio_pct != null ? k.prob_cierre_promedio_pct + '%' : '—'));
        setText('predKpiHist', (k.tasa_cierre_historica_pct != null ? k.tasa_cierre_historica_pct + '%' : '—'));
        setText('predKpiMuestra', k.muestra_historica_cerradas ?? 0);
        setText('predKpiSla', k.alertas_sla_banco ?? 0);
    }

    function renderEstancamiento(rows, filtro) {
        const tbody = document.querySelector('#tabla-pred-estancamiento tbody');
        if (!tbody) return;
        const q = (filtro || '').trim().toLowerCase();
        let html = '';
        (rows || []).forEach(function (r) {
            const blob = [
                r.solicitud_id, r.nombre_cliente, r.estado, r.sucursal, r.gestor_nombre, r.nivel
            ].join(' ').toLowerCase();
            if (q && blob.indexOf(q) === -1) return;
            const factores = Array.isArray(r.factores) ? r.factores.join('; ') : '';
            html += '<tr>'
                + '<td><a href="solicitudes.php?abrir_solicitud=' + encodeURIComponent(r.solicitud_id) + '">' + esc(r.solicitud_id) + '</a></td>'
                + '<td>' + esc(r.nombre_cliente) + '</td>'
                + '<td>' + esc(r.estado) + '</td>'
                + '<td>' + esc(r.sucursal) + '</td>'
                + '<td>' + esc(r.gestor_nombre) + '</td>'
                + '<td class="text-end">' + esc(r.horas_sin_avance) + '</td>'
                + '<td class="text-end">' + esc(r.dias_abierta) + '</td>'
                + '<td class="text-end fw-semibold">' + esc(r.score_estancamiento) + '</td>'
                + '<td>' + badgeNivel(r.nivel) + '</td>'
                + '<td class="small">' + esc(factores) + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html || '<tr><td colspan="10" class="text-muted text-center">Sin datos</td></tr>';
    }

    function renderCierre(rows, filtro) {
        const tbody = document.querySelector('#tabla-pred-cierre tbody');
        if (!tbody) return;
        const q = (filtro || '').trim().toLowerCase();
        let html = '';
        (rows || []).forEach(function (r) {
            const blob = [
                r.solicitud_id, r.nombre_cliente, r.estado, r.sucursal, r.abono_bucket
            ].join(' ').toLowerCase();
            if (q && blob.indexOf(q) === -1) return;
            html += '<tr>'
                + '<td><a href="solicitudes.php?abrir_solicitud=' + encodeURIComponent(r.solicitud_id) + '">' + esc(r.solicitud_id) + '</a></td>'
                + '<td>' + esc(r.nombre_cliente) + '</td>'
                + '<td>' + esc(r.estado) + '</td>'
                + '<td>' + esc(r.sucursal) + '</td>'
                + '<td class="text-end fw-semibold">' + esc(r.prob_cierre_pct) + '%</td>'
                + '<td>' + esc(r.abono_bucket) + '</td>'
                + '<td>' + (r.tiene_eval_positiva ? 'Sí' : 'No') + '</td>'
                + '<td class="text-end">' + esc(r.score_estancamiento) + '</td>'
                + '<td>' + badgeNivel(r.nivel_estancamiento) + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html || '<tr><td colspan="9" class="text-muted text-center">Sin datos</td></tr>';
    }

    function renderSla(sla) {
        sla = sla || {};
        const tbB = document.querySelector('#tabla-pred-sla-bancos tbody');
        const tbA = document.querySelector('#tabla-pred-sla-alertas tbody');
        if (tbB) {
            let html = '';
            (sla.bancos || []).forEach(function (b) {
                html += '<tr>'
                    + '<td>' + esc(b.banco_nombre) + '</td>'
                    + '<td class="text-end">' + esc(b.muestra) + '</td>'
                    + '<td class="text-end">' + (b.horas_mediana != null ? esc(b.horas_mediana) : '—') + '</td>'
                    + '<td class="text-end">' + (b.horas_promedio != null ? esc(b.horas_promedio) : '—') + '</td>'
                    + '<td class="text-end">' + (b.dias_mediana != null ? esc(b.dias_mediana) : '—') + '</td>'
                    + '<td class="text-end">' + esc(b.pendientes) + '</td>'
                    + '</tr>';
            });
            tbB.innerHTML = html || '<tr><td colspan="6" class="text-muted text-center">Sin datos</td></tr>';
        }
        if (tbA) {
            let html = '';
            (sla.pendiente_alerta || []).forEach(function (a) {
                html += '<tr>'
                    + '<td><a href="solicitudes.php?abrir_solicitud=' + encodeURIComponent(a.solicitud_id) + '">' + esc(a.solicitud_id) + '</a></td>'
                    + '<td>' + esc(a.banco_nombre) + '</td>'
                    + '<td class="text-end">' + esc(a.horas_transcurridas) + '</td>'
                    + '<td class="text-end">' + (a.horas_esperadas_mediana != null ? esc(a.horas_esperadas_mediana) : '—') + '</td>'
                    + '<td class="text-end">' + (a.ratio_vs_mediana != null ? esc(a.ratio_vs_mediana) : '—') + '</td>'
                    + '<td>' + badgeNivel(a.nivel) + '</td>'
                    + '</tr>';
            });
            tbA.innerHTML = html || '<tr><td colspan="6" class="text-muted text-center">Sin alertas</td></tr>';
        }
    }

    function applyFilters() {
        if (!cachePred) return;
        const fEst = document.getElementById('predFiltroEst');
        const fProb = document.getElementById('predFiltroProb');
        renderEstancamiento(cachePred.estancamiento_top || cachePred.estancamiento || [], fEst ? fEst.value : '');
        renderCierre(cachePred.probabilidad_cierre || [], fProb ? fProb.value : '');
    }

    window.loadReportePredicciones = function loadReportePredicciones() {
        const btn = document.getElementById('btnPredActualizar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cargando…';
        }
        fetch('api/reportes.php?action=reporte_predicciones')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    alert((data && data.message) || 'No se pudo cargar predicciones');
                    return;
                }
                cachePred = data;
                renderKpis(data.kpis);
                const nota = document.getElementById('predNotaMeta');
                if (nota && data.meta) {
                    nota.textContent = (data.meta.nota || '') + (data.meta.generado_en ? ' · Generado: ' + data.meta.generado_en : '');
                }
                applyFilters();
                renderSla(data.sla_bancos);
            })
            .catch(function () {
                alert('Error de red al cargar predicciones');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync me-1"></i>Actualizar';
                }
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btnPredActualizar');
        if (btn) btn.addEventListener('click', function () { loadReportePredicciones(); });
        const fEst = document.getElementById('predFiltroEst');
        const fProb = document.getElementById('predFiltroProb');
        if (fEst) fEst.addEventListener('input', applyFilters);
        if (fProb) fProb.addEventListener('input', applyFilters);
    });
})();
