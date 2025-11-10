<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['solicitud_id'])) {
            obtenerVehiculosSolicitud($_GET['solicitud_id']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        }
        break;
        
    case 'POST':
        guardarVehiculos();
        break;
        
    case 'DELETE':
        eliminarVehiculo($_GET['id'] ?? null);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

function obtenerVehiculosSolicitud($solicitud_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehiculos_solicitud WHERE solicitud_id = ? ORDER BY orden ASC");
        $stmt->execute([$solicitud_id]);
        $vehiculos = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $vehiculos]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener vehículos']);
    }
}

function guardarVehiculos() {
    global $pdo;
    
    try {
        $solicitud_id = $_POST['solicitud_id'] ?? null;
        $vehiculos = json_decode($_POST['vehiculos'], true);
        
        if (!$solicitud_id) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        // Eliminar vehículos existentes
        $stmt = $pdo->prepare("DELETE FROM vehiculos_solicitud WHERE solicitud_id = ?");
        $stmt->execute([$solicitud_id]);
        
        // Insertar nuevos vehículos
        $stmt = $pdo->prepare("
            INSERT INTO vehiculos_solicitud (solicitud_id, marca, modelo, anio, kilometraje, precio, abono_porcentaje, abono_monto, orden)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($vehiculos as $index => $vehiculo) {
            $stmt->execute([
                $solicitud_id,
                $vehiculo['marca'] ?? null,
                $vehiculo['modelo'] ?? null,
                $vehiculo['anio'] ?? null,
                $vehiculo['kilometraje'] ?? null,
                $vehiculo['precio'] ?? null,
                $vehiculo['abono_porcentaje'] ?? null,
                $vehiculo['abono_monto'] ?? null,
                $index + 1
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Vehículos guardados correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar vehículos']);
    }
}

function eliminarVehiculo($id) {
    global $pdo;
    
    try {
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de vehículo requerido']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM vehiculos_solicitud WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Vehículo eliminado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar vehículo']);
    }
}
?>
