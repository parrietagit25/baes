<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';

// Verificar que el usuario tenga permisos
$userRoles = $_SESSION['user_roles'];
$puedeImportar = in_array('ROLE_GESTOR', $userRoles) || 
                 in_array('ROLE_ADMIN', $userRoles);

if (!$puedeImportar) {
    header('Location: dashboard.php');
    exit();
}

$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    try {
        $archivo = $_FILES['archivo_csv'];
        
        // Verificar que se subió un archivo
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }
        
        // Verificar que es un archivo CSV
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('El archivo debe ser un CSV');
        }
        
        // Leer el archivo CSV
        $handle = fopen($archivo['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('No se pudo abrir el archivo');
        }
        
        $importados = 0;
        $errores = [];
        $fila = 0;
        
        // Leer la primera fila (encabezados)
        $encabezados = fgetcsv($handle);
        if (!$encabezados) {
            throw new Exception('El archivo CSV está vacío');
        }
        
        // Mapear encabezados a columnas de la base de datos
        $mapeo = [
            'nombre_cliente' => 'nombre_cliente',
            'email' => 'email',
            'telefono' => 'telefono',
            'cedula' => 'cedula',
            'direccion' => 'direccion',
            'empresa' => 'nombre_empresa_negocio'
        ];
        
        $indices = [];
        foreach ($mapeo as $campo => $columna) {
            $indice = array_search($campo, $encabezados);
            if ($indice !== false) {
                $indices[$campo] = $indice;
            }
        }
        
        // Verificar campos obligatorios
        if (!isset($indices['nombre_cliente']) || !isset($indices['email'])) {
            throw new Exception('El archivo debe contener las columnas: nombre_cliente, email');
        }
        
        // Procesar cada fila
        while (($datos = fgetcsv($handle)) !== false) {
            $fila++;
            
            try {
                // Extraer datos según los índices
                $nombre = trim($datos[$indices['nombre_cliente']] ?? '');
                $email = trim($datos[$indices['email']] ?? '');
                $telefono = isset($indices['telefono']) ? trim($datos[$indices['telefono']] ?? '') : '';
                $cedula = isset($indices['cedula']) ? trim($datos[$indices['cedula']] ?? '') : 'CSV-' . $fila;
                $direccion = isset($indices['direccion']) ? trim($datos[$indices['direccion']] ?? '') : '';
                $empresa = isset($indices['empresa']) ? trim($datos[$indices['empresa']] ?? '') : '';
                
                // Validar datos obligatorios
                if (empty($nombre) || empty($email)) {
                    $errores[] = "Fila $fila: Faltan datos obligatorios (nombre o email)";
                    continue;
                }
                
                // Verificar si ya existe (por email)
                $stmt = $pdo->prepare("SELECT id FROM solicitudes_credito WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $errores[] = "Fila $fila: Ya existe una solicitud con el email $email";
                    continue;
                }
                
                // Insertar solicitud
                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_credito (
                        gestor_id, tipo_persona, nombre_cliente, cedula, telefono, email,
                        direccion, nombre_empresa_negocio, comentarios_gestor, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $comentarios = "Solicitud importada desde CSV (fila $fila)";
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    'Natural',
                    $nombre,
                    $cedula,
                    $telefono,
                    $email,
                    $direccion,
                    $empresa,
                    $comentarios,
                    'Nueva'
                ]);
                
                $solicitudId = $pdo->lastInsertId();
                
                // Crear nota de importación
                $stmt = $pdo->prepare("
                    INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                    VALUES (?, ?, 'Actualización', 'Importación CSV', ?)
                ");
                $stmt->execute([$solicitudId, $_SESSION['user_id'], $comentarios]);
                
                $importados++;
                
            } catch (Exception $e) {
                $errores[] = "Fila $fila: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        $mensaje = "Importación completada. $importados solicitudes importadas correctamente.";
        if (!empty($errores)) {
            $mensaje .= " Errores: " . count($errores);
        }
        $tipoMensaje = 'success';
        
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipoMensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar CSV - Solicitud de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
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
                            <h2 class="mb-1">Importar Leads desde CSV</h2>
                            <p class="text-muted mb-0">Importa leads desde un archivo CSV exportado de Pipedrive</p>
                        </div>
                        <div>
                            <a href="pipedrive.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Pipedrive
                            </a>
                        </div>
                    </div>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $tipoMensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-upload me-2"></i>Subir Archivo CSV
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                        <div class="upload-area" id="uploadArea">
                                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                            <h5>Arrastra tu archivo CSV aquí</h5>
                                            <p class="text-muted">o haz clic para seleccionar</p>
                                            <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" class="d-none" required>
                                            <button type="button" class="btn btn-primary" onclick="document.getElementById('archivo_csv').click()">
                                                <i class="fas fa-folder-open me-2"></i>Seleccionar Archivo
                                            </button>
                                        </div>
                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-success" id="btnImportar" disabled>
                                                <i class="fas fa-download me-2"></i>Importar Leads
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Formato Requerido
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="small">El archivo CSV debe contener las siguientes columnas:</p>
                                    <ul class="list-unstyled small">
                                        <li><strong class="text-danger">nombre_cliente</strong> - Nombre completo</li>
                                        <li><strong class="text-danger">email</strong> - Correo electrónico</li>
                                        <li><strong class="text-warning">telefono</strong> - Teléfono (opcional)</li>
                                        <li><strong class="text-warning">cedula</strong> - Cédula (opcional)</li>
                                        <li><strong class="text-warning">direccion</strong> - Dirección (opcional)</li>
                                        <li><strong class="text-warning">empresa</strong> - Empresa (opcional)</li>
                                    </ul>
                                    <div class="alert alert-info small">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Tip:</strong> Las columnas marcadas en rojo son obligatorias.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-download me-2"></i>Plantilla CSV
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="small">Descarga una plantilla para crear tu archivo CSV:</p>
                                    <a href="descargar_plantilla.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download me-1"></i>Descargar Plantilla
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('archivo_csv');
        const btnImportar = document.getElementById('btnImportar');

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                actualizarInterfaz();
            }
        });

        // Click to select file
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', actualizarInterfaz);

        function actualizarInterfaz() {
            const file = fileInput.files[0];
            if (file) {
                uploadArea.innerHTML = `
                    <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                    <h5>${file.name}</h5>
                    <p class="text-muted">Tamaño: ${(file.size / 1024).toFixed(1)} KB</p>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarArchivo()">
                        <i class="fas fa-edit me-2"></i>Cambiar Archivo
                    </button>
                `;
                btnImportar.disabled = false;
            }
        }

        function cambiarArchivo() {
            fileInput.value = '';
            uploadArea.innerHTML = `
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                <h5>Arrastra tu archivo CSV aquí</h5>
                <p class="text-muted">o haz clic para seleccionar</p>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('archivo_csv').click()">
                    <i class="fas fa-folder-open me-2"></i>Seleccionar Archivo
                </button>
            `;
            btnImportar.disabled = true;
        }
    </script>
</body>
</html>
