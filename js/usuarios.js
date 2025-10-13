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

    // Configurar evento para mostrar/ocultar select de banco
    $(document).on('change', 'input[name="roles[]"]', function() {
        console.log('=== CAMBIO DETECTADO EN ROLES ===');
        console.log('Checkbox cambiado:', this);
        console.log('Valor:', $(this).val());
        console.log('Checked:', $(this).is(':checked'));
        console.log('Label text:', $(this).next('label').text());
        
        // Ejecutar inmediatamente y también con delay
        toggleBancoSelect();
        
        setTimeout(function() {
            console.log('Ejecutando toggleBancoSelect después del delay');
            toggleBancoSelect();
        }, 100);
    });
    
    // También configurar evento click para mayor compatibilidad
    $(document).on('click', 'input[name="roles[]"]', function() {
        console.log('=== CLICK DETECTADO EN ROLES ===');
        setTimeout(function() {
            toggleBancoSelect();
        }, 50);
    });

    // Evento cuando se abre el modal
    $('#usuarioModal').on('shown.bs.modal', function() {
        console.log('=== MODAL ABIERTO ===');
        cargarBancos();
        setTimeout(function() {
            toggleBancoSelect();
        }, 100);
    });

    // Evento específico para el checkbox de ROLE_BANCO (ID 8)
    $(document).on('change', '#rol_8', function() {
        console.log('=== EVENTO ESPECÍFICO ROLE_BANCO ===');
        console.log('Checkbox ROLE_BANCO cambiado:', this);
        console.log('Valor:', $(this).val());
        console.log('Checked:', $(this).is(':checked'));
        
        if ($(this).is(':checked')) {
            console.log('✅ ROLE_BANCO seleccionado - Mostrando select de banco');
            $('#bancoSection').show();
            $('#banco_id').prop('required', true);
        } else {
            console.log('❌ ROLE_BANCO deseleccionado - Ocultando select de banco');
            $('#bancoSection').hide();
            $('#banco_id').prop('required', false);
            $('#banco_id').val('');
        }
    });

    // Evento específico para el checkbox de ROLE_BANCO con click también
    $(document).on('click', '#rol_8', function() {
        console.log('=== CLICK ESPECÍFICO ROLE_BANCO ===');
        setTimeout(function() {
            if ($('#rol_8').is(':checked')) {
                console.log('✅ ROLE_BANCO click - Mostrando select de banco');
                $('#bancoSection').show();
                $('#banco_id').prop('required', true);
            } else {
                console.log('❌ ROLE_BANCO click - Ocultando select de banco');
                $('#bancoSection').hide();
                $('#banco_id').prop('required', false);
                $('#banco_id').val('');
            }
        }, 50);
    });
});

// Función para limpiar el formulario de usuario
function limpiarFormulario() {
    console.log('=== LIMPIANDO FORMULARIO ===');
    
    $('#usuario_id').val('');
    $('#usuarioForm')[0].reset();
    $('#usuarioModalLabel').html('<i class="fas fa-user-plus me-2"></i>Registrar Usuario');
    $('#password').prop('required', true);
    
    // Desmarcar todos los roles
    $('input[name="roles[]"]').prop('checked', false);
    
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
                
                // Limpiar y marcar roles
                $('input[name="roles[]"]').prop('checked', false);
                if (usuario.roles) {
                    usuario.roles.forEach(function(rol) {
                        $(`input[name="roles[]"][value="${rol.rol_id}"]`).prop('checked', true);
                    });
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
    const formData = new FormData($('#usuarioForm')[0]);
    const usuarioId = $('#usuario_id').val();
    
    console.log('=== GUARDANDO USUARIO ===');
    console.log('Usuario ID:', usuarioId);
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Determinar si es creación o actualización
    const method = usuarioId ? 'PUT' : 'POST';
    
    // Para PUT, necesitamos enviar los datos como string, no como FormData
    let dataToSend;
    if (method === 'PUT') {
        // Convertir FormData a objeto y luego a string
        const dataObj = {};
        for (let [key, value] of formData.entries()) {
            if (dataObj[key]) {
                // Si ya existe, convertir a array
                if (Array.isArray(dataObj[key])) {
                    dataObj[key].push(value);
                } else {
                    dataObj[key] = [dataObj[key], value];
                }
            } else {
                dataObj[key] = value;
            }
        }
        dataToSend = $.param(dataObj);
        console.log('Datos PUT:', dataToSend);
    } else {
        dataToSend = formData;
    }
    
    $.ajax({
        url: 'api/usuarios.php',
        type: method,
        data: dataToSend,
        processData: method === 'POST',
        contentType: method === 'POST' ? false : 'application/x-www-form-urlencoded',
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
        error: function() {
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
    console.log('Banco section encontrado:', bancoSection.length);
    
    let hasBancoRole = false;
    let rolesSeleccionados = [];
    
    // Verificar cada checkbox de rol marcado
    $('input[name="roles[]"]:checked').each(function() {
        const rolId = $(this).val();
        const labelText = $(this).next('label').text().toUpperCase();
        
        rolesSeleccionados.push({
            id: rolId,
            text: labelText,
            checked: $(this).is(':checked')
        });
        
        console.log('Rol seleccionado:', rolId, labelText);
        
        // Verificar si es ROLE_BANCO (por ID o por texto)
        if (rolId == '8' || labelText.includes('ROLE_BANCO') || labelText.includes('BANCO')) {
            hasBancoRole = true;
            console.log('✅ ROL BANCO DETECTADO!');
        }
    });
    
    console.log('Roles seleccionados:', rolesSeleccionados);
    console.log('Tiene rol de banco:', hasBancoRole);
    
    if (hasBancoRole) {
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
