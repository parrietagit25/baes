<?php
/**
 * Página pública: subir adjuntos con token (24 h, reutilizable).
 * Subida por POST clásico (evita 403 de Cloudflare en fetch/XHR multipart).
 * Enlace: .../financiamiento/solicitar_adjuntos.php?t=TOKEN
 */
require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    motus_emitir_mantenimiento_html();
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/financiamiento_adjuntos_public_lib.php';

$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['t']) ? trim((string) $_POST['t']) : '';
} else {
    $token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
}

$alertaTipo = '';
$alertaMsg = '';
if (isset($_GET['ok']) && $_GET['ok'] === '1') {
    $alertaTipo = 'ok';
    $alertaMsg = isset($_GET['m']) ? (string) $_GET['m'] : 'Archivos subidos correctamente.';
} elseif (isset($_GET['err']) && $_GET['err'] === '1') {
    $alertaTipo = 'err';
    $alertaMsg = isset($_GET['m']) ? (string) $_GET['m'] : 'No se pudo subir.';
}

$ctx = null;
$errorToken = '';
if ($token === '') {
    $errorToken = 'Enlace incompleto.';
} elseif (!isset($pdo) || !($pdo instanceof PDO)) {
    $errorToken = 'Servicio no disponible.';
} elseif (!finAdjTok_tablaExiste($pdo)) {
    $errorToken = 'Función no disponible (falta migración de tokens).';
} else {
    $ctx = finAdjTok_resolver($pdo, $token);
    if ($ctx === null) {
        $errorToken = 'El enlace no es válido, fue reemplazado o ya caducó.';
    }
}

// POST clásico: procesar y redirigir (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ctx !== null) {
    $resultado = finAdjTok_procesarSubida($pdo, $ctx, finAdjTok_filesFromRequest());
    $q = 't=' . rawurlencode($token);
    if (!empty($resultado['success'])) {
        $q .= '&ok=1&m=' . rawurlencode((string) ($resultado['message'] ?? 'Listo.'));
    } else {
        $q .= '&err=1&m=' . rawurlencode((string) ($resultado['message'] ?? 'Error al subir.'));
    }
    header('Location: solicitar_adjuntos.php?' . $q);
    exit;
}

$adjuntos = [];
if ($ctx !== null) {
    $adjuntos = finAdjTok_listarAdjuntos($pdo, $ctx['fr_id']);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Subir adjuntos - AutoMarket</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #0b1220; color: #eaf0ff; min-height: 100vh; padding: 20px; }
    .card-adj { background: #0f1b33; border: 1px solid rgba(255,255,255,.12); border-radius: 16px; max-width: 640px; margin: 0 auto; padding: 24px; }
    .list-adj .item { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
    .text-muted-soft { color: rgba(234,240,255,.65) !important; }
  </style>
</head>
<body>
  <div class="card-adj">
    <h1 class="h5 mb-2"><i class="fas fa-paperclip me-2"></i>Subir documentos</h1>
    <?php if ($errorToken !== ''): ?>
      <p class="text-muted-soft small mb-3">No se pudo abrir el enlace.</p>
      <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($errorToken, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
      <p class="text-muted-soft small mb-3">
        Hola, <?php echo htmlspecialchars($ctx['cliente_nombre'] !== '' ? $ctx['cliente_nombre'] : 'cliente', ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <?php if ($alertaMsg !== ''): ?>
        <div class="alert <?php echo $alertaTipo === 'ok' ? 'alert-success' : 'alert-danger'; ?>" role="alert">
          <?php echo htmlspecialchars($alertaMsg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <p class="small mb-3">Puede subir archivos (PDF, imágenes, Word/Excel; máx. 10 MB c/u). El enlace es válido hasta la fecha indicada; al volver a abrirlo verá los adjuntos que ya cargó.</p>

      <h2 class="h6">Adjuntos ya cargados</h2>
      <div class="list-adj mb-3">
        <?php if (!$adjuntos): ?>
          <p class="text-muted-soft small mb-0">Ninguno aún.</p>
        <?php else: ?>
          <?php foreach ($adjuntos as $a): ?>
            <?php
              $nombre = $a['nombre_original'] !== '' ? $a['nombre_original'] : ('Adjunto #' . $a['id']);
              $parts = array_filter([
                  $a['tipo_archivo'] ?? '',
                  !empty($a['tamano_archivo']) ? (round(((int) $a['tamano_archivo']) / 1024, 1) . ' KB') : '',
                  $a['fecha_subida'] ?? '',
              ], static function ($v) { return $v !== '' && $v !== null; });
            ?>
            <div class="item">
              <div><i class="fas fa-file me-2"></i><strong><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <?php if ($parts): ?>
                <div class="small text-muted-soft mt-1"><?php echo htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" action="solicitar_adjuntos.php" class="mb-2">
        <input type="hidden" name="t" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <label class="form-label small" for="inputFiles">Seleccionar archivos</label>
        <input type="file" class="form-control form-control-sm mb-3" id="inputFiles" name="adjuntos[]" multiple required
               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,image/*,application/pdf">
        <button type="submit" class="btn btn-success" id="btnSubir">
          <i class="fas fa-cloud-upload-alt me-1"></i>Subir adjuntos
        </button>
      </form>
      <p class="small text-muted-soft mb-0">
        Enlace válido hasta: <?php echo htmlspecialchars((string) $ctx['expires_at'], ENT_QUOTES, 'UTF-8'); ?>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
