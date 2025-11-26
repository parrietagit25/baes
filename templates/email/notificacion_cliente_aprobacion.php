<?php
$content = '
    <h2>¡Felicidades! Su Solicitud ha sido Aprobada</h2>
    <p>Estimado/a <strong>' . htmlspecialchars($cliente_nombre) . '</strong>,</p>
    
    <p>Nos complace informarle que su solicitud de crédito ha sido <strong>APROBADA</strong>.</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . htmlspecialchars($solicitud['id']) . '</strong></p>
        <p><strong>Estado:</strong> 
            <span class="badge badge-success">Aprobada</span>
        </p>
';

if (!empty($solicitud['letra'])) {
    $content .= '<p><strong>Cuota Mensual:</strong> $' . number_format($solicitud['letra'], 2) . '</p>';
}
if (!empty($solicitud['plazo'])) {
    $content .= '<p><strong>Plazo:</strong> ' . htmlspecialchars($solicitud['plazo']) . ' meses</p>';
}
if (!empty($solicitud['abono_banco'])) {
    $content .= '<p><strong>Abono Inicial:</strong> $' . number_format($solicitud['abono_banco'], 2) . '</p>';
}
if (!empty($solicitud['promocion'])) {
    $content .= '<p><strong>Promoción Especial:</strong> ' . htmlspecialchars($solicitud['promocion']) . '</p>';
}

$content .= '
    </div>
    
    <p><strong>Próximos Pasos:</strong></p>
    <ul>
        <li>Nuestro equipo se pondrá en contacto con usted para coordinar la documentación necesaria</li>
        <li>Se programará una cita para la firma de documentos</li>
        <li>Una vez completada la documentación, procederemos con la entrega del vehículo</li>
    </ul>
    
    <p>Si tiene alguna pregunta, no dude en contactarnos.</p>
    
    <p>Saludos cordiales,<br>Equipo Automarket Rent a Car</p>
';

include __DIR__ . '/base.php';
?>

