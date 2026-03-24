<?php
/**
 * Prueba simple de envío de correo usando EmailService (SendGrid o SMTP Outlook).
 *
 * Uso en navegador:
 *   - /test_sendgrid.php?to=tu_correo@dominio.com
 *
 * Variables en .env (raíz del proyecto, junto a composer.json): véase comentario abajo.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level()) {
    ob_end_flush();
}

require_once __DIR__ . '/includes/EmailService.php';

$to = isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)
    ? $_GET['to']
    : null;

if (!$to) {
    echo "Falta el parámetro ?to=correo@dominio.com\n\n";
    echo "SMTP (Microsoft 365 / Outlook) en .env en la raíz del proyecto:\n";
    echo "  EMAIL_DRIVER=smtp\n";
    echo "  SMTP_HOST=smtp.office365.com\n";
    echo "  SMTP_PORT=587\n";
    echo "  SMTP_USER=notificaciones@tu-dominio.com\n";
    echo "  SMTP_PASS=\"tu_contraseña_con_caracteres_especiales\"\n";
    echo "  SMTP_SECURE=tls\n";
    echo "  SENDGRID_FROM_EMAIL=notificaciones@tu-dominio.com\n";
    echo "  (el remitente debe coincidir con la cuenta SMTP en muchos servidores)\n";
    exit;
}

$cfg = require __DIR__ . '/config/email.php';
$driver = $cfg['driver'] ?? 'sendgrid';
$smtpOk = ($cfg['smtp_host'] ?? '') !== '' && ($cfg['smtp_user'] ?? '') !== '' && ($cfg['smtp_pass'] ?? '') !== '';
$usaSmtp = ($driver === 'smtp' || ($cfg['smtp_host'] ?? '') !== '') && $smtpOk;

echo "Modo: " . ($usaSmtp ? "SMTP ({$cfg['smtp_host']})" : "SendGrid API") . "\n";
if ($usaSmtp) {
    $tout = (int) ($cfg['smtp_timeout'] ?? 25);
    echo "SMTP_USER: " . ($cfg['smtp_user'] ?? '') . "\n";
    echo "SMTP_PASS: " . (strlen($cfg['smtp_pass'] ?? '') > 0 ? "sí (configurada)" : "NO") . "\n";
    echo "SMTP_TIMEOUT: {$tout}s (evita 504 de Cloudflare si el host bloquea el puerto 587)\n";
} else {
    $key = $cfg['sendgrid_api_key'] ?? '';
    echo "SENDGRID_API_KEY: " . (strlen($key) > 10 ? "sí (longitud " . strlen($key) . ")" : "NO - define SMTP_* en .env o SENDGRID_API_KEY") . "\n";
}
echo "\n";

try {
    echo "Conectando y enviando...\n";
    flush();

    set_time_limit(120);
    $emailService = new EmailService();
    $subject = 'Prueba correo (SMTP/SendGrid) - Motus / Financiamiento';
    $bodyHtml = '<p>Este es un correo de <strong>prueba</strong> enviado desde test_sendgrid.php.</p>'
        . '<p>Si recibes este mensaje, la configuración de correo está funcionando.</p>';
    $bodyText = "Este es un correo de PRUEBA enviado desde test_sendgrid.php.\n"
        . "Si recibes este mensaje, la configuración de correo está funcionando.\n";

    $result = $emailService->enviarCorreo($to, $subject, $bodyHtml, '', $bodyText, []);

    echo "Resultado enviarCorreo:\n";
    var_export($result);
    echo "\n";
} catch (Throwable $e) {
    echo "ERROR al enviar correo:\n";
    echo $e->getMessage() . "\n";
}

if ($usaSmtp) {
    echo "\n--- Si ves \"SMTP code: 110\" o \"Connection timed out\" ---\n";
    echo "No es la contraseña: el servidor web NO llega al host SMTP (red/firewall).\n\n";
    echo "1) Microsoft 365 (correo @grupopcr.com.pa): prueba en .env:\n";
    echo "   SMTP_HOST=smtp.office365.com\n";
    echo "   SMTP_PORT=587\n";
    echo "   SMTP_SECURE=tls\n\n";
    echo "2) Si 587 sigue en timeout, prueba SSL en otro puerto (algunos hosts bloquean 587):\n";
    echo "   SMTP_HOST=smtp.office365.com\n";
    echo "   SMTP_PORT=465\n";
    echo "   SMTP_SECURE=ssl\n\n";
    echo "3) Pide al proveedor del hosting que permita salida TCP a Internet en 587 y/o 465\n";
    echo "   (a los endpoints SMTP de Microsoft). Sin eso, SMTP directo no funcionará.\n\n";
    echo "4) Alternativa: servicio de relay (SendGrid, SES, etc.) cuando el datacenter bloquea SMTP.\n";
}

