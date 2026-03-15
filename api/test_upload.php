<?php
/**
 * Prueba de subida: identificar si el 403 viene de nginx, Apache o la aplicación.
 * NO requiere login. Borrar o restringir en producción.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Test-Upload: 1');

$method = $_SERVER['REQUEST_METHOD'];
$out = [
    'ok' => true,
    'mensaje' => '',
    'diagnostico' => [],
    'php_limits' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
    ],
    'request' => [
        'method' => $method,
        'content_type' => $_SERVER['HTTP_CONTENT_TYPE'] ?? '(no enviado)',
        'content_length' => $_SERVER['HTTP_CONTENT_LENGTH'] ?? '(no enviado)',
    ],
];

// GET: comprobar que la petición llega a PHP
if ($method === 'GET') {
    $out['mensaje'] = 'GET llegó a PHP. Nginx y Apache permiten esta ruta.';
    $out['diagnostico'][] = 'Si ves esto, las peticiones GET a /api/ funcionan.';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// POST sin archivo: comprobar que POST está permitido
if ($method === 'POST') {
    $out['diagnostico'][] = 'POST llegó a PHP.';

    if (empty($_SERVER['HTTP_CONTENT_TYPE']) || strpos($_SERVER['HTTP_CONTENT_TYPE'], 'multipart/form-data') === false) {
        $out['mensaje'] = 'POST sin multipart llegó a PHP. Nginx/Apache permiten POST.';
        $out['diagnostico'][] = 'Prueba ahora POST con archivo (multipart/form-data) para ver si el 403 es solo por subida.';
        $out['post_body_size'] = strlen(file_get_contents('php://input'));
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST con multipart (subida de archivo)
    $out['mensaje'] = 'POST con multipart/form-data llegó a PHP. La subida está permitida hasta este punto.';
    $out['diagnostico'][] = 'Si ves esto al subir un archivo, el 403 NO viene de nginx/Apache para esta URL.';
    $out['files_received'] = [];
    $out['post_keys'] = array_keys($_POST);

    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $f) {
            $out['files_received'][] = [
                'name' => $f['name'] ?? '',
                'size' => $f['size'] ?? 0,
                'type' => $f['type'] ?? '',
                'error' => $f['error'] ?? 0,
                'error_msg' => _upload_error_msg($f['error'] ?? 0),
            ];
        }
    } else {
        $out['diagnostico'][] = '$_FILES vacío: puede ser límite post_max_size superado o cuerpo no enviado como multipart.';
    }

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Solo GET o POST'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

function _upload_error_msg($code) {
    $codes = [0 => 'OK', 1 => 'UPLOAD_ERR_INI_SIZE', 2 => 'UPLOAD_ERR_FORM_SIZE', 3 => 'UPLOAD_ERR_PARTIAL', 4 => 'UPLOAD_ERR_NO_FILE', 6 => 'UPLOAD_ERR_NO_TMP_DIR', 7 => 'UPLOAD_ERR_CANT_WRITE', 8 => 'UPLOAD_ERR_EXTENSION'];
    return $codes[$code] ?? 'unknown';
}
