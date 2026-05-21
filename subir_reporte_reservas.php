<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/reporte_reservas_pagina_helper.php';

$userRoles = $_SESSION['user_roles'] ?? [];
$isAdmin = in_array('ROLE_ADMIN', $userRoles, true);
$isGestor = in_array('ROLE_GESTOR', $userRoles, true);
if (!$isAdmin && !$isGestor) {
    header('Location: dashboard.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Subida por formulario HTML (sin incluir api/ — evita salida inválida)
if (isset($_GET['servicio']) && $_GET['servicio'] === '1' && ($_GET['modo'] ?? '') === 'form') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['archivo'])) {
        $_SESSION['flash_reporte'] = ['tipo' => 'danger', 'mensaje' => 'No se recibió el archivo'];
        header('Location: subir_reporte_reservas.php');
        exit();
    }
    $resultado = reporte_reservas_subir_e_importar($pdo, $userId, $_FILES['archivo']);
    reporte_reservas_flash_desde_resultado($resultado, !empty($resultado['success']) ? 'success' : 'danger');
    header('Location: subir_reporte_reservas.php');
    exit();
}

// Descarga y API JSON
if (isset($_GET['servicio']) && $_GET['servicio'] === '1') {
    require __DIR__ . '/api/reporte_reservas.php';
    exit();
}

// Importar filas tras subida (navegación normal, sin AJAX)
if (!empty($_GET['importar_id'])) {
    $importarId = (int) $_GET['importar_id'];
    $imp = reporte_reservas_importar($pdo, $importarId, $userId);
    reporte_reservas_flash_desde_resultado($imp, 'success');
    header('Location: subir_reporte_reservas.php');
    exit();
}

// Acciones por formulario POST (procesar / eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_reporte'])) {
    $accion = (string) $_POST['accion_reporte'];
    $reporteIdPost = (int) ($_POST['reporte_id'] ?? 0);
    if ($accion === 'importar' && $reporteIdPost > 0) {
        $res = reporte_reservas_importar($pdo, $reporteIdPost, $userId);
        reporte_reservas_flash_desde_resultado($res);
        header('Location: subir_reporte_reservas.php?ver_reporte=' . $reporteIdPost);
        exit();
    }
    if ($accion === 'procesar' && $reporteIdPost > 0) {
        $res = reporte_reservas_procesar($pdo, $reporteIdPost, $userId);
        reporte_reservas_flash_desde_resultado($res);
        header('Location: subir_reporte_reservas.php?ver_reporte=' . $reporteIdPost);
        exit();
    }
    if ($accion === 'eliminar' && $isAdmin && $reporteIdPost > 0) {
        $res = reporte_reservas_eliminar($pdo, $reporteIdPost);
        reporte_reservas_flash_desde_resultado($res);
        header('Location: subir_reporte_reservas.php');
        exit();
    }
    $_SESSION['flash_reporte'] = ['tipo' => 'danger', 'mensaje' => 'Acción no permitida'];
    header('Location: subir_reporte_reservas.php');
    exit();
}

$flashReporte = $_SESSION['flash_reporte'] ?? null;
unset($_SESSION['flash_reporte']);

$reportesLista = reporte_reservas_listar($pdo);
$verReporteId = isset($_GET['ver_reporte']) ? (int) $_GET['ver_reporte'] : 0;
$lineasDetalle = $verReporteId > 0 ? reporte_reservas_listar_lineas($pdo, $verReporteId) : [];

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function badge_estado_reporte_php(?string $estado): string
{
    $e = strtolower((string) ($estado ?: 'pendiente'));
    $map = ['pendiente' => 'secondary', 'procesando' => 'warning', 'completado' => 'success', 'error' => 'danger'];
    $cls = $map[$e] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . h($e) . '</span>';
}

function badge_estado_linea_php(?string $estado): string
{
    $e = strtolower(str_replace('_', ' ', (string) ($estado ?: 'pendiente')));
    $raw = strtolower((string) ($estado ?: 'pendiente'));
    $map = ['pendiente' => 'secondary', 'aplicado' => 'success', 'sin_coincidencia' => 'warning', 'error' => 'danger'];
    $cls = $map[$raw] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . h($e) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Reporte de Reservas - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .upload-zone { border: 2px dashed #ced4da; border-radius: 12px; padding: 2rem; text-align: center; background: #fff; cursor: pointer; }
        .upload-zone.dragover { border-color: #0d6efd; background: #f0f7ff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="mb-4">
                    <h2 class="mb-1">Subir Reporte de Reservas</h2>
                    <p class="text-muted mb-0">Excel de reservas (fila 1 encabezados, datos desde fila 2, columnas A–AJ). Coincide solicitudes y aparta vehículos.</p>
                </div>
                <div class="alert alert-info py-2 small mb-3">
                    Clave de actualización: <strong>Mov ID</strong> (columna B). Cédula (I), Correo (J), Nombre (H), Marca (Q), Modelo (R), Año (U), Km (V), Total (Z), Abono (AI).
                    Si vuelve a subir el mismo archivo u otro con registros repetidos, se actualizan las filas existentes e insertan las nuevas. Tras subir use <strong>Importar</strong> y luego <strong>Procesar</strong>.
                </div>

                <?php if ($flashReporte): ?>
                <div class="alert alert-<?php echo h($flashReporte['tipo'] ?? 'info'); ?> alert-dismissible fade show">
                    <?php echo h($flashReporte['mensaje'] ?? ''); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form id="formReporteReservas" method="post" enctype="multipart/form-data"
                              action="subir_reporte_reservas.php?servicio=1&amp;modo=form">
                            <div class="upload-zone mb-3" id="uploadZone">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <p class="mb-2">Arrastre el archivo aquí o haga clic para seleccionar</p>
                                <p class="small text-muted mb-3">Formato recomendado: .xlsx (máx. 25 MB)</p>
                                <input type="file" class="form-control d-none" id="archivo_reporte" name="archivo"
                                       accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnElegirArchivo">
                                    <i class="fas fa-folder-open me-1"></i>Elegir archivo
                                </button>
                                <div id="nombreArchivoElegido" class="small text-success mt-2 fw-semibold"></div>
                            </div>
                            <button type="submit" class="btn btn-success" id="btnSubirReporte" disabled>
                                <i class="fas fa-upload me-2"></i>Subir reporte
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Reportes subidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaReportesReservas" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Archivo</th>
                                        <th>Tamaño</th>
                                        <th>Subido por</th>
                                        <th>Estado</th>
                                        <th>Filas</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($reportesLista as $row):
                                    $subidoPor = trim(($row['usuario_nombre'] ?? '') . ' ' . ($row['usuario_apellido'] ?? ''));
                                    $filasInfo = isset($row['filas_total']) ? (int) $row['filas_total'] : null;
                                    ?>
                                    <tr>
                                        <td data-order="<?php echo (int) $row['id']; ?>"><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo h($row['nombre_original'] ?? ''); ?></td>
                                        <td><?php echo h(reporte_reservas_formatear_tamano($row['tamano_bytes'] ?? 0)); ?></td>
                                        <td><?php echo h($subidoPor !== '' ? $subidoPor : '-'); ?></td>
                                        <td><?php echo isset($row['estado']) ? badge_estado_reporte_php($row['estado']) : '—'; ?></td>
                                        <td><?php
                                            if ($filasInfo !== null) {
                                                echo (int) $filasInfo;
                                                if (isset($row['filas_aplicadas'])) {
                                                    echo ' <small class="text-muted">(' . (int) $row['filas_aplicadas'] . ' ok)</small>';
                                                }
                                            } else {
                                                echo '—';
                                            }
                                        ?></td>
                                        <td><?php echo h($row['fecha_subida'] ?? ''); ?></td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary me-1" href="subir_reporte_reservas.php?servicio=1&amp;download=<?php echo (int) $row['id']; ?>" title="Descargar"><i class="fas fa-download"></i></a>
                                            <a class="btn btn-sm btn-outline-info me-1" href="subir_reporte_reservas.php?ver_reporte=<?php echo (int) $row['id']; ?>" title="Ver detalle"><i class="fas fa-list"></i></a>
                                            <?php if (($filasInfo ?? 0) === 0): ?>
                                            <form method="post" action="subir_reporte_reservas.php" class="d-inline">
                                                <input type="hidden" name="accion_reporte" value="importar">
                                                <input type="hidden" name="reporte_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="Importar filas del Excel"><i class="fas fa-file-import"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="post" action="subir_reporte_reservas.php" class="d-inline" onsubmit="return confirm('¿Procesar este reporte?');">
                                                <input type="hidden" name="accion_reporte" value="procesar">
                                                <input type="hidden" name="reporte_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success me-1" title="Procesar"><i class="fas fa-cogs"></i></button>
                                            </form>
                                            <?php if ($isAdmin): ?>
                                            <form method="post" action="subir_reporte_reservas.php" class="d-inline" onsubmit="return confirm('¿Eliminar este reporte?');">
                                                <input type="hidden" name="accion_reporte" value="eliminar">
                                                <input type="hidden" name="reporte_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($verReporteId > 0): ?>
                <div class="card mt-4" id="cardDetalleLineas">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between">
                        <h5 class="mb-0">Detalle reporte #<?php echo $verReporteId; ?></h5>
                        <a href="subir_reporte_reservas.php" class="btn-close" aria-label="Cerrar"></a>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="tablaLineasReporte" class="table table-sm table-striped w-100">
                            <thead>
                                <tr>
                                    <th>Fila</th><th>Cliente</th><th>Cédula</th><th>Vehículo</th>
                                    <th>Sol.</th><th>Match</th><th>Estado</th><th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lineasDetalle as $ln):
                                $veh = trim(implode(' ', array_filter([$ln['marca'] ?? '', $ln['modelo'] ?? '', $ln['anio'] ?? ''])));
                                ?>
                                <tr>
                                    <td><?php echo (int) ($ln['fila_excel'] ?? 0); ?></td>
                                    <td><?php echo h($ln['nombre_cliente'] ?? ''); ?></td>
                                    <td><?php echo h($ln['cedula'] ?? ''); ?></td>
                                    <td><?php echo h($veh); ?></td>
                                    <td><?php echo !empty($ln['solicitud_id']) ? '#' . (int) $ln['solicitud_id'] : '—'; ?></td>
                                    <td><?php echo (!empty($ln['match_por']) && $ln['match_por'] !== 'ninguno') ? h($ln['match_por']) : '—'; ?></td>
                                    <td><?php echo badge_estado_linea_php($ln['estado'] ?? ''); ?></td>
                                    <td class="small"><?php echo h($ln['mensaje'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.MOTUS_REPORTE_RESERVAS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
window.MOTUS_USAR_AJAX_REPORTES = false;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="js/subir_reporte_reservas.js?v=<?php echo file_exists(__DIR__ . '/js/subir_reporte_reservas.js') ? filemtime(__DIR__ . '/js/subir_reporte_reservas.js') : time(); ?>"></script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
