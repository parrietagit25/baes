<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';

if (!$isGestor && !$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

$soloLectura = $isGestor && !$isAdmin;

$usuariosBanco = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.nombre, u.apellido, u.email, u.cargo, u.telefono, u.activo,
               b.nombre AS banco_nombre
        FROM usuarios u
        INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
        INNER JOIN roles r ON ur.rol_id = r.id AND r.nombre = 'ROLE_BANCO'
        LEFT JOIN bancos b ON u.banco_id = b.id
        GROUP BY u.id, u.nombre, u.apellido, u.email, u.cargo, u.telefono, u.activo, b.nombre
        ORDER BY u.nombre ASC, u.apellido ASC
    ");
    $usuariosBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('usuarios_banco.php: ' . $e->getMessage());
}

$total = count($usuariosBanco);
$activos = count(array_filter($usuariosBanco, static function ($u) {
    return (int) ($u['activo'] ?? 0) === 1;
}));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios Banco - MOTUS</title>
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
        .stats-card { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .stats-number { font-size: 2.2rem; font-weight: bold; margin-bottom: 8px; }
        .stats-label { font-size: 0.95rem; opacity: 0.95; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="mb-4">
                    <h2 class="mb-1">Usuarios Banco</h2>
                    <p class="text-muted mb-0">Usuarios con rol banco asignados en el sistema.</p>
                </div>

                <div class="alert alert-secondary py-2 small mb-3">
                    <i class="fas fa-eye me-1"></i>
                    <?php if ($soloLectura): ?>
                    Vista de consulta (solo lectura). No puede crear, editar ni eliminar usuarios.
                    <?php else: ?>
                    Vista de consulta. Para administrar usuarios use <a href="usuarios.php">Gestión de Usuarios</a>.
                    <?php endif; ?>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card text-center">
                            <div class="stats-number"><?php echo (int) $total; ?></div>
                            <div class="stats-label">Total usuarios banco</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="stats-number"><?php echo (int) $activos; ?></div>
                            <div class="stats-label">Activos</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="usuariosBancoTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Cargo</th>
                                        <th>Teléfono</th>
                                        <th>Banco</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuariosBanco as $u): ?>
                                    <tr>
                                        <td data-order="<?php echo (int) $u['id']; ?>"><?php echo (int) $u['id']; ?></td>
                                        <td><?php echo htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido'])); ?></td>
                                        <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($u['cargo'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($u['telefono'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($u['banco_nombre'])): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($u['banco_nombre']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) ($u['activo'] ?? 0) === 1): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#usuariosBancoTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        pageLength: 15,
        order: [[0, 'desc']],
        columnDefs: [{ type: 'num', targets: 0 }],
        responsive: true,
        autoWidth: false
    });
});
</script>
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
</body>
</html>
