<?php
/**
 * Genera y descarga el PDF de borrador del formulario público de financiamiento
 * (misma plantilla que el envío al vendedor / api/sol_financiamiento_pdf.php).
 *
 * POST JSON: cuerpo igual al objeto "payload" de solicitud_publica.php (sin archivos).
 * No requiere sesión. Tamaño máximo del cuerpo: ~2 MB.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

if (ob_get_level()) {
    ob_end_clean();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Retry-After: 900');
    echo json_encode([
        'success' => false,
        'message' => motus_mantenimiento_mensaje(),
        'maintenance' => true,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Use POST con JSON']);
    exit;
}

$maxBytes = 2 * 1024 * 1024;
$raw = (string) file_get_contents('php://input');
if (strlen($raw) > $maxBytes) {
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Solicitud demasiado grande']);
    exit;
}

$decoded = $raw !== '' ? json_decode($raw, true) : null;
$row = is_array($decoded) ? $decoded : null;
if ($row === null) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'JSON no válido']);
    exit;
}

unset($row['__meta'], $row['__telemetria'], $row['token'], $row['imagen_cedula'], $row['acepta']);

$firmaBase64 = isset($row['firma']) ? trim((string) $row['firma']) : '';
unset($row['firma']);

if ($firmaBase64 !== '' && str_starts_with($firmaBase64, 'data:image')) {
    $parts = explode(',', $firmaBase64, 2);
    $firmaBase64 = isset($parts[1]) ? trim($parts[1]) : '';
}

// Misma consolidación que solicitud_publica.php para dirección
if (empty($row['prov_dist_corr'])) {
    $provincia = trim((string) ($row['provincia'] ?? ''));
    $distrito = trim((string) ($row['distrito'] ?? ''));
    $corregimiento = trim((string) ($row['corregimiento'] ?? ''));
    $row['prov_dist_corr'] = implode(', ', array_values(array_filter([$provincia, $distrito, $corregimiento], static function ($x) {
        return $x !== '';
    })));
}
if (empty($row['barriada_calle_casa'])) {
    $barriada = trim((string) ($row['barriada'] ?? ''));
    $calle = trim((string) ($row['calle'] ?? ''));
    $direccion = trim((string) ($row['direccion'] ?? ''));
    $row['barriada_calle_casa'] = implode(' - ', array_values(array_filter([$barriada, $calle, $direccion], static function ($x) {
        return $x !== '';
    })));
}
if (empty($row['edificio_apto'])) {
    $casaEdif = trim((string) ($row['casa_edif'] ?? ''));
    $numeroCasaApto = trim((string) ($row['numero_casa_apto'] ?? ''));
    $row['edificio_apto'] = implode(', ', array_values(array_filter([$casaEdif, $numeroCasaApto], static function ($x) {
        return $x !== '';
    })));
}

$nombre = trim((string) ($row['cliente_nombre'] ?? ''));
if ($nombre === '') {
    $nombre = 'Solicitud_Financiamiento';
}

require_once __DIR__ . '/../includes/pdf_financiamiento.php';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se encontró vendor/autoload.php. Ejecute composer install en la raíz del proyecto.';
    exit;
}
require_once $vendorAutoload;
if (!class_exists('Dompdf\Dompdf')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Instale dompdf: composer install';
    exit;
}

try {
    $html = buildPdfHtmlFinanciamiento($row, $firmaBase64, $nombre);
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nombreArchivo = 'Solicitud_Financiamiento_borrador_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre);
    $nombreArchivo = substr($nombreArchivo, 0, 110) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $dompdf->output();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el PDF']);
}
