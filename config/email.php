<?php
/**
 * Configuración de correo: solo Resend.
 *
 * Variables: .env en la raíz, variables de entorno del sistema, o config/email.local.php (opcional).
 */

$__emailEnvFile = __DIR__ . '/../.env';
if (is_file($__emailEnvFile) && is_readable($__emailEnvFile)) {
    $__lines = @file($__emailEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($__lines) {
        foreach ($__lines as $__line) {
            $__line = trim($__line);
            if ($__line === '' || strpos($__line, '#') === 0) {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $__line, $__m)) {
                $__key = $__m[1];
                $__val = trim($__m[2]);
                if (strpos($__val, '"') === 0 && substr($__val, -1) === '"') {
                    $__val = substr($__val, 1, -1);
                } elseif (strpos($__val, "'") === 0 && substr($__val, -1) === "'") {
                    $__val = substr($__val, 1, -1);
                }
                if (getenv($__key) === false) {
                    putenv("$__key=$__val");
                    $_ENV[$__key] = $__val;
                }
            }
        }
    }
}
unset($__emailEnvFile, $__lines, $__line, $__m, $__key, $__val);

if (!function_exists('getEnvOrDefault')) {
    function getEnvOrDefault($key, $default) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

/**
 * MAIL_* tiene prioridad; si está vacío se usan SENDGRID_* (legado cuando el correo era SendGrid).
 */
if (!function_exists('emailEnvPrimaryOrLegacy')) {
    function emailEnvPrimaryOrLegacy(string $primaryKey, string $legacyKey, string $default): string {
        $v = getenv($primaryKey);
        if ($v !== false && trim((string) $v) !== '') {
            return trim((string) $v);
        }
        $legacy = getenv($legacyKey);
        if ($legacy !== false && trim((string) $legacy) !== '') {
            return trim((string) $legacy);
        }
        return $default;
    }
}

$localConfigPath = __DIR__ . '/email.local.php';
if (file_exists($localConfigPath)) {
    return require $localConfigPath;
}

$resendBase = getEnvOrDefault('RESEND_BASE_URL', 'api.resend.com');
$resendBase = preg_replace('#^https?://#i', '', rtrim((string) $resendBase, '/'));

return [
    'resend_api_key' => getEnvOrDefault('RESEND_API_KEY', ''),
    'resend_base_url' => $resendBase !== '' ? $resendBase : 'api.resend.com',
    'from_email' => emailEnvPrimaryOrLegacy('MAIL_FROM_EMAIL', 'SENDGRID_FROM_EMAIL', 'onboarding@resend.dev'),
    'from_name' => emailEnvPrimaryOrLegacy('MAIL_FROM_NAME', 'SENDGRID_FROM_NAME', 'AutoMarket Seminuevos'),
    'reply_to_email' => emailEnvPrimaryOrLegacy('MAIL_REPLY_TO', 'SENDGRID_REPLY_TO', ''),
    'reply_to_name' => emailEnvPrimaryOrLegacy('MAIL_REPLY_TO_NAME', 'SENDGRID_REPLY_TO_NAME', 'AutoMarket - Soporte'),
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'AutoMarket Seminuevos',
];
