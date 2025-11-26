<?php
/**
 * Script de prueba para el sistema de correos con SendGrid
 * 
 * Uso: php test_email.php
 */

// Cargar configuración
// Nota: No necesitamos database.php para probar el sistema de correos
require_once 'includes/EmailService.php';

echo "=== Prueba del Sistema de Correos ===\n\n";

// Solicitar email de prueba
echo "Ingrese el email de destino para la prueba: ";
$email = trim(fgets(STDIN));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Email inválido\n";
    exit(1);
}

echo "\nEnviando correo de prueba a: $email\n";

try {
    $emailService = new EmailService();
    
    $resultado = $emailService->enviarCorreo(
        $email,
        'Usuario de Prueba',
        'Prueba del Sistema de Correos - Automarket Rent a Car',
        '
        <h2>Correo de Prueba</h2>
        <p>Este es un correo de prueba del sistema Automarket Rent a Car.</p>
        <p>Si recibiste este correo, significa que la configuración de SendGrid está correcta.</p>
        <p><strong>Fecha:</strong> ' . date('Y-m-d H:i:s') . '</p>
        ',
        'Este es un correo de prueba del sistema Automarket Rent a Car. Si recibiste este correo, significa que la configuración de SendGrid está correcta.'
    );
    
    if ($resultado['success']) {
        echo "\n✓ Correo enviado correctamente!\n";
        echo "Mensaje: " . $resultado['message'] . "\n";
    } else {
        echo "\n✗ Error al enviar correo\n";
        echo "Mensaje: " . $resultado['message'] . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Prueba completada ===\n";

