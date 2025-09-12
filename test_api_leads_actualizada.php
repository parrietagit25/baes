<?php
/**
 * Script para probar la API de leads actualizada según la documentación oficial
 */

echo "=== PRUEBA DE API DE LEADS ACTUALIZADA ===\n\n";

// Configuración de Pipedrive
$apiKey = '0aabc590a7654fa313f2b195c2fb8657f0a4c098';
$baseUrl = 'https://api.pipedrive.com/v1';

// 1. Probar conexión básica
echo "1. Probando conexión básica...\n";
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

// 2. Probar API de leads (nueva implementación)
echo "2. Probando API de leads (nueva implementación)...\n";
$leadsUrl = $baseUrl . '/leads?api_token=' . $apiKey . '&limit=5';

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

echo "Código HTTP: $leadsHttpCode\n";

if ($leadsHttpCode === 200) {
    $leadsData = json_decode($leadsResponse, true);
    
    if ($leadsData && isset($leadsData['success']) && $leadsData['success']) {
        echo "✅ API de leads funcionando correctamente!\n";
        echo "Total de leads: " . count($leadsData['data']) . "\n\n";
        
        if (count($leadsData['data']) > 0) {
            echo "Primeros leads encontrados:\n";
            foreach (array_slice($leadsData['data'], 0, 3) as $lead) {
                echo "- ID: {$lead['id']} | Título: " . ($lead['title'] ?? 'Sin título') . "\n";
                echo "  Persona ID: " . ($lead['person_id'] ?? 'N/A') . "\n";
                echo "  Organización ID: " . ($lead['organization_id'] ?? 'N/A') . "\n";
                if (isset($lead['value'])) {
                    echo "  Valor: {$lead['value']['amount']} {$lead['value']['currency']}\n";
                }
                echo "  Estado: " . (isset($lead['is_archived']) && $lead['is_archived'] ? 'Archivado' : 'Activo') . "\n";
                echo "\n";
            }
        } else {
            echo "No hay leads disponibles en tu cuenta.\n";
        }
        
        echo "✅ La integración con la API de leads está funcionando correctamente!\n";
        echo "Puedes usar la sincronización automática desde el sistema.\n";
        
    } else {
        echo "❌ Error en respuesta de leads: " . ($leadsData['error'] ?? 'Error desconocido') . "\n";
    }
} else if ($leadsHttpCode === 402) {
    echo "⚠️  Error 402: Se requiere suscripción de pago para acceder a la API de leads\n";
    echo "   Esto puede indicar que tu plan de Pipedrive no incluye acceso a leads\n";
} else {
    echo "❌ Error HTTP inesperado: $leadsHttpCode\n";
    echo "Respuesta: $leadsResponse\n";
}

// 3. Probar API de personas (para verificar que funciona)
echo "\n3. Probando API de personas (para verificar acceso)...\n";
$personsUrl = $baseUrl . '/persons?api_token=' . $apiKey . '&limit=3';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $personsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$personsResponse = curl_exec($ch);
$personsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($personsHttpCode === 200) {
    $personsData = json_decode($personsResponse, true);
    if ($personsData && isset($personsData['success']) && $personsData['success']) {
        echo "✅ API de personas funcionando correctamente\n";
        echo "Total de personas: " . count($personsData['data']) . "\n";
    } else {
        echo "❌ Error en API de personas\n";
    }
} else {
    echo "❌ Error HTTP en API de personas: $personsHttpCode\n";
}

echo "\n=== RESUMEN ===\n";
if ($httpCode === 200 && $leadsHttpCode === 200) {
    echo "🎯 ESTADO: Integración completa funcionando\n";
    echo "   - Conexión básica: ✅\n";
    echo "   - API de leads: ✅\n";
    echo "   - API de personas: " . ($personsHttpCode === 200 ? "✅" : "❌") . "\n";
    echo "\n📋 PRÓXIMOS PASOS:\n";
    echo "   1. Usa la sincronización automática desde el sistema\n";
    echo "   2. Los leads se importarán automáticamente\n";
    echo "   3. Revisa las estadísticas en tiempo real\n";
} else if ($httpCode === 200 && $leadsHttpCode === 402) {
    echo "⚠️  ESTADO: Conexión OK, pero leads requieren suscripción\n";
    echo "   - Conexión básica: ✅\n";
    echo "   - API de leads: ❌ (Requiere suscripción)\n";
    echo "   - API de personas: " . ($personsHttpCode === 200 ? "✅" : "❌") . "\n";
    echo "\n📋 PRÓXIMOS PASOS:\n";
    echo "   1. Usa la importación CSV como alternativa\n";
    echo "   2. Contacta a Pipedrive para habilitar leads\n";
    echo "   3. Una vez habilitado, la sincronización automática funcionará\n";
} else {
    echo "❌ ESTADO: Problemas de conexión\n";
    echo "   - Conexión básica: " . ($httpCode === 200 ? "✅" : "❌") . "\n";
    echo "   - API de leads: " . ($leadsHttpCode === 200 ? "✅" : "❌") . "\n";
    echo "\n🔧 SOLUCIONES:\n";
    echo "   - Verificar API key de Pipedrive\n";
    echo "   - Verificar conexión a internet\n";
    echo "   - Contactar soporte de Pipedrive\n";
}

echo "\n=== FIN DE PRUEBA ===\n";
?>
