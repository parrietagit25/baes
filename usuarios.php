<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';

// Verificar si el usuario es administrador
if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    header('Location: dashboard.php');
    exit();
}

// Obtener lista de usuarios
$stmt = $pdo->query("
    SELECT u.*, GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles, b.nombre as banco_nombre
    FROM usuarios u
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    LEFT JOIN bancos b ON u.banco_id = b.id
    GROUP BY u.id
    ORDER BY u.fecha_creacion DESC
");
$usuarios = $stmt->fetchAll();

// Obtener roles disponibles
$stmt = $pdo->query("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre");
$roles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Solicitud de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: #3498db;
            color: #fff;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .btn-action {
            margin: 0 2px;
            border-radius: 8px;
        }
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Gestión de Usuarios</h2>
                            <p class="text-muted mb-0">Administrar usuarios de Solicitud de Crédito</p>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#usuarioModal" onclick="limpiarFormulario()">
                            <i class="fas fa-plus me-2"></i>Registrar Usuario
                        </button>
                    </div>

                    <!-- Estadísticas Rápidas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo count($usuarios); ?></div>
                                <div class="stats-label">Total Usuarios</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <div class="stats-number"><?php echo count(array_filter($usuarios, function($u) { return $u['activo'] == 1; })); ?></div>
                                <div class="stats-label">Usuarios Activos</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <div class="stats-number"><?php echo count($roles); ?></div>
                                <div class="stats-label">Roles Disponibles</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <div class="stats-number"><?php echo count(array_filter($usuarios, function($u) { return $u['primer_acceso'] == 1; })); ?></div>
                                <div class="stats-label">Primer Acceso</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Usuarios -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="usuariosTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Cargo</th>
                                            <th>Roles</th>
                                            <th>Banco</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo $usuario['id']; ?></td>
                                            <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['cargo'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($usuario['roles'] ?? 'Sin roles'); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($usuario['banco_nombre']): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($usuario['banco_nombre']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($usuario['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="editarUsuario(<?php echo $usuario['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

    <!-- Modal de Usuario -->
    <div class="modal fade" id="usuarioModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="usuarioModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Registrar Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="usuarioForm" method="POST" action="api/usuarios.php">
                    <div class="modal-body">
                        <input type="hidden" id="usuario_id" name="id">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                                <div class="mb-3">
                                    <label for="pais" class="form-label">País</label>
                                    <input type="text" class="form-control" id="pais" name="pais">
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="apellido" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" required>
                                </div>
                                <div class="mb-3">
                                    <label for="cargo" class="form-label">Cargo</label>
                                    <input type="text" class="form-control" id="cargo" name="cargo">
                                </div>
                                <div class="mb-3">
                                    <label for="id_cobrador" class="form-label">ID Cobrador</label>
                                    <input type="text" class="form-control" id="id_cobrador" name="id_cobrador">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono">
                                </div>
                                <div class="mb-3">
                                    <label for="id_vendedor" class="form-label">ID Vendedor</label>
                                    <input type="text" class="form-control" id="id_vendedor" name="id_vendedor">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Activo</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                        <label class="form-check-label" for="activo">
                                            Usuario activo
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Primer Acceso</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="primer_acceso" name="primer_acceso" value="1" checked>
                                        <label class="form-check-label" for="primer_acceso">
                                            Requiere cambio de contraseña
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="rol_id" class="form-label">Rol *</label>
                            <select class="form-select" id="rol_id" name="rol_id" required>
                                <option value="">Seleccionar rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Select de Banco (solo para usuarios tipo banco) -->
                        <div class="mb-3" id="bancoSection" style="display: none;">
                            <label for="banco_id" class="form-label">Banco Asignado</label>
                            <select class="form-select" id="banco_id" name="banco_id">
                                <option value="">Seleccionar banco...</option>
                                <!-- Se llenará via JavaScript -->
                            </select>
                            <div class="form-text">Solo aplica para usuarios con rol de banco</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/usuarios.js"></script>
</body>
</html>
