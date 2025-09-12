<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';

// Verificar si el usuario es administrador
if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    header('Location: dashboard.php');
    exit();
}

// Obtener lista de roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles - Solicitud de Crédito</title>
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
        .badge-role {
            font-size: 0.85em;
            padding: 6px 10px;
        }
        .role-system {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        }
        .role-custom {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
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
                            <h2 class="mb-1">Gestión de Roles</h2>
                            <p class="text-muted mb-0">Administrar roles y permisos de Solicitud de Crédito</p>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#rolModal" onclick="limpiarFormularioRol()">
                            <i class="fas fa-plus me-2"></i>Nuevo Rol
                        </button>
                    </div>

                    <!-- Tabla de Roles -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="rolesTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre del Rol</th>
                                            <th>Descripción</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Usuarios Asignados</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $rol): ?>
                                        <?php
                                        // Contar usuarios asignados a este rol
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuario_roles WHERE rol_id = ?");
                                        $stmt->execute([$rol['id']]);
                                        $usuarios_asignados = $stmt->fetch()['total'];
                                        
                                        // Determinar si es rol del sistema
                                        $rolesSistema = ['ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_USER'];
                                        $esRolSistema = in_array($rol['nombre'], $rolesSistema);
                                        ?>
                                        <tr>
                                            <td><?php echo $rol['id']; ?></td>
                                            <td>
                                                <span class="badge badge-role <?php echo $esRolSistema ? 'role-system' : 'role-custom'; ?>">
                                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($rol['descripcion'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($esRolSistema): ?>
                                                    <span class="badge bg-primary">Sistema</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Personalizado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rol['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $usuarios_asignados; ?> usuarios</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="editarRol(<?php echo $rol['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!$esRolSistema && $usuarios_asignados == 0): ?>
                                                <button class="btn btn-sm btn-danger btn-action" onclick="eliminarRol(<?php echo $rol['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

    <!-- Modal de Rol -->
    <div class="modal fade" id="rolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rolModalLabel">
                        <i class="fas fa-user-shield me-2"></i>Nuevo Rol
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="rolForm" method="POST" action="api/roles.php">
                    <div class="modal-body">
                        <input type="hidden" id="rol_id" name="id">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Rol *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   placeholder="Ej: ROLE_EDITOR" required>
                            <div class="form-text">Usar formato ROLE_NOMBRE (ej: ROLE_EDITOR, ROLE_REPORTER)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" 
                                      rows="3" placeholder="Descripción del rol y sus permisos"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                <label class="form-check-label" for="activo">
                                    Rol activo
                                </label>
                            </div>
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
    <script src="js/roles.js"></script>
</body>
</html>
