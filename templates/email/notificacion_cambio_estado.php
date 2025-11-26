<?php
$content = '
    <h2>Cambio de Estado en Solicitud</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($gestor_nombre) . '</strong>,</p>
    
    <p>Le informamos que ha ocurrido un cambio de estado en una de sus solicitudes:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Estado Anterior:</strong> 
            <span class="badge badge-info">' . htmlspecialchars($estado_anterior) . '</span>
        </p>
        <p><strong>Estado Nuevo:</strong> 
            <span class="badge badge-success">' . htmlspecialchars($estado_nuevo) . '</span>
        </p>
    </div>
    
    <p>Puede ver los detalles completos de la solicitud haciendo clic en el siguiente botón:</p>
    
    <p style="text-align: center;">
        <a href="' . htmlspecialchars($app_url) . '/solicitudes.php?id=' . $solicitud['id'] . '" class="button">
            Ver Solicitud
        </a>
    </p>
    
    <p>Saludos cordiales,<br>Automarket Rent a Car</p>
';

include __DIR__ . '/base.php';
?>

