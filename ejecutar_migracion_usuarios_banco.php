<?php
/**
 * Script para ejecutar la migración de usuarios banco
 * Agrega la columna banco_id a la tabla usuarios
 */

require_once 'config/database.php';

try {
    echo "=== EJECUTANDO MIGRACIÓN DE USUARIOS BANCO ===\n";
    
    // Leer el archivo de migración
    $migrationFile = 'database/migracion_usuarios_banco.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Archivo de migración no encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (!$sql) {
        throw new Exception("No se pudo leer el archivo de migración");
    }
    
    // Dividir las consultas SQL
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "Ejecutando " . count($queries) . " consultas SQL...\n";
    
    foreach ($queries as $index => $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }
        
        echo "Ejecutando consulta " . ($index + 1) . "...\n";
        echo "SQL: " . substr($query, 0, 100) . "...\n";
        
        try {
            $pdo->exec($query);
            echo "✅ Consulta ejecutada exitosamente\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  La columna ya existe, continuando...\n";
            } else {
                echo "❌ Error en consulta: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "\n";
    }
    
    echo "=== MIGRACIÓN COMPLETADA EXITOSAMENTE ===\n";
    echo "La columna banco_id ha sido agregada a la tabla usuarios.\n";
    echo "Ahora los usuarios banco pueden ser asignados a solicitudes.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>

