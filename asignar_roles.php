<?php
/**
 * Script para asignar roles a usuarios existentes
 */

session_start();
require_once 'config/database.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['user_id']) || !in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    die('Solo administradores pueden ejecutar este script');
}

try {
    echo "=== ASIGNACIÓN DE ROLES ===\n\n";
    
    // Obtener todos los usuarios
    $stmt = $pdo->query("SELECT id, nombre, apellido, email FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll();
    
    echo "Usuarios encontrados:\n";
    foreach ($usuarios as $usuario) {
        echo "- ID: {$usuario['id']} | {$usuario['nombre']} {$usuario['apellido']} ({$usuario['email']})\n";
    }
    
    echo "\n";
    
    // Obtener roles disponibles
    $stmt = $pdo->query("SELECT id, nombre FROM roles ORDER BY id");
    $roles = $stmt->fetchAll();
    
    echo "Roles disponibles:\n";
    foreach ($roles as $rol) {
        echo "- ID: {$rol['id']} | {$rol['nombre']}\n";
    }
    
    echo "\n";
    
    // Asignar rol de GESTOR al usuario admin (ID 1) si no lo tiene
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_roles WHERE usuario_id = 1 AND rol_id = (SELECT id FROM roles WHERE nombre = 'ROLE_GESTOR')");
    $stmt->execute();
    $tieneGestor = $stmt->fetchColumn();
    
    if (!$tieneGestor) {
        $stmt = $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (1, (SELECT id FROM roles WHERE nombre = 'ROLE_GESTOR'))");
        $stmt->execute();
        echo "✅ Rol GESTOR asignado al usuario administrador\n";
    } else {
        echo "ℹ️  El usuario administrador ya tiene rol GESTOR\n";
    }
    
    // Asignar rol de BANCO al usuario admin (ID 1) si no lo tiene
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_roles WHERE usuario_id = 1 AND rol_id = (SELECT id FROM roles WHERE nombre = 'ROLE_BANCO')");
    $stmt->execute();
    $tieneBanco = $stmt->fetchColumn();
    
    if (!$tieneBanco) {
        $stmt = $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (1, (SELECT id FROM roles WHERE nombre = 'ROLE_BANCO'))");
        $stmt->execute();
        echo "✅ Rol BANCO asignado al usuario administrador\n";
    } else {
        echo "ℹ️  El usuario administrador ya tiene rol BANCO\n";
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    // Verificar roles del usuario admin
    $stmt = $pdo->prepare("
        SELECT r.nombre 
        FROM roles r 
        INNER JOIN usuario_roles ur ON r.id = ur.rol_id 
        WHERE ur.usuario_id = 1
    ");
    $stmt->execute();
    $rolesAdmin = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Roles del usuario administrador: " . implode(', ', $rolesAdmin) . "\n";
    
    if (in_array('ROLE_GESTOR', $rolesAdmin)) {
        echo "✅ El usuario administrador puede crear solicitudes\n";
    } else {
        echo "❌ El usuario administrador NO puede crear solicitudes\n";
    }
    
    if (in_array('ROLE_BANCO', $rolesAdmin)) {
        echo "✅ El usuario administrador puede analizar solicitudes del banco\n";
    } else {
        echo "❌ El usuario administrador NO puede analizar solicitudes del banco\n";
    }
    
    echo "\n=== INSTRUCCIONES ===\n";
    echo "1. Cierra sesión y vuelve a iniciar sesión para actualizar los roles\n";
    echo "2. Ahora deberías poder crear solicitudes de crédito\n";
    echo "3. Para crear usuarios específicos:\n";
    echo "   - GESTOR: Para crear solicitudes\n";
    echo "   - BANCO: Para analizar solicitudes\n";
    echo "   - ADMIN: Acceso completo\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

