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

// Cargar SendGrid - compatible con versiones antiguas y nuevas
// Si es versión antigua, cargar manualmente
if (file_exists(__DIR__ . '/../vendor/sendgrid/sendgrid/lib/SendGrid.php') && !class_exists('SendGrid\Mail\Mail')) {
    require_once __DIR__ . '/../vendor/sendgrid/sendgrid/lib/SendGrid.php';
}

class EmailService {
    private $config;
    private $sendgrid;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
        
        // Inicializar SendGrid
        try {
            $this->sendgrid = new \SendGrid($this->config['sendgrid_api_key']);
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
            // Detectar versión de SendGrid y usar la API apropiada
            // Primero verificar si existe la clase nueva
            $tieneV7 = class_exists('SendGrid\Mail\Mail');
            
            if ($tieneV7) {
                // Versión nueva de SendGrid (v7+)
                return $this->enviarCorreoV7($to, $toName, $subject, $bodyHTML, $bodyText, $attachments);
            } else {
                // Versión antigua de SendGrid (v6 o anterior)
                return $this->enviarCorreoV6($to, $toName, $subject, $bodyHTML, $bodyText, $attachments);
            }
        } catch (Exception $e) {
            error_log("Error al enviar correo con SendGrid: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envío usando SendGrid v7+ (API moderna)
     */
    private function enviarCorreoV7($to, $toName, $subject, $bodyHTML, $bodyText, $attachments) {
        // Verificar que la clase existe antes de usarla
        if (!class_exists('SendGrid\Mail\Mail')) {
            throw new Exception('SendGrid v7 no está disponible, pero se intentó usar enviarCorreoV7');
        }
        
        $email = new \SendGrid\Mail\Mail();
        
        $email->setFrom($this->config['from_email'], $this->config['from_name']);
        $email->addTo($to, $toName ?: '');
        $email->setReplyTo($this->config['reply_to_email'], $this->config['reply_to_name']);
        $email->setSubject($subject);
        $email->addContent("text/html", $bodyHTML);
        $email->addContent("text/plain", $bodyText ?: strip_tags($bodyHTML));
        
        // Adjuntos
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $fileContent = base64_encode(file_get_contents($attachment));
                $fileName = basename($attachment);
                $mimeType = function_exists('mime_content_type') 
                    ? mime_content_type($attachment) 
                    : 'application/octet-stream';
                
                $attachment_obj = new \SendGrid\Mail\Attachment();
                $attachment_obj->setContent($fileContent);
                $attachment_obj->setType($mimeType);
                $attachment_obj->setFilename($fileName);
                $attachment_obj->setDisposition("attachment");
                $email->addAttachment($attachment_obj);
            }
        }
        
        $response = $this->sendgrid->send($email);
        $statusCode = $response->statusCode();
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['success' => true, 'message' => 'Correo enviado correctamente', 'status_code' => $statusCode];
        } else {
            $body = $response->body();
            error_log("SendGrid error: Status $statusCode - $body");
            return ['success' => false, 'message' => "Error al enviar correo: Status $statusCode", 'status_code' => $statusCode];
        }
    }
    
    /**
     * Envío usando SendGrid v6 o anterior (API antigua)
     */
    private function enviarCorreoV6($to, $toName, $subject, $bodyHTML, $bodyText, $attachments) {
        // Suprimir warnings de deprecación de SendGrid
        $errorLevel = error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ob_start();
        
        try {
            // Verificar que las clases de la versión antigua existen
            if (!class_exists('SendGrid\Email')) {
                // Intentar cargar manualmente
                $sendgridPath = __DIR__ . '/../vendor/sendgrid/sendgrid/lib/SendGrid.php';
                if (file_exists($sendgridPath)) {
                    require_once $sendgridPath;
                }
                
                if (!class_exists('SendGrid\Email')) {
                    throw new Exception('No se pudo cargar SendGrid. Verifica que esté instalado correctamente.');
                }
            }
            
            // Usar la API antigua de SendGrid
            $from = new \SendGrid\Email($this->config['from_name'], $this->config['from_email']);
            $to_email = new \SendGrid\Email($toName ?: '', $to);
            $content_html = new \SendGrid\Content("text/html", $bodyHTML);
            $content_text = new \SendGrid\Content("text/plain", $bodyText ?: strip_tags($bodyHTML));
            
            $mail = new \SendGrid\Mail($from, $subject, $to_email, $content_text);
            $mail->addContent($content_html);
            
            // Reply-To
            if (!empty($this->config['reply_to_email'])) {
                $replyTo = new \SendGrid\Email($this->config['reply_to_name'], $this->config['reply_to_email']);
                $mail->setReplyTo($replyTo);
            }
            
            // Adjuntos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $fileContent = base64_encode(file_get_contents($attachment));
                    $fileName = basename($attachment);
                    $mimeType = function_exists('mime_content_type') 
                        ? mime_content_type($attachment) 
                        : 'application/octet-stream';
                    
                    $attachment_obj = new \SendGrid\Attachment();
                    $attachment_obj->setContent($fileContent);
                    $attachment_obj->setType($mimeType);
                    $attachment_obj->setFilename($fileName);
                    $attachment_obj->setDisposition("attachment");
                    $mail->addAttachment($attachment_obj);
                }
            }
            
            $response = $this->sendgrid->client->mail()->send()->post($mail);
            $statusCode = $response->statusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $resultado = ['success' => true, 'message' => 'Correo enviado correctamente', 'status_code' => $statusCode];
            } else {
                $body = $response->body();
                error_log("SendGrid error: Status $statusCode - $body");
                $resultado = ['success' => false, 'message' => "Error al enviar correo: Status $statusCode", 'status_code' => $statusCode];
            }
        } finally {
            // Limpiar cualquier salida de warnings
            ob_end_clean();
            error_reporting($errorLevel);
        }
        
        return $resultado;
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
