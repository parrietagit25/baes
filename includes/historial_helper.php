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

/**
 * Extrae el motivo de una descripción de cambio de estado registrada en historial.
 */
function historialExtraerMotivoCambioEstado(?string $descripcion): string {
    $descripcion = trim((string) $descripcion);
    if ($descripcion === '') {
        return '';
    }
    if (preg_match('/Motivo:\s*(.*)$/s', $descripcion, $m)) {
        return trim((string) $m[1]);
    }
    return $descripcion;
}

/**
 * Resumen de cambios de estado por solicitud (conteo + último registro).
 *
 * @param list<int> $solicitudIds
 * @return array<int, array{count:int, ultima_fecha:string, ultima_nota:string}>
 */
function historialResumenCambiosEstadoPorSolicitudes(PDO $pdo, array $solicitudIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $solicitudIds), static function (int $id): bool {
        return $id > 0;
    })));
    if (!$ids) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT solicitud_id, descripcion, fecha_creacion
            FROM historial_solicitud
            WHERE solicitud_id IN ($placeholders)
              AND tipo_accion = 'cambio_estado'
            ORDER BY solicitud_id ASC, fecha_creacion DESC
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['solicitud_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (!isset($out[$sid])) {
                $out[$sid] = [
                    'count' => 0,
                    'ultima_fecha' => (string) ($row['fecha_creacion'] ?? ''),
                    'ultima_nota' => historialExtraerMotivoCambioEstado($row['descripcion'] ?? ''),
                ];
            }
            $out[$sid]['count']++;
        }

        return $out;
    } catch (PDOException $e) {
        error_log('historialResumenCambiosEstadoPorSolicitudes: ' . $e->getMessage());
        return [];
    }
}
