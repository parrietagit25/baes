<?php
$content = '
    <h2>Nueva Solicitud Asignada</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($banco_nombre) . '</strong>,</p>
    
    <p>Se le ha asignado una nueva solicitud de crédito para evaluación:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Cédula:</strong> ' . htmlspecialchars($solicitud['cedula'] ?? 'N/A') . '</p>
        <p><strong>Teléfono:</strong> ' . htmlspecialchars($solicitud['telefono'] ?? 'N/A') . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($solicitud['email'] ?? 'N/A') . '</p>
';

if (!empty($solicitud['marca_auto']) && !empty($solicitud['modelo_auto'])) {
    $content .= '<p><strong>Vehículo:</strong> ' . 
        htmlspecialchars($solicitud['marca_auto']) . ' ' . 
        htmlspecialchars($solicitud['modelo_auto']) . 
        (!empty($solicitud['año_auto']) ? ' (' . $solicitud['año_auto'] . ')' : '') . 
        '</p>';
}

if (!empty($solicitud['precio_especial'])) {
    $content .= '<p><strong>Precio:</strong> $' . number_format($solicitud['precio_especial'], 2) . '</p>';
}

if (!empty($solicitud['comentarios_gestor'])) {
    $content .= '<p><strong>Comentarios del Gestor:</strong><br>' . 
        nl2br(htmlspecialchars($solicitud['comentarios_gestor'])) . '</p>';
}

$content .= '
    </div>
    
    <p>Por favor, revise y evalúe esta solicitud a la brevedad posible.</p>
    
    <p style="text-align: center;">
        <a href="' . htmlspecialchars($app_url) . '/solicitudes.php?id=' . $solicitud['id'] . '" class="button">
            Ver y Evaluar Solicitud
        </a>
    </p>
    
    <p>Saludos cordiales,<br>Sistema BAES</p>
';

include __DIR__ . '/base.php';
?>

