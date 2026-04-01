<?php
/**
 * Configuración de correo: solo Resend.
 *
 * Variables: .env en la raíz, variables de entorno del sistema, o config/email.local.php (opcional).
 */

$__emailEnvFile = __DIR__ . '/../.env';
/** Claves que el archivo .env debe poder fijar siempre (Docker a veces inyecta MAIL_FROM_EMAIL=onboarding@resend.dev). */
$__emailEnvKeysFromFileAlways = [
    'RESEND_API_KEY', 'RESEND_BASE_URL',
    'MAIL_FROM_EMAIL', 'MAIL_FROM_NAME', 'MAIL_REPLY_TO', 'MAIL_REPLY_TO_NAME',
    'SENDGRID_FROM_EMAIL', 'SENDGRID_FROM_NAME', 'SENDGRID_REPLY_TO', 'SENDGRID_REPLY_TO_NAME',
    'MAIL_SHOW_APP_LINK_IN_EMAILS',
];
if (is_file($__emailEnvFile) && is_readable($__emailEnvFile)) {
    $__lines = @file($__emailEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $__parsed = [];
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
                $__parsed[$__key] = $__val;
            }
        }
    }
    foreach ($__parsed as $__key => $__val) {
        if (in_array($__key, $__emailEnvKeysFromFileAlways, true)) {
            putenv("$__key=$__val");
            $_ENV[$__key] = $__val;
        } elseif (getenv($__key) === false) {
            putenv("$__key=$__val");
            $_ENV[$__key] = $__val;
        }
    }
    unset($__parsed);
}
unset($__emailEnvFile, $__lines, $__line, $__m, $__key, $__val, $__emailEnvKeysFromFileAlways);

if (!function_exists('getEnvOrDefault')) {
    function getEnvOrDefault($key, $default) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

/**
 * MAIL_* tiene prioridad; si está vacío se usan SENDGRID_* (legado cuando el correo era SendGrid).
 * Si solo Docker dejó MAIL_FROM_EMAIL=onboarding@resend.dev pero SENDGRID_* trae el dominio verificado, usa el legado.
 */
if (!function_exists('emailEnvPrimaryOrLegacy')) {
    function emailEnvPrimaryOrLegacy(string $primaryKey, string $legacyKey, string $default, bool $forFromEmail = false): string {
        $primaryT = '';
        $v = getenv($primaryKey);
        if ($v !== false && trim((string) $v) !== '') {
            $primaryT = trim((string) $v);
        }
        $legacyT = '';
        $legacy = getenv($legacyKey);
        if ($legacy !== false && trim((string) $legacy) !== '') {
            $legacyT = trim((string) $legacy);
        }
        if ($forFromEmail && $primaryT !== '' && strcasecmp($primaryT, 'onboarding@resend.dev') === 0 && $legacyT !== '') {
            $primaryT = '';
        }
        if ($primaryT !== '') {
            return $primaryT;
        }
        if ($legacyT !== '') {
            return $legacyT;
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
    'from_email' => emailEnvPrimaryOrLegacy('MAIL_FROM_EMAIL', 'SENDGRID_FROM_EMAIL', 'onboarding@resend.dev', true),
    'from_name' => emailEnvPrimaryOrLegacy('MAIL_FROM_NAME', 'SENDGRID_FROM_NAME', 'AutoMarket Seminuevos'),
    'reply_to_email' => emailEnvPrimaryOrLegacy('MAIL_REPLY_TO', 'SENDGRID_REPLY_TO', ''),
    'reply_to_name' => emailEnvPrimaryOrLegacy('MAIL_REPLY_TO_NAME', 'SENDGRID_REPLY_TO_NAME', 'AutoMarket - Soporte'),
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'AutoMarket Seminuevos',
    // Resumen a bancos: botón "Ver solicitud en MOTUS" (0/false por defecto)
    'mail_show_app_link_in_emails' => filter_var(
        getEnvOrDefault('MAIL_SHOW_APP_LINK_IN_EMAILS', '0'),
        FILTER_VALIDATE_BOOLEAN
    ),
];
