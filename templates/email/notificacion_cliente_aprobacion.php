<?php
$h = static function ($value): string {
    $text = trim((string) ($value ?? ''));
    return htmlspecialchars($text !== '' ? $text : '—', ENT_QUOTES, 'UTF-8');
};
$money = static function ($value): string {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '—';
    }
    return '$' . number_format((float) $value, 2, '.', ',');
};

$esPreaprobada = (($solicitud['evaluacion_decision'] ?? '') === 'preaprobado');
$estadoTitulo = $esPreaprobada ? 'Preaprobada' : 'Aprobada';
$estadoMayuscula = mb_strtoupper($estadoTitulo, 'UTF-8');
$bancoNombre = trim((string) ($solicitud['banco_nombre'] ?? ''));
if ($bancoNombre === '') {
    $bancoNombre = 'Banco';
}

$vehiculo = trim(implode(' – ', array_filter([
    trim((string) ($solicitud['vehiculo_marca'] ?? $solicitud['marca_auto'] ?? '')),
    trim((string) ($solicitud['vehiculo_modelo'] ?? $solicitud['modelo_auto'] ?? '')),
    trim((string) ($solicitud['vehiculo_anio'] ?? $solicitud['ao_auto'] ?? '')),
])));
if ($vehiculo === '') {
    $vehiculo = '—';
}

$precioVenta = $solicitud['precio_venta_banco']
    ?? $solicitud['vehiculo_precio']
    ?? $solicitud['precio_especial']
    ?? null;
$abonoInicial = $solicitud['abono_inicial_banco'] ?? null;
$bonoBanco = (float) ($solicitud['bono_banco_abono'] ?? 0);
$totalAbono = $solicitud['total_abono_calculo'] ?? $abonoInicial;
$plazo = $solicitud['plazo_banco'] ?? null;
$cuotaMensual = $solicitud['cuota_mensual_banco'] ?? null;
$promocion = $solicitud['promocion_banco'] ?? null;

$content = '
    <h2>¡Felicitaciones! ¡Su solicitud ha sido ' . $h($estadoTitulo) . '!</h2>
    <p>Estimado/a <strong>' . $h($cliente_nombre) . '</strong>,</p>

    <p>Nos complace informarle que su solicitud de crédito ha sido
       <strong>' . $h($estadoMayuscula) . '</strong> por <strong>' . $h($bancoNombre) . '</strong>.</p>

    <h3>Detalles de su aprobación</h3>
    <div class="info-box">
        <p><strong>Vehículo (Marca – Modelo – Año):</strong> ' . $h($vehiculo) . '</p>
        <p><strong>Precio del auto:</strong> ' . $h($money($precioVenta)) . '</p>
        <p><strong>Abono inicial:</strong> ' . $h($money($abonoInicial))
            . ' + <strong>Bono Banco:</strong> ' . $h($money($bonoBanco))
            . ' = <strong>Total de abono contemplado para el cálculo de la letra:</strong> '
            . $h($money($totalAbono)) . '</p>
        <p><strong>Plazo:</strong> '
            . ($plazo !== null && $plazo !== '' ? $h($plazo) . ' meses' : '—') . '</p>
        <p><strong>Cuota mensual:</strong> ' . $h($money($cuotaMensual)) . '</p>
        <p><strong>Promoción especial:</strong> ' . $h($promocion) . '</p>
    </div>

    <p>Estamos muy contentos de acompañarle en este importante paso hacia la adquisición de su auto.</p>

    <p>Le invitamos a contactar a su Ejecutivo de Ventas, quien le brindará toda la orientación
       necesaria para avanzar de manera ágil y segura.</p>

    <div class="info-box" style="border-left: 4px solid #dc3545; background: #fff8f8;">
        <p style="margin-top:0;"><strong>IMPORTANTE — Próximos pasos</strong></p>
        <ol style="margin-bottom:0; padding-left: 1.25rem;">
            <li>Reservar la unidad de su preferencia.</li>
            <li>Completar la documentación pendiente para avanzar el proceso de financiamiento.</li>
            <li>Una vez completados los puntos 1 y 2, se le estará contactando para coordinar la cita de firma de documentos.</li>
            <li>Permítanos ayudarle a encontrar la póliza ideal para su vehículo, presentándole opciones de las principales aseguradoras para que elija la más conveniente.</li>
        </ol>
    </div>

    <p>Agradecemos la confianza depositada en nosotros y esperamos acompañarle muy pronto en la entrega de su nuevo auto.</p>

    <p><strong>¡Gracias por elegirnos y felicitaciones por este gran paso!</strong></p>

    <p>Saludos cordiales,<br>Equipo AutoMarket Seminuevos</p>
';

include __DIR__ . '/base.php';
?>

