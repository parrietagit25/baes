<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validar_acceso.php';
require_once __DIR__ . '/includes/usuario_actividad_helper.php';
require_once __DIR__ . '/includes/seguridad_acceso_helper.php';

if (!motus_actividad_es_admin_principal()) {
    header('Location: dashboard.php');
    exit();
}

$aviso = '';
$avisoTipo = 'success';
if (!empty($_SESSION['monitoreo_aviso'])) {
    $aviso = (string) $_SESSION['monitoreo_aviso'];
    $avisoTipo = (string) ($_SESSION['monitoreo_aviso_tipo'] ?? 'success');
    unset($_SESSION['monitoreo_aviso'], $_SESSION['monitoreo_aviso_tipo']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo) && $pdo instanceof PDO) {
    $accion = (string) ($_POST['accion'] ?? '');
    try {
        if ($accion === 'desbloquear') {
            $bucket = (string) ($_POST['bucket'] ?? '');
            $clave = (string) ($_POST['clave'] ?? '');
            $puertas = seguridad_rate_puertas();
            if (!isset($puertas[$bucket]) || $clave === '') {
                throw new RuntimeException('Registro no válido.');
            }
            seguridad_rate_limpiar($pdo, $bucket, $clave);
            $_SESSION['monitoreo_aviso'] = 'Bloqueo quitado para ' . $clave . '.';
        } elseif ($accion === 'bloquear') {
            $ip = trim((string) ($_POST['ip'] ?? ''));
            $horas = (int) ($_POST['horas'] ?? 1);
            $puertasElegidas = isset($_POST['puertas']) && is_array($_POST['puertas']) ? $_POST['puertas'] : [];
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Escriba una IP válida.');
            }
            $permitidas = ['login_ip', 'reset', 'form_publico'];
            $aplicar = array_values(array_intersect($permitidas, array_map('strval', $puertasElegidas)));
            if (!$aplicar) {
                throw new RuntimeException('Elija al menos una puerta.');
            }
            foreach ($aplicar as $bucket) {
                seguridad_rate_bloquear_manual($pdo, $bucket, $ip, $horas);
            }
            $_SESSION['monitoreo_aviso'] = 'IP ' . $ip . ' bloqueada por ' . max(1, min(168, $horas)) . ' h.';
        } else {
            throw new RuntimeException('Acción no reconocida.');
        }
    } catch (Throwable $e) {
        $_SESSION['monitoreo_aviso'] = $e->getMessage();
        $_SESSION['monitoreo_aviso_tipo'] = 'danger';
    }
    header('Location: monitoreo_seguridad.php');
    exit();
}

$puertas = seguridad_rate_puertas();
$bloqueos = [];
$loginsIp = [];
$intentos = [];
$kpi = ['bloqueados' => 0, 'ok_24h' => 0, 'fallos_24h' => 0, 'ips_24h' => 0];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $bloqueos = seguridad_rate_listar($pdo);
        foreach ($bloqueos as $b) {
            if (!empty($b['activo'])) {
                $kpi['bloqueados']++;
            }
        }
        motus_actividad_asegurar_tabla($pdo);
        $stK = $pdo->query("
            SELECT
              SUM(evento = 'login') AS ok_24h,
              SUM(evento = 'login_failed') AS fallos_24h,
              COUNT(DISTINCT ip) AS ips_24h
            FROM usuario_actividad
            WHERE evento IN ('login', 'login_failed')
              AND created_at >= (NOW() - INTERVAL 1 DAY)
        ");
        $rowK = $stK ? $stK->fetch(PDO::FETCH_ASSOC) : [];
        $kpi['ok_24h'] = (int) ($rowK['ok_24h'] ?? 0);
        $kpi['fallos_24h'] = (int) ($rowK['fallos_24h'] ?? 0);
        $kpi['ips_24h'] = (int) ($rowK['ips_24h'] ?? 0);

        $stIp = $pdo->query("
            SELECT ip,
                   SUM(evento = 'login') AS ok,
                   SUM(evento = 'login_failed') AS fallos,
                   MAX(created_at) AS ultima
            FROM usuario_actividad
            WHERE evento IN ('login', 'login_failed')
              AND created_at >= (NOW() - INTERVAL 7 DAY)
              AND ip IS NOT NULL AND ip <> ''
            GROUP BY ip
            ORDER BY fallos DESC, ultima DESC
            LIMIT 200
        ");
        $loginsIp = $stIp ? ($stIp->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $stDet = $pdo->query("
            SELECT a.created_at, a.evento, a.ip, a.detalle, a.usuario_id,
                   u.nombre, u.apellido, u.email
            FROM usuario_actividad a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.evento IN ('login', 'login_failed')
            ORDER BY a.id DESC
            LIMIT 150
        ");
        $intentos = $stDet ? ($stDet->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        error_log('monitoreo_seguridad: ' . $e->getMessage());
        $aviso = 'No se pudo cargar el monitoreo.';
        $avisoTipo = 'danger';
    }
}

function monitoreo_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function monitoreo_minutos(int $seg): string
{
    if ($seg <= 0) {
        return '—';
    }
    $m = (int) ceil($seg / 60);
    return $m . ' min';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de seguridad - MOTUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .sidebar .nav-link { color: #ecf0f1; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background: #3498db; color: #fff; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .page-header { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="container-fluid py-4">
                <div class="page-header">
                    <h2 class="mb-1"><i class="fas fa-shield-alt me-2"></i>Monitoreo de seguridad</h2>
                    <p class="mb-0 opacity-90">Solo el administrador principal. Resumen de resguardos, intentos de login y bloqueos por IP.</p>
                </div>

                <?php if ($aviso !== ''): ?>
                <div class="alert alert-<?php echo $avisoTipo === 'danger' ? 'danger' : 'success'; ?>"><?php echo monitoreo_h($aviso); ?></div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Bloqueos activos</div><div class="h4 mb-0 text-danger"><?php echo (int) $kpi['bloqueados']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Logins OK (24 h)</div><div class="h4 mb-0 text-success"><?php echo (int) $kpi['ok_24h']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">Intentos fallidos (24 h)</div><div class="h4 mb-0 text-warning"><?php echo (int) $kpi['fallos_24h']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center"><div class="text-muted small">IPs distintas (24 h)</div><div class="h4 mb-0"><?php echo (int) $kpi['ips_24h']; ?></div></div></div></div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Resguardo activo en Motus</h6>
                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li>Los errores de PHP no se muestran al visitante. Quedan en el registro del servidor.</li>
                                    <li>La cookie de sesión es HttpOnly y SameSite=Lax. Lleva Secure cuando la visita llega por HTTPS.</li>
                                    <li>Al entrar bien al panel, el identificador de sesión se renueva.</li>
                                    <li>El navegador recibe encabezados que evitan adivinar el tipo de archivo, incrustar el sitio, y pedir cámara, micrófono, ubicación o pago.</li>
                                    <li>La política de contenido está en modo informe: observa scripts y estilos, y no bloquea lo que el sitio ya carga.</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li>Nginx no publica su versión. .env, .git, SQL, logs y la carpeta database responden 404.</li>
                                    <li>Login del panel: 5 fallos por IP y correo, o 20 por IP, y pausa de 15 minutos. Lo usan staff y banco.</li>
                                    <li>Recuperar contraseña: 5 solicitudes por IP y pausa de 15 minutos.</li>
                                    <li>El formulario de financiamiento sigue público, sin login. Tiene su propia pausa: 10 envíos por IP cada 15 minutos.</li>
                                    <li>La exportación completa de la base solo la puede hacer el administrador principal.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Bloquear una IP</h6>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="accion" value="bloquear">
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="ip">IP</label>
                                <input class="form-control form-control-sm" id="ip" name="ip" required placeholder="190.0.0.1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="horas">Horas</label>
                                <input class="form-control form-control-sm" id="horas" name="horas" type="number" min="1" max="168" value="1" required>
                            </div>
                            <div class="col-md-5">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="puertas[]" value="login_ip" id="pLogin" checked>
                                    <label class="form-check-label" for="pLogin">Login</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="puertas[]" value="reset" id="pReset" checked>
                                    <label class="form-check-label" for="pReset">Recuperar clave</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="puertas[]" value="form_publico" id="pForm">
                                    <label class="form-check-label" for="pForm">Formulario público</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-danger btn-sm w-100">Bloquear</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Bloqueos y contadores</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Puerta</th>
                                        <th>Clave</th>
                                        <th>Intentos</th>
                                        <th>Ventana</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$bloqueos): ?>
                                    <tr><td colspan="6" class="text-muted text-center">No hay registros de pausa.</td></tr>
                                <?php else: foreach ($bloqueos as $b): ?>
                                    <tr>
                                        <td><?php echo monitoreo_h($puertas[$b['bucket']] ?? $b['bucket']); ?></td>
                                        <td><code><?php echo monitoreo_h($b['clave']); ?></code></td>
                                        <td><?php echo (int) $b['intentos']; ?></td>
                                        <td><?php echo monitoreo_h($b['ventana_inicio']); ?></td>
                                        <td>
                                            <?php if (!empty($b['activo'])): ?>
                                                <span class="badge bg-danger">Bloqueada · <?php echo monitoreo_h(monitoreo_minutos((int) $b['espera'])); ?></span>
                                                <div class="text-muted"><?php echo monitoreo_h($b['bloqueado_hasta']); ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">En conteo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="desbloquear">
                                                <input type="hidden" name="bucket" value="<?php echo monitoreo_h($b['bucket']); ?>">
                                                <input type="hidden" name="clave" value="<?php echo monitoreo_h($b['clave']); ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm">Desbloquear</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Logins por IP (7 días)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>IP</th><th>Correctos</th><th>Fallidos</th><th>Última vez</th></tr>
                                </thead>
                                <tbody>
                                <?php if (!$loginsIp): ?>
                                    <tr><td colspan="4" class="text-muted text-center">Sin logins registrados en el periodo.</td></tr>
                                <?php else: foreach ($loginsIp as $r): ?>
                                    <tr>
                                        <td><code><?php echo monitoreo_h($r['ip']); ?></code></td>
                                        <td><?php echo (int) $r['ok']; ?></td>
                                        <td><?php echo (int) $r['fallos']; ?></td>
                                        <td><?php echo monitoreo_h($r['ultima']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Últimos intentos de login</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>Fecha</th><th>Resultado</th><th>IP</th><th>Usuario</th><th>Detalle</th></tr>
                                </thead>
                                <tbody>
                                <?php if (!$intentos): ?>
                                    <tr><td colspan="5" class="text-muted text-center">Sin intentos registrados.</td></tr>
                                <?php else: foreach ($intentos as $r):
                                    $nom = trim((string) (($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')));
                                    if ($nom === '') {
                                        $nom = (string) ($r['email'] ?? '');
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo monitoreo_h($r['created_at']); ?></td>
                                        <td>
                                            <?php if (($r['evento'] ?? '') === 'login'): ?>
                                                <span class="badge bg-success">Correcto</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Fallido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo monitoreo_h($r['ip'] ?: '—'); ?></code></td>
                                        <td><?php echo monitoreo_h($nom !== '' ? $nom : '—'); ?></td>
                                        <td><?php echo monitoreo_h($r['detalle'] ?: '—'); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
