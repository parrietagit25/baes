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

function interpretar_chatbot_habilitado(?string $valorDb): bool
{
    if ($valorDb === null) {
        return true;
    }
    $v = strtolower(trim($valorDb));
    return !in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
        $stmt->execute(['chatbot_habilitado']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $valorDb = ($row && array_key_exists('valor', $row)) ? $row['valor'] : null;
        $enabled = interpretar_chatbot_habilitado($valorDb);
        echo json_encode(['success' => true, 'data' => ['chatbot_habilitado' => $enabled]]);
    } catch (PDOException $e) {
        error_log('configuracion_sistema GET: ' . $e->getMessage());
        echo json_encode(['success' => true, 'data' => ['chatbot_habilitado' => true]]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = $_POST;
    }
    if (!array_key_exists('chatbot_habilitado', $raw)) {
        echo json_encode(['success' => false, 'message' => 'Falta el campo chatbot_habilitado']);
        exit;
    }
    $hab = $raw['chatbot_habilitado'];
    $activo = ($hab === true || $hab === 1 || $hab === '1' || $hab === 'true' || $hab === 'on' || $hab === 'yes');
    $val = $activo ? '1' : '0';
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO configuracion_sistema (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute(['chatbot_habilitado', $val]);
        echo json_encode([
            'success' => true,
            'message' => $activo ? 'Asistente de IA habilitado.' : 'Asistente de IA deshabilitado.',
            'data' => ['chatbot_habilitado' => $activo],
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
