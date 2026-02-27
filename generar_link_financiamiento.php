<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

$mensaje = '';
$linkGenerado = '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'motus.grupopcr.com.pa') . dirname($_SERVER['REQUEST_URI']);
$baseUrl = rtrim($baseUrl, '/');
$formUrl = $baseUrl . '/solicitud_financiamiento.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_destino'])) {
    $email = trim($_POST['email_destino']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Ingrese un correo electrónico válido.';
    } else {
        $token = bin2hex(random_bytes(24));
        try {
            $stmt = $pdo->prepare("INSERT INTO link_financiamiento (email_destino, token, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $_SESSION['user_id']]);
            $linkGenerado = $formUrl . '?t=' . $token;
            $mensaje = 'Link generado. Compártalo con el cliente. Al completar el formulario, recibirá por correo el PDF en: ' . $email;
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                $mensaje = 'Error: Ejecute el script database/link_financiamiento.sql para crear la tabla link_financiamiento.';
            } else {
                $mensaje = 'Error al generar el link.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar link - Solicitud de Financiamiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .link-box { background: #f0f4f8; border: 1px solid #dee2e6; border-radius: 12px; padding: 12px 16px; word-break: break-all; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <h2 class="mb-4"><i class="fas fa-link me-2"></i>Generar link de Solicitud de Financiamiento</h2>
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Ingrese el correo electrónico al que desea recibir el formulario completado (PDF). Se generará un link para enviar al cliente. Cuando el cliente llene y envíe el formulario, recibirá por correo el PDF con los datos y la firma.
                            </p>
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="email_destino" class="form-label">Correo donde recibir el PDF *</label>
                                    <input type="email" class="form-control form-control-lg" id="email_destino" name="email_destino" required placeholder="vendedor@ejemplo.com" value="<?php echo isset($_POST['email_destino']) ? htmlspecialchars($_POST['email_destino']) : ''; ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-plus me-2"></i>Generar link</button>
                            </form>
                            <?php if ($mensaje): ?>
                                <div class="alert <?php echo $linkGenerado ? 'alert-success' : 'alert-danger'; ?> mt-3"><?php echo htmlspecialchars($mensaje); ?></div>
                            <?php endif; ?>
                            <?php if ($linkGenerado): ?>
                                <div class="mt-4">
                                    <label class="form-label">Link para el cliente (copie y comparta):</label>
                                    <div class="link-box d-flex align-items-center gap-2">
                                        <input type="text" class="form-control border-0 bg-transparent p-0" id="linkInput" value="<?php echo htmlspecialchars($linkGenerado); ?>" readonly>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="copiarLink()"><i class="fas fa-copy me-1"></i>Copiar</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function copiarLink() {
            var input = document.getElementById('linkInput');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(function() {
                alert('Link copiado al portapapeles.');
            });
        }
    </script>
</body>
</html>
