<?php
/**
 * Script para debuggear roles de usuario
 */

session_start();
require_once 'config/database.php';

echo "=== DEBUG DE ROLES ===\n\n";

// Verificar si hay sesión
if (!isset($_SESSION['user_id'])) {
    echo "❌ No hay sesión activa\n";
    echo "Por favor, inicia sesión primero\n";
    exit;
}

echo "✅ Sesión activa\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "User Name: " . $_SESSION['user_name'] . "\n";
echo "User Email: " . $_SESSION['user_email'] . "\n";

// Verificar roles en sesión
if (isset($_SESSION['user_roles'])) {
    echo "Roles en sesión: " . implode(', ', $_SESSION['user_roles']) . "\n";
} else {
    echo "❌ No hay roles en la sesión\n";
}

// Verificar roles en base de datos
try {
    $stmt = $pdo->prepare("
        SELECT r.nombre 
        FROM roles r 
        INNER JOIN usuario_roles ur ON r.id = ur.rol_id 
        WHERE ur.usuario_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rolesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Roles en BD: " . implode(', ', $rolesDB) . "\n";
    
    // Verificar si puede crear solicitudes
    if (in_array('ROLE_GESTOR', $rolesDB) || in_array('ROLE_ADMIN', $rolesDB)) {
        echo "✅ Puede crear solicitudes\n";
    } else {
        echo "❌ NO puede crear solicitudes\n";
        echo "Necesita rol ROLE_GESTOR o ROLE_ADMIN\n";
    }
    
    // Verificar si puede analizar solicitudes del banco
    if (in_array('ROLE_BANCO', $rolesDB) || in_array('ROLE_ADMIN', $rolesDB)) {
        echo "✅ Puede analizar solicitudes del banco\n";
    } else {
        echo "❌ NO puede analizar solicitudes del banco\n";
        echo "Necesita rol ROLE_BANCO o ROLE_ADMIN\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error al verificar roles: " . $e->getMessage() . "\n";
}

echo "\n=== SOLUCIÓN ===\n";
echo "Si no tienes los roles necesarios:\n";
echo "1. Ejecuta: php asignar_roles.php\n";
echo "2. O asigna roles manualmente desde la gestión de usuarios\n";
echo "3. Cierra sesión y vuelve a iniciar sesión\n";
?>

