<?php
/** Panel Rep. Sucursales — incluir desde reportes.php */
$panelSucVisible = ($submenu ?? '') === 'sucursales';
?>
<div id="panel-sucursales" class="report-panel" style="display: <?php echo $panelSucVisible ? 'block' : 'none'; ?>;">
    <div class="alert alert-secondary small mb-3">
        <strong>Siglas:</strong> CH, CV, TBM, VIS, BDC = agentes · SP-CH, SP-CV, SP-TBM, SP-VIS, SP-BDC = supervisores · SP-NN = supervisor nacional.
        El total del supervisor es la suma de solicitudes de los agentes de su sucursal.
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-0">Año</label>
                    <select id="sucFiltroAnio" class="form-select form-select-sm">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 4; $y--): ?>
                        <option value="<?php echo $y; ?>"<?php echo $y === (int) date('Y') ? ' selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="btnSucFiltrar"><i class="fas fa-sync me-1"></i>Actualizar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">Total solicitudes</div><div class="h5 mb-0" id="sucKpiTotal">0</div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">En <span id="sucAnioLabel"><?php echo date('Y'); ?></span></div><div class="h5 mb-0 text-primary" id="sucKpiAnio">0</div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">Agentes activos</div><div class="h5 mb-0" id="sucKpiAgentes">0</div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">Tasa aprobación</div><div class="h5 mb-0 text-success" id="sucKpiTasa">—</div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">Sucursal líder</div><div class="h6 mb-0" id="sucKpiLider">—</div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="text-muted small">Sin ejecutivo</div><div class="h5 mb-0 text-warning" id="sucKpiSinEv">0</div></div></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Volumen por sucursal</h6><div class="fin-chart-wrap"><canvas id="sucChartPieSucursal"></canvas></div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Por estado (global)</h6><div class="fin-chart-wrap"><canvas id="sucChartPieEstado"></canvas></div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><h6 class="mb-2">Top agentes</h6><div class="fin-chart-wrap"><canvas id="sucChartBarAgentes"></canvas></div></div></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-12"><div class="card"><div class="card-body"><h6 class="mb-2">Línea de tiempo — solicitudes por mes y sucursal (<span id="sucAnioLabel2"><?php echo date('Y'); ?></span>)</h6><div class="fin-chart-wrap" style="min-height:320px"><canvas id="sucChartLineMes"></canvas></div></div></div></div>
        <div class="col-12"><div class="card"><div class="card-body"><h6 class="mb-2">Por sucursal y estado (barras apiladas)</h6><div class="fin-chart-wrap" style="min-height:280px"><canvas id="sucChartBarSucursal"></canvas></div></div></div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Resumen por sucursal</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-reportes table-sm" id="tabla-sucursal-resumen">
                    <thead class="table-light"><tr><th>Cód.</th><th>Sucursal</th><?php foreach ($estadosCol as $e): ?><th class="text-center"><?php echo htmlspecialchars($e); ?></th><?php endforeach; ?><th class="text-center">Total</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Resumen por agente de ventas</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-reportes table-sm" id="tabla-sucursal-agentes">
                    <thead class="table-light"><tr><th>Agente</th><?php foreach ($estadosCol as $e): ?><th class="text-center"><?php echo htmlspecialchars($e); ?></th><?php endforeach; ?><th class="text-center">Total</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Resumen por supervisor (total de la sucursal)</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-reportes table-sm" id="tabla-sucursal-supervisores">
                    <thead class="table-light"><tr><th>Supervisor</th><th>Sucursal</th><?php foreach ($estadosCol as $e): ?><th class="text-center"><?php echo htmlspecialchars($e); ?></th><?php endforeach; ?><th class="text-center">Total</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
