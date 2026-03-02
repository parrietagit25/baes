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

function buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombreCliente) {
    $h = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };
    $baseDir = dirname(__DIR__);
    $logoPath = $baseDir . '/img/seminuevos.jpg';
    $logoImg = '';
    if (is_file($logoPath)) {
        $logoData = @file_get_contents($logoPath);
        if ($logoData !== false) {
            $logoB64 = base64_encode($logoData);
            $logoImg = '<img src="data:image/jpeg;base64,' . $logoB64 . '" alt="AUTOMARKET SEMINUEVOS" style="height:52px;width:auto;display:block;" />';
        }
    }

    $secHeader = '#1e3a5f'; // azul oscuro como en la imagen
    $bloqueSec = function($titulo, $pares) use ($h, $secHeader) {
        $r = '<tr><td colspan="2" style="background:' . $secHeader . ';color:#fff;padding:6px 8px;font-weight:bold;font-size:11px;">' . $titulo . '</td></tr>';
        foreach ($pares as $k => $v) {
            if ((string)$v === '') continue;
            $r .= '<tr><td style="width:38%;padding:5px 8px;border-bottom:1px solid #ddd;">' . $k . '</td><td style="padding:5px 8px;border-bottom:1px solid #ddd;">' . $v . '</td></tr>';
        }
        return $r;
    };

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#000;margin:0;padding:0;width:100%;}
        .pdf-wrap{width:100%;}
        .pdf-title{font-size:14px;font-weight:bold;margin:0 0 6px 0;}
        .banner{background:' . $secHeader . ';color:#fff;padding:8px 10px;text-align:center;font-weight:bold;font-size:11px;margin:10px 0;width:100%;}
        table{width:100%;border-collapse:collapse;} td,th{border:1px solid #ccc;padding:5px 8px;text-align:left;}
        .firma{max-width:280px;max-height:120px;}
        .grid2{width:100%;border-collapse:collapse;font-size:10px;}
        .grid2 td{padding:4px 8px;border:1px solid #ddd;vertical-align:top;}
        .header-row{width:100%;border:none;}
        .header-row td{border:none;padding:0;vertical-align:top;}
        .header-left{width:100%;}
        .header-logo{text-align:right;white-space:nowrap;}
        .footer-note{font-size:8px;color:#555;margin-top:14px;line-height:1.3;}
    </style></head><body>';
    $html .= '<div class="pdf-wrap">';
    if ($logoImg !== '') {
        $html .= '<table class="header-row"><tr><td style="width:100%;border:none;padding:0;"></td><td class="header-logo" style="border:none;padding:0;vertical-align:top;">' . $logoImg . '</td></tr></table>';
    }
    $html .= '<div class="banner">Análisis de Perfil para Financiamientos de Bancos</div>';
    $html .= '<table>';

    $html .= $bloqueSec('A. INFORMACIÓN DEL CLIENTE:', [
        'NOMBRE Y APELLIDO:' => $h($input['cliente_nombre'] ?? ''),
        'ESTADO CIVIL:' => $h($input['cliente_estado_civil'] ?? ''),
        'CÉDULA/PASAPORTE/RUC:' => $h($input['cliente_id'] ?? ''),
        'FECHA DE NACIMIENTO:' => $h($input['cliente_nacimiento'] ?? ''),
        'EDAD:' => $h($input['cliente_edad'] ?? ''),
        'SEXO:' => $h($input['cliente_sexo'] ?? ''),
        'NACIONALIDAD:' => $h($input['cliente_nacionalidad'] ?? ''),
        'DEPENDIENTES:' => $h($input['cliente_dependientes'] ?? ''),
        'PESO (lbs):' => $h($input['cliente_peso'] ?? ''),
        'ESTATURA (m):' => $h($input['cliente_estatura'] ?? ''),
        'CORREO:' => $h($input['cliente_correo'] ?? ''),
    ]);

    $vivienda = $h($input['vivienda'] ?? '');
    if (isset($input['vivienda_monto']) && (string)$input['vivienda_monto'] !== '') {
        $vivienda .= ' — MONTO $: ' . $h($input['vivienda_monto']);
    }
    $html .= $bloqueSec('B. DIRECCIÓN RESIDENCIAL:', [
        'PROPIA / HIPOTECADA / ALQUILADA — MONTO $:' => $vivienda,
        'PROVINCIA, DISTRITO, CORREGIMIENTO:' => $h($input['prov_dist_corr'] ?? ''),
        'BARRIADA, No. CALLE, CASA No.:' => $h($input['barriada_calle_casa'] ?? ''),
        'EDIFICIO, APARTAMENTO No.:' => $h($input['edificio_apto'] ?? ''),
        'TELÉFONO DE RESIDENCIA:' => $h($input['tel_residencia'] ?? ''),
        'CELULAR:' => $h($input['celular_cliente'] ?? ''),
        'CORREO ELECTRÓNICO:' => $h($input['cliente_correo'] ?? $input['correo_residencial'] ?? ''),
    ]);

    $html .= $bloqueSec('C. INFORMACIÓN LABORAL:', [
        'NOMBRE DE LA EMPRESA:' => $h($input['empresa_nombre'] ?? ''),
        'OCUPACIÓN:' => $h($input['empresa_ocupacion'] ?? ''),
        'AÑOS DE SERVICIO:' => $h($input['empresa_anios'] ?? ''),
        'DIRECCIÓN:' => $h($input['empresa_direccion'] ?? ''),
        'TELÉFONO:' => $h($input['empresa_telefono'] ?? ''),
        'SALARIO:' => $h($input['empresa_salario'] ?? ''),
        'INDEPENDIENTE Y/O OTROS INGRESOS — OCUPACIÓN:' => $h($input['otros_ingresos'] ?? ''),
        'TRABAJO ANTERIOR SI TIENE MENOS DE 2 AÑOS:' => $h($input['trabajo_anterior'] ?? ''),
    ]);

    $hayConyuge = !empty($input['con_nombre']) || !empty($input['con_id']);
    if ($hayConyuge) {
        $html .= $bloqueSec('D. SOLICITANTE ADICIONAL Y/O CÓNYUGE:', [
            'NOMBRE Y APELLIDO:' => $h($input['con_nombre'] ?? ''),
            'ESTADO CIVIL:' => $h($input['con_estado_civil'] ?? ''),
            'CÉDULA/PASAPORTE/RUC:' => $h($input['con_id'] ?? ''),
            'FECHA DE NACIMIENTO:' => $h($input['con_nacimiento'] ?? ''),
            'EDAD:' => $h($input['con_edad'] ?? ''),
            'SEXO:' => $h($input['con_sexo'] ?? ''),
            'NACIONALIDAD:' => $h($input['con_nacionalidad'] ?? ''),
            'DEPENDIENTES:' => $h($input['con_dependientes'] ?? ''),
            'CORREO:' => $h($input['con_correo'] ?? ''),
            'NOMBRE DE LA EMPRESA:' => $h($input['con_empresa'] ?? ''),
            'OCUPACIÓN:' => $h($input['con_ocupacion'] ?? ''),
            'AÑOS DE SERVICIO:' => $h($input['con_anios'] ?? ''),
            'DIRECCIÓN:' => $h($input['con_direccion'] ?? ''),
            'TELÉFONO / CELULAR:' => $h($input['con_tel'] ?? ''),
            'SALARIO:' => $h($input['con_salario'] ?? ''),
            'INDEPENDIENTE Y/O OTROS INGRESOS — OCUPACIÓN:' => $h($input['con_otros_ingresos'] ?? ''),
            'TRABAJO ANTERIOR SI TIENE MENOS DE 2 AÑOS:' => $h($input['con_trabajo_anterior'] ?? ''),
        ]);
    }

    $html .= $bloqueSec('E. REFERENCIAS — 2 REF PERSONALES (NO PARIENTES):', [
        '1. NOMBRE COMPLETO:' => $h($input['refp1_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['refp1_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['refp1_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['refp1_cel'] ?? ''),
        '2. NOMBRE COMPLETO:' => $h($input['refp2_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['refp2_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['refp2_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['refp2_cel'] ?? ''),
    ]);
    $html .= $bloqueSec('2 REF FAMILIARES (QUE NO VIVAN CON USTED):', [
        '1. NOMBRE COMPLETO:' => $h($input['reff1_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['reff1_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['reff1_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['reff1_cel'] ?? ''),
        '2. NOMBRE COMPLETO:' => $h($input['reff2_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['reff2_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['reff2_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['reff2_cel'] ?? ''),
    ]);

    if ($firmaBase64 !== '') {
        $html .= '<tr><td colspan="2" style="background:#eee;padding:6px 8px;font-weight:bold">Firma del solicitante</td></tr>';
        $html .= '<tr><td colspan="2" style="padding:10px;"><img class="firma" src="data:image/png;base64,' . $firmaBase64 . '" alt="Firma"/></td></tr>';
    }
    $firmantesExtra = isset($input['firmantes_adicionales']) ? $input['firmantes_adicionales'] : '';
    if ($firmantesExtra !== '') {
        $lista = @json_decode($firmantesExtra, true);
        if (is_array($lista)) {
            foreach ($lista as $fa) {
                $nom = isset($fa['nombre']) ? $h($fa['nombre']) : '';
                $img = isset($fa['firma']) && $fa['firma'] !== '' ? $fa['firma'] : null;
                if ($nom !== '' && $img !== null) {
                    $html .= '<tr><td colspan="2" style="background:#eee;padding:6px 8px;font-weight:bold">Firma: ' . $nom . '</td></tr>';
                    $html .= '<tr><td colspan="2" style="padding:10px;"><img class="firma" src="data:image/png;base64,' . $img . '" alt="Firma ' . $nom . '"/></td></tr>';
                }
            }
        }
    }
    $html .= '</table>';
    $html .= '<p class="footer-note">El solicitante autoriza a PANAMA CAR RENTAL, S.A. y a las instituciones financieras con las que tramite (MULTIBANK, INC., THE BANK OF NOVA SCOTIA (PANAMÁ), S.A., BANCO GENERAL, S.A., GLOBAL BANK CORPORATION, BAC International Bank, Inc., BANISTMO, S.A., BANCO DELTA, S.A., BANESCO (Panamá), S.A., BANISI, S.A., MULTIFINANCIAMIENTOS, S.A., entre otras) a verificar la información proporcionada y a consultar bureaus de crédito según corresponda.</p>';
    $html .= '</div></body></html>';
    return $html;
}

$solicitudId = 0;
$emailEnviado = false;

// 1) Enviar PDF por correo si hay token (no requiere base de datos)
if ($token !== '') {
    try {
        $emailDestino = @base64_decode(str_replace(['-', '_'], ['+', '/'], $token), true);
        if ($emailDestino === false) $emailDestino = @base64_decode($token, true);
        if (is_string($emailDestino) && filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
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
                $config = require __DIR__ . '/../config/email.php';
                $fromEmail = $config['from_email'] ?? 'noreply@ejemplo.com';
                $fromName = $config['from_name'] ?? 'Solicitud de Crédito';
                require_once __DIR__ . '/../includes/EmailService.php';
                $emailService = new EmailService();
                $asunto = 'Solicitud de Financiamiento completada - ' . $nombre;
                $cuerpo = '<p>Se ha recibido una solicitud de financiamiento completada.</p><p><strong>Cliente:</strong> ' . htmlspecialchars($nombre) . '</p><p>Ver adjunto PDF con todos los datos y la firma.</p>';
                $result = $emailService->enviarCorreo($emailDestino, '', $asunto, $cuerpo, strip_tags($cuerpo), [$pdfPath]);
                @unlink($pdfPath);
                $emailEnviado = !empty($result['success']);
            }
        }
    } catch (Exception $e) {
        error_log('solicitud_publica email: ' . $e->getMessage());
        logSolPub('email error: ' . $e->getMessage());
    }
}

// 2) Opcional: guardar en base de datos (si falla, no bloqueamos; el correo ya pudo enviarse)
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
                        marca_auto, modelo_auto, año_auto, kilometraje, precio_especial, abono_monto,
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
        ? 'Solicitud enviada correctamente. Hemos enviado una copia por correo al vendedor.'
        : 'Solicitud enviada correctamente. Nos pondremos en contacto contigo.',
    'data' => ['id' => $solicitudId]
]);
exit();