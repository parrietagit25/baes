<?php
/**
 * Lectura y actualización de parámetros de sistema (solo administrador).
 */
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo el administrador puede cambiar esta configuración']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function asegurar_tabla_configuracion_sistema(PDO $pdo): void
{
    $sqlTabla = "CREATE TABLE IF NOT EXISTS configuracion_sistema (
        clave varchar(64) NOT NULL,
        valor text NOT NULL,
        actualizado_en timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlTabla);

    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_sistema (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE clave = clave'
    );
    $stmt->execute(['chatbot_habilitado', '1']);
    $stmt->execute(['mantenimiento_activo', '0']);
}

function interpretar_chatbot_habilitado(?string $valorDb): bool
{
    if ($valorDb === null) {
        return true;
    }
    $v = strtolower(trim($valorDb));
    return !in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true);
}

function interpretar_mantenimiento_activo(?string $valorDb): bool
{
    if ($valorDb === null) {
        return false;
    }
    $v = strtolower(trim($valorDb));
    return in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'on', 'enabled'], true);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        asegurar_tabla_configuracion_sistema($pdo);
        $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion_sistema WHERE clave IN ('chatbot_habilitado', 'mantenimiento_activo')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $r) {
            if (!isset($r['clave'])) {
                continue;
            }
            $map[(string) $r['clave']] = isset($r['valor']) ? (string) $r['valor'] : null;
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'chatbot_habilitado' => interpretar_chatbot_habilitado($map['chatbot_habilitado'] ?? null),
                'mantenimiento_activo' => interpretar_mantenimiento_activo($map['mantenimiento_activo'] ?? null),
            ]
        ]);
    } catch (PDOException $e) {
        error_log('configuracion_sistema GET: ' . $e->getMessage());
        echo json_encode([
            'success' => true,
            'data' => [
                'chatbot_habilitado' => true,
                'mantenimiento_activo' => false,
            ]
        ]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = $_POST;
    }
    $camposValidos = ['chatbot_habilitado', 'mantenimiento_activo'];
    $camposRecibidos = array_intersect($camposValidos, array_keys($raw));
    if (count($camposRecibidos) === 0) {
        echo json_encode(['success' => false, 'message' => 'Falta al menos uno de los campos: chatbot_habilitado o mantenimiento_activo']);
        exit;
    }
    try {
        asegurar_tabla_configuracion_sistema($pdo);
        $stmt = $pdo->prepare('INSERT INTO configuracion_sistema (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $responseData = [];
        $mensajes = [];

        if (array_key_exists('chatbot_habilitado', $raw)) {
            $hab = $raw['chatbot_habilitado'];
            $activo = ($hab === true || $hab === 1 || $hab === '1' || $hab === 'true' || $hab === 'on' || $hab === 'yes');
            $stmt->execute(['chatbot_habilitado', $activo ? '1' : '0']);
            $responseData['chatbot_habilitado'] = $activo;
            $mensajes[] = $activo ? 'Asistente de IA habilitado.' : 'Asistente de IA deshabilitado.';
        }

        if (array_key_exists('mantenimiento_activo', $raw)) {
            $man = $raw['mantenimiento_activo'];
            $mantenimientoActivo = ($man === true || $man === 1 || $man === '1' || $man === 'true' || $man === 'on' || $man === 'yes');
            $stmt->execute(['mantenimiento_activo', $mantenimientoActivo ? '1' : '0']);
            $responseData['mantenimiento_activo'] = $mantenimientoActivo;
            $mensajes[] = $mantenimientoActivo ? 'Modo mantenimiento activado.' : 'Modo mantenimiento desactivado.';
        }

        echo json_encode([
            'success' => true,
            'message' => implode(' ', $mensajes),
            'data' => $responseData,
        ]);
    } catch (PDOException $e) {
        error_log('configuracion_sistema POST: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar. Ejecute en la base de datos database/migracion_configuracion_sistema.sql',
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
