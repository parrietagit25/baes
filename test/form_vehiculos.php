<?php
/**
 * Test: guardar vehículos en vehiculos_solicitud.
 * Requiere estar logueado (misma sesión que el panel). Usa api/vehiculos_solicitud.php.
 */
session_start();
$base = dirname(__DIR__);
require_once $base . '/config/database.php';

// Listar últimas solicitudes para elegir ID
$solicitudes = [];
try {
    $stmt = $pdo->query("SELECT id, nombre_cliente, fecha_creacion FROM solicitudes_credito ORDER BY id DESC LIMIT 30");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test – Guardar vehículo</title>
    <style>
        body { font-family: sans-serif; max-width: 520px; margin: 20px auto; padding: 0 15px; }
        h1 { font-size: 1.25rem; }
        section { margin: 1rem 0; padding: 10px; background: #f5f5f5; border-radius: 6px; }
        label { display: block; margin: 6px 0 2px 0; font-size: 0.9rem; }
        input, select { width: 100%; max-width: 300px; padding: 6px; box-sizing: border-box; }
        button { margin-top: 10px; padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .error { background: #fef2f2; color: #b91c1c; padding: 10px; border-radius: 6px; margin: 10px 0; }
        .ok { background: #f0fdf4; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0; }
        #resultado { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Test – Guardar vehículo (vehiculos_solicitud)</h1>
    <p>Debes estar <strong>logueado</strong> en el sistema para que la API acepte la petición.</p>

    <form id="formVehiculo">
        <section>
            <label>solicitud_id *</label>
            <select name="solicitud_id" required>
                <option value="">Seleccionar solicitud...</option>
                <?php foreach ($solicitudes as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['id'] . ' – ' . ($s['nombre_cliente'] ?? 'N/A')); ?></option>
                <?php endforeach; ?>
            </select>
        </section>
        <section>
            <h2 style="margin:0 0 8px 0; font-size:1rem;">Vehículo (campos vacíos se guardan como NULL)</h2>
            <label>marca</label>
            <input type="text" name="marca" value="Toyota" maxlength="100">
            <label>modelo</label>
            <input type="text" name="modelo" value="Corolla" maxlength="100">
            <label>anio (número)</label>
            <input type="number" name="anio" value="2022" min="1900" max="2030">
            <label>kilometraje</label>
            <input type="number" name="kilometraje" value="15000" min="0">
            <label>precio (decimal)</label>
            <input type="text" name="precio" value="25000.00" placeholder="vacío = NULL">
            <label>abono_porcentaje</label>
            <input type="text" name="abono_porcentaje" value="20" placeholder="vacío = NULL">
            <label>abono_monto</label>
            <input type="text" name="abono_monto" value="5000" placeholder="vacío = NULL">
        </section>
        <button type="submit">Guardar vehículo en vehiculos_solicitud</button>
    </form>

    <div id="resultado"></div>

    <p style="margin-top: 20px;"><a href="form_solicitud.php">Formulario solicitud completa</a></p>

    <script>
    document.getElementById('formVehiculo').addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        var solicitudId = fd.get('solicitud_id');
        var vehiculos = [{
            marca: fd.get('marca') || '',
            modelo: fd.get('modelo') || '',
            anio: fd.get('anio') || '',
            kilometraje: fd.get('kilometraje') || '',
            precio: fd.get('precio') || '',
            abono_porcentaje: fd.get('abono_porcentaje') || '',
            abono_monto: fd.get('abono_monto') || ''
        }];

        var result = document.getElementById('resultado');
        result.innerHTML = 'Enviando...';

        fetch('../api/vehiculos_solicitud.php', {
            method: 'POST',
            body: new URLSearchParams({
                solicitud_id: solicitudId,
                vehiculos: JSON.stringify(vehiculos)
            })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(res) {
            if (res.data.success) {
                result.innerHTML = '<div class="ok">' + (res.data.message || 'Vehículos guardados correctamente') + '</div>';
            } else {
                result.innerHTML = '<div class="error">' + (res.data.message || 'Error') + '</div>';
            }
        })
        .catch(function() {
            result.innerHTML = '<div class="error">Error de conexión o respuesta no JSON. ¿Estás logueado?</div>';
        });
    });
    </script>
</body>
</html>
