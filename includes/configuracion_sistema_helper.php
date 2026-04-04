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
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        motus_cargar_pdo_config();
        global $pdo;
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $cached = true;
            return true;
        }
        $st = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
        $st->execute(['chatbot_habilitado']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $row === null) {
            $cached = true;
            return true;
        }
        $v = strtolower(trim((string) ($row['valor'] ?? '')));
        $cached = !in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true);
        return $cached;
    } catch (Throwable $e) {
        error_log('motus_chatbot_habilitado: ' . $e->getMessage());
        $cached = true;
        return true;
    }
}
