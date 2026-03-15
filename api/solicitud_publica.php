<?php
/**
 * API pública para crear solicitudes desde el formulario externo (sin login).
 * Solo acepta POST para crear una solicitud.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido',
        'error_detail' => 'Se recibió ' . ($_SERVER['REQUEST_METHOD'] ?? 'vacío') . '. Este endpoint solo acepta POST.'
    ]);
    exit();
}

$logFile = '/tmp/solicitud_publica_baes_log.txt';
$debugMode = (getenv('SOLICITUD_PUBLICA_DEBUG') === '1' || getenv('APP_DEBUG') === '1');
function logSolPub($msg) {
    @file_put_contents($GLOBALS['logFile'], date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function sendError500($message, $detail = null) {
    $payload = ['success' => false, 'message' => $message];
    if ($detail !== null) {
        $payload['error_detail'] = $detail;
    }
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode($payload);
    exit();
}

set_exception_handler(function (Throwable $e) {
    logSolPub('UNCAUGHT: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sendError500(
        'Error inesperado en el servidor.',
        $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
    );
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

logSolPub('start');

// Obtener body JSON si viene por fetch
$input = $_POST;
if (empty($input) || !is_array($input)) {
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    $input = is_array($decoded) ? $decoded : [];
}

// Quitar __meta del payload si existe
unset($input['__meta']);
$token = isset($input['token']) ? trim($input['token']) : '';
$token = $token !== '' ? urldecode($token) : '';
$firmaBase64 = isset($input['firma']) ? $input['firma'] : '';
unset($input['token'], $input['firma']);

// Validación mínima (no depende de base de datos)
$nombre = trim($input['cliente_nombre'] ?? $input['nombre_cliente'] ?? '');
$cedula = trim($input['cliente_id'] ?? $input['cedula'] ?? '');
if ($nombre === '' || $cedula === '') {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Nombre del cliente y cédula son obligatorios']);
    exit();
}

function getDefaultGestorId($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.activo = 1 AND r.nombre IN ('ROLE_ADMIN', 'ROLE_GESTOR')
            ORDER BY r.nombre = 'ROLE_ADMIN' DESC, u.id ASC
            LIMIT 1
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) return (int)$row['id'];
    } catch (PDOException $e) {
        // Si no existe tabla usuario_roles, usar fallback
    }
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE activo = 1 ORDER BY id ASC LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id'] : null;
}

function mapGenero($v) {
    if (empty($v)) return null;
    if (strtoupper($v) === 'M') return 'Masculino';
    if (strtoupper($v) === 'F') return 'Femenino';
    return 'Otro';
}

function toNum($v, $default = null) {
    if ($v === '' || $v === null) return $default;
    return is_numeric($v) ? (float)$v : $default;
}

function toInt($v, $default = null) {
    if ($v === '' || $v === null) return $default;
    return is_numeric($v) ? (int)$v : $default;
}

require_once __DIR__ . '/../includes/pdf_financiamiento.php';

$solicitudId = 0;
$emailEnviado = false;

// 1) Envío de correos financiamiento: PDF al vendedor (token) y copia al cliente (cliente_correo)
$emailDestinoVendedor = null;
if ($token !== '') {
    $decoded = @base64_decode(str_replace(['-', '_'], ['+', '/'], $token), true);
    if ($decoded === false) $decoded = @base64_decode($token, true);
    if (is_string($decoded) && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
        $emailDestinoVendedor = $decoded;
    }
}
$emailCliente = isset($input['cliente_correo']) && filter_var(trim($input['cliente_correo']), FILTER_VALIDATE_EMAIL) ? trim($input['cliente_correo']) : null;

if ($emailDestinoVendedor !== null || $emailCliente !== null) {
    try {
        $pdfPath = null;
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            if (class_exists('Dompdf\Dompdf')) {
                $html = buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombre);
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfPath = sys_get_temp_dir() . '/sol_fin_' . uniqid('', true) . '.pdf';
                file_put_contents($pdfPath, $dompdf->output());
            }
        }
        if ($pdfPath && file_exists($pdfPath)) {
            require_once __DIR__ . '/../includes/EmailService.php';
            $emailService = new EmailService();
            $asuntoVendedor = 'Solicitud de Financiamiento completada - ' . $nombre;
            $cuerpoVendedor = '<p>Se ha recibido una solicitud de financiamiento completada.</p><p><strong>Cliente:</strong> ' . htmlspecialchars($nombre) . '</p><p>Ver adjunto PDF con todos los datos y la firma.</p>';
            $asuntoCliente = 'Recibimos su Solicitud de Financiamiento - AutoMarket';
            $cuerpoCliente = '<p>Estimado/a ' . htmlspecialchars($nombre) . ',</p><p>Hemos recibido correctamente su solicitud de financiamiento. Adjunto encontrará una copia en PDF.</p><p>Nos pondremos en contacto a la brevedad.</p><p>— AutoMarket</p>';

            if ($emailDestinoVendedor !== null) {
                $result = $emailService->enviarCorreo($emailDestinoVendedor, $asuntoVendedor, $cuerpoVendedor, '', strip_tags($cuerpoVendedor), [$pdfPath]);
                $emailEnviado = !empty($result['success']);
            }
            if ($emailCliente !== null && $emailCliente !== $emailDestinoVendedor) {
                $resultCliente = $emailService->enviarCorreo($emailCliente, $asuntoCliente, $cuerpoCliente, '', strip_tags($cuerpoCliente), [$pdfPath]);
                if (!empty($resultCliente['success'])) $emailEnviado = true;
            }
            @unlink($pdfPath);
        }
    } catch (Exception $e) {
        error_log('solicitud_publica email: ' . $e->getMessage());
        logSolPub('email error: ' . $e->getMessage());
    }
}

// 2a) Guardar en financiamiento_registros. Usa financiamiento/config_db.php o, si no existe, config/database.php (misma base motus_baes).
$pdoReg = null;
$configDbPath = __DIR__ . '/../financiamiento/config_db.php';
if (is_file($configDbPath)) {
    try {
        require_once $configDbPath;
        $pdoReg = isset($pdo_financiamiento) && $pdo_financiamiento instanceof PDO ? $pdo_financiamiento : null;
    } catch (Throwable $e) {
        logSolPub('financiamiento config_db: ' . $e->getMessage());
    }
}
if (!$pdoReg && is_file(__DIR__ . '/../config/database.php')) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdoReg = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
    } catch (Throwable $e) {
        logSolPub('financiamiento database: ' . $e->getMessage());
    }
}
if ($pdoReg) {
    try {
        $v = function($key, $trim = true) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return $trim ? trim((string)$x) : $x;
        };
        $vNum = function($key) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return is_numeric($x) ? (float)$x : null;
        };
        $vInt = function($key) use ($input) {
            $x = $input[$key] ?? null;
            if ($x === null || $x === '') return null;
            return is_numeric($x) ? (int)$x : null;
        };
        $vDate = function($key) use ($input) {
            $x = trim((string)($input[$key] ?? ''));
            if ($x === '') return null;
            return $x;
        };
        $stmtReg = $pdoReg->prepare("
            INSERT INTO financiamiento_registros (
                token_email, ip,
                cliente_nombre, cliente_estado_civil, cliente_sexo, cliente_id, cliente_nacimiento, cliente_edad,
                cliente_nacionalidad, cliente_dependientes, cliente_correo, cliente_peso, cliente_estatura,
                vivienda, vivienda_monto, prov_dist_corr, tel_residencia, barriada_calle_casa, celular_cliente,
                edificio_apto, correo_residencial,
                empresa_nombre, empresa_ocupacion, empresa_anios, empresa_telefono, empresa_salario, empresa_direccion,
                otros_ingresos, ocupacion_otros, trabajo_anterior,
                tiene_conyuge, con_nombre, con_estado_civil, con_sexo, con_id, con_nacimiento, con_edad,
                con_nacionalidad, con_dependientes, con_correo, con_empresa, con_ocupacion, con_anios, con_tel,
                con_salario, con_direccion, con_otros_ingresos, con_trabajo_anterior,
                refp1_nombre, refp1_cel, refp1_dir_res, refp1_dir_lab,
                refp2_nombre, refp2_cel, refp2_dir_res, refp2_dir_lab,
                reff1_nombre, reff1_cel, reff1_dir_res, reff1_dir_lab,
                reff2_nombre, reff2_cel, reff2_dir_res, reff2_dir_lab,
                marca_auto, modelo_auto, anio_auto, kms_cod_auto, precio_venta, abono,
                sucursal, nombre_gestor, comentarios_gestor, firma, firmantes_adicionales
            ) VALUES (
                ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");
        $stmtReg->execute([
            $token ?: null, $_SERVER['REMOTE_ADDR'] ?? null,
            $v('cliente_nombre'), $v('cliente_estado_civil'), $v('cliente_sexo'), $v('cliente_id'), $vDate('cliente_nacimiento'), $vInt('cliente_edad'),
            $v('cliente_nacionalidad'), $vInt('cliente_dependientes'), $v('cliente_correo'), $vNum('cliente_peso'), $vNum('cliente_estatura'),
            $v('vivienda'), $vNum('vivienda_monto'), $v('prov_dist_corr'), $v('tel_residencia'), $v('barriada_calle_casa'), $v('celular_cliente'),
            $v('edificio_apto'), $v('correo_residencial'),
            $v('empresa_nombre'), $v('empresa_ocupacion'), $v('empresa_anios'), $v('empresa_telefono'), $vNum('empresa_salario'), $v('empresa_direccion'),
            $v('otros_ingresos'), $v('ocupacion_otros'), $v('trabajo_anterior'),
            !empty($input['tiene_conyuge']) ? 1 : 0,
            $v('con_nombre'), $v('con_estado_civil'), $v('con_sexo'), $v('con_id'), $vDate('con_nacimiento'), $vInt('con_edad'),
            $v('con_nacionalidad'), $vInt('con_dependientes'), $v('con_correo'), $v('con_empresa'), $v('con_ocupacion'), $v('con_anios'), $v('con_tel'),
            $vNum('con_salario'), $v('con_direccion'), $v('con_otros_ingresos'), $v('con_trabajo_anterior'),
            $v('refp1_nombre'), $v('refp1_cel'), $v('refp1_dir_res'), $v('refp1_dir_lab'),
            $v('refp2_nombre'), $v('refp2_cel'), $v('refp2_dir_res'), $v('refp2_dir_lab'),
            $v('reff1_nombre'), $v('reff1_cel'), $v('reff1_dir_res'), $v('reff1_dir_lab'),
            $v('reff2_nombre'), $v('reff2_cel'), $v('reff2_dir_res'), $v('reff2_dir_lab'),
            $v('marca_auto'), $v('modelo_auto'), $vInt('anio_auto'), $vInt('kms_cod_auto'), $vNum('precio_venta'), $vNum('abono'),
            $v('sucursal'), $v('nombre_gestor'), $v('comentarios_gestor'), $firmaBase64 ?: null, isset($input['firmantes_adicionales']) ? $input['firmantes_adicionales'] : null
        ]);
    } catch (PDOException $e) {
        logSolPub('financiamiento_registros: ' . $e->getMessage());
    } catch (Throwable $e) {
        logSolPub('financiamiento_registros: ' . $e->getMessage());
    }
}

// 2b) Opcional: guardar en solicitudes_credito (panel Motus). Requiere config/database.php e historial_helper
$configPath = __DIR__ . '/../config/database.php';
$historialPath = __DIR__ . '/../includes/historial_helper.php';
if (is_file($configPath) && is_file($historialPath)) {
    try {
        require_once $configPath;
        require_once $historialPath;
        if (isset($pdo) && $pdo instanceof PDO) {
            $gestorId = getDefaultGestorId($pdo);
            if ($gestorId) {
                // Construir comentarios_gestor con datos extra del wizard
                $extras = [];
                if (!empty($input['sucursal'])) $extras[] = 'Sucursal: ' . $input['sucursal'];
                if (!empty($input['nombre_gestor'])) $extras[] = 'Gestor indicado: ' . $input['nombre_gestor'];
                if (!empty($input['vivienda'])) $extras[] = 'Vivienda: ' . $input['vivienda'] . (isset($input['vivienda_monto']) && $input['vivienda_monto'] !== '' ? ' (Monto: ' . $input['vivienda_monto'] . ')' : '');
                if (!empty($input['cliente_nacionalidad'])) $extras[] = 'Nacionalidad: ' . $input['cliente_nacionalidad'];
                if (!empty($input['prov_dist_corr'])) $extras[] = 'Prov/Dist/Corr: ' . $input['prov_dist_corr'];
                if (!empty($input['empresa_direccion'])) $extras[] = 'Dirección laboral: ' . $input['empresa_direccion'];
                if (!empty($input['otros_ingresos'])) $extras[] = 'Otros ingresos: ' . $input['otros_ingresos'];
                if (!empty($input['trabajo_anterior'])) $extras[] = 'Trabajo anterior: ' . $input['trabajo_anterior'];
                if (!empty($input['tiene_conyuge']) && !empty($input['con_nombre'])) {
                    $extras[] = 'Cónyuge: ' . $input['con_nombre'] . ' | Cédula: ' . ($input['con_id'] ?? '') . ' | Empresa: ' . ($input['con_empresa'] ?? '') . ' | Salario: ' . ($input['con_salario'] ?? '');
                }
                $refs = [];
                if (!empty($input['refp1_nombre'])) $refs[] = 'Ref. Personal 1: ' . $input['refp1_nombre'] . ' ' . ($input['refp1_cel'] ?? '');
                if (!empty($input['refp2_nombre'])) $refs[] = 'Ref. Personal 2: ' . $input['refp2_nombre'] . ' ' . ($input['refp2_cel'] ?? '');
                if (!empty($input['reff1_nombre'])) $refs[] = 'Ref. Familiar 1: ' . $input['reff1_nombre'] . ' ' . ($input['reff1_cel'] ?? '');
                if (!empty($input['reff2_nombre'])) $refs[] = 'Ref. Familiar 2: ' . $input['reff2_nombre'] . ' ' . ($input['reff2_cel'] ?? '');
                if (!empty($refs)) $extras[] = 'Referencias: ' . implode('; ', $refs);
                $comentariosGestor = trim(($input['comentarios_gestor'] ?? '') . "\n\n[Solicitud desde formulario público]\n" . implode("\n", $extras));

                $casado = 0;
                if (!empty($input['cliente_estado_civil'])) {
                    $ec = $input['cliente_estado_civil'];
                    if (stripos($ec, 'Casado') !== false || stripos($ec, 'Unión') !== false) $casado = 1;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_credito (
                        gestor_id, tipo_persona, nombre_cliente, cedula, edad, genero,
                        telefono, telefono_principal, email, direccion, provincia, distrito, corregimiento,
                        barriada, casa_edif, numero_casa_apto, casado, hijos, perfil_financiero,
                        ingreso, tiempo_laborar, ocupacion, nombre_empresa_negocio,
                        marca_auto, modelo_auto, ao_auto, kilometraje, precio_especial, abono_monto,
                        comentarios_gestor
                    ) VALUES (?, 'Natural', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $gestorId, $nombre, $cedula, toInt($input['cliente_edad'] ?? null), mapGenero($input['cliente_sexo'] ?? null),
                    $input['tel_residencia'] ?? null, $input['celular_cliente'] ?? null, $input['cliente_correo'] ?? $input['correo_residencial'] ?? null,
                    $input['barriada_calle_casa'] ?? null, $input['prov_dist_corr'] ?? null, null, null, null,
                    $input['edificio_apto'] ?? null, $casado, toInt($input['cliente_dependientes'] ?? null, 0), 'Asalariado',
                    toNum($input['empresa_salario'] ?? null), isset($input['empresa_anios']) ? $input['empresa_anios'] . ' años' : null,
                    $input['empresa_ocupacion'] ?? null, $input['empresa_nombre'] ?? null,
                    $input['marca_auto'] ?? null, $input['modelo_auto'] ?? null, toInt($input['anio_auto'] ?? null), toInt($input['kms_cod_auto'] ?? null),
                    toNum($input['precio_venta'] ?? null), toNum($input['abono'] ?? null), $comentariosGestor
                ]);
                $solicitudId = (int) $pdo->lastInsertId();
                try {
                    $stmtNota = $pdo->prepare("INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido) VALUES (?, ?, 'Comentario', 'Solicitud desde formulario público', 'Solicitud enviada desde el formulario de financiamiento (sin login).')");
                    $stmtNota->execute([$solicitudId, $gestorId]);
                } catch (PDOException $e) { /* ignorar */ }
                try {
                    registrarHistorialSolicitud($pdo, $solicitudId, $gestorId, 'creacion', 'Solicitud enviada desde formulario público de financiamiento', null, 'Nueva');
                } catch (Throwable $e) { /* ignorar */ }
            }
        }
    } catch (Throwable $e) {
        logSolPub('DB optional fail: ' . $e->getMessage());
        error_log('solicitud_publica DB: ' . $e->getMessage());
    }
}

if (ob_get_level()) ob_end_clean();
echo json_encode([
    'success' => true,
    'message' => $emailEnviado
        ? 'Solicitud enviada correctamente. Se ha enviado una copia por correo.'
        : 'Solicitud enviada correctamente. Nos pondremos en contacto contigo.',
    'data' => ['id' => $solicitudId]
]);
exit();