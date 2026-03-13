<?php
/**
 * Prueba rápida: genera un PDF y lo envía por correo.
 * Uso: abre test_pdf_email.php en el navegador, escribe un correo y pulsa Enviar.
 * Requiere: config/email.local.php (SMTP o SendGrid) y composer (dompdf, phpmailer o sendgrid).
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
header('Content-Type: text/html; charset=utf-8');
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['email'])) {
    $email = trim($_POST['email'] ?? $_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo no válido.';
    } else {
        $pdfPath = null;
        try {
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                throw new RuntimeException('No existe vendor/autoload.php. Ejecuta en la raíz del proyecto: composer install');
            }
            require_once $autoload;
            if (!class_exists('Dompdf\Dompdf')) {
                throw new RuntimeException('Dompdf no está instalado. En la raíz del proyecto ejecuta: composer install (o en Docker: docker exec motus_php composer install)');
            }
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;padding:20px;} h1{color:#333;} table{border-collapse:collapse;} td,th{border:1px solid #ccc;padding:8px;}</style></head><body>';
            $html .= '<h1>PDF de prueba</h1><p>Generado el ' . date('d/m/Y H:i:s') . '</p>';
            $html .= '<table><tr><th>Campo</th><th>Valor</th></tr>';
            $html .= '<tr><td>Cliente</td><td>Juan Pérez Prueba</td></tr><tr><td>Cédula</td><td>8-123-4567</td></tr><tr><td>Vehículo</td><td>Toyota Corolla 2022</td></tr></table>';
            $html .= '<p style="margin-top:20px;">Este es un correo de prueba del sistema.</p></body></html>';

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfPath = sys_get_temp_dir() . '/test_pdf_' . uniqid('', true) . '.pdf';
            file_put_contents($pdfPath, $dompdf->output());

            $config = require __DIR__ . '/config/email.php';
            require_once __DIR__ . '/includes/EmailService.php';
            $emailService = new EmailService();
            $asunto = 'Prueba PDF y correo - ' . date('d/m/Y H:i');
            $cuerpo = '<p>Adjunto PDF de prueba.</p><p>Si recibes este correo, el envío está funcionando.</p>';
            $result = $emailService->enviarCorreo($email, $asunto, $cuerpo, '', strip_tags($cuerpo), [$pdfPath]);
            @unlink($pdfPath);

            if (!empty($result['success'])) {
                $mensaje = 'Correo enviado a ' . htmlspecialchars($email) . ' con el PDF adjunto.';
            } else {
                $error = $result['message'] ?? 'No se pudo enviar el correo.';
            }
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Prueba PDF y email</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 480px; margin: 40px auto; padding: 20px; }
    h1 { font-size: 1.25rem; }
    input[type="email"] { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; }
    button { padding: 10px 20px; background: #4ea1ff; color: #fff; border: 0; border-radius: 8px; cursor: pointer; }
    .ok { color: #0a0; margin-top: 12px; }
    .err { color: #c00; margin-top: 12px; }
  </style>
</head>
<body>
  <h1>Prueba: PDF + envío de correo</h1>
  <form method="post" action="">
    <label for="email">Correo de destino</label>
    <input type="email" id="email" name="email" required placeholder="tu@correo.com" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" />
    <button type="submit">Generar PDF y enviar</button>
  </form>
  <?php if ($mensaje): ?>
  <p class="ok"><?php echo $mensaje; ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
  <p class="err"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>
</body>
</html>
