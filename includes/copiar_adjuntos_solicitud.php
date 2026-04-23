<?php

/**
 * Copia adjuntos entre solicitudes (archivos en disco + filas en adjuntos_solicitud).
 */

function financiamiento_registros_tiene_columna(PDO $pdo, string $nombreColumna): bool {
    static $cache = [];
    if (array_key_exists($nombreColumna, $cache)) {
        return $cache[$nombreColumna];
    }
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            $cache[$nombreColumna] = false;
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'financiamiento_registros'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $nombreColumna]);
        $cache[$nombreColumna] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$nombreColumna] = false;
    }
    return $cache[$nombreColumna];
}

function solicitudes_credito_tiene_columna(PDO $pdo, string $nombreColumna): bool {
    static $cache = [];
    if (array_key_exists($nombreColumna, $cache)) {
        return $cache[$nombreColumna];
    }
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            $cache[$nombreColumna] = false;
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'solicitudes_credito'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $nombreColumna]);
        $cache[$nombreColumna] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$nombreColumna] = false;
    }
    return $cache[$nombreColumna];
}

function adjuntos_solicitud_tamano_columna(PDO $pdo): ?string {
    static $col = '__unset__';
    if ($col !== '__unset__') {
        return $col ?: null;
    }
    $col = null;
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'adjuntos_solicitud'
              AND COLUMN_NAME IN ('tamaño_archivo', 'tamano_archivo', 'tamao_archivo')
            LIMIT 1
        ");
        $stmt->execute([$dbName]);
        $found = $stmt->fetchColumn();
        $col = $found ?: null;
    } catch (Throwable $e) {
        $col = null;
    }
    return $col;
}

function adjuntos_solicitud_tiene_texto_extraido(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            $cache = false;
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'adjuntos_solicitud'
              AND COLUMN_NAME = 'texto_extraido'
        ");
        $stmt->execute([$dbName]);
        $cache = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * @return int número de adjuntos insertados
 */
function copiarAdjuntosEntreSolicitudes(PDO $pdo, int $desdeSolicitudId, int $hastaSolicitudId, int $usuarioId): int {
    if ($desdeSolicitudId <= 0 || $hastaSolicitudId <= 0 || $desdeSolicitudId === $hastaSolicitudId) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT * FROM adjuntos_solicitud WHERE solicitud_id = ? ORDER BY id ASC');
    $stmt->execute([$desdeSolicitudId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return 0;
    }

    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $dirRel = 'adjuntos/solicitudes/';
    $dirAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel);
    if (!is_dir($dirAbs)) {
        @mkdir($dirAbs, 0755, true);
    }

    $tamCol = adjuntos_solicitud_tamano_columna($pdo);
    $tieneTexto = adjuntos_solicitud_tiene_texto_extraido($pdo);
    $insertados = 0;

    foreach ($rows as $row) {
        $rutaRel = (string) ($row['ruta_archivo'] ?? '');
        if ($rutaRel === '') {
            continue;
        }
        $srcAbs = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($rutaRel, '/\\'));
        if (!is_file($srcAbs)) {
            continue;
        }

        $nombreOriginal = (string) ($row['nombre_original'] ?? 'adjunto');
        $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $nombreArchivo = 'cpy_' . uniqid('', true) . '_' . time() . $ext;
        $destAbs = rtrim($dirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (!@copy($srcAbs, $destAbs)) {
            continue;
        }

        $rutaDb = $dirRel . $nombreArchivo;
        $mime = (string) ($row['tipo_archivo'] ?? 'application/octet-stream');
        $size = (int) (@filesize($destAbs) ?: 0);
        $desc = isset($row['descripcion']) && $row['descripcion'] !== '' ? (string) $row['descripcion'] : null;
        $textoEx = $tieneTexto && !empty($row['texto_extraido']) ? (string) $row['texto_extraido'] : null;

        try {
            if ($tamCol) {
                if ($tieneTexto && $textoEx !== null) {
                    $ins = $pdo->prepare("
                        INSERT INTO adjuntos_solicitud
                        (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, `$tamCol`, descripcion, texto_extraido)
                        VALUES (?,?,?,?,?,?,?,?,?)
                    ");
                    $ins->execute([$hastaSolicitudId, $usuarioId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $size, $desc, $textoEx]);
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO adjuntos_solicitud
                        (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, `$tamCol`, descripcion)
                        VALUES (?,?,?,?,?,?,?,?)
                    ");
                    $ins->execute([$hastaSolicitudId, $usuarioId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $size, $desc]);
                }
            } else {
                if ($tieneTexto && $textoEx !== null) {
                    $ins = $pdo->prepare('
                        INSERT INTO adjuntos_solicitud
                        (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, descripcion, texto_extraido)
                        VALUES (?,?,?,?,?,?,?,?)
                    ');
                    $ins->execute([$hastaSolicitudId, $usuarioId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $desc, $textoEx]);
                } else {
                    $ins = $pdo->prepare('
                        INSERT INTO adjuntos_solicitud
                        (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, descripcion)
                        VALUES (?,?,?,?,?,?,?)
                    ');
                    $ins->execute([$hastaSolicitudId, $usuarioId, $nombreArchivo, $nombreOriginal, $rutaDb, $mime, $desc]);
                }
            }
            $insertados++;
        } catch (Throwable $e) {
            @unlink($destAbs);
            error_log('copiarAdjuntosEntreSolicitudes: ' . $e->getMessage());
        }
    }

    return $insertados;
}

/**
 * Orígenes: solicitud vinculada en el registro de financiamiento y/o otras solicitudes con el mismo financiamiento_registro_id.
 *
 * @return int total de adjuntos copiados
 */
function copiarAdjuntosDesdeRegistroFinanciamiento(PDO $pdo, int $finRegistroId, int $nuevaSolicitudId, int $usuarioId): int {
    if ($finRegistroId <= 0 || $nuevaSolicitudId <= 0) {
        return 0;
    }

    $origenes = [];

    if (financiamiento_registros_tiene_columna($pdo, 'solicitud_credito_id')) {
        try {
            $st = $pdo->prepare('SELECT solicitud_credito_id FROM financiamiento_registros WHERE id = ?');
            $st->execute([$finRegistroId]);
            $x = (int) $st->fetchColumn();
            if ($x > 0 && $x !== $nuevaSolicitudId) {
                $origenes[] = $x;
            }
        } catch (Throwable $e) {
            error_log('copiarAdjuntosDesdeRegistroFinanciamiento (solicitud_credito_id): ' . $e->getMessage());
        }
    }

    if (solicitudes_credito_tiene_columna($pdo, 'financiamiento_registro_id')) {
        try {
            $st2 = $pdo->prepare('SELECT id FROM solicitudes_credito WHERE financiamiento_registro_id = ? AND id <> ?');
            $st2->execute([$finRegistroId, $nuevaSolicitudId]);
            foreach ($st2->fetchAll(PDO::FETCH_COLUMN) as $oid) {
                $oid = (int) $oid;
                if ($oid > 0 && $oid !== $nuevaSolicitudId) {
                    $origenes[] = $oid;
                }
            }
        } catch (Throwable $e) {
            error_log('copiarAdjuntosDesdeRegistroFinanciamiento (financiamiento_registro_id): ' . $e->getMessage());
        }
    }

    // Fallback para instalaciones sin solicitud_credito_id:
    // ubica la solicitud origen creada por formulario público por cédula + (email o nombre).
    try {
        $st3 = $pdo->prepare("
            SELECT sc.id
            FROM solicitudes_credito sc
            INNER JOIN financiamiento_registros fr ON fr.id = ?
            WHERE sc.id <> ?
              AND sc.comentarios_gestor LIKE '%[Solicitud desde formulario público]%'
              AND fr.cliente_id IS NOT NULL AND fr.cliente_id <> ''
              AND sc.cedula = fr.cliente_id
              AND (
                    (fr.cliente_correo IS NOT NULL AND fr.cliente_correo <> '' AND sc.email = fr.cliente_correo)
                    OR
                    (fr.cliente_nombre IS NOT NULL AND fr.cliente_nombre <> '' AND sc.nombre_cliente = fr.cliente_nombre)
                  )
            ORDER BY sc.id DESC
            LIMIT 1
        ");
        $st3->execute([$finRegistroId, $nuevaSolicitudId]);
        $fallbackOrigen = (int) $st3->fetchColumn();
        if ($fallbackOrigen > 0 && $fallbackOrigen !== $nuevaSolicitudId) {
            $origenes[] = $fallbackOrigen;
        }
    } catch (Throwable $e) {
        error_log('copiarAdjuntosDesdeRegistroFinanciamiento (fallback por cedula/email): ' . $e->getMessage());
    }

    $origenes = array_values(array_unique($origenes));
    $total = 0;
    foreach ($origenes as $oid) {
        $total += copiarAdjuntosEntreSolicitudes($pdo, $oid, $nuevaSolicitudId, $usuarioId);
    }

    return $total;
}
