<?php
/**
 * API: listado y detalle de financiamiento_registros (formulario público).
 * Solo usuarios con sesión y rol Admin o Gestor.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userRoles = $_SESSION['user_roles'] ?? [];
$puedeAcceder = in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)
    || in_array('ROLE_BANCO', $userRoles) || in_array('ROLE_VENDEDOR', $userRoles);
$isAdmin = in_array('ROLE_ADMIN', $userRoles);
if (!$puedeAcceder) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

/**
 * Columna opcional: vincula el envío público con solicitudes_credito sin usar financiamiento_registro_id en esa solicitud.
 */
function sol_fin_fr_tiene_solicitud_credito_id(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            $cache = false;
            return false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'financiamiento_registros'
              AND COLUMN_NAME = 'solicitud_credito_id'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Columna opcional en solicitudes_credito para vínculo directo con financiamiento_registros.
 */
function sol_fin_sc_tiene_financiamiento_registro_id(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            $cache = false;
            return false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'solicitudes_credito'
              AND COLUMN_NAME = 'financiamiento_registro_id'
        ");
        $s->execute([$db]);
        $cache = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function sol_fin_tiene_tabla_adjuntos_fin_reg(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) return $cache = false;
        $s = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'adjuntos_financiamiento_registros'
        ");
        $s->execute([$db]);
        $cache = ((int)$s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Verifica si existe una columna en financiamiento_registros.
 */
function sol_fin_fr_tiene_columna(PDO $pdo, string $col): bool {
    static $cache = [];
    if (array_key_exists($col, $cache)) {
        return $cache[$col];
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            $cache[$col] = false;
            return false;
        }
        $s = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'financiamiento_registros'
              AND COLUMN_NAME = ?
        ");
        $s->execute([$db, $col]);
        $cache[$col] = ((int)$s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$col] = false;
    }
    return $cache[$col];
}

function sol_fin_parse_post_input(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function sol_fin_tabla_existe(PDO $pdo, string $tableName): bool {
    static $mem = [];
    if (isset($mem[$tableName])) {
        return $mem[$tableName];
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$db) {
            return $mem[$tableName] = false;
        }
        $s = $pdo->prepare('
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = ? AND table_name = ?
        ');
        $s->execute([$db, $tableName]);
        return $mem[$tableName] = ((int) $s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return $mem[$tableName] = false;
    }
}

/**
 * Normaliza valor enviado desde el formulario admin para guardar en financiamiento_registros.
 *
 * @param array<string, mixed> $meta
 * @return mixed|null
 */
function sol_fin_normalizar_valor_admin(string $col, $valor, array $meta) {
    if ($col === 'tiene_conyuge') {
        if ($valor === '' || $valor === null) {
            return 0;
        }
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }
        $s = strtolower(trim((string) $valor));
        if (in_array($s, ['1', 'true', 'yes', 'sí', 'si', 'on'], true)) {
            return 1;
        }
        return 0;
    }
    $textarea = $meta['textarea'] ?? [];
    if (in_array($col, $textarea, true)) {
        $t = trim((string) $valor);
        return $t === '' ? null : $t;
    }
    $dates = $meta['date'] ?? [];
    if (in_array($col, $dates, true)) {
        $t = trim((string) $valor);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
            return $t;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $t)) {
            $dt = DateTime::createFromFormat('d/m/Y', $t);
            if ($dt && $dt->format('d/m/Y') === $t) {
                return $dt->format('Y-m-d');
            }
            $dt2 = DateTime::createFromFormat('m/d/Y', $t);
            if ($dt2 && $dt2->format('m/d/Y') === $t) {
                return $dt2->format('Y-m-d');
            }
        }
        return $t;
    }
    $numbers = $meta['number'] ?? [];
    if (in_array($col, $numbers, true)) {
        if ($valor === '' || $valor === null) {
            return null;
        }
        if (!is_numeric($valor)) {
            return null;
        }
        return 0 + $valor;
    }
    $t = trim((string) $valor);
    return $t === '' ? null : $t;
}

/**
 * Compara dos valores de celda para detectar cambio (auditoría).
 */
function sol_fin_valor_celda_igual($a, $b): bool {
    if ($a === null && $b === null) {
        return true;
    }
    if ($a === null || $b === null) {
        return false;
    }
    return (string) $a === (string) $b;
}

/**
 * Devuelve SELECT robusto para columnas esperadas en financiamiento_registros.
 * Si una columna no existe, devuelve NULL AS columna para evitar 500.
 */
function sol_fin_fr_select_cols(PDO $pdo, string $alias = 'fr'): string {
    $cols = [
        'id',
        'fecha_creacion',
        'nombre_gestor',
        'email_vendedor',
        'cliente_nombre',
        'cliente_id',
        'cliente_correo',
        'celular_cliente',
        'empresa_nombre',
        'empresa_salario',
        'marca_auto',
        'modelo_auto',
        'anio_auto',
        'precio_venta',
    ];
    $parts = [];
    foreach ($cols as $c) {
        if (sol_fin_fr_tiene_columna($pdo, $c)) {
            $parts[] = "{$alias}.{$c}";
        } else {
            $parts[] = "NULL AS {$c}";
        }
    }
    return implode(", ", $parts);
}

/**
 * Expresión SQL correlacionada: cantidad de adjuntos vinculados al registro fr.
 * Evita IN(SELECT … UNION …) (puede fallar o comportarse distinto según versión MySQL/MariaDB).
 */
function sol_fin_sql_adjuntos_count(PDO $pdo, string $frIdRef): string {
    // Regla estricta: Sol Financiamiento solo cuenta adjuntos del propio registro público.
    // Evita mezclar adjuntos históricos de solicitudes_credito del mismo vendedor/cliente.
    if (!sol_fin_tiene_tabla_adjuntos_fin_reg($pdo)) {
        return "0";
    }
    return "(SELECT COUNT(*) FROM adjuntos_financiamiento_registros afr WHERE afr.financiamiento_registro_id = {$frIdRef})";
}

function sol_fin_adjuntar_cero_adjuntos(array $rows): array {
    foreach ($rows as &$r) {
        $r['adjuntos_count'] = 0;
    }
    unset($r);
    return $rows;
}

/**
 * Lista adjuntos vinculados a un registro de financiamiento.
 *
 * @return array<int, array<string, mixed>>
 */
function sol_fin_obtener_adjuntos_por_registro(PDO $pdo, int $frId): array {
    if ($frId <= 0) {
        return [];
    }

    $stmtFr = $pdo->prepare("SELECT id, cliente_id, cliente_correo, cliente_nombre, solicitud_credito_id FROM financiamiento_registros WHERE id = ?");
    $stmtFr->execute([$frId]);
    $fr = $stmtFr->fetch(PDO::FETCH_ASSOC);
    if (!$fr) {
        return [];
    }

    // Regla estricta: listar solo adjuntos del registro público actual.
    if (!sol_fin_tiene_tabla_adjuntos_fin_reg($pdo)) {
        return [];
    }

    $rows = [];
    try {
        $st2 = $pdo->prepare("
            SELECT
                id,
                NULL AS solicitud_id,
                nombre_original,
                ruta_archivo,
                tipo_archivo,
                fecha_subida
            FROM adjuntos_financiamiento_registros
            WHERE financiamiento_registro_id = ?
            ORDER BY fecha_subida DESC, id DESC
        ");
        $st2->execute([$frId]);
        $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('sol_fin_obtener_adjuntos_por_registro adjuntos_fin_reg: ' . $e->getMessage());
    }

    usort($rows, static function($a, $b) {
        return strcmp((string)($b['fecha_subida'] ?? ''), (string)($a['fecha_subida'] ?? ''));
    });
    return $rows;
}

try {
    $postIn = ($_SERVER['REQUEST_METHOD'] === 'POST') ? sol_fin_parse_post_input() : [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($postIn['action'] ?? '') === 'delete')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador puede borrar registros.']);
            exit;
        }
        $id = isset($postIn['id']) ? trim((string) $postIn['id']) : '';
        if ($id === '' || !ctype_digit($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM financiamiento_registros WHERE id = ?');
        $stmt->execute([(int) $id]);
        if ($stmt->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado o ya eliminado.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($postIn['action'] ?? '') === 'update_registro')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador puede editar registros.']);
            exit;
        }
        if (!sol_fin_tabla_existe($pdo, 'financiamiento_registro_auditoria')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Falta la tabla de auditoría. Ejecute database/migracion_financiamiento_auditoria_refirma.sql en la base de datos.']);
            exit;
        }
        $id = isset($postIn['id']) ? trim((string) $postIn['id']) : '';
        if ($id === '' || !ctype_digit($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $datos = $postIn['datos'] ?? null;
        if (!is_array($datos)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Debe enviar "datos" como objeto JSON.']);
            exit;
        }
        $meta = require __DIR__ . '/../includes/financiamiento_registro_admin_campos.php';
        $whitelist = array_flip($meta['columnas_editables']);
        $st = $pdo->prepare('SELECT * FROM financiamiento_registros WHERE id = ?');
        $st->execute([(int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado.']);
            exit;
        }
        $cambios = [];
        $sets = [];
        foreach ($datos as $col => $valor) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            if (!isset($whitelist[$col])) {
                continue;
            }
            if (!sol_fin_fr_tiene_columna($pdo, $col)) {
                continue;
            }
            $old = array_key_exists($col, $row) ? $row[$col] : null;
            $nuevo = sol_fin_normalizar_valor_admin($col, $valor, $meta);
            if (sol_fin_valor_celda_igual($old, $nuevo)) {
                continue;
            }
            $cambios[$col] = ['old' => $old, 'new' => $nuevo];
            $sets[$col] = $nuevo;
        }
        if ($sets === []) {
            echo json_encode(['success' => true, 'message' => 'No hubo cambios que guardar.', 'cambios' => 0]);
            exit;
        }
        $parts = [];
        $vals = [];
        foreach ($sets as $c => $v) {
            $parts[] = '`' . str_replace('`', '', $c) . '` = ?';
            $vals[] = $v;
        }
        $vals[] = (int) $id;
        $sql = 'UPDATE financiamiento_registros SET ' . implode(', ', $parts) . ' WHERE id = ?';
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare($sql);
            $up->execute($vals);
            $aud = $pdo->prepare('
                INSERT INTO financiamiento_registro_auditoria
                (financiamiento_registro_id, usuario_id, fecha_modificacion, cambios_json)
                VALUES (?, ?, NOW(), ?)
            ');
            $aud->execute([
                (int) $id,
                (int) ($_SESSION['user_id'] ?? 0),
                json_encode($cambios, JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Registro actualizado. Se registró la auditoría.',
            'cambios' => count($cambios),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($postIn['action'] ?? '') === 'generar_refirma')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador puede generar enlaces de refirma.']);
            exit;
        }
        if (!sol_fin_tabla_existe($pdo, 'financiamiento_refirma_token')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Falta la tabla de tokens. Ejecute database/migracion_financiamiento_auditoria_refirma.sql en la base de datos.']);
            exit;
        }
        $id = isset($postIn['id']) ? trim((string) $postIn['id']) : '';
        if ($id === '' || !ctype_digit($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $frId = (int) $id;
        $st = $pdo->prepare('SELECT id, cliente_nombre, cliente_correo, email_vendedor FROM financiamiento_registros WHERE id = ?');
        $st->execute([$frId]);
        $fr = $st->fetch(PDO::FETCH_ASSOC);
        if (!$fr) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado.']);
            exit;
        }
        $emailCliente = isset($fr['cliente_correo']) ? trim((string) $fr['cliente_correo']) : '';
        if ($emailCliente === '' || !filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El registro no tiene un correo de cliente válido; no se puede enviar el enlace de refirma.']);
            exit;
        }
        $emailVend = isset($fr['email_vendedor']) ? trim((string) $fr['email_vendedor']) : '';
        $emailVend = ($emailVend !== '' && filter_var($emailVend, FILTER_VALIDATE_EMAIL)) ? $emailVend : null;

        $token = strtolower(bin2hex(random_bytes(32)));
        $expires = (new DateTimeImmutable('now'))->modify('+30 minutes');
        $expiresSql = $expires->format('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $inv = $pdo->prepare('
                UPDATE financiamiento_refirma_token SET used_at = NOW()
                WHERE financiamiento_registro_id = ? AND used_at IS NULL
            ');
            $inv->execute([$frId]);
            $ins = $pdo->prepare('
                INSERT INTO financiamiento_refirma_token
                (financiamiento_registro_id, token, expires_at, created_by_usuario_id)
                VALUES (?, ?, ?, ?)
            ');
            $ins->execute([$frId, $token, $expiresSql, (int) ($_SESSION['user_id'] ?? 0)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $emailCfg = require __DIR__ . '/../config/email.php';
        $baseUrl = rtrim((string) ($emailCfg['app_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = '';
        }
        $pathRel = '/financiamiento/refirma.php?t=' . rawurlencode($token);
        $linkAbs = ($baseUrl !== '' ? $baseUrl : '') . $pathRel;

        $nombreCli = trim((string) ($fr['cliente_nombre'] ?? ''));
        $asunto = 'Enlace para firmar de nuevo su solicitud de financiamiento';
        $expLeg = $expires->format('d/m/Y H:i');
        $cuerpoHtml = '<p>Estimado/a ' . htmlspecialchars($nombreCli !== '' ? $nombreCli : 'cliente', ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Para <strong>volver a firmar</strong> su solicitud de financiamiento, use el siguiente enlace. Solo podrá usarse <strong>una vez</strong> y caduca el <strong>' . htmlspecialchars($expLeg, ENT_QUOTES, 'UTF-8') . '</strong> (30 minutos desde su generación).</p>'
            . '<p><a href="' . htmlspecialchars($linkAbs, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($linkAbs, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p>Si no solicitó este correo, puede ignorarlo.</p>'
            . '<p style="font-size:13px;color:#555;">Este mensaje es automático.</p>';
        $cuerpoTxt = "Estimado/a {$nombreCli},\n\n"
            . "Use este enlace para volver a firmar (una sola vez; válido hasta {$expLeg}):\n{$linkAbs}\n";

        require_once __DIR__ . '/../includes/EmailService.php';
        $mail = new EmailService();
        $resCliente = $mail->enviarCorreo($emailCliente, $asunto, $cuerpoHtml, '', $cuerpoTxt, [], [], [], '');
        if ($emailVend !== null && strcasecmp($emailVend, $emailCliente) !== 0) {
            $cuerpoV = '<p>Se generó un enlace para que el cliente <strong>' . htmlspecialchars($nombreCli, ENT_QUOTES, 'UTF-8') . '</strong> vuelva a firmar la solicitud de financiamiento (registro #' . (int) $frId . ').</p>'
                . '<p>Enlace (caduca ' . htmlspecialchars($expLeg, ENT_QUOTES, 'UTF-8') . '):<br><a href="' . htmlspecialchars($linkAbs, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($linkAbs, ENT_QUOTES, 'UTF-8') . '</a></p>'
                . '<p style="font-size:13px;color:#555;">Mensaje automático.</p>';
            $mail->enviarCorreo($emailVend, $asunto . ' (notificación vendedor)', $cuerpoV, '', strip_tags($cuerpoV), [], [], [], '');
        }
        $okC = !empty($resCliente['success']);
        if (!$okC) {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'message' => 'El token se generó pero no se pudo enviar el correo al cliente: ' . ($resCliente['message'] ?? 'error'),
                'data' => ['expires_at' => $expiresSql, 'link' => $linkAbs],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Enlace generado y enviado al cliente' . ($emailVend && strcasecmp($emailVend, $emailCliente) !== 0 ? ' y al vendedor' : '') . '. Válido 30 minutos.',
            'data' => ['expires_at' => $expiresSql],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM financiamiento_registros WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // firma y firmantes_adicionales se envían completos para mostrar en el detalle
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        }
        exit;
    }

    if (isset($_GET['adjuntos_id']) && ctype_digit($_GET['adjuntos_id'])) {
        $frId = (int)$_GET['adjuntos_id'];
        $rows = sol_fin_obtener_adjuntos_por_registro($pdo, $frId);
        echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_GET['busqueda']) && trim((string)$_GET['busqueda']) !== '') {
        $term = '%' . trim($_GET['busqueda']) . '%';
        $rows = [];
        $frCols = sol_fin_fr_select_cols($pdo, 'fr');
        try {
            $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
            $stmt = $pdo->prepare("
                SELECT {$frCols},
                       {$adjCount} AS adjuntos_count
                FROM financiamiento_registros fr
                WHERE fr.cliente_nombre LIKE ? OR fr.cliente_id LIKE ? OR fr.cliente_correo LIKE ? OR fr.celular_cliente LIKE ?
                ORDER BY fr.fecha_creacion DESC
                LIMIT 20
            ");
            $stmt->execute([$term, $term, $term, $term]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('sol_financiamiento busqueda (sin conteo adjuntos): ' . $e->getMessage());
            $stmt = $pdo->prepare("
                SELECT {$frCols}
                FROM financiamiento_registros fr
                WHERE fr.cliente_nombre LIKE ? OR fr.cliente_id LIKE ? OR fr.cliente_correo LIKE ? OR fr.celular_cliente LIKE ?
                ORDER BY fr.fecha_creacion DESC
                LIMIT 20
            ");
            $stmt->execute([$term, $term, $term, $term]);
            $rows = sol_fin_adjuntar_cero_adjuntos($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    $limite = isset($_GET['limite']) && ctype_digit((string) $_GET['limite']) ? (int) $_GET['limite'] : 0;
    $solicitudExcluir = isset($_GET['solicitud_id']) && ctype_digit((string) $_GET['solicitud_id'])
        ? (int) $_GET['solicitud_id'] : 0;

    $rows = [];
    $frCols = sol_fin_fr_select_cols($pdo, 'fr');
    if ($limite > 0) {
        $limiteSql = min($limite, 1000);
        $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
        $sqlFiltrado = "
            SELECT {$frCols},
                   {$adjCount} AS adjuntos_count
            FROM financiamiento_registros fr
            WHERE NOT EXISTS (
                SELECT 1 FROM solicitudes_credito sc
                WHERE sc.financiamiento_registro_id IS NOT NULL
                  AND sc.financiamiento_registro_id = fr.id
                  AND (? = 0 OR sc.id <> ?)
            )
            ORDER BY fr.fecha_creacion DESC
            LIMIT {$limiteSql}
        ";
        try {
            $stmt = $pdo->prepare($sqlFiltrado);
            $stmt->execute([$solicitudExcluir, $solicitudExcluir]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Columna no migrada, tablas en otras bases, etc.: devolver listado sin filtrar.
            error_log('sol_financiamiento listado (fallback sin filtro vínculo solicitud): ' . $e->getMessage());
            try {
                $adjCountFb = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
                $sqlSimple = "
                    SELECT {$frCols},
                           {$adjCountFb} AS adjuntos_count
                    FROM financiamiento_registros fr
                    ORDER BY fr.fecha_creacion DESC
                    LIMIT {$limiteSql}
                ";
                $stmt = $pdo->query($sqlSimple);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                error_log('sol_financiamiento listado (sin conteo adjuntos): ' . $e2->getMessage());
                $sqlPlain = "
                    SELECT {$frCols}
                    FROM financiamiento_registros fr
                    ORDER BY fr.fecha_creacion DESC
                    LIMIT {$limiteSql}
                ";
                $stmt = $pdo->query($sqlPlain);
                $rows = sol_fin_adjuntar_cero_adjuntos($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    } else {
        try {
            $adjCountAll = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
            $sql = "
                SELECT {$frCols},
                       {$adjCountAll} AS adjuntos_count
                FROM financiamiento_registros fr
                ORDER BY fr.fecha_creacion DESC
            ";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('sol_financiamiento listado completo (sin conteo adjuntos): ' . $e->getMessage());
            $sql = "
                SELECT {$frCols}
                FROM financiamiento_registros fr
                ORDER BY fr.fecha_creacion DESC
            ";
            $stmt = $pdo->query($sql);
            $rows = sol_fin_adjuntar_cero_adjuntos($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('sol_financiamiento: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos', 'data' => []]);
}
