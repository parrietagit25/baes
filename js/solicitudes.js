// Inicializar DataTable para solicitudes
$(document).ready(function() {
    console.log('=== INICIALIZANDO SOLICITUDES ===');
    
    // Deshabilitar campos si el usuario es banco
    if (window.userRoles && window.userRoles.isBanco) {
        deshabilitarCamposParaBanco();
    }
    
    cargarSolicitudes();

    // Configurar formulario de solicitudes usando delegación de eventos
    $(document).on('submit', '#solicitudForm', function(e) {
        console.log('=== FORMULARIO ENVIADO (delegado) ===');
        e.preventDefault();
        e.stopPropagation();
        guardarSolicitud();
        return false;
    });
    
    // También agregar event listener directo por si acaso
    $('#solicitudForm').on('submit', function(e) {
        console.log('=== FORMULARIO ENVIADO (directo) ===');
        e.preventDefault();
        e.stopPropagation();
        guardarSolicitud();
        return false;
    });
    
    // Event listener adicional directamente en el botón de guardar (por si el submit no funciona)
    $(document).on('click', '#solicitudForm button[type="submit"]', function(e) {
        console.log('=== CLICK EN BOTÓN GUARDAR ===');
        e.preventDefault();
        e.stopPropagation();
        const form = $(this).closest('form');
        if (form.length > 0 && form.attr('id') === 'solicitudForm') {
            console.log('Ejecutando guardarSolicitud desde click del botón');
            guardarSolicitud();
        }
        return false;
    });
    
    // Configurar formulario de notas (delegado para formularios dinámicos)
    $(document).on('submit', '.formNotaMuro', function(e) {
        e.preventDefault();
        enviarNotaMuro($(this));
    });
    
    // Configurar formulario de notas antiguo por compatibilidad
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
    
    // Event listener para mostrar/ocultar nota cuando se selecciona "Completada"
    $(document).on('change', '#nuevo_estado', function() {
        const estadoSeleccionado = $(this).val();
        const notaCompletada = $('#nota_completada');
        
        if (estadoSeleccionado === 'Completada') {
            notaCompletada.removeClass('d-none');
        } else {
            notaCompletada.addClass('d-none');
        }
    });
    
    // Limpiar la nota cuando se cierre el modal
    $('#cambioEstadoModal').on('hidden.bs.modal', function() {
        $('#nota_completada').addClass('d-none');
        $('#nuevo_estado').val('');
    });
    
    // Event listener cuando el modal de solicitud se muestra
    $('#solicitudModal').on('shown.bs.modal', function() {
        console.log('=== MODAL DE SOLICITUD ABIERTO ===');
        
        // Asegurarse de que el event listener del formulario esté activo
        const form = $('#solicitudForm');
        if (form.length > 0) {
            console.log('Formulario encontrado, configurando event listeners...');
            
            // Remover event listeners anteriores para evitar duplicados
            form.off('submit');
            
            // Agregar event listener del submit
            form.on('submit', function(e) {
                console.log('=== FORMULARIO ENVIADO (modal abierto) ===');
                e.preventDefault();
                e.stopPropagation();
                guardarSolicitud();
                return false;
            });
            
            // Event listener en el botón directamente
            const btnGuardar = $('#btnGuardarSolicitud');
            if (btnGuardar.length > 0) {
                console.log('Botón de guardar encontrado');
                btnGuardar.off('click').on('click', function(e) {
                    console.log('=== CLICK EN BOTÓN GUARDAR (modal abierto) ===');
                    e.preventDefault();
                    e.stopPropagation();
                    guardarSolicitud();
                    return false;
                });
            }
        }
    });
});

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
        
        // Detectar dinámicamente la última columna (acciones)
        const ultimaColumna = columnasHeader - 1;
        
        $('#solicitudesTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            responsive: true,
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            columnDefs: [
                { orderable: false, targets: [ultimaColumna] } // Columna de acciones no ordenable (última columna)
            ],
            autoWidth: false,
            processing: true,
            deferRender: true
        });
        
        console.log('✅ DataTable de solicitudes inicializado correctamente');
        
    } catch (error) {
        console.error('❌ Error al inicializar DataTable de solicitudes:', error);
        console.error('Detalles del error:', error.message);
    }
}

// Función para inicializar DataTable (las solicitudes ya vienen del HTML)
function cargarSolicitudes() {
    // Verificar si la tabla tiene filas ya cargadas desde el servidor
    const filasExistentes = $('#solicitudesTable tbody tr').length;
    console.log('Filas existentes en el HTML:', filasExistentes);
    
    if (filasExistentes > 0) {
        // Si hay filas en el HTML, simplemente inicializar DataTable sin recargar
        console.log('Inicializando DataTable con datos existentes del HTML');
        setTimeout(inicializarDataTable, 300);
        return;
    }
    
    // Si no hay filas, cargar desde la API (caso normal)
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
    
    // Ocultar pestaña "Cita y Firma" al crear nueva solicitud
    $('#cita-firma-tab-li').hide();
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
    
    // Verificar que el formulario existe
    const form = $('#solicitudForm');
    if (form.length === 0) {
        console.error('❌ ERROR: El formulario #solicitudForm no existe');
        mostrarAlerta('Error: El formulario no se encuentra', 'danger');
        return;
    }
    
    // Validar campos requeridos manualmente
    const camposRequeridos = {
        'tipo_persona': 'Tipo de Persona',
        'nombre_cliente': 'Nombre del Cliente',
        'cedula': '# de Cédula',
        'perfil_financiero': 'Perfil Financiero'
    };
    
    let camposFaltantes = [];
    for (let campo in camposRequeridos) {
        const valor = $(`#${campo}`).val();
        if (!valor || valor.trim() === '') {
            camposFaltantes.push(camposRequeridos[campo]);
        }
    }
    
    if (camposFaltantes.length > 0) {
        console.error('❌ ERROR: Campos requeridos faltantes:', camposFaltantes);
        mostrarAlerta('Por favor, complete los siguientes campos requeridos:\n\n• ' + camposFaltantes.join('\n• '), 'warning');
        
        // Resaltar campos faltantes
        for (let campo in camposRequeridos) {
            const valor = $(`#${campo}`).val();
            if (!valor || valor.trim() === '') {
                $(`#${campo}`).addClass('is-invalid');
                setTimeout(() => {
                    $(`#${campo}`).removeClass('is-invalid');
                }, 3000);
            }
        }
        
        // Hacer scroll al primer campo faltante
        if (camposFaltantes.length > 0) {
            const primerCampo = Object.keys(camposRequeridos).find(campo => {
                const valor = $(`#${campo}`).val();
                return !valor || valor.trim() === '';
            });
            if (primerCampo) {
                $(`#${primerCampo}`).focus();
                $('html, body').animate({
                    scrollTop: $(`#${primerCampo}`).offset().top - 100
                }, 500);
            }
        }
        
        return;
    }
    
    // Validar que el formulario sea válido (HTML5 validation)
    if (!form[0].checkValidity()) {
        console.error('❌ ERROR: El formulario no es válido (validación HTML5)');
        form[0].reportValidity();
        return;
    }
    
    const formData = new FormData(form[0]);
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
    
    // Mostrar indicador de carga
    const submitButton = form.find('button[type="submit"]');
    const originalText = submitButton.html();
    submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
    
    $.ajax({
        url: 'api/solicitudes.php',
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('✅ Respuesta del servidor:', response);
            submitButton.prop('disabled', false).html(originalText);
            
            if (response.success) {
                const nuevaSolicitudId = response.data && response.data.id ? response.data.id : solicitudId;
                
                // Guardar vehículos
                if (nuevaSolicitudId && vehiculosList.length > 0) {
                    guardarVehiculos(nuevaSolicitudId);
                } else {
                    mostrarAlertaFinGuardado(solicitudId, nuevaSolicitudId);
                }
            } else {
                mostrarAlerta('Error al guardar solicitud: ' + (response.message || 'Error desconocido'), 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ ERROR AJAX:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            submitButton.prop('disabled', false).html(originalText);
            
            let mensaje = 'Error de conexión al guardar solicitud';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.message) {
                    mensaje = errorResponse.message;
                }
            } catch (e) {
                // Si no se puede parsear, usar el mensaje por defecto
            }
            mostrarAlerta(mensaje, 'danger');
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
      $('#email_pipedrive').val(solicitud.email_pipedrive);
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
      $('#profesion').val(solicitud.profesion);
      $('#ocupacion').val(solicitud.ocupacion);
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
      
      $('#continuidad_laboral').val(solicitud.continuidad_laboral);
      
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
    
    // Cargar vehículos
    cargarVehiculos(solicitud.id);
    
    // Deshabilitar campos si el usuario es banco
    if (window.userRoles && window.userRoles.isBanco) {
        deshabilitarCamposParaBanco();
    }
    
    // Verificar si hay evaluación seleccionada para mostrar/ocultar pestaña "Cita y Firma"
    verificarEvaluacionSeleccionada(solicitud.id);
    
    // Mostrar modal
    $('#solicitudModal').modal('show');
}

// Función para ver muro de tiempo
function verMuro(id) {
    // Cargar información de la solicitud
    cargarInfoSolicitud(id);
    
    // Cargar vehículos y usuarios banco para crear las pestañas
    cargarMuroCompleto(id);
    
    // Mostrar modal
    $('#muroModal').modal('show');
}

// Función para cargar el muro completo con vehículos y usuarios banco
function cargarMuroCompleto(solicitudId) {
    // Cargar vehículos
    $.ajax({
        url: 'api/vehiculos_solicitud.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(responseVehiculos) {
            const vehiculos = responseVehiculos.success ? responseVehiculos.data : [];
            
                          // Cargar usuarios banco
              $.ajax({
                  url: 'api/usuarios_banco_solicitudes.php',
                  type: 'GET',
                  data: { solicitud_id: solicitudId },
                  dataType: 'json',
                  success: function(responseUsuarios) {
                      let usuariosBanco = responseUsuarios.success ? responseUsuarios.data : [];
                      
                      // Si el usuario es tipo banco (y no es admin ni gestor), filtrar solo su propia pestaña
                      if (window.userRoles && window.userRoles.isBanco && 
                          !window.userRoles.isAdmin && !window.userRoles.isGestor && 
                          window.userId) {
                          usuariosBanco = usuariosBanco.filter(function(usuario) {
                              // Comparar el usuario_banco_id con el userId del usuario logueado
                              return usuario.usuario_banco_id == window.userId;
                          });
                      }
                      
                      // Generar estructura de pestañas
                      generarPestanasMuro(solicitudId, vehiculos, usuariosBanco);
                  },
                error: function() {
                    console.error('Error al cargar usuarios banco');
                    generarPestanasMuro(solicitudId, vehiculos, []);
                }
            });
        },
        error: function() {
            console.error('Error al cargar vehículos');
            generarPestanasMuro(solicitudId, [], []);
        }
    });
}

// Función para generar las pestañas del muro
function generarPestanasMuro(solicitudId, vehiculos, usuariosBanco) {
    const tabsContainer = $('#vehiculosTabs');
    const contentContainer = $('#vehiculosTabContent');
    
    tabsContainer.empty();
    contentContainer.empty();
    
    // Si no hay vehículos, mostrar un tab general
    if (vehiculos.length === 0) {
        const tabHtml = `
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="fas fa-car me-2"></i>General
                </button>
            </li>
        `;
        tabsContainer.html(tabHtml);
        
        generarContenidoTab(solicitudId, 'general', null, usuariosBanco, true);
        
        // Cargar notas del primer tab
        cargarNotasPrimerTab();
        return;
    }
    
    // Generar un tab por cada vehículo
    vehiculos.forEach((vehiculo, index) => {
        const activo = index === 0 ? 'active' : '';
        const vehId = `vehiculo_${vehiculo.id || index}`;
        
        const tabHtml = `
            <li class="nav-item" role="presentation">
                <button class="nav-link ${activo}" data-bs-toggle="tab" data-bs-target="#${vehId}" type="button">
                    <i class="fas fa-car me-2"></i>${vehiculo.marca || 'Sin marca'} ${vehiculo.modelo || ''} (${vehiculo.anio || 'N/A'})
                </button>
            </li>
        `;
        tabsContainer.append(tabHtml);
        
        // Generar contenido del tab (pasar si es el primero para la clase activo)
        generarContenidoTab(solicitudId, vehId, vehiculo, usuariosBanco, index === 0);
    });
    
    // Cargar notas del primer tab
    cargarNotasPrimerTab();
}

// Función para generar el contenido de un tab
function generarContenidoTab(solicitudId, tabId, vehiculo, usuariosBanco, isFirst = false) {
    const activo = isFirst ? 'show active' : '';
    
    let contenidoHTML = `
        <div class="tab-pane fade ${activo}" id="${tabId}" role="tabpanel">
            <div class="mt-3">
                ${vehiculo ? `
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-car me-2"></i>Vehículo</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4"><strong>Marca:</strong> ${vehiculo.marca || '-'}</div>
                                <div class="col-md-4"><strong>Modelo:</strong> ${vehiculo.modelo || '-'}</div>
                                <div class="col-md-4"><strong>Año:</strong> ${vehiculo.anio || '-'}</div>
                                <div class="col-md-4 mt-2"><strong>Precio:</strong> $${vehiculo.precio || '-'}</div>
                                <div class="col-md-4 mt-2"><strong>Kilometraje:</strong> ${vehiculo.kilometraje || '-'}</div>
                            </div>
                        </div>
                    </div>
                ` : ''}
    `;
    
    // Si no hay usuarios banco, mostrar solo comentarios generales
    if (usuariosBanco.length === 0) {
        contenidoHTML += generarFormularioNota(solicitudId, null, vehiculo ? vehiculo.id : null);
        contenidoHTML += generarListaNotas(solicitudId, vehiculo ? vehiculo.id : null);
    } else {
        // Generar sub-pestañas para cada usuario banco
        contenidoHTML += `
                <ul class="nav nav-pills mb-3" id="usuariosTab_${tabId}" role="tablist">
        `;
        
        usuariosBanco.forEach((usuario, index) => {
            const activoUsuario = index === 0 ? 'active' : '';
            const usuarioId = `usuario_${usuario.id}`;
            
            contenidoHTML += `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link ${activoUsuario}" data-bs-toggle="tab" data-bs-target="#${tabId}_${usuarioId}" type="button">
                            ${usuario.usuario_nombre || ''} ${usuario.usuario_apellido || ''}
                        </button>
                    </li>
            `;
        });
        
        contenidoHTML += `
                </ul>
                <div class="tab-content" id="usuariosContent_${tabId}">
        `;
        
        usuariosBanco.forEach((usuario, index) => {
            const activoUsuario = index === 0 ? 'show active' : '';
            const usuarioId = `usuario_${usuario.id}`;
            
            contenidoHTML += `
                    <div class="tab-pane fade ${activoUsuario}" id="${tabId}_${usuarioId}" role="tabpanel">
            `;
            
            contenidoHTML += generarFormularioNota(solicitudId, usuario.id, vehiculo ? vehiculo.id : null);
            contenidoHTML += generarListaNotas(solicitudId, vehiculo ? vehiculo.id : null, usuario.id);
            
            contenidoHTML += `
                    </div>
            `;
        });
        
        contenidoHTML += `
                </div>
        `;
    }
    
    contenidoHTML += `
            </div>
        </div>
    `;
    
    $('#vehiculosTabContent').append(contenidoHTML);
}

// Función para cargar las notas del primer tab activo
function cargarNotasPrimerTab() {
    setTimeout(function() {
        // Buscar el primer tab que esté visible (Bootstrap 5 usa .show para visible)
        const primerContainer = $('.tab-pane.show .notas-container, .tab-pane.active .notas-container').first();
        
        if (primerContainer.length > 0) {
            const containerId = primerContainer.attr('id');
            const parts = containerId.split('_');
            if (parts.length >= 3) {
                const solicitudId = parts[1];
                const vehiculoId = parts[2] !== 'general' ? parts[2] : null;
                const usuarioBancoId = parts.length > 3 && parts[3] !== 'general' ? parts[3] : null;
                cargarNotasMuro(solicitudId, vehiculoId, usuarioBancoId, containerId);
            }
        } else {
            console.log('No se encontró contenedor de notas activo');
        }
    }, 300);
}

// Función para generar formulario de notas
function generarFormularioNota(solicitudId, usuarioBancoId, vehiculoId) {
    return `
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Nota</h6>
            </div>
            <div class="card-body">
                <form class="formNotaMuro" data-solicitud="${solicitudId}" data-usuario-banco="${usuarioBancoId || ''}" data-vehiculo="${vehiculoId || ''}">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Nota</label>
                                <select class="form-select" name="tipo_nota" required>
                                    <option value="Comentario">Comentario</option>
                                    <option value="Actualización">Actualización</option>
                                    <option value="Documento">Documento</option>
                                    <option value="Respuesta Banco">Respuesta Banco</option>
                                    <option value="Respuesta Cliente">Respuesta Cliente</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Título</label>
                                <input type="text" class="form-control" name="titulo" placeholder="Título de la nota">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido *</label>
                        <textarea class="form-control" name="contenido" rows="3" required placeholder="Escriba su nota aquí..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Nota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

// Función para generar lista de notas
function generarListaNotas(solicitudId, vehiculoId, usuarioBancoId) {
    const containerId = `notas_${solicitudId}_${vehiculoId || 'general'}_${usuarioBancoId || 'general'}`;
    return `
        <div id="${containerId}" class="notas-container">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin me-2"></i>Cargando notas...
            </div>
        </div>
    `;
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
function cargarNotasMuro(solicitudId, vehiculoId, usuarioBancoId, containerId) {
    const data = { solicitud_id: solicitudId };
    
    if (vehiculoId) {
        data.vehiculo_id = vehiculoId;
    }
    if (usuarioBancoId) {
        data.usuario_banco_id = usuarioBancoId;
    }
    
    const container = containerId ? $('#' + containerId) : $('#muroNotasContainer');
    
    $.ajax({
        url: 'api/notas.php',
        type: 'GET',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarNotasMuro(response.data, container);
            } else {
                container.html('<div class="alert alert-danger">Error al cargar notas: ' + response.message + '</div>');
            }
        },
        error: function() {
            container.html('<div class="alert alert-danger">Error de conexión al cargar notas</div>');
        }
    });
}

// Función para mostrar notas en el muro
function mostrarNotasMuro(notas, container) {
    if (!container) {
        container = $('#muroNotasContainer');
    }
    
    if (notas.length === 0) {
        container.html('<div class="alert alert-info text-center">No hay notas para esta sección</div>');
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

// Función para enviar nota desde formulario dinámico del muro
function enviarNotaMuro($form) {
    const formData = new FormData($form[0]);
    
    // Agregar datos adicionales del formulario
    const solicitudId = $form.data('solicitud');
    const usuarioBancoId = $form.data('usuario-banco');
    const vehiculoId = $form.data('vehiculo');
    
    formData.append('solicitud_id', solicitudId);
    
    if (usuarioBancoId) {
        formData.append('usuario_banco_id', usuarioBancoId);
    }
    if (vehiculoId) {
        formData.append('vehiculo_id', vehiculoId);
    }
    
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
                $form[0].reset();
                
                // Recargar notas del contenedor correspondiente
                const containerId = $form.closest('.tab-pane').find('.notas-container:first').attr('id');
                const parts = containerId.split('_');
                if (parts.length >= 3) {
                    const solId = parts[1];
                    const vehId = parts[2] !== 'general' ? parts[2] : null;
                    const usrBancoId = parts.length > 3 && parts[3] !== 'general' ? parts[3] : null;
                    cargarNotasMuro(solId, vehId, usrBancoId, containerId);
                }
            } else {
                mostrarAlerta('Error al enviar nota: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al enviar nota', 'danger');
        }
    });
}

// Función para enviar nota (compatibilidad con formulario antiguo)
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
                        <strong>${usuario.usuario_nombre || ''} ${usuario.usuario_apellido || ''}</strong><br>
                        <small class="text-muted">${usuario.usuario_email || ''}</small>
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

// ==================== GESTIÓN DE VEHÍCULOS ====================
let vehiculosList = [];

/**
 * Agregar un nuevo vehículo al formulario
 */
function agregarVehiculo() {
    const index = vehiculosList.length;
    vehiculosList.push({
        id: null,
        marca: '',
        modelo: '',
        anio: '',
        kilometraje: '',
        precio: '',
        abono_porcentaje: '',
        abono_monto: ''
    });
    renderizarVehiculos();
}

  /**
   * Eliminar un vehículo de la lista
   */
  function eliminarVehiculo(index) {
      vehiculosList.splice(index, 1);
      renderizarVehiculos();
  }
  
  /**
   * Abrir modal para confirmar apartar vehículo
   */
  function abrirModalApartarVehiculo(index) {
      if (!vehiculosList[index]) {
          mostrarAlerta('Vehículo no encontrado', 'danger');
          return;
      }
      
      const vehiculo = vehiculosList[index];
      const marcaModelo = vehiculo.marca && vehiculo.modelo 
          ? `${vehiculo.marca} ${vehiculo.modelo}` 
          : `Vehículo ${index + 1}`;
      
      // Guardar el index del vehículo en un campo oculto
      $('#vehiculo_index_apartar').val(index);
      
      // Mostrar información del vehículo en la modal
      $('#vehiculo_info_apartar').text(marcaModelo);
      
      // Mostrar la modal
      $('#modalApartarVehiculo').modal('show');
  }

/**
 * Actualizar un campo de vehículo
 */
function actualizarVehiculo(index, campo, valor) {
    if (vehiculosList[index]) {
        vehiculosList[index][campo] = valor;
    }
}

/**
 * Renderizar la lista de vehículos
 */
function renderizarVehiculos() {
    const container = $('#listaVehiculos');
    container.empty();
    
    if (vehiculosList.length === 0) {
        container.html('<div class="alert alert-info">No hay vehículos agregados</div>');
        return;
    }
    
    vehiculosList.forEach((vehiculo, index) => {
        // Verificar si el usuario puede ver los botones (solo admin y gestor)
        const puedeEditar = window.userRoles && (window.userRoles.isAdmin || window.userRoles.isGestor);
        const botonesHTML = puedeEditar ? `
                      <div class="btn-group btn-group-sm">
                          <button type="button" class="btn btn-warning" onclick="abrirModalApartarVehiculo(${index})" title="Apartar Vehículo">
                              <i class="fas fa-bookmark"></i> Apartar
                          </button>
                          <button type="button" class="btn btn-danger" onclick="eliminarVehiculo(${index})" title="Eliminar Vehículo">
                              <i class="fas fa-trash"></i>
                          </button>
                      </div>` : '';
        
        const card = $(`
                          <div class="card mb-3" data-index="${index}">
                  <div class="card-header d-flex justify-content-between align-items-center">
                      <strong>Vehículo ${index + 1}</strong>
                      ${botonesHTML}
                  </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" 
                                       value="${vehiculo.marca || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'marca', this.value)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Modelo</label>
                                <input type="text" class="form-control" 
                                       value="${vehiculo.modelo || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'modelo', this.value)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Año</label>
                                <input type="number" class="form-control" 
                                       value="${vehiculo.anio || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'anio', this.value)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kilometraje</label>
                                <input type="number" class="form-control" 
                                       value="${vehiculo.kilometraje || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'kilometraje', this.value)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Precio</label>
                                <input type="number" class="form-control" step="0.01"
                                       value="${vehiculo.precio || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'precio', this.value)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Abono (%)</label>
                                <input type="number" class="form-control" step="0.01"
                                       value="${vehiculo.abono_porcentaje || ''}" 
                                       onchange="actualizarVehiculo(${index}, 'abono_porcentaje', this.value)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
        container.append(card);
    });
}

/**
 * Limpiar lista de vehículos
 */
function limpiarVehiculos() {
    vehiculosList = [];
    renderizarVehiculos();
}

/**
 * Cargar vehículos existentes para una solicitud
 */
function cargarVehiculos(solicitudId) {
    $.ajax({
        url: 'api/vehiculos_solicitud.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                vehiculosList = response.data || [];
                renderizarVehiculos();
            }
        },
        error: function() {
            console.error('Error al cargar vehículos');
        }
    });
}

/**
 * Guardar vehículos de la solicitud
 */
function guardarVehiculos(solicitudId) {
    $.ajax({
        url: 'api/vehiculos_solicitud.php',
        type: 'POST',
        data: {
            solicitud_id: solicitudId,
            vehiculos: JSON.stringify(vehiculosList)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const solicitudIdAnterior = $('#solicitud_id').val();
                mostrarAlertaFinGuardado(solicitudIdAnterior, solicitudId);
            } else {
                mostrarAlerta('Error al guardar vehículos: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al guardar vehículos', 'danger');
        }
    });
}

/**
 * Mostrar alerta final después de guardar
 */
function mostrarAlertaFinGuardado(solicitudIdAnterior, nuevaSolicitudId) {
    const mensaje = solicitudIdAnterior ? 'Solicitud actualizada correctamente' : 'Solicitud creada correctamente';
    mostrarAlerta(mensaje, 'success');
    
    // Si es una nueva solicitud, mostrar mensaje
    if (!solicitudIdAnterior) {
        mostrarAlerta('Solicitud creada correctamente. Puedes gestionar adjuntos desde la tabla de solicitudes.', 'success');
    }
    
    $('#solicitudModal').modal('hide');
    limpiarVehiculos();
    cargarSolicitudes();
}

// ========== FUNCIONES PARA GESTIÓN DE CITAS Y FIRMA ==========

// Función para verificar si hay una evaluación seleccionada y mostrar/ocultar pestaña "Cita y Firma"
function verificarEvaluacionSeleccionada(solicitudId) {
    // La pestaña "Cita y Firma" solo debe aparecer para admin y gestor
    // Si el usuario NO es admin ni gestor, ocultar la pestaña inmediatamente
    if (!window.userRoles || (!window.userRoles.isAdmin && !window.userRoles.isGestor)) {
        $('#cita-firma-tab-li').hide();
        // Si estaba activa, cambiar a otra pestaña
        if ($('#cita-firma-tab').hasClass('active')) {
            $('#datos-generales-tab').tab('show');
        }
        return;
    }
    
    // Solo para admin y gestor: verificar si hay una evaluación seleccionada
    $.ajax({
        url: 'api/evaluaciones_banco.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            // Si hay una evaluación seleccionada, mostrar la pestaña
            if (response.success && response.evaluacion_seleccionada) {
                $('#cita-firma-tab-li').show();
            } else {
                // No hay evaluación seleccionada, ocultar la pestaña
                $('#cita-firma-tab-li').hide();
                // Si estaba activa, cambiar a otra pestaña
                if ($('#cita-firma-tab').hasClass('active')) {
                    $('#datos-generales-tab').tab('show');
                }
            }
        },
        error: function(xhr) {
            // En caso de error, ocultar la pestaña
            $('#cita-firma-tab-li').hide();
            // Si estaba activa, cambiar a otra pestaña
            if ($('#cita-firma-tab').hasClass('active')) {
                $('#datos-generales-tab').tab('show');
            }
        }
    });
}

// Función para cargar citas cuando se abre la pestaña "Cita y Firma"
$(document).on('shown.bs.tab', '#cita-firma-tab', function() {
    const solicitudId = $('#solicitud_id').val();
    if (solicitudId) {
        cargarCitas(solicitudId);
    }
});

// Función para cargar citas
function cargarCitas(solicitudId) {
    $('#citasTableBody').html('<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando citas...</td></tr>');
    
    $.ajax({
        url: 'api/citas_firma.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarCitas(response.data);
            } else {
                $('#citasTableBody').html('<tr><td colspan="5" class="text-center text-muted">' + (response.message || 'No se pudieron cargar las citas') + '</td></tr>');
            }
        },
        error: function() {
            $('#citasTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error al cargar las citas</td></tr>');
        }
    });
}

// Función para mostrar citas en la tabla
function mostrarCitas(citas) {
    const tbody = $('#citasTableBody');
    tbody.empty();
    
    if (citas.length === 0) {
        tbody.html('<tr><td colspan="5" class="text-center text-muted">No hay citas registradas</td></tr>');
        return;
    }
    
    citas.forEach(function(cita) {
        const fecha = new Date(cita.fecha_cita).toLocaleDateString('es-PA');
        const hora = cita.hora_cita ? cita.hora_cita.substring(0, 5) : '-';
        const comentarios = cita.comentarios || '-';
        
        // Estado de asistencia
        let estadoBadge = '';
        switch(cita.asistio) {
            case 'asistio':
                estadoBadge = '<span class="badge bg-success">Asistió</span>';
                break;
            case 'no_asistio':
                estadoBadge = '<span class="badge bg-danger">No Asistió</span>';
                break;
            default:
                estadoBadge = '<span class="badge bg-warning">Pendiente</span>';
        }
        
        // Botones de acción
        let botonesAccion = '';
        if (cita.asistio !== 'asistio') {
            botonesAccion += '<button class="btn btn-success btn-sm me-1" onclick="actualizarAsistencia(' + cita.id + ', \'asistio\')" title="Marcar como Asistió"><i class="fas fa-check"></i> Asistió</button>';
        }
        if (cita.asistio !== 'no_asistio') {
            botonesAccion += '<button class="btn btn-danger btn-sm me-1" onclick="actualizarAsistencia(' + cita.id + ', \'no_asistio\')" title="Marcar como No Asistió"><i class="fas fa-times"></i> No Asistió</button>';
        }
        // Botón de eliminar
        botonesAccion += '<button class="btn btn-danger btn-sm" onclick="eliminarCita(' + cita.id + ')" title="Eliminar Cita"><i class="fas fa-trash"></i></button>';
        
        const row = `
            <tr>
                <td>${fecha}</td>
                <td>${hora}</td>
                <td>${comentarios}</td>
                <td>${estadoBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        ${botonesAccion}
                    </div>
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

// Función para guardar una nueva cita
function guardarCita() {
    const solicitudId = $('#solicitud_id').val();
    const fechaCita = $('#fecha_cita').val();
    const horaCita = $('#hora_cita').val();
    const comentarios = $('#comentarios_cita').val();
    
    if (!solicitudId) {
        mostrarAlerta('Debe guardar la solicitud antes de crear una cita', 'warning');
        return;
    }
    
    if (!fechaCita || !horaCita) {
        mostrarAlerta('Por favor complete la fecha y hora de la cita', 'warning');
        return;
    }
    
    $.ajax({
        url: 'api/citas_firma.php',
        type: 'POST',
        data: {
            solicitud_id: solicitudId,
            fecha_cita: fechaCita,
            hora_cita: horaCita,
            comentarios: comentarios
        },
        dataType: 'json',
                  success: function(response) {
              if (response.success) {
                  mostrarAlerta('Cita creada correctamente', 'success');
                  // Recargar la página después de un breve delay para mostrar el mensaje
                  setTimeout(function() {
                      location.reload();
                  }, 1500);
              } else {
                  mostrarAlerta('Error al crear cita: ' + response.message, 'danger');
              }
          },
        error: function() {
            mostrarAlerta('Error de conexión al crear cita', 'danger');
        }
    });
}

// Función para actualizar la asistencia de una cita
function actualizarAsistencia(citaId, asistio) {
    const solicitudId = $('#solicitud_id').val();
    
    if (!solicitudId) {
        mostrarAlerta('Error: No se encontró el ID de la solicitud', 'danger');
        return;
    }
    
    // Enviar datos como form-urlencoded para PUT request
    const datos = 'id=' + encodeURIComponent(citaId) + '&asistio=' + encodeURIComponent(asistio);
    
    $.ajax({
        url: 'api/citas_firma.php',
        type: 'PUT',
        data: datos,
        contentType: 'application/x-www-form-urlencoded',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Asistencia actualizada correctamente', 'success');
                // Recargar citas
                cargarCitas(solicitudId);
            } else {
                mostrarAlerta('Error al actualizar asistencia: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al actualizar asistencia', 'danger');
        }
    });
}

// Función para eliminar una cita
function eliminarCita(citaId) {
    if (!confirm('¿Está seguro de que desea eliminar esta cita? Esta acción no se puede deshacer.')) {
        return;
    }
    
    const solicitudId = $('#solicitud_id').val();
    
    if (!solicitudId) {
        mostrarAlerta('Error: No se encontró el ID de la solicitud', 'danger');
        return;
    }
    
    // Enviar datos como form-urlencoded para DELETE request
    const datos = 'id=' + encodeURIComponent(citaId);
    
    $.ajax({
        url: 'api/citas_firma.php',
        type: 'DELETE',
        data: datos,
        contentType: 'application/x-www-form-urlencoded',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Cita eliminada correctamente', 'success');
                // Recargar citas
                cargarCitas(solicitudId);
            } else {
                mostrarAlerta('Error al eliminar cita: ' + response.message, 'danger');
            }
        },
        error: function(xhr) {
            let mensaje = 'Error de conexión al eliminar cita';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.message) {
                    mensaje = errorResponse.message;
                }
            } catch (e) {
                // Usar mensaje por defecto
            }
            mostrarAlerta(mensaje, 'danger');
        }
    });
}

