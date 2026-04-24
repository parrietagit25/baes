<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';
require_once 'includes/encuestas_satisfaccion_data.php';

if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [], true)) {
    header('Location: dashboard.php');
    exit();
}

$tab = $_GET['tab'] ?? 'vendedor';
if (!in_array($tab, ['vendedor', 'gestor'], true)) {
    $tab = 'vendedor';
}

function encuestas_cargar_filas(PDO $pdo, string $table): array
{
    $sql = "SELECT id, usuario_id, nombre_completo, cargo,
        puntuacion_1, puntuacion_2, puntuacion_3, puntuacion_4, puntuacion_5, recomendaciones, creado_en
        FROM `{$table}` ORDER BY creado_en DESC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$errV = null;
$errG = null;
$rowsV = [];
$rowsG = [];
try {
    $rowsV = encuestas_cargar_filas($pdo, 'encuesta_formulario_publico_vendedor');
} catch (Throwable $e) {
    $errV = $e->getMessage();
}
try {
    $rowsG = encuestas_cargar_filas($pdo, 'encuesta_proceso_gestor');
} catch (Throwable $e) {
    $errG = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de encuestas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .enc-h { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: #fff; border-radius: 15px; padding: 1.5rem; margin-bottom: 1.25rem; }
        .table-enc th { font-size: 0.8rem; white-space: nowrap; }
        .table-enc td { font-size: 0.85rem; vertical-align: middle; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="enc-h">
                    <h2 class="h4 mb-1"><i class="fas fa-poll me-2"></i>Resultados de encuestas</h2>
                    <p class="mb-0 small opacity-90">Los formularios son <strong>públicos</strong> (sin inicio de sesión). Aplique <code>database/migracion_encuestas_satisfaccion.sql</code> si aún no existen las tablas. Enlaces para compartir: <code>encuesta_formulario_publico_vendedores.php</code> y <code>encuesta_proceso_gestor.php</code>.</p>
                </div>

                <ul class="nav nav-pills mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $tab === 'vendedor' ? 'active' : ''; ?>" href="?tab=vendedor">Formulario público (vendedores)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $tab === 'gestor' ? 'active' : ''; ?>" href="?tab=gestor">Proceso (gestores)</a>
                    </li>
                </ul>

                <?php if ($tab === 'vendedor'): ?>
                    <?php if ($errV): ?>
                        <div class="alert alert-warning">No se pudo leer la tabla de vendedores: <code><?php echo htmlspecialchars($errV); ?></code></div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-enc table-striped table-hover mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Nombre</th>
                                            <th>Cargo</th>
                                            <?php foreach ($ENCUESTA_VENDEDOR_PREGUNTAS as $i => $t): ?>
                                                <th title="<?php echo htmlspecialchars($t); ?>">P<?php echo (int) $i; ?></th>
                                            <?php endforeach; ?>
                                            <th>Prom.</th>
                                            <th>Recomendaciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($rowsV as $r): ?>
                                            <?php
                                            $p = (float) ($r['puntuacion_1'] + $r['puntuacion_2'] + $r['puntuacion_3'] + $r['puntuacion_4'] + $r['puntuacion_5']) / 5.0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['creado_en']); ?></td>
                                                <td><?php echo htmlspecialchars($r['nombre_completo']); ?></td>
                                                <td><?php echo htmlspecialchars($r['cargo']); ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_1']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_2']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_3']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_4']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_5']; ?></td>
                                                <td class="text-center fw-bold"><?php echo number_format($p, 1, ',', ''); ?></td>
                                                <td class="text-break small"><?php echo $r['recomendaciones'] === null || $r['recomendaciones'] === '' ? '—' : nl2br(htmlspecialchars($r['recomendaciones'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="p-3 mb-0 text-muted small">Total: <?php echo count($rowsV); ?> respuesta(s).</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($errG): ?>
                        <div class="alert alert-warning">No se pudo leer la tabla de gestores: <code><?php echo htmlspecialchars($errG); ?></code></div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-enc table-striped table-hover mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Nombre</th>
                                            <th>Cargo</th>
                                            <?php foreach ($ENCUESTA_GESTOR_PREGUNTAS as $i => $t): ?>
                                                <th title="<?php echo htmlspecialchars($t); ?>">P<?php echo (int) $i; ?></th>
                                            <?php endforeach; ?>
                                            <th>Prom.</th>
                                            <th>Recomendaciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($rowsG as $r): ?>
                                            <?php
                                            $p = (float) ($r['puntuacion_1'] + $r['puntuacion_2'] + $r['puntuacion_3'] + $r['puntuacion_4'] + $r['puntuacion_5']) / 5.0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['creado_en']); ?></td>
                                                <td><?php echo htmlspecialchars($r['nombre_completo']); ?></td>
                                                <td><?php echo htmlspecialchars($r['cargo']); ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_1']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_2']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_3']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_4']; ?></td>
                                                <td class="text-center"><?php echo (int) $r['puntuacion_5']; ?></td>
                                                <td class="text-center fw-bold"><?php echo number_format($p, 1, ',', ''); ?></td>
                                                <td class="text-break small"><?php echo $r['recomendaciones'] === null || $r['recomendaciones'] === '' ? '—' : nl2br(htmlspecialchars($r['recomendaciones'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="p-3 mb-0 text-muted small">Total: <?php echo count($rowsG); ?> respuesta(s).</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
