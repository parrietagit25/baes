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
        // Verificar que se enviaron archivos
        if (!isset($_FILES['archivo'])) {
            echo json_encode(['success' => false, 'message' => 'No se envió ningún archivo']);
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
        
        $archivos = $_FILES['archivo'];
        $archivosSubidos = 0;
        $archivosFallidos = 0;
        $errores = [];
        
        // Detectar si es un array de archivos o un solo archivo
        $esArray = is_array($archivos['name']);
        
        if (!$esArray) {
            // Convertir a array para simplificar el procesamiento
            $archivos = [
                'name' => [$archivos['name']],
                'type' => [$archivos['type']],
                'tmp_name' => [$archivos['tmp_name']],
                'error' => [$archivos['error']],
                'size' => [$archivos['size']]
            ];
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
        
        // Crear directorio si no existe
        $directorio = '../adjuntos/solicitudes/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        // Procesar cada archivo
        for ($i = 0; $i < count($archivos['name']); $i++) {
            // Verificar errores de subida
            if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
                $errores[] = $archivos['name'][$i] . ': Error en la subida';
                $archivosFallidos++;
                continue;
            }
            
            $nombreOriginal = $archivos['name'][$i];
            $tipoArchivo = $archivos['type'][$i];
            $tamañoArchivo = $archivos['size'][$i];
            $tmpName = $archivos['tmp_name'][$i];
            
            // Validar tamaño (máximo 10MB)
            if ($tamañoArchivo > 10 * 1024 * 1024) {
                $errores[] = $nombreOriginal . ': Archivo demasiado grande (máximo 10MB)';
                $archivosFallidos++;
                continue;
            }
            
            // Validar tipo de archivo
            if (!in_array($tipoArchivo, $tiposPermitidos)) {
                $errores[] = $nombreOriginal . ': Tipo de archivo no permitido';
                $archivosFallidos++;
                continue;
            }
            
            // Generar nombre único para el archivo
            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            $nombreArchivo = uniqid() . '_' . time() . '_' . $i . '.' . $extension;
            $rutaArchivo = $directorio . $nombreArchivo;
            $rutaArchivoDB = 'adjuntos/solicitudes/' . $nombreArchivo;
            
            // Mover archivo
            if (move_uploaded_file($tmpName, $rutaArchivo)) {
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
                
                $archivosSubidos++;
            } else {
                $errores[] = $nombreOriginal . ': Error al guardar el archivo';
                $archivosFallidos++;
            }
        }
        
        // Preparar respuesta
        if ($archivosSubidos > 0) {
            $mensaje = $archivosSubidos . ' archivo(s) subido(s) correctamente';
            if ($archivosFallidos > 0) {
                $mensaje .= '. ' . $archivosFallidos . ' archivo(s) fallaron';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $mensaje,
                'data' => [
                    'count' => $archivosSubidos,
                    'failed' => $archivosFallidos,
                    'errors' => $errores
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo subir ningún archivo',
                'errors' => $errores
            ]);
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
