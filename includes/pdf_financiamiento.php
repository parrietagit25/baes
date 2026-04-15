<?php
/**
 * Genera el HTML del PDF de solicitud de financiamiento (mismo que se envía por correo).
 * Uso: buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombreCliente)
 * $input: array con claves iguales a columnas de financiamiento_registros (o del formulario).
 */
function buildPdfHtmlFinanciamiento($input, $firmaBase64, $nombreCliente) {
    $h = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };
    $enhanceSignatureBase64 = function($b64) {
        $b64 = trim((string)$b64);
        if ($b64 === '') return $b64;
        if (!function_exists('imagecreatefromstring')) return $b64;

        $bin = @base64_decode($b64, true);
        if ($bin === false) return $b64;
        $img = @imagecreatefromstring($bin);
        if (!$img) return $b64;

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($img);
            return $b64;
        }

        imagesavealpha($img, true);
        imagealphablending($img, false);

        // Convertir trazos claros a tinta más oscura sin perder transparencia.
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $lum = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

                // Solo tocar píxeles con trazo visible (no totalmente transparentes).
                if ($a < 120) {
                    // Entre más claro el trazo, más lo oscurecemos.
                    $new = (int)max(12, min(255, ($lum * 0.35)));
                    $col = imagecolorallocatealpha($img, $new, $new, $new, $a);
                    imagesetpixel($img, $x, $y, $col);
                }
            }
        }

        ob_start();
        imagepng($img);
        $out = ob_get_clean();
        imagedestroy($img);
        return $out ? base64_encode($out) : $b64;
    };
    $baseDir = dirname(__DIR__);
    $logoPath = $baseDir . '/img/seminuevos.jpg';
    $logoImg = '';
    if (is_file($logoPath)) {
        $logoData = @file_get_contents($logoPath);
        if ($logoData !== false) {
            $logoB64 = base64_encode($logoData);
            $logoImg = '<img src="data:image/jpeg;base64,' . $logoB64 . '" alt="AUTOMARKET SEMINUEVOS" style="height:52px;width:auto;display:block;" />';
        }
    }

    $secHeader = '#1e3a5f';
    $bloqueSec = function($titulo, $pares) use ($h, $secHeader) {
        $r = '<tr><td colspan="2" style="background:' . $secHeader . ';color:#fff;padding:6px 8px;font-weight:bold;font-size:11px;">' . $titulo . '</td></tr>';
        foreach ($pares as $k => $v) {
            if ((string)$v === '') continue;
            $r .= '<tr><td style="width:38%;padding:5px 8px;border-bottom:1px solid #ddd;">' . $k . '</td><td style="padding:5px 8px;border-bottom:1px solid #ddd;">' . $v . '</td></tr>';
        }
        return $r;
    };

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#000;margin:0;padding:0;width:100%;}
        .pdf-wrap{width:100%;}
        .pdf-title{font-size:14px;font-weight:bold;margin:0 0 6px 0;}
        .banner{background:' . $secHeader . ';color:#fff;padding:8px 10px;text-align:center;font-weight:bold;font-size:11px;margin:10px 0;width:100%;}
        table{width:100%;border-collapse:collapse;} td,th{border:1px solid #ccc;padding:5px 8px;text-align:left;}
        .firma{
            max-width:280px;
            max-height:120px;
            background:#fff;
            border:1px solid #d1d5db;
            /* Dompdf puede ignorar filtros avanzados; se mantiene por compatibilidad */
            filter: contrast(260%) brightness(55%) grayscale(100%);
        }
        .grid2{width:100%;border-collapse:collapse;font-size:10px;}
        .grid2 td{padding:4px 8px;border:1px solid #ddd;vertical-align:top;}
        .header-row{width:100%;border:none;}
        .header-row td{border:none;padding:0;vertical-align:top;}
        .header-left{width:100%;}
        .header-logo{text-align:right;white-space:nowrap;}
        .footer-note{font-size:8px;color:#555;margin-top:14px;line-height:1.3;}
    </style></head><body>';
    $html .= '<div class="pdf-wrap">';
    if ($logoImg !== '') {
        $html .= '<table class="header-row"><tr><td style="width:100%;border:none;padding:0;"></td><td class="header-logo" style="border:none;padding:0;vertical-align:top;">' . $logoImg . '</td></tr></table>';
    }
    $html .= '<div class="banner">Análisis de Perfil para Financiamientos de Bancos</div>';
    $html .= '<table>';

    $html .= $bloqueSec('A. INFORMACIÓN DEL CLIENTE:', [
        'NOMBRE Y APELLIDO:' => $h($input['cliente_nombre'] ?? ''),
        'ESTADO CIVIL:' => $h($input['cliente_estado_civil'] ?? ''),
        'CÉDULA/PASAPORTE/RUC:' => $h($input['cliente_id'] ?? ''),
        'FECHA DE NACIMIENTO:' => $h($input['cliente_nacimiento'] ?? ''),
        'EDAD:' => $h($input['cliente_edad'] ?? ''),
        'SEXO:' => $h($input['cliente_sexo'] ?? ''),
        'NACIONALIDAD:' => $h($input['cliente_nacionalidad'] ?? ''),
        'DEPENDIENTES:' => $h($input['cliente_dependientes'] ?? ''),
        'PESO (lbs):' => $h($input['cliente_peso'] ?? ''),
        'ESTATURA (m):' => $h($input['cliente_estatura'] ?? ''),
        'CORREO:' => $h($input['cliente_correo'] ?? ''),
    ]);

    $vivienda = $h($input['vivienda'] ?? '');
    if (isset($input['vivienda_monto']) && (string)$input['vivienda_monto'] !== '') {
        $vivienda .= ' — MONTO $: ' . $h($input['vivienda_monto']);
    }
    $html .= $bloqueSec('B. DIRECCIÓN RESIDENCIAL:', [
        'PROPIA / HIPOTECADA / ALQUILADA — MONTO $:' => $vivienda,
        'PROVINCIA, DISTRITO, CORREGIMIENTO:' => $h($input['prov_dist_corr'] ?? ''),
        'BARRIADA, No. CALLE, CASA No.:' => $h($input['barriada_calle_casa'] ?? ''),
        'EDIFICIO, APARTAMENTO No.:' => $h($input['edificio_apto'] ?? ''),
        'TELÉFONO DE RESIDENCIA:' => $h($input['tel_residencia'] ?? ''),
        'CELULAR:' => $h($input['celular_cliente'] ?? ''),
        'CORREO ELECTRÓNICO:' => $h($input['cliente_correo'] ?? $input['correo_residencial'] ?? ''),
    ]);

    $html .= $bloqueSec('C. INFORMACIÓN LABORAL:', [
        'NOMBRE DE LA EMPRESA:' => $h($input['empresa_nombre'] ?? ''),
        'OCUPACIÓN:' => $h($input['empresa_ocupacion'] ?? ''),
        'AÑOS DE SERVICIO:' => $h($input['empresa_anios'] ?? ''),
        'DIRECCIÓN:' => $h($input['empresa_direccion'] ?? ''),
        'TELÉFONO:' => $h($input['empresa_telefono'] ?? ''),
        'SALARIO:' => $h($input['empresa_salario'] ?? ''),
        'INDEPENDIENTE Y/O OTROS INGRESOS — OCUPACIÓN:' => $h($input['otros_ingresos'] ?? ''),
        'TRABAJO ANTERIOR SI TIENE MENOS DE 2 AÑOS:' => $h($input['trabajo_anterior'] ?? ''),
    ]);

    $hayConyuge = !empty($input['con_nombre']) || !empty($input['con_id']);
    if ($hayConyuge) {
        $html .= $bloqueSec('D. SOLICITANTE ADICIONAL Y/O CÓNYUGE:', [
            'NOMBRE Y APELLIDO:' => $h($input['con_nombre'] ?? ''),
            'ESTADO CIVIL:' => $h($input['con_estado_civil'] ?? ''),
            'CÉDULA/PASAPORTE/RUC:' => $h($input['con_id'] ?? ''),
            'FECHA DE NACIMIENTO:' => $h($input['con_nacimiento'] ?? ''),
            'EDAD:' => $h($input['con_edad'] ?? ''),
            'SEXO:' => $h($input['con_sexo'] ?? ''),
            'NACIONALIDAD:' => $h($input['con_nacionalidad'] ?? ''),
            'DEPENDIENTES:' => $h($input['con_dependientes'] ?? ''),
            'CORREO:' => $h($input['con_correo'] ?? ''),
            'NOMBRE DE LA EMPRESA:' => $h($input['con_empresa'] ?? ''),
            'OCUPACIÓN:' => $h($input['con_ocupacion'] ?? ''),
            'AÑOS DE SERVICIO:' => $h($input['con_anios'] ?? ''),
            'DIRECCIÓN:' => $h($input['con_direccion'] ?? ''),
            'TELÉFONO / CELULAR:' => $h($input['con_tel'] ?? ''),
            'SALARIO:' => $h($input['con_salario'] ?? ''),
            'INDEPENDIENTE Y/O OTROS INGRESOS — OCUPACIÓN:' => $h($input['con_otros_ingresos'] ?? ''),
            'TRABAJO ANTERIOR SI TIENE MENOS DE 2 AÑOS:' => $h($input['con_trabajo_anterior'] ?? ''),
        ]);
    }

    $html .= $bloqueSec('E. REFERENCIAS — 2 REF PERSONALES (NO PARIENTES):', [
        '1. NOMBRE COMPLETO:' => $h($input['refp1_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['refp1_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['refp1_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['refp1_cel'] ?? ''),
        '2. NOMBRE COMPLETO:' => $h($input['refp2_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['refp2_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['refp2_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['refp2_cel'] ?? ''),
    ]);
    $html .= $bloqueSec('2 REF FAMILIARES (QUE NO VIVAN CON USTED):', [
        '1. NOMBRE COMPLETO:' => $h($input['reff1_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['reff1_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['reff1_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['reff1_cel'] ?? ''),
        '2. NOMBRE COMPLETO:' => $h($input['reff2_nombre'] ?? ''),
        '   DIR. RESIDENCIAL:' => $h($input['reff2_dir_res'] ?? ''),
        '   LUGAR DONDE LABORA (EMPRESA/MINISTERIO):' => $h($input['reff2_dir_lab'] ?? ''),
        '   CELULAR:' => $h($input['reff2_cel'] ?? ''),
    ]);

    $html .= '</table>';
    $html .= '<div style="margin-top:10px;page-break-inside:avoid;">';
    $html .= '<p class="footer-note">Con la firma de esta solicitud, autorizo a PANAMA CAR RENTAL, S.A., MULTIBANK, INC., THE BANK OF NOVA SCOTIA (PANAMÁ), S.A., BANCO GENERAL, S.A., GLOBAL BANK CORPORATION, BAC International Bank, Inc., BANISTMO, S.A., BANCO DELTA, S.A., BANESCO (Panamá), S.A., BANISI, S.A., MULTIFINANCIAMIENTOS, S.A., FOSTRIAN Apoyo Financiera, CORPORACION DE CREDITO, S.A., CORPORACION DE FINANZAS DEL PAIS, S.A., FINANCIERA PACIFICO, DAVIVIENDA, ALIADO LEASING, CENTRO FINANCIERO EMPRESARIAL, SUMA FINANCIERA; a solicitar, consultar, recopilar, transmitir y revelar cualquier información, datos y documentos brindados en esta solicitud; se trate, comparta, transfiera, intercambie y utilice con terceros, ya sea que se concluya o no la adquisición del producto o servicio.</p>';
    $html .= '<p class="footer-note">En cumplimiento de lo establecido en la Ley 81 de 2019 de Protección de Datos Personales, le comunicamos que los datos que usted nos facilite quedarán incorporados y serán tratados en nuestra base de datos con el fin de poderle prestar nuevos servicios, así como para mantenerle informado sobre temas relacionados con la empresa y sus servicios. Por este medio exonero expresamente a PANAMA CAR RENTAL, S.A. y/o a sus afiliadas, empleados, ejecutivos, dignatarios y apoderados, de cualquier consecuencia o responsabilidad resultante del ejercicio que ustedes hagan el derecho a solicitar o suministrar información o por razón de cualquier autorización de la presente.</p>';

    $firmaPdf = $enhanceSignatureBase64($firmaBase64);
    if ($firmaPdf !== '' && $firmaPdf !== null) {
        $html .= '<table style="margin-top:8px;"><tr><td style="background:#eee;padding:6px 8px;font-weight:bold">Firma del solicitante</td></tr>';
        $html .= '<tr><td style="padding:10px;"><img class="firma" src="data:image/png;base64,' . $firmaPdf . '" alt="Firma"/></td></tr></table>';
    }
    $firmantesExtra = isset($input['firmantes_adicionales']) ? $input['firmantes_adicionales'] : '';
    if ($firmantesExtra !== '') {
        $lista = @json_decode($firmantesExtra, true);
        if (is_array($lista)) {
            foreach ($lista as $fa) {
                $nom = isset($fa['nombre']) ? $h($fa['nombre']) : '';
                $img = isset($fa['firma']) && $fa['firma'] !== '' ? $fa['firma'] : null;
                if ($nom !== '' && $img !== null) {
                    $imgPdf = $enhanceSignatureBase64($img);
                    $html .= '<table style="margin-top:6px;"><tr><td style="background:#eee;padding:6px 8px;font-weight:bold">Firma: ' . $nom . '</td></tr>';
                    $html .= '<tr><td style="padding:10px;"><img class="firma" src="data:image/png;base64,' . $imgPdf . '" alt="Firma ' . $nom . '"/></td></tr></table>';
                }
            }
        }
    }
    $firmaPath = $baseDir . '/img/firma.jpg';
    if (is_file($firmaPath)) {
        $firmaImgData = @file_get_contents($firmaPath);
        if ($firmaImgData !== false) {
            $firmaB64 = base64_encode($firmaImgData);
            $html .= '<div style="margin-top:14px;text-align:right;"><img src="data:image/jpeg;base64,' . $firmaB64 . '" alt="Firma" style="max-height:90px;width:auto;" /></div>';
        }
    }
    $html .= '</div>';
    $html .= '</div></body></html>';
    return $html;
}
