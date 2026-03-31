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

$localConfigPath = __DIR__ . '/email.local.php';
if (file_exists($localConfigPath)) {
    return require $localConfigPath;
}

return [
    'resend_api_key' => getEnvOrDefault('RESEND_API_KEY', ''),
    'resend_base_url' => getEnvOrDefault('RESEND_BASE_URL', 'api.resend.com'),
    'from_email' => getEnvOrDefault('MAIL_FROM_EMAIL', 'onboarding@resend.dev'),
    'from_name' => getEnvOrDefault('MAIL_FROM_NAME', 'AutoMarket Seminuevos'),
    'reply_to_email' => getEnvOrDefault('MAIL_REPLY_TO', ''),
    'reply_to_name' => getEnvOrDefault('MAIL_REPLY_TO_NAME', 'AutoMarket - Soporte'),
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'AutoMarket Seminuevos',
];
