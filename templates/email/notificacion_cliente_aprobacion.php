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
    
    <div class="info-box" style="border-left: 4px solid #dc3545; background: #fff8f8;">
        <p style="margin-top:0;"><strong>IMPORTANTE — Próximos pasos</strong></p>
        <ul style="margin-bottom:0; padding-left: 1.25rem;">
            <li>Nuestro equipo se pondrá en contacto con usted para acompañarle en los próximos pasos.</li>
            <li>Reservar la unidad de su preferencia.</li>
            <li>Completar la documentación requerida para la unidad.</li>
            <li>Confirmar su disponibilidad para la firma de documentos.</li>
            <li>Se programará una cita para la firma de documentos.</li>
            <li>Trabajamos con las principales aseguradoras del país; le haremos llegar las mejores propuestas de pólizas.</li>
        </ul>
    </div>
    
    <p>Si tiene alguna pregunta, no dude en contactarnos.</p>
    
    <p>Saludos cordiales,<br>Equipo AutoMarket Seminuevos</p>
';

include __DIR__ . '/base.php';
?>

