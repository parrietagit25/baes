// Inicializar DataTable para bancos
$(document).ready(function() {
    console.log('=== INICIALIZANDO BANCOS ===');
    cargarBancos();

    // Configurar formulario de bancos
    $('#bancoForm').on('submit', function(e) {
        console.log('=== FORMULARIO ENVIADO ===');
        e.preventDefault();
        guardarBanco();
    });
});

// Función para cargar bancos
function cargarBancos() {
    $.ajax({
        url: 'api/bancos.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de la API:', response);
            if (response.success) {
                const tbody = $('#bancosTable tbody');
                tbody.empty();
                
                console.log('Datos recibidos:', response.data);
                let filasGeneradas = 0;
                response.data.forEach(function(banco) {
                    console.log('Procesando banco:', banco);
                    
                    const estadoClass = banco.activo ? 'estado-activo' : 'estado-inactivo';
                    const estadoTexto = banco.activo ? 'Activo' : 'Inactivo';
                    
                    // Información de usuarios asignados
                    const usuariosCount = parseInt(banco.usuarios_count) || 0;
                    console.log('Usuarios count para', banco.nombre, ':', usuariosCount);
                    
                    const usuariosInfo = usuariosCount > 0 
                        ? `<span class="badge bg-info">${usuariosCount} usuario${usuariosCount > 1 ? 's' : ''}</span>`
                        : '<span class="text-muted">Sin usuarios</span>';
                    
                    const verUsuariosBtn = usuariosCount > 0 
                        ? `<button class="btn btn-sm btn-outline-info ms-2" onclick="verUsuariosBanco(${banco.id}, '${banco.nombre.replace(/'/g, "\\'")}')" title="Ver Usuarios">
                            <i class="fas fa-users"></i>
                        </button>`
                        : '';
                    
                    const row = `
                        <tr>
                            <td>${banco.id}</td>
                            <td><strong>${banco.nombre}</strong></td>
                            <td><span class="badge bg-secondary">${banco.codigo}</span></td>
                            <td>${banco.contacto_principal || '-'}</td>
                            <td>${banco.telefono || '-'}</td>
                            <td>${banco.email || '-'}</td>
                            <td>
                                ${usuariosInfo}
                                ${verUsuariosBtn}
                            </td>
                            <td><span class="badge badge-estado ${estadoClass}">${estadoTexto}</span></td>
                            <td>${formatearFecha(banco.fecha_creacion)}</td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <button class="btn btn-info btn-action" onclick="verDetalles(${banco.id})" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-primary btn-action" onclick="editarBanco(${banco.id})" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-danger btn-action" onclick="eliminarBanco(${banco.id})" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                    
                    console.log('Fila generada para', banco.nombre, ':', row);
                    tbody.append(row);
                    filasGeneradas++;
                });
                
                console.log('Total de filas generadas:', filasGeneradas);
                console.log('Filas en tbody:', tbody.find('tr').length);
                
                // Inicializar DataTable si no existe
                if (!$.fn.DataTable.isDataTable('#bancosTable')) {
                    // Pequeño delay para asegurar que el DOM esté completamente actualizado
                    setTimeout(function() {
                        try {
                            console.log('Inicializando DataTable...');
                            $('#bancosTable').DataTable({
                                language: {
                                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                                },
                                pageLength: 10,
                                order: [[1, 'asc']], // Ordenar por nombre
                                columnDefs: [
                                    { orderable: false, targets: [9] } // Columna de acciones no ordenable
                                ],
                                responsive: true,
                                autoWidth: false,
                                destroy: true // Destruir tabla anterior si existe
                            });
                            console.log('DataTable inicializado correctamente');
                        } catch (error) {
                            console.error('Error al inicializar DataTable:', error);
                        }
                    }, 200);
                } else {
                    // Si DataTable ya existe, recargar los datos
                    try {
                        console.log('Recargando datos de DataTable...');
                        $('#bancosTable').DataTable().clear().rows.add($('#bancosTable tbody tr')).draw();
                        console.log('Datos recargados correctamente');
                    } catch (error) {
                        console.error('Error al recargar DataTable:', error);
                    }
                }
            } else {
                mostrarAlerta('Error al cargar bancos: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar bancos', 'danger');
        }
    });
}

// Función para ver detalles de un banco
function verDetalles(bancoId) {
    console.log('=== VIENDO DETALLES ===');
    console.log('ID del banco:', bancoId);
    
    $.ajax({
        url: 'api/bancos.php',
        type: 'GET',
        data: { id: bancoId },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de detalles:', response);
            if (response.success) {
                const banco = response.data;
                mostrarDetallesBanco(banco);
            } else {
                mostrarAlerta('Error al obtener detalles: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al obtener detalles', 'danger');
        }
    });
}

// Función para mostrar detalles en un modal
function mostrarDetallesBanco(banco) {
    const modal = `
        <div class="modal fade" id="detallesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                        <h5 class="modal-title">
                            <i class="fas fa-university me-2"></i>Detalles del Banco
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Información Básica</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td>${banco.nombre}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Código:</strong></td>
                                        <td><span class="badge bg-secondary">${banco.codigo}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td><span class="badge ${banco.activo ? 'bg-success' : 'bg-danger'}">${banco.activo ? 'Activo' : 'Inactivo'}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Descripción:</strong></td>
                                        <td>${banco.descripcion || 'No especificada'}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Información de Contacto</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Dirección:</strong></td>
                                        <td>${banco.direccion || 'No especificada'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td>${banco.telefono || 'No especificado'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>${banco.email || 'No especificado'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sitio Web:</strong></td>
                                        <td>${banco.sitio_web ? `<a href="${banco.sitio_web}" target="_blank">${banco.sitio_web}</a>` : 'No especificado'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        ${banco.contacto_principal ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">Contacto Principal</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td>${banco.contacto_principal}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td>${banco.telefono_contacto || 'No especificado'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>${banco.email_contacto || 'No especificado'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">Información del Sistema</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Fecha de Creación:</strong></td>
                                        <td>${formatearFecha(banco.fecha_creacion)}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Última Actualización:</strong></td>
                                        <td>${formatearFecha(banco.fecha_actualizacion)}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="editarBanco(${banco.id})">
                            <i class="fas fa-edit me-2"></i>Editar Banco
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior si existe
    $('#detallesModal').remove();
    
    // Agregar nuevo modal al body
    $('body').append(modal);
    
    // Mostrar modal
    $('#detallesModal').modal('show');
}

// Función para editar banco
function editarBanco(bancoId) {
    console.log('=== EDITANDO BANCO ===');
    console.log('ID del banco:', bancoId);
    
    $.ajax({
        url: 'api/bancos.php',
        type: 'GET',
        data: { id: bancoId },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de edición:', response);
            if (response.success) {
                llenarFormularioEdicion(response.data);
                $('#bancoModal').modal('show');
            } else {
                mostrarAlerta('Error al obtener datos del banco: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al obtener datos del banco', 'danger');
        }
    });
}

// Función para llenar formulario de edición
function llenarFormularioEdicion(banco) {
    console.log('=== LLENANDO FORMULARIO DE EDICIÓN ===');
    console.log('Banco recibido:', banco);
    
    $('#banco_id').val(banco.id);
    $('#nombre').val(banco.nombre);
    $('#codigo').val(banco.codigo);
    $('#descripcion').val(banco.descripcion || '');
    $('#direccion').val(banco.direccion || '');
    $('#telefono').val(banco.telefono || '');
    $('#email').val(banco.email || '');
    $('#sitio_web').val(banco.sitio_web || '');
    $('#contacto_principal').val(banco.contacto_principal || '');
    $('#telefono_contacto').val(banco.telefono_contacto || '');
    $('#email_contacto').val(banco.email_contacto || '');
    $('#activo').prop('checked', banco.activo == 1);
    
    $('#bancoModalLabel').html('<i class="fas fa-university me-2"></i>Editar Banco');
}

// Función para guardar banco
function guardarBanco() {
    console.log('=== GUARDANDO BANCO ===');
    const formData = new FormData($('#bancoForm')[0]);
    const bancoId = $('#banco_id').val();
    
    console.log('Banco ID:', bancoId);
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Usar POST para ambos casos, con parámetro adicional para actualización
    const method = 'POST';
    if (bancoId) {
        formData.append('_method', 'PUT'); // Add a hidden field to indicate PUT
    }
    console.log('Método:', method);
    
    $.ajax({
        url: 'api/bancos.php',
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de guardado:', response);
            if (response.success) {
                mostrarAlerta(response.message, 'success');
                $('#bancoModal').modal('hide');
                cargarBancos();
            } else {
                mostrarAlerta('Error al guardar banco: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            let mensaje = 'Error de conexión al guardar banco';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                mensaje = xhr.responseJSON.message;
            }
            mostrarAlerta(mensaje, 'danger');
        }
    });
}

// Función para eliminar banco
function eliminarBanco(bancoId) {
    // Mostrar confirmación
    if (!confirm('¿Estás seguro de que deseas eliminar este banco?\n\nEsta acción NO se puede deshacer.')) {
        return;
    }
    
    console.log('=== ELIMINANDO BANCO ===');
    console.log('ID del banco:', bancoId);
    
    $.ajax({
        url: 'api/bancos.php',
        type: 'DELETE',
        data: { id: bancoId },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de eliminación:', response);
            if (response.success) {
                mostrarAlerta('Banco eliminado correctamente', 'success');
                cargarBancos();
            } else {
                mostrarAlerta('Error al eliminar banco: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            let mensaje = 'Error de conexión al eliminar banco';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                mensaje = xhr.responseJSON.message;
            }
            mostrarAlerta(mensaje, 'danger');
        }
    });
}

// Función para limpiar formulario
function limpiarFormularioBanco() {
    $('#banco_id').val('');
    $('#bancoForm')[0].reset();
    $('#bancoModalLabel').html('<i class="fas fa-university me-2"></i>Nuevo Banco');
    $('#activo').prop('checked', true);
}

// Función para formatear fecha
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Función para ver usuarios asignados a un banco
function verUsuariosBanco(bancoId, nombreBanco) {
    console.log('=== VIENDO USUARIOS DEL BANCO ===');
    console.log('ID del banco:', bancoId);
    console.log('Nombre del banco:', nombreBanco);
    
    $.ajax({
        url: 'api/bancos.php',
        type: 'GET',
        data: { usuarios: bancoId },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de usuarios:', response);
            if (response.success) {
                mostrarUsuariosBanco(bancoId, nombreBanco, response.data);
            } else {
                mostrarAlerta('Error al obtener usuarios: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al obtener usuarios', 'danger');
        }
    });
}

// Función para mostrar usuarios en un modal
function mostrarUsuariosBanco(bancoId, nombreBanco, usuarios) {
    console.log('=== MOSTRANDO USUARIOS DEL BANCO ===');
    console.log('Usuarios recibidos:', usuarios);
    
    let usuariosHtml = '';
    if (usuarios && usuarios.length > 0) {
        usuarios.forEach(function(usuario) {
            const roles = usuario.roles ? usuario.roles.join(', ') : 'Sin roles';
            usuariosHtml += `
                <tr>
                    <td>${usuario.id}</td>
                    <td><strong>${usuario.nombre} ${usuario.apellido}</strong></td>
                    <td>${usuario.email}</td>
                    <td><span class="badge bg-primary">${roles}</span></td>
                    <td>${formatearFecha(usuario.fecha_creacion)}</td>
                    <td>
                        <span class="badge ${usuario.activo ? 'bg-success' : 'bg-danger'}">
                            ${usuario.activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                </tr>
            `;
        });
    } else {
        usuariosHtml = `
            <tr>
                <td colspan="6" class="text-center text-muted">
                    <i class="fas fa-users-slash me-2"></i>No hay usuarios asignados a este banco
                </td>
            </tr>
        `;
    }
    
    const modal = `
        <div class="modal fade" id="usuariosModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);">
                        <h5 class="modal-title">
                            <i class="fas fa-users me-2"></i>Usuarios Asignados - ${nombreBanco}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-primary">
                                    <i class="fas fa-university me-2"></i>Banco: ${nombreBanco}
                                </h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="badge bg-info fs-6">
                                    ${usuarios ? usuarios.length : 0} usuario${usuarios && usuarios.length !== 1 ? 's' : ''} asignado${usuarios && usuarios.length !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>Roles</th>
                                        <th>Fecha Registro</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${usuariosHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="window.open('usuarios.php', '_blank')">
                            <i class="fas fa-external-link-alt me-2"></i>Gestionar Usuarios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior si existe
    $('#usuariosModal').remove();
    
    // Agregar nuevo modal al body
    $('body').append(modal);
    
    // Mostrar modal
    $('#usuariosModal').modal('show');
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
