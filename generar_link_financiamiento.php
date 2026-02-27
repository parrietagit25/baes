<?php
/**
 * Generador de link para Solicitud de Financiamiento.
 * Página pública, sin login ni base de datos. El correo se codifica en el propio link.
 */
$mensaje = '';
$linkGenerado = '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($path && $path !== '\\') $baseUrl .= $path;
$baseUrl = rtrim($baseUrl, '/');
$formUrl = $baseUrl . '/solicitud_financiamiento.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_destino'])) {
    $email = trim($_POST['email_destino']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Ingrese un correo electrónico válido.';
    } else {
        $linkGenerado = $formUrl . '?e=' . rawurlencode(base64_encode($email));
        $mensaje = 'Link generado. Compártalo con el cliente. Al completar el formulario, recibirá por correo el PDF en: ' . $email;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar link - Solicitud de Financiamiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; min-height: 100vh; padding: 2rem 0; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); max-width: 560px; margin: 0 auto; }
        .link-box { background: #f0f4f8; border: 1px solid #dee2e6; border-radius: 12px; padding: 12px 16px; word-break: break-all; font-family: monospace; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="h3"><i class="fas fa-link me-2"></i>Generar link de Solicitud de Financiamiento</h1>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <p class="text-muted mb-4">
                    Ingrese el correo electrónico al que desea recibir el formulario completado (PDF). Se generará un link para enviar al cliente. Cuando el cliente llene y envíe el formulario, recibirá por correo el PDF con los datos y la firma.
                </p>
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="email_destino" class="form-label">Correo donde recibir el PDF *</label>
                        <input type="email" class="form-control form-control-lg" id="email_destino" name="email_destino" required placeholder="vendedor@ejemplo.com" value="<?php echo isset($_POST['email_destino']) ? htmlspecialchars($_POST['email_destino']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-plus me-2"></i>Generar link</button>
                </form>
                <?php if ($mensaje): ?>
                    <div class="alert <?php echo $linkGenerado ? 'alert-success' : 'alert-danger'; ?> mt-3 mb-0"><?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>
                <?php if ($linkGenerado): ?>
                    <div class="mt-4">
                        <label class="form-label">Link para el cliente (copie y comparta):</label>
                        <div class="link-box d-flex align-items-center gap-2 flex-wrap">
                            <input type="text" class="form-control border-0 bg-transparent p-0 flex-grow-1" id="linkInput" value="<?php echo htmlspecialchars($linkGenerado); ?>" readonly>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="copiarLink()"><i class="fas fa-copy me-1"></i>Copiar</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        function copiarLink() {
            var input = document.getElementById('linkInput');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(function() { alert('Link copiado al portapapeles.'); });
        }
    </script>
</body>
</html>
