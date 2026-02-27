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
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$logFile = '/tmp/solicitud_publica_baes_log.txt';
function logSolPub($msg) {
    @file_put_contents($GLOBALS['logFile'], date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
logSolPub('start');

$configPath = __DIR__ . '/../config/database.php';
$historialPath = __DIR__ . '/../includes/historial_helper.php';
if (!is_file($configPath) || !is_file($historialPath)) {
    logSolPub('missing file: config=' . (is_file($configPath) ? 'ok' : $configPath) . ' historial=' . (is_file($historialPath) ? 'ok' : $historialPath));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor.']);
    exit();
}
try {
    require_once $configPath;
    require_once $historialPath;
    logSolPub('require ok');
} catch (Throwable $e) {
    logSolPub('require fail: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al conectar con el servidor.']);
    exit();
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    logSolPub('pdo no definido');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor.']);
    exit();
}
logSolPub('pdo ok');

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

function getDefaultGestorId($pdo) {
    $stmt = $pdo->query("
        SELECT u.id FROM usuarios u
        INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
        INNER JOIN roles r ON r.id = ur.rol_id
        WHERE u.activo = 1 AND r.nombre IN ('ROLE_ADMIN', 'ROLE_GESTOR')
        ORDER BY r.nombre = 'ROLE_ADMIN' DESC, u.id ASC
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $bloque = function($titulo, $pares) {
        $r = '<tr><td colspan="2" style="background:#eee;padding:6px;font-weight:bold">' . $titulo . '</td></tr>';
        foreach ($pares as $k => $v) {
            if ((string)$v === '') continue;
            $r .= '<tr><td style="width:35%">' . $k . '</td><td>' . $v . '</td></tr>';
        }
        return $r;
    };
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;} table{width:100%;border-collapse:collapse;} td,th{border:1px solid #ccc;padding:6px;text-align:left;} .firma{max-width:280px;max-height:120px;}</style></head><body>';
    $html .= '<h1>Solicitud de Financiamiento</h1><p>Cliente: ' . $h($nombreCliente) . '</p>';
    $html .= '<table>';
    $html .= $bloque('1. Generales', [
        'Sucursal' => $h($input['sucursal'] ?? ''),
        'Nombre gestor' => $h($input['nombre_gestor'] ?? ''),
        'Marca/Modelo/Año auto' => $h(($input['marca_auto'] ?? '') . ' ' . ($input['modelo_auto'] ?? '') . ' ' . ($input['anio_auto'] ?? '')),
        'KMS / Cód. auto' => $h($input['kms_cod_auto'] ?? ''),
        'Precio venta (USD)' => $h($input['precio_venta'] ?? ''),
        'Abono (USD)' => $h($input['abono'] ?? ''),
    ]);
    $html .= $bloque('2. Cliente', [
        'Nombre' => $h($input['cliente_nombre'] ?? ''),
        'Estado civil / Sexo' => $h(($input['cliente_estado_civil'] ?? '') . ' / ' . ($input['cliente_sexo'] ?? '')),
        'Cédula' => $h($input['cliente_id'] ?? ''),
        'Fecha nacimiento / Edad' => $h(($input['cliente_nacimiento'] ?? '') . ' / ' . ($input['cliente_edad'] ?? '')),
        'Nacionalidad' => $h($input['cliente_nacionalidad'] ?? ''),
        'Dependientes' => $h($input['cliente_dependientes'] ?? ''),
        'Correo' => $h($input['cliente_correo'] ?? ''),
    ]);
    $html .= $bloque('3. Dirección', [
        'Vivienda' => $h(($input['vivienda'] ?? '') . (isset($input['vivienda_monto']) && $input['vivienda_monto'] !== '' ? ' - ' . $input['vivienda_monto'] . ' USD' : '')),
        'Provincia, Distrito, Corregimiento' => $h($input['prov_dist_corr'] ?? ''),
        'Barriada, calle, casa' => $h($input['barriada_calle_casa'] ?? ''),
        'Edificio/Apto' => $h($input['edificio_apto'] ?? ''),
        'Tel. residencia' => $h($input['tel_residencia'] ?? ''),
        'Celular' => $h($input['celular_cliente'] ?? ''),
    ]);
    $html .= $bloque('4. Laboral', [
        'Empresa' => $h($input['empresa_nombre'] ?? ''),
        'Ocupación' => $h($input['empresa_ocupacion'] ?? ''),
        'Años servicio' => $h($input['empresa_anios'] ?? ''),
        'Salario (USD)' => $h($input['empresa_salario'] ?? ''),
        'Dirección' => $h($input['empresa_direccion'] ?? ''),
        'Otros ingresos' => $h($input['otros_ingresos'] ?? ''),
        'Trabajo anterior' => $h($input['trabajo_anterior'] ?? ''),
    ]);
    $html .= $bloque('5. Referencias', [
        'Ref. Personal 1' => $h(($input['refp1_nombre'] ?? '') . ' - ' . ($input['refp1_cel'] ?? '')),
        'Ref. Personal 2' => $h(($input['refp2_nombre'] ?? '') . ' - ' . ($input['refp2_cel'] ?? '')),
        'Ref. Familiar 1' => $h(($input['reff1_nombre'] ?? '') . ' - ' . ($input['reff1_cel'] ?? '')),
        'Ref. Familiar 2' => $h(($input['reff2_nombre'] ?? '') . ' - ' . ($input['reff2_cel'] ?? '')),
    ]);
    if ($firmaBase64 !== '') {
        $html .= '<tr><td colspan="2" style="background:#eee;padding:6px;font-weight:bold">Firma del solicitante</td></tr>';
        $html .= '<tr><td colspan="2"><img class="firma" src="data:image/png;base64,' . $firmaBase64 . '" alt="Firma"/></td></tr>';
    }
    $html .= '</table></body></html>';
    return $html;
}

try {
    logSolPub('get gestor');
    $gestorId = getDefaultGestorId($pdo);
    logSolPub('gestorId=' . ($gestorId ?: 'null'));
    if (!$gestorId) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No hay gestor configurado para recibir solicitudes']);
        exit();
    }

    // Campos obligatorios mínimos
    $nombre = trim($input['cliente_nombre'] ?? $input['nombre_cliente'] ?? '');
    $cedula = trim($input['cliente_id'] ?? $input['cedula'] ?? '');
    if ($nombre === '' || $cedula === '') {
        echo json_encode(['success' => false, 'message' => 'Nombre del cliente y cédula son obligatorios']);
        exit();
    }

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

    logSolPub('insert');
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
        $gestorId,
        $nombre,
        $cedula,
        toInt($input['cliente_edad'] ?? null),
        mapGenero($input['cliente_sexo'] ?? null),
        $input['tel_residencia'] ?? null,
        $input['celular_cliente'] ?? null,
        $input['cliente_correo'] ?? $input['correo_residencial'] ?? null,
        $input['barriada_calle_casa'] ?? null,
        $input['prov_dist_corr'] ?? null, // guardamos en provincia el texto completo si no separamos
        null,
        null,
        null,
        $input['edificio_apto'] ?? null,
        $casado,
        toInt($input['cliente_dependientes'] ?? null, 0),
        'Asalariado',
        toNum($input['empresa_salario'] ?? null),
        isset($input['empresa_anios']) ? $input['empresa_anios'] . ' años' : null,
        $input['empresa_ocupacion'] ?? null,
        $input['empresa_nombre'] ?? null,
        $input['marca_auto'] ?? null,
        $input['modelo_auto'] ?? null,
        toInt($input['anio_auto'] ?? null),
        toInt($input['kms_cod_auto'] ?? null),
        toNum($input['precio_venta'] ?? null),
        toNum($input['abono'] ?? null),
        $comentariosGestor
    ]);

    $solicitudId = (int) $pdo->lastInsertId();

    // Nota inicial (opcional: si falla no bloqueamos)
    try {
        $stmtNota = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
            VALUES (?, ?, 'Comentario', 'Solicitud desde formulario público', 'Solicitud enviada desde el formulario de financiamiento (sin login).')
        ");
        $stmtNota->execute([$solicitudId, $gestorId]);
    } catch (PDOException $e) {
        error_log('solicitud_publica nota: ' . $e->getMessage());
    }

    // Historial (opcional: si la tabla no existe no bloqueamos)
    try {
        registrarHistorialSolicitud($pdo, $solicitudId, $gestorId, 'creacion', 'Solicitud enviada desde formulario público de financiamiento', null, 'Nueva');
    } catch (Throwable $e) {
        error_log('solicitud_publica historial: ' . $e->getMessage());
    }

    // Si hay token (email codificado en base64): enviar PDF por correo a ese email
    $emailEnviado = false;
    if ($token !== '') {
        try {
            $emailDestino = @base64_decode(str_replace(['-', '_'], ['+', '/'], $token), true);
            if ($emailDestino === false) $emailDestino = @base64_decode($token, true);
            if (!is_string($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) $emailDestino = null;
            if ($emailDestino) {
                $pdfPath = null;
                if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                    if (class_exists('Dompdf\Dompdf')) {
                        $html = buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombre);
                        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                        $dompdf->loadHtml($html, 'UTF-8');
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();
                        $pdfPath = sys_get_temp_dir() . '/sol_fin_' . $solicitudId . '_' . uniqid() . '.pdf';
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
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => $emailEnviado
            ? 'Solicitud registrada correctamente. Hemos enviado una copia por correo al vendedor.'
            : 'Solicitud registrada correctamente. Nos pondremos en contacto contigo.',
        'data' => ['id' => $solicitudId]
    ]);
    exit();

} catch (PDOException $e) {
    $msg = 'PDO: ' . $e->getMessage();
    error_log('solicitud_publica ' . $msg);
    logSolPub($msg);
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar la solicitud. Intenta de nuevo más tarde.']);
} catch (Throwable $e) {
    $msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
    error_log('solicitud_publica: ' . $msg);
    logSolPub($msg);
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud. Intenta de nuevo más tarde.']);
}
