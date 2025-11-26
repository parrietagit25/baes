<?php
$horasPendiente = isset($solicitud['fecha_actualizacion']) 
    ? round((time() - strtotime($solicitud['fecha_actualizacion'])) / 3600, 1)
    : 0;

$content = '
    <h2>Recordatorio: Solicitud Pendiente</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($banco_nombre) . '</strong>,</p>
    
    <p>Le recordamos que tiene una solicitud de crédito pendiente de evaluación:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Cédula:</strong> ' . htmlspecialchars($solicitud['cedula'] ?? 'N/A') . '</p>
        <p><strong>Estado:</strong> 
            <span class="badge badge-info">' . htmlspecialchars($solicitud['estado'] ?? 'Nueva') . '</span>
        </p>
        <p><strong>Tiempo pendiente:</strong> ' . $horasPendiente . ' horas</p>
';

if (!empty($solicitud['marca_auto']) && !empty($solicitud['modelo_auto'])) {
    $content .= '<p><strong>Vehículo:</strong> ' . 
        htmlspecialchars($solicitud['marca_auto']) . ' ' . 
        htmlspecialchars($solicitud['modelo_auto']) . 
        (!empty($solicitud['año_auto']) ? ' (' . $solicitud['año_auto'] . ')' : '') . 
        '</p>';
}

$content .= '
    </div>
    
    <p>Por favor, revise y evalúe esta solicitud lo antes posible.</p>
    
    <p style="text-align: center;">
        <a href="' . htmlspecialchars($app_url) . '/solicitudes.php?id=' . $solicitud['id'] . '" class="button">
            Evaluar Solicitud
        </a>
    </p>
    
    <p>Saludos cordiales,<br>Automarket Rent a Car</p>
';

include __DIR__ . '/base.php';
?>

