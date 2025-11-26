<?php
/**
 * Servicio de envío de correos electrónicos usando SendGrid
 * 
 * Utiliza SendGrid API para el envío de correos
 * Incluye templates HTML para diferentes tipos de notificaciones
 */

// Cargar autoload de Composer si existe
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die('Error: Composer no está instalado. Ejecuta: composer install');
}

use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;
use SendGrid\Mail\Attachment;
use SendGrid;

class EmailService {
    private $config;
    private $sendgrid;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
        
        // Inicializar SendGrid
        try {
            $this->sendgrid = new SendGrid($this->config['sendgrid_api_key']);
        } catch (Exception $e) {
            error_log("Error al inicializar SendGrid: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Envía un correo genérico
     * 
     * @param string $to Email del destinatario
     * @param string $toName Nombre del destinatario
     * @param string $subject Asunto del correo
     * @param string $bodyHTML Cuerpo del correo en HTML
     * @param string $bodyText Cuerpo del correo en texto plano (opcional)
     * @param array $attachments Array de rutas de archivos adjuntos (opcional)
     * @return array ['success' => bool, 'message' => string]
     */
    public function enviarCorreo(
        $to,
        $toName = '',
        $subject,
        $bodyHTML,
        $bodyText = '',
        $attachments = []
    ) {
        try {
            // Crear objeto Mail de SendGrid
            $email = new Mail();
            
            // Remitente
            $email->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
            
            // Destinatario
            $email->addTo($to, $toName ?: '');
            
            // Reply-To
            $email->setReplyTo(
                $this->config['reply_to_email'],
                $this->config['reply_to_name']
            );
            
            // Asunto y contenido
            $email->setSubject($subject);
            $email->addContent("text/html", $bodyHTML);
            
            // Texto plano (opcional)
            if ($bodyText) {
                $email->addContent("text/plain", $bodyText);
            } else {
                $email->addContent("text/plain", strip_tags($bodyHTML));
            }
            
            // Adjuntos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $fileContent = base64_encode(file_get_contents($attachment));
                    $fileName = basename($attachment);
                    $mimeType = function_exists('mime_content_type') 
                        ? mime_content_type($attachment) 
                        : 'application/octet-stream';
                    
                    $attachment_obj = new Attachment();
                    $attachment_obj->setContent($fileContent);
                    $attachment_obj->setType($mimeType);
                    $attachment_obj->setFilename($fileName);
                    $attachment_obj->setDisposition("attachment");
                    
                    $email->addAttachment($attachment_obj);
                }
            }
            
            // Enviar correo
            $response = $this->sendgrid->send($email);
            
            // Verificar respuesta
            $statusCode = $response->statusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'message' => 'Correo enviado correctamente',
                    'status_code' => $statusCode
                ];
            } else {
                $body = $response->body();
                error_log("SendGrid error: Status $statusCode - $body");
                return [
                    'success' => false,
                    'message' => "Error al enviar correo: Status $statusCode",
                    'status_code' => $statusCode,
                    'response' => $body
                ];
            }
            
        } catch (TypeException $e) {
            error_log("Error de tipo en SendGrid: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Error al enviar correo con SendGrid: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envía correo usando un template
     * 
     * @param string $to Email del destinatario
     * @param string $toName Nombre del destinatario
     * @param string $template Nombre del template (sin extensión)
     * @param array $data Datos para reemplazar en el template
     * @return array ['success' => bool, 'message' => string]
     */
    public function enviarTemplate($to, $toName, $template, $data = []) {
        $templatePath = __DIR__ . '/../templates/email/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            return [
                'success' => false,
                'message' => "Template no encontrado: {$template}"
            ];
        }
        
        // Extraer variables del array $data para usarlas en el template
        extract($data);
        
        // Capturar el contenido del template
        ob_start();
        include $templatePath;
        $bodyHTML = ob_get_clean();
        
        // Generar texto plano desde HTML
        $bodyText = strip_tags($bodyHTML);
        
        // Obtener asunto del template si está definido
        $subject = $data['subject'] ?? 'Notificación de Automarket Rent a Car';
        
        return $this->enviarCorreo($to, $toName, $subject, $bodyHTML, $bodyText);
    }
    
    /**
     * Notifica al vendedor cuando el banco responde
     */
    public function notificarVendedorBancoResponde($vendedorEmail, $vendedorNombre, $solicitud) {
        return $this->enviarTemplate(
            $vendedorEmail,
            $vendedorNombre,
            'notificacion_banco_responde',
            [
                'subject' => 'Respuesta del Banco - Solicitud #' . $solicitud['id'],
                'vendedor_nombre' => $vendedorNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url']
            ]
        );
    }
    
    /**
     * Envía recordatorio al banco sobre una solicitud pendiente
     */
    public function enviarRecordatorioBanco($bancoEmail, $bancoNombre, $solicitud) {
        return $this->enviarTemplate(
            $bancoEmail,
            $bancoNombre,
            'recordatorio_banco',
            [
                'subject' => 'Recordatorio: Solicitud Pendiente #' . $solicitud['id'],
                'banco_nombre' => $bancoNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url']
            ]
        );
    }
    
    /**
     * Notifica al banco cuando se le asigna una nueva solicitud
     */
    public function notificarBancoNuevaSolicitud($bancoEmail, $bancoNombre, $solicitud) {
        return $this->enviarTemplate(
            $bancoEmail,
            $bancoNombre,
            'notificacion_nueva_solicitud',
            [
                'subject' => 'Nueva Solicitud Asignada #' . $solicitud['id'],
                'banco_nombre' => $bancoNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url']
            ]
        );
    }
    
    /**
     * Notifica al cliente cuando su solicitud es aprobada
     */
    public function notificarClienteAprobacion($clienteEmail, $clienteNombre, $solicitud) {
        return $this->enviarTemplate(
            $clienteEmail,
            $clienteNombre,
            'notificacion_cliente_aprobacion',
            [
                'subject' => '¡Felicidades! Su solicitud de crédito ha sido aprobada',
                'cliente_nombre' => $clienteNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url']
            ]
        );
    }
    
    /**
     * Notifica al gestor sobre cambios importantes en la solicitud
     */
    public function notificarGestorCambioEstado($gestorEmail, $gestorNombre, $solicitud, $estadoAnterior, $estadoNuevo) {
        return $this->enviarTemplate(
            $gestorEmail,
            $gestorNombre,
            'notificacion_cambio_estado',
            [
                'subject' => 'Cambio de Estado - Solicitud #' . $solicitud['id'],
                'gestor_nombre' => $gestorNombre,
                'solicitud' => $solicitud,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'app_url' => $this->config['app_url']
            ]
        );
    }
    
    /**
     * Notifica cuando se solicita una reevaluación
     */
    public function notificarReevaluacion($bancoEmail, $bancoNombre, $solicitud, $comentario) {
        return $this->enviarTemplate(
            $bancoEmail,
            $bancoNombre,
            'notificacion_reevaluacion',
            [
                'subject' => 'Solicitud de Reevaluación - Solicitud #' . $solicitud['id'],
                'banco_nombre' => $bancoNombre,
                'solicitud' => $solicitud,
                'comentario' => $comentario,
                'app_url' => $this->config['app_url']
            ]
        );
    }
}
