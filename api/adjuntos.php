<?php
/**
 * API para gestión de adjuntos de solicitudes
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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['solicitud_id'])) {
            obtenerAdjuntos($_GET['solicitud_id']);
        } elseif (isset($_GET['id'])) {
            if (isset($_GET['action']) && $_GET['action'] === 'descargar') {
                descargarAdjunto($_GET['id']);
            } else {
                obtenerAdjunto($_GET['id']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        }
        break;
        
    case 'POST':
        subirAdjunto();
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            eliminarAdjunto($_GET['id']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de adjunto requerido']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerAdjuntos($solicitudId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.nombre, u.apellido
            FROM adjuntos_solicitud a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.solicitud_id = ?
            ORDER BY a.fecha_subida DESC
        ");
        $stmt->execute([$solicitudId]);
        $adjuntos = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $adjuntos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerAdjunto($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.nombre, u.apellido
            FROM adjuntos_solicitud a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $adjunto = $stmt->fetch();
        
        if ($adjunto) {
            echo json_encode(['success' => true, 'data' => $adjunto]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Adjunto no encontrado']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function subirAdjunto() {
    global $pdo;
    
    try {
        // Log de depuración
        error_log("=== SUBIR ADJUNTO DEBUG ===");
        error_log("FILES: " . print_r($_FILES, true));
        error_log("POST: " . print_r($_POST, true));
        
        // Verificar que se envió un archivo
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            error_log("Error: Archivo no enviado o error en upload. Error code: " . ($_FILES['archivo']['error'] ?? 'NO_FILES'));
            echo json_encode(['success' => false, 'message' => 'No se envió ningún archivo o hubo un error']);
            return;
        }
        
        $solicitudId = $_POST['solicitud_id'] ?? null;
        $descripcion = $_POST['descripcion'] ?? '';
        
        if (!$solicitudId) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        // Verificar que la solicitud existe
        $stmt = $pdo->prepare("SELECT id FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        $archivo = $_FILES['archivo'];
        $nombreOriginal = $archivo['name'];
        $tipoArchivo = $archivo['type'];
        $tamañoArchivo = $archivo['size'];
        
        // Validar tamaño (máximo 10MB)
        if ($tamañoArchivo > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 10MB']);
            return;
        }
        
        // Validar tipo de archivo
        $tiposPermitidos = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain'
        ];
        
        if (!in_array($tipoArchivo, $tiposPermitidos)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
            return;
        }
        
        // Generar nombre único para el archivo
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
        $rutaArchivo = '../adjuntos/solicitudes/' . $nombreArchivo;
        $rutaArchivoDB = 'adjuntos/solicitudes/' . $nombreArchivo; // Ruta para la base de datos
        
        // Crear directorio si no existe
        $directorio = dirname($rutaArchivo);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        // Mover archivo
        error_log("Intentando mover archivo de: " . $archivo['tmp_name'] . " a: " . $rutaArchivo);
        error_log("Archivo temporal existe: " . (file_exists($archivo['tmp_name']) ? 'SÍ' : 'NO'));
        error_log("Directorio destino existe: " . (is_dir($directorio) ? 'SÍ' : 'NO'));
        error_log("Permisos directorio: " . substr(sprintf('%o', fileperms($directorio)), -4));
        
        if (move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
            error_log("Archivo movido exitosamente");
            // Guardar en base de datos
            $stmt = $pdo->prepare("
                INSERT INTO adjuntos_solicitud 
                (solicitud_id, usuario_id, nombre_archivo, nombre_original, ruta_archivo, tipo_archivo, tamaño_archivo, descripcion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $solicitudId,
                $_SESSION['user_id'],
                $nombreArchivo,
                $nombreOriginal,
                $rutaArchivoDB,
                $tipoArchivo,
                $tamañoArchivo,
                $descripcion
            ]);
            
            $adjuntoId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Archivo subido correctamente',
                'data' => [
                    'id' => $adjuntoId,
                    'nombre_original' => $nombreOriginal,
                    'tamaño' => $tamañoArchivo,
                    'tipo' => $tipoArchivo
                ]
            ]);
        } else {
            error_log("Error al mover archivo. Error: " . error_get_last()['message']);
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function eliminarAdjunto($id) {
    global $pdo;
    
    try {
        // Obtener información del adjunto
        $stmt = $pdo->prepare("SELECT * FROM adjuntos_solicitud WHERE id = ?");
        $stmt->execute([$id]);
        $adjunto = $stmt->fetch();
        
        if (!$adjunto) {
            echo json_encode(['success' => false, 'message' => 'Adjunto no encontrado']);
            return;
        }
        
        // Verificar permisos (solo el usuario que subió el archivo o admin puede eliminarlo)
        if ($adjunto['usuario_id'] != $_SESSION['user_id'] && !in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar este archivo']);
            return;
        }
        
        // Eliminar archivo físico (ajustar ruta relativa desde api/)
        $rutaArchivo = '../' . $adjunto['ruta_archivo'];
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }
        
        // Eliminar de base de datos
        $stmt = $pdo->prepare("DELETE FROM adjuntos_solicitud WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Adjunto eliminado correctamente']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function descargarAdjunto($id) {
    global $pdo;
    
    try {
        error_log("=== DESCARGAR ADJUNTO DEBUG ===");
        error_log("ID solicitado: " . $id);
        
        // Obtener información del adjunto
        $stmt = $pdo->prepare("SELECT * FROM adjuntos_solicitud WHERE id = ?");
        $stmt->execute([$id]);
        $adjunto = $stmt->fetch();
        
        error_log("Adjunto encontrado: " . print_r($adjunto, true));
        
        if (!$adjunto) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
            return;
        }
        
        // Verificar que el archivo existe (ajustar ruta relativa desde api/)
        $rutaArchivo = '../' . $adjunto['ruta_archivo'];
        if (!file_exists($rutaArchivo)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El archivo no existe en el servidor: ' . $rutaArchivo]);
            return;
        }
        
        // Limpiar buffer de salida
        ob_clean();
        
        // Configurar headers para descarga
        header('Content-Type: ' . $adjunto['tipo_archivo']);
        header('Content-Disposition: attachment; filename="' . $adjunto['nombre_original'] . '"');
        header('Content-Length: ' . $adjunto['tamaño_archivo']);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Leer y enviar el archivo
        readfile($rutaArchivo);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
