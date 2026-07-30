<?php
/**
 * Seguimiento de actividad de usuarios internos MOTUS.
 */

if (!function_exists('motus_actividad_es_admin_principal')) {
    function motus_actividad_es_admin_principal(?int $userId = null): bool
    {
        $id = $userId;
        if ($id === null) {
            $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        }
        return $id === 1;
    }
}

if (!function_exists('motus_actividad_client_ip')) {
    function motus_actividad_client_ip(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_CLIENT_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        foreach ($candidates as $raw) {
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            foreach (array_map('trim', explode(',', $raw)) as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        foreach ($candidates as $raw) {
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            foreach (array_map('trim', explode(',', $raw)) as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return null;
    }
}

if (!function_exists('motus_actividad_asegurar_tabla')) {
    function motus_actividad_asegurar_tabla(PDO $pdo): void
    {
        static $ok = false;
        if ($ok) {
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuario_actividad (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id INT NULL,
              session_key VARCHAR(64) NULL,
              evento VARCHAR(40) NOT NULL,
              pagina VARCHAR(120) NULL,
              seccion VARCHAR(120) NULL,
              detalle VARCHAR(500) NULL,
              url_path VARCHAR(500) NULL,
              ip VARCHAR(45) NULL,
              user_agent VARCHAR(500) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_ua_usuario_fecha (usuario_id, created_at),
              KEY idx_ua_evento_fecha (evento, created_at),
              KEY idx_ua_created (created_at),
              KEY idx_ua_session (session_key),
              KEY idx_ua_ip (ip)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $ok = true;
    }
}

if (!function_exists('motus_actividad_mapa_secciones')) {
    /** @return array<string, array{seccion:string,etiqueta:string}> */
    function motus_actividad_mapa_secciones(): array
    {
        return [
            'dashboard.php' => ['seccion' => 'Dashboard', 'etiqueta' => 'Dashboard'],
            'solicitudes.php' => ['seccion' => 'Solicitudes', 'etiqueta' => 'Solicitud de Crédito'],
            'historico_solicitudes.php' => ['seccion' => 'Solicitudes', 'etiqueta' => 'Histórico de Solicitudes'],
            'mis_propuestas_banco.php' => ['seccion' => 'Solicitudes', 'etiqueta' => 'Propuestas banco'],
            'sol_financiamiento.php' => ['seccion' => 'Solicitudes', 'etiqueta' => 'Sol. Financiamiento'],
            'subir_reporte_reservas.php' => ['seccion' => 'Solicitudes', 'etiqueta' => 'Reporte reservas'],
            'usuarios.php' => ['seccion' => 'Administración', 'etiqueta' => 'Usuarios'],
            'roles.php' => ['seccion' => 'Administración', 'etiqueta' => 'Roles'],
            'bancos.php' => ['seccion' => 'Administración', 'etiqueta' => 'Bancos'],
            'usuarios_banco.php' => ['seccion' => 'Administración', 'etiqueta' => 'Usuarios banco'],
            'ejecutivos_ventas.php' => ['seccion' => 'Administración', 'etiqueta' => 'Ejecutivos de ventas'],
            'configuracion.php' => ['seccion' => 'Administración', 'etiqueta' => 'Configuración'],
            'ferias.php' => ['seccion' => 'Ferias', 'etiqueta' => 'Ferias'],
            'feria_panel.php' => ['seccion' => 'Ferias', 'etiqueta' => 'Panel feria'],
            'pipedrive.php' => ['seccion' => 'Integraciones', 'etiqueta' => 'Pipedrive'],
            'reportes.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Reportes'],
            'seguimiento_financiamiento.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Seguimiento financiamiento'],
            'seguimiento_banco.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Seguimiento banco'],
            'reportes_banco.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Rep. Usuarios Banco'],
            'reportes_banco_fin_enlazada.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Sol. Fin. + Motus'],
            'encuestas_resultados.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Encuestas'],
            'seguimiento_usuarios.php' => ['seccion' => 'Reportes', 'etiqueta' => 'Seguimiento de Usuarios'],
            'index.php' => ['seccion' => 'Acceso', 'etiqueta' => 'Login'],
            'logout.php' => ['seccion' => 'Acceso', 'etiqueta' => 'Cerrar sesión'],
            'recuperar_password.php' => ['seccion' => 'Acceso', 'etiqueta' => 'Recuperar contraseña'],
            'restablecer_password.php' => ['seccion' => 'Acceso', 'etiqueta' => 'Restablecer contraseña'],
        ];
    }
}

if (!function_exists('motus_actividad_meta_pagina')) {
    /** @return array{seccion:string,etiqueta:string} */
    function motus_actividad_meta_pagina(string $pagina): array
    {
        $pagina = basename($pagina);
        $map = motus_actividad_mapa_secciones();
        if (isset($map[$pagina])) {
            return $map[$pagina];
        }
        $base = preg_replace('/\.php$/i', '', $pagina) ?: $pagina;
        return ['seccion' => 'Otra', 'etiqueta' => $base];
    }
}

if (!function_exists('motus_actividad_session_key')) {
    function motus_actividad_session_key(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        if (empty($_SESSION['actividad_sid']) || !is_string($_SESSION['actividad_sid'])) {
            try {
                $_SESSION['actividad_sid'] = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $_SESSION['actividad_sid'] = md5(uniqid((string) mt_rand(), true));
            }
        }
        return (string) $_SESSION['actividad_sid'];
    }
}

/**
 * @param array{
 *   usuario_id?:int|null,
 *   session_key?:string|null,
 *   pagina?:string|null,
 *   seccion?:string|null,
 *   detalle?:string|null,
 *   url_path?:string|null,
 *   ip?:string|null,
 *   user_agent?:string|null
 * } $opts
 */
if (!function_exists('motus_actividad_registrar')) {
    function motus_actividad_registrar(PDO $pdo, string $evento, array $opts = []): void
    {
        $evento = strtolower(trim($evento));
        if ($evento === '' || strlen($evento) > 40) {
            return;
        }
        try {
            motus_actividad_asegurar_tabla($pdo);

            $pagina = isset($opts['pagina']) ? basename((string) $opts['pagina']) : null;
            if ($pagina === '') {
                $pagina = null;
            }
            $meta = $pagina ? motus_actividad_meta_pagina($pagina) : ['seccion' => null, 'etiqueta' => null];
            $seccion = isset($opts['seccion']) && trim((string) $opts['seccion']) !== ''
                ? substr(trim((string) $opts['seccion']), 0, 120)
                : ($meta['seccion'] ?? null);
            $detalle = isset($opts['detalle']) ? substr(trim((string) $opts['detalle']), 0, 500) : null;
            if ($detalle === '') {
                $detalle = null;
            }
            if ($detalle === null && !empty($meta['etiqueta']) && in_array($evento, ['page_view', 'login', 'logout'], true)) {
                $detalle = $meta['etiqueta'];
            }

            $urlPath = isset($opts['url_path']) ? substr(trim((string) $opts['url_path']), 0, 500) : null;
            if ($urlPath === '') {
                $urlPath = null;
            }

            $usuarioId = array_key_exists('usuario_id', $opts)
                ? ($opts['usuario_id'] !== null ? (int) $opts['usuario_id'] : null)
                : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
            if ($usuarioId !== null && $usuarioId <= 0) {
                $usuarioId = null;
            }

            $sessionKey = array_key_exists('session_key', $opts)
                ? ($opts['session_key'] !== null ? substr((string) $opts['session_key'], 0, 64) : null)
                : motus_actividad_session_key();

            $ip = array_key_exists('ip', $opts) ? $opts['ip'] : motus_actividad_client_ip();
            if (is_string($ip)) {
                $ip = substr($ip, 0, 45);
            } else {
                $ip = null;
            }

            $ua = array_key_exists('user_agent', $opts)
                ? $opts['user_agent']
                : ($_SERVER['HTTP_USER_AGENT'] ?? null);
            if (is_string($ua)) {
                $ua = substr($ua, 0, 500);
            } else {
                $ua = null;
            }

            $st = $pdo->prepare('
                INSERT INTO usuario_actividad
                  (usuario_id, session_key, evento, pagina, seccion, detalle, url_path, ip, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $st->execute([
                $usuarioId,
                $sessionKey,
                $evento,
                $pagina,
                $seccion,
                $detalle,
                $urlPath,
                $ip,
                $ua,
            ]);
        } catch (Throwable $e) {
            error_log('motus_actividad_registrar: ' . $e->getMessage());
        }
    }
}

if (!function_exists('motus_actividad_registrar_page_view')) {
    function motus_actividad_registrar_page_view(PDO $pdo, string $pagina): void
    {
        $pagina = basename($pagina);
        if ($pagina === '') {
            return;
        }
        // Evitar spam al refrescar la misma pantalla en pocos segundos.
        $now = time();
        $lastPage = $_SESSION['actividad_last_page'] ?? '';
        $lastAt = (int) ($_SESSION['actividad_last_page_at'] ?? 0);
        if ($lastPage === $pagina && ($now - $lastAt) < 4) {
            return;
        }
        $_SESSION['actividad_last_page'] = $pagina;
        $_SESSION['actividad_last_page_at'] = $now;

        $qs = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';
        $urlPath = '/' . $pagina . ($qs !== '' ? ('?' . substr($qs, 0, 400)) : '');
        $detalleExtra = null;
        if ($pagina === 'solicitudes.php' && isset($_GET['id'])) {
            $detalleExtra = 'Solicitud #' . (int) $_GET['id'];
        } elseif ($pagina === 'reportes.php' && isset($_GET['submenu'])) {
            $detalleExtra = 'Submenú: ' . substr(preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['submenu']), 0, 40);
        }

        motus_actividad_registrar($pdo, 'page_view', [
            'pagina' => $pagina,
            'url_path' => $urlPath,
            'detalle' => $detalleExtra,
        ]);
    }
}
