// Inicializar DataTable para usuarios
$(document).ready(function() {
    $('#usuariosTable').DataTable({
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

    // Configurar formulario de usuarios
    $('#usuarioForm').on('submit', function(e) {
        e.preventDefault();
        guardarUsuario();
    });

    // Cargar bancos al inicializar
    cargarBancos();

    // Configurar evento para mostrar/ocultar select de banco cuando cambia el rol
    $(document).on('change', '#rol_id', function() {
        console.log('=== CAMBIO DETECTADO EN ROL ===');
        toggleBancoSelect();
    });

    // Evento cuando se abre el modal
    $('#usuarioModal').on('shown.bs.modal', function() {
        console.log('=== MODAL ABIERTO ===');
        cargarBancos();
        toggleBancoSelect();
    });
});

// Función para limpiar el formulario de usuario
function limpiarFormulario() {
    console.log('=== LIMPIANDO FORMULARIO ===');
    
    $('#usuario_id').val('');
    $('#usuarioForm')[0].reset();
    $('#usuarioModalLabel').html('<i class="fas fa-user-plus me-2"></i>Registrar Usuario');
    $('#password').prop('required', true);
    
    // Ocultar select de banco y limpiar valor
    $('#bancoSection').hide();
    $('#banco_id').val('').prop('required', false);
    
    console.log('✅ Formulario limpiado');
}


// Función para editar usuario
function editarUsuario(id) {
    $.ajax({
        url: 'api/usuarios.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const usuario = response.data;
                
                // Llenar el formulario
                $('#usuario_id').val(usuario.id);
                $('#nombre').val(usuario.nombre);
                $('#apellido').val(usuario.apellido);
                $('#email').val(usuario.email);
                $('#pais').val(usuario.pais || '');
                $('#cargo').val(usuario.cargo || '');
                $('#telefono').val(usuario.telefono || '');
                $('#id_cobrador').val(usuario.id_cobrador || '');
                $('#id_vendedor').val(usuario.id_vendedor || '');
                
                // Configurar checkboxes
                $('#activo').prop('checked', usuario.activo == 1);
                $('#primer_acceso').prop('checked', usuario.primer_acceso == 1);
                
                // Configurar rol (tomar el primer rol del usuario)
                if (usuario.roles && usuario.roles.length > 0) {
                    $('#rol_id').val(usuario.roles[0].rol_id);
                }
                
                // Configurar banco
                $('#banco_id').val(usuario.banco_id || '');
                
                // Mostrar/ocultar select de banco según roles
                toggleBancoSelect();
                
                // Cambiar título del modal
                $('#usuarioModalLabel').html('<i class="fas fa-user-edit me-2"></i>Editar Usuario');
                $('#password').prop('required', false);
                
                // Mostrar modal
                $('#usuarioModal').modal('show');
            } else {
                mostrarAlerta('Error al cargar usuario: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar usuario', 'danger');
        }
    });
}

// Función para eliminar usuario
function eliminarUsuario(id) {
    if (confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
        $.ajax({
            url: 'api/usuarios.php',
            type: 'DELETE',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    mostrarAlerta('Usuario eliminado correctamente', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    mostrarAlerta('Error al eliminar usuario: ' + response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Error de conexión al eliminar usuario', 'danger');
            }
        });
    }
}

// Función para guardar usuario
function guardarUsuario() {
    const usuarioId = $('#usuario_id').val();
    
    console.log('=== GUARDANDO USUARIO ===');
    console.log('Usuario ID:', usuarioId);
    
    // Determinar si es creación o actualización
    const method = usuarioId ? 'PUT' : 'POST';
    
    // Construir objeto de datos
    const dataObj = {
        id: usuarioId || '',
        nombre: $('#nombre').val(),
        apellido: $('#apellido').val(),
        email: $('#email').val(),
        pais: $('#pais').val() || '',
        cargo: $('#cargo').val() || '',
        telefono: $('#telefono').val() || '',
        id_cobrador: $('#id_cobrador').val() || '',
        id_vendedor: $('#id_vendedor').val() || '',
        activo: $('#activo').is(':checked') ? '1' : '0',
        primer_acceso: $('#primer_acceso').is(':checked') ? '1' : '0',
        rol_id: $('#rol_id').val() || '',
        banco_id: $('#banco_id').val() || ''
    };
    
    // Solo agregar password si está presente
    const password = $('#password').val();
    if (password) {
        dataObj.password = password;
    }
    
    console.log('Datos a enviar:', dataObj);
    
    $.ajax({
        url: 'api/usuarios.php',
        type: method,
        data: dataObj,
        processData: true,
        contentType: 'application/x-www-form-urlencoded',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const mensaje = usuarioId ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente';
                mostrarAlerta(mensaje, 'success');
                $('#usuarioModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                mostrarAlerta('Error al guardar usuario: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al guardar usuario:', error);
            console.error('Response:', xhr.responseText);
            mostrarAlerta('Error de conexión al guardar usuario', 'danger');
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

// Función para validar email único
function validarEmailUnico(email, idExcluir = null) {
    return new Promise((resolve) => {
        $.ajax({
            url: 'api/usuarios.php',
            type: 'GET',
            data: { 
                validar_email: email,
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

// Validación en tiempo real del email
$('#email').on('blur', function() {
    const email = $(this).val();
    const id = $('#usuario_id').val();
    
    if (email && email.includes('@')) {
        validarEmailUnico(email, id).then(function(disponible) {
            if (!disponible) {
                $(this).addClass('is-invalid');
                if (!$(this).next('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Este email ya está registrado</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        });
    }
});

// Función para cambiar estado del usuario (activar/desactivar)
function cambiarEstadoUsuario(id, estadoActual) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const mensaje = nuevoEstado == 1 ? 'activar' : 'desactivar';
    
    if (confirm(`¿Está seguro de que desea ${mensaje} este usuario?`)) {
        $.ajax({
            url: 'api/usuarios.php',
            type: 'PUT',
            data: { 
                id: id,
                activo: nuevoEstado
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    mostrarAlerta(`Usuario ${mensaje}do correctamente`, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    mostrarAlerta('Error al cambiar estado del usuario: ' + response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Error de conexión al cambiar estado del usuario', 'danger');
            }
        });
    }
}

// Función para ver roles de un usuario
function verRolesUsuario(id, nombreUsuario) {
    $.ajax({
        url: 'api/usuarios.php',
        type: 'GET',
        data: { roles_usuario: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarModalRoles(response.data, nombreUsuario);
            } else {
                mostrarAlerta('Error al cargar roles del usuario: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al cargar roles del usuario', 'danger');
        }
    });
}

// Función para mostrar modal de roles del usuario
function mostrarModalRoles(roles, nombreUsuario) {
    let contenido = `
        <div class="modal fade" id="rolesUsuarioModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-shield me-2"></i>Roles de: ${nombreUsuario}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Rol</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    if (roles.length > 0) {
        roles.forEach(function(rol) {
            const estado = rol.activo == 1 ? 'Activo' : 'Inactivo';
            const colorEstado = rol.activo == 1 ? 'success' : 'danger';
            contenido += `
                <tr>
                    <td>${rol.rol_id}</td>
                    <td>${rol.nombre}</td>
                    <td>${rol.descripcion || '-'}</td>
                    <td><span class="badge bg-${colorEstado}">${estado}</span></td>
                </tr>
            `;
        });
    } else {
        contenido += `
            <tr>
                <td colspan="4" class="text-center text-muted">Este usuario no tiene roles asignados</td>
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
    $('#rolesUsuarioModal').remove();
    
    // Agregar nuevo modal al body
    $('body').append(contenido);
    
    // Mostrar modal
    $('#rolesUsuarioModal').modal('show');
}

// Función para cargar bancos
function cargarBancos() {
    console.log('=== CARGANDO BANCOS ===');
    
    $.ajax({
        url: 'api/usuarios.php',
        type: 'GET',
        data: { bancos: true },
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta de bancos:', response);
            if (response.success) {
                const select = $('#banco_id');
                select.empty();
                select.append('<option value="">Seleccionar banco...</option>');
                
                console.log('Bancos recibidos:', response.data);
                response.data.forEach(function(banco) {
                    console.log('Agregando banco:', banco);
                    select.append(`<option value="${banco.id}">${banco.nombre} (${banco.codigo})</option>`);
                });
                
                console.log('✅ Bancos cargados correctamente');
            } else {
                console.error('Error en respuesta de bancos:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX al cargar bancos:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
        }
    });
}

// Función para mostrar/ocultar select de banco
function toggleBancoSelect() {
    console.log('=== EJECUTANDO TOGGLE BANCO SELECT ===');
    
    const bancoSection = $('#bancoSection');
    const rolId = $('#rol_id').val();
    
    console.log('Rol seleccionado:', rolId);
    
    // Verificar si es ROLE_BANCO (ID 8)
    if (rolId == '8') {
        bancoSection.show();
        $('#banco_id').prop('required', true);
        console.log('✅ Mostrando select de banco');
        
        // Asegurar que los bancos estén cargados
        if ($('#banco_id option').length <= 1) {
            console.log('Cargando bancos...');
            cargarBancos();
        }
    } else {
        bancoSection.hide();
        $('#banco_id').prop('required', false);
        $('#banco_id').val('');
        console.log('❌ Ocultando select de banco');
    }
}
