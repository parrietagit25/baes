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
 * Historial de cambios de estado por solicitud (conteo + textos concatenados para export).
 *
 * @param list<int> $solicitudIds
 * @return array<int, array{
 *   count:int,
 *   ultima_fecha:string,
 *   ultima_nota:string,
 *   notas_txt:string,
 *   estados_txt:string
 * }>
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
            SELECT solicitud_id, descripcion, estado_anterior, estado_nuevo, fecha_creacion
            FROM historial_solicitud
            WHERE solicitud_id IN ($placeholders)
              AND tipo_accion = 'cambio_estado'
            ORDER BY solicitud_id ASC, fecha_creacion ASC
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
                    'ultima_fecha' => '',
                    'ultima_nota' => '',
                    'notas_txt' => '',
                    'estados_txt' => '',
                    'notas_partes' => [],
                    'estados_partes' => [],
                ];
            }

            $out[$sid]['count']++;
            $fecha = (string) ($row['fecha_creacion'] ?? '');
            $out[$sid]['ultima_fecha'] = $fecha;
            $nota = historialExtraerMotivoCambioEstado($row['descripcion'] ?? '');
            $out[$sid]['ultima_nota'] = $nota;

            $n = $out[$sid]['count'];
            $de = trim((string) ($row['estado_anterior'] ?? ''));
            $a = trim((string) ($row['estado_nuevo'] ?? ''));
            $transicion = ($de !== '' || $a !== '') ? ($de !== '' ? $de : '—') . ' → ' . ($a !== '' ? $a : '—') : '—';
            $out[$sid]['notas_partes'][] = 'Nota' . $n . ': ' . ($nota !== '' ? $nota : '—');
            $out[$sid]['estados_partes'][] = 'Estado' . $n . ': ' . $transicion;
        }

        foreach ($out as $sid => $item) {
            $out[$sid]['notas_txt'] = implode(', ', $item['notas_partes']);
            $out[$sid]['estados_txt'] = implode(', ', $item['estados_partes']);
            unset($out[$sid]['notas_partes'], $out[$sid]['estados_partes']);
        }

        return $out;
    } catch (PDOException $e) {
        error_log('historialResumenCambiosEstadoPorSolicitudes: ' . $e->getMessage());
        return [];
    }
}
