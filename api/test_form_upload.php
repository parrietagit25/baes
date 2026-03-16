<?php
/**
 * Prueba mínima de subida de archivo SIN JS.
 * - No requiere login.
 * - Solo intenta guardar el archivo en /uploads_test/.
 * - Úsalo solo para diagnóstico; bórralo o protégelo después.
 */

// Mostrar errores en pantalla mientras diagnosticamos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$destPath = $uploadDir . '/' . date('Ymd_His_') . basename($file['name']);

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo 'Error al subir el archivo. Código: ' . $file['error'];
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo 'No se pudo mover el archivo al destino.';
    exit;
}

echo "Archivo guardado correctamente en: " . htmlspecialchars($destPath, ENT_QUOTES, 'UTF-8');

