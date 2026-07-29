<?php
session_start();
require_once __DIR__ . '/includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    motus_emitir_mantenimiento_html();
    exit();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/password_reset_helper.php';

$error = '';
$success = '';
$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
}

$tokenValido = false;
if (isset($pdo) && $pdo instanceof PDO && $token !== '') {
    $tokenValido = password_reset_buscar_token_valido($pdo, $token) !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $error = 'No se pudo conectar a la base de datos. Intente nuevamente.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');
        $res = password_reset_aplicar($pdo, $token, $password, $password2);
        if (!empty($res['ok'])) {
            $success = $res['message'];
            $tokenValido = false;
        } else {
            $error = $res['message'] ?? 'No se pudo restablecer la contraseña.';
            $tokenValido = password_reset_buscar_token_valido($pdo, $token) !== null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body { padding: 40px; }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: #fff;
        }
        .btn-login:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .link-muted { color: #6c757d; text-decoration: none; }
        .link-muted:hover { color: #28a745; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-lock me-2"></i>Nueva contraseña</h2>
            <p class="mb-0">Defina una contraseña segura</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-login btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Ir a iniciar sesión
                    </a>
                </div>
            <?php elseif (!$tokenValido): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-link-slash me-2"></i>El enlace no es válido o ha expirado. Solicite uno nuevo.
                </div>
                <div class="text-center mt-3">
                    <a href="recuperar_password.php" class="btn btn-login btn-primary">
                        Solicitar nuevo enlace
                    </a>
                </div>
            <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Nueva contraseña
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Mínimo 8 caracteres" minlength="8" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password_confirm" class="form-label">
                        <i class="fas fa-lock me-2"></i>Confirmar contraseña
                    </label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                           placeholder="Repita la contraseña" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-login btn-primary">
                    <i class="fas fa-save me-2"></i>Guardar contraseña
                </button>
            </form>
            <?php endif; ?>

            <?php if (!$success): ?>
            <div class="text-center mt-4">
                <a href="index.php" class="link-muted">
                    <i class="fas fa-arrow-left me-1"></i>Volver al inicio de sesión
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
