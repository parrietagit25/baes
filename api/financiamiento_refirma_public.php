<?php
/**
 * API pública: validar token de refirma y guardar nueva firma (solo firma).
 * Sin sesión. Token de un solo uso y caducidad 30 min (configurado al generar).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => motus_mantenimiento_mensaje(), 'maintenance' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

function refirma_tabla_token_existe(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            return $cache = false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = ? AND table_name = 'financiamiento_refirma_token'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function refirma_normalizar_firma(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('#^data:image/[^;]+;base64,(.+)$#is', $raw, $m)) {
        $raw = $m[1];
    }
    $raw = preg_replace('/\s+/', '', $raw);
    if (strlen($raw) < 80) {
        return null;
    }
    return $raw;
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Base de datos no disponible.']);
        exit;
    }
    if (!refirma_tabla_token_existe($pdo)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Función no disponible: ejecute la migración de refirma en la base de datos.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Enlace inválido.']);
            exit;
        }
        $st = $pdo->prepare("
            SELECT t.id AS token_id, t.expires_at, t.used_at, fr.cliente_nombre
            FROM financiamiento_refirma_token t
            INNER JOIN financiamiento_registros fr ON fr.id = t.financiamiento_registro_id
            WHERE t.token = ?
            LIMIT 1
        ");
        $st->execute([strtolower($token)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Enlace no válido o ya no existe.']);
            exit;
        }
        if (!empty($row['used_at'])) {
            http_response_code(410);
            echo json_encode(['success' => false, 'message' => 'Este enlace ya fue utilizado.']);
            exit;
        }
        $exp = strtotime((string) $row['expires_at']);
        if ($exp !== false && $exp < time()) {
            http_response_code(410);
            echo json_encode(['success' => false, 'message' => 'Este enlace ha expirado. Solicite uno nuevo al administrador.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'cliente_nombre' => $row['cliente_nombre'] ?? '',
                'expires_at' => $row['expires_at'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '', true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
        exit;
    }
    $token = isset($body['token']) ? trim((string) $body['token']) : '';
    $firma = isset($body['firma']) ? (string) $body['firma'] : '';
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }
    $firmaNorm = refirma_normalizar_firma($firma);
    if ($firmaNorm === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Debe enviar una firma válida (dibuje en el recuadro).']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("
            SELECT t.id AS token_id, t.financiamiento_registro_id, t.expires_at, t.used_at
            FROM financiamiento_refirma_token t
            WHERE t.token = ?
            LIMIT 1
            FOR UPDATE
        ");
        $st->execute([strtolower($token)]);
        $tok = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tok) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Enlace no válido.']);
            exit;
        }
        if (!empty($tok['used_at'])) {
            $pdo->rollBack();
            http_response_code(410);
            echo json_encode(['success' => false, 'message' => 'Este enlace ya fue utilizado.']);
            exit;
        }
        $exp = strtotime((string) $tok['expires_at']);
        if ($exp !== false && $exp < time()) {
            $pdo->rollBack();
            http_response_code(410);
            echo json_encode(['success' => false, 'message' => 'Este enlace ha expirado.']);
            exit;
        }
        $frId = (int) $tok['financiamiento_registro_id'];
        $upd = $pdo->prepare('UPDATE financiamiento_registros SET firma = ? WHERE id = ?');
        $upd->execute([$firmaNorm, $frId]);
        $upd2 = $pdo->prepare('UPDATE financiamiento_refirma_token SET used_at = NOW() WHERE id = ?');
        $upd2->execute([(int) $tok['token_id']]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Firma guardada correctamente. Puede cerrar esta ventana.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (Throwable $e) {
    error_log('financiamiento_refirma_public: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor.']);
}
