<?php
/**
 * Lógica de reportes de reservas para subir_reporte_reservas.php (sin depender de AJAX / Cloudflare).
 */

function reporte_reservas_tabla_existe(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM reportes_reservas LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function reporte_reservas_lineas_existe(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM reportes_reservas_lineas LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function reporte_reservas_columnas_extendidas(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT estado FROM reportes_reservas LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function reporte_reservas_listar(PDO $pdo): array
{
    if (!reporte_reservas_tabla_existe($pdo)) {
        return [];
    }
    $colsExtra = reporte_reservas_columnas_extendidas($pdo)
        ? ', r.estado, r.filas_total, r.filas_aplicadas, r.filas_sin_coincidencia, r.fecha_procesado'
        : '';
    $stmt = $pdo->query("
        SELECT r.id, r.nombre_original, r.tamano_bytes, r.mime_type, r.fecha_subida
               {$colsExtra},
               u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM reportes_reservas r
        INNER JOIN usuarios u ON u.id = r.usuario_id
        ORDER BY r.fecha_subida DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reporte_reservas_listar_lineas(PDO $pdo, int $reporteId): array
{
    if ($reporteId <= 0 || !reporte_reservas_lineas_existe($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT id, fila_excel, nombre_cliente, cedula, correo_cliente, marca, modelo, anio,
               precio_total, abono_monto, solicitud_id, match_por, estado, mensaje
        FROM reportes_reservas_lineas
        WHERE reporte_id = ?
        ORDER BY fila_excel ASC
    ");
    $stmt->execute([$reporteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reporte_reservas_ruta_archivo(PDO $pdo, int $reporteId): ?string
{
    $stmt = $pdo->prepare('SELECT ruta_archivo FROM reportes_reservas WHERE id = ?');
    $stmt->execute([$reporteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['ruta_archivo']);
    return is_file($path) ? $path : null;
}

function reporte_reservas_importar(PDO $pdo, int $reporteId, int $usuarioId): array
{
    if (!reporte_reservas_lineas_existe($pdo)) {
        return ['success' => false, 'message' => 'Ejecute database/migracion_reportes_reservas_lineas.sql'];
    }
    $path = reporte_reservas_ruta_archivo($pdo, $reporteId);
    if (!$path) {
        return ['success' => false, 'message' => 'Archivo del reporte no encontrado'];
    }
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');
    require_once __DIR__ . '/ReservasProformaProcessor.php';
    $proc = new ReservasProformaProcessor($pdo, $reporteId, $usuarioId);
    return $proc->importarDesdeArchivo($path);
}

function reporte_reservas_procesar(PDO $pdo, int $reporteId, int $usuarioId): array
{
    if (!reporte_reservas_lineas_existe($pdo)) {
        return ['success' => false, 'message' => 'Ejecute database/migracion_reportes_reservas_lineas.sql'];
    }
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');
    require_once __DIR__ . '/ReservasProformaProcessor.php';
    $proc = new ReservasProformaProcessor($pdo, $reporteId, $usuarioId);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reportes_reservas_lineas WHERE reporte_id = ?');
    $stmt->execute([$reporteId]);
    if ((int) $stmt->fetchColumn() === 0) {
        $path = reporte_reservas_ruta_archivo($pdo, $reporteId);
        if ($path) {
            $imp = $proc->importarDesdeArchivo($path);
            if (!$imp['success']) {
                return $imp;
            }
        }
    }
    return $proc->procesarCoincidencias();
}

function reporte_reservas_eliminar(PDO $pdo, int $reporteId): array
{
    if ($reporteId <= 0) {
        return ['success' => false, 'message' => 'ID requerido'];
    }
    try {
        $stmt = $pdo->prepare('SELECT ruta_archivo FROM reportes_reservas WHERE id = ?');
        $stmt->execute([$reporteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }
        $pdo->prepare('DELETE FROM reportes_reservas WHERE id = ?')->execute([$reporteId]);
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['ruta_archivo']);
        if (is_file($path)) {
            @unlink($path);
        }
        return ['success' => true, 'message' => 'Reporte eliminado'];
    } catch (PDOException $e) {
        error_log('reporte_reservas eliminar pagina: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error al eliminar'];
    }
}

function reporte_reservas_flash_desde_resultado(array $resultado, string $tipoOk = 'success'): void
{
    $tipo = 'danger';
    if (!empty($resultado['success'])) {
        $tipo = (string) ($resultado['flash_tipo'] ?? $tipoOk);
    }
    $_SESSION['flash_reporte'] = [
        'tipo' => $tipo,
        'mensaje' => (string) ($resultado['message'] ?? ''),
    ];
}

function reporte_reservas_formatear_tamano($bytes): string
{
    $n = (int) $bytes;
    if ($n < 1024) {
        return $n . ' B';
    }
    if ($n < 1024 * 1024) {
        return number_format($n / 1024, 1) . ' KB';
    }
    return number_format($n / (1024 * 1024), 2) . ' MB';
}

function reporte_reservas_mensaje_error_subida(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'El archivo supera upload_max_filesize del servidor',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño permitido del formulario',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente',
        UPLOAD_ERR_NO_FILE => 'Debe seleccionar un archivo válido',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
        UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida',
    ];
    return $map[$code] ?? ('Error de subida (código ' . $code . ')');
}

/**
 * Guarda el archivo subido y registra en reportes_reservas (sin pasar por api/reporte_reservas.php).
 *
 * @param array<string, mixed> $file Entrada $_FILES['archivo']
 * @return array{success: bool, message: string, reporte_id?: int, ext?: string}
 */
function reporte_reservas_subir_archivo(PDO $pdo, int $usuarioId, array $file): array
{
    if (!reporte_reservas_tabla_existe($pdo)) {
        return ['success' => false, 'message' => 'La tabla reportes_reservas no existe. Ejecute database/migracion_reportes_reservas.sql'];
    }
    $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => reporte_reservas_mensaje_error_subida($uploadErr)];
    }

    $nombreOriginal = (string) ($file['name'] ?? 'reporte');
    $size = (int) ($file['size'] ?? 0);
    $mime = (string) ($file['type'] ?? '');
    $ext = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        return ['success' => false, 'message' => 'Formato no permitido. Use .xlsx o .csv'];
    }
    if ($size <= 0 || $size > 25 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El archivo debe ser mayor a 0 y menor de 25 MB'];
    }

    $baseDir = realpath(dirname(__DIR__) . '/adjuntos');
    if ($baseDir === false) {
        $baseDir = dirname(__DIR__) . '/adjuntos';
    }
    $dir = $baseDir . DIRECTORY_SEPARATOR . 'reportes_reservas';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['success' => false, 'message' => 'No se pudo crear el directorio adjuntos/reportes_reservas'];
    }

    try {
        $token = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $token = uniqid('rep_', true);
    }
    $nombreGuardado = $token . '.' . $ext;
    $rutaCompleta = $dir . DIRECTORY_SEPARATOR . $nombreGuardado;
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $rutaCompleta)) {
        return ['success' => false, 'message' => 'Error al guardar el archivo en el servidor'];
    }

    $rutaRelativa = 'adjuntos/reportes_reservas/' . $nombreGuardado;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reportes_reservas (nombre_original, ruta_archivo, tamano_bytes, mime_type, usuario_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nombreOriginal,
            $rutaRelativa,
            $size,
            $mime !== '' ? $mime : null,
            $usuarioId,
        ]);
        return [
            'success' => true,
            'message' => 'Archivo guardado correctamente',
            'reporte_id' => (int) $pdo->lastInsertId(),
            'ext' => $ext,
        ];
    } catch (PDOException $e) {
        @unlink($rutaCompleta);
        error_log('reporte_reservas_subir_archivo: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar el reporte en la base de datos'];
    }
}

/** Subida + importación de filas en una sola operación (formulario HTML). */
function reporte_reservas_subir_e_importar(PDO $pdo, int $usuarioId, array $file): array
{
    $sub = reporte_reservas_subir_archivo($pdo, $usuarioId, $file);
    if (!$sub['success'] || empty($sub['reporte_id'])) {
        return $sub;
    }
    $reporteId = (int) $sub['reporte_id'];
    $ext = (string) ($sub['ext'] ?? '');
    if (!in_array($ext, ['xlsx', 'csv'], true) || !reporte_reservas_lineas_existe($pdo)) {
        return [
            'success' => true,
            'message' => $sub['message'] . '. Importe las filas con el botón «Importar» o ejecute la migración de líneas.',
            'reporte_id' => $reporteId,
        ];
    }
    if (!class_exists('ZipArchive') && $ext === 'xlsx') {
        return [
            'success' => true,
            'message' => 'Archivo guardado. El servidor necesita la extensión PHP zip para leer .xlsx.',
            'reporte_id' => $reporteId,
        ];
    }
    $imp = reporte_reservas_importar($pdo, $reporteId, $usuarioId);
    if ($imp['success']) {
        return [
            'success' => true,
            'message' => $sub['message'] . ' ' . ($imp['message'] ?? ''),
            'reporte_id' => $reporteId,
        ];
    }
    return [
        'success' => true,
        'flash_tipo' => 'warning',
        'message' => 'Archivo guardado (#' . $reporteId . '), pero falló la importación: ' . ($imp['message'] ?? ''),
        'reporte_id' => $reporteId,
    ];
}
