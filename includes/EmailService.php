<?php
/**
 * Servicio de envío de correos electrónicos
 * 
 * Utiliza PHPMailer para el envío de correos con soporte SMTP
 * Incluye templates HTML para diferentes tipos de notificaciones
 */

// Cargar autoload de Composer si existe
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die('Error: Composer no está instalado. Ejecuta: composer install');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $config;
    private $mailer;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
        $this->mailer = new PHPMailer(true);
        $this->configurarMailer();
    }
    
    /**
     * Configura PHPMailer con los parámetros SMTP
     */
    private function configurarMailer() {
        try {
            // Configuración del servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_secure'];
            $this->mailer->Port = $this->config['smtp_port'];
            $this->mailer->CharSet = 'UTF-8';
            
            // Remitente
            $this->mailer->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
            
            // Reply-To
            $this->mailer->addReplyTo(
                $this->config['reply_to_email'],
                $this->config['reply_to_name']
            );
            
            // Debug (solo en desarrollo)
            if ($this->config['debug']) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
        } catch (Exception $e) {
            error_log("Error al configurar EmailService: " . $e->getMessage());
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
            // Limpiar destinatarios anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Destinatario
            $this->mailer->addAddress($to, $toName);
            
            // Contenido
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $bodyHTML;
            $this->mailer->AltBody = $bodyText ?: strip_tags($bodyHTML);
            
            // Adjuntos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $this->mailer->addAttachment($attachment);
                }
            }
            
            // Enviar
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Correo enviado correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $this->mailer->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Error al enviar correo: ' . $this->mailer->ErrorInfo
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
        $subject = $data['subject'] ?? 'Notificación del Sistema BAES';
        
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

