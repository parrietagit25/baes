<?php
/**
 * Envía resumen de solicitud al corredor (solo el adjunto del formulario Corredor).
 */
session_start();

$esDescarga = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && isset($_GET['id'])
    && isset($_GET['action'])
    && $_GET['action'] === 'descargar';

if (!$esDescarga) {
    header('Content-Type: application/json');
}

if (!isset($_SESSION['user_id'])) {
    if ($esDescarga) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/historial_helper.php';

$userRoles = $_SESSION['user_roles'] ?? [];
if (!is_array($userRoles)) {
    $userRoles = [];
}
// Misma política que envío de resumen a banco: usuario autenticado con acceso a Motus.
// La pestaña Corredor se oculta en UI para roles solo-banco.

asegurarTablaCorredorEnvios($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        if ($esDescarga) {
            descargarEnvioCorredor($pdo, (int) $_GET['id']);
            exit;
        }
        $solicitudId = isset($_GET['solicitud_id']) ? (int) $_GET['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            echo json_encode(['success' => false, 'message' => 'solicitud_id requerido']);
            exit;
        }
        listarEnviosCorredor($pdo, $solicitudId);
        exit;
    }

    if ($method === 'POST') {
        enviarResumenCorredorApi($pdo);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('enviar_resumen_corredor: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al procesar Corredor']);
}

function asegurarTablaCorredorEnvios(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_corredor_envios (
          id INT NOT NULL AUTO_INCREMENT,
          solicitud_id INT NOT NULL,
          usuario_id INT NOT NULL,
          email_corredor VARCHAR(255) NOT NULL,
          comentario_interno TEXT NULL,
          comentario_correo TEXT NULL,
          nombre_original VARCHAR(255) NOT NULL,
          nombre_archivo VARCHAR(255) NOT NULL,
          ruta_archivo VARCHAR(500) NOT NULL,
          tipo_archivo VARCHAR(120) NULL,
          tamano_archivo INT NULL DEFAULT 0,
          estado ENUM('enviado','fallido') NOT NULL DEFAULT 'enviado',
          mensaje VARCHAR(500) NULL,
          fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_corredor_solicitud (solicitud_id),
          KEY idx_corredor_fecha (fecha_envio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $done = true;
}

function listarEnviosCorredor(PDO $pdo, int $solicitudId): void {
    $stmt = $pdo->prepare("
        SELECT e.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM email_corredor_envios e
        LEFT JOIN usuarios u ON u.id = e.usuario_id
        WHERE e.solicitud_id = ?
        ORDER BY e.fecha_envio DESC, e.id DESC
    ");
    $stmt->execute([$solicitudId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function descargarEnvioCorredor(PDO $pdo, int $id): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    $stmt = $pdo->prepare('SELECT * FROM email_corredor_envios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        return;
    }
    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $row['ruta_archivo']);
    $abs = $root . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($abs);
    if ($real === false || !is_file($real) || !is_readable($real)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Archivo no disponible en disco']);
        return;
    }
    $nombre = (string) ($row['nombre_original'] ?: basename($real));
    $mime = (string) ($row['tipo_archivo'] ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($real));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre) . '"');
    readfile($real);
}

function enviarResumenCorredorApi(PDO $pdo): void {
    $solicitudId = isset($_POST['solicitud_id']) ? (int) $_POST['solicitud_id'] : 0;
    $emailCorredor = trim((string) ($_POST['email_corredor'] ?? ''));
    $comentarioInterno = trim((string) ($_POST['comentario_interno'] ?? ''));
    $comentarioCorreo = trim((string) ($_POST['comentario_correo'] ?? ''));

    if ($solicitudId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Solicitud requerida']);
        return;
    }
    if ($emailCorredor === '' || !filter_var($emailCorredor, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email del corredor inválido']);
        return;
    }
    if (!isset($_FILES['archivo']) || (int) ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Adjunto obligatorio']);
        return;
    }

    $file = $_FILES['archivo'];
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'El archivo supera 10MB']);
        return;
    }

    $tiposPermitidos = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? '');
    if (!in_array($mime, $tiposPermitidos, true)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM solicitudes_credito WHERE id = ?');
    $stmt->execute([$solicitudId]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$solicitud) {
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        return;
    }
    if (isset($solicitud['ao_auto'])) {
        $solicitud['año_auto'] = $solicitud['ao_auto'];
    }

    $dirRel = 'adjuntos/corredor/' . $solicitudId;
    $dirAbs = __DIR__ . '/../' . $dirRel;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo crear carpeta de adjuntos']);
        return;
    }

    $nombreOriginal = basename((string) $file['name']);
    $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $nombreArchivo = 'corredor_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
    $rutaRel = $dirRel . '/' . $nombreArchivo;
    $rutaAbs = $dirAbs . '/' . $nombreArchivo;
    if (!move_uploaded_file($file['tmp_name'], $rutaAbs)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM vehiculos_solicitud WHERE solicitud_id = ? ORDER BY id');
    $stmt->execute([$solicitudId]);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT e.*, u.nombre as banco_nombre, u.apellido as banco_apellido
        FROM evaluaciones_banco e
        INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
        INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
        WHERE e.solicitud_id = ?
        ORDER BY e.fecha_evaluacion DESC
    ");
    $stmt->execute([$solicitudId]);
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cfg = file_exists(__DIR__ . '/../config/email.php') ? require __DIR__ . '/../config/email.php' : [];
    $app_url = (function_exists('getenv') && getenv('APP_URL')) ? getenv('APP_URL') : '';
    if ($app_url === '' || $app_url === false) {
        $app_url = $cfg['app_url'] ?? '';
    }
    $mostrarEnlaceMotus = !empty($cfg['mail_show_app_link_in_emails']);

    // Resumen igual al de bancos, pero sin listar/adjuntar los adjuntos de la solicitud.
    $html = construirResumenSolicitudHtml(
        $solicitud,
        $vehiculos,
        $evaluaciones,
        [],
        'Corredor',
        $app_url,
        $mostrarEnlaceMotus,
        $comentarioCorreo
    );

    $replyToGestor = '';
    $cc = [];
    $gestorId = isset($solicitud['gestor_id']) ? (int) $solicitud['gestor_id'] : 0;
    if ($gestorId > 0) {
        $stmtG = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
        $stmtG->execute([$gestorId]);
        $gestorEmail = trim((string) ($stmtG->fetchColumn() ?: ''));
        if ($gestorEmail !== '' && filter_var($gestorEmail, FILTER_VALIDATE_EMAIL)) {
            $replyToGestor = $gestorEmail;
            $cc[] = $gestorEmail;
        }
    }

    $emailService = (new EmailService())->paraSolicitud($solicitudId);
    $resultado = $emailService->enviarCorreo(
        $emailCorredor,
        asuntoResumenSolicitudBancoMail($solicitud),
        $html,
        'Corredor',
        strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)),
        [['path' => $rutaAbs, 'filename' => $nombreOriginal]],
        $cc,
        [],
        $replyToGestor,
        $solicitudId
    );

    $ok = !empty($resultado['success']);
    $mensaje = (string) ($resultado['message'] ?? ($ok ? 'Enviado' : 'Error al enviar'));

    $stmt = $pdo->prepare("
        INSERT INTO email_corredor_envios
            (solicitud_id, usuario_id, email_corredor, comentario_interno, comentario_correo,
             nombre_original, nombre_archivo, ruta_archivo, tipo_archivo, tamano_archivo, estado, mensaje)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $solicitudId,
        (int) $_SESSION['user_id'],
        $emailCorredor,
        $comentarioInterno !== '' ? $comentarioInterno : null,
        $comentarioCorreo !== '' ? $comentarioCorreo : null,
        $nombreOriginal,
        $nombreArchivo,
        $rutaRel,
        $mime,
        (int) ($file['size'] ?? 0),
        $ok ? 'enviado' : 'fallido',
        mb_substr($mensaje, 0, 500),
    ]);

    if ($ok) {
        try {
            registrarHistorialSolicitud(
                $pdo,
                $solicitudId,
                (int) $_SESSION['user_id'],
                'actualizacion',
                'Resumen enviado al corredor ' . $emailCorredor . ' con adjunto: ' . $nombreOriginal,
                null,
                null
            );
        } catch (Throwable $e) {
            error_log('historial corredor: ' . $e->getMessage());
        }
        echo json_encode(['success' => true, 'message' => 'Resumen enviado al corredor correctamente']);
        return;
    }

    echo json_encode(['success' => false, 'message' => $mensaje !== '' ? $mensaje : 'No se pudo enviar el correo']);
}
