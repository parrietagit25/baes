<?php
/**
 * Helper para registrar acciones en el historial de solicitudes
 */

/**
 * Registra una acción en el historial de una solicitud
 * @param PDO $pdo
 * @param int $solicitudId
 * @param int $usuarioId
 * @param string $tipoAccion creacion|cambio_estado|documento_agregado|asignacion_banco|actualizacion_datos|evaluacion_banco
 * @param string $descripcion
 * @param string|null $estadoAnterior
 * @param string|null $estadoNuevo
 * @return bool
 */
function registrarHistorialSolicitud($pdo, $solicitudId, $usuarioId, $tipoAccion, $descripcion, $estadoAnterior = null, $estadoNuevo = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO historial_solicitud (solicitud_id, usuario_id, tipo_accion, descripcion, estado_anterior, estado_nuevo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$solicitudId, $usuarioId, $tipoAccion, $descripcion, $estadoAnterior, $estadoNuevo]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al registrar historial: " . $e->getMessage());
        return false;
    }
}
