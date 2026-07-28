<?php
/**
 * API de reportes para administradores de banco (ROLE_ADMIN_BANCO).
 */

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/banco_scope_helper.php';
require_once __DIR__ . '/../includes/reportes_banco_usuarios_data.php';
require_once __DIR__ . '/../includes/reportes_banco_fin_enlazada_data.php';
require_once __DIR__ . '/../includes/reportes_banco_seguimiento_data.php';

if (!isset($_SESSION['user_id']) || !motus_es_admin_banco($_SESSION['user_roles'] ?? [])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

$bancoId = motus_obtener_banco_id_usuario($pdo, (int) $_SESSION['user_id']);
if (!$bancoId) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Banco no configurado']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'exportar_excel_usuarios') {
    require_once __DIR__ . '/../includes/xlsx_export.php';
    $data = reportes_banco_usuarios_data($pdo, $bancoId);
    $headers = array_merge(['Usuario', 'Email'], REPORTES_BANCO_USUARIOS_COLUMNAS, ['Total']);
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            $row['nombre'],
            $row['email'],
            $row['En revision'],
            $row['Preaprobada'],
            $row['Aprobadas'],
            $row['Completadas'],
            $row['Desistimiento'],
            $row['Rechazadas'],
            $row['total'],
        ];
    }
    motus_output_xlsx_download('reporte_usuarios_banco.xlsx', 'Usuarios Banco', $headers, $rows);
    exit();
}

if ($action === 'exportar_excel_fin_enlazada') {
    require_once __DIR__ . '/../includes/xlsx_export.php';
    $filt = rep_fin_banco_parse_filtros();
    [$headers, $rows] = rep_fin_filas_export_enlazada_banco($pdo, $bancoId, $filt);
    if ($headers === []) {
        motus_output_xlsx_download('reporte_fin_enlazada_banco.xlsx', 'Info', ['Mensaje'], [['Sin datos o migración pendiente']]);
    } else {
        motus_output_xlsx_download('reporte_fin_enlazada_banco.xlsx', 'Fin Motus Banco', $headers, $rows);
    }
    exit();
}

if ($action === 'exportar_excel_seguimiento') {
    require_once __DIR__ . '/../includes/xlsx_export.php';
    $exp = rep_segbanco_export_pack($pdo, $bancoId);
    motus_output_xlsx_download('seguimiento_banco.xlsx', 'Seguimiento', $exp['headers'], $exp['rows']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'reporte_usuarios':
        try {
            echo json_encode([
                'success' => true,
                'data' => reportes_banco_usuarios_data($pdo, $bancoId),
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
        }
        break;

    case 'solicitudes_usuario_columna':
        $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
        $columna = trim((string) ($_GET['columna'] ?? ''));
        if ($usuarioId <= 0 || $columna === '') {
            echo json_encode(['success' => false, 'message' => 'usuario_id y columna requeridos']);
            break;
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND banco_id = ? AND activo = 1 LIMIT 1');
            $stmt->execute([$usuarioId, $bancoId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Usuario no pertenece a su banco']);
                break;
            }
            echo json_encode([
                'success' => true,
                'data' => reportes_banco_usuarios_solicitudes($pdo, $bancoId, $usuarioId, $columna),
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
        }
        break;

    case 'reporte_fin_enlazada':
        try {
            echo json_encode(rep_fin_build_reporte_enlazada_banco($pdo, $bancoId), JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('reporte_fin_enlazada banco: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
        }
        break;

    case 'reporte_seguimiento':
        try {
            echo json_encode(rep_segbanco_build_reporte($pdo, $bancoId), JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('reporte_seguimiento banco: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
