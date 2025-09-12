<?php
/**
 * Script para probar la conexión con Pipedrive
 */

// Configuración de Pipedrive
$apiKey = '0aabc590a7654fa313f2b195c2fb8657f0a4c098';
$baseUrl = 'https://api.pipedrive.com/v1';

echo "=== PRUEBA DE CONEXIÓN CON PIPEDRIVE ===\n\n";

// Probar conexión básica
$url = $baseUrl . '/users/me?api_token=' . $apiKey;

echo "1. Probando conexión básica...\n";
echo "URL: $url\n\n";

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
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    echo "❌ Error de cURL: $error\n";
    exit;
}

echo "Código HTTP: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ Conexión exitosa!\n";
        echo "Usuario: " . $data['data']['name'] . "\n";
        echo "Email: " . $data['data']['email'] . "\n";
        echo "ID: " . $data['data']['id'] . "\n\n";
        
        // Probar obtener leads
        echo "2. Probando obtener leads...\n";
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
        
        if ($leadsHttpCode === 200) {
            $leadsData = json_decode($leadsResponse, true);
            
            if ($leadsData && isset($leadsData['success']) && $leadsData['success']) {
                echo "✅ Leads obtenidos correctamente!\n";
                echo "Total de leads: " . count($leadsData['data']) . "\n\n";
                
                echo "Primeros 3 leads:\n";
                foreach (array_slice($leadsData['data'], 0, 3) as $lead) {
                    echo "- ID: {$lead['id']} | Nombre: " . ($lead['name'] ?? 'Sin nombre') . "\n";
                    if (!empty($lead['email'])) {
                        echo "  Email: " . $lead['email'][0]['value'] . "\n";
                    }
                    if (!empty($lead['phone'])) {
                        echo "  Teléfono: " . $lead['phone'][0]['value'] . "\n";
                    }
                    echo "\n";
                }
                
                echo "✅ La integración con Pipedrive está funcionando correctamente!\n";
                echo "Puedes usar la página de integración en el sistema.\n";
                
            } else {
                echo "❌ Error al obtener leads: " . ($leadsData['error'] ?? 'Error desconocido') . "\n";
            }
        } else {
            echo "❌ Error HTTP al obtener leads: $leadsHttpCode\n";
        }
        
    } else {
        echo "❌ Error en respuesta: " . ($data['error'] ?? 'Error desconocido') . "\n";
    }
} else {
    echo "❌ Error HTTP: $httpCode\n";
    echo "Respuesta: $response\n";
}

echo "\n=== FIN DE PRUEBA ===\n";
?>
