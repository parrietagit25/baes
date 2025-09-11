<?php
// Archivo de test para generar y verificar contraseñas encriptadas
echo "<h2>🔐 Test de Contraseñas - Sistema de Usuarios</h2>";

// Contraseña que queremos usar
$password = 'admin123';

echo "<h3>📝 Información de la Contraseña</h3>";
echo "<p><strong>Contraseña original:</strong> $password</p>";

// Generar hash con password_hash()
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "<p><strong>Hash generado:</strong> $hash</p>";

// Verificar si la contraseña coincide con el hash
$verificacion = password_verify($password, $hash);
echo "<p><strong>Verificación:</strong> " . ($verificacion ? '✅ Correcta' : '❌ Incorrecta') . "</p>";

// Verificar con el hash que está en el esquema
$hash_esquema = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "<h3>🔍 Verificación con Hash del Esquema</h3>";
echo "<p><strong>Hash del esquema:</strong> $hash_esquema</p>";

$verificacion_esquema = password_verify($password, $hash_esquema);
echo "<p><strong>Verificación con hash del esquema:</strong> " . ($verificacion_esquema ? '✅ Correcta' : '❌ Incorrecta') . "</p>";

// Generar diferentes hashes para la misma contraseña
echo "<h3>🔄 Múltiples Hashes para la Misma Contraseña</h3>";
for ($i = 1; $i <= 3; $i++) {
    $hash_multiple = password_hash($password, PASSWORD_DEFAULT);
    $verificacion_multiple = password_verify($password, $hash_multiple);
    echo "<p><strong>Hash $i:</strong> $hash_multiple</p>";
    echo "<p><strong>Verificación:</strong> " . ($verificacion_multiple ? '✅ Correcta' : '❌ Incorrecta') . "</p>";
    echo "<hr>";
}

// Generar hash para diferentes contraseñas
echo "<h3>🎯 Hashes para Diferentes Contraseñas</h3>";
$contraseñas = ['admin123', 'password', '123456', 'qwerty'];

foreach ($contraseñas as $pass) {
    $hash_pass = password_hash($pass, PASSWORD_DEFAULT);
    echo "<p><strong>Contraseña:</strong> $pass</p>";
    echo "<p><strong>Hash:</strong> $hash_pass</p>";
    echo "<hr>";
}

// Información sobre el algoritmo
echo "<h3>ℹ️ Información del Algoritmo</h3>";
$info = password_get_info($hash);
echo "<pre>";
print_r($info);
echo "</pre>";

// Costo del algoritmo
$costo = 12;
$hash_costo = password_hash($password, PASSWORD_DEFAULT, ['cost' => $costo]);
echo "<p><strong>Hash con costo $costo:</strong> $hash_costo</p>";

// Tiempo de verificación
echo "<h3>⏱️ Tiempo de Verificación</h3>";
$inicio = microtime(true);
password_verify($password, $hash);
$fin = microtime(true);
$tiempo = ($fin - $inicio) * 1000;
echo "<p><strong>Tiempo de verificación:</strong> " . number_format($tiempo, 4) . " ms</p>";

echo "<hr>";
echo "<h3>💡 Instrucciones para el Sistema</h3>";
echo "<p>1. <strong>Copiar el hash generado</strong> y reemplazarlo en el archivo <code>database/schema.sql</code></p>";
echo "<p>2. <strong>O usar directamente:</strong> admin@sistema.com / admin123</p>";
echo "<p>3. <strong>Verificar que la base de datos</strong> tenga el usuario con el hash correcto</p>";

// Botón para generar nuevo hash
echo "<h3>🆕 Generar Nuevo Hash</h3>";
echo "<form method='POST'>";
echo "<input type='text' name='nueva_password' placeholder='Nueva contraseña' value='admin123'>";
echo "<button type='submit'>Generar Hash</button>";
echo "</form>";

if ($_POST && isset($_POST['nueva_password'])) {
    $nueva_password = $_POST['nueva_password'];
    $nuevo_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
    echo "<h4>🎉 Nuevo Hash Generado</h4>";
    echo "<p><strong>Contraseña:</strong> $nueva_password</p>";
    echo "<p><strong>Hash:</strong> <code>$nuevo_hash</code></p>";
    echo "<p><strong>SQL para insertar:</strong></p>";
    echo "<pre>";
    echo "INSERT INTO usuarios (nombre, apellido, email, password, pais, cargo, activo, primer_acceso) VALUES ";
    echo "('Administrador', 'Sistema', 'admin@sistema.com', '$nuevo_hash', 'México', 'Administrador del Sistema', 1, 0);";
    echo "</pre>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
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
form {
    margin: 20px 0;
}
input[type="text"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 10px;
    width: 200px;
}
button {
    padding: 8px 16px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover {
    background: #0056b3;
}
</style>
