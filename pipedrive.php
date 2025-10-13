<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/validar_acceso.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integración Pipedrive - Solicitud de Crédito</title>
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
        .pipedrive-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .lead-card {
            border-left: 4px solid #ff6b6b;
            margin-bottom: 15px;
        }
        .status-connected {
            color: #28a745;
        }
        .status-disconnected {
            color: #dc3545;
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
                            <h2 class="mb-1">Integración Pipedrive</h2>
                            <p class="text-muted mb-0">Sincronización de leads desde Pipedrive</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-info" onclick="probarConexion()">
                                <i class="fas fa-wifi me-2"></i>Probar Conexión
                            </button>
                            <button class="btn btn-success" onclick="sincronizarLeads()">
                                <i class="fas fa-sync me-2"></i>Sincronizar Leads
                            </button>
                        </div>
                    </div>

                    <!-- Estado de Conexión -->
                    <div class="pipedrive-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-2">
                                    <i class="fas fa-plug me-2"></i>Estado de Conexión
                                </h5>
                                <p class="mb-0" id="connectionStatus">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Verificando conexión...
                                </p>
                                <div id="connectionDetails" class="mt-2" style="display: none;">
                                    <small id="connectionMessage"></small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-cog fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Alerta de Suscripción -->
                    <div id="subscriptionAlert" class="alert alert-warning" style="display: none;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="mb-1">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Suscripción Requerida
                                </h6>
                                <p class="mb-0" id="subscriptionMessage"></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-outline-warning btn-sm" onclick="mostrarAlternativas()">
                                    <i class="fas fa-lightbulb me-1"></i>Ver Alternativas
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h4 id="totalLeads">-</h4>
                                    <p class="text-muted mb-0">Leads en Pipedrive</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-download fa-2x text-success mb-2"></i>
                                    <h4 id="leadsImportados">-</h4>
                                    <p class="text-muted mb-0">Leads Importados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                    <h4 id="ultimaSincronizacion">-</h4>
                                    <p class="text-muted mb-0">Última Sincronización</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                                    <h4 id="tasaConversion">-</h4>
                                    <p class="text-muted mb-0">Tasa de Conversión</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas de Importación CSV -->
                    <div class="row mb-4" id="csvStats" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-file-csv me-2"></i>Estadísticas de Importación CSV
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-3">
                                            <h4 class="text-success" id="csvImportados">0</h4>
                                            <p class="text-muted mb-0">Importados Hoy</p>
                                        </div>
                                        <div class="col-md-3">
                                            <h4 class="text-info" id="csvTotal">0</h4>
                                            <p class="text-muted mb-0">Total Importados</p>
                                        </div>
                                        <div class="col-md-3">
                                            <h4 class="text-warning" id="csvErrores">0</h4>
                                            <p class="text-muted mb-0">Errores</p>
                                        </div>
                                        <div class="col-md-3">
                                            <h4 class="text-primary" id="csvUltimaImportacion">-</h4>
                                            <p class="text-muted mb-0">Última Importación</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Leads -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>Leads de Pipedrive
                                </h5>
                                <div id="filtrosInfo" class="text-muted small" style="display: none;">
                                    <i class="fas fa-filter me-1"></i>
                                    <span id="filtrosTexto">Filtros aplicados</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="leadsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Persona</th>
                                            <th>Organización</th>
                                            <th>Valor</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="leadsTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando leads...
                                            </td>
                                        </tr>
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
        $(document).ready(function() {
            probarConexion();
            cargarLeads();
            cargarEstadisticasCSV();
            
            // Configurar DataTable
            $('#leadsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                responsive: true,
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                columnDefs: [
                    { orderable: false, targets: [6] } // Columna de acciones no ordenable
                ]
            });
        });

        function probarConexion() {
            $.ajax({
                url: 'api/pipedrive.php',
                type: 'GET',
                data: { action: 'test' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#connectionStatus').html(`
                            <i class="fas fa-check-circle me-2 status-connected"></i>
                            Conectado - Usuario: ${response.data.usuario}
                        `);
                        $('#connectionDetails').show();
                        $('#connectionMessage').text('Conexión básica exitosa. Probando acceso a leads...');
                        
                        // Probar acceso a leads
                        probarAccesoLeads();
                    } else {
                        $('#connectionStatus').html(`
                            <i class="fas fa-times-circle me-2 status-disconnected"></i>
                            Error: ${response.message}
                        `);
                    }
                },
                error: function() {
                    $('#connectionStatus').html(`
                        <i class="fas fa-times-circle me-2 status-disconnected"></i>
                        Error de conexión
                    `);
                }
            });
        }

        function probarAccesoLeads() {
            $.ajax({
                url: 'api/pipedrive.php',
                type: 'GET',
                data: { action: 'leads' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#connectionMessage').text('✅ Acceso completo a leads disponible');
                        $('#subscriptionAlert').hide();
                    } else if (response.error_code === 'PAYMENT_REQUIRED') {
                        $('#connectionMessage').text('⚠️ Conexión básica OK, pero tu plan de Pipedrive no incluye acceso a la API de leads');
                        $('#subscriptionMessage').text('Tu plan actual de Pipedrive no incluye acceso a la API de leads y personas. Usa la importación CSV como alternativa.');
                        $('#subscriptionAlert').show();
                    } else {
                        $('#connectionMessage').text('❌ Error al acceder a leads: ' + response.message);
                    }
                },
                error: function() {
                    $('#connectionMessage').text('❌ Error de conexión al probar leads');
                }
            });
        }

        function cargarLeads() {
            $.ajax({
                url: 'api/pipedrive.php',
                type: 'GET',
                data: { action: 'leads' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        mostrarLeads(response.data);
                        $('#totalLeads').text(response.data.length);
                        $('#leadsImportados').text('-');
                        $('#ultimaSincronizacion').text('Ahora');
                        $('#tasaConversion').text('-');
                        
                        // Mostrar información de filtros si está disponible
                        if (response.filtros_aplicados) {
                            const filtros = response.filtros_aplicados;
                            let textoFiltros = `Solo activos: ${filtros.total_encontrados}/${filtros.total_original}`;
                            if (filtros.ordenado_por_fecha) {
                                textoFiltros += ` | Ordenado por fecha`;
                            }
                            $('#filtrosTexto').text(textoFiltros);
                            $('#filtrosInfo').show();
                        }
                    } else if (response.error_code === 'PAYMENT_REQUIRED') {
                        mostrarMensajeSuscripcion();
                    } else {
                        $('#leadsTableBody').html(`
                            <tr>
                                <td colspan="7" class="text-center text-danger">
                                    Error al cargar leads: ${response.message}
                                </td>
                            </tr>
                        `);
                    }
                },
                error: function() {
                    $('#leadsTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center text-danger">
                                Error de conexión al cargar leads
                            </td>
                        </tr>
                    `);
                }
            });
        }

        function mostrarMensajeSuscripcion() {
            $('#leadsTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="text-warning">Suscripción Requerida</h5>
                            <p class="text-muted mb-3">
                                Se requiere una suscripción de pago en Pipedrive para acceder a la API de leads.
                            </p>
                            <div class="btn-group">
                                <button class="btn btn-warning" onclick="mostrarAlternativas()">
                                    <i class="fas fa-lightbulb me-2"></i>Ver Alternativas
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            `);
            
            // Actualizar estadísticas
            $('#totalLeads').text('N/A');
            $('#leadsImportados').text('N/A');
            $('#ultimaSincronizacion').text('N/A');
            $('#tasaConversion').text('N/A');
            
            // Mostrar estadísticas CSV
            $('#csvStats').show();
        }

        function cargarEstadisticasCSV() {
            $.ajax({
                url: 'api/estadisticas_csv.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#csvImportados').text(response.data.hoy || 0);
                        $('#csvTotal').text(response.data.total || 0);
                        $('#csvErrores').text(response.data.errores || 0);
                        $('#csvUltimaImportacion').text(response.data.ultima || '-');
                        
                        // Mostrar estadísticas si hay datos
                        if (response.data.total > 0) {
                            $('#csvStats').show();
                        }
                    }
                },
                error: function() {
                    // Silenciar error, no es crítico
                }
            });
        }

        function mostrarLeads(leads) {
            const tbody = $('#leadsTableBody');
            tbody.empty();
            
            if (leads.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No hay leads disponibles
                        </td>
                    </tr>
                `);
                return;
            }
            
            leads.forEach(function(lead) {
                // Los leads tienen una estructura diferente según la API oficial
                const titulo = lead.title || 'Sin título';
                const valor = lead.value ? `${lead.value.amount} ${lead.value.currency}` : '-';
                const fechaEsperada = lead.expected_close_date || '-';
                const estado = lead.is_archived ? 'Archivado' : 'Activo';
                const estadoClass = lead.is_archived ? 'bg-secondary' : 'bg-success';
                
                const row = `
                    <tr>
                        <td>${lead.id}</td>
                        <td>${titulo}</td>
                        <td>${lead.person_id ? 'Persona ID: ' + lead.person_id : '-'}</td>
                        <td>${lead.organization_id ? 'Org ID: ' + lead.organization_id : '-'}</td>
                        <td>${valor}</td>
                        <td>
                            <span class="badge ${estadoClass}">${estado}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success btn-action" onclick="importarLead('${lead.id}')" title="Importar Lead">
                                <i class="fas fa-download"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function importarLead(leadId) {
            if (confirm('¿Está seguro de que desea importar este lead como solicitud de crédito?')) {
                $.ajax({
                    url: 'api/pipedrive.php',
                    type: 'POST',
                    data: { 
                        action: 'import_lead',
                        lead_id: leadId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            mostrarAlerta('Lead importado correctamente', 'success');
                            // Recargar la tabla
                            cargarLeads();
                        } else {
                            mostrarAlerta('Error al importar lead: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        mostrarAlerta('Error de conexión al importar lead', 'danger');
                    }
                });
            }
        }

        function sincronizarLeads() {
            if (confirm('¿Está seguro de que desea sincronizar todos los leads disponibles? Esto puede tomar varios minutos.')) {
                mostrarAlerta('Iniciando sincronización...', 'info');
                
                $.ajax({
                    url: 'api/pipedrive.php',
                    type: 'GET',
                    data: { action: 'sync' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            let mensaje = `Sincronización completada - Solo leads recientes y activos:\n`;
                            mensaje += `✅ Importados: ${data.importados}\n`;
                            mensaje += `⏭️ Saltados: ${data.saltados}\n`;
                            mensaje += `📊 Total procesados: ${data.total_procesados}`;
                            
                            if (data.errores && data.errores.length > 0) {
                                mensaje += `\n⚠️ Errores: ${data.errores.length}`;
                            }
                            
                            mostrarAlerta(mensaje, 'success');
                            cargarLeads();
                        } else {
                            mostrarAlerta('Error en sincronización: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        mostrarAlerta('Error de conexión durante la sincronización', 'danger');
                    }
                });
            }
        }

        function mostrarAlerta(mensaje, tipo) {
            const alerta = `
                <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${mensaje}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('.main-content .container-fluid').prepend(alerta);
            
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        }

        function mostrarAlternativas() {
            const alternativas = `
                <div class="modal fade" id="alternativasModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-lightbulb me-2"></i>Alternativas para Pipedrive
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-upload me-2 text-primary"></i>Importación Manual
                                                </h6>
                                                <p class="card-text">
                                                    Puedes crear solicitudes de crédito manualmente desde la página de solicitudes.
                                                </p>
                                                <a href="solicitudes.php" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-plus me-1"></i>Crear Solicitud
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-file-excel me-2 text-success"></i>Importación CSV
                                                </h6>
                                                <p class="card-text">
                                                    Exporta leads desde Pipedrive en CSV e impórtalos al sistema.
                                                </p>
                                                <button class="btn btn-success btn-sm" onclick="mostrarFormatoCSV()">
                                                    <i class="fas fa-info-circle me-1"></i>Ver Formato
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h6><i class="fas fa-cog me-2"></i>Configuración de Pipedrive</h6>
                                    <div class="alert alert-info">
                                        <strong>Para habilitar la API de leads:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li><strong>Actualiza tu plan de Pipedrive</strong> a uno que incluya acceso a la API</li>
                                            <li>Los planes que incluyen API: <strong>Professional, Premium, Ultimate</strong></li>
                                            <li>El plan <strong>Light/Growth</strong> no incluye acceso a la API de leads</li>
                                            <li>Una vez actualizado, la integración funcionará automáticamente</li>
                                        </ol>
                                        <div class="mt-3">
                                            <strong>Alternativa inmediata:</strong> Usa la importación CSV para trabajar con tus leads actuales.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(alternativas);
            $('#alternativasModal').modal('show');
            
            // Limpiar modal al cerrar
            $('#alternativasModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        function mostrarFormatoCSV() {
            const formato = `
                <div class="modal fade" id="formatoCSVModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-file-csv me-2"></i>Formato CSV para Importación
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Para importar leads desde Pipedrive, exporta los datos en CSV con las siguientes columnas:</p>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Columna</th>
                                                <th>Descripción</th>
                                                <th>Obligatorio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>nombre_cliente</code></td>
                                                <td>Nombre completo del cliente</td>
                                                <td><span class="badge bg-danger">Sí</span></td>
                                            </tr>
                                            <tr>
                                                <td><code>email</code></td>
                                                <td>Correo electrónico</td>
                                                <td><span class="badge bg-danger">Sí</span></td>
                                            </tr>
                                            <tr>
                                                <td><code>telefono</code></td>
                                                <td>Número de teléfono</td>
                                                <td><span class="badge bg-warning">No</span></td>
                                            </tr>
                                            <tr>
                                                <td><code>cedula</code></td>
                                                <td>Número de cédula</td>
                                                <td><span class="badge bg-danger">Sí</span></td>
                                            </tr>
                                            <tr>
                                                <td><code>direccion</code></td>
                                                <td>Dirección completa</td>
                                                <td><span class="badge bg-warning">No</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <strong>Nota:</strong> Una vez que tengas el archivo CSV, contacta al administrador del sistema para configurar la importación automática.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(formato);
            $('#formatoCSVModal').modal('show');
            
            $('#formatoCSVModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }
    </script>
</body>
</html>
