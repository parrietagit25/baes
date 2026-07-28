<?php
/**
 * Alcance de visibilidad para roles bancarios.
 * ROLE_BANCO: solo SC con asignación personal activa.
 * ROLE_ADMIN_BANCO: todas las SC asignadas a usuarios de su misma entidad (banco_id).
 */

function motus_roles_usuario(?array $roles = null): array {
    if ($roles !== null) {
        return $roles;
    }
    return $_SESSION['user_roles'] ?? [];
}

function motus_es_admin_banco(?array $roles = null): bool {
    return in_array('ROLE_ADMIN_BANCO', motus_roles_usuario($roles), true);
}

function motus_es_analista_banco(?array $roles = null): bool {
    return in_array('ROLE_BANCO', motus_roles_usuario($roles), true);
}

/** Vista/acceso tipo banco (analista o admin de entidad). */
function motus_es_vista_banco(?array $roles = null): bool {
    return motus_es_analista_banco($roles) || motus_es_admin_banco($roles);
}

function motus_obtener_banco_id_usuario(PDO $pdo, ?int $usuarioId = null): ?int {
    $uid = $usuarioId ?? (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return null;
    }
    if (
        $usuarioId === null
        && array_key_exists('banco_id', $_SESSION)
        && $_SESSION['banco_id'] !== null
        && $_SESSION['banco_id'] !== ''
    ) {
        $cached = (int) $_SESSION['banco_id'];
        return $cached > 0 ? $cached : null;
    }
    try {
        $stmt = $pdo->prepare('SELECT banco_id FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $bancoId = isset($row['banco_id']) && $row['banco_id'] !== null && $row['banco_id'] !== ''
            ? (int) $row['banco_id']
            : null;
        if ($usuarioId === null) {
            $_SESSION['banco_id'] = $bancoId;
        }
        return $bancoId && $bancoId > 0 ? $bancoId : null;
    } catch (Throwable $e) {
        error_log('motus_obtener_banco_id_usuario: ' . $e->getMessage());
        return null;
    }
}

/**
 * EXISTS SQL (sin alias de solicitudes) para filtrar por alcance banco.
 * Usar con tabla base solicitudes_credito (sin alias) o pasar $aliasSolicitud = 's'.
 *
 * @return array{0: string, 1: array<int, int>} [sqlFragment, params]
 */
function motus_sql_filtro_alcance_banco(PDO $pdo, ?array $roles = null, string $colSolicitudId = 'solicitudes_credito.id'): array {
    $roles = motus_roles_usuario($roles);
    $usuarioId = (int) ($_SESSION['user_id'] ?? 0);

    if (motus_es_admin_banco($roles)) {
        $bancoId = motus_obtener_banco_id_usuario($pdo, $usuarioId);
        if (!$bancoId) {
            return [' AND 1=0 ', []];
        }
        $sql = " AND EXISTS (
            SELECT 1
            FROM usuarios_banco_solicitudes ubs_scope
            INNER JOIN usuarios u_scope ON u_scope.id = ubs_scope.usuario_banco_id
            WHERE ubs_scope.solicitud_id = {$colSolicitudId}
              AND ubs_scope.estado = 'activo'
              AND u_scope.banco_id = ?
        ) ";
        return [$sql, [$bancoId]];
    }

    if (motus_es_analista_banco($roles)) {
        $sql = " AND EXISTS (
            SELECT 1
            FROM usuarios_banco_solicitudes ubs_scope
            WHERE ubs_scope.solicitud_id = {$colSolicitudId}
              AND ubs_scope.estado = 'activo'
              AND ubs_scope.usuario_banco_id = ?
        ) ";
        return [$sql, [$usuarioId]];
    }

    return ['', []];
}

/**
 * ¿La solicitud está en el alcance del usuario banco / admin banco?
 */
function motus_solicitud_en_alcance_banco(PDO $pdo, int $solicitudId, ?array $roles = null): bool {
    $roles = motus_roles_usuario($roles);
    if (!motus_es_vista_banco($roles)) {
        return false;
    }
    $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
    if (motus_es_admin_banco($roles)) {
        $bancoId = motus_obtener_banco_id_usuario($pdo, $usuarioId);
        if (!$bancoId) {
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT 1
            FROM usuarios_banco_solicitudes ubs
            INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
            WHERE ubs.solicitud_id = ?
              AND ubs.estado = 'activo'
              AND u.banco_id = ?
            LIMIT 1
        ");
        $stmt->execute([$solicitudId, $bancoId]);
        return (bool) $stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM usuarios_banco_solicitudes ubs
        WHERE ubs.solicitud_id = ?
          AND ubs.estado = 'activo'
          AND ubs.usuario_banco_id = ?
        LIMIT 1
    ");
    $stmt->execute([$solicitudId, $usuarioId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Etiqueta de estado para lista banco: solo revisión o decisión del martillo.
 *
 * @return array{label:string,class:string,decision:?string}
 */
function motus_estado_vista_banco(?string $decision): array
{
    $d = strtolower(trim((string) $decision));
    $map = [
        'preaprobado' => ['label' => 'Preaprobado', 'class' => 'estado-aprobada'],
        'aprobado' => ['label' => 'Aprobada', 'class' => 'estado-aprobada'],
        'aprobado_condicional' => ['label' => 'Aprobado Condicional', 'class' => 'estado-aprobada'],
        'rechazado' => ['label' => 'Rechazada', 'class' => 'estado-rechazada'],
    ];
    if ($d !== '' && isset($map[$d])) {
        return [
            'label' => $map[$d]['label'],
            'class' => $map[$d]['class'],
            'decision' => $d,
        ];
    }

    return [
        'label' => 'Revisión de los bancos',
        'class' => 'estado-revision',
        'decision' => null,
    ];
}

/**
 * Fragmento SQL: última decisión del martillo para el alcance del usuario banco.
 */
function motus_sql_decision_banco_lista(bool $esAdminBanco, int $usuarioId, int $bancoId = 0): string
{
    if ($esAdminBanco && $bancoId > 0) {
        $bancoId = (int) $bancoId;

        return "(
            SELECT eb.decision
            FROM evaluaciones_banco eb
            INNER JOIN usuarios_banco_solicitudes ubs_dec ON ubs_dec.id = eb.usuario_banco_id
            INNER JOIN usuarios u_dec ON u_dec.id = ubs_dec.usuario_banco_id
            WHERE eb.solicitud_id = s.id
              AND u_dec.banco_id = {$bancoId}
            ORDER BY eb.fecha_evaluacion DESC, eb.id DESC
            LIMIT 1
        ) AS decision_banco_vista";
    }

    $usuarioId = (int) $usuarioId;

    return "(
        SELECT eb.decision
        FROM evaluaciones_banco eb
        INNER JOIN usuarios_banco_solicitudes ubs_dec ON ubs_dec.id = eb.usuario_banco_id
        WHERE eb.solicitud_id = s.id
          AND ubs_dec.usuario_banco_id = {$usuarioId}
        ORDER BY eb.fecha_evaluacion DESC, eb.id DESC
        LIMIT 1
    ) AS decision_banco_vista";
}
