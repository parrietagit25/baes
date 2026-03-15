<?php
/**
 * Genera y descarga el PDF de un registro de financiamiento_registros (mismo que se envía por correo).
 * GET id=... — Solo Admin y Gestor con sesión.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No autorizado';
    exit;
}

$userRoles = $_SESSION['user_roles'] ?? [];
if (!in_array('ROLE_ADMIN', $userRoles) && !in_array('ROLE_GESTOR', $userRoles)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acceso denegado';
    exit;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '' || !ctype_digit($id)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ID de registro no válido';
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pdf_financiamiento.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM financiamiento_registros WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Registro no encontrado';
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error de base de datos';
    exit;
}

$nombre = isset($row['cliente_nombre']) ? trim($row['cliente_nombre']) : 'Solicitud';
$firmaBase64 = isset($row['firma']) ? $row['firma'] : '';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se encontró vendor/autoload.php. En la raíz del proyecto ejecute: composer install' . "\n";
    echo '(Si usa Docker: docker exec -it <contenedor_php> composer install --working-dir=/ruta/del/proyecto)';
    exit;
}
require_once $vendorAutoload;
if (!class_exists('Dompdf\Dompdf')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El paquete dompdf/dompdf no está instalado. En la raíz del proyecto ejecute: composer install';
    exit;
}

$html = buildPdfHtmlFinanciamiento($row, $firmaBase64, $nombre);
$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombreArchivo = 'Solicitud_Financiamiento_' . $id . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre);
$nombreArchivo = substr($nombreArchivo, 0, 116) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
echo $dompdf->output();
