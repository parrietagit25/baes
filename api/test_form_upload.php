<?php
/**
 * Prueba mínima de subida de archivo SIN JS + OCR.
 * - No requiere login.
 * - Guarda el archivo en /uploads_test/.
 * - Ejecuta OCR/pdftotext mediante OcrHelper y guarda el resultado
 *   en una tabla de pruebas (ocr_pruebas).
 * - Úsalo solo para diagnóstico; bórralo o protégelo después.
 */

// Mostrar errores en pantalla mientras diagnosticamos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/OcrHelper.php';

// Solo aceptar POST con archivo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido. Usa POST.';
    exit;
}

if (!isset($_FILES['archivo'])) {
    echo 'No se recibió ningún archivo en el campo "archivo".';
    exit;
}

$file = $_FILES['archivo'];

// Carpeta de pruebas (crear si no existe)
$uploadDir = __DIR__ . '/../uploads_test';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Nombre destino simple (timestamp + nombre original)
$destFileName = date('Ymd_His_') . basename($file['name']);
$destPath = $uploadDir . '/' . $destFileName;

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo 'Error al subir el archivo. Código: ' . $file['error'];
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo 'No se pudo mover el archivo al destino.';
    exit;
}

// Intentar extraer texto con el mismo helper que usas en adjuntos
$textoExtraido = '';
$rutaAbsoluta = realpath($destPath) ?: $destPath;
try {
    if (is_file($rutaAbsoluta)) {
        $mime = $file['type'] ?? mime_content_type($rutaAbsoluta) ?: 'application/octet-stream';
        $textoExtraido = OcrHelper::extraerTexto($rutaAbsoluta, $mime);
    }
} catch (Throwable $e) {
    error_log('OCR prueba: ' . $e->getMessage());
}

// Limitar tamaño por seguridad (igual que en adjuntos: 1 MB)
if ($textoExtraido !== '') {
    $maxLen = 1024 * 1024;
    if (strlen($textoExtraido) > $maxLen) {
        $textoExtraido = substr($textoExtraido, 0, $maxLen);
    }
}

// Guardar en tabla de pruebas (ocr_pruebas)
try {
    $stmt = $pdo->prepare("
        INSERT INTO ocr_pruebas
        (nombre_original, nombre_guardado, ruta_relativa, mime_type, size_bytes, texto_extraido, creado_en)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    // Ruta relativa pensada desde la raíz del proyecto
    $rutaRelativa = 'uploads_test/' . $destFileName;

    $stmt->execute([
        $file['name'] ?? '',
        $destFileName,
        $rutaRelativa,
        $file['type'] ?? '',
        $file['size'] ?? 0,
        $textoExtraido !== '' ? $textoExtraido : null,
    ]);
} catch (Throwable $e) {
    error_log('Insert ocr_pruebas fallo: ' . $e->getMessage());
}

// Respuesta simple en HTML para ver rápido el resultado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado prueba OCR</title>
</head>
<body>
    <h1>Resultado de la prueba OCR</h1>

    <p><strong>Archivo guardado en el servidor:</strong><br>
        <?php echo htmlspecialchars($destPath, ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Nombre original:</strong>
        <?php echo htmlspecialchars($file['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>MIME type:</strong>
        <?php echo htmlspecialchars($file['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

    <h2>Texto extraído (tabla de pruebas)</h2>
    <?php if ($textoExtraido !== ''): ?>
        <pre style="white-space: pre-wrap; word-break: break-word; background:#f5f5f5; padding:1rem; max-height:70vh; overflow:auto;">
<?php echo htmlspecialchars($textoExtraido, ENT_QUOTES, 'UTF-8'); ?>
        </pre>
    <?php else: ?>
        <p><em>No se extrajo texto (vacío o error en OCR).</em></p>
    <?php endif; ?>

    <p style="margin-top:2rem;">
        <a href="/baes/test_form_upload.html">← Volver al formulario de prueba</a>
    </p>
</body>
</html>

