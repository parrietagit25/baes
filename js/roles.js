// Inicializar DataTable para roles
$(document).ready(function() {
    $('#rolesTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        columnDefs: [
            { orderable: false, targets: [6] } // Columna de acciones no ordenable
        ]
    });

    // Configurar formulario de roles
    $('#rolForm').on('submit', function(e) {
        e.preventDefault();
        guardarRol();
    });
});

// Función para limpiar el formulario de rol
function limpiarFormularioRol() {
    $('#rol_id').val('');
    $('#rolForm')[0].reset();
    $('#rolModalLabel').html('<i class="fas fa-user-shield me-2"></i>Nuevo Rol');
    $('#activo').prop('checked', true);
}

// Función para editar rol
function editarRol(id) {
    $.ajax({
        url: 'api/roles.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const rol = response.data;
                
                // Llenar el formulario
                $('#rol_id').val(rol.id);
                $('#nombre').val(rol.nombre);
                $('#descripcion').val(rol.descripcion || '');
                $('#activo').prop('checked', rol.activo == 1);
                
                // Cambiar título del modal
                $('#rolModalLabel').html('<i class="fas fa-user-edit me-2"></i>Editar Rol');
                
                // Mostrar modal
                $('#rolModal').modal('show');
            } else {
                mostrarAlerta('Error al cargar rol: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar rol', 'danger');
        }
    });
}

// Función para eliminar rol
function eliminarRol(id) {
    // Obtener información del rol antes de eliminar
    $.ajax({
        url: 'api/roles.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const rol = response.data;
                
                // Verificar si es rol del sistema
                const rolesSistema = ['ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_USER'];
                if (rolesSistema.includes(rol.nombre)) {
                    mostrarAlerta('No se puede eliminar un rol del sistema', 'warning');
                    return;
                }
                
                // Verificar si tiene usuarios asignados
                $.ajax({
                    url: 'api/roles.php',
                    type: 'GET',
                    data: { verificar_usuarios: id },
                    dataType: 'json',
                    success: function(verificacion) {
                        if (verificacion.usuarios_asignados > 0) {
                            mostrarAlerta(`No se puede eliminar este rol porque tiene ${verificacion.usuarios_asignados} usuario(s) asignado(s)`, 'warning');
                            return;
                        }
                        
                        // Confirmar eliminación
                        if (confirm(`¿Está seguro de que desea eliminar el rol "${rol.nombre}"? Esta acción no se puede deshacer.`)) {
                            eliminarRolConfirmado(id);
                        }
                    },
                    error: function() {
                        mostrarAlerta('Error al verificar usuarios asignados', 'danger');
                    }
                });
            }
        },
        error: function() {
            mostrarAlerta('Error al obtener información del rol', 'danger');
        }
    });
}

// Función para confirmar eliminación del rol
function eliminarRolConfirmado(id) {
    $.ajax({
        url: 'api/roles.php',
        type: 'DELETE',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Rol eliminado correctamente', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                mostrarAlerta('Error al eliminar rol: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al eliminar rol', 'danger');
        }
    });
}

// Función para guardar rol
function guardarRol() {
    const formData = new FormData($('#rolForm')[0]);
    const rolId = $('#rol_id').val();
    
    // Determinar si es creación o actualización
    const method = rolId ? 'PUT' : 'POST';
    
    $.ajax({
        url: 'api/roles.php',
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const mensaje = rolId ? 'Rol actualizado correctamente' : 'Rol creado correctamente';
                mostrarAlerta(mensaje, 'success');
                $('#rolModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                mostrarAlerta('Error al guardar rol: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al guardar rol', 'danger');
        }
    });
}

// Función para mostrar alertas
function mostrarAlerta(mensaje, tipo) {
    const alerta = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle'} me-2"></i>
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

// Validación en tiempo real del nombre del rol
$('#nombre').on('blur', function() {
    const nombre = $(this).val();
    const id = $('#rol_id').val();
    
    if (nombre && nombre.trim() !== '') {
        // Validar formato ROLE_NOMBRE
        if (!nombre.match(/^ROLE_[A-Z_]+$/)) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">El nombre debe seguir el formato ROLE_NOMBRE (ej: ROLE_EDITOR)</div>');
            }
            return;
        }
        
        // Validar nombre único
        validarNombreUnico(nombre, id).then(function(disponible) {
            if (!disponible) {
                $(this).addClass('is-invalid');
                if (!$(this).next('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Este nombre de rol ya existe</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        });
    }
});

// Función para validar nombre único del rol
function validarNombreUnico(nombre, idExcluir = null) {
    return new Promise((resolve) => {
        $.ajax({
            url: 'api/roles.php',
            type: 'GET',
            data: { 
                validar_nombre: nombre,
                excluir_id: idExcluir 
            },
            dataType: 'json',
            success: function(response) {
                resolve(response.disponible);
            },
            error: function() {
                resolve(false);
            }
        });
    });
}

// Función para cambiar estado del rol (activar/desactivar)
function cambiarEstadoRol(id, estadoActual) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const mensaje = nuevoEstado == 1 ? 'activar' : 'desactivar';
    
    if (confirm(`¿Está seguro de que desea ${mensaje} este rol?`)) {
        $.ajax({
            url: 'api/roles.php',
            type: 'PUT',
            data: { 
                id: id,
                activo: nuevoEstado
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    mostrarAlerta(`Rol ${mensaje}do correctamente`, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    mostrarAlerta('Error al cambiar estado del rol: ' + response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Error de conexión al cambiar estado del rol', 'danger');
            }
        });
    }
}

// Función para ver usuarios asignados a un rol
function verUsuariosRol(id, nombreRol) {
    $.ajax({
        url: 'api/roles.php',
        type: 'GET',
        data: { usuarios_rol: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarModalUsuarios(response.data, nombreRol);
            } else {
                mostrarAlerta('Error al cargar usuarios del rol: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar usuarios del rol', 'danger');
        }
    });
}

// Función para mostrar modal de usuarios asignados
function mostrarModalUsuarios(usuarios, nombreRol) {
    let contenido = `
        <div class="modal fade" id="usuariosRolModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-users me-2"></i>Usuarios con Rol: ${nombreRol}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    if (usuarios.length > 0) {
        usuarios.forEach(function(usuario) {
            const estado = usuario.activo == 1 ? 'Activo' : 'Inactivo';
            const colorEstado = usuario.activo == 1 ? 'success' : 'danger';
            contenido += `
                <tr>
                    <td>${usuario.id}</td>
                    <td>${usuario.nombre} ${usuario.apellido}</td>
                    <td>${usuario.email}</td>
                    <td><span class="badge bg-${colorEstado}">${estado}</span></td>
                </tr>
            `;
        });
    } else {
        contenido += `
            <tr>
                <td colspan="4" class="text-center text-muted">No hay usuarios asignados a este rol</td>
            </tr>
        `;
    }
    
    contenido += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior si existe
    $('#usuariosRolModal').remove();
    
    // Agregar nuevo modal al body
    $('body').append(contenido);
    
    // Mostrar modal
    $('#usuariosRolModal').modal('show');
}
