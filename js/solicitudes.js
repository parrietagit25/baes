// Inicializar DataTable para solicitudes
$(document).ready(function() {
    cargarSolicitudes();

    // Configurar formulario de solicitudes
    $('#solicitudForm').on('submit', function(e) {
        e.preventDefault();
        guardarSolicitud();
    });
    
    // Configurar formulario de notas
    $('#notaForm').on('submit', function(e) {
        e.preventDefault();
        enviarNota();
    });
    
    // Contador de caracteres para comentarios
    $('#comentarios_gestor').on('input', function() {
        const maxLength = 1000;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#contador_comentarios').text(remaining);
        
        if (remaining < 0) {
            $('#contador_comentarios').addClass('text-danger');
        } else {
            $('#contador_comentarios').removeClass('text-danger');
        }
    });
});

// Función para cargar solicitudes
function cargarSolicitudes() {
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de la API:', response);
            if (response.success) {
                const tbody = $('#solicitudesTable tbody');
                tbody.empty();
                
                console.log('Datos recibidos:', response.data);
                response.data.forEach(function(solicitud) {
                    const estadoClass = getEstadoClass(solicitud.estado);
                    const respuestaClass = getRespuestaClass(solicitud.respuesta_banco);
                    
                    const row = `
                        <tr>
                            <td>${solicitud.id}</td>
                            <td>${solicitud.nombre_cliente}</td>
                            <td>${solicitud.cedula}</td>
                            <td>${solicitud.marca_auto || '-'} ${solicitud.modelo_auto || ''} ${solicitud.año_auto || ''}</td>
                            <td>${solicitud.gestor_nombre} ${solicitud.gestor_apellido}</td>
                            <td><span class="badge badge-estado ${estadoClass}">${solicitud.estado}</span></td>
                            <td><span class="badge badge-estado ${respuestaClass}">${solicitud.respuesta_banco}</span></td>
                            <td>${formatearFecha(solicitud.fecha_creacion)}</td>
                            <td>
                                <button class="btn btn-sm btn-info btn-action" onclick="verDetalles(${solicitud.id})" title="Ver Detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary btn-action" onclick="editarSolicitud(${solicitud.id})" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success btn-action" onclick="verMuro(${solicitud.id})" title="Ver Muro">
                                    <i class="fas fa-comments"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
                
                // Inicializar DataTable si no existe
                if (!$.fn.DataTable.isDataTable('#solicitudesTable')) {
                    $('#solicitudesTable').DataTable({
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                        },
                        responsive: true,
                        order: [[0, 'desc']],
                        pageLength: 10,
                        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                        columnDefs: [
                            { orderable: false, targets: [8] }
                        ]
                    });
                }
            }
        },
        error: function() {
            mostrarAlerta('Error al cargar solicitudes', 'danger');
        }
    });
}

// Función para obtener clase CSS del estado
function getEstadoClass(estado) {
    const clases = {
        'Nueva': 'estado-nueva',
        'En Revisión Banco': 'estado-revision',
        'Aprobada': 'estado-aprobada',
        'Rechazada': 'estado-rechazada',
        'Completada': 'estado-completada'
    };
    return clases[estado] || 'estado-nueva';
}

// Función para obtener clase CSS de la respuesta del banco
function getRespuestaClass(respuesta) {
    const clases = {
        'Pendiente': 'estado-revision',
        'Aprobado': 'estado-aprobada',
        'Pre Aprobado': 'estado-revision',
        'Rechazado': 'estado-rechazada'
    };
    return clases[respuesta] || 'estado-revision';
}

// Función para formatear fecha
function formatearFecha(fecha) {
    const date = new Date(fecha);
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
}

// Función para limpiar formulario
function limpiarFormularioSolicitud() {
    $('#solicitud_id').val('');
    $('#solicitudForm')[0].reset();
    $('#solicitudModalLabel').html('<i class="fas fa-file-alt me-2"></i>Nueva Solicitud de Crédito');
    $('#contador_comentarios').text('1000');
    
    // Activar primera pestaña
    $('#datos-generales-tab').tab('show');
}

// Función para guardar solicitud
function guardarSolicitud() {
    const formData = new FormData($('#solicitudForm')[0]);
    const solicitudId = $('#solicitud_id').val();
    
    // Determinar si es creación o actualización
    const method = solicitudId ? 'PUT' : 'POST';
    
    $.ajax({
        url: 'api/solicitudes.php',
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const mensaje = solicitudId ? 'Solicitud actualizada correctamente' : 'Solicitud creada correctamente';
                mostrarAlerta(mensaje, 'success');
                $('#solicitudModal').modal('hide');
                cargarSolicitudes();
            } else {
                mostrarAlerta('Error al guardar solicitud: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al guardar solicitud', 'danger');
        }
    });
}

// Función para ver detalles
function verDetalles(id) {
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarDetalles(response.data);
            } else {
                mostrarAlerta('Error al cargar detalles: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar detalles', 'danger');
        }
    });
}

// Función para mostrar detalles en modal
function mostrarDetalles(solicitud) {
    const contenido = `
        <div class="row">
            <div class="col-md-6">
                <h6>Datos del Cliente</h6>
                <p><strong>Nombre:</strong> ${solicitud.nombre_cliente}</p>
                <p><strong>Cédula:</strong> ${solicitud.cedula}</p>
                <p><strong>Teléfono:</strong> ${solicitud.telefono || '-'}</p>
                <p><strong>Email:</strong> ${solicitud.email || '-'}</p>
                <p><strong>Dirección:</strong> ${solicitud.direccion || '-'}</p>
            </div>
            <div class="col-md-6">
                <h6>Datos del Vehículo</h6>
                <p><strong>Marca:</strong> ${solicitud.marca_auto || '-'}</p>
                <p><strong>Modelo:</strong> ${solicitud.modelo_auto || '-'}</p>
                <p><strong>Año:</strong> ${solicitud.año_auto || '-'}</p>
                <p><strong>Precio:</strong> $${solicitud.precio_especial || '-'}</p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Comentarios del Gestor</h6>
                <p>${solicitud.comentarios_gestor || 'Sin comentarios'}</p>
            </div>
        </div>
    `;
    
    $('#detallesContent').html(contenido);
    $('#detallesModal').modal('show');
}

// Función para editar solicitud
function editarSolicitud(id) {
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                llenarFormularioEdicion(response.data);
            } else {
                mostrarAlerta('Error al cargar solicitud: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar solicitud', 'danger');
        }
    });
}

// Función para llenar formulario de edición
function llenarFormularioEdicion(solicitud) {
    // Llenar campos básicos
    $('#solicitud_id').val(solicitud.id);
    $('#tipo_persona').val(solicitud.tipo_persona);
    $('#nombre_cliente').val(solicitud.nombre_cliente);
    $('#cedula').val(solicitud.cedula);
    $('#edad').val(solicitud.edad);
    $('#genero').val(solicitud.genero);
    $('#telefono').val(solicitud.telefono);
    $('#email').val(solicitud.email);
    $('#casado').prop('checked', solicitud.casado == 1);
    $('#hijos').val(solicitud.hijos);
    
    // Dirección
    $('#provincia').val(solicitud.provincia);
    $('#distrito').val(solicitud.distrito);
    $('#corregimiento').val(solicitud.corregimiento);
    $('#barriada').val(solicitud.barriada);
    $('#casa_edif').val(solicitud.casa_edif);
    $('#numero_casa_apto').val(solicitud.numero_casa_apto);
    $('#direccion').val(solicitud.direccion);
    
    // Perfil financiero
    $('#perfil_financiero').val(solicitud.perfil_financiero);
    $('#ingreso').val(solicitud.ingreso);
    $('#tiempo_laborar').val(solicitud.tiempo_laborar);
    $('#nombre_empresa_negocio').val(solicitud.nombre_empresa_negocio);
    $('#estabilidad_laboral').val(solicitud.estabilidad_laboral);
    $('#fecha_constitucion').val(solicitud.fecha_constitucion);
    
    // Datos del auto
    $('#marca_auto').val(solicitud.marca_auto);
    $('#modelo_auto').val(solicitud.modelo_auto);
    $('#año_auto').val(solicitud.año_auto);
    $('#kilometraje').val(solicitud.kilometraje);
    $('#precio_especial').val(solicitud.precio_especial);
    $('#abono_porcentaje').val(solicitud.abono_porcentaje);
    $('#abono_monto').val(solicitud.abono_monto);
    
    // Comentarios
    $('#comentarios_gestor').val(solicitud.comentarios_gestor);
    $('#contador_comentarios').text(1000 - (solicitud.comentarios_gestor || '').length);
    
    // Cambiar título del modal
    $('#solicitudModalLabel').html('<i class="fas fa-edit me-2"></i>Editar Solicitud de Crédito');
    
    // Mostrar modal
    $('#solicitudModal').modal('show');
}

// Función para ver muro de tiempo
function verMuro(id) {
    // Cargar información de la solicitud
    cargarInfoSolicitud(id);
    
    // Configurar formulario de notas
    $('#nota_solicitud_id').val(id);
    
    // Cargar notas existentes
    cargarNotasMuro(id);
    
    // Mostrar modal
    $('#muroModal').modal('show');
}

// Función para cargar información de la solicitud en el muro
function cargarInfoSolicitud(id) {
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const solicitud = response.data;
                const infoHtml = `
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Cliente</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Nombre:</strong> ${solicitud.nombre_cliente}</p>
                                <p class="mb-1"><strong>Cédula:</strong> ${solicitud.cedula}</p>
                                <p class="mb-0"><strong>Teléfono:</strong> ${solicitud.telefono || '-'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-car me-2"></i>Vehículo</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Marca:</strong> ${solicitud.marca_auto || '-'}</p>
                                <p class="mb-1"><strong>Modelo:</strong> ${solicitud.modelo_auto || '-'}</p>
                                <p class="mb-0"><strong>Año:</strong> ${solicitud.año_auto || '-'}</p>
                            </div>
                        </div>
                    </div>
                `;
                $('#muroSolicitudInfo').html(infoHtml);
            }
        },
        error: function() {
            $('#muroSolicitudInfo').html('<div class="alert alert-danger">Error al cargar información de la solicitud</div>');
        }
    });
}

// Función para cargar notas del muro
function cargarNotasMuro(solicitudId) {
    $.ajax({
        url: 'api/notas.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarNotasMuro(response.data);
            } else {
                $('#muroNotasContainer').html('<div class="alert alert-danger">Error al cargar notas: ' + response.message + '</div>');
            }
        },
        error: function() {
            $('#muroNotasContainer').html('<div class="alert alert-danger">Error de conexión al cargar notas</div>');
        }
    });
}

// Función para mostrar notas en el muro
function mostrarNotasMuro(notas) {
    const container = $('#muroNotasContainer');
    
    if (notas.length === 0) {
        container.html('<div class="alert alert-info text-center">No hay notas para esta solicitud</div>');
        return;
    }
    
    let html = '';
    notas.forEach(function(nota) {
        const tipoClass = getTipoNotaClass(nota.tipo_nota);
        const fecha = new Date(nota.fecha_creacion).toLocaleString('es-ES');
        
        html += `
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${nota.nombre} ${nota.apellido}</strong>
                        <span class="badge ${tipoClass} ms-2">${nota.tipo_nota}</span>
                    </div>
                    <small class="text-muted">${fecha}</small>
                </div>
                <div class="card-body">
                    ${nota.titulo ? `<h6 class="card-title">${nota.titulo}</h6>` : ''}
                    <p class="card-text">${nota.contenido}</p>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

// Función para obtener clase CSS del tipo de nota
function getTipoNotaClass(tipo) {
    const clases = {
        'Comentario': 'bg-primary',
        'Actualización': 'bg-info',
        'Documento': 'bg-success',
        'Respuesta Banco': 'bg-warning',
        'Respuesta Cliente': 'bg-danger'
    };
    return clases[tipo] || 'bg-secondary';
}

// Función para enviar nota
function enviarNota() {
    const formData = new FormData($('#notaForm')[0]);
    
    $.ajax({
        url: 'api/notas.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Nota enviada correctamente', 'success');
                $('#notaForm')[0].reset();
                $('#nota_solicitud_id').val($('#nota_solicitud_id').val()); // Mantener el ID
                
                // Recargar notas
                const solicitudId = $('#nota_solicitud_id').val();
                cargarNotasMuro(solicitudId);
            } else {
                mostrarAlerta('Error al enviar nota: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al enviar nota', 'danger');
        }
    });
}

// Función para mostrar alertas
function mostrarAlerta(mensaje, tipo) {
    const alerta = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insertar alerta al inicio del contenido principal
    $('.main-content .container-fluid').prepend(alerta);
    
    // Auto-ocultar después de 5 segundos
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
