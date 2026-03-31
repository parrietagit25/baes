<?php
/**
 * Prueba de envío con Resend (EmailService).
 *
 * Uso: /test_email.php?to=correo@dominio.com
 * Compatible: /test_sendgrid.php redirige aquí.
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

$to = isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)
    ? $_GET['to']
    : null;

if (!$to) {
    echo "Uso: ?to=correo@dominio.com\n\n";
    echo "Variables en .env (o entorno Docker):\n";
    echo "  RESEND_API_KEY=re_...\n";
    echo "  RESEND_BASE_URL=api.resend.com\n";
    echo "  MAIL_FROM_EMAIL=onboarding@resend.dev\n";
    echo "  MAIL_FROM_NAME=AutoMarket Seminuevos\n";
    echo "  MAIL_REPLY_TO= (opcional)\n";
    exit;
}

require_once __DIR__ . '/includes/EmailService.php';

$cfg = require __DIR__ . '/config/email.php';
$key = (string) ($cfg['resend_api_key'] ?? '');
echo "Proveedor: Resend\n";
echo "RESEND_API_KEY: " . (strlen($key) > 10 ? 'sí (longitud ' . strlen($key) . ')' : 'NO configurada') . "\n";
echo "MAIL_FROM_EMAIL: " . ($cfg['from_email'] ?? '') . "\n\n";

try {
    echo "Enviando...\n";
    flush();
    set_time_limit(60);
    $emailService = new EmailService();
    $subject = 'Prueba correo Resend - Motus / Financiamiento';
    $bodyHtml = '<p>Correo de <strong>prueba</strong> desde test_email.php (Resend).</p>';
    $bodyText = "Correo de prueba desde test_email.php.\n";
    $result = $emailService->enviarCorreo($to, $subject, $bodyHtml, '', $bodyText, []);
    echo "Resultado:\n";
    var_export($result);
    echo "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
