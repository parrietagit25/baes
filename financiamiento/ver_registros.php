<?php
/**
 * Ver registros del formulario de financiamiento. Requiere login (usuarios en financiamiento_usuarios).
 */
require_once __DIR__ . '/includes/auth.php';
financiamiento_requiere_login('login.php');

$pdo = financiamiento_pdo();
$mensaje = '';
$registros = [];

// Petición de detalle en JSON — todos los campos del registro
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $stmt = $pdo->prepare("SELECT * FROM financiamiento_registros WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        unset($row['firma']); // No enviar imagen base64 en listado; opcional: mostrar en detalle
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo '{}';
    }
    exit;
}

// Listar registros (columnas del formulario)
try {
    $stmt = $pdo->query("
        SELECT id, fecha_creacion, cliente_nombre AS nombre, cliente_id AS cedula, cliente_correo AS email, celular_cliente AS telefono
        FROM financiamiento_registros
        ORDER BY fecha_creacion DESC
    ");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = 'Error al cargar registros. Asegúrese de haber ejecutado financiamiento/database.sql.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros - Financiamiento</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; background: #0b1220; color: #eaf0ff; min-height: 100vh; margin: 0; padding: 20px; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.35rem; margin: 0 0 0.5rem 0; }
        .sub { color: #9fb0d0; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .toolbar { margin-bottom: 1rem; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        a { color: #4ea1ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 8px 14px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 8px; color: #eaf0ff; font-size: 0.9rem; cursor: pointer; }
        .btn:hover { background: rgba(255,255,255,.15); }
        .menu-financiamiento .btn.active { background: rgba(78,161,255,.35); }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.04); border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,.08); }
        th { background: rgba(78,161,255,.2); font-weight: 600; font-size: 0.85rem; }
        tr:hover { background: rgba(255,255,255,.03); }
        .empty { text-align: center; color: #9fb0d0; padding: 3rem; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
        .modal.show { display: flex; }
        .modal-content { background: #0f1b33; border: 1px solid rgba(255,255,255,.15); border-radius: 16px; max-width: 700px; width: 100%; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.1); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.25rem; overflow: auto; font-size: 0.85rem; white-space: pre-wrap; word-break: break-word; }
        .modal-close { background: none; border: none; color: #9fb0d0; font-size: 1.5rem; cursor: pointer; padding: 0 6px; line-height: 1; }
        .modal-close:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Registros del formulario de financiamiento</h1>
        <p class="sub">Personas que han completado el formulario.</p>
        <?php financiamiento_menu('registros'); ?>
        <?php if ($mensaje): ?>
            <p style="color: #ff5d5d;"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php elseif (empty($registros)): ?>
            <table><tr><td class="empty">No hay registros aún.</td></tr></table>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Cédula</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($r['fecha_creacion']))); ?></td>
                        <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($r['cedula']); ?></td>
                        <td><?php echo htmlspecialchars($r['email'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['telefono'] ?? '—'); ?></td>
                        <td><button type="button" class="btn ver-detalle" data-id="<?php echo (int)$r['id']; ?>">Ver detalle</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="modalDetalle" class="modal" role="dialog" aria-label="Detalle del registro">
        <div class="modal-content">
            <div class="modal-header">
                <strong>Datos completos del formulario</strong>
                <button type="button" class="modal-close" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
    document.querySelectorAll('.ver-detalle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var body = document.getElementById('modalBody');
            body.textContent = 'Cargando…';
            document.getElementById('modalDetalle').classList.add('show');
            fetch('ver_registros.php?id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    body.textContent = JSON.stringify(data, null, 2);
                })
                .catch(function() { body.textContent = 'Error al cargar.'; });
        });
    });
    function cerrarModal() {
        document.getElementById('modalDetalle').classList.remove('show');
    }
    document.getElementById('modalDetalle').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });
    </script>
</body>
</html>
