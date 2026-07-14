<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';

$feriaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($feriaId <= 0) {
    header('Location: ferias.php');
    exit();
}

$feriaNombre = 'Feria #' . $feriaId;
try {
    $stmt = $pdo->prepare('SELECT nombre, fecha_inicio, fecha_fin, lugar FROM ferias WHERE id = ?');
    $stmt->execute([$feriaId]);
    $feriaRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($feriaRow) {
        $feriaNombre = $feriaRow['nombre'];
    } else {
        header('Location: ferias.php');
        exit();
    }
} catch (PDOException $e) {
    // Tabla aún no migrada: la API avisará
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Feria - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .stats-card { color: white; border-radius: 12px; padding: 14px 12px; margin-bottom: 12px; text-align: center; }
        .stats-number { font-size: 1.75rem; font-weight: bold; line-height: 1.1; }
        .stats-label { font-size: 0.8rem; opacity: 0.95; margin-top: 4px; }
        .badge-estado { font-size: 0.8em; padding: 5px 8px; }
        .estado-nueva { background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%); }
        .estado-revision { background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%); color: #2d3436; }
        .estado-aprobada { background: linear-gradient(135deg, #00b894 0%, #55efc4 100%); color: #2d3436; }
        .estado-rechazada { background: linear-gradient(135deg, #d63031 0%, #ff7675 100%); }
        .estado-completada { background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); }
        .estado-sin-asoc { background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%); }
        .cliente-tel { font-size: 0.8rem; color: #6c757d; }
        .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #00b894; display: inline-block; margin-right: 6px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        #panelTable td { vertical-align: middle; font-size: 0.9rem; }
        .btn-ojo { padding: 0.25rem 0.5rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div>
                        <a href="ferias.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i>Volver a Ferias</a>
                        <h2 class="mb-1 mt-1"><i class="fas fa-th-large me-2"></i><?php echo htmlspecialchars($feriaNombre); ?></h2>
                        <p class="text-muted mb-0" id="feriaMeta">
                            <?php
                            if (!empty($feriaRow)) {
                                $fi = $feriaRow['fecha_inicio'] ?? '';
                                $ff = $feriaRow['fecha_fin'] ?? '';
                                echo htmlspecialchars(($fi && $ff) ? ($fi . ' — ' . $ff) : '');
                                if (!empty($feriaRow['lugar'])) {
                                    echo ' · ' . htmlspecialchars($feriaRow['lugar']);
                                }
                            }
                            ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success bg-opacity-75"><span class="live-dot"></span>En vivo · cada 3 s</span>
                        <div class="small text-muted mt-1" id="panelLastUpdate">—</div>
                    </div>
                </div>

                <div class="row g-2 mb-3" id="statsCards">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);">
                            <div class="stats-number" id="stat_total">0</div>
                            <div class="stats-label">Total</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%);">
                            <div class="stats-number" id="stat_sin_solicitud">0</div>
                            <div class="stats-label">Sin solicitud asociada</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);">
                            <div class="stats-number" id="stat_con_solicitud">0</div>
                            <div class="stats-label">Con solicitud</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%); color: #2d3436;">
                            <div class="stats-number" id="stat_en_revision_banco">0</div>
                            <div class="stats-label">En revisión banco</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #00b894 0%, #55efc4 100%); color: #2d3436;">
                            <div class="stats-number" id="stat_aprobadas">0</div>
                            <div class="stats-label">Aprobadas</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stats-card" style="background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);">
                            <div class="stats-number" id="stat_completadas">0</div>
                            <div class="stats-label">Completadas</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <p class="small text-muted mb-2"><i class="fas fa-eye me-1"></i>Solo lectura. Use el ojo para abrir el registro en MOTUS.</p>
                        <div class="table-responsive">
                            <table id="panelTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Solicitud crédito?</th>
                                        <th>Cliente</th>
                                        <th>Vehículo</th>
                                        <th>Vendedor</th>
                                        <th>Gestor</th>
                                        <th>Bancos asignados</th>
                                        <th>Respuestas bancos</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="9" class="text-center text-muted py-4">Cargando panel...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.MOTUS_FERIA_ID = <?php echo (int) $feriaId; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/feria_panel.js?v=<?php echo file_exists(__DIR__ . '/js/feria_panel.js') ? filemtime(__DIR__ . '/js/feria_panel.js') : time(); ?>"></script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
