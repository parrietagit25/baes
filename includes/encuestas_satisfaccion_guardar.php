<?php
/**
 * Validación y guardado de encuestas de satisfacción (público; usuario_id opcional).
 *
 * @return string|null Mensaje de error, o null si se guardó bien
 */
function encuestas_guardar_vendedor(PDO $pdo, ?int $usuarioId = null): ?string
{
    $e = encuestas_validar_comun();
    if ($e !== null) {
        return $e;
    }

    $sql = "INSERT INTO encuesta_formulario_publico_vendedor
        (usuario_id, nombre_completo, cargo, puntuacion_1, puntuacion_2, puntuacion_3, puntuacion_4, puntuacion_5, recomendaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $pdo->prepare($sql);
    $st->execute([
        $usuarioId,
        $_POST['nombre_completo'],
        $_POST['cargo'],
        (int) $_POST['p1'],
        (int) $_POST['p2'],
        (int) $_POST['p3'],
        (int) $_POST['p4'],
        (int) $_POST['p5'],
        encuestas_recomendaciones(),
    ]);
    return null;
}

function encuestas_guardar_gestor(PDO $pdo, ?int $usuarioId = null): ?string
{
    $e = encuestas_validar_comun();
    if ($e !== null) {
        return $e;
    }

    $sql = "INSERT INTO encuesta_proceso_gestor
        (usuario_id, nombre_completo, cargo, puntuacion_1, puntuacion_2, puntuacion_3, puntuacion_4, puntuacion_5, recomendaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $pdo->prepare($sql);
    $st->execute([
        $usuarioId,
        $_POST['nombre_completo'],
        $_POST['cargo'],
        (int) $_POST['p1'],
        (int) $_POST['p2'],
        (int) $_POST['p3'],
        (int) $_POST['p4'],
        (int) $_POST['p5'],
        encuestas_recomendaciones(),
    ]);
    return null;
}

function encuestas_recomendaciones(): ?string
{
    $t = trim((string) ($_POST['recomendaciones'] ?? ''));
    return $t === '' ? null : $t;
}

function encuestas_validar_comun(): ?string
{
    if (trim((string) ($_POST['enc_honeypot'] ?? '')) !== '') {
        return 'No se pudo enviar el formulario. Recargue la página e intente de nuevo.';
    }
    $n = trim((string) ($_POST['nombre_completo'] ?? ''));
    $c = trim((string) ($_POST['cargo'] ?? ''));
    if ($n === '' || mb_strlen($n) > 200) {
        return 'Indique su nombre completo (máximo 200 caracteres).';
    }
    if ($c === '' || mb_strlen($c) > 200) {
        return 'Indique su cargo (máximo 200 caracteres).';
    }
    for ($i = 1; $i <= 5; $i++) {
        if (!isset($_POST['p' . $i])) {
            return 'Responda todas las preguntas con estrellas (1 a 5).';
        }
        $v = (int) $_POST['p' . $i];
        if ($v < 1 || $v > 5) {
            return 'Las puntuaciones deben estar entre 1 y 5 estrellas.';
        }
    }
    if (mb_strlen((string) ($_POST['recomendaciones'] ?? '')) > 10000) {
        return 'Las recomendaciones son demasiado largas.';
    }
    $_POST['nombre_completo'] = $n;
    $_POST['cargo'] = $c;
    return null;
}
