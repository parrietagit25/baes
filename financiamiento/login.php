<?php
/**
 * Login del módulo financiamiento. Usuarios en tabla financiamiento_usuarios.
 */
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

financiamiento_crear_tabla_usuarios_si_no_existe();
financiamiento_crear_primer_usuario_si_vacio();

$mensaje = '';
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : 'ver_registros.php';
if ($redirect === '' || strpos($redirect, 'login') !== false) $redirect = 'ver_registros.php';

if (isset($_GET['salir'])) {
    unset($_SESSION['financiamiento_user_id'], $_SESSION['financiamiento_user_nombre'], $_SESSION['financiamiento_user_email']);
    header('Location: login.php');
    exit;
}

$user = financiamiento_usuario_logueado();
if ($user) {
    header('Location: ' . (isset($_GET['redirect']) ? $redirect : 'ver_registros.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($email === '' || $pass === '') {
        $mensaje = 'Ingrese email y contraseña.';
    } else {
        $pdo = financiamiento_pdo();
        if (!$pdo) {
            $mensaje = 'Error de conexión a la base de datos.';
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre, email, password_hash FROM financiamiento_usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($pass, $row['password_hash'])) {
                $_SESSION['financiamiento_user_id'] = (int)$row['id'];
                $_SESSION['financiamiento_user_nombre'] = $row['nombre'];
                $_SESSION['financiamiento_user_email'] = $row['email'];
                header('Location: ' . $redirect);
                exit;
            }
            $mensaje = 'Email o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Financiamiento</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; background: #0f1b33; color: #eaf0ff; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 16px; padding: 2rem; max-width: 380px; width: 100%; }
        h1 { margin: 0 0 1rem 0; font-size: 1.25rem; }
        label { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,.2); border-radius: 8px; background: rgba(0,0,0,.2); color: #fff; font-size: 1rem; margin-bottom: 0.5rem; }
        button { width: 100%; margin-top: 0.5rem; padding: 12px; background: #4ea1ff; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #3d8de6; }
        .error { background: rgba(255,93,93,.2); color: #ff5d5d; padding: 10px; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; }
        .hint { font-size: 0.8rem; color: #9fb0d0; margin-top: 1rem; }
        a { color: #4ea1ff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Acceso al sistema</h1>
        <p style="margin:0 0 1rem 0; color: #9fb0d0; font-size: 0.9rem;">Ingrese con su usuario del módulo de financiamiento.</p>
        <form method="post" action="">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
        <?php if ($mensaje): ?><div class="error"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <p class="hint">Usuario inicial: <strong>admin@ejemplo.com</strong> / <strong>admin123</strong>. Cámbielo en Usuarios.</p>
        <p style="margin-top: 1.5rem; font-size: 0.85rem;"><a href="index.php">← Formulario público</a></p>
    </div>
</body>
</html>
