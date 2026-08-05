<?php
$h = static function ($v) {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
};

$respuesta = trim((string) ($solicitud['respuesta_banco'] ?? 'Pendiente'));
$bancoNombre = trim((string) ($solicitud['banco_nombre'] ?? ''));
if ($bancoNombre === '') {
    $bancoNombre = 'Banco';
}
$bancoUsuario = trim((string) ($solicitud['banco_usuario'] ?? ''));

$badgeClass = 'danger';
if ($respuesta === 'Aprobado') {
    $badgeClass = 'success';
} elseif ($respuesta === 'Pre Aprobado' || $respuesta === 'Aprobado Condicional') {
    $badgeClass = 'warning';
}

$content = '
    <h2>Respuesta del Banco</h2>
    <p>Estimado/a <strong>' . $h($vendedor_nombre) . '</strong>,</p>
    
    <p>Le informamos que <strong>' . $h($bancoNombre) . '</strong> ha respondido a la solicitud de crédito que usted gestionó:</p>
    
    <div class="info-box">
        <p><strong>Solicitud #' . $h($solicitud['id'] ?? '') . '</strong></p>
        <p><strong>Cliente:</strong> ' . $h($solicitud['nombre_cliente'] ?? 'N/A') . '</p>
        <p><strong>Cédula:</strong> ' . $h($solicitud['cedula'] ?? 'N/A') . '</p>
        <p><strong>Entidad bancaria:</strong> ' . $h($bancoNombre) . '</p>';

if ($bancoUsuario !== '') {
    $content .= '
        <p><strong>Analista / usuario banco:</strong> ' . $h($bancoUsuario) . '</p>';
}

$content .= '
        <p><strong>Respuesta:</strong> 
            <span class="badge badge-' . $badgeClass . '">
                ' . $h($respuesta) . '
            </span>
        </p>
';

if (!empty($solicitud['evaluacion_razon'])) {
    $content .= '<p><strong>Razón:</strong> ' . $h($solicitud['evaluacion_razon']) . '</p>';
}
if (!empty($solicitud['letra'])) {
    $content .= '<p><strong>Letra:</strong> $' . number_format((float) $solicitud['letra'], 2) . '</p>';
}
if (!empty($solicitud['plazo'])) {
    $content .= '<p><strong>Plazo:</strong> ' . $h($solicitud['plazo']) . ' meses</p>';
}
if (!empty($solicitud['abono_banco'])) {
    $content .= '<p><strong>Abono:</strong> $' . number_format((float) $solicitud['abono_banco'], 2) . '</p>';
}
if (!empty($solicitud['promocion'])) {
    $content .= '<p><strong>Promoción:</strong> ' . $h($solicitud['promocion']) . '</p>';
}
if (!empty($solicitud['comentarios_ejecutivo_banco'])) {
    $content .= '<p><strong>Comentarios:</strong><br>' . nl2br($h($solicitud['comentarios_ejecutivo_banco'])) . '</p>';
}

$sid = (int) ($solicitud['id'] ?? 0);
$appUrl = rtrim((string) ($app_url ?? ''), '/');

$content .= '
    </div>
    
    <p>Puede ver los detalles completos de la solicitud haciendo clic en el siguiente botón:</p>
    
    <p style="text-align: center;">
        <a href="' . $h($appUrl . '/solicitudes.php?abrir_solicitud=' . $sid) . '" class="button">
            Ver Solicitud
        </a>
    </p>
    
    <p>Saludos cordiales,<br>AutoMarket Seminuevos — MOTUS</p>
';

include __DIR__ . '/base.php';
?>
