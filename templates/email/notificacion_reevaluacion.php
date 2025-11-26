<?php
$content = '
    <h2>Solicitud de Reevaluación</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($banco_nombre) . '</strong>,</p>
    
    <p>Se ha solicitado una reevaluación de la siguiente solicitud de crédito:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Cédula:</strong> ' . htmlspecialchars($solicitud['cedula'] ?? 'N/A') . '</p>
    </div>
    
    <p><strong>Comentario de la solicitud:</strong></p>
    <div class="info-box">
        <p>' . nl2br(htmlspecialchars($comentario)) . '</p>
    </div>
    
    <p>Por favor, revise nuevamente esta solicitud y proporcione una nueva evaluación.</p>
    
    <p style="text-align: center;">
        <a href="' . htmlspecialchars($app_url) . '/solicitudes.php?id=' . $solicitud['id'] . '" class="button">
            Reevaluar Solicitud
        </a>
    </p>
    
    <p>Saludos cordiales,<br>Automarket Rent a Car</p>
';

include __DIR__ . '/base.php';
?>

