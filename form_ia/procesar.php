<?php
header('Content-Type: application/json');

// ==========================================
// CONFIGURACIÓN DE GOOGLE CLOUD VISION API
// ==========================================
$googleVisionApiKey = ''; 

// ==========================================
// CONFIGURACIÓN DE BASE DE DATOS (MySQL)
// ==========================================
$dbHost = '127.0.0.1';
$dbUser = 'root'; // Usuario por defecto de XAMPP
$dbPass = '';     // Contraseña por defecto vacía
$dbName = 'datos_form';

// Directorios de subida
$uploadDirCedulas = 'uploads/cedulas/';
$uploadDirFirmas = 'uploads/firmas/';
$uploadDirFirmasExtraidas = 'uploads/firmas_extraidas/';

// Crear directorios si no existen
if (!file_exists($uploadDirCedulas)) mkdir($uploadDirCedulas, 0777, true);
if (!file_exists($uploadDirFirmas)) mkdir($uploadDirFirmas, 0777, true);
if (!file_exists($uploadDirFirmasExtraidas)) mkdir($uploadDirFirmasExtraidas, 0777, true);

$response = [
    'success' => false,
    'message' => '',
    'raw_text' => '',
    'parsed_data' => [],
    'cedula_path' => '',
    'firma_path' => '',
    'firma_extraida_path' => ''
];

try {
    // 0. PREPARAR BASE DE DATOS
    // Conectar a MySQL (sin seleccionar base de datos aún)
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear Base de Datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    $pdo->exec("USE `$dbName`");

    // Crear Tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `registros` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `numero_cedula` VARCHAR(50),
        `texto_crudo` TEXT,
        `foto_cedula_path` VARCHAR(255),
        `firma_dibujada_path` VARCHAR(255),
        `firma_extraida_path` VARCHAR(255),
        `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 1. PROCESAR FOTO DE LA CÉDULA EDITADA (BASE64)
    if (!isset($_POST['cedula_editada']) || empty($_POST['cedula_editada'])) {
        throw new Exception("Error: No se recibió la imagen de la cédula recortada.");
    }

    $cedulaBase64 = $_POST['cedula_editada'];
    // Remover el encabezado 'data:image/jpeg;base64,' o 'data:image/png;base64,'
    $cedulaParts = explode(',', $cedulaBase64);
    if (count($cedulaParts) !== 2) {
         throw new Exception("Formato de cédula inválido.");
    }
    $cedulaData = base64_decode($cedulaParts[1]);
    
    $cedulaName = 'cedula_' . time() . '_' . uniqid() . '.jpg';
    $cedulaPath = $uploadDirCedulas . $cedulaName;

    if (file_put_contents($cedulaPath, $cedulaData) === false) {
        throw new Exception("No se pudo guardar la imagen de la cédula en el servidor.");
    }
    $response['cedula_path'] = $cedulaPath;

    // 2. PROCESAR FIRMA DIBUJADA (BASE64)
    if (!isset($_POST['firma']) || empty($_POST['firma'])) {
        throw new Exception("No se recibió la firma dibujada.");
    }
    $firmaBase64 = $_POST['firma'];
    $firmaParts = explode(',', $firmaBase64);
    $firmaData = base64_decode($firmaParts[1]);
    $firmaName = 'firma_dibujada_' . time() . '_' . uniqid() . '.png';
    $firmaPath = $uploadDirFirmas . $firmaName;

    if (file_put_contents($firmaPath, $firmaData) === false) {
        throw new Exception("No se pudo guardar la firma dibujada.");
    }
    $response['firma_path'] = $firmaPath;

    // 2.5 PROCESAR FIRMA EXTRAÍDA (CROPPER - BASE64)
    $firmaExtraidaPath = null;
    if (isset($_POST['firma_extraida']) && !empty($_POST['firma_extraida'])) {
        $firmaExtraidaBase64 = $_POST['firma_extraida'];
        $firmaExtParts = explode(',', $firmaExtraidaBase64);
        if(count($firmaExtParts) == 2) {
            $firmaExtData = base64_decode($firmaExtParts[1]);
            $firmaExtName = 'firma_recortada_' . time() . '_' . uniqid() . '.png';
            $firmaExtraidaPath = $uploadDirFirmasExtraidas . $firmaExtName;
            file_put_contents($firmaExtraidaPath, $firmaExtData);
            $response['firma_extraida_path'] = $firmaExtraidaPath;
        }
    }

    // 3. LLAMADA A GOOGLE CLOUD VISION API (OCR)
    $numeroCedulaEncontrado = null;
    $rawText = "";

    if (!empty($googleVisionApiKey) && $googleVisionApiKey !== 'TU_API_KEY_DE_GOOGLE_AQUI') {
        $imageData = base64_encode(file_get_contents($cedulaPath));
        
        $requestData = [
            'requests' => [
                [
                    'image' => ['content' => $imageData],
                    'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]]
                ]
            ]
        ];

        $jsonRequest = json_encode($requestData);
        $url = "https://vision.googleapis.com/v1/images:annotate?key=" . $googleVisionApiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonRequest)
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $visionResponse = json_decode($result, true);
            if (isset($visionResponse['responses'][0]['textAnnotations'][0]['description'])) {
                $rawText = $visionResponse['responses'][0]['textAnnotations'][0]['description'];
                $response['raw_text'] = $rawText;
                
                // EXPRESIÓN REGULAR PARA CÉDULAS DE PANAMÁ
                // Detecta formatos como: 8-123-456, PE-12-345, E-8-12345, 20-12-1234
                if (preg_match('/(?:PE|E|N|NT|\d{1,2})[- ]?\d{1,4}[- ]?\d{1,6}/i', $rawText, $matches)) {
                    $numeroCedulaEncontrado = trim($matches[0]);
                    $response['parsed_data']['Cédula de Panamá'] = $numeroCedulaEncontrado;
                }
            } else {
                 $response['raw_text'] = "Google Vision no detectó texto en la imagen.";
            }
        } else {
             $response['raw_text'] = "Error de API Vision: $httpCode. $result";
        }
    } else {
         $response['raw_text'] = "FALTA CONFIGURAR API KEY de Google Cloud.";
    }

    // 4. GUARDAR EN BASE DE DATOS
    $stmt = $pdo->prepare("INSERT INTO registros (numero_cedula, texto_crudo, foto_cedula_path, firma_dibujada_path, firma_extraida_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $numeroCedulaEncontrado,
        $rawText,
        $cedulaPath,
        $firmaPath,
        $firmaExtraidaPath
    ]);

    $response['success'] = true;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
