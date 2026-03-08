<?php
/**
 * Recibe el formulario de prueba y ejecuta el mismo INSERT que api/solicitudes.php.
 * Muestra el resultado o el error completo (mensaje PDO + errorInfo) para depurar.
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);
require_once $base . '/config/database.php';
require_once $base . '/includes/historial_helper.php';

header('Content-Type: text/html; charset=utf-8');

// Sin POST: solo mostrar enlace al formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Test – Resultado</title></head><body>';
    echo '<h1>Test – Probar solicitud</h1>';
    echo '<p>No se recibió POST. Use el <a href="form_solicitud.php">formulario de prueba</a> para enviar una solicitud completa.</p>';
    echo '</body></html>';
    exit;
}

// --- Misma lógica que api/solicitudes.php crearSolicitud ---
$convertirNumero = function($valor, $default = null) {
    if ($valor === '' || $valor === null) {
        return $default;
    }
    return is_numeric($valor) ? (int)$valor : $default;
};

$generoVal = trim((string)($_POST['genero'] ?? ''));
$generoPermitidos = ['Masculino', 'Femenino', 'Otro'];
$mapGenero = ['M' => 'Masculino', 'F' => 'Femenino', 'm' => 'Masculino', 'f' => 'Femenino'];
if ($generoVal === '') {
    $generoVal = null;
} elseif (isset($mapGenero[$generoVal])) {
    $generoVal = $mapGenero[$generoVal];
} elseif (!in_array($generoVal, $generoPermitidos, true)) {
    $generoVal = null;
}

$vacíoANull = function($v) {
    $v = trim((string)($v ?? ''));
    return $v === '' ? null : $v;
};
$estabilidadLaboral = $vacíoANull($_POST['estabilidad_laboral'] ?? null);
$fechaConstitucion = $vacíoANull($_POST['fecha_constitucion'] ?? null);
$ingreso = $vacíoANull($_POST['ingreso'] ?? null);
$precioEspecial = $vacíoANull($_POST['precio_especial'] ?? null);
$abonoPorcentaje = $vacíoANull($_POST['abono_porcentaje'] ?? null);
$abonoMonto = $vacíoANull($_POST['abono_monto'] ?? null);

// gestor_id: sesión o POST (formulario de prueba)
$gestorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : $convertirNumero($_POST['gestor_id'] ?? null, 1);
if ($gestorId < 1) {
    $gestorId = 1;
}

$bancoId = $convertirNumero($_POST['banco_id'] ?? null);

$valores = [
    $gestorId,
    $bancoId ?: null,
    $_POST['tipo_persona'] ?? '',
    $_POST['nombre_cliente'] ?? '',
    $_POST['cedula'] ?? '',
    $convertirNumero($_POST['edad'] ?? null),
    $generoVal,
    $_POST['direccion'] ?? null,
    $_POST['provincia'] ?? null,
    $_POST['distrito'] ?? null,
    $_POST['corregimiento'] ?? null,
    $_POST['barriada'] ?? null,
    $_POST['casa_edif'] ?? null,
    $_POST['numero_casa_apto'] ?? null,
    $_POST['telefono'] ?? null,
    $_POST['email'] ?? null,
    $_POST['email_pipedrive'] ?? null,
    isset($_POST['casado']) ? 1 : 0,
    $convertirNumero($_POST['hijos'] ?? null, 0),
    $_POST['perfil_financiero'] ?? '',
    $ingreso,
    $_POST['tiempo_laborar'] ?? null,
    $_POST['profesion'] ?? null,
    $_POST['ocupacion'] ?? null,
    $_POST['nombre_empresa_negocio'] ?? null,
    $estabilidadLaboral,
    $fechaConstitucion,
    $_POST['continuidad_laboral'] ?? null,
    $_POST['marca_auto'] ?? null,
    $_POST['modelo_auto'] ?? null,
    $convertirNumero($_POST['año_auto'] ?? null),
    $convertirNumero($_POST['kilometraje'] ?? null),
    $precioEspecial,
    $abonoPorcentaje,
    $abonoMonto,
    $_POST['comentarios_gestor'] ?? null
];

// Validar requeridos
$camposRequeridos = ['tipo_persona', 'nombre_cliente', 'cedula', 'perfil_financiero'];
foreach ($camposRequeridos as $campo) {
    if (empty($_POST[$campo])) {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Test – Error</title></head><body>';
        echo '<h1>Validación</h1><p class="error">El campo <strong>' . htmlspecialchars($campo) . '</strong> es requerido.</p>';
        echo '<p><a href="form_solicitud.php">Volver al formulario</a></p></body></html>';
        exit;
    }
}

$sql = "
    INSERT INTO solicitudes_credito (
        gestor_id, banco_id, tipo_persona, nombre_cliente, cedula, edad, genero,
        direccion, provincia, distrito, corregimiento, barriada, casa_edif,
        numero_casa_apto, telefono, email, email_pipedrive, casado, hijos, perfil_financiero,
        ingreso, tiempo_laborar, profesion, ocupacion, nombre_empresa_negocio, estabilidad_laboral,
        fecha_constitucion, continuidad_laboral, marca_auto, modelo_auto, año_auto, kilometraje,
        precio_especial, abono_porcentaje, abono_monto, comentarios_gestor
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test – Resultado</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 20px auto; padding: 0 15px; }
        h1, h2 { font-size: 1.2rem; }
        pre { background: #f5f5f5; padding: 12px; overflow-x: auto; font-size: 0.85rem; }
        .error { background: #fef2f2; color: #b91c1c; padding: 12px; border-radius: 6px; margin: 10px 0; }
        .ok { background: #f0fdf4; color: #166534; padding: 12px; border-radius: 6px; margin: 10px 0; }
        .info { background: #eff6ff; padding: 12px; border-radius: 6px; margin: 10px 0; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <h1>Test – Resultado de crear solicitud</h1>

<?php
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);
    $solicitudId = $pdo->lastInsertId();

    // Nota inicial y historial (como en la API)
    $stmt = $pdo->prepare("
        INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
        VALUES (?, ?, 'Comentario', 'Solicitud Creada', 'Solicitud de crédito creada desde test/probar_solicitud.php')
    ");
    $stmt->execute([$solicitudId, $gestorId]);
    registrarHistorialSolicitud($pdo, $solicitudId, $gestorId, 'creacion', 'Solicitud de crédito creada (test)', null, 'Nueva');

    echo '<div class="ok"><strong>Éxito.</strong> Solicitud creada con ID: ' . (int)$solicitudId . '</div>';
} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>Error PDO:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
    $info = isset($stmt) ? $stmt->errorInfo() : $pdo->errorInfo();
    echo '<h2>errorInfo()</h2><pre>' . htmlspecialchars(print_r($info, true)) . '</pre>';
    echo '<h2>Valores enviados al INSERT (para comparar con la tabla)</h2><pre>' . htmlspecialchars(print_r($valores, true)) . '</pre>';
    echo '<h2>POST recibido</h2><pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
    echo '<h2>SQL (placeholders)</h2><pre>' . htmlspecialchars($sql) . '</pre>';
}
?>

    <p><a href="form_solicitud.php">Volver al formulario</a></p>
</body>
</html>
