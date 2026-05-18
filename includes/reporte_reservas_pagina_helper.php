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
    $_SESSION['flash_reporte'] = [
        'tipo' => !empty($resultado['success']) ? $tipoOk : 'danger',
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
