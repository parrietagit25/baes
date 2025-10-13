// ==================== FUNCIONES DE ADJUNTOS ====================

// Función para mostrar alertas
window.mostrarAlerta = function(mensaje, tipo = 'info') {
    // Crear el HTML de la alerta
    const alertaHtml = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Insertar la alerta al inicio del contenedor principal
    const container = $('.container-fluid').first();
    if (container.length === 0) {
        // Si no hay contenedor, usar el body
        $('body').prepend(alertaHtml);
    } else {
        container.prepend(alertaHtml);
    }
    
    // Auto-ocultar después de 5 segundos
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
};

// Función para subir adjunto (definida globalmente)
window.subirAdjunto = function() {
    console.log('=== INICIANDO SUBIDA DE ADJUNTO ===');
    
    const solicitudId = $('#adjunto_solicitud_id').val();
    
    console.log('Solicitud ID:', solicitudId);
    console.log('Campo adjunto_solicitud_id existe:', $('#adjunto_solicitud_id').length > 0);
    console.log('Valor del campo:', $('#adjunto_solicitud_id').val());
    
    // Validar que se seleccionó un archivo
    const archivo = $('#archivo_adjunto')[0].files[0];
    if (!archivo) {
        console.error('No se seleccionó archivo');
        mostrarAlerta('Por favor selecciona un archivo', 'warning');
        return;
    }
    
    console.log('Archivo seleccionado:', archivo.name, 'Tamaño:', archivo.size);
    
    // Construir FormData manualmente
    const formData = new FormData();
    formData.append('solicitud_id', solicitudId);
    formData.append('archivo', archivo);
    formData.append('descripcion', $('#descripcion_adjunto').val() || '');
    
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    if (!solicitudId) {
        console.error('No hay ID de solicitud');
        mostrarAlerta('Error: No se ha seleccionado una solicitud', 'danger');
        return;
    }
    
    // Validar tamaño (máximo 10MB)
    if (archivo.size > 10 * 1024 * 1024) {
        console.error('Archivo demasiado grande:', archivo.size);
        mostrarAlerta('El archivo es demasiado grande. Máximo 10MB', 'warning');
        return;
    }
    
    console.log('Enviando petición AJAX...');
    
    $.ajax({
        url: 'api/adjuntos.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta recibida:', response);
            if (response.success) {
                mostrarAlerta('Archivo subido correctamente', 'success');
                // Limpiar formulario
                const form = document.getElementById('adjuntoForm');
                if (form) {
                    form.reset();
                }
                cargarAdjuntos(solicitudId);
            } else {
                console.error('Error en respuesta:', response.message);
                mostrarAlerta('Error al subir archivo: ' + response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', status, error);
            console.error('Response text:', xhr.responseText);
            mostrarAlerta('Error de conexión al subir archivo: ' + error, 'danger');
        }
    });
};

// Función para cargar adjuntos de una solicitud
window.cargarAdjuntos = function(solicitudId) {
    console.log('=== CARGANDO ADJUNTOS ===');
    console.log('Solicitud ID:', solicitudId);
    
    $.ajax({
        url: 'api/adjuntos.php',
        type: 'GET',
        data: { solicitud_id: solicitudId },
        dataType: 'json',
        success: function(response) {
            console.log('Adjuntos recibidos:', response);
            if (response.success) {
                mostrarAdjuntos(response.data);
            } else {
                console.error('Error al cargar adjuntos:', response.message);
                $('#adjuntosContainer').html('<div class="text-center text-muted">Error al cargar adjuntos</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX al cargar adjuntos:', status, error);
            $('#adjuntosContainer').html('<div class="text-center text-muted">Error de conexión</div>');
        }
    });
};

// Función para mostrar la lista de adjuntos
window.mostrarAdjuntos = function(adjuntos) {
    console.log('Mostrando adjuntos:', adjuntos);
    
    if (!adjuntos || adjuntos.length === 0) {
        $('#adjuntosContainer').html('<div class="text-center text-muted">No hay archivos adjuntos</div>');
        return;
    }
    
    let html = '';
    adjuntos.forEach(function(adjunto) {
        const icono = obtenerIconoArchivo(adjunto.tipo_archivo);
        const tamaño = formatearTamaño(adjunto.tamaño_archivo);
        const fecha = new Date(adjunto.fecha_subida).toLocaleDateString('es-ES');
        
        html += `
            <div class="adjunto-item d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                <div class="d-flex align-items-center">
                    <i class="${icono} me-2 text-primary"></i>
                    <div>
                        <div class="fw-bold">${adjunto.nombre_original}</div>
                        <small class="text-muted">${tamaño} • ${fecha}</small>
                        ${adjunto.descripcion ? `<div class="text-muted small">${adjunto.descripcion}</div>` : ''}
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="descargarAdjunto(${adjunto.id})" title="Descargar">
                        <i class="fas fa-download"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunto(${adjunto.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    $('#adjuntosContainer').html(html);
};

// Función para obtener el icono según el tipo de archivo
window.obtenerIconoArchivo = function(tipo) {
    const iconos = {
        'application/pdf': 'fas fa-file-pdf text-danger',
        'application/msword': 'fas fa-file-word text-primary',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fas fa-file-word text-primary',
        'application/vnd.ms-excel': 'fas fa-file-excel text-success',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fas fa-file-excel text-success',
        'image/jpeg': 'fas fa-file-image text-info',
        'image/jpg': 'fas fa-file-image text-info',
        'image/png': 'fas fa-file-image text-info',
        'image/gif': 'fas fa-file-image text-info',
        'text/plain': 'fas fa-file-alt text-secondary'
    };
    return iconos[tipo] || 'fas fa-file text-muted';
};

// Función para formatear el tamaño del archivo
window.formatearTamaño = function(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Función para descargar un adjunto
window.descargarAdjunto = function(id) {
    console.log('Descargando adjunto ID:', id);
    
    // Usar window.open para forzar la descarga
    const url = 'api/adjuntos.php?action=descargar&id=' + id;
    console.log('URL de descarga:', url);
    
    // Abrir en nueva ventana para forzar descarga
    window.open(url, '_blank');
};

// Función para eliminar un adjunto
window.eliminarAdjunto = function(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar este archivo?')) {
        return;
    }
    
    $.ajax({
        url: 'api/adjuntos.php?id=' + id,
        type: 'DELETE',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarAlerta('Archivo eliminado correctamente', 'success');
                // Recargar la lista de adjuntos
                const solicitudId = $('#adjunto_solicitud_id').val();
                if (solicitudId) {
                    cargarAdjuntos(solicitudId);
                }
            } else {
                mostrarAlerta('Error al eliminar archivo: ' + response.message, 'danger');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión al eliminar archivo', 'danger');
        }
    });
};

// Función para configurar adjuntos cuando se abre el modal de edición
window.configurarAdjuntos = function(solicitudId) {
    $('#adjunto_solicitud_id').val(solicitudId);
    cargarAdjuntos(solicitudId);
};
