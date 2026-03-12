<?php
/**
 * Autenticación del módulo financiamiento (usuarios en tabla financiamiento_usuarios).
 */
if (!defined('FINANCIAMIENTO_INCLUDES')) {
    $base = dirname(__DIR__);
    if (is_file($base . '/config_db.php')) {
        require_once $base . '/config_db.php';
        $GLOBALS['pdo_financiamiento'] = isset($pdo_financiamiento) ? $pdo_financiamiento : null;
    }
    if ((!isset($GLOBALS['pdo_financiamiento']) || !($GLOBALS['pdo_financiamiento'] instanceof PDO)) && is_file(dirname($base) . '/config/database.php')) {
        require_once dirname($base) . '/config/database.php';
        $GLOBALS['pdo_financiamiento'] = isset($pdo) ? $pdo : null;
    }
    define('FINANCIAMIENTO_INCLUDES', true);
}

function financiamiento_pdo() {
    return isset($GLOBALS['pdo_financiamiento']) && $GLOBALS['pdo_financiamiento'] instanceof PDO
        ? $GLOBALS['pdo_financiamiento'] : null;
}

function financiamiento_crear_tabla_usuarios_si_no_existe() {
    $pdo = financiamiento_pdo();
    if (!$pdo) return false;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financiamiento_usuarios (
            id int(11) NOT NULL AUTO_INCREMENT,
            nombre varchar(120) NOT NULL,
            email varchar(255) NOT NULL,
            password_hash varchar(255) NOT NULL,
            activo tinyint(1) NOT NULL DEFAULT 1,
            fecha_creacion timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    return true;
}

function financiamiento_crear_primer_usuario_si_vacio() {
    $pdo = financiamiento_pdo();
    if (!$pdo) return;
    financiamiento_crear_tabla_usuarios_si_no_existe();
    $stmt = $pdo->query("SELECT COUNT(*) FROM financiamiento_usuarios");
    if ($stmt && (int)$stmt->fetchColumn() > 0) return;
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO financiamiento_usuarios (nombre, email, password_hash, activo) VALUES (?, ?, ?, 1)");
    $stmt->execute(['Administrador', 'admin@ejemplo.com', $hash]);
}

function financiamiento_usuario_logueado() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['financiamiento_user_id'])) return null;
    $pdo = financiamiento_pdo();
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT id, nombre, email FROM financiamiento_usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$_SESSION['financiamiento_user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function financiamiento_requiere_login($redirigir_a = 'login.php') {
    $u = financiamiento_usuario_logueado();
    if ($u) return $u;
    header('Location: ' . $redirigir_a . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    exit;
}

function financiamiento_menu($pagina_actual = '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $nombre = $_SESSION['financiamiento_user_nombre'] ?? 'Usuario';
    echo '<nav class="menu-financiamiento" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">';
    echo '<a href="ver_registros.php" class="btn ' . ($pagina_actual === 'registros' ? 'active' : '') . '">Ver registros</a>';
    echo '<a href="usuarios.php" class="btn ' . ($pagina_actual === 'usuarios' ? 'active' : '') . '">Usuarios</a>';
    echo '<a href="generar_link.php" class="btn">Generar link</a>';
    echo '<a href="index.php" class="btn" target="_blank">Formulario público</a>';
    echo '<span style="margin-left:auto;color:#9fb0d0;font-size:0.9rem;">' . htmlspecialchars($nombre) . '</span>';
    echo '<a href="login.php?salir=1" class="btn">Cerrar sesión</a>';
    echo '</nav>';
}
