<?php
/**
 * API pública para crear solicitudes desde el formulario externo (sin login).
 * Solo acepta POST para crear una solicitud.
 */

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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/historial_helper.php';

// Obtener body JSON si viene por fetch
$input = $_POST;
if (empty($input) && ($raw = file_get_contents('php://input'))) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

// Quitar __meta del payload si existe
unset($input['__meta']);

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

try {
    $gestorId = getDefaultGestorId($pdo);
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

    // Nota inicial
    $stmtNota = $pdo->prepare("
        INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
        VALUES (?, ?, 'Comentario', 'Solicitud desde formulario público', 'Solicitud enviada desde el formulario de financiamiento (sin login).')
    ");
    $stmtNota->execute([$solicitudId, $gestorId]);

    // Historial con el gestor por defecto como "autor"
    registrarHistorialSolicitud($pdo, $solicitudId, $gestorId, 'creacion', 'Solicitud enviada desde formulario público de financiamiento', null, 'Nueva');

    echo json_encode([
        'success' => true,
        'message' => 'Solicitud registrada correctamente. Nos pondremos en contacto contigo.',
        'data' => ['id' => $solicitudId]
    ]);

} catch (PDOException $e) {
    error_log('solicitud_publica: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar la solicitud. Intenta de nuevo más tarde.']);
}
