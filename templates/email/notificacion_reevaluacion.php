<?php
/**
 * Correo al usuario banco cuando el gestor/admin solicita reevaluación de su decisión.
 * Variables: $banco_nombre, $gestor_nombre, $solicitud, $evaluacion, $comentario, $app_url, $subject
 */
$h = static function ($s) {
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
};
$money = static function ($v) {
    if ($v === null || $v === '') {
        return '—';
    }
    return '$' . number_format((float) $v, 2, '.', ',');
};
$decisionLabel = strtoupper(str_replace('_', ' ', (string) ($evaluacion['decision'] ?? '')));
$vehiculoTxt = trim(implode(' ', array_filter([
    $evaluacion['vehiculo_marca'] ?? '',
    $evaluacion['vehiculo_modelo'] ?? '',
    !empty($evaluacion['vehiculo_anio']) ? '(' . $evaluacion['vehiculo_anio'] . ')' : '',
])));
if ($vehiculoTxt === '') {
    $vehiculoTxt = '—';
}
$unidadTxt = trim((string) ($evaluacion['vehiculo_unidad'] ?? ''));
$gestorTxt = trim((string) ($gestor_nombre ?? ''));
if ($gestorTxt === '') {
    $gestorTxt = 'Gestor';
}

$content = '
    <h2>Solicitud de reevaluación</h2>
    <p>Estimado/a <strong>' . $h($banco_nombre) . '</strong>,</p>

    <p>El gestor <strong>' . $h($gestorTxt) . '</strong> ha solicitado que reevalúe su decisión sobre la siguiente solicitud de crédito.</p>

    <div class="info-box">
        <p><strong>Solicitud #' . $h($solicitud['id'] ?? '') . '</strong></p>
        <p><strong>Cliente:</strong> ' . $h($solicitud['nombre_cliente'] ?? '—') . '</p>
        <p><strong>Cédula:</strong> ' . $h($solicitud['cedula'] ?? '—') . '</p>
        <p><strong>Teléfono:</strong> ' . $h($solicitud['telefono'] ?? '—') . '</p>
        <p><strong>Perfil financiero:</strong> ' . $h($solicitud['perfil_financiero'] ?? '—') . '</p>
        <p><strong>Estado actual:</strong> ' . $h($solicitud['estado'] ?? '—') . '</p>
        <p><strong>Vehículo evaluado:</strong> ' . $h($vehiculoTxt)
            . ($unidadTxt !== '' ? ' · Unidad ' . $h($unidadTxt) : '') . '</p>
    </div>

    <div class="info-box">
        <p><strong>Su decisión a reevaluar</strong></p>
        <p><strong>Decisión:</strong> ' . $h($decisionLabel !== '' ? $decisionLabel : '—') . '</p>
        <p><strong>Razón:</strong> ' . $h($evaluacion['razon'] ?? '—') . '</p>
        <p><strong>Tasa:</strong> '
            . (isset($evaluacion['tasa_bancaria']) && $evaluacion['tasa_bancaria'] !== null && $evaluacion['tasa_bancaria'] !== ''
                ? $h(number_format((float) $evaluacion['tasa_bancaria'], 2)) . '%'
                : '—') . '</p>
        <p><strong>Precio de venta / valor:</strong> ' . $h($money($evaluacion['valor_financiar'] ?? null)) . '</p>
        <p><strong>Abono:</strong> ' . $h($money($evaluacion['abono'] ?? null)) . '</p>
        <p><strong>Plazo:</strong> '
            . (!empty($evaluacion['plazo']) ? $h($evaluacion['plazo']) . ' meses' : '—') . '</p>
        <p><strong>Letra mensual:</strong> ' . $h($money($evaluacion['letra'] ?? null)) . '</p>
        <p><strong>Letra quincenal:</strong> ' . $h($money($evaluacion['letra_quincenal'] ?? null)) . '</p>
        <p><strong>Promoción:</strong> ' . $h($evaluacion['promocion'] ?? '—') . '</p>
        <p><strong>Cuantía:</strong> ' . $h($money($evaluacion['cuantia'] ?? null)) . '</p>
        <p><strong>Comentarios de su evaluación:</strong><br>'
            . nl2br($h($evaluacion['comentarios'] ?? '—')) . '</p>
    </div>

    <p><strong>Motivo de la reevaluación (gestor):</strong></p>
    <div class="info-box">
        <p>' . nl2br($h($comentario ?? '')) . '</p>
    </div>

    <p>Por favor ingrese a MOTUS y registre una nueva evaluación para esta solicitud.</p>

    <p style="text-align: center;">
        <a href="' . $h(rtrim((string) ($app_url ?? ''), '/') . '/solicitudes.php?abrir_solicitud=' . (int) ($solicitud['id'] ?? 0)) . '" class="button">
            Abrir solicitud
        </a>
    </p>

    <p>Saludos cordiales,<br>AutoMarket Seminuevos — MOTUS</p>
';

include __DIR__ . '/base.php';
