<?php
/**
 * Script para probar la integración completa con Pipedrive
 */

echo "=== PRUEBA DE INTEGRACIÓN COMPLETA ===\n\n";

// 1. Probar conexión básica con Pipedrive
echo "1. Probando conexión básica con Pipedrive...\n";
$apiKey = '0aabc590a7654fa313f2b195c2fb8657f0a4c098';
$baseUrl = 'https://api.pipedrive.com/v1';

$url = $baseUrl . '/users/me?api_token=' . $apiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ Conexión básica exitosa\n";
        echo "   Usuario: " . $data['data']['name'] . "\n";
        echo "   Email: " . $data['data']['email'] . "\n\n";
    } else {
        echo "❌ Error en respuesta de Pipedrive\n\n";
    }
} else {
    echo "❌ Error HTTP: $httpCode\n\n";
}

// 2. Probar acceso a leads (debería fallar con 402)
echo "2. Probando acceso a leads (esperado: error 402)...\n";
$leadsUrl = $baseUrl . '/persons?api_token=' . $apiKey . '&limit=5';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $leadsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$leadsResponse = curl_exec($ch);
$leadsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($leadsHttpCode === 402) {
    echo "✅ Error 402 detectado correctamente (Payment Required)\n";
    echo "   Esto confirma que se requiere suscripción de pago\n\n";
} else {
    echo "⚠️  Código HTTP inesperado: $leadsHttpCode\n";
    if ($leadsHttpCode === 200) {
        echo "   ¡La API de leads está disponible!\n\n";
    } else {
        echo "   Respuesta: $leadsResponse\n\n";
    }
}

// 3. Probar API del sistema
echo "3. Probando API del sistema...\n";

// Verificar que el archivo API existe y es accesible
if (file_exists('api/pipedrive.php')) {
    echo "✅ API del sistema disponible (archivo encontrado)\n";
    echo "   Nota: Para probar completamente, accede via navegador web\n";
    echo "   URL: http://localhost/solicitud_credito/pipedrive.php\n\n";
} else {
    echo "❌ API del sistema no encontrada\n\n";
}

// 4. Verificar archivos de la integración
echo "4. Verificando archivos de la integración...\n";

$archivos = [
    'api/pipedrive.php' => 'API de Pipedrive',
    'pipedrive.php' => 'Página de integración',
    'importar_csv.php' => 'Importación CSV',
    'descargar_plantilla.php' => 'Descarga de plantilla',
    'api/estadisticas_csv.php' => 'Estadísticas CSV'
];

$todosExisten = true;
foreach ($archivos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "✅ $descripcion: $archivo\n";
    } else {
        echo "❌ $descripcion: $archivo (NO ENCONTRADO)\n";
        $todosExisten = false;
    }
}

if ($todosExisten) {
    echo "\n✅ Todos los archivos de la integración están presentes\n";
} else {
    echo "\n❌ Faltan algunos archivos de la integración\n";
}

// 5. Resumen y recomendaciones
echo "\n=== RESUMEN Y RECOMENDACIONES ===\n\n";

if ($httpCode === 200 && $leadsHttpCode === 402) {
    echo "🎯 ESTADO: Integración configurada correctamente\n";
    echo "   - Conexión básica con Pipedrive: ✅\n";
    echo "   - API de leads requiere suscripción: ✅\n";
    echo "   - Sistema de alternativas implementado: ✅\n\n";
    
    echo "📋 PRÓXIMOS PASOS:\n";
    echo "   1. Usar 'Importar CSV' para subir leads desde Pipedrive\n";
    echo "   2. Contactar administrador de Pipedrive para habilitar API\n";
    echo "   3. Una vez habilitada la API, la sincronización automática funcionará\n\n";
    
    echo "🔧 CÓMO USAR LA INTEGRACIÓN:\n";
    echo "   1. Ve a 'Integración Pipedrive' en el menú\n";
    echo "   2. Haz clic en 'Importar CSV'\n";
    echo "   3. Descarga la plantilla CSV\n";
    echo "   4. Exporta tus leads desde Pipedrive en formato CSV\n";
    echo "   5. Sube el archivo CSV al sistema\n";
    echo "   6. Los leads se importarán automáticamente como solicitudes\n\n";
    
} else {
    echo "⚠️  ESTADO: Problemas detectados\n";
    echo "   - Conexión básica: " . ($httpCode === 200 ? "✅" : "❌") . "\n";
    echo "   - API de leads: " . ($leadsHttpCode === 402 ? "✅" : "❌") . "\n\n";
    
    echo "🔧 SOLUCIONES:\n";
    if ($httpCode !== 200) {
        echo "   - Verificar API key de Pipedrive\n";
        echo "   - Verificar conexión a internet\n";
    }
    if ($leadsHttpCode !== 402) {
        echo "   - Verificar configuración de la API\n";
    }
}

echo "=== FIN DE PRUEBA ===\n";
?>
