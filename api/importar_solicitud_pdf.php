<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$roles = $_SESSION['user_roles'] ?? [];
if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_GESTOR', $roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../includes/OcrHelper.php';

function impPdfFirstMatch(string $text, array $patterns): ?string {
    foreach ($patterns as $p) {
        if (@preg_match($p, $text, $m) && !empty($m[1])) {
            return trim((string)$m[1]);
        }
    }
    return null;
}

function impPdfToNumber(?string $v): ?string {
    if ($v === null) return null;
    $x = trim($v);
    if ($x === '') return null;
    $x = str_replace([',', '$'], ['', ''], $x);
    return is_numeric($x) ? (string)$x : null;
}

try {
    if (!isset($_FILES['archivo_pdf'])) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un archivo PDF.']);
        exit;
    }
    $f = $_FILES['archivo_pdf'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir el archivo PDF.']);
        exit;
    }

    $nombreOriginal = (string)($f['name'] ?? 'solicitud.pdf');
    $tmp = (string)($f['tmp_name'] ?? '');
    $size = (int)($f['size'] ?? 0);
    $mime = (string)($f['type'] ?? '');

    if ($size <= 0 || $size > (10 * 1024 * 1024)) {
        echo json_encode(['success' => false, 'message' => 'El PDF debe pesar máximo 10MB.']);
        exit;
    }
    $ext = strtolower((string)pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($ext !== 'pdf' && $mime !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF.']);
        exit;
    }

    $baseDir = realpath(__DIR__ . '/..');
    if (!$baseDir) {
        throw new RuntimeException('No se pudo resolver la ruta base.');
    }
    $tmpDir = $baseDir . DIRECTORY_SEPARATOR . 'adjuntos' . DIRECTORY_SEPARATOR . 'tmp_import_pdf';
    if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        $tmpDir = rtrim((string)sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'baes_tmp_import_pdf';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No se pudo crear directorio temporal para importación.');
        }
    }

    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $token = md5(uniqid((string)mt_rand(), true));
    }
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
    if ($safeName === '' || $safeName === null) $safeName = 'solicitud.pdf';
    $destPdf = $tmpDir . DIRECTORY_SEPARATOR . $token . '.pdf';
    $destMeta = $tmpDir . DIRECTORY_SEPARATOR . $token . '.json';

    if (!move_uploaded_file($tmp, $destPdf)) {
        throw new RuntimeException('No se pudo guardar temporalmente el PDF en: ' . $tmpDir);
    }

    $texto = OcrHelper::extraerTexto($destPdf, 'application/pdf');
    $textoNorm = preg_replace('/\s+/u', ' ', (string)$texto);
    $textoNorm = trim((string)$textoNorm);

    if ($textoNorm === '' || mb_strlen($textoNorm, 'UTF-8') < 30) {
        @unlink($destPdf);
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo extraer texto del PDF. Verifique que sea legible o con texto seleccionable.'
        ]);
        exit;
    }

    $prefill = [
        'cliente_nombre' => impPdfFirstMatch($textoNorm, [
            '/NOMBRE Y APELLIDO:\s*(.*?)\s*(?:ESTADO CIVIL:|C[ÉE]DULA\/PASAPORTE\/RUC:)/iu'
        ]),
        'cliente_id' => impPdfFirstMatch($textoNorm, [
            '/C[ÉE]DULA\/PASAPORTE\/RUC:\s*(.*?)\s*(?:FECHA DE NACIMIENTO:|EDAD:)/iu'
        ]),
        'cliente_edad' => impPdfFirstMatch($textoNorm, [
            '/EDAD:\s*([0-9]{1,3})/iu'
        ]),
        'cliente_sexo' => impPdfFirstMatch($textoNorm, [
            '/SEXO:\s*(Masculino|Femenino|M|F|Otro)/iu'
        ]),
        'cliente_dependientes' => impPdfFirstMatch($textoNorm, [
            '/DEPENDIENTES:\s*([0-9]{1,2})/iu'
        ]),
        'cliente_correo' => impPdfFirstMatch($textoNorm, [
            '/CORREO(?: ELECTR[ÓO]NICO)?:\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/iu'
        ]),
        'celular_cliente' => impPdfFirstMatch($textoNorm, [
            '/CELULAR:\s*([0-9+\-\s()]{6,25})/iu'
        ]),
        'tel_residencia' => impPdfFirstMatch($textoNorm, [
            '/TEL[ÉE]FONO DE RESIDENCIA:\s*([0-9+\-\s()]{6,25})/iu'
        ]),
        'prov_dist_corr' => impPdfFirstMatch($textoNorm, [
            '/PROVINCIA,\s*DISTRITO,\s*CORREGIMIENTO:\s*(.*?)\s*(?:BARRIADA,\s*No\.\s*CALLE,\s*CASA No\.:|EDIFICIO,\s*APARTAMENTO No\.:)/iu'
        ]),
        'barriada_calle_casa' => impPdfFirstMatch($textoNorm, [
            '/BARRIADA,\s*No\.\s*CALLE,\s*CASA No\.:\s*(.*?)\s*(?:EDIFICIO,\s*APARTAMENTO No\.:|TEL[ÉE]FONO DE RESIDENCIA:)/iu'
        ]),
        'edificio_apto' => impPdfFirstMatch($textoNorm, [
            '/EDIFICIO,\s*APARTAMENTO No\.:\s*(.*?)\s*(?:TEL[ÉE]FONO DE RESIDENCIA:|CELULAR:)/iu'
        ]),
        'empresa_nombre' => impPdfFirstMatch($textoNorm, [
            '/NOMBRE DE LA EMPRESA:\s*(.*?)\s*(?:OCUPACI[ÓO]N:|A[ÑN]OS DE SERVICIO:)/iu'
        ]),
        'empresa_ocupacion' => impPdfFirstMatch($textoNorm, [
            '/OCUPACI[ÓO]N:\s*(.*?)\s*(?:A[ÑN]OS DE SERVICIO:|DIRECCI[ÓO]N:)/iu'
        ]),
        'empresa_anios' => impPdfFirstMatch($textoNorm, [
            '/A[ÑN]OS DE SERVICIO:\s*([0-9]{1,2})/iu'
        ]),
        'empresa_salario' => impPdfToNumber(impPdfFirstMatch($textoNorm, [
            '/SALARIO:\s*([$0-9.,]+)/iu'
        ])),
        'trabajo_anterior' => impPdfFirstMatch($textoNorm, [
            '/TRABAJO ANTERIOR SI TIENE MENOS DE 2 A[ÑN]OS:\s*(.*?)\s*(?:D\.|E\.|REFERENCIAS|$)/iu'
        ]),
        'marca_auto' => impPdfFirstMatch($textoNorm, [
            '/MARCA(?: DEL)? (?:AUTO|VEH[ÍI]CULO):\s*(.*?)\s*(?:MODELO:|A[ÑN]O:)/iu'
        ]),
        'modelo_auto' => impPdfFirstMatch($textoNorm, [
            '/MODELO:\s*(.*?)\s*(?:A[ÑN]O:|KMS?:|PRECIO)/iu'
        ]),
        'anio_auto' => impPdfFirstMatch($textoNorm, [
            '/A[ÑN]O:\s*([0-9]{4})/iu'
        ]),
        'kms_cod_auto' => impPdfFirstMatch($textoNorm, [
            '/(?:KMS?|KIL[ÓO]METRAJE):\s*([0-9.,]+)/iu'
        ]),
        'precio_venta' => impPdfToNumber(impPdfFirstMatch($textoNorm, [
            '/PRECIO(?: DE VENTA)?:\s*([$0-9.,]+)/iu'
        ])),
        'abono' => impPdfToNumber(impPdfFirstMatch($textoNorm, [
            '/ABONO:\s*([$0-9.,]+)/iu'
        ])),
    ];

    $meta = [
        'token' => $token,
        'user_id' => (int)$_SESSION['user_id'],
        'nombre_original' => $nombreOriginal,
        'mime' => 'application/pdf',
        'size' => $size,
        'created_at' => date('c'),
    ];
    @file_put_contents($destMeta, json_encode($meta, JSON_UNESCAPED_UNICODE));

    $tipoPersona = trim((string)($_POST['tipo_persona'] ?? ''));
    $perfilFinanciero = trim((string)($_POST['perfil_financiero'] ?? ''));

    echo json_encode([
        'success' => true,
        'message' => 'PDF procesado correctamente.',
        'data' => [
            'token' => $token,
            'nombre_original' => $nombreOriginal,
            'tipo_persona' => $tipoPersona,
            'perfil_financiero' => $perfilFinanciero,
            'prefill' => $prefill
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('importar_solicitud_pdf error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el PDF.',
        'error_detail' => $e->getMessage()
    ]);
}

