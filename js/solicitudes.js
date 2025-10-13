// Inicializar DataTable para solicitudes
$(document).ready(function() {
    console.log('=== INICIALIZANDO SOLICITUDES ===');
    
    // Deshabilitar campos si el usuario es banco
    if (window.userRoles && window.userRoles.isBanco) {
        deshabilitarCamposParaBanco();
    }
    
    cargarSolicitudes();

    // Configurar formulario de solicitudes
    $('#solicitudForm').on('submit', function(e) {
        console.log('=== FORMULARIO ENVIADO ===');
        e.preventDefault();
        guardarSolicitud();
    });
    
    // Configurar formulario de notas
    $('#notaForm').on('submit', function(e) {
        e.preventDefault();
        enviarNota();
    });
    
    // El formulario de adjuntos ahora usa onclick en el botón
    
    // Configurar funcionalidad de usuarios banco
    configurarUsuariosBanco();
    
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
                let filasGeneradas = 0;
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
                            <td>
                                ${solicitud.banco_nombre ? 
                                    `<span class="badge bg-info">
                                        ${solicitud.banco_nombre} ${solicitud.banco_apellido}
                                        ${solicitud.banco_institucion ? `<br><small>(${solicitud.banco_institucion})</small>` : ''}
                                    </span>` : 
                                    '<span class="text-muted">Sin asignar</span>'
                                }
                            </td>
                            <td><span class="badge badge-estado ${estadoClass}">${solicitud.estado}</span></td>
                            <!-- <td><span class="badge badge-estado ${respuestaClass}">${solicitud.respuesta_banco}</span></td> -->
                            <td>${formatearFecha(solicitud.fecha_creacion)}</td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <button class="btn btn-info btn-action" onclick="verDetalles(${solicitud.id})" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-primary btn-action" onclick="editarSolicitud(${solicitud.id})" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <button class="btn btn-success btn-action" onclick="verMuro(${solicitud.id})" title="Ver Muro">
                                            <i class="fas fa-comments"></i>
                                        </button>
                                        <button class="btn btn-warning btn-action" onclick="abrirModalAdjuntosDesdeTabla(${solicitud.id})" title="Gestionar Adjuntos">
                                            <i class="fas fa-paperclip"></i>
                                        </button>
                                    </div>
                                    ${(window.userRoles && window.userRoles.isBanco && solicitud.estado === 'En Revisión Banco') ? `
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <button class="btn btn-success btn-action" onclick="abrirModalAprobacion(${solicitud.id})" title="Aprobar/Rechazar Solicitud">
                                            <i class="fas fa-gavel"></i>
                                        </button>
                                    </div>
                                    ` : ''}
                                    ${(window.userRoles && window.userRoles.isAdmin) ? `
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <button class="btn btn-warning btn-action" onclick="abrirModalCambioEstado(${solicitud.id})" title="Cambiar Estado (Solo Admin)">
                                            <i class="fas fa-cogs"></i>
                                        </button>
                                    </div>
                                    ` : ''}
                                    ${(window.userRoles && window.userRoles.isAdmin) ? `
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-danger btn-action" onclick="eliminarSolicitud(${solicitud.id})" title="Eliminar Solicitud (Solo Admin)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                    filasGeneradas++;
                });
                
                console.log('Total de filas generadas:', filasGeneradas);
                console.log('Filas en tbody:', tbody.find('tr').length);
                
                // Verificar que las filas tengan el número correcto de columnas
                const primeraFila = tbody.find('tr:first');
                if (primeraFila.length > 0) {
                    const columnas = primeraFila.find('td').length;
                    console.log('Número de columnas en la primera fila:', columnas);
                    console.log('Número de columnas en el header:', $('#solicitudesTable thead th').length);
                    
                    if (columnas !== $('#solicitudesTable thead th').length) {
                        console.error('❌ ERROR: Desajuste en el número de columnas');
                        console.error('Header:', $('#solicitudesTable thead th').length, 'Body:', columnas);
                    } else {
                        console.log('✅ Columnas coinciden correctamente');
                    }
                }
                
                // Función para inicializar DataTable de forma segura
                function inicializarDataTable() {
                    try {
                        // Destruir DataTable existente si existe
                        if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                            console.log('Destruyendo DataTable existente...');
                            $('#solicitudesTable').DataTable().destroy();
                        }
                        
                        // Verificar que las filas estén completamente renderizadas
                        const filas = $('#solicitudesTable tbody tr');
                        console.log('Filas encontradas:', filas.length);
                        
                        if (filas.length === 0) {
                            console.warn('No hay filas para mostrar, reintentando en 100ms...');
                            setTimeout(inicializarDataTable, 100);
                            return;
                        }
                        
                        // Verificar que la primera fila tenga el número correcto de columnas
                        const primeraFila = filas.first();
                        const columnas = primeraFila.find('td').length;
                        const columnasHeader = $('#solicitudesTable thead th').length;
                        
                        console.log('Columnas en fila:', columnas, 'Columnas en header:', columnasHeader);
                        
                        if (columnas !== columnasHeader) {
                            console.error('❌ Desajuste de columnas detectado, reintentando...');
                            setTimeout(inicializarDataTable, 200);
                            return;
                        }
                        
                        console.log('Inicializando DataTable de solicitudes...');
                        
                        $('#solicitudesTable').DataTable({
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                            },
                            responsive: true,
                            order: [[0, 'desc']],
                            pageLength: 10,
                            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                            columnDefs: [
                                { orderable: false, targets: [8] } // Columna de acciones no ordenable (actualizado: era [9] cuando había columna "Respuesta Banco")
                            ],
                            autoWidth: false,
                            processing: true,
                            deferRender: true,
                            destroy: true
                        });
                        
                        console.log('✅ DataTable de solicitudes inicializado correctamente');
                        
                    } catch (error) {
                        console.error('❌ Error al inicializar DataTable de solicitudes:', error);
                        console.error('Detalles del error:', error.message);
                        console.error('Stack trace:', error.stack);
                    }
                }
                
                // Inicializar DataTable después de un delay
                setTimeout(inicializarDataTable, 300);
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
    if (!fecha || fecha === '0000-00-00' || fecha === '0000-00-00 00:00:00') {
        return '-';
    }
    const date = new Date(fecha);
    if (isNaN(date.getTime())) {
        return '-';
    }
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
}

// Función para limpiar formulario
function limpiarFormularioSolicitud() {
    $('#solicitud_id').val('');
    $('#solicitudForm')[0].reset();
    $('#solicitudModalLabel').html('<i class="fas fa-file-alt me-2"></i>Nueva Solicitud de Crédito');
    $('#contador_comentarios').text('1000');
    
    // Resetear botones
    $('#btnCerrarDespuesAdjuntos').hide();
    $('button[type="submit"]').show();
    
    // Activar primera pestaña
    $('#datos-generales-tab').tab('show');
}

// Función para abrir modal de adjuntos
function abrirModalAdjuntos() {
    const solicitudId = $('#solicitud_id').val();
    
    if (!solicitudId) {
        mostrarAlerta('Debe guardar la solicitud antes de gestionar adjuntos', 'warning');
        return;
    }
    
    // Configurar el ID de solicitud para adjuntos
    $('#adjunto_solicitud_id').val(solicitudId);
    
    // Cargar información de la solicitud
    cargarInfoSolicitudAdjuntos(solicitudId);
    
    // Cargar adjuntos existentes
    cargarAdjuntos(solicitudId);
    
    // Mostrar modal
    $('#adjuntosModal').modal('show');
}

// Función para abrir modal de adjuntos desde la tabla
function abrirModalAdjuntosDesdeTabla(solicitudId) {
    // Configurar el ID de solicitud para adjuntos
    $('#adjunto_solicitud_id').val(solicitudId);
    
    // Cargar información de la solicitud
    cargarInfoSolicitudAdjuntos(solicitudId);
    
    // Cargar adjuntos existentes
    cargarAdjuntos(solicitudId);
    
    // Mostrar modal
    $('#adjuntosModal').modal('show');
}

// Función para cargar información de la solicitud en el modal de adjuntos
function cargarInfoSolicitudAdjuntos(solicitudId) {
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        data: { id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const solicitud = response.data;
                const infoHtml = `
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-file-alt me-2"></i>Solicitud #${solicitud.id}
                                </h6>
                                <p class="card-text mb-1">
                                    <strong>Cliente:</strong> ${solicitud.nombre_cliente}
                                </p>
                                <p class="card-text mb-1">
                                    <strong>Cédula:</strong> ${solicitud.cedula}
                                </p>
                                <p class="card-text mb-0">
                                    <strong>Estado:</strong> <span class="badge bg-primary">${solicitud.estado}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                `;
                $('#adjuntosSolicitudInfo').html(infoHtml);
            }
        },
        error: function() {
            $('#adjuntosSolicitudInfo').html('<div class="col-md-12"><div class="alert alert-warning">Error al cargar información de la solicitud</div></div>');
        }
    });
}

// Función para guardar solicitud
function guardarSolicitud() {
    console.log('=== GUARDANDO SOLICITUD ===');
    const formData = new FormData($('#solicitudForm')[0]);
    const solicitudId = $('#solicitud_id').val();
    
    console.log('Solicitud ID:', solicitudId);
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Usar POST para ambos casos, con parámetro adicional para actualización
    const method = 'POST';
    if (solicitudId) {
        formData.append('_method', 'PUT');
    }
    console.log('Método:', method);
    
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
                
                // Si es una nueva solicitud, mostrar mensaje y cerrar modal
                if (!solicitudId && response.data && response.data.id) {
                    mostrarAlerta('Solicitud creada correctamente. Puedes gestionar adjuntos desde la tabla de solicitudes.', 'success');
                    $('#solicitudModal').modal('hide');
                } else {
                    $('#solicitudModal').modal('hide');
                }
                
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
    console.log('=== EDITANDO SOLICITUD ===');
    console.log('ID recibido:', id);
    
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            console.log('=== RESPUESTA AJAX EDITAR ===');
            console.log('Response completa:', response);
            if (response.success) {
                console.log('Datos de solicitud:', response.data);
                llenarFormularioEdicion(response.data);
            } else {
                console.error('Error en respuesta:', response.message);
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
    console.log('=== LLENANDO FORMULARIO DE EDICIÓN ===');
    console.log('Solicitud recibida:', solicitud);
    console.log('ID de solicitud:', solicitud.id);
    
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
    // Limpiar fechas inválidas
    if (solicitud.estabilidad_laboral && solicitud.estabilidad_laboral !== '0000-00-00' && solicitud.estabilidad_laboral !== '-') {
        $('#estabilidad_laboral').val(solicitud.estabilidad_laboral);
    } else {
        $('#estabilidad_laboral').val('');
    }
    
    if (solicitud.fecha_constitucion && solicitud.fecha_constitucion !== '0000-00-00' && solicitud.fecha_constitucion !== '-') {
        $('#fecha_constitucion').val(solicitud.fecha_constitucion);
    } else {
        $('#fecha_constitucion').val('');
    }
    
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
    
    // Asignaciones
    $('#banco_id').val(solicitud.banco_id || '');
    $('#vendedor_id').val(solicitud.vendedor_id || '');
    
    // Cambiar título del modal
    $('#solicitudModalLabel').html('<i class="fas fa-edit me-2"></i>Editar Solicitud de Crédito');
    
    // Configurar adjuntos ANTES de mostrar el modal
    // El modal de adjuntos se abrirá independientemente
    
    // Inicializar usuarios banco
    inicializarUsuariosBanco(solicitud.id);
    
    // Deshabilitar campos si el usuario es banco
    if (window.userRoles && window.userRoles.isBanco) {
        deshabilitarCamposParaBanco();
    }
    
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

// Función para eliminar solicitud
function eliminarSolicitud(solicitudId) {
    // Mostrar confirmación
    if (!confirm('¿Estás seguro de que deseas eliminar esta solicitud?\n\nEsta acción eliminará:\n- La solicitud\n- Todas las notas del muro\n- Todos los archivos adjuntos\n\nEsta acción NO se puede deshacer.')) {
        return;
    }
    
    // Mostrar confirmación adicional para mayor seguridad
    if (!confirm('CONFIRMACIÓN FINAL:\n\n¿Realmente deseas eliminar esta solicitud?\n\nEsta acción es IRREVERSIBLE.')) {
        return;
    }
    
    console.log('=== ELIMINANDO SOLICITUD ===');
    console.log('ID de solicitud:', solicitudId);
    
    $.ajax({
        url: 'api/solicitudes.php',
        type: 'DELETE',
        data: { id: solicitudId },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de eliminación:', response);
            if (response.success) {
                mostrarAlerta('Solicitud eliminada correctamente junto con sus notas y adjuntos', 'success');
                
                // Recargar la tabla de solicitudes
                cargarSolicitudes();
                
                // Cerrar cualquier modal abierto
                $('.modal').modal('hide');
            } else {
                mostrarAlerta('Error al eliminar solicitud: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            let mensaje = 'Error de conexión al eliminar solicitud';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                mensaje = xhr.responseJSON.message;
            }
            mostrarAlerta(mensaje, 'danger');
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

// ===== FUNCIONES DE USUARIOS BANCO =====

let solicitudActualId = null;
let usuarioSeleccionado = null;

/**
 * Configurar funcionalidad de usuarios banco
 */
function configurarUsuariosBanco() {
    // Autocompletado de usuarios banco
    $('#buscar_usuario_banco').on('input', function() {
        const termino = $(this).val();
        if (termino.length >= 2) {
            buscarUsuariosBanco(termino);
        } else {
            $('#sugerencias_usuarios').hide();
        }
    });
    
    // Ocultar sugerencias al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#buscar_usuario_banco, #sugerencias_usuarios').length) {
            $('#sugerencias_usuarios').hide();
        }
    });
    
    // Manejar clic en sugerencias usando delegación de eventos
    $(document).on('click', '.sugerencia-usuario', function() {
        console.log('Clic en sugerencia detectado');
        
        // Obtener datos de los atributos individuales
        const $element = $(this);
        usuarioSeleccionado = {
            id: $element.data('usuario-id'),
            nombre: $element.data('usuario-nombre'),
            apellido: $element.data('usuario-apellido'),
            email: $element.data('usuario-email'),
            banco_nombre: $element.data('usuario-banco')
        };
        
        console.log('Usuario seleccionado:', usuarioSeleccionado);
        
        // Validar que tenemos los datos necesarios
        if (!usuarioSeleccionado.id || !usuarioSeleccionado.nombre) {
            console.error('Datos de usuario incompletos:', usuarioSeleccionado);
            mostrarAlerta('Error al seleccionar usuario', 'danger');
            return;
        }
        
        $('#buscar_usuario_banco').val(`${usuarioSeleccionado.nombre} ${usuarioSeleccionado.apellido} - ${usuarioSeleccionado.banco_nombre || 'Sin banco'}`);
        $('#agregar_usuario_banco').prop('disabled', false);
        $('#sugerencias_usuarios').hide();
        
        console.log('Usuario seleccionado correctamente');
    });
    
    // Agregar usuario banco
    $('#agregar_usuario_banco').on('click', function() {
        console.log('Botón agregar usuario clickeado');
        console.log('Usuario seleccionado:', usuarioSeleccionado);
        console.log('Solicitud actual ID:', solicitudActualId);
        if (usuarioSeleccionado && solicitudActualId) {
            asignarUsuarioBanco(solicitudActualId, usuarioSeleccionado.id);
        } else {
            console.error('No se puede asignar: usuario o solicitud faltante');
            mostrarAlerta('Por favor seleccione un usuario y asegúrese de estar editando una solicitud', 'warning');
        }
    });
    
    // Enviar mensaje
    $('#enviar_mensaje').on('click', function() {
        const mensaje = $('#mensaje_muro').val().trim();
        if (mensaje && solicitudActualId) {
            enviarMensaje(solicitudActualId, mensaje);
        }
    });
}

/**
 * Buscar usuarios banco para autocompletado
 */
function buscarUsuariosBanco(termino) {
    console.log('Buscando usuarios banco con término:', termino);
    $.ajax({
        url: 'api/usuarios_banco_solicitudes.php',
        type: 'GET',
        data: { q: termino },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de búsqueda:', response);
            if (response.success) {
                console.log('Usuarios encontrados:', response.data.length);
                mostrarSugerencias(response.data);
            } else {
                console.error('Error en respuesta:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al buscar usuarios banco:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
        }
    });
}

/**
 * Mostrar sugerencias de usuarios
 */
function mostrarSugerencias(usuarios) {
    console.log('Mostrando sugerencias para usuarios:', usuarios);
    const container = $('#sugerencias_usuarios');
    container.empty();
    
    if (usuarios.length === 0) {
        container.html('<div class="list-group-item text-muted">No se encontraron usuarios</div>');
    } else {
        usuarios.forEach(usuario => {
            console.log('Agregando sugerencia para usuario:', usuario);
            const item = $(`
                <div class="list-group-item list-group-item-action sugerencia-usuario" 
                     data-usuario-id="${usuario.id}"
                     data-usuario-nombre="${usuario.nombre}"
                     data-usuario-apellido="${usuario.apellido}"
                     data-usuario-email="${usuario.email}"
                     data-usuario-banco="${usuario.banco_nombre || ''}">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${usuario.nombre} ${usuario.apellido}</h6>
                        <small>${usuario.banco_nombre || 'Sin banco'}</small>
                    </div>
                    <p class="mb-1 text-muted">${usuario.email}</p>
                </div>
            `);
            container.append(item);
        });
    }
    
    console.log('Sugerencias agregadas, mostrando container');
    container.show();
}

/**
 * Asignar usuario banco a solicitud
 */
function asignarUsuarioBanco(solicitudId, usuarioId) {
    console.log('Asignando usuario banco:', { solicitudId, usuarioId });
    $.ajax({
        url: 'api/usuarios_banco_solicitudes.php',
        type: 'POST',
        data: {
            solicitud_id: solicitudId,
            usuario_banco_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de asignación:', response);
            if (response.success) {
                mostrarAlerta('Usuario asignado correctamente', 'success');
                cargarUsuariosAsignados(solicitudId);
                limpiarFormularioUsuario();
            } else {
                mostrarAlerta(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al asignar usuario:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            mostrarAlerta('Error al asignar usuario', 'danger');
        }
    });
}

/**
 * Cargar usuarios asignados a una solicitud
 */
function cargarUsuariosAsignados(solicitudId) {
    $.ajax({
        url: 'api/usuarios_banco_solicitudes.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarUsuariosAsignados(response.data);
            }
        },
        error: function() {
            console.error('Error al cargar usuarios asignados');
        }
    });
}

/**
 * Mostrar usuarios asignados en la tabla
 */
function mostrarUsuariosAsignados(usuarios) {
    const tbody = $('#usuariosAsignadosBody');
    tbody.empty();
    
    if (usuarios.length === 0) {
        tbody.html('<tr><td colspan="6" class="text-center text-muted">No hay usuarios asignados</td></tr>');
        return;
    }
    
    usuarios.forEach(usuario => {
        const estadoClass = usuario.estado === 'activo' ? 'success' : 'secondary';
        const estadoText = usuario.estado === 'activo' ? 'Activo' : 'Inactivo';
        
        const row = $(`
            <tr>
                <td>
                    <div>
                        <strong>${usuario.nombre} ${usuario.apellido}</strong><br>
                        <small class="text-muted">${usuario.email}</small>
                    </div>
                </td>
                <td>${usuario.banco_nombre || 'Sin banco'}</td>
                <td><span class="badge bg-${estadoClass}">${estadoText}</span></td>
                <td>${formatearFecha(usuario.fecha_asignacion)}</td>
                <td>-</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-${usuario.estado === 'activo' ? 'warning' : 'success'}" 
                                onclick="cambiarEstadoUsuario(${usuario.id}, '${usuario.estado === 'activo' ? 'inactivo' : 'activo'}')">
                            <i class="fas fa-${usuario.estado === 'activo' ? 'pause' : 'play'}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="desasignarUsuario(${usuario.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `);
        tbody.append(row);
    });
}

/**
 * Cambiar estado de usuario (activar/desactivar)
 */
function cambiarEstadoUsuario(id, nuevoEstado) {
    console.log('Cambiando estado de usuario:', { id, nuevoEstado });
    $.ajax({
        url: 'api/usuarios_banco_solicitudes.php',
        type: 'PUT',
        data: {
            id: id,
            estado: nuevoEstado
        },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de cambio de estado:', response);
            if (response.success) {
                mostrarAlerta('Estado actualizado correctamente', 'success');
                cargarUsuariosAsignados(solicitudActualId);
            } else {
                mostrarAlerta(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al actualizar estado:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            mostrarAlerta('Error al actualizar estado', 'danger');
        }
    });
}

/**
 * Desasignar usuario
 */
function desasignarUsuario(id) {
    console.log('Desasignando usuario con ID:', id);
    if (confirm('¿Está seguro de que desea desasignar este usuario?')) {
        $.ajax({
            url: 'api/usuarios_banco_solicitudes.php?id=' + id,
            type: 'DELETE',
            dataType: 'json',
            success: function(response) {
                console.log('Respuesta de desasignación:', response);
                if (response.success) {
                    mostrarAlerta('Usuario desasignado correctamente', 'success');
                    cargarUsuariosAsignados(solicitudActualId);
                } else {
                    mostrarAlerta(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al desasignar usuario:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                mostrarAlerta('Error al desasignar usuario', 'danger');
            }
        });
    }
}

/**
 * Enviar mensaje al muro
 */
function enviarMensaje(solicitudId, mensaje) {
    $.ajax({
        url: 'api/mensajes_solicitud.php',
        type: 'POST',
        data: {
            solicitud_id: solicitudId,
            mensaje: mensaje
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Mensaje enviado correctamente', 'success');
                $('#mensaje_muro').val('');
                cargarMensajes(solicitudId);
            } else {
                mostrarAlerta(response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error al enviar mensaje', 'danger');
        }
    });
}

/**
 * Cargar mensajes del muro
 */
function cargarMensajes(solicitudId) {
    $.ajax({
        url: 'api/mensajes_solicitud.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarMensajes(response.data);
            }
        },
        error: function() {
            console.error('Error al cargar mensajes');
        }
    });
}

/**
 * Mostrar mensajes en el historial
 */
function mostrarMensajes(mensajes) {
    const container = $('#historialMensajes');
    container.empty();
    
    if (mensajes.length === 0) {
        container.html('<div class="text-center text-muted">No hay mensajes</div>');
        return;
    }
    
    mensajes.forEach(mensaje => {
        const tipoClass = {
            'banco': 'info',
            'gestor': 'primary',
            'general': 'secondary'
        }[mensaje.tipo] || 'secondary';
        
        const item = $(`
            <div class="card mb-2">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${mensaje.nombre} ${mensaje.apellido}</strong>
                            <span class="badge bg-${tipoClass} ms-2">${mensaje.rol_nombre || 'Usuario'}</span>
                        </div>
                        <small class="text-muted">${formatearFecha(mensaje.fecha_creacion)}</small>
                    </div>
                    <p class="mb-0 mt-1">${mensaje.mensaje}</p>
                </div>
            </div>
        `);
        container.append(item);
    });
    
    // Scroll al final
    container.scrollTop(container[0].scrollHeight);
}

/**
 * Limpiar formulario de usuario
 */
function limpiarFormularioUsuario() {
    $('#buscar_usuario_banco').val('');
    $('#agregar_usuario_banco').prop('disabled', true);
    usuarioSeleccionado = null;
    $('#sugerencias_usuarios').hide();
}

/**
 * Inicializar pestaña de usuarios banco cuando se abre el modal
 */
function inicializarUsuariosBanco(solicitudId) {
    solicitudActualId = solicitudId;
    if (solicitudId) {
        cargarUsuariosAsignados(solicitudId);
        cargarMensajes(solicitudId);
    }
}

/**
 * Deshabilitar todos los campos de edición para usuarios banco
 */
function deshabilitarCamposParaBanco() {
    console.log('=== DESHABILITANDO CAMPOS PARA USUARIO BANCO ===');
    
    // Deshabilitar todos los inputs, selects y textareas en el modal
    $('#solicitudModal input, #solicitudModal select, #solicitudModal textarea').each(function() {
        $(this).prop('disabled', true);
        $(this).addClass('form-control-disabled');
    });
    
    // Deshabilitar botones de acción
    $('#solicitudModal button[type="submit"]').prop('disabled', true);
    $('#solicitudModal button[id*="btn"]').prop('disabled', true);
    
    // Agregar estilo visual para indicar que está deshabilitado
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .form-control-disabled {
                background-color: #f8f9fa !important;
                opacity: 0.7 !important;
                cursor: not-allowed !important;
            }
            .form-control-disabled:focus {
                box-shadow: none !important;
                border-color: #ced4da !important;
            }
        `)
        .appendTo('head');
}

