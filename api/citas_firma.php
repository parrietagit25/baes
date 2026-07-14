<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;

switch ($method) {
    case 'GET':
        if (isset($_GET['solicitud_id'])) {
            obtenerEventos((int) $_GET['solicitud_id']);
        } elseif (isset($_GET['id'])) {
            obtenerEvento((int) $_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        }
        break;

    case 'POST':
        if ($action === 'actualizar') {
            actualizarEvento();
        } else {
            crearEvento();
        }
        break;

    case 'DELETE':
        eliminarEvento();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function solicitudConPropuesta($solicitudId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.id, s.evaluacion_seleccionada
        FROM solicitudes_credito s
        WHERE s.id = ? AND s.evaluacion_seleccionada IS NOT NULL
    ");
    $stmt->execute([$solicitudId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function usuarioPuedeGestionarCitaFirma($evaluacionSeleccionada) {
    $userRoles = $_SESSION['user_roles'] ?? [];
    if (in_array('ROLE_ADMIN', $userRoles, true) || in_array('ROLE_GESTOR', $userRoles, true)) {
        return true;
    }
    if (!in_array('ROLE_BANCO', $userRoles, true) || empty($evaluacionSeleccionada)) {
        return false;
    }
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.usuario_banco_id
        FROM evaluaciones_banco e
        INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
        WHERE e.id = ? AND ubs.usuario_banco_id = ?
    ");
    $stmt->execute([$evaluacionSeleccionada, $_SESSION['user_id']]);
    return (bool) $stmt->fetch();
}

function obtenerEventos($solicitudId) {
    global $pdo;

    try {
        $solicitud = solicitudConPropuesta($solicitudId);
        if (!$solicitud) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o no tiene propuesta seleccionada']);
            return;
        }
        if (!usuarioPuedeGestionarCitaFirma($solicitud['evaluacion_seleccionada'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene acceso a esta solicitud']);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id, solicitud_id, nombre_evento, fecha_cita AS fecha_evento, comentarios AS comentario,
                   fecha_creacion, fecha_actualizacion
            FROM citas_firma
            WHERE solicitud_id = ?
            ORDER BY fecha_cita DESC, id DESC
        ");
        $stmt->execute([$solicitudId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}

function obtenerEvento($eventoId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.solicitud_id, c.nombre_evento, c.fecha_cita AS fecha_evento,
                   c.comentarios AS comentario, s.evaluacion_seleccionada
            FROM citas_firma c
            INNER JOIN solicitudes_credito s ON s.id = c.solicitud_id
            WHERE c.id = ?
        ");
        $stmt->execute([$eventoId]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento no encontrado']);
            return;
        }
        if (!usuarioPuedeGestionarCitaFirma($evento['evaluacion_seleccionada'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene acceso']);
            return;
        }
        unset($evento['evaluacion_seleccionada']);
        echo json_encode(['success' => true, 'data' => $evento]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}

function crearEvento() {
    global $pdo;

    try {
        $solicitudId = (int) ($_POST['solicitud_id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre_evento'] ?? ''));
        $fecha = trim((string) ($_POST['fecha_evento'] ?? ''));
        $comentario = trim((string) ($_POST['comentario'] ?? ''));

        if ($solicitudId <= 0 || $nombre === '' || $fecha === '') {
            echo json_encode(['success' => false, 'message' => 'Nombre del evento y fecha son requeridos']);
            return;
        }

        $solicitud = solicitudConPropuesta($solicitudId);
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o no tiene propuesta seleccionada']);
            return;
        }
        if (!usuarioPuedeGestionarCitaFirma($solicitud['evaluacion_seleccionada'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para crear eventos en esta solicitud']);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO citas_firma (solicitud_id, nombre_evento, fecha_cita, hora_cita, comentarios)
            VALUES (?, ?, ?, NULL, ?)
        ");
        $stmt->execute([
            $solicitudId,
            $nombre,
            $fecha,
            $comentario !== '' ? $comentario : null
        ]);

        $id = (int) $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Evento registrado correctamente',
            'data' => [
                'id' => $id,
                'solicitud_id' => $solicitudId,
                'nombre_evento' => $nombre,
                'fecha_evento' => $fecha,
                'comentario' => $comentario !== '' ? $comentario : null
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear evento: ' . $e->getMessage()]);
    }
}

function actualizarEvento() {
    global $pdo;

    try {
        $eventoId = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre_evento'] ?? ''));
        $fecha = trim((string) ($_POST['fecha_evento'] ?? ''));
        $comentario = trim((string) ($_POST['comentario'] ?? ''));

        if ($eventoId <= 0 || $nombre === '' || $fecha === '') {
            echo json_encode(['success' => false, 'message' => 'ID, nombre del evento y fecha son requeridos']);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT c.id, c.solicitud_id, s.evaluacion_seleccionada
            FROM citas_firma c
            INNER JOIN solicitudes_credito s ON s.id = c.solicitud_id
            WHERE c.id = ?
        ");
        $stmt->execute([$eventoId]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento no encontrado']);
            return;
        }
        if (!usuarioPuedeGestionarCitaFirma($evento['evaluacion_seleccionada'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para editar este evento']);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE citas_firma
            SET nombre_evento = ?, fecha_cita = ?, comentarios = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nombre,
            $fecha,
            $comentario !== '' ? $comentario : null,
            $eventoId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Evento actualizado correctamente',
            'data' => [
                'id' => $eventoId,
                'solicitud_id' => (int) $evento['solicitud_id'],
                'nombre_evento' => $nombre,
                'fecha_evento' => $fecha,
                'comentario' => $comentario !== '' ? $comentario : null
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar evento: ' . $e->getMessage()]);
    }
}

function eliminarEvento() {
    global $pdo;

    try {
        $input = file_get_contents('php://input');
        parse_str($input, $_DELETE);
        $eventoId = (int) ($_DELETE['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);

        if ($eventoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de evento requerido']);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT c.id, c.solicitud_id, s.evaluacion_seleccionada
            FROM citas_firma c
            INNER JOIN solicitudes_credito s ON s.id = c.solicitud_id
            WHERE c.id = ?
        ");
        $stmt->execute([$eventoId]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento no encontrado']);
            return;
        }
        if (!usuarioPuedeGestionarCitaFirma($evento['evaluacion_seleccionada'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar este evento']);
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM citas_firma WHERE id = ? AND solicitud_id = ?');
        $stmt->execute([$eventoId, $evento['solicitud_id']]);

        if ($stmt->rowCount() !== 1) {
            echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el evento']);
            return;
        }

        echo json_encode(['success' => true, 'message' => 'Evento eliminado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar evento: ' . $e->getMessage()]);
    }
}
