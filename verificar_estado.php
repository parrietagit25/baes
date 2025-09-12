<?php
/**
 * Script para verificar el estado de las migraciones
 */

require_once 'config/database.php';

try {
    echo "Verificando estado de las migraciones...\n\n";
    
    // Verificar si las tablas existen
    $tablas = [
        'solicitudes_credito',
        'notas_solicitud', 
        'documentos_solicitud'
    ];
    
    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabla '$tabla' existe\n";
            
            // Contar registros
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tabla");
            $count = $stmt->fetch()['total'];
            echo "   - Registros: $count\n";
        } else {
            echo "❌ Tabla '$tabla' NO existe\n";
        }
    }
    
    echo "\n";
    
    // Verificar roles
    $stmt = $pdo->query("SELECT nombre FROM roles WHERE nombre IN ('ROLE_GESTOR', 'ROLE_BANCO')");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Roles del sistema:\n";
    foreach (['ROLE_GESTOR', 'ROLE_BANCO'] as $rol) {
        if (in_array($rol, $roles)) {
            echo "✅ $rol existe\n";
        } else {
            echo "❌ $rol NO existe\n";
        }
    }
    
    echo "\n";
    
    // Verificar estructura de solicitudes_credito si existe
    if (in_array('solicitudes_credito', $tablas)) {
        echo "Estructura de la tabla solicitudes_credito:\n";
        $stmt = $pdo->query("DESCRIBE solicitudes_credito");
        $columnas = $stmt->fetchAll();
        
        foreach ($columnas as $columna) {
            echo "- {$columna['Field']} ({$columna['Type']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

