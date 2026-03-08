<?php
/**
 * Formulario de prueba: envía TODOS los campos de una solicitud de crédito.
 * Uso: rellenar (hay valores por defecto) y enviar para depurar errores al crear.
 */
session_start();
$base = dirname(__DIR__);
require_once $base . '/config/database.php';

// Obtener gestores para el dropdown (para pruebas sin sesión)
$gestores = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, apellido FROM usuarios WHERE activo = 1 ORDER BY id ASC LIMIT 50");
    $gestores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $gestores = [];
}

// Bancos para el dropdown
$bancos = [];
try {
    $stmt = $pdo->query("SELECT id, nombre FROM bancos WHERE activo = 1 ORDER BY nombre ASC");
    $bancos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bancos = [];
}

$gestorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : ($gestores[0]['id'] ?? 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test – Formulario solicitud completa</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 0 15px; }
        h1 { font-size: 1.3rem; }
        section { margin: 1.2rem 0; padding: 10px; background: #f5f5f5; border-radius: 6px; }
        section h2 { margin: 0 0 8px 0; font-size: 1rem; }
        label { display: block; margin: 6px 0 2px 0; font-size: 0.9rem; }
        input, select, textarea { width: 100%; max-width: 400px; padding: 6px; box-sizing: border-box; }
        .inline { display: inline; width: auto; }
        button { margin-top: 10px; padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .note { font-size: 0.85rem; color: #666; margin-top: 4px; }
        .error { background: #fef2f2; color: #b91c1c; padding: 10px; border-radius: 6px; margin: 10px 0; }
        .ok { background: #f0fdf4; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Test – Formulario solicitud completa</h1>
    <p class="note">Este formulario envía todos los campos que espera el INSERT de creación. Use para reproducir y depurar errores (Data truncated, 500, etc.).</p>

    <form action="probar_solicitud.php" method="post">
        <section>
            <h2>Gestor y banco</h2>
            <label>gestor_id (si no hay sesión, elija un usuario)</label>
            <select name="gestor_id">
                <?php foreach ($gestores as $g): ?>
                    <option value="<?php echo (int)$g['id']; ?>" <?php echo ($gestorId === (int)$g['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['nombre'] . ' ' . $g['apellido'] . ' (ID ' . $g['id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (empty($gestores)): ?>
                    <option value="1">ID 1 (crear usuario si no existe)</option>
                <?php endif; ?>
            </select>
            <label>banco_id (opcional)</label>
            <select name="banco_id">
                <option value="">-- Sin banco --</option>
                <?php foreach ($bancos as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </section>

        <section>
            <h2>Datos personales (requeridos: tipo_persona, nombre_cliente, cedula, perfil_financiero)</h2>
            <label>tipo_persona</label>
            <select name="tipo_persona" required>
                <option value="Natural">Natural</option>
                <option value="Jurídica">Jurídica</option>
            </select>
            <label>nombre_cliente</label>
            <input type="text" name="nombre_cliente" value="Cliente Prueba Test" required maxlength="255">
            <label>cedula</label>
            <input type="text" name="cedula" value="8-123-4567" required maxlength="50">
            <label>edad</label>
            <input type="number" name="edad" value="30" min="0" max="120">
            <label>genero (M/F/Masculino/Femenino/Otro o vacío)</label>
            <select name="genero">
                <option value="">-- Vacío --</option>
                <option value="M">M (Masculino)</option>
                <option value="F">F (Femenino)</option>
                <option value="Masculino">Masculino</option>
                <option value="Femenino">Femenino</option>
                <option value="Otro">Otro</option>
            </select>
            <label>perfil_financiero</label>
            <input type="text" name="perfil_financiero" value="A" required maxlength="100">
            <label>casado (checkbox = 1)</label>
            <input type="checkbox" name="casado" value="1">
            <label>hijos</label>
            <input type="number" name="hijos" value="0" min="0">
        </section>

        <section>
            <h2>Dirección</h2>
            <label>direccion</label>
            <input type="text" name="direccion" value="Calle Test 123" maxlength="255">
            <label>provincia</label>
            <input type="text" name="provincia" value="Panamá" maxlength="100">
            <label>distrito</label>
            <input type="text" name="distrito" value="Panamá" maxlength="100">
            <label>corregimiento</label>
            <input type="text" name="corregimiento" value="" maxlength="100">
            <label>barriada</label>
            <input type="text" name="barriada" value="" maxlength="100">
            <label>casa_edif</label>
            <input type="text" name="casa_edif" value="" maxlength="100">
            <label>numero_casa_apto</label>
            <input type="text" name="numero_casa_apto" value="" maxlength="50">
        </section>

        <section>
            <h2>Contacto</h2>
            <label>telefono</label>
            <input type="text" name="telefono" value="6000-0000" maxlength="50">
            <label>email</label>
            <input type="email" name="email" value="test@test.com" maxlength="255">
            <label>email_pipedrive</label>
            <input type="email" name="email_pipedrive" value="" maxlength="255">
        </section>

        <section>
            <h2>Laboral</h2>
            <label>ingreso (decimal o vacío)</label>
            <input type="text" name="ingreso" value="1500.00" placeholder="vacío = NULL">
            <label>tiempo_laborar</label>
            <input type="text" name="tiempo_laborar" value="2 años" maxlength="100">
            <label>profesion</label>
            <input type="text" name="profesion" value="Ingeniero" maxlength="100">
            <label>ocupacion</label>
            <input type="text" name="ocupacion" value="Empleado" maxlength="100">
            <label>nombre_empresa_negocio</label>
            <input type="text" name="nombre_empresa_negocio" value="Empresa Test" maxlength="255">
            <label>estabilidad_laboral (vacío = NULL)</label>
            <input type="text" name="estabilidad_laboral" value="" maxlength="100">
            <label>fecha_constitucion (YYYY-MM-DD o vacío)</label>
            <input type="text" name="fecha_constitucion" value="" placeholder="vacío = NULL">
            <label>continuidad_laboral</label>
            <input type="text" name="continuidad_laboral" value="" maxlength="100">
        </section>

        <section>
            <h2>Vehículo</h2>
            <label>marca_auto</label>
            <input type="text" name="marca_auto" value="Toyota" maxlength="100">
            <label>modelo_auto</label>
            <input type="text" name="modelo_auto" value="Corolla" maxlength="100">
            <label>año_auto (número)</label>
            <input type="number" name="año_auto" value="2022" min="1900" max="2030">
            <label>kilometraje</label>
            <input type="number" name="kilometraje" value="15000" min="0">
        </section>

        <section>
            <h2>Financiamiento</h2>
            <label>precio_especial (decimal o vacío)</label>
            <input type="text" name="precio_especial" value="25000.00" placeholder="vacío = NULL">
            <label>abono_porcentaje (vacío = NULL)</label>
            <input type="text" name="abono_porcentaje" value="20" placeholder="vacío = NULL">
            <label>abono_monto (vacío = NULL)</label>
            <input type="text" name="abono_monto" value="5000" placeholder="vacío = NULL">
        </section>

        <section>
            <h2>Comentarios</h2>
            <label>comentarios_gestor</label>
            <textarea name="comentarios_gestor" rows="2">Solicitud de prueba desde test/form_solicitud.php</textarea>
        </section>

        <button type="submit">Enviar solicitud de prueba</button>
    </form>

    <p class="note" style="margin-top: 20px;"><a href="probar_solicitud.php">Ver resultado anterior (probar_solicitud.php)</a></p>
</body>
</html>
