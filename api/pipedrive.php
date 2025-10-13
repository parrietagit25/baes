<?php
/**
 * API para integración con Pipedrive
 */

session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';

// Configuración de Pipedrive
define('PIPEDRIVE_API_KEY', '9c8606b29310e29b3880066aad0426b59a555cfc');
define('PIPEDRIVE_BASE_URL', 'https://grupopcr.pipedrive.com/api/v1');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'leads':
                    obtenerLeads();
                    break;
                case 'sync':
                    sincronizarLeads();
                    break;
                case 'test':
                    probarConexion();
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                    break;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción requerida']);
        }
        break;
        
    case 'POST':
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'import_lead':
                    importarLead();
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                    break;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción requerida']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function probarConexion() {
    $url = PIPEDRIVE_BASE_URL . '/users/me?api_token=' . PIPEDRIVE_API_KEY;
    
    $response = hacerPeticionPipedrive($url);
    
    if ($response && isset($response['success']) && $response['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Conexión exitosa con Pipedrive',
            'data' => [
                'usuario' => $response['data']['name'],
                'email' => $response['data']['email']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error de conexión con Pipedrive: ' . ($response['error'] ?? 'Error desconocido')
        ]);
    }
}

function obtenerLeads() {
    // Obtener leads recientes y activos (no archivados)
    $url = PIPEDRIVE_BASE_URL . '/leads?api_token=' . PIPEDRIVE_API_KEY . '&limit=50&sort=add_time&sort_direction=desc';
    
    $response = hacerPeticionPipedrive($url);
    
    if ($response && isset($response['success']) && $response['success']) {
        // Filtrar solo leads activos (no archivados)
        $leadsActivos = array_filter($response['data'], function($lead) {
            return !isset($lead['is_archived']) || !$lead['is_archived'];
        });
        
        // Reindexar el array
        $leadsActivos = array_values($leadsActivos);
        
        echo json_encode([
            'success' => true,
            'data' => $leadsActivos,
            'filtros_aplicados' => [
                'solo_activos' => true,
                'ordenado_por_fecha' => 'descendente',
                'total_encontrados' => count($leadsActivos),
                'total_original' => count($response['data'])
            ]
        ]);
    } else {
        // Verificar si es error 402 (Payment Required)
        if (isset($response['error']) && strpos($response['error'], '402') !== false) {
            echo json_encode([
                'success' => false,
                'message' => 'Se requiere suscripción de pago en Pipedrive para acceder a la API de leads',
                'error_code' => 'PAYMENT_REQUIRED',
                'suggestion' => 'Contacta al administrador de Pipedrive para habilitar el acceso a la API'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener leads: ' . ($response['error'] ?? 'Error desconocido')
            ]);
        }
    }
}

function sincronizarLeads() {
    global $pdo;
    
    try {
        // Obtener leads recientes y activos de Pipedrive
        $url = PIPEDRIVE_BASE_URL . '/leads?api_token=' . PIPEDRIVE_API_KEY . '&limit=100&sort=add_time&sort_direction=desc';
        $response = hacerPeticionPipedrive($url);
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener leads de Pipedrive']);
            return;
        }
        
        // Filtrar solo leads activos (no archivados)
        $leads = array_filter($response['data'], function($lead) {
            return !isset($lead['is_archived']) || !$lead['is_archived'];
        });
        
        $leads = array_values($leads); // Reindexar
        $importados = 0;
        $errores = [];
        $saltados = 0;
        
        foreach ($leads as $lead) {
            try {
                // Obtener información de la persona asociada al lead
                $personaInfo = obtenerInfoPersona($lead['person_id'] ?? null);
                
                if (!$personaInfo) {
                    $errores[] = "Lead {$lead['id']}: No se pudo obtener información de la persona";
                    continue;
                }
                
                // Verificar si el lead ya existe (por email)
                if (empty($personaInfo['email'])) {
                    $saltados++;
                    continue;
                }
                
                $email = $personaInfo['email'];
                $stmt = $pdo->prepare("SELECT id FROM solicitudes_credito WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $saltados++; // Ya existe, saltar
                    continue;
                }
                
                // Crear solicitud desde el lead
                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_credito (
                        gestor_id, tipo_persona, nombre_cliente, cedula, telefono, email,
                        direccion, comentarios_gestor, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $nombre = $personaInfo['name'] ?? 'Sin nombre';
                $cedula = 'PIPEDRIVE-' . $lead['id']; // Usar ID de Pipedrive como cédula temporal
                $telefono = $personaInfo['phone'] ?? null;
                $direccion = $personaInfo['address'] ?? null;
                $comentarios = "Lead importado desde Pipedrive (Lead ID: {$lead['id']}, Persona ID: {$lead['person_id']})";
                
                $stmt->execute([
                    $_SESSION['user_id'], // Gestor actual
                    'Natural', // Por defecto persona natural
                    $nombre,
                    $cedula,
                    $telefono,
                    $email,
                    $direccion,
                    $comentarios,
                    'Nueva'
                ]);
                
                $solicitudId = $pdo->lastInsertId();
                
                // Crear nota de importación
                $stmt = $pdo->prepare("
                    INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                    VALUES (?, ?, 'Actualización', 'Lead Importado', ?)
                ");
                $stmt->execute([$solicitudId, $_SESSION['user_id'], $comentarios]);
                
                $importados++;
                
            } catch (Exception $e) {
                $errores[] = "Error con lead {$lead['id']}: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Sincronización completada - Solo leads recientes y activos",
            'data' => [
                'importados' => $importados,
                'saltados' => $saltados,
                'errores' => $errores,
                'total_procesados' => count($leads),
                'filtros_aplicados' => [
                    'solo_activos' => true,
                    'ordenado_por_fecha' => 'descendente',
                    'leads_archivados_excluidos' => true
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error en sincronización: ' . $e->getMessage()]);
    }
}

function obtenerInfoPersona($personId) {
    if (!$personId) return null;
    
    $url = PIPEDRIVE_BASE_URL . '/persons/' . $personId . '?api_token=' . PIPEDRIVE_API_KEY;
    $response = hacerPeticionPipedrive($url);
    
    if ($response && isset($response['success']) && $response['success']) {
        $persona = $response['data'];
        return [
            'name' => $persona['name'] ?? null,
            'email' => !empty($persona['email']) ? $persona['email'][0]['value'] : null,
            'phone' => !empty($persona['phone']) ? $persona['phone'][0]['value'] : null,
            'address' => $persona['address'] ?? null
        ];
    }
    
    return null;
}

function importarLead() {
    global $pdo;
    
    try {
        $leadId = $_POST['lead_id'];
        
        // Obtener datos específicos del lead usando la API correcta
        $url = PIPEDRIVE_BASE_URL . '/leads/' . $leadId . '?api_token=' . PIPEDRIVE_API_KEY;
        $response = hacerPeticionPipedrive($url);
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener lead de Pipedrive']);
            return;
        }
        
        $lead = $response['data'];
        
        // Obtener información de la persona asociada
        $personaInfo = obtenerInfoPersona($lead['person_id'] ?? null);
        
        if (!$personaInfo) {
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener información de la persona asociada al lead']);
            return;
        }
        
        // Verificar si ya existe
        if (!empty($personaInfo['email'])) {
            $email = $personaInfo['email'];
            $stmt = $pdo->prepare("SELECT id FROM solicitudes_credito WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este lead ya ha sido importado']);
                return;
            }
        }
        
        // Crear solicitud
        $stmt = $pdo->prepare("
            INSERT INTO solicitudes_credito (
                gestor_id, tipo_persona, nombre_cliente, cedula, telefono, email,
                direccion, comentarios_gestor, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $nombre = $personaInfo['name'] ?? 'Sin nombre';
        $cedula = 'PIPEDRIVE-' . $lead['id'];
        $telefono = $personaInfo['phone'] ?? null;
        $email = $personaInfo['email'] ?? null;
        $direccion = $personaInfo['address'] ?? null;
        $comentarios = "Lead importado desde Pipedrive (Lead ID: {$lead['id']}, Persona ID: {$lead['person_id']})";
        
        $stmt->execute([
            $_SESSION['user_id'],
            'Natural',
            $nombre,
            $cedula,
            $telefono,
            $email,
            $direccion,
            $comentarios,
            'Nueva'
        ]);
        
        $solicitudId = $pdo->lastInsertId();
        
        // Crear nota
        $stmt = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
            VALUES (?, ?, 'Actualización', 'Lead Importado', ?)
        ");
        $stmt->execute([$solicitudId, $_SESSION['user_id'], $comentarios]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Lead importado correctamente',
            'data' => ['solicitud_id' => $solicitudId]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al importar lead: ' . $e->getMessage()]);
    }
}

function hacerPeticionPipedrive($url) {
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
        return ['success' => false, 'error' => $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'HTTP ' . $httpCode, 'http_code' => $httpCode];
    }
    
    return json_decode($response, true);
}
?>
