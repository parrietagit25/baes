<?php
// Conexión a la Base de Datos
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'datos_form';

$registros = [];
$error = null;

try {
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    if ($stmt->fetchColumn() > 0) {
        $pdo->exec("USE `$dbName`");
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'registros'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM registros ORDER BY fecha_registro DESC");
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = "Error de conexión a la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Cédulas</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.html">
                <i class="fas fa-id-card me-2"></i>Validación de Identidad
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="index.html">
                            <i class="fas fa-camera-retro me-1"></i> Capturar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="registros.php">
                            <i class="fas fa-list-alt me-1"></i> Ver Registros
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="row">
            <div class="col-12">
                <h3 class="fw-bold mb-4 text-dark"><i class="fas fa-database me-2 text-primary"></i>Registros Almacenados</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php elseif (empty($registros)): ?>
                    <div class="alert alert-info shadow-sm border-0">
                        <i class="fas fa-info-circle me-2"></i>Aún no hay registros en la base de datos. ¡Ve a la sección "Capturar" para añadir el primero!
                    </div>
                <?php else: ?>
                    <div class="table-responsive bg-white">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Cédula Detectada</th>
                                    <th scope="col" class="text-center">Foto Cédula</th>
                                    <th scope="col" class="text-center">Firma Recortada</th>
                                    <th scope="col" class="text-center">Firma Dibujada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $row): ?>
                                <tr>
                                    <td class="fw-bold text-muted">#<?= htmlspecialchars($row['id']) ?></td>
                                    <td>
                                        <div class="small">
                                            <i class="far fa-calendar-alt me-1 text-primary"></i>
                                            <?= date('d/m/Y H:i', strtotime($row['fecha_registro'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['numero_cedula']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 fs-6">
                                                <?= htmlspecialchars($row['numero_cedula']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">No detectada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['foto_cedula_path'] && file_exists($row['foto_cedula_path'])): ?>
                                            <img src="<?= htmlspecialchars($row['foto_cedula_path']) ?>" class="thumb-img" alt="Cédula" onclick="openImageModal(this.src)">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['firma_extraida_path'] && file_exists($row['firma_extraida_path'])): ?>
                                            <img src="<?= htmlspecialchars($row['firma_extraida_path']) ?>" class="thumb-img" alt="Firma Extraída" onclick="openImageModal(this.src)">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['firma_dibujada_path'] && file_exists($row['firma_dibujada_path'])): ?>
                                            <img src="<?= htmlspecialchars($row['firma_dibujada_path']) ?>" class="thumb-img bg-light" alt="Firma Dibujada" onclick="openImageModal(this.src)">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" class="img-fluid rounded shadow-lg" alt="Zoomed Image">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal Logic for Image Zoom
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        const modalImage = document.getElementById('modalImage');

        function openImageModal(src) {
            modalImage.src = src;
            imageModal.show();
        }
    </script>
</body>
</html>
