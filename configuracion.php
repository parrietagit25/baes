<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/configuracion_sistema_helper.php';

$chatbotActual = motus_chatbot_habilitado();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del sistema - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .modal-header { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); color: white; border-radius: 15px 15px 0 0; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <h2 class="mb-1"><i class="fas fa-sliders-h me-2 text-primary"></i>Configuración del sistema</h2>
                <p class="text-muted mb-4">Ajustes globales que afectan a todos los usuarios con sesión iniciada.</p>

                <div class="alert alert-info small mb-4">
                    <i class="fas fa-database me-1"></i>
                    Si al guardar aparece un error de base de datos, ejecute <code>database/migracion_configuracion_sistema.sql</code>.
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-robot me-2 text-info"></i>Asistente de IA (chat)</h5>
                        <p class="card-text text-muted small">
                            Controla la burbuja de chat y las llamadas por voz del asistente en todas las pantallas del panel.
                            Al deshabilitar, no se muestra el widget y las APIs del chat responden con error (nadie puede usarlo hasta que lo vuelva a activar).
                        </p>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="switchChatbotHabilitado" <?php echo $chatbotActual ? 'checked' : ''; ?> style="width: 3rem; height: 1.5rem;">
                            <label class="form-check-label ms-2" for="switchChatbotHabilitado" id="labelChatbotEstado">
                                <?php echo $chatbotActual ? 'Asistente habilitado' : 'Asistente deshabilitado'; ?>
                            </label>
                        </div>
                        <button type="button" class="btn btn-primary" id="btnGuardarChatbot">
                            <i class="fas fa-save me-1"></i>Guardar cambio
                        </button>
                        <span class="ms-2 small text-muted" id="msgGuardarChatbot"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
(function () {
    var $sw = $('#switchChatbotHabilitado');
    var $lbl = $('#labelChatbotEstado');
    $sw.on('change', function () {
        $lbl.text($sw.is(':checked') ? 'Asistente habilitado' : 'Asistente deshabilitado');
    });
    $('#btnGuardarChatbot').on('click', function () {
        var $msg = $('#msgGuardarChatbot');
        $msg.removeClass('text-danger text-success').text('Guardando…');
        $.ajax({
            url: 'api/configuracion_sistema.php',
            type: 'POST',
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify({ chatbot_habilitado: $sw.is(':checked') }),
            dataType: 'json'
        }).done(function (r) {
            if (r.success) {
                $msg.removeClass('text-danger').addClass('text-success').text(r.message || 'Guardado.');
            } else {
                $msg.removeClass('text-success').addClass('text-danger').text(r.message || 'Error');
            }
        }).fail(function (xhr) {
            var m = 'Error de conexión';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.message) m = j.message;
            } catch (e) { /* ignore */ }
            $msg.removeClass('text-success').addClass('text-danger').text(m);
        });
    });
})();
</script>
<?php if ($chatbotActual): ?>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
<?php endif; ?>
</body>
</html>
