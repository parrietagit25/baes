<?php
// Script de verificación de la base de datos
echo "<h2>🔍 Verificación de Base de Datos - Sistema de Usuarios</h2>";

try {
    // Intentar conectar a la base de datos
    require_once 'config/database.php';
    echo "<p style='color: green;'>✅ <strong>Conexión exitosa</strong> a la base de datos</p>";
    
    // Verificar que las tablas existan
    echo "<h3>📋 Verificación de Tablas</h3>";
    
    $tablas = ['usuarios', 'roles', 'usuario_roles'];
    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Tabla <strong>$tabla</strong> existe</p>";
        } else {
            echo "<p style='color: red;'>❌ Tabla <strong>$tabla</strong> NO existe</p>";
        }
    }
    
    // Verificar usuarios en la tabla
    echo "<h3>👥 Verificación de Usuarios</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_usuarios = $stmt->fetch()['total'];
    echo "<p><strong>Total de usuarios:</strong> $total_usuarios</p>";
    
    if ($total_usuarios > 0) {
        $stmt = $pdo->query("SELECT id, nombre, apellido, email, activo FROM usuarios ORDER BY id");
        $usuarios = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nombre</th><th>Email</th><th>Estado</th></tr>";
        
        foreach ($usuarios as $usuario) {
            $estado = $usuario['activo'] ? 'Activo' : 'Inactivo';
            $color = $usuario['activo'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$usuario['id']}</td>";
            echo "<td>{$usuario['nombre']} {$usuario['apellido']}</td>";
            echo "<td>{$usuario['email']}</td>";
            echo "<td style='color: $color;'>$estado</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar roles
    echo "<h3>🔐 Verificación de Roles</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
    $total_roles = $stmt->fetch()['total'];
    echo "<p><strong>Total de roles:</strong> $total_roles</p>";
    
    if ($total_roles > 0) {
        $stmt = $pdo->query("SELECT id, nombre, activo FROM roles ORDER BY id");
        $roles = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Rol</th><th>Estado</th></tr>";
        
        foreach ($roles as $rol) {
            $estado = $rol['activo'] ? 'Activo' : 'Inactivo';
            $color = $rol['activo'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$rol['id']}</td>";
            echo "<td>{$rol['nombre']}</td>";
            echo "<td style='color: $color;'>$estado</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verificar asignaciones de roles
    echo "<h3>🔗 Verificación de Asignaciones de Roles</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuario_roles");
    $total_asignaciones = $stmt->fetch()['total'];
    echo "<p><strong>Total de asignaciones de roles:</strong> $total_asignaciones</p>";
    
    if ($total_asignaciones > 0) {
        $stmt = $pdo->query("
            SELECT u.nombre, u.apellido, r.nombre as rol
            FROM usuario_roles ur
            INNER JOIN usuarios u ON ur.usuario_id = u.id
            INNER JOIN roles r ON ur.rol_id = r.id
            ORDER BY u.id
        ");
        $asignaciones = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Usuario</th><th>Rol</th></tr>";
        
        foreach ($asignaciones as $asignacion) {
            echo "<tr>";
            echo "<td>{$asignacion['nombre']} {$asignacion['apellido']}</td>";
            echo "<td>{$asignacion['rol']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test de login
    echo "<h3>🧪 Test de Login</h3>";
    $email = 'admin@sistema.com';
    $password = 'admin123';
    
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, password, activo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "<p>✅ Usuario <strong>$email</strong> encontrado</p>";
        echo "<p><strong>ID:</strong> {$usuario['id']}</p>";
        echo "<p><strong>Nombre:</strong> {$usuario['nombre']} {$usuario['apellido']}</p>";
        echo "<p><strong>Estado:</strong> " . ($usuario['activo'] ? 'Activo' : 'Inactivo') . "</p>";
        
        // Verificar contraseña
        if (password_verify($password, $usuario['password'])) {
            echo "<p style='color: green;'>✅ <strong>Contraseña correcta</strong> para admin123</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>Contraseña incorrecta</strong> para admin123</p>";
            echo "<p><strong>Hash almacenado:</strong> {$usuario['password']}</p>";
            
            // Generar hash correcto
            $hash_correcto = password_hash($password, PASSWORD_DEFAULT);
            echo "<p><strong>Hash correcto para admin123:</strong> $hash_correcto</p>";
            
            // SQL para actualizar
            echo "<h4>🔧 SQL para Corregir la Contraseña</h4>";
            echo "<pre>";
            echo "UPDATE usuarios SET password = '$hash_correcto' WHERE email = '$email';";
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ Usuario <strong>$email</strong> NO encontrado</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ <strong>Error de conexión:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>🔧 Solución de Problemas</h3>";
    echo "<p>1. Verificar que MySQL esté ejecutándose</p>";
    echo "<p>2. Verificar credenciales en <code>config/database.php</code></p>";
    echo "<p>3. Verificar que la base de datos <code>sistema_usuarios</code> exista</p>";
    echo "<p>4. Ejecutar el archivo <code>database/schema.sql</code></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>Error general:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>📋 Pasos para Solucionar el Problema</h3>";
echo "<ol>";
echo "<li><strong>Ejecutar el esquema:</strong> Importar <code>database/schema.sql</code> en MySQL</li>";
echo "<li><strong>Verificar conexión:</strong> Revisar <code>config/database.php</code></li>";
echo "<li><strong>Test de contraseñas:</strong> Usar <code>test_password.php</code></li>";
echo "<li><strong>Insertar admin:</strong> Ejecutar <code>database/insert_admin.sql</code></li>";
echo "<li><strong>Verificar datos:</strong> Usar <code>test_database.php</code></li>";
echo "</ol>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2, h3, h4 {
    color: #333;
}
p {
    margin: 10px 0;
    line-height: 1.6;
}
code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
pre {
    background: #f8f8f8;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    border: 1px solid #ddd;
}
hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 20px 0;
}
table {
    background: white;
}
th, td {
    padding: 8px 12px;
    text-align: left;
}
ol {
    line-height: 1.8;
}
</style>
