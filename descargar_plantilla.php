<?php
// Crear plantilla CSV para importación de leads
$plantilla = [
    ['nombre_cliente', 'email', 'telefono', 'cedula', 'direccion', 'empresa'],
    ['Juan Pérez', 'juan.perez@email.com', '12345678', '123456789', 'Calle 1, Ciudad', 'Empresa ABC'],
    ['María García', 'maria.garcia@email.com', '87654321', '987654321', 'Avenida 2, Ciudad', 'Compañía XYZ']
];

// Configurar headers para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_leads.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Crear archivo CSV
$output = fopen('php://output', 'w');

// Agregar BOM para UTF-8 (para que Excel abra correctamente)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir datos
foreach ($plantilla as $fila) {
    fputcsv($output, $fila);
}

fclose($output);
?>
