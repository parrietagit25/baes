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
<?php
/**
 * Ver registros del formulario de financiamiento. Requiere login (usuarios en financiamiento_usuarios).
 */
require_once __DIR__ . '/includes/auth.php';
financiamiento_requiere_login('login.php');

$pdo = financiamiento_pdo();
$mensaje = '';
$registros = [];

function tabla_tiene_columna(PDO $pdo, string $tabla, string $columna): bool {
    static $cache = [];
    $k = $tabla . '.' . $columna;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            $cache[$k] = false;
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $tabla, $columna]);
        $cache[$k] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$k] = false;
    }
    return $cache[$k];
}

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

// Petición de adjuntos en JSON por registro de financiamiento.
if (isset($_GET['adjuntos_id']) && ctype_digit($_GET['adjuntos_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $frId = (int)$_GET['adjuntos_id'];

    try {
        $stmtFr = $pdo->prepare("SELECT id, cliente_id, cliente_correo, cliente_nombre, solicitud_credito_id FROM financiamiento_registros WHERE id = ?");
        $stmtFr->execute([$frId]);
        $fr = $stmtFr->fetch(PDO::FETCH_ASSOC);
        if (!$fr) {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado', 'data' => []]);
            exit;
        }

        $tieneScFinReg = tabla_tiene_columna($pdo, 'solicitudes_credito', 'financiamiento_registro_id');
        $tieneFrSolCred = tabla_tiene_columna($pdo, 'financiamiento_registros', 'solicitud_credito_id');
        $orConds = [];
        $params = [];

        if ($tieneScFinReg) {
            $orConds[] = "s.id IN (SELECT sc.id FROM solicitudes_credito sc WHERE sc.financiamiento_registro_id = ?)";
            $params[] = $frId;
        }
        if ($tieneFrSolCred && !empty($fr['solicitud_credito_id'])) {
            $orConds[] = "s.id = ?";
            $params[] = (int)$fr['solicitud_credito_id'];
        }
        if (!empty($fr['cliente_id'])) {
            $fallback = "
                s.id IN (
                    SELECT sc2.id
                    FROM solicitudes_credito sc2
                    WHERE sc2.comentarios_gestor LIKE '%[Solicitud desde formulario público]%'
                      AND sc2.cedula = ?
            ";
            $params[] = (string)$fr['cliente_id'];
            if (!empty($fr['cliente_correo'])) {
                $fallback .= " AND (sc2.email = ? OR sc2.nombre_cliente = ?)";
                $params[] = (string)$fr['cliente_correo'];
                $params[] = (string)($fr['cliente_nombre'] ?? '');
            } elseif (!empty($fr['cliente_nombre'])) {
                $fallback .= " AND sc2.nombre_cliente = ?";
                $params[] = (string)$fr['cliente_nombre'];
            }
            $fallback .= ")";
            $orConds[] = $fallback;
        }

        if (!$orConds) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $sql = "
            SELECT
                a.id,
                a.solicitud_id,
                a.nombre_original,
                a.ruta_archivo,
                a.tipo_archivo,
                a.fecha_subida
            FROM adjuntos_solicitud a
            INNER JOIN solicitudes_credito s ON s.id = a.solicitud_id
            WHERE " . implode(' OR ', $orConds) . "
            ORDER BY a.fecha_subida DESC, a.id DESC
        ";
        $stmtAdj = $pdo->prepare($sql);
        $stmtAdj->execute($params);
        $rows = $stmtAdj->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar adjuntos', 'data' => []]);
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
        .adj-list { list-style: none; margin: 0; padding: 0; }
        .adj-item { padding: 10px; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; margin-bottom: 8px; background: rgba(255,255,255,.03); }
        .adj-item a { font-weight: 600; }
        .adj-meta { color: #9fb0d0; font-size: 0.82rem; margin-top: 4px; }
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
                        <th>Acciones</th>
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
                        <td style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="btn ver-detalle" data-id="<?php echo (int)$r['id']; ?>">Ver detalle</button>
                            <button type="button" class="btn ver-adjuntos" data-id="<?php echo (int)$r['id']; ?>">Adjuntos</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="modalDetalle" class="modal" role="dialog" aria-label="Detalle del registro">
        <div class="modal-content">
            <div class="modal-header">
                <strong id="modalTitle">Datos completos del formulario</strong>
                <button type="button" class="modal-close" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
    function abrirModal(title, htmlOrText, asHtml) {
        document.getElementById('modalTitle').textContent = title;
        var body = document.getElementById('modalBody');
        if (asHtml) {
            body.innerHTML = htmlOrText;
        } else {
            body.textContent = htmlOrText;
        }
        document.getElementById('modalDetalle').classList.add('show');
    }

    document.querySelectorAll('.ver-detalle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            abrirModal('Datos completos del formulario', 'Cargando…', false);
            fetch('ver_registros.php?id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    abrirModal('Datos completos del formulario', JSON.stringify(data, null, 2), false);
                })
                .catch(function() { abrirModal('Datos completos del formulario', 'Error al cargar.', false); });
        });
    });

    document.querySelectorAll('.ver-adjuntos').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            abrirModal('Adjuntos del registro', 'Cargando adjuntos…', false);
            fetch('ver_registros.php?adjuntos_id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res || !res.success) {
                        abrirModal('Adjuntos del registro', 'No se pudieron consultar los adjuntos.', false);
                        return;
                    }
                    var rows = Array.isArray(res.data) ? res.data : [];
                    if (rows.length === 0) {
                        abrirModal('Adjuntos del registro', 'Este registro no tiene adjuntos vinculados.', false);
                        return;
                    }
                    var html = '<ul class="adj-list">';
                    rows.forEach(function(a) {
                        var nombre = a.nombre_original || a.ruta_archivo || ('Adjunto #' + a.id);
                        var ruta = String(a.ruta_archivo || '');
                        var href = ruta ? ('../' + ruta.replace(/^\/+/, '')) : '#';
                        var fecha = a.fecha_subida || '—';
                        var tipo = a.tipo_archivo || '—';
                        var solicitud = a.solicitud_id || '—';
                        html += '<li class="adj-item">'
                            + '<a href="' + href + '" target="_blank" rel="noopener">' + escapeHtml(nombre) + '</a>'
                            + '<div class="adj-meta">Solicitud: ' + escapeHtml(String(solicitud)) + ' · Tipo: ' + escapeHtml(tipo) + '</div>'
                            + '<div class="adj-meta">Ruta: ' + escapeHtml(ruta) + ' · Fecha: ' + escapeHtml(String(fecha)) + '</div>'
                            + '</li>';
                    });
                    html += '</ul>';
                    abrirModal('Adjuntos del registro (' + rows.length + ')', html, true);
                })
                .catch(function() {
                    abrirModal('Adjuntos del registro', 'Error al cargar adjuntos.', false);
                });
        });
    });

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function cerrarModal() {
        document.getElementById('modalDetalle').classList.remove('show');
    }
    document.getElementById('modalDetalle').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });
    </script>
</body>
</html>
