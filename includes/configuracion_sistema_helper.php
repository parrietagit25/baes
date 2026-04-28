<?php
/**
 * Lectura de claves en configuracion_sistema (migración: database/migracion_configuracion_sistema.sql).
 */

function motus_cargar_pdo_config(): void
{
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        return;
    }
    $path = __DIR__ . '/../config/database.php';
    if (is_file($path)) {
        require_once $path;
    }
}

/**
 * Si la tabla o la clave no existen, o hay error, se asume habilitado (comportamiento anterior).
 */
function motus_chatbot_habilitado(): bool
{
    return motus_config_bool('chatbot_habilitado', true, __FUNCTION__);
}

function motus_config_valor(string $clave, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }
    try {
        motus_cargar_pdo_config();
        global $pdo;
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $cache[$clave] = $default;
            return $cache[$clave];
        }
        $st = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $cache[$clave] = ($row && array_key_exists('valor', $row)) ? (string) $row['valor'] : $default;
        return $cache[$clave];
    } catch (Throwable $e) {
        error_log('motus_config_valor(' . $clave . '): ' . $e->getMessage());
        $cache[$clave] = $default;
        return $cache[$clave];
    }
}

function motus_config_bool(string $clave, bool $default = true, string $logContext = 'motus_config_bool'): bool
{
    $valor = motus_config_valor($clave, $default ? '1' : '0');
    if ($valor === null) {
        return $default;
    }
    $v = strtolower(trim($valor));
    return !in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true);
}

function motus_mantenimiento_activo(): bool
{
    return motus_config_bool('mantenimiento_activo', false, __FUNCTION__);
}

function motus_mantenimiento_mensaje(): string
{
    $msg = motus_config_valor('mantenimiento_mensaje', '');
    $msg = trim((string) $msg);
    if ($msg === '') {
        return 'En este momento estamos actualizando el sistema. Intente nuevamente en unos minutos.';
    }
    return $msg;
}

function motus_emitir_mantenimiento_html(?string $mensaje = null): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 900');
        header('Content-Type: text/html; charset=UTF-8');
    }
    $m = htmlspecialchars($mensaje ?? motus_mantenimiento_mensaje(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sistema en mantenimiento</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f5f7fb;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}.card{max-width:680px;background:#fff;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.08);padding:32px;text-align:center}.badge{display:inline-block;background:#f59e0b;color:#fff;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin-bottom:14px}h1{margin:0 0 12px;font-size:28px}p{margin:0;font-size:16px;line-height:1.5;color:#4b5563}</style></head><body><main class="card"><div class="badge">Mantenimiento</div><h1>Sistema temporalmente no disponible</h1><p>' . $m . '</p></main></body></html>';
}
