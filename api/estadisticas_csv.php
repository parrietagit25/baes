<?php
/**
 * API para estadísticas de importación CSV
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

try {
    // Estadísticas de hoy
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as hoy 
        FROM solicitudes_credito 
        WHERE DATE(fecha_creacion) = CURDATE() 
        AND comentarios_gestor LIKE '%importado desde CSV%'
    ");
    $stmt->execute();
    $hoy = $stmt->fetch()['hoy'];

    // Total importados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM solicitudes_credito 
        WHERE comentarios_gestor LIKE '%importado desde CSV%'
    ");
    $stmt->execute();
    $total = $stmt->fetch()['total'];

    // Errores (solicitudes con comentarios de error)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as errores 
        FROM solicitudes_credito 
        WHERE comentarios_gestor LIKE '%Error%' 
        AND comentarios_gestor LIKE '%CSV%'
    ");
    $stmt->execute();
    $errores = $stmt->fetch()['errores'];

    // Última importación
    $stmt = $pdo->prepare("
        SELECT MAX(fecha_creacion) as ultima 
        FROM solicitudes_credito 
        WHERE comentarios_gestor LIKE '%importado desde CSV%'
    ");
    $stmt->execute();
    $ultima = $stmt->fetch()['ultima'];

    // Formatear fecha
    if ($ultima) {
        $fecha = new DateTime($ultima);
        $ultima = $fecha->format('d/m/Y H:i');
    } else {
        $ultima = '-';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'hoy' => (int)$hoy,
            'total' => (int)$total,
            'errores' => (int)$errores,
            'ultima' => $ultima
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}
?>
