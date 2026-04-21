document.addEventListener('DOMContentLoaded', function() {
    // 1. Elements
    const cedulaInput = document.getElementById('cedulaInput');
    const imagePreview = document.getElementById('imagePreview');
    const uploadIcon = document.getElementById('uploadIcon');
    const uploadText = document.getElementById('uploadText');
    const idForm = document.getElementById('idForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const resultsCard = document.getElementById('resultsCard');
    const extractedData = document.getElementById('extractedData');

    // UI elements for new flow
    const photoActionsContainer = document.getElementById('photoActionsContainer');
    const editDocBtn = document.getElementById('editDocBtn');
    const extractSignatureBtn = document.getElementById('extractSignatureBtn');
    const cedulaEditadaInput = document.getElementById('cedulaEditadaInput');
    const firmaExtraidaInput = document.getElementById('firmaExtraidaInput');
    const croppedResultContainer = document.getElementById('croppedResultContainer');
    const tracingBackground = document.getElementById('tracingBackground');

    // Cropper Elements
    const cropperModalElement = document.getElementById('cropperModal');
    const cropperModal = new bootstrap.Modal(cropperModalElement);
    const cropperModalLabel = document.getElementById('cropperModalLabel');
    const cropperHelperText = document.getElementById('cropperHelperText');
    const imageToCrop = document.getElementById('imageToCrop');
    const cropBtn = document.getElementById('cropBtn');
    const rotateActions = document.getElementById('rotateActions');
    const rotateLeftBtn = document.getElementById('rotateLeftBtn');
    const rotateRightBtn = document.getElementById('rotateRightBtn');
    
    let cropper;
    let cropperMode = 'document'; // 'document' or 'signature'
    let originalImageSrc = ''; // To store the unedited camera capture

    // 2. Initialize Signature Pad
    const canvas = document.getElementById('signaturePad');
    function resizeCanvas() {
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    const signaturePad = new SignaturePad(canvas, {
        penColor: "rgb(0, 0, 100)", // Dark blue ink
        backgroundColor: "rgba(0,0,0,0)", // Transparent background
        minWidth: 1.5,
        maxWidth: 3
    });

    document.getElementById('clearSignature').addEventListener('click', () => {
        signaturePad.clear();
    });

    // 3. Image Capture Logic
    cedulaInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.match('image.*')) {
                Swal.fire('Error', 'Por favor selecciona una imagen válida.', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                originalImageSrc = e.target.result;
                
                // Show raw image temporarily
                imagePreview.src = originalImageSrc;
                imagePreview.classList.remove('d-none');
                uploadIcon.classList.add('d-none');
                uploadText.classList.add('d-none');
                photoActionsContainer.classList.remove('d-none');
                
                // Automatically open the editor for the document
                openCropper('document');
            }
            reader.readAsDataURL(file);
        }
    });

    // Action Buttons
    editDocBtn.addEventListener('click', () => openCropper('document'));
    extractSignatureBtn.addEventListener('click', () => openCropper('signature'));

    // Cropper Setup Function
    function openCropper(mode) {
        cropperMode = mode;
        
        if (cropperMode === 'document') {
            cropperModalLabel.innerHTML = '<i class="fas fa-crop me-2"></i>Recortar y Girar Documento';
            cropperHelperText.innerHTML = 'Ajusta el recuadro para que solo se vea la cédula. Usa los botones superiores para girar la foto si es necesario.';
            rotateActions.classList.remove('d-none');
            // Edit the original raw image to prevent quality loss from multiple edits
            imageToCrop.src = originalImageSrc; 
        } else {
            cropperModalLabel.innerHTML = '<i class="fas fa-signature me-2"></i>Recortar Firma';
            cropperHelperText.innerHTML = 'Encuadra **únicamente la firma** que aparece en la foto. Esto creará una marca de agua transparente abajo para que puedas calcarla.';
            rotateActions.classList.add('d-none');
            // Extract signature from the already edited document
            imageToCrop.src = imagePreview.src; 
        }
        
        cropperModal.show();
    }

    // Modal Events
    cropperModalElement.addEventListener('shown.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
        }
        
        const autoCropRatio = cropperMode === 'document' ? 0.9 : 0.2;
        
        cropper = new Cropper(imageToCrop, {
            viewMode: 1,
            autoCropArea: autoCropRatio,
            guides: true,
            background: false,
            responsive: true,
            rotatable: true
        });
    });

    // Rotation Actions
    rotateLeftBtn.addEventListener('click', () => { if(cropper) cropper.rotate(-90); });
    rotateRightBtn.addEventListener('click', () => { if(cropper) cropper.rotate(90); });

    // Apply Crop
    cropBtn.addEventListener('click', function() {
        if (!cropper) return;
        
        const canvas = cropper.getCroppedCanvas({
            // Optional: limit max resolution to save bandwidth
            maxWidth: 2000,
            maxHeight: 2000
        });
        
        if (canvas) {
            // Use JPEG for document to save space, PNG for signature to keep it clean (though no alpha channel from crop usually)
            const format = cropperMode === 'document' ? 'image/jpeg' : 'image/png';
            const quality = cropperMode === 'document' ? 0.8 : 1.0;
            const croppedImageDataUrl = canvas.toDataURL(format, quality);
            
            if (cropperMode === 'document') {
                imagePreview.src = croppedImageDataUrl;
                cedulaEditadaInput.value = croppedImageDataUrl; // Save base64
            } else {
                firmaExtraidaInput.value = croppedImageDataUrl;
                // Set as tracing background
                tracingBackground.src = croppedImageDataUrl;
                tracingBackground.classList.remove('d-none');
                croppedResultContainer.classList.remove('d-none');
            }
            
            cropperModal.hide();
        }
    });


    // 4. Form Submission
    idForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validations
        if (!cedulaEditadaInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta la Cédula',
                text: 'Por favor toma la foto de tu cédula y recórtala.'
            });
            return;
        }

        if (signaturePad.isEmpty()) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta la Firma',
                text: 'Por favor dibuja/calca tu firma en el recuadro inferior.'
            });
            return;
        }

        // Prepare data
        const formData = new FormData();
        
        // We now send the edited base64 instead of the file
        formData.append('cedula_editada', cedulaEditadaInput.value);
        
        // Get signature as base64 png
        const signatureData = signaturePad.toDataURL('image/png');
        formData.append('firma', signatureData);
        
        // Extracted signature from ID
        if (firmaExtraidaInput.value) {
            formData.append('firma_extraida', firmaExtraidaInput.value);
        }

        // UI Changes
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
        loadingOverlay.classList.remove('d-none');
        resultsCard.classList.add('d-none');

        // Send to backend
        fetch('procesar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i>Procesar Datos';
            loadingOverlay.classList.add('d-none');

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Verificación Exitosa!',
                    text: 'Los datos y la firma han sido guardados correctamente.',
                    confirmButtonColor: '#198754'
                });

                // Display results
                let html = '<ul class="list-group list-group-flush">';
                
                if (data.parsed_data && Object.keys(data.parsed_data).length > 0) {
                    for (const [key, value] of Object.entries(data.parsed_data)) {
                        const formattedKey = key.charAt(0).toUpperCase() + key.slice(1);
                        html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="text-muted">${formattedKey}</span>
                                    <span class="fw-bold text-dark text-end">${value}</span>
                                 </li>`;
                    }
                } else if (data.raw_text) {
                    html += `<li class="list-group-item">
                                <h6 class="text-muted mb-2">Texto Crudo Extraído (Google Vision):</h6>
                                <pre class="bg-light p-3 rounded border" style="font-size: 0.85rem; white-space: pre-wrap; color: #555; max-height: 200px; overflow-y: auto;">${data.raw_text}</pre>
                             </li>`;
                } else {
                     html += `<li class="list-group-item text-center text-muted">No se pudo extraer texto claro de la imagen.</li>`;
                }
                
                // Show paths where files were saved
                html += `<li class="list-group-item bg-light mt-3 border-top">
                            <small class="text-muted d-block"><i class="fas fa-folder-open me-1"></i>Archivos Guardados:</small>
                            <small class="text-primary d-block text-break">${data.cedula_path || 'No guardada'}</small>
                            <small class="text-primary d-block text-break">${data.firma_path || 'No guardada'}</small>
                            ${data.firma_extraida_path ? `<small class="text-success d-block text-break">${data.firma_extraida_path}</small>` : ''}
                         </li>`;

                html += '</ul>';
                extractedData.innerHTML = html;
                resultsCard.classList.remove('d-none');
                
                resultsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al procesar',
                    text: data.message || 'Ocurrió un problema en el servidor.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i>Procesar Datos';
            loadingOverlay.classList.add('d-none');
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo contactar con el servidor. Verifica tu conexión.'
            });
        });
    });
});
