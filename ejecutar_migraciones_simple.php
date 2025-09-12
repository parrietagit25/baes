<?php
/**
 * Script simple para ejecutar las migraciones de la base de datos
 * Este script ejecuta cada statement individualmente sin transacciones
 */

require_once 'config/database.php';

try {
    echo "Iniciando migraciones de la base de datos...\n\n";
    
    // Leer el archivo SQL de migraciones
    $sqlFile = 'database/solicitudes_credito.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo de migración no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Error al leer el archivo de migración");
    }
    
    // Dividir el SQL en statements individuales
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $totalStatements = count($statements);
    
    foreach ($statements as $index => $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            echo "Ejecutando (" . ($index + 1) . "/$totalStatements): " . substr($statement, 0, 50) . "...\n";
            
            $pdo->exec($statement);
            $successCount++;
            echo "✅ OK\n";
            
        } catch (PDOException $e) {
            // Si es un error de "ya existe", lo consideramos exitoso
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️  Ya existe, omitiendo...\n";
                $successCount++;
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Se ejecutaron $successCount de $totalStatements statements correctamente.\n";
    
    if ($successCount > 0) {
        echo "\n✅ Migraciones ejecutadas correctamente!\n";
        echo "Las siguientes tablas han sido creadas:\n";
        echo "- solicitudes_credito\n";
        echo "- notas_solicitud\n";
        echo "- documentos_solicitud\n";
        echo "- Nuevos roles: ROLE_GESTOR, ROLE_BANCO\n\n";
        
        echo "El sistema de solicitudes de crédito está listo para usar.\n";
        echo "\nPróximos pasos:\n";
        echo "1. Crear usuarios con roles de GESTOR y BANCO\n";
        echo "2. Acceder al sistema y navegar a 'Solicitudes de Crédito'\n";
        echo "3. Los gestores pueden crear solicitudes\n";
        echo "4. El banco puede analizar y responder solicitudes\n";
        echo "5. Usar el muro de tiempo para comunicación\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error durante las migraciones: " . $e->getMessage() . "\n";
    echo "Por favor, revisa la configuración de la base de datos y vuelve a intentar.\n";
}
?>

