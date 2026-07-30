<?php
/**
 * Datos del reporte de seguimiento de usuarios (solo admin id=1).
 */

require_once __DIR__ . '/usuario_actividad_helper.php';

/**
 * @return array{desde:string,hasta:string,usuario_id:?int,evento:string,q:string,limit:int}
 */
function seguimiento_usuarios_parse_filtros(): array
{
    $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');
    $desdeDef = (new DateTimeImmutable('-7 days'))->format('Y-m-d');
    $desde = trim((string) ($_GET['desde'] ?? $desdeDef));
    $hasta = trim((string) ($_GET['hasta'] ?? $hoy));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $desde = $desdeDef;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $hasta = $hoy;
    }
    if ($desde > $hasta) {
        $tmp = $desde;
        $desde = $hasta;
        $hasta = $tmp;
    }
    $usuarioId = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int) $_GET['usuario_id'] : null;
    if ($usuarioId !== null && $usuarioId <= 0) {
        $usuarioId = null;
    }
    $evento = strtolower(trim((string) ($_GET['evento'] ?? '')));
    if ($evento !== '' && !preg_match('/^[a-z_]{1,40}$/', $evento)) {
        $evento = '';
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) > 80) {
        $q = mb_substr($q, 0, 80);
    }
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
    if ($limit < 50) {
        $limit = 50;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }
    return compact('desde', 'hasta', 'usuarioId', 'evento', 'q', 'limit');
}

/**
 * @param array{desde:string,hasta:string,usuario_id?:?int,usuarioId?:?int,evento:string,q:string,limit:int} $f
 * @return array{where:string,params:array}
 */
function seguimiento_usuarios_where(array $f): array
{
    $usuarioId = $f['usuarioId'] ?? ($f['usuario_id'] ?? null);
    $where = ['a.created_at >= ?', 'a.created_at < DATE_ADD(?, INTERVAL 1 DAY)'];
    $params = [$f['desde'] . ' 00:00:00', $f['hasta']];
    if ($usuarioId) {
        $where[] = 'a.usuario_id = ?';
        $params[] = (int) $usuarioId;
    }
    if ($f['evento'] !== '') {
        $where[] = 'a.evento = ?';
        $params[] = $f['evento'];
    }
    if ($f['q'] !== '') {
        $like = '%' . $f['q'] . '%';
        $where[] = '(a.ip LIKE ? OR a.detalle LIKE ? OR a.pagina LIKE ? OR a.seccion LIKE ? OR a.url_path LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function seguimiento_usuarios_data(PDO $pdo, array $f): array
{
    motus_actividad_asegurar_tabla($pdo);
    $w = seguimiento_usuarios_where($f);

    $sqlKpi = "
        SELECT
          COUNT(*) AS total_eventos,
          SUM(CASE WHEN a.evento = 'login' THEN 1 ELSE 0 END) AS total_logins,
          SUM(CASE WHEN a.evento = 'logout' THEN 1 ELSE 0 END) AS total_logouts,
          SUM(CASE WHEN a.evento = 'page_view' THEN 1 ELSE 0 END) AS total_page_views,
          COUNT(DISTINCT a.usuario_id) AS usuarios_unicos,
          COUNT(DISTINCT a.session_key) AS sesiones
        FROM usuario_actividad a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE {$w['where']}
    ";
    $st = $pdo->prepare($sqlKpi);
    $st->execute($w['params']);
    $kpi = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $stOnline = $pdo->query("
        SELECT a.usuario_id, MAX(a.created_at) AS ultima_vez,
               u.nombre, u.apellido, u.email,
               (SELECT a2.pagina FROM usuario_actividad a2
                 WHERE a2.usuario_id = a.usuario_id
                 ORDER BY a2.id DESC LIMIT 1) AS pagina,
               (SELECT a2.ip FROM usuario_actividad a2
                 WHERE a2.usuario_id = a.usuario_id
                 ORDER BY a2.id DESC LIMIT 1) AS ip,
               (SELECT a2.seccion FROM usuario_actividad a2
                 WHERE a2.usuario_id = a.usuario_id
                 ORDER BY a2.id DESC LIMIT 1) AS seccion
        FROM usuario_actividad a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.created_at >= (NOW() - INTERVAL 5 MINUTE)
          AND a.usuario_id IS NOT NULL
          AND a.evento IN ('page_view','heartbeat','click','action','visibility','login')
        GROUP BY a.usuario_id, u.nombre, u.apellido, u.email
        ORDER BY ultima_vez DESC
        LIMIT 50
    ");
    $online = $stOnline ? $stOnline->fetchAll(PDO::FETCH_ASSOC) : [];

    $sqlTop = "
        SELECT COALESCE(a.pagina, '(sin página)') AS pagina,
               COALESCE(a.seccion, 'Otra') AS seccion,
               COUNT(*) AS visitas
        FROM usuario_actividad a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE {$w['where']} AND a.evento = 'page_view'
        GROUP BY a.pagina, a.seccion
        ORDER BY visitas DESC
        LIMIT 15
    ";
    $stTop = $pdo->prepare($sqlTop);
    $stTop->execute($w['params']);
    $topPaginas = $stTop->fetchAll(PDO::FETCH_ASSOC);

    $sqlEv = "
        SELECT a.evento, COUNT(*) AS total
        FROM usuario_actividad a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE {$w['where']}
        GROUP BY a.evento
        ORDER BY total DESC
    ";
    $stEv = $pdo->prepare($sqlEv);
    $stEv->execute($w['params']);
    $porEvento = $stEv->fetchAll(PDO::FETCH_ASSOC);

    $limit = (int) $f['limit'];
    $sqlRows = "
        SELECT a.id, a.usuario_id, a.session_key, a.evento, a.pagina, a.seccion,
               a.detalle, a.url_path, a.ip, a.user_agent, a.created_at,
               u.nombre, u.apellido, u.email
        FROM usuario_actividad a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE {$w['where']}
        ORDER BY a.id DESC
        LIMIT {$limit}
    ";
    $stRows = $pdo->prepare($sqlRows);
    $stRows->execute($w['params']);
    $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

    $usuarios = $pdo->query("
        SELECT id, nombre, apellido, email, activo
        FROM usuarios
        ORDER BY nombre, apellido
    ")->fetchAll(PDO::FETCH_ASSOC);

    return [
        'filtros' => [
            'desde' => $f['desde'],
            'hasta' => $f['hasta'],
            'usuario_id' => $f['usuarioId'] ?? null,
            'evento' => $f['evento'],
            'q' => $f['q'],
            'limit' => $limit,
        ],
        'kpi' => [
            'total_eventos' => (int) ($kpi['total_eventos'] ?? 0),
            'total_logins' => (int) ($kpi['total_logins'] ?? 0),
            'total_logouts' => (int) ($kpi['total_logouts'] ?? 0),
            'total_page_views' => (int) ($kpi['total_page_views'] ?? 0),
            'usuarios_unicos' => (int) ($kpi['usuarios_unicos'] ?? 0),
            'sesiones' => (int) ($kpi['sesiones'] ?? 0),
            'online_ahora' => count($online),
        ],
        'online' => $online,
        'top_paginas' => $topPaginas,
        'por_evento' => $porEvento,
        'usuarios' => $usuarios,
        'rows' => $rows,
    ];
}
