<?php
/**
 * Encuesta pública: proceso y sistema (gestor). No requiere inicio de sesión.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/encuestas_satisfaccion_data.php';
require_once __DIR__ . '/includes/encuestas_satisfaccion_guardar.php';

$mensajeExito = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $e = encuestas_guardar_gestor($pdo, null);
    if ($e === null) {
        $mensajeExito = true;
    } else {
        $error = $e;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta — Proceso (gestor)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #e9ecef; min-height: 100vh; }
        .enc-wrap { max-width: 48rem; margin: 0 auto; }
        .enc-top { background: #fff; border-bottom: 1px solid #dee2e6; }
        .enc-head { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: #fff; border-radius: 15px; padding: 1.5rem; margin-bottom: 1.25rem; }
        .preg-block { background: #fff; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .enc-puntuacion .btn-check:checked + .btn { background: linear-gradient(180deg, #e2d4f3, #c9b3e6); border-color: #6f42c1; color: #1a1a1a; font-weight: 600; }
        .enc-puntuacion { flex-wrap: wrap; gap: 0.25rem; }
        .enc-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="enc-top py-3">
        <div class="enc-wrap px-3">
            <a href="index.php" class="text-decoration-none text-body"><i class="fas fa-arrow-left me-1"></i> Volver al inicio</a>
        </div>
    </div>
    <div class="container enc-wrap py-4">
        <div class="enc-head">
            <h1 class="h4 mb-1"><i class="fas fa-star me-2"></i>Encuesta: proceso y sistema (gestor)</h1>
            <p class="mb-0 small opacity-90">Cinco aspectos con puntuación de 1 a 5 y un espacio para recomendaciones al final.</p>
        </div>

        <?php if ($mensajeExito): ?>
            <div class="alert alert-success shadow-sm">
                <i class="fas fa-check-circle me-2"></i>Gracias, su respuesta fue guardada correctamente.
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="encuesta" action="" autocomplete="on">
                <p class="enc-hp" aria-hidden="true">
                    <label for="enc_honeypot">No rellenar</label>
                    <input type="text" name="enc_honeypot" id="enc_honeypot" value="" tabindex="-1" autocomplete="off">
                </p>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nombre_completo" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required maxlength="200" value="<?php echo htmlspecialchars((string) ($_POST['nombre_completo'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="cargo" class="form-label">Cargo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cargo" name="cargo" required maxlength="200" value="<?php echo htmlspecialchars((string) ($_POST['cargo'] ?? '')); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <?php foreach ($ENCUESTA_GESTOR_PREGUNTAS as $num => $texto): ?>
                <div class="preg-block">
                    <p class="fw-semibold mb-2"><?php echo (int) $num; ?>. <?php echo htmlspecialchars($texto); ?></p>
                    <p class="small text-muted mb-2">1 = bajo &nbsp;·&nbsp; 5 = excelente</p>
                    <div class="btn-group enc-puntuacion" role="group" aria-label="Puntuación de la pregunta <?php echo (int) $num; ?>">
                        <?php
                        for ($e = 1; $e <= 5; $e++) {
                            $id = 'p' . $num . '_s' . $e;
                            $sel = (isset($_POST['p' . $num]) && (int) $_POST['p' . $num] === $e) ? ' checked' : '';
                            $req = ($num === 1 && $e === 1) ? ' required' : '';
                            echo '<input type="radio" class="btn-check" name="p' . (int) $num . '" value="' . $e . '" id="' . htmlspecialchars($id) . '" autocomplete="off"' . $sel . $req . '>';
                            echo '<label class="btn btn-outline-secondary" for="' . htmlspecialchars($id) . '">';
                            for ($i = 0; $i < $e; $i++) {
                                echo '<i class="fas fa-star text-warning" aria-hidden="true"></i>';
                            }
                            echo ' <span class="visually-hidden">(' . $e . ' de 5 estrellas)</span></label>';
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="preg-block">
                    <label for="recomendaciones" class="form-label fw-semibold">Recomendaciones o comentarios (opcional)</label>
                    <textarea class="form-control" name="recomendaciones" id="recomendaciones" rows="4" maxlength="10000" placeholder="Sugerencias de mejora, bloqueos habituales, formación, etc."><?php echo htmlspecialchars((string) ($_POST['recomendaciones'] ?? '')); ?></textarea>
                </div>

                <button type="submit" class="btn text-white btn-lg w-100 w-sm-auto" style="background: #6f42c1;">
                    <i class="fas fa-paper-plane me-2"></i>Enviar encuesta
                </button>
            </form>
        <?php endif; ?>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
