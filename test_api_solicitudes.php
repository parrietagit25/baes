<?php
/**
 * Script para probar la API de solicitudes
 */

session_start();
require_once 'config/database.php';

// Simular sesión de administrador
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Administrador Sistema';
$_SESSION['user_email'] = 'admin@sistema.com';
$_SESSION['user_roles'] = ['ROLE_ADMIN', 'ROLE_GESTOR', 'ROLE_BANCO'];

echo "=== PRUEBA DE API DE SOLICITUDES ===\n\n";

// Probar obtener solicitudes
echo "1. Probando obtener solicitudes...\n";
$stmt = $pdo->query("
    SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido,
           COUNT(n.id) as total_notas
    FROM solicitudes_credito s
    LEFT JOIN usuarios u ON s.gestor_id = u.id
    LEFT JOIN notas_solicitud n ON s.id = n.solicitud_id
    GROUP BY s.id
    ORDER BY s.fecha_creacion DESC
");
$solicitudes = $stmt->fetchAll();

echo "Solicitudes encontradas: " . count($solicitudes) . "\n";

foreach ($solicitudes as $solicitud) {
    echo "- ID: {$solicitud['id']} | Cliente: {$solicitud['nombre_cliente']} | Cédula: {$solicitud['cedula']} | Estado: {$solicitud['estado']}\n";
}

echo "\n2. Probando API directamente...\n";

// Simular llamada a la API
ob_start();
include 'api/solicitudes.php';
$output = ob_get_clean();

echo "Respuesta de la API:\n";
echo $output . "\n";

echo "\n3. Verificando estructura de la tabla...\n";
$stmt = $pdo->query("DESCRIBE solicitudes_credito");
$columnas = $stmt->fetchAll();

echo "Columnas de la tabla:\n";
foreach ($columnas as $columna) {
    echo "- {$columna['Field']} ({$columna['Type']})\n";
}

echo "\n=== FIN DE PRUEBA ===\n";
?>

