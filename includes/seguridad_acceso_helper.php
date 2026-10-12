<?php
/**
 * Pausas por abuso, con baldes separados.
 * login_par / login_ip: panel (staff y banco).
 * reset: recuperación de clave.
 * form_publico: envíos del formulario de financiamiento (sigue siendo anónimo).
 */

if (!function_exists('seguridad_ip_cliente')) {
    function seguridad_ip_cliente(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $proxyLocal = $remote === ''
            || $remote === '127.0.0.1'
            || $remote === '::1'
            || str_starts_with($remote, '10.')
            || str_starts_with($remote, '192.168.')
            || str_starts_with($remote, '172.');

        if ($proxyLocal) {
            $real = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
            if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP)) {
                return substr($real, 0, 45);
            }
            $fwd = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($fwd !== '') {
                $primero = trim(explode(',', $fwd)[0]);
                if (filter_var($primero, FILTER_VALIDATE_IP)) {
                    return substr($primero, 0, 45);
                }
            }
        }

        return $remote !== '' ? substr($remote, 0, 45) : '0.0.0.0';
    }
}

if (!function_exists('seguridad_rate_asegurar_tabla')) {
    function seguridad_rate_asegurar_tabla(PDO $pdo): void
    {
        static $lista = false;
        if ($lista) {
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS seguridad_rate_limit (
                bucket VARCHAR(40) NOT NULL,
                clave VARCHAR(190) NOT NULL,
                intentos INT NOT NULL DEFAULT 0,
                ventana_inicio DATETIME NOT NULL,
                bloqueado_hasta DATETIME NULL,
                PRIMARY KEY (bucket, clave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $lista = true;
    }
}

if (!function_exists('seguridad_rate_bloqueado')) {
    /**
     * @return array{bloqueado:bool,espera:int}
     */
    function seguridad_rate_bloqueado(PDO $pdo, string $bucket, string $clave): array
    {
        try {
            seguridad_rate_asegurar_tabla($pdo);
            $st = $pdo->prepare('
                SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta)) AS espera
                FROM seguridad_rate_limit
                WHERE bucket = ? AND clave = ? AND bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()
                LIMIT 1
            ');
            $st->execute([$bucket, $clave]);
            $espera = $st->fetchColumn();
            if ($espera === false) {
                return ['bloqueado' => false, 'espera' => 0];
            }
            return ['bloqueado' => true, 'espera' => (int) $espera];
        } catch (Throwable $e) {
            error_log('seguridad_rate_bloqueado: ' . $e->getMessage());
            return ['bloqueado' => false, 'espera' => 0];
        }
    }
}

if (!function_exists('seguridad_rate_registrar')) {
    /**
     * Suma un intento. Al llegar a $max dentro de la ventana, pausa $bloqueoSeg segundos.
     *
     * @return array{bloqueado:bool,espera:int}
     */
    function seguridad_rate_registrar(PDO $pdo, string $bucket, string $clave, int $max, int $ventanaSeg, int $bloqueoSeg): array
    {
        $max = max(1, $max);
        $ventanaSeg = max(30, $ventanaSeg);
        $bloqueoSeg = max(30, $bloqueoSeg);
        try {
            seguridad_rate_asegurar_tabla($pdo);
            $ya = seguridad_rate_bloqueado($pdo, $bucket, $clave);
            if ($ya['bloqueado']) {
                return $ya;
            }

            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT intentos, ventana_inicio FROM seguridad_rate_limit WHERE bucket = ? AND clave = ? FOR UPDATE');
            $st->execute([$bucket, $clave]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $ins = $pdo->prepare('INSERT INTO seguridad_rate_limit (bucket, clave, intentos, ventana_inicio, bloqueado_hasta) VALUES (?, ?, 1, NOW(), NULL)');
                $ins->execute([$bucket, $clave]);
                $pdo->commit();
                return ['bloqueado' => false, 'espera' => 0];
            }

            $stEdad = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, ?, NOW())');
            $stEdad->execute([$row['ventana_inicio']]);
            $edad = (int) $stEdad->fetchColumn();
            $intentos = ($edad > $ventanaSeg) ? 1 : ((int) $row['intentos'] + 1);
            $bloqueadoHasta = null;
            if ($intentos >= $max) {
                $bloqueadoHasta = (new DateTimeImmutable('now'))->modify('+' . $bloqueoSeg . ' seconds')->format('Y-m-d H:i:s');
            }

            if ($edad > $ventanaSeg) {
                $up = $pdo->prepare('UPDATE seguridad_rate_limit SET intentos = ?, ventana_inicio = NOW(), bloqueado_hasta = ? WHERE bucket = ? AND clave = ?');
                $up->execute([$intentos, $bloqueadoHasta, $bucket, $clave]);
            } else {
                $up = $pdo->prepare('UPDATE seguridad_rate_limit SET intentos = ?, bloqueado_hasta = ? WHERE bucket = ? AND clave = ?');
                $up->execute([$intentos, $bloqueadoHasta, $bucket, $clave]);
            }
            $pdo->commit();

            return [
                'bloqueado' => $bloqueadoHasta !== null,
                'espera' => $bloqueadoHasta !== null ? $bloqueoSeg : 0,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('seguridad_rate_registrar: ' . $e->getMessage());
            return ['bloqueado' => false, 'espera' => 0];
        }
    }
}

if (!function_exists('seguridad_rate_limpiar')) {
    function seguridad_rate_limpiar(PDO $pdo, string $bucket, string $clave): void
    {
        try {
            seguridad_rate_asegurar_tabla($pdo);
            $st = $pdo->prepare('DELETE FROM seguridad_rate_limit WHERE bucket = ? AND clave = ?');
            $st->execute([$bucket, $clave]);
        } catch (Throwable $e) {
            error_log('seguridad_rate_limpiar: ' . $e->getMessage());
        }
    }
}
