<?php
/**
 * Funciones helper para facilitar el envío de correos
 * 
 * Este archivo proporciona funciones de alto nivel para enviar correos
 * en diferentes escenarios del sistema
 */

require_once __DIR__ . '/EmailService.php';

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
        
        return $emailService->enviarRecordatorioBanco(
            $solicitud['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $solicitud
        );
        
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
        
        return $emailService->notificarBancoNuevaSolicitud(
            $solicitud['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $solicitud
        );
        
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
        $resultado = $stmt->fetch();
        
        if (!$resultado || empty($resultado['banco_email'])) {
            return ['success' => false, 'message' => 'Usuario banco no encontrado o sin email'];
        }
        
        $emailService = new EmailService();
        $bancoNombre = trim(($resultado['banco_nombre'] ?? '') . ' ' . ($resultado['banco_apellido'] ?? ''));
        
        return $emailService->notificarReevaluacion(
            $resultado['banco_email'],
            $bancoNombre ?: 'Usuario Banco',
            $resultado,
            $comentario
        );
        
    } catch (Exception $e) {
        error_log("Error al notificar reevaluación: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al enviar correo'];
    }
}

