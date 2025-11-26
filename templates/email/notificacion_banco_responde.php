<?php
$content = '
    <h2>Respuesta del Banco</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($vendedor_nombre) . '</strong>,</p>
    
    <p>Le informamos que el banco ha respondido a la solicitud de crédito que usted gestionó:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Cédula:</strong> ' . htmlspecialchars($solicitud['cedula'] ?? 'N/A') . '</p>
        <p><strong>Respuesta del Banco:</strong> 
            <span class="badge badge-' . 
            ($solicitud['respuesta_banco'] == 'Aprobado' ? 'success' : 
             ($solicitud['respuesta_banco'] == 'Pre Aprobado' ? 'warning' : 'danger')) . '">
                ' . htmlspecialchars($solicitud['respuesta_banco'] ?? 'Pendiente') . '
            </span>
        </p>
';

if (!empty($solicitud['letra'])) {
    $content .= '<p><strong>Letra:</strong> $' . number_format($solicitud['letra'], 2) . '</p>';
}
if (!empty($solicitud['plazo'])) {
    $content .= '<p><strong>Plazo:</strong> ' . htmlspecialchars($solicitud['plazo']) . ' meses</p>';
}
if (!empty($solicitud['abono_banco'])) {
    $content .= '<p><strong>Abono:</strong> $' . number_format($solicitud['abono_banco'], 2) . '</p>';
}
if (!empty($solicitud['promocion'])) {
    $content .= '<p><strong>Promoción:</strong> ' . htmlspecialchars($solicitud['promocion']) . '</p>';
}
if (!empty($solicitud['comentarios_ejecutivo_banco'])) {
    $content .= '<p><strong>Comentarios:</strong><br>' . nl2br(htmlspecialchars($solicitud['comentarios_ejecutivo_banco'])) . '</p>';
}

$content .= '
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

