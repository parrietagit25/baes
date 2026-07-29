<?php
/**
 * Recuperación / restablecimiento de contraseña (tabla usuarios).
 */

if (!function_exists('password_reset_asegurar_tabla')) {
    function password_reset_asegurar_tabla(PDO $pdo): void
    {
        static $ok = false;
        if ($ok) {
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id INT NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              used_at DATETIME DEFAULT NULL,
              ip_solicitud VARCHAR(45) DEFAULT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uk_token_hash (token_hash),
              KEY idx_usuario (usuario_id),
              KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $ok = true;
    }
}

if (!function_exists('password_reset_app_url')) {
    function password_reset_app_url(): string
    {
        $cfg = file_exists(__DIR__ . '/../config/email.php') ? require __DIR__ . '/../config/email.php' : [];
        $url = '';
        if (function_exists('getenv')) {
            $env = getenv('APP_URL');
            if ($env !== false && trim((string) $env) !== '') {
                $url = trim((string) $env);
            }
        }
        if ($url === '') {
            $url = trim((string) ($cfg['app_url'] ?? ''));
        }
        return rtrim($url, '/');
    }
}

if (!function_exists('password_reset_hash_token')) {
    function password_reset_hash_token(string $token): string
    {
        return hash('sha256', strtolower(trim($token)));
    }
}

if (!function_exists('password_reset_mensaje_generico')) {
    /** Mensaje neutro (no revela si el email existe). */
    function password_reset_mensaje_generico(): string
    {
        return 'Si el correo está registrado y el usuario está activo, enviamos un enlace para restablecer la contraseña. Revise su bandeja de entrada y spam.';
    }
}

/**
 * Solicita reset: crea token y envía correo si el usuario existe y está activo.
 * Siempre retorna el mismo mensaje de éxito hacia el usuario (salvo fallo de envío con usuario real).
 *
 * @return array{ok:bool,message:string}
 */
if (!function_exists('password_reset_solicitar')) {
    function password_reset_solicitar(PDO $pdo, string $email, ?string $ip = null): array
    {
        $email = trim(mb_strtolower($email));
        $msgOk = password_reset_mensaje_generico();

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Ingrese un correo electrónico válido.'];
        }

        password_reset_asegurar_tabla($pdo);

        $stmt = $pdo->prepare('SELECT id, nombre, apellido, email, activo FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $usuarioId = (int) $user['id'];

        // Anti-spam: no reenviar si hay un token reciente (< 60 s).
        $stRecent = $pdo->prepare("
            SELECT id FROM password_reset_tokens
            WHERE usuario_id = ? AND used_at IS NULL AND created_at > (NOW() - INTERVAL 60 SECOND)
            LIMIT 1
        ");
        $stRecent->execute([$usuarioId]);
        if ($stRecent->fetchColumn()) {
            return ['ok' => true, 'message' => $msgOk];
        }

        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL')
            ->execute([$usuarioId]);

        $token = bin2hex(random_bytes(32));
        $tokenHash = password_reset_hash_token($token);
        $expires = (new DateTimeImmutable('now'))->modify('+1 hour');
        $expiresSql = $expires->format('Y-m-d H:i:s');

        $ins = $pdo->prepare('
            INSERT INTO password_reset_tokens (usuario_id, token_hash, expires_at, ip_solicitud)
            VALUES (?, ?, ?, ?)
        ');
        $ins->execute([$usuarioId, $tokenHash, $expiresSql, $ip !== null ? substr($ip, 0, 45) : null]);

        $baseUrl = password_reset_app_url();
        if ($baseUrl === '') {
            error_log('password_reset: APP_URL / app_url vacío; no se puede armar el enlace.');
            return ['ok' => false, 'message' => 'No se pudo generar el enlace. Contacte al administrador.'];
        }
        $link = $baseUrl . '/restablecer_password.php?t=' . rawurlencode($token);
        $nombre = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
        if ($nombre === '') {
            $nombre = 'usuario';
        }
        $expLeg = $expires->format('d/m/Y H:i');

        $subject = 'Restablecer contraseña - MOTUS';
        $app_name = 'MOTUS - AutoMarket Seminuevos';
        $content = '<h2>Restablecer contraseña</h2>'
            . '<p>Hola <strong>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Recibimos una solicitud para restablecer la contraseña de su cuenta en MOTUS.</p>'
            . '<p>El enlace es válido por <strong>1 hora</strong> (hasta ' . htmlspecialchars($expLeg, ENT_QUOTES, 'UTF-8') . ') y solo puede usarse una vez.</p>'
            . '<p style="text-align:center;margin:24px 0;">'
            . '<a class="button" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Restablecer contraseña</a>'
            . '</p>'
            . '<p style="font-size:13px;color:#555;word-break:break-all;">Si el botón no funciona, copie y pegue este enlace:<br>'
            . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Si usted no solicitó este cambio, ignore este correo. Su contraseña no se modificará.</p>';

        ob_start();
        include __DIR__ . '/../templates/email/base.php';
        $bodyHtml = ob_get_clean();
        $bodyTxt = "Hola {$nombre},\n\n"
            . "Para restablecer su contraseña en MOTUS use este enlace (válido 1 hora, un solo uso):\n{$link}\n\n"
            . "Si no solicitó este cambio, ignore este mensaje.\n";

        require_once __DIR__ . '/EmailService.php';
        $mail = (new EmailService())->sinCcAuditoria();
        $res = $mail->enviarCorreo(
            $user['email'],
            $subject,
            $bodyHtml,
            $nombre,
            $bodyTxt,
            [],
            [],
            [],
            ''
        );

        if (empty($res['success'])) {
            error_log('password_reset envío fallido: ' . ($res['message'] ?? 'error'));
            return [
                'ok' => false,
                'message' => 'No se pudo enviar el correo. Intente más tarde o contacte al administrador.',
            ];
        }

        return ['ok' => true, 'message' => $msgOk];
    }
}

/**
 * @return array|null Fila token+usuario o null si inválido/expirado/usado
 */
if (!function_exists('password_reset_buscar_token_valido')) {
    function password_reset_buscar_token_valido(PDO $pdo, string $token): ?array
    {
        $token = strtolower(trim($token));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        password_reset_asegurar_tabla($pdo);
        $hash = password_reset_hash_token($token);
        $st = $pdo->prepare("
            SELECT t.id AS token_id, t.usuario_id, t.expires_at, t.used_at,
                   u.email, u.nombre, u.apellido, u.activo
            FROM password_reset_tokens t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token_hash = ?
            LIMIT 1
        ");
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['used_at'])) {
            return null;
        }
        if ((int) ($row['activo'] ?? 0) !== 1) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return $row;
    }
}

/**
 * @return array{ok:bool,message:string}
 */
if (!function_exists('password_reset_aplicar')) {
    function password_reset_aplicar(PDO $pdo, string $token, string $password, string $passwordConfirm): array
    {
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'message' => 'Las contraseñas no coinciden.'];
        }

        $row = password_reset_buscar_token_valido($pdo, $token);
        if (!$row) {
            return ['ok' => false, 'message' => 'El enlace no es válido o ha expirado. Solicite uno nuevo.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare('UPDATE usuarios SET password = ?, fecha_actualizacion = NOW() WHERE id = ? AND activo = 1');
            $upd->execute([$hash, (int) $row['usuario_id']]);
            if ($upd->rowCount() < 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'No se pudo actualizar la contraseña.'];
            }
            $mark = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $mark->execute([(int) $row['token_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL')
                ->execute([(int) $row['usuario_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('password_reset_aplicar: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Error al guardar la nueva contraseña. Intente nuevamente.'];
        }

        return ['ok' => true, 'message' => 'Contraseña actualizada correctamente. Ya puede iniciar sesión.'];
    }
}
