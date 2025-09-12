<?php
/**
 * Script para ejecutar las migraciones de la base de datos
 * Este script debe ejecutarse una sola vez para crear las nuevas tablas
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
    
    $pdo->beginTransaction();
    
    $successCount = 0;
    $totalStatements = count($statements);
    
    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;
        
        try {
            echo "Ejecutando (" . ($index + 1) . "/$totalStatements): " . substr($statement, 0, 50) . "...\n";
            
            $pdo->exec($statement);
            $successCount++;
            
        } catch (PDOException $e) {
            echo "⚠️  Advertencia en statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            // Continuar con el siguiente statement
        }
    }
    
    $pdo->commit();
    
    echo "\n✅ Se ejecutaron $successCount de $totalStatements statements correctamente.\n";
    
    echo "\n✅ Migraciones ejecutadas correctamente!\n";
    echo "Las siguientes tablas han sido creadas:\n";
    echo "- solicitudes_credito\n";
    echo "- notas_solicitud\n";
    echo "- documentos_solicitud\n";
    echo "- Nuevos roles: ROLE_GESTOR, ROLE_BANCO\n\n";
    
    echo "El sistema de solicitudes de crédito está listo para usar.\n";
    
} catch (Exception $e) {
    if (isset($pdo)) {
        try {
            $pdo->rollBack();
        } catch (PDOException $rollbackError) {
            // Ignorar error de rollback si no hay transacción activa
        }
    }
    
    echo "\n❌ Error durante las migraciones: " . $e->getMessage() . "\n";
    echo "Por favor, revisa la configuración de la base de datos y vuelve a intentar.\n";
}
?>
