// Inicializar DataTable
$(document).ready(function() {
    $('#usuariosTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]]
    });

    // Configurar formulario de usuarios
    $('#usuarioForm').on('submit', function(e) {
        e.preventDefault();
        guardarUsuario();
    });


});

// Función para limpiar el formulario de usuario
function limpiarFormulario() {
    $('#usuario_id').val('');
    $('#usuarioForm')[0].reset();
    $('#usuarioModalLabel').html('<i class="fas fa-user-plus me-2"></i>Registrar Usuario');
    $('#password').prop('required', true);
    
    // Desmarcar todos los roles
    $('input[name="roles[]"]').prop('checked', false);
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
    
    $.ajax({
        url: 'api/usuarios.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Usuario guardado correctamente', 'success');
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
