<?php
/**
 * Prueba simple de envío de correo usando EmailService (SendGrid/SMTP).
 *
 * Uso en navegador:
 *   - /test_sendgrid.php?to=tu_correo@dominio.com
 *
 * El script usa la misma configuración que el sistema (config/email.php),
 * por lo que debe existir la API Key de SendGrid (o SMTP) ya configurada.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/EmailService.php';

$to = isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)
    ? $_GET['to']
    : null;

if (!$to) {
    echo "Falta el parámetro ?to=correo@dominio.com\n";
    exit;
}

// Diagnóstico: ¿PHP ve la API Key?
$key = getenv('SENDGRID_API_KEY');
if ($key === false || $key === '') {
    if (file_exists(__DIR__ . '/config/email.local.php')) {
        $c = require __DIR__ . '/config/email.local.php';
        $key = is_array($c) ? ($c['sendgrid_api_key'] ?? '') : '';
    }
}
echo "API Key: " . (strlen($key) > 10 ? "Sí (longitud " . strlen($key) . ")" : "NO - añade SENDGRID_API_KEY al .env y reinicia el contenedor") . "\n\n";

try {
    $emailService = new EmailService();
    $subject = 'Prueba SendGrid - Motus / Financiamiento';
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

