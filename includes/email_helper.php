<?php
/**
 * Funciones helper para facilitar el envío de correos
 * 
 * Este archivo proporciona funciones de alto nivel para enviar correos
 * en diferentes escenarios del sistema
 */

require_once __DIR__ . '/EmailService.php';

/**
 * Incrementa correos_enviados en la fila de asignación (mismo usuario banco y solicitud).
 */
function incrementarCorreosEnviadosUsuarioBancoSolicitud(PDO $pdo, int $solicitudId, int $usuarioBancoUserId): void {
    if ($solicitudId < 1 || $usuarioBancoUserId < 1) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE usuarios_banco_solicitudes SET correos_enviados = correos_enviados + 1 WHERE solicitud_id = ? AND usuario_banco_id = ?'
        );
        $stmt->execute([$solicitudId, $usuarioBancoUserId]);
    } catch (Throwable $e) {
        error_log('incrementarCorreosEnviadosUsuarioBancoSolicitud: ' . $e->getMessage());
    }
}

/**
 * Incrementa correos_enviados por id de usuarios_banco_solicitudes (p. ej. reevaluación: FK en evaluaciones_banco).
 */
function incrementarCorreosEnviadosPorAsignacion(PDO $pdo, int $usuariosBancoSolicitudesId): void {
    if ($usuariosBancoSolicitudesId < 1) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE usuarios_banco_solicitudes SET correos_enviados = correos_enviados + 1 WHERE id = ?'
        );
        $stmt->execute([$usuariosBancoSolicitudesId]);
    } catch (Throwable $e) {
        error_log('incrementarCorreosEnviadosPorAsignacion: ' . $e->getMessage());
    }
}

/**
 * Correos en copia (CC) para el resumen al usuario banco: quien envía (sesión), email Pipedrive si existe,
 * y el ejecutivo de ventas asignado en Datos Generales (si tiene email válido). El destinatario principal sigue siendo el usuario banco (Para).
 *
 * @return list<string>
 */
function obtenerCopiasResumenSolicitudBanco(PDO $pdo, array $solicitud, string $emailDestinoBanco): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $cc = [];
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['email'])) {
            $e = trim((string) $u['email']);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $e;
            }
        }
    }
    if (!empty($solicitud['email_pipedrive'])) {
        $e = trim((string) $solicitud['email_pipedrive']);
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $cc[] = $e;
        }
    }
    $ejecutivoVentasId = isset($solicitud['ejecutivo_ventas_id']) ? (int) $solicitud['ejecutivo_ventas_id'] : 0;
    if ($ejecutivoVentasId > 0) {
        try {
            $stmtEv = $pdo->prepare('SELECT email FROM ejecutivos_ventas WHERE id = ? LIMIT 1');
            $stmtEv->execute([$ejecutivoVentasId]);
            $ev = $stmtEv->fetch(PDO::FETCH_ASSOC);
            if ($ev && !empty($ev['email'])) {
                $e = trim((string) $ev['email']);
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $e;
                }
            }
        } catch (PDOException $e) {
            error_log('obtenerCopiasResumenSolicitudBanco ejecutivos_ventas: ' . $e->getMessage());
        }
    }
    $destLower = strtolower(trim($emailDestinoBanco));
    $seen = [];
    $out = [];
    foreach ($cc as $e) {
        $k = strtolower($e);
        if ($k === $destLower || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $e;
    }
    return $out;
}

/**
 * Rutas absolutas de adjuntos de solicitud listas para EmailService (con nombre original en el correo).
 * Omite archivos inexistentes, rutas sospechosas y archivos &gt; 20 MB (límite práctico de proveedores).
 *
 * @param array<int, array<string, mixed>> $filas Filas con ruta_archivo y nombre_original
 * @return array<int, array{path: string, filename: string}>
 */
function adjuntosArchivosParaCorreoResumen(array $filas): array {
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = __DIR__ . '/..';
    }
    $maxBytes = 20 * 1024 * 1024;
    $out = [];
    $nombreUsos = [];

    foreach ($filas as $row) {
        $rel = trim((string) ($row['ruta_archivo'] ?? ''));
        if ($rel === '' || str_contains($rel, '..')) {
            continue;
        }
        $relNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        $abs = $root . DIRECTORY_SEPARATOR . $relNorm;
        $absReal = realpath($abs);
        if ($absReal !== false) {
            $abs = $absReal;
        }
        if (!is_file($abs) || !is_readable($abs)) {
            continue;
        }
        if (filesize($abs) > $maxBytes) {
            error_log('adjuntosArchivosParaCorreoResumen: omitido por tamaño: ' . $abs);
            continue;
        }

        $nom = trim((string) ($row['nombre_original'] ?? ''));
        if ($nom === '') {
            $nom = basename($rel);
        }
        $nom = str_replace(["\0", "\r", "\n"], '', $nom);

        $clave = strtolower($nom);
        if (!isset($nombreUsos[$clave])) {
            $nombreUsos[$clave] = 0;
            $nombreFinal = $nom;
        } else {
            $nombreUsos[$clave]++;
            $info = pathinfo($nom);
            $stem = $info['filename'] !== '' ? $info['filename'] : 'archivo';
            $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
            $nombreFinal = $stem . '_' . $nombreUsos[$clave] . $ext;
        }

        $out[] = ['path' => $abs, 'filename' => $nombreFinal];
    }

    return $out;
}

/**
 * Envía notificación al vendedor cuando el banco responde
 */
function enviarNotificacionVendedor($solicitudId) {
    global $pdo;
    
    try {
        // Obtener información completa de la solicitud
        $stmt = $pdo->prepare("
            SELECT s.*, u.email as vendedor_email, u.nombre as vendedor_nombre, u.apellido as vendedor_apellido
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.vendedor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud || empty($solicitud['vendedor_email'])) {
            return ['success' => false, 'message' => 'Vendedor no encontrado o sin email'];
        }
        
        $emailService = new EmailService();
        $vendedorNombre = trim(($solicitud['vendedor_nombre'] ?? '') . ' ' . ($solicitud['vendedor_apellido'] ?? ''));

        return $emailService->notificarVendedorBancoResponde(
            $solicitud['vendedor_email'],
            $vendedorNombre ?: 'Vendedor',
            $solicitud
        );
        
    } catch (Exception $e) {
        error_log("Error al enviar notificación al vendedor: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Envía recordatorio al banco sobre una solicitud pendiente
 */
function enviarRecordatorioBanco($solicitudId, $usuarioBancoId) {
    global $pdo;
    
    try {
        // Obtener información de la solicitud y el banco
        $stmt = $pdo->prepare("
            SELECT s.*, u.email as banco_email, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM solicitudes_credito s
            INNER JOIN usuarios_banco_solicitudes ubs ON s.id = ubs.solicitud_id AND ubs.estado = 'activo'
            INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
            WHERE s.id = ? AND u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$solicitudId, $usuarioBancoId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud || empty($solicitud['banco_email'])) {
            return ['success' => false, 'message' => 'Usuario banco no encontrado o sin email'];
        }
        
        $emailService = new EmailService();
        $bancoNombre = trim(($solicitud['banco_nombre'] ?? '') . ' ' . ($solicitud['banco_apellido'] ?? ''));

        $resultado = $emailService->enviarRecordatorioBanco(
            $solicitud['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $solicitud
        );
        if (!empty($resultado['success'])) {
            incrementarCorreosEnviadosUsuarioBancoSolicitud($pdo, (int) $solicitudId, (int) $usuarioBancoId);
        }
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error al enviar recordatorio al banco: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Notifica al banco cuando se le asigna una nueva solicitud
 */
function notificarBancoNuevaSolicitud($solicitudId, $usuarioBancoId) {
    global $pdo;
    
    try {
        // Obtener información de la solicitud y el banco
        $stmt = $pdo->prepare("
            SELECT s.*, u.email as banco_email, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM solicitudes_credito s
            INNER JOIN usuarios u ON u.id = ?
            WHERE s.id = ?
        ");
        $stmt->execute([$usuarioBancoId, $solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud || empty($solicitud['banco_email'])) {
            return ['success' => false, 'message' => 'Usuario banco no encontrado o sin email'];
        }
        
        $emailService = new EmailService();
        $bancoNombre = trim(($solicitud['banco_nombre'] ?? '') . ' ' . ($solicitud['banco_apellido'] ?? ''));

        $resultado = $emailService->notificarBancoNuevaSolicitud(
            $solicitud['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $solicitud
        );
        if (!empty($resultado['success'])) {
            incrementarCorreosEnviadosUsuarioBancoSolicitud($pdo, (int) $solicitudId, (int) $usuarioBancoId);
        }
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error al notificar banco de nueva solicitud: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Notifica al cliente cuando su solicitud es aprobada
 */
function notificarClienteAprobacion($solicitudId) {
    global $pdo;
    
    try {
        // Obtener información de la solicitud
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud || empty($solicitud['email'])) {
            return ['success' => false, 'message' => 'Cliente no encontrado o sin email'];
        }
        
        // Solo enviar si está aprobada
        if ($solicitud['respuesta_banco'] !== 'Aprobado' && $solicitud['respuesta_banco'] !== 'Pre Aprobado') {
            return ['success' => false, 'message' => 'La solicitud no está aprobada'];
        }
        
        $emailService = new EmailService();

        return $emailService->notificarClienteAprobacion(
            $solicitud['email'],
            $solicitud['nombre_cliente'],
            $solicitud
        );
        
    } catch (Exception $e) {
        error_log("Error al notificar cliente de aprobación: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Notifica al gestor sobre cambios de estado
 */
function notificarGestorCambioEstado($solicitudId, $estadoAnterior, $estadoNuevo) {
    global $pdo;
    
    try {
        // Obtener información de la solicitud y el gestor
        $stmt = $pdo->prepare("
            SELECT s.*, u.email as gestor_email, u.nombre as gestor_nombre, u.apellido as gestor_apellido
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud || empty($solicitud['gestor_email'])) {
            return ['success' => false, 'message' => 'Gestor no encontrado o sin email'];
        }
        
        $emailService = new EmailService();
        $gestorNombre = trim(($solicitud['gestor_nombre'] ?? '') . ' ' . ($solicitud['gestor_apellido'] ?? ''));

        return $emailService->notificarGestorCambioEstado(
            $solicitud['gestor_email'],
            $gestorNombre ?: 'Gestor',
            $solicitud,
            $estadoAnterior,
            $estadoNuevo
        );
        
    } catch (Exception $e) {
        error_log("Error al notificar gestor de cambio de estado: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Notifica cuando se solicita una reevaluación
 */
function notificarReevaluacion($solicitudId, $evaluacionId, $comentario) {
    global $pdo;
    
    try {
        // Obtener información de la evaluación y el banco
        $stmt = $pdo->prepare("
            SELECT e.*, s.*, u.email as banco_email, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM evaluaciones_banco e
            INNER JOIN solicitudes_credito s ON e.solicitud_id = s.id
            INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
            INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
            WHERE e.id = ? AND s.id = ?
        ");
        $stmt->execute([$evaluacionId, $solicitudId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['banco_email'])) {
            return ['success' => false, 'message' => 'Usuario banco no encontrado o sin email'];
        }

        $emailService = new EmailService();
        $bancoNombre = trim(($row['banco_nombre'] ?? '') . ' ' . ($row['banco_apellido'] ?? ''));

        $resultado = $emailService->notificarReevaluacion(
            $row['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $row,
            $comentario
        );
        if (!empty($resultado['success']) && isset($row['usuario_banco_id'])) {
            incrementarCorreosEnviadosPorAsignacion($pdo, (int) $row['usuario_banco_id']);
        }
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error al notificar reevaluación: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

/**
 * Asunto del correo de resumen al usuario banco (incluye nombre del cliente si existe).
 */
function asuntoResumenSolicitudBancoMail(array $solicitud): string {
    $id = (int) ($solicitud['id'] ?? 0);
    $nombre = trim((string) ($solicitud['nombre_cliente'] ?? ''));
    $nombre = preg_replace('/[\r\n\x00]+/', ' ', $nombre);
    $nombre = preg_replace('/\s+/u', ' ', $nombre);
    $nombre = trim($nombre);
    $max = 80;
    if ($nombre !== '' && function_exists('mb_strlen') && mb_strlen($nombre, 'UTF-8') > $max) {
        $nombre = mb_substr($nombre, 0, $max - 1, 'UTF-8') . '…';
    } elseif ($nombre !== '' && strlen($nombre) > $max) {
        $nombre = substr($nombre, 0, $max - 3) . '...';
    }
    if ($nombre !== '') {
        return 'Resumen Solicitud #' . $id . ' — ' . $nombre . ' — MOTUS';
    }
    return 'Resumen Solicitud #' . $id . ' — MOTUS';
}

/**
 * Envía al usuario banco un resumen completo de la solicitud por correo
 * (datos generales, perfil financiero, datos del auto, análisis, adjuntos).
 */
function enviarResumenSolicitudBanco($solicitudId, $usuarioBancoId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.email as banco_email, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM usuarios u
            INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
            INNER JOIN roles r ON ur.rol_id = r.id
            WHERE u.id = ? AND r.nombre = 'ROLE_BANCO'
        ");
        $stmt->execute([$usuarioBancoId]);
        $banco = $stmt->fetch();
        if (!$banco || empty($banco['banco_email'])) {
            return ['success' => false, 'message' => 'Usuario banco no encontrado o sin email'];
        }

        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }
        if (isset($solicitud['ao_auto'])) $solicitud['año_auto'] = $solicitud['ao_auto'];

        $stmt = $pdo->prepare("SELECT * FROM vehiculos_solicitud WHERE solicitud_id = ? ORDER BY id");
        $stmt->execute([$solicitudId]);
        $vehiculos = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT e.*, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM evaluaciones_banco e
            INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
            INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
            WHERE e.solicitud_id = ?
            ORDER BY e.fecha_evaluacion DESC
        ");
        $stmt->execute([$solicitudId]);
        $evaluaciones = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT nombre_original, tipo_archivo, ruta_archivo
            FROM adjuntos_solicitud
            WHERE solicitud_id = ?
            ORDER BY fecha_subida DESC
        ");
        $stmt->execute([$solicitudId]);
        $adjuntos = $stmt->fetchAll();

        $cfg = file_exists(__DIR__ . '/../config/email.php') ? require __DIR__ . '/../config/email.php' : [];
        $app_url = (function_exists('getenv') && getenv('APP_URL')) ? getenv('APP_URL') : '';
        if ($app_url === '' || $app_url === false) {
            $app_url = $cfg['app_url'] ?? '';
        }
        $mostrarEnlaceMotus = !empty($cfg['mail_show_app_link_in_emails']);

        $bancoNombre = trim(($banco['banco_nombre'] ?? '') . ' ' . ($banco['banco_apellido'] ?? ''));
        $html = construirResumenSolicitudHtml(
            $solicitud,
            $vehiculos,
            $evaluaciones,
            $adjuntos,
            $bancoNombre,
            $app_url,
            $mostrarEnlaceMotus
        );

        $copiasCc = obtenerCopiasResumenSolicitudBanco($pdo, $solicitud, (string) $banco['banco_email']);
        $ccVisibles = [];
        $replyToGestor = '';
        $gestorId = isset($solicitud['gestor_id']) ? (int)$solicitud['gestor_id'] : 0;
        if ($gestorId > 0) {
            try {
                $stmtGestor = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
                $stmtGestor->execute([$gestorId]);
                $gestor = $stmtGestor->fetch(PDO::FETCH_ASSOC);
                if ($gestor && !empty($gestor['email'])) {
                    $emailGestor = trim((string)$gestor['email']);
                    if ($emailGestor !== '' && filter_var($emailGestor, FILTER_VALIDATE_EMAIL)) {
                        $replyToGestor = $emailGestor;
                        $ccVisibles[] = $emailGestor;
                        // Evitar duplicar el gestor cuando también salga en la lista de copias.
                        $copiasCc = array_values(array_filter($copiasCc, function($e) use ($emailGestor) {
                            return strtolower((string)$e) !== strtolower($emailGestor);
                        }));
                    }
                }
            } catch (PDOException $e) {
                error_log('enviarResumenSolicitudBanco gestor cc: ' . $e->getMessage());
            }
        }
        $ccLower = [];
        foreach ($ccVisibles as $addr) {
            $ccLower[strtolower(trim((string) $addr))] = true;
        }
        foreach ($copiasCc as $e) {
            $e = trim((string) $e);
            if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $k = strtolower($e);
            if (isset($ccLower[$k])) {
                continue;
            }
            $ccLower[$k] = true;
            $ccVisibles[] = $e;
        }
        $archivosAdjuntos = adjuntosArchivosParaCorreoResumen($adjuntos);

        $emailService = new EmailService();
        $resultado = $emailService->enviarCorreo(
            $banco['banco_email'],
            asuntoResumenSolicitudBancoMail($solicitud),
            $html,
            $bancoNombre ?: 'Usuario Banco',
            strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)),
            $archivosAdjuntos,
            $ccVisibles,
            [],
            $replyToGestor
        );
        if (!empty($resultado['success'])) {
            incrementarCorreosEnviadosUsuarioBancoSolicitud($pdo, (int) $solicitudId, (int) $usuarioBancoId);
        }
        return $resultado;
    } catch (Exception $e) {
        error_log("Error enviarResumenSolicitudBanco: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar el correo'];
    }
}

/**
 * Envía un único correo de resumen para todos los bancos asignados usando CCO.
 * Incluye en CCO: usuarios banco asignados, vendedor de la solicitud y correo Pipedrive.
 * Las respuestas del correo se dirigen al gestor asignado (Reply-To), cuando exista.
 */
function enviarResumenSolicitudBancoTodosUnCorreo($solicitudId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([(int) $solicitudId]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }
        if (isset($solicitud['ao_auto'])) {
            $solicitud['año_auto'] = $solicitud['ao_auto'];
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id as usuario_banco_id, u.email
            FROM usuarios_banco_solicitudes ubs
            INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
            WHERE ubs.solicitud_id = ?
            ORDER BY ubs.id
        ");
        $stmt->execute([(int) $solicitudId]);
        $bancos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bcc = [];
        $idsUsuariosBanco = [];
        foreach ($bancos as $row) {
            $uid = isset($row['usuario_banco_id']) ? (int) $row['usuario_banco_id'] : 0;
            $email = trim((string) ($row['email'] ?? ''));
            if ($uid < 1 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $idsUsuariosBanco[] = $uid;
            $bcc[] = $email;
        }
        if (count($bcc) === 0) {
            return ['success' => false, 'message' => 'No hay usuarios banco asignados con email válido'];
        }

        $vendedorId = isset($solicitud['vendedor_id']) ? (int) $solicitud['vendedor_id'] : 0;
        if ($vendedorId > 0) {
            $stmtVend = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
            $stmtVend->execute([$vendedorId]);
            $vendedor = $stmtVend->fetch(PDO::FETCH_ASSOC);
            if ($vendedor && !empty($vendedor['email'])) {
                $emailVendedor = trim((string) $vendedor['email']);
                if ($emailVendedor !== '' && filter_var($emailVendedor, FILTER_VALIDATE_EMAIL)) {
                    $bcc[] = $emailVendedor;
                }
            }
        }

        if (!empty($solicitud['email_pipedrive'])) {
            $emailPipe = trim((string) $solicitud['email_pipedrive']);
            if ($emailPipe !== '' && filter_var($emailPipe, FILTER_VALIDATE_EMAIL)) {
                $bcc[] = $emailPipe;
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM vehiculos_solicitud WHERE solicitud_id = ? ORDER BY id");
        $stmt->execute([(int) $solicitudId]);
        $vehiculos = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT e.*, u.nombre as banco_nombre, u.apellido as banco_apellido
            FROM evaluaciones_banco e
            INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
            INNER JOIN usuarios u ON ubs.usuario_banco_id = u.id
            WHERE e.solicitud_id = ?
            ORDER BY e.fecha_evaluacion DESC
        ");
        $stmt->execute([(int) $solicitudId]);
        $evaluaciones = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT nombre_original, tipo_archivo, ruta_archivo
            FROM adjuntos_solicitud
            WHERE solicitud_id = ?
            ORDER BY fecha_subida DESC
        ");
        $stmt->execute([(int) $solicitudId]);
        $adjuntos = $stmt->fetchAll();

        $cfg = file_exists(__DIR__ . '/../config/email.php') ? require __DIR__ . '/../config/email.php' : [];
        $app_url = (function_exists('getenv') && getenv('APP_URL')) ? getenv('APP_URL') : '';
        if ($app_url === '' || $app_url === false) {
            $app_url = $cfg['app_url'] ?? '';
        }
        $mostrarEnlaceMotus = !empty($cfg['mail_show_app_link_in_emails']);

        $html = construirResumenSolicitudHtml(
            $solicitud,
            $vehiculos,
            $evaluaciones,
            $adjuntos,
            'Usuarios Banco',
            $app_url,
            $mostrarEnlaceMotus
        );

        $replyToGestor = '';
        $gestorId = isset($solicitud['gestor_id']) ? (int) $solicitud['gestor_id'] : 0;
        if ($gestorId > 0) {
            $stmtGestor = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
            $stmtGestor->execute([$gestorId]);
            $gestor = $stmtGestor->fetch(PDO::FETCH_ASSOC);
            if ($gestor && !empty($gestor['email'])) {
                $emailGestor = trim((string) $gestor['email']);
                if ($emailGestor !== '' && filter_var($emailGestor, FILTER_VALIDATE_EMAIL)) {
                    $replyToGestor = $emailGestor;
                }
            }
        }

        $toPrincipal = '';
        if (!empty($_SESSION['user_id'])) {
            $stmtUser = $pdo->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
            $stmtUser->execute([(int) $_SESSION['user_id']]);
            $usr = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($usr && !empty($usr['email'])) {
                $emailUsr = trim((string) $usr['email']);
                if ($emailUsr !== '' && filter_var($emailUsr, FILTER_VALIDATE_EMAIL)) {
                    $toPrincipal = $emailUsr;
                }
            }
        }
        if ($toPrincipal === '') {
            $toPrincipal = 'no-reply@automarketcr.com';
        }

        $archivosAdjuntos = adjuntosArchivosParaCorreoResumen($adjuntos);
        $emailService = new EmailService();
        $resultado = $emailService->enviarCorreo(
            $toPrincipal,
            asuntoResumenSolicitudBancoMail($solicitud),
            $html,
            '',
            strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)),
            $archivosAdjuntos,
            [],
            $bcc,
            $replyToGestor
        );

        if (!empty($resultado['success'])) {
            foreach (array_unique($idsUsuariosBanco) as $uid) {
                incrementarCorreosEnviadosUsuarioBancoSolicitud($pdo, (int) $solicitudId, (int) $uid);
            }
            $resultado['enviados'] = count(array_unique($idsUsuariosBanco));
        }

        return $resultado;
    } catch (Throwable $e) {
        error_log("Error enviarResumenSolicitudBancoTodosUnCorreo: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar el correo'];
    }
}

function construirResumenSolicitudHtml($solicitud, $vehiculos, $evaluaciones, $adjuntos, $bancoNombre, $app_url, $mostrarEnlaceMotus = false) {
    $h = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };
    $n = function($v, $dec = 0) { return $v !== null && $v !== '' ? number_format((float)$v, $dec, ',', '.') : 'N/A'; };
    $formatAbono = function($porcentaje, $monto) use ($n) {
        $partes = [];
        if ($porcentaje !== null && $porcentaje !== '') {
            $partes[] = $n($porcentaje, 2) . '%';
        }
        if ($monto !== null && $monto !== '') {
            $partes[] = '$' . $n($monto, 2);
        }
        return $partes ? implode(' / ', $partes) : 'N/A';
    };
    
    $linkVer = ($mostrarEnlaceMotus && $app_url)
        ? '<p><a href="' . $h($app_url) . '/solicitudes.php?id=' . (int)$solicitud['id'] . '" style="display:inline-block;padding:12px 24px;background:#e8f1ff;color:#0b3a6f;text-decoration:none;border-radius:6px;border:1px solid #cddff7;">Ver solicitud en MOTUS</a></p>'
        : '';
    
    $html = '<h2>Resumen de Solicitud de Crédito #' . (int)$solicitud['id'] . '</h2>';
    $html .= '<p>Estimado/a <strong>' . $h($bancoNombre) . '</strong>,</p>';
    $html .= '<p>Se adjunta un resumen de la solicitud para su revisión.</p>';

    $html .= '<h3>Datos generales</h3><div class="info-box" style="background:#f8f9fa;border-left:4px solid #0d6efd;padding:12px;margin:10px 0;">';
    $html .= '<p><strong>Cliente:</strong> ' . $h($solicitud['nombre_cliente']) . '</p>';
    $html .= '<p><strong>Cédula:</strong> ' . $h($solicitud['cedula']) . '</p>';
    $html .= '<p><strong>Teléfono:</strong> ' . $h($solicitud['telefono']) . '</p>';
    $html .= '<p><strong>Email:</strong> ' . $h($solicitud['email']) . '</p>';
    $html .= '<p><strong>Dirección:</strong> ' . $h($solicitud['direccion']) . '</p>';
    $html .= '<p><strong>Estado:</strong> ' . $h($solicitud['estado']) . '</p>';
    $html .= '</div>';

    $html .= '<h3>Perfil financiero</h3><div class="info-box" style="background:#f8f9fa;border-left:4px solid #28a745;padding:12px;margin:10px 0;">';
    $html .= '<p><strong>Perfil:</strong> ' . $h($solicitud['perfil_financiero']) . '</p>';
    $html .= '<p><strong>Ingreso:</strong> ' . $n($solicitud['ingreso'], 2) . '</p>';
    $html .= '<p><strong>Profesión/Ocupación:</strong> ' . $h($solicitud['profesion']) . ' / ' . $h($solicitud['ocupacion']) . '</p>';
    $html .= '<p><strong>Empresa/Negocio:</strong> ' . $h($solicitud['nombre_empresa_negocio']) . '</p>';
    $html .= '<p><strong>Estabilidad laboral:</strong> ' . $h($solicitud['estabilidad_laboral']) . '</p>';
    $html .= '</div>';

    $comentariosGestor = trim((string)($solicitud['comentarios_gestor'] ?? ''));
    $html .= '<h3>Análisis del gestor</h3><div class="info-box" style="background:#f8f9fa;border-left:4px solid #6f42c1;padding:12px;margin:10px 0;">';
    if ($comentariosGestor !== '') {
        $html .= '<p style="margin:0;white-space:pre-wrap;">' . nl2br($h($comentariosGestor)) . '</p>';
    } else {
        $html .= '<p style="margin:0;color:#6c757d;">Sin análisis registrado.</p>';
    }
    $html .= '</div>';

    $html .= '<h3>Datos del auto</h3><div class="info-box" style="background:#f8f9fa;border-left:4px solid #ffc107;padding:12px;margin:10px 0;">';
    if (!empty($vehiculos)) {
        foreach ($vehiculos as $v) {
            $html .= '<p><strong>Vehículo:</strong> ' . $h($v['marca'] ?? '') . ' ' . $h($v['modelo'] ?? '') . ' ' . $h($v['anio'] ?? '') . '</p>';
            $html .= '<p><strong>Kilometraje:</strong> ' . $n($v['kilometraje']) . '</p>';
            $html .= '<p><strong>Precio:</strong> $' . $n($v['precio']) . '</p>';
            $html .= '<p><strong>Abono:</strong> ' . $formatAbono($v['abono_porcentaje'] ?? null, $v['abono_monto'] ?? null) . '</p>';
            $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:10px 0;">';
        }
    } else {
        $html .= '<p>Marca: ' . $h($solicitud['marca_auto']) . ', Modelo: ' . $h($solicitud['modelo_auto']) . ', Año: ' . $h($solicitud['año_auto'] ?? $solicitud['ao_auto'] ?? '') . '</p>';
        $html .= '<p>Kilometraje: ' . $n($solicitud['kilometraje']) . '</p>';
        $html .= '<p>Precio especial: $' . $n($solicitud['precio_especial']) . '</p>';
        $html .= '<p>Abono: ' . $formatAbono($solicitud['abono_porcentaje'] ?? null, $solicitud['abono_monto'] ?? null) . '</p>';
    }
    $html .= '</div>';

    if (!empty($evaluaciones)) {
        $html .= '<h3>Análisis / Evaluaciones</h3><div class="info-box" style="background:#f8f9fa;border-left:4px solid #17a2b8;padding:12px;margin:10px 0;">';
        foreach ($evaluaciones as $e) {
            $tasaStr = '';
            if (isset($e['tasa_bancaria']) && $e['tasa_bancaria'] !== null && $e['tasa_bancaria'] !== '') {
                $tasaStr = ' — <strong>Tasa:</strong> ' . $n($e['tasa_bancaria'], 2) . '%';
            }
            $html .= '<p><strong>Evaluación:</strong> ' . $h($e['decision'] ?? '') . $tasaStr . ' — ' . $h($e['comentarios'] ?? '') . ' (Fecha: ' . $h($e['fecha_evaluacion'] ?? '') . ')</p>';
        }
        $html .= '</div>';
    }

    if (!empty($adjuntos)) {
        $html .= '<h3>Adjuntos</h3><div class="info-box" style="background:#f8f9fa;padding:12px;margin:10px 0;"><ul>';
        foreach ($adjuntos as $a) {
            $html .= '<li>' . $h($a['nombre_original']) . ' (' . $h($a['tipo_archivo']) . ')</li>';
        }
        $html .= '</ul></div>';
    }

    $html .= $linkVer;
    $html .= '<p>Saludos cordiales,<br><strong>MOTUS - AutoMarket Seminuevos</strong></p>';

    $subject = asuntoResumenSolicitudBancoMail($solicitud);
    $app_name = 'MOTUS - AutoMarket Seminuevos';
    $content = $html;
    ob_start();
    include __DIR__ . '/../templates/email/base.php';
    return ob_get_clean();
}

