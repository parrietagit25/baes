<?php
/**
 * Gestionar usuarios del módulo financiamiento (ver registros y este panel).
 */
require_once __DIR__ . '/includes/auth.php';
financiamiento_requiere_login('login.php');

$pdo = financiamiento_pdo();
$mensaje = '';
$error = '';

financiamiento_crear_tabla_usuarios_si_no_existe();

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($nombre === '' || $email === '') {
        $error = 'Nombre y email son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM financiamiento_usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Ya existe un usuario con ese email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO financiamiento_usuarios (nombre, email, password_hash, activo) VALUES (?, ?, ?, 1)");
                $stmt->execute([$nombre, $email, $hash]);
                $mensaje = 'Usuario creado correctamente.';
            }
        } catch (PDOException $e) {
            $error = 'Error al crear: ' . $e->getMessage();
        }
    }
}

// Actualizar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    if ($id < 1 || $nombre === '' || $email === '') {
        $error = 'Datos inválidos.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM financiamiento_usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $error = 'Ya existe otro usuario con ese email.';
            } else {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = 'La contraseña debe tener al menos 6 caracteres.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE financiamiento_usuarios SET nombre = ?, email = ?, password_hash = ?, activo = ? WHERE id = ?");
                        $stmt->execute([$nombre, $email, $hash, $activo, $id]);
                        $mensaje = 'Usuario actualizado.';
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE financiamiento_usuarios SET nombre = ?, email = ?, activo = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $activo, $id]);
                    $mensaje = 'Usuario actualizado.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Desactivar / eliminar
if (isset($_GET['desactivar']) && ctype_digit($_GET['desactivar'])) {
    $id = (int)$_GET['desactivar'];
    try {
        $stmt = $pdo->prepare("UPDATE financiamiento_usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Usuario desactivado.';
    } catch (PDOException $e) {
        $error = 'Error al desactivar.';
    }
}
if (isset($_GET['activar']) && ctype_digit($_GET['activar'])) {
    $id = (int)$_GET['activar'];
    try {
        $stmt = $pdo->prepare("UPDATE financiamiento_usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Usuario activado.';
    } catch (PDOException $e) {
        $error = 'Error al activar.';
    }
}

$usuarios = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, email, activo, fecha_creacion FROM financiamiento_usuarios ORDER BY nombre");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'No se pudo cargar la lista. Ejecute database_usuarios.sql.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Financiamiento</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; background: #0b1220; color: #eaf0ff; min-height: 100vh; margin: 0; padding: 20px; }
        .wrap { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.35rem; margin: 0 0 0.5rem 0; }
        .sub { color: #9fb0d0; font-size: 0.9rem; margin-bottom: 1rem; }
        .btn { display: inline-block; padding: 8px 14px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 8px; color: #eaf0ff; font-size: 0.9rem; text-decoration: none; cursor: pointer; border: none; }
        .btn:hover { background: rgba(255,255,255,.15); }
        .btn.primary { background: #4ea1ff; color: #fff; }
        .menu-financiamiento .btn.active { background: rgba(78,161,255,.35); }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 1rem; }
        .alert.success { background: rgba(52,211,153,.2); color: #34d399; }
        .alert.danger { background: rgba(255,93,93,.2); color: #ff5d5d; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.04); border-radius: 12px; overflow: hidden; margin-top: 1rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,.08); }
        th { background: rgba(78,161,255,.2); font-weight: 600; font-size: 0.85rem; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
        .modal.show { display: flex; }
        .modal-content { background: #0f1b33; border: 1px solid rgba(255,255,255,.15); border-radius: 16px; max-width: 440px; width: 100%; padding: 1.5rem; }
        .modal-content label { display: block; margin: 10px 0 4px 0; font-size: 0.9rem; }
        .modal-content input { width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,.2); background: rgba(0,0,0,.2); color: #fff; }
        .modal-content .cb { display: flex; align-items: center; gap: 8px; margin: 12px 0; }
        .modal-actions { margin-top: 1.25rem; display: flex; gap: 10px; }
        .inactivo { opacity: 0.6; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Usuarios del módulo</h1>
        <p class="sub">Usuarios que pueden entrar a ver registros y gestionar este módulo.</p>
        <?php financiamiento_menu('usuarios'); ?>

        <?php if ($mensaje): ?><div class="alert success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div>
            <button type="button" class="btn primary" onclick="abrirModalNuevo()">+ Nuevo usuario</button>
        </div>

        <?php if (empty($usuarios)): ?>
            <p style="color:#9fb0d0; margin-top:1rem;">No hay usuarios. Ejecute database_usuarios.sql o cree uno con el botón anterior.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Email</th><th>Estado</th><th>Fecha</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr class="<?php echo $u['activo'] ? '' : 'inactivo'; ?>">
                        <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($u['fecha_creacion'])); ?></td>
                        <td>
                            <button type="button" class="btn" onclick='editar(<?php echo json_encode($u); ?>)'>Editar</button>
                            <?php if ($u['activo']): ?>
                                <a href="usuarios.php?desactivar=<?php echo (int)$u['id']; ?>" class="btn" onclick="return confirm('¿Desactivar este usuario?');">Desactivar</a>
                            <?php else: ?>
                                <a href="usuarios.php?activar=<?php echo (int)$u['id']; ?>" class="btn">Activar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="modalForm" class="modal">
        <div class="modal-content">
            <form method="post" action="" id="formUsuario">
                <input type="hidden" name="accion" id="formAccion" value="crear">
                <input type="hidden" name="id" id="formId" value="">
                <label>Nombre *</label>
                <input type="text" name="nombre" id="formNombre" required>
                <label>Email *</label>
                <input type="email" name="email" id="formEmail" required>
                <label id="labelPass">Contraseña *</label>
                <input type="password" name="password" id="formPassword" minlength="6" placeholder="Mínimo 6 caracteres">
                <div class="cb" id="formActivoWrap" style="display:none;">
                    <input type="checkbox" name="activo" id="formActivo" value="1" checked>
                    <label for="formActivo" style="margin:0;">Activo</label>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn primary">Guardar</button>
                    <button type="button" class="btn" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function abrirModalNuevo() {
        document.getElementById('formAccion').value = 'crear';
        document.getElementById('formId').value = '';
        document.getElementById('formNombre').value = '';
        document.getElementById('formEmail').value = '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = true;
        document.getElementById('labelPass').style.display = 'block';
        document.getElementById('formPassword').parentElement.style.display = 'block';
        document.getElementById('formActivoWrap').style.display = 'none';
        document.getElementById('modalForm').classList.add('show');
    }
    function editar(u) {
        document.getElementById('formAccion').value = 'editar';
        document.getElementById('formId').value = u.id;
        document.getElementById('formNombre').value = u.nombre;
        document.getElementById('formEmail').value = u.email;
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = false;
        document.getElementById('labelPass').textContent = 'Contraseña (dejar en blanco para no cambiar)';
        document.getElementById('formActivoWrap').style.display = 'flex';
        document.getElementById('formActivo').checked = u.activo == 1;
        document.getElementById('modalForm').classList.add('show');
    }
    function cerrarModal() {
        document.getElementById('modalForm').classList.remove('show');
    }
    document.getElementById('modalForm').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });
    </script>
</body>
</html>
