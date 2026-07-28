<?php
/**
 * Datos del reporte de solicitudes por usuario banco (admin de entidad).
 */

declare(strict_types=1);

const REPORTES_BANCO_USUARIOS_COLUMNAS = [
    'En revision',
    'Preaprobada',
    'Aprobadas',
    'Completadas',
    'Desistimiento',
    'Rechazadas',
];

/** @return array<string, string> estado solicitud => columna del reporte */
function reportes_banco_usuarios_mapa_estados(): array
{
    return [
        'Nueva' => 'En revision',
        'En Revisión Banco' => 'En revision',
        'Evaluacion' => 'En revision',
        'Reevaluación por los Bancos' => 'En revision',
        'Comité' => 'En revision',
        'Reconsideración' => 'En revision',
        'Aprobado con Condición' => 'En revision',
        'Pend. Firma' => 'En revision',
        'Pend. Poliza' => 'En revision',
        'Pend. Abono' => 'En revision',
        'Pend. Abono y poliza' => 'En revision',
        'Pend. CPP' => 'En revision',
        'Pre Aprobado' => 'Preaprobada',
        'Aprobada' => 'Aprobadas',
        'Completada' => 'Completadas',
        'Desistimiento' => 'Desistimiento',
        'Rechazada' => 'Rechazadas',
    ];
}

function reportes_banco_usuarios_mapear_estado(?string $estado): ?string
{
    if ($estado === null || $estado === '') {
        return null;
    }
    $mapa = reportes_banco_usuarios_mapa_estados();

    return $mapa[$estado] ?? null;
}

/** @return list<string> */
function reportes_banco_usuarios_estados_por_columna(string $columna): array
{
    $estados = [];
    foreach (reportes_banco_usuarios_mapa_estados() as $estado => $col) {
        if ($col === $columna) {
            $estados[] = $estado;
        }
    }

    return $estados;
}

/** @return array<int, array<string, mixed>> */
function reportes_banco_usuarios_fila_vacia(int $usuarioId, string $nombre, string $email): array
{
    $fila = [
        'usuario_id' => $usuarioId,
        'nombre' => $nombre,
        'email' => $email,
        'total' => 0,
    ];
    foreach (REPORTES_BANCO_USUARIOS_COLUMNAS as $col) {
        $fila[$col] = 0;
    }

    return $fila;
}

/**
 * @return array<int, array<string, mixed>>
 */
function reportes_banco_usuarios_data(PDO $pdo, int $bancoId): array
{
    $sql = "
        SELECT
            u.id AS usuario_id,
            u.nombre,
            u.apellido,
            u.email,
            s.estado,
            COUNT(DISTINCT s.id) AS total
        FROM usuarios u
        INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
        INNER JOIN roles r ON r.id = ur.rol_id AND r.nombre IN ('ROLE_BANCO', 'ROLE_ADMIN_BANCO')
        LEFT JOIN usuarios_banco_solicitudes ubs
            ON ubs.usuario_banco_id = u.id AND ubs.estado = 'activo'
        LEFT JOIN solicitudes_credito s ON s.id = ubs.solicitud_id
        WHERE u.activo = 1
          AND u.banco_id = ?
        GROUP BY u.id, u.nombre, u.apellido, u.email, s.estado
        ORDER BY u.apellido, u.nombre, s.estado
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bancoId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porUsuario = [];
    foreach ($rows as $r) {
        $id = (int) $r['usuario_id'];
        if (!isset($porUsuario[$id])) {
            $porUsuario[$id] = reportes_banco_usuarios_fila_vacia(
                $id,
                trim($r['nombre'] . ' ' . $r['apellido']),
                (string) $r['email']
            );
        }
        $columna = reportes_banco_usuarios_mapear_estado($r['estado'] ?? null);
        if ($columna !== null) {
            $n = (int) $r['total'];
            $porUsuario[$id][$columna] += $n;
            $porUsuario[$id]['total'] += $n;
        }
    }

    return array_values($porUsuario);
}

/**
 * @return list<array<string, mixed>>
 */
function reportes_banco_usuarios_solicitudes(PDO $pdo, int $bancoId, int $usuarioId, string $columna): array
{
    if (!in_array($columna, REPORTES_BANCO_USUARIOS_COLUMNAS, true)) {
        return [];
    }

    $estados = reportes_banco_usuarios_estados_por_columna($columna);
    if ($estados === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($estados), '?'));
    $sql = "
        SELECT DISTINCT
            s.id,
            s.nombre_cliente,
            s.cedula,
            s.estado,
            s.fecha_creacion,
            s.fecha_actualizacion
        FROM solicitudes_credito s
        INNER JOIN usuarios_banco_solicitudes ubs
            ON ubs.solicitud_id = s.id
           AND ubs.usuario_banco_id = ?
           AND ubs.estado = 'activo'
        INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id AND u.banco_id = ?
        WHERE s.estado IN ({$placeholders})
        ORDER BY s.fecha_actualizacion DESC
    ";
    $params = array_merge([$usuarioId, $bancoId], $estados);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
