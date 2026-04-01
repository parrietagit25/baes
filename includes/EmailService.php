<?php
/**
 * Envío de correos vía Resend (único proveedor).
 */

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die('Error: Composer no está instalado. Ejecuta: composer install');
}

use Resend\Exceptions\ErrorException;

class EmailService {
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
        $nombreCorrecto = 'AutoMarket Seminuevos';
        foreach (['from_name', 'reply_to_name', 'app_name'] as $key) {
            if (!empty($this->config[$key]) && (stripos($this->config[$key], 'Rent a Car') !== false || stripos($this->config[$key], 'Automarket Rent') !== false)) {
                $this->config[$key] = $key === 'reply_to_name' ? $nombreCorrecto . ' - Soporte' : $nombreCorrecto;
            }
        }

        $apiKey = trim((string) ($this->config['resend_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Falta RESEND_API_KEY en .env o variables de entorno.');
        }

        $base = trim((string) ($this->config['resend_base_url'] ?? 'api.resend.com'));
        $base = preg_replace('#^https?://#i', '', rtrim($base, '/'));
        if ($base !== '') {
            putenv('RESEND_BASE_URL=' . $base);
            $_ENV['RESEND_BASE_URL'] = $base;
        }
    }

    /**
     * @param array $attachments Rutas locales de archivos
     * @param array $cc Lista de correos en copia (solo dirección, sin nombre)
     */
    public function enviarCorreo(
        $to,
        $subject,
        $bodyHTML,
        $toName = '',
        $bodyText = '',
        $attachments = [],
        array $cc = []
    ) {
        try {
            return $this->enviarCorreoResend($to, $toName, $subject, $bodyHTML, $bodyText, $attachments, $cc);
        } catch (Throwable $e) {
            error_log('Error al enviar correo (Resend): ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al enviar correo: ' . $e->getMessage()];
        }
    }

    private function mensajeErrorResend(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return 'Error Resend: respuesta vacía';
        }
        if (stripos($raw, 'only send testing emails') !== false
            || stripos($raw, 'verify a domain') !== false) {
            return 'Resend (modo prueba): con onboarding@resend.dev solo puedes enviar a tu propio correo. '
                . 'Para notificar a bancos u otros destinatarios, verifica un dominio en https://resend.com/domains '
                . 'y configura MAIL_FROM_EMAIL (y opcionalmente MAIL_REPLY_TO) con una dirección de ese dominio. '
                . 'Detalle: ' . $raw;
        }
        return 'Error Resend: ' . $raw;
    }

    private function enviarCorreoResend($to, $toName, $subject, $bodyHTML, $bodyText, $attachments, array $cc = []) {
        $apiKey = trim((string) ($this->config['resend_api_key'] ?? ''));
        $fromName = trim((string) ($this->config['from_name'] ?? ''));
        $fromEmail = trim((string) ($this->config['from_email'] ?? ''));
        $from = $fromName !== '' ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail;

        $toAddr = trim((string) $to);
        $params = [
            'from' => $from,
            'to' => $toName !== '' ? [$toName . ' <' . $toAddr . '>'] : [$toAddr],
            'subject' => $subject,
            'html' => $bodyHTML,
            'text' => $bodyText !== '' ? $bodyText : strip_tags($bodyHTML),
        ];

        $ccList = $this->normalizarListaCorreosCc($cc, $toAddr, $fromEmail);
        if ($ccList !== []) {
            $params['cc'] = $ccList;
        }

        $replyTo = trim((string) ($this->config['reply_to_email'] ?? ''));
        if ($replyTo !== '') {
            $params['reply_to'] = $replyTo;
        }

        if (!empty($attachments)) {
            $atts = [];
            foreach ($attachments as $path) {
                if (!is_string($path) || !is_file($path)) {
                    continue;
                }
                $content = @file_get_contents($path);
                if ($content === false) {
                    continue;
                }
                $atts[] = [
                    'filename' => basename($path),
                    'content' => base64_encode($content),
                ];
            }
            if ($atts !== []) {
                $params['attachments'] = $atts;
            }
        }

        try {
            $client = Resend::client($apiKey);
            $result = $client->emails->send($params);
            return [
                'success' => true,
                'message' => 'Correo enviado correctamente',
                'provider' => 'resend',
                'id' => $result->id ?? null,
            ];
        } catch (ErrorException $e) {
            return [
                'success' => false,
                'message' => $this->mensajeErrorResend($e->getMessage()),
                'provider' => 'resend',
            ];
        }
    }

    /**
     * @param array<int, string> $cc
     * @return list<string>
     */
    private function normalizarListaCorreosCc(array $cc, string $toPrincipal, string $fromEmail): array {
        $toLower = strtolower($toPrincipal);
        $fromLower = strtolower(trim($fromEmail));
        $seen = [];
        $out = [];
        foreach ($cc as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $e = trim($raw);
            if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $k = strtolower($e);
            if ($k === $toLower || ($fromLower !== '' && $k === $fromLower)) {
                continue;
            }
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $e;
        }
        return $out;
    }

    public function enviarTemplate($to, $toName, $template, $data = []) {
        $templatePath = __DIR__ . '/../templates/email/' . $template . '.php';

        if (!file_exists($templatePath)) {
            return [
                'success' => false,
                'message' => "Template no encontrado: {$template}",
            ];
        }

        extract($data);

        ob_start();
        include $templatePath;
        $bodyHTML = ob_get_clean();

        $bodyText = strip_tags($bodyHTML);
        $subject = $data['subject'] ?? 'Notificación de AutoMarket Seminuevos';

        return $this->enviarCorreo($to, $subject, $bodyHTML, $toName, $bodyText, []);
    }

    public function notificarVendedorBancoResponde($vendedorEmail, $vendedorNombre, $solicitud) {
        return $this->enviarTemplate(
            $vendedorEmail,
            $vendedorNombre,
            'notificacion_banco_responde',
            [
                'subject' => 'Respuesta del Banco - Solicitud #' . $solicitud['id'],
                'vendedor_nombre' => $vendedorNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url'],
            ]
        );
    }

    public function enviarRecordatorioBanco($bancoEmail, $bancoNombre, $solicitud) {
        return $this->enviarTemplate(
            $bancoEmail,
            $bancoNombre,
            'recordatorio_banco',
            [
                'subject' => 'Recordatorio: Solicitud Pendiente #' . $solicitud['id'],
                'banco_nombre' => $bancoNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url'],
            ]
        );
    }

    public function notificarBancoNuevaSolicitud($bancoEmail, $bancoNombre, $solicitud) {
        return $this->enviarTemplate(
            $bancoEmail,
            $bancoNombre,
            'notificacion_nueva_solicitud',
            [
                'subject' => 'Nueva Solicitud Asignada #' . $solicitud['id'],
                'banco_nombre' => $bancoNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url'],
            ]
        );
    }

    public function notificarClienteAprobacion($clienteEmail, $clienteNombre, $solicitud) {
        return $this->enviarTemplate(
            $clienteEmail,
            $clienteNombre,
            'notificacion_cliente_aprobacion',
            [
                'subject' => '¡Felicidades! Su solicitud de crédito ha sido aprobada',
                'cliente_nombre' => $clienteNombre,
                'solicitud' => $solicitud,
                'app_url' => $this->config['app_url'],
            ]
        );
    }

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
                'app_url' => $this->config['app_url'],
            ]
        );
    }

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
                'app_url' => $this->config['app_url'],
            ]
        );
    }
}
