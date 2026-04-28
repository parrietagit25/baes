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
 * Expresión SQL correlacionada: cantidad de adjuntos vinculados al registro fr.
 * Evita IN(SELECT … UNION …) (puede fallar o comportarse distinto según versión MySQL/MariaDB).
 */
function sol_fin_sql_adjuntos_count(PDO $pdo, string $frIdRef): string {
    $condiciones = [];
    $tieneScFinReg = sol_fin_sc_tiene_financiamiento_registro_id($pdo);
    $tieneFrSolCred = sol_fin_fr_tiene_solicitud_credito_id($pdo);

    if ($tieneScFinReg) {
        $condiciones[] = "a.solicitud_id IN (SELECT sc.id FROM solicitudes_credito sc WHERE sc.financiamiento_registro_id = {$frIdRef})";
    }

    if ($tieneFrSolCred) {
        $condiciones[] = "a.solicitud_id = (SELECT frc.solicitud_credito_id FROM financiamiento_registros frc WHERE frc.id = {$frIdRef} AND frc.solicitud_credito_id IS NOT NULL)";
    }

    // Heurística de respaldo para instalaciones sin columnas de vínculo o con datos históricos.
    // No depende del marcador en comentarios (puede truncarse en algunas instalaciones).
    $condiciones[] = "a.solicitud_id IN (
        SELECT sc.id
        FROM solicitudes_credito sc
        LEFT JOIN financiamiento_registros frx ON frx.id = {$frIdRef}
        WHERE frx.cliente_id IS NOT NULL AND frx.cliente_id <> ''
          AND REPLACE(REPLACE(UPPER(COALESCE(sc.cedula,'')),'-',''),' ','') =
              REPLACE(REPLACE(UPPER(COALESCE(frx.cliente_id,'')),'-',''),' ','')
          AND (
                frx.fecha_creacion IS NULL
                OR sc.fecha_creacion IS NULL
                OR ABS(TIMESTAMPDIFF(DAY, sc.fecha_creacion, frx.fecha_creacion)) <= 30
          )
    )";

    if (!$condiciones) {
        return "0";
    }

    $expr = "(SELECT COUNT(*) FROM adjuntos_solicitud a WHERE " . implode(' OR ', $condiciones) . ")";
    if (sol_fin_tiene_tabla_adjuntos_fin_reg($pdo)) {
        $expr .= " + (SELECT COUNT(*) FROM adjuntos_financiamiento_registros afr WHERE afr.financiamiento_registro_id = {$frIdRef})";
    }
    return $expr;
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

    $orConds = [];
    $params = [];
    $tieneScFinReg = sol_fin_sc_tiene_financiamiento_registro_id($pdo);
    $tieneFrSolCred = sol_fin_fr_tiene_solicitud_credito_id($pdo);

    if ($tieneScFinReg) {
        $orConds[] = "s.id IN (SELECT sc.id FROM solicitudes_credito sc WHERE sc.financiamiento_registro_id = ?)";
        $params[] = $frId;
    }
    if ($tieneFrSolCred && !empty($fr['solicitud_credito_id'])) {
        $orConds[] = "s.id = ?";
        $params[] = (int)$fr['solicitud_credito_id'];
    }
    if (!empty($fr['cliente_id'])) {
        $fallback = "
            s.id IN (
                SELECT sc2.id
                FROM solicitudes_credito sc2
                INNER JOIN financiamiento_registros fr2 ON fr2.id = ?
                WHERE REPLACE(REPLACE(UPPER(COALESCE(sc2.cedula,'')),'-',''),' ','') =
                      REPLACE(REPLACE(UPPER(COALESCE(?,'')),'-',''),' ','')
        ";
        $params[] = $frId;
        $params[] = (string)$fr['cliente_id'];
        $fallback .= "
                AND (
                    fr2.fecha_creacion IS NULL
                    OR sc2.fecha_creacion IS NULL
                    OR ABS(TIMESTAMPDIFF(DAY, sc2.fecha_creacion, fr2.fecha_creacion)) <= 30
                )
        ";
        $fallback .= ")";
        $orConds[] = $fallback;
    }

    $rows = [];
    if ($orConds) {
        $sql = "
            SELECT
                a.id,
                a.solicitud_id,
                a.nombre_original,
                a.ruta_archivo,
                a.tipo_archivo,
                a.fecha_subida
            FROM adjuntos_solicitud a
            INNER JOIN solicitudes_credito s ON s.id = a.solicitud_id
            WHERE " . implode(' OR ', $orConds) . "
            ORDER BY a.fecha_subida DESC, a.id DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (sol_fin_tiene_tabla_adjuntos_fin_reg($pdo)) {
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
            $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $rows = array_merge($rows, $rows2);
        } catch (Throwable $e) {
            error_log('sol_fin_obtener_adjuntos_por_registro adjuntos_fin_reg: ' . $e->getMessage());
        }
    }

    usort($rows, static function($a, $b) {
        return strcmp((string)($b['fecha_subida'] ?? ''), (string)($a['fecha_subida'] ?? ''));
    });
    return $rows;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador puede borrar registros.']);
            exit;
        }
        $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
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
        try {
            $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
            $stmt = $pdo->prepare("
                SELECT fr.id, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
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
                SELECT fr.id, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente
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
    if ($limite > 0) {
        $limiteSql = min($limite, 1000);
        $adjCount = sol_fin_sql_adjuntos_count($pdo, 'fr.id');
        $sqlFiltrado = "
            SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                   fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
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
                    SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                           fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
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
                    SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                           fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta
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
                SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                       fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta,
                       {$adjCountAll} AS adjuntos_count
                FROM financiamiento_registros fr
                ORDER BY fr.fecha_creacion DESC
            ";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('sol_financiamiento listado completo (sin conteo adjuntos): ' . $e->getMessage());
            $sql = "
                SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_id, fr.cliente_correo, fr.celular_cliente,
                       fr.empresa_nombre, fr.empresa_salario, fr.marca_auto, fr.modelo_auto, fr.anio_auto, fr.precio_venta
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
