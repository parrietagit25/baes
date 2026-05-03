<?php
/**
 * API de reportes (solo administrador)
 */

session_start();
$action = $_GET['action'] ?? '';

if (!isset($_SESSION['user_id']) || !in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($action === 'exportar_todos_excel') {
    exportarTodosReportesExcel();
    exit();
}
if (in_array($action, [
    'exportar_excel_usuarios',
    'exportar_excel_vendedores',
    'exportar_excel_tiempo',
    'exportar_excel_banco',
    'exportar_excel_correos',
    'exportar_excel_encuestas_vendedores',
    'exportar_excel_encuestas_gestores',
    'exportar_excel_telemetria',
    'exportar_excel_fin_publica',
    'exportar_excel_fin_enlazada',
], true)) {
    exportarReporteCsv($action);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'reporte_usuarios':
        reporteUsuarios();
        break;
    case 'reporte_vendedores':
        reporteVendedores();
        break;
    case 'solicitudes_usuario_estado':
        solicitudesPorUsuarioEstado();
        break;
    case 'solicitudes_vendedor_estado':
        solicitudesPorVendedorEstado();
        break;
    case 'reporte_tiempo':
        reporteTiempo();
        break;
    case 'historial_solicitud':
        historialSolicitud();
        break;
    case 'reporte_banco':
        reporteBanco();
        break;
    case 'reporte_emails_resumen':
        reporteEmailsResumen();
        break;
    case 'reporte_encuestas':
        reporteEncuestas();
        break;
    case 'reporte_telemetria':
        reporteTelemetria();
        break;
    case 'reporte_fin_publica_demografia':
        reporteFinPublicaDemografia();
        break;
    case 'reporte_fin_publica_enlazada':
        reporteFinPublicaEnlazada();
        break;
    case 'exportar_todos_excel':
        // Ya atendido al inicio.
        echo json_encode(['success' => false, 'message' => 'Acción ya ejecutada']);
        break;
    case 'exportar_excel_usuarios':
    case 'exportar_excel_vendedores':
    case 'exportar_excel_tiempo':
    case 'exportar_excel_banco':
    case 'exportar_excel_correos':
    case 'exportar_excel_encuestas_vendedores':
    case 'exportar_excel_encuestas_gestores':
    case 'exportar_excel_telemetria':
    case 'exportar_excel_fin_publica':
    case 'exportar_excel_fin_enlazada':
        // Ya atendido al inicio.
        echo json_encode(['success' => false, 'message' => 'Acción ya ejecutada']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function exportarReporteCsv(string $action): void {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';

    if ($action === 'exportar_excel_usuarios') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['usuario_id'] ?? '',
                $r['nombre'] ?? '',
                $r['email'] ?? '',
                $r['Nueva'] ?? 0,
                $r['En Revisión Banco'] ?? 0,
                $r['Aprobada'] ?? 0,
                $r['Rechazada'] ?? 0,
                $r['Completada'] ?? 0,
                $r['Desistimiento'] ?? 0,
                $r['total'] ?? 0,
            ];
        }, _dataReporteUsuarios($pdo));
        _outputXlsxDownload('reporte_usuarios.xlsx', 'Rep Usuarios', [
            'Usuario ID', 'Nombre', 'Email', 'Nueva', 'En Revision Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento', 'Total'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_vendedores') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['vendedor_id'] ?? '',
                $r['nombre'] ?? '',
                $r['email'] ?? '',
                $r['Nueva'] ?? 0,
                $r['En Revisión Banco'] ?? 0,
                $r['Aprobada'] ?? 0,
                $r['Rechazada'] ?? 0,
                $r['Completada'] ?? 0,
                $r['Desistimiento'] ?? 0,
                $r['total'] ?? 0,
            ];
        }, _dataReporteVendedores($pdo));
        _outputXlsxDownload('reporte_vendedores.xlsx', 'Rep Vendedores', [
            'Vendedor ID', 'Nombre', 'Email', 'Nueva', 'En Revision Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento', 'Total'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_tiempo') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['cedula'] ?? '',
                $r['estado'] ?? '',
                $r['fecha_creacion'] ?? '',
                $r['fecha_actualizacion'] ?? '',
                $r['dias_en_estado_actual'] ?? '',
                $r['horas_en_estado_actual'] ?? '',
            ];
        }, _dataReporteTiempo($pdo));
        _outputXlsxDownload('reporte_tiempo.xlsx', 'Rep Tiempo', [
            'Solicitud ID', 'Cliente', 'Cedula', 'Estado', 'Fecha Creacion', 'Fecha Actualizacion', 'Dias Estado Actual', 'Horas Estado Actual'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_banco') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['solicitud_id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['cedula'] ?? '',
                $r['estado'] ?? '',
                $r['banco_nombre'] ?? '',
                $r['fecha_asignacion'] ?? '',
                $r['fecha_respuesta'] ?? '',
                !empty($r['pendiente']) ? 'Si' : 'No',
                $r['dias_respuesta'] ?? '',
                $r['horas_respuesta'] ?? '',
            ];
        }, _dataReporteBanco($pdo));
        _outputXlsxDownload('reporte_banco.xlsx', 'Rep Banco', [
            'Solicitud ID', 'Cliente', 'Cedula', 'Estado Solicitud', 'Banco', 'Fecha Asignacion', 'Fecha Respuesta', 'Pendiente', 'Dias Respuesta', 'Horas Respuesta'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_correos') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['solicitud_id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['destinatario_email'] ?? '',
                $r['tipo_envio'] ?? '',
                $r['estado'] ?? '',
                $r['provider'] ?? '',
                $r['provider_message_id'] ?? '',
                $r['mensaje'] ?? '',
                $r['fecha_envio'] ?? '',
            ];
        }, _dataReporteEmails($pdo));
        _outputXlsxDownload('reporte_correos.xlsx', 'Rep Correos', [
            'ID', 'Solicitud ID', 'Cliente', 'Destinatario', 'Tipo Envio', 'Estado', 'Provider', 'Provider Message ID', 'Mensaje', 'Fecha Envio'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_encuestas_vendedores') {
        $enc = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['creado_en'] ?? '',
                $r['nombre_completo'] ?? '',
                $r['cargo'] ?? '',
                $r['puntuacion_1'] ?? '',
                $r['puntuacion_2'] ?? '',
                $r['puntuacion_3'] ?? '',
                $r['puntuacion_4'] ?? '',
                $r['puntuacion_5'] ?? '',
                $r['promedio_fila'] ?? '',
                $r['recomendaciones'] ?? '',
            ];
        }, $enc['filas'] ?? []);
        _outputXlsxDownload('reporte_encuestas_vendedores.xlsx', 'Enc Vendedores', [
            'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_encuestas_gestores') {
        $enc = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['creado_en'] ?? '',
                $r['nombre_completo'] ?? '',
                $r['cargo'] ?? '',
                $r['puntuacion_1'] ?? '',
                $r['puntuacion_2'] ?? '',
                $r['puntuacion_3'] ?? '',
                $r['puntuacion_4'] ?? '',
                $r['puntuacion_5'] ?? '',
                $r['promedio_fila'] ?? '',
                $r['recomendaciones'] ?? '',
            ];
        }, $enc['filas'] ?? []);
        _outputXlsxDownload('reporte_encuestas_gestores.xlsx', 'Enc Gestores', [
            'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_fin_publica') {
        require_once __DIR__ . '/../includes/reportes_fin_demografia_data.php';
        $filt = rep_fin_parse_filtros();
        $rows = rep_fin_filas_export_publica($pdo, $filt);
        _outputXlsxDownload('reporte_fin_publica_demografia.xlsx', 'Sol Publica', [
            'ID', 'Fecha', 'Cliente', 'Sexo', 'Genero agrupado', 'Edad calc', 'Rango edad', 'Salario USD', 'Rango salario', 'Perfil estimado', 'Sector estimado',
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_fin_enlazada') {
        require_once __DIR__ . '/../includes/reportes_fin_demografia_data.php';
        $filt = rep_fin_parse_filtros();
        [$headers, $rows] = rep_fin_filas_export_enlazada($pdo, $filt);
        if ($headers === []) {
            _outputXlsxDownload('reporte_fin_enlazada.xlsx', 'Info', [
                'Mensaje',
            ], [['Ejecute migracion solicitud_financiamiento_registro_id o no hay columna financiamiento_registro_id.']]);
            return;
        }
        _outputXlsxDownload('reporte_fin_publica_enlazada.xlsx', 'Publica Motus', $headers, $rows);
        return;
    }

    if ($action === 'exportar_excel_telemetria') {
        $rowsData = _dataReporteTelemetria($pdo);
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['fecha_creacion'] ?? '',
                $r['cliente_nombre'] ?? '',
                $r['cliente_id'] ?? '',
                $r['celular_cliente'] ?? '',
                $r['cliente_correo'] ?? '',
                $r['ip'] ?? '',
                $r['geo_country'] ?? '',
                $r['geo_city'] ?? '',
                $r['telemetria_session_id'] ?? '',
                $r['telemetria_started_at'] ?? '',
                $r['telemetria_submitted_at'] ?? '',
                $r['telemetria_duracion_segundos'] ?? '',
                $r['paso0_seg'] ?? '',
                $r['paso1_seg'] ?? '',
                $r['paso2_seg'] ?? '',
                $r['paso3_seg'] ?? '',
                $r['paso4_seg'] ?? '',
                $r['platform'] ?? '',
                $r['timezone'] ?? '',
                $r['viewport'] ?? '',
                $r['screen'] ?? '',
            ];
        }, $rowsData);
        _outputXlsxDownload('reporte_telemetria.xlsx', 'Rep Telemetria', [
            'ID', 'Fecha Registro', 'Cliente', 'Cedula', 'Celular', 'Email', 'IP', 'Pais', 'Ciudad',
            'Sesion', 'Inicio', 'Fin', 'Duracion Seg',
            'Paso A Seg', 'Paso B Seg', 'Paso C Seg', 'Paso D Seg', 'Paso E Seg',
            'Plataforma', 'Timezone', 'Viewport', 'Pantalla'
        ], $rows);
        return;
    }
}

function _outputXlsxDownload(string $fileName, string $sheetName, array $headers, array $rows): void {
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmp === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo crear archivo temporal XLSX']);
        return;
    }
    $xlsxPath = $tmp . '.xlsx';
    @rename($tmp, $xlsxPath);

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($xlsxPath);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo generar XLSX']);
        return;
    }

    $allRows = array_merge([$headers], $rows);
    $sheetXml = _xlsxBuildSheetXml($allRows);
    $safeSheetName = _xlsxSafeSheetName($sheetName);

    $zip->addFromString('[Content_Types].xml', _xlsxContentTypesXml());
    $zip->addFromString('_rels/.rels', _xlsxRootRelsXml());
    $zip->addFromString('docProps/app.xml', _xlsxAppXml());
    $zip->addFromString('docProps/core.xml', _xlsxCoreXml());
    $zip->addFromString('xl/workbook.xml', _xlsxWorkbookXml($safeSheetName));
    $zip->addFromString('xl/_rels/workbook.xml.rels', _xlsxWorkbookRelsXml());
    $zip->addFromString('xl/styles.xml', _xlsxStylesXml());
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) filesize($xlsxPath));
    readfile($xlsxPath);
    @unlink($xlsxPath);
}

function _xlsxSafeSheetName(string $name): string {
    $n = preg_replace('/[\\\\\\/*?:\\[\\]]/', ' ', $name) ?? 'Hoja1';
    $n = trim($n);
    if ($n === '') {
        $n = 'Hoja1';
    }
    if (function_exists('mb_substr')) {
        $n = mb_substr($n, 0, 31);
    } else {
        $n = substr($n, 0, 31);
    }
    return $n;
}

function _xlsxEsc(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function _xlsxColLetter(int $col): string {
    $letter = '';
    while ($col > 0) {
        $mod = ($col - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $col = intdiv($col - 1, 26);
    }
    return $letter;
}

function _xlsxBuildSheetXml(array $rows): string {
    $xmlRows = '';
    $rowNum = 1;
    foreach ($rows as $row) {
        $xmlCells = '';
        $colNum = 1;
        foreach ($row as $value) {
            $cellRef = _xlsxColLetter($colNum) . $rowNum;
            $style = ($rowNum === 1) ? ' s="1"' : '';
            if ($value === null) {
                $value = '';
            }
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)))) {
                $num = is_string($value) ? str_replace(',', '.', trim($value)) : (string) $value;
                $xmlCells .= '<c r="' . $cellRef . '"' . $style . '><v>' . _xlsxEsc((string) $num) . '</v></c>';
            } else {
                $xmlCells .= '<c r="' . $cellRef . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . _xlsxEsc((string) $value) . '</t></is></c>';
            }
            $colNum++;
        }
        $xmlRows .= '<row r="' . $rowNum . '">' . $xmlCells . '</row>';
        $rowNum++;
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetData>' . $xmlRows . '</sheetData>'
        . '</worksheet>';
}

function _xlsxContentTypesXml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';
}

function _xlsxRootRelsXml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function _xlsxAppXml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Motus</Application>'
        . '</Properties>';
}

function _xlsxCoreXml(): string {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:creator>Motus</dc:creator>'
        . '<cp:lastModifiedBy>Motus</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function _xlsxWorkbookXml(string $sheetName): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . _xlsxEsc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function _xlsxWorkbookRelsXml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function _xlsxStylesXml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function exportarTodosReportesExcel() {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';
    $tmpZip = tempnam(sys_get_temp_dir(), 'rep_motus_');
    if ($tmpZip === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo crear archivo temporal']);
        return;
    }

    $zipPath = $tmpZip . '.zip';
    @rename($tmpZip, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo crear ZIP de exportación']);
        return;
    }

    $usuarios = _dataReporteUsuarios($pdo);
    _zipAddCsv($zip, 'reporte_usuarios.csv', [
        'Usuario ID', 'Nombre', 'Email', 'Nueva', 'En Revision Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento', 'Total'
    ], array_map(static function(array $r): array {
        return [
            $r['usuario_id'] ?? '',
            $r['nombre'] ?? '',
            $r['email'] ?? '',
            $r['Nueva'] ?? 0,
            $r['En Revisión Banco'] ?? 0,
            $r['Aprobada'] ?? 0,
            $r['Rechazada'] ?? 0,
            $r['Completada'] ?? 0,
            $r['Desistimiento'] ?? 0,
            $r['total'] ?? 0,
        ];
    }, $usuarios));

    $tiempo = _dataReporteTiempo($pdo);
    _zipAddCsv($zip, 'reporte_tiempo.csv', [
        'Solicitud ID', 'Cliente', 'Cedula', 'Estado', 'Fecha Creacion', 'Fecha Actualizacion', 'Dias Estado Actual', 'Horas Estado Actual'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['cedula'] ?? '',
            $r['estado'] ?? '',
            $r['fecha_creacion'] ?? '',
            $r['fecha_actualizacion'] ?? '',
            $r['dias_en_estado_actual'] ?? '',
            $r['horas_en_estado_actual'] ?? '',
        ];
    }, $tiempo));

    $banco = _dataReporteBanco($pdo);
    _zipAddCsv($zip, 'reporte_banco.csv', [
        'Solicitud ID', 'Cliente', 'Cedula', 'Estado Solicitud', 'Banco', 'Fecha Asignacion', 'Fecha Respuesta', 'Pendiente', 'Dias Respuesta', 'Horas Respuesta'
    ], array_map(static function(array $r): array {
        return [
            $r['solicitud_id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['cedula'] ?? '',
            $r['estado'] ?? '',
            $r['banco_nombre'] ?? '',
            $r['fecha_asignacion'] ?? '',
            $r['fecha_respuesta'] ?? '',
            !empty($r['pendiente']) ? 'Si' : 'No',
            $r['dias_respuesta'] ?? '',
            $r['horas_respuesta'] ?? '',
        ];
    }, $banco));

    $emails = _dataReporteEmails($pdo);
    _zipAddCsv($zip, 'reporte_correos.csv', [
        'ID', 'Solicitud ID', 'Cliente', 'Destinatario', 'Tipo Envio', 'Estado', 'Provider', 'Provider Message ID', 'Mensaje', 'Fecha Envio'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['solicitud_id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['destinatario_email'] ?? '',
            $r['tipo_envio'] ?? '',
            $r['estado'] ?? '',
            $r['provider'] ?? '',
            $r['provider_message_id'] ?? '',
            $r['mensaje'] ?? '',
            $r['fecha_envio'] ?? '',
        ];
    }, $emails));

    $encV = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
    $encG = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);
    _zipAddCsv($zip, 'reporte_encuestas_vendedores.csv', [
        'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['creado_en'] ?? '',
            $r['nombre_completo'] ?? '',
            $r['cargo'] ?? '',
            $r['puntuacion_1'] ?? '',
            $r['puntuacion_2'] ?? '',
            $r['puntuacion_3'] ?? '',
            $r['puntuacion_4'] ?? '',
            $r['puntuacion_5'] ?? '',
            $r['promedio_fila'] ?? '',
            $r['recomendaciones'] ?? '',
        ];
    }, $encV['filas'] ?? []));
    _zipAddCsv($zip, 'reporte_encuestas_gestores.csv', [
        'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['creado_en'] ?? '',
            $r['nombre_completo'] ?? '',
            $r['cargo'] ?? '',
            $r['puntuacion_1'] ?? '',
            $r['puntuacion_2'] ?? '',
            $r['puntuacion_3'] ?? '',
            $r['puntuacion_4'] ?? '',
            $r['puntuacion_5'] ?? '',
            $r['promedio_fila'] ?? '',
            $r['recomendaciones'] ?? '',
        ];
    }, $encG['filas'] ?? []));

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="reportes_motus_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
}

function _zipAddCsv(ZipArchive $zip, string $fileName, array $headers, array $rows): void {
    $fp = fopen('php://temp', 'r+');
    if ($fp === false) {
        return;
    }
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers, ';');
    foreach ($rows as $row) {
        $safe = [];
        foreach ($row as $value) {
            if (is_bool($value)) {
                $safe[] = $value ? '1' : '0';
            } elseif ($value === null) {
                $safe[] = '';
            } else {
                $safe[] = (string) $value;
            }
        }
        fputcsv($fp, $safe, ';');
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    if ($csv !== false) {
        $zip->addFromString($fileName, $csv);
    }
}

function _dataReporteUsuarios(PDO $pdo): array {
    $sql = "
        SELECT 
            u.id as usuario_id,
            u.nombre,
            u.apellido,
            u.email,
            s.estado,
            COUNT(s.id) as total
        FROM usuarios u
        LEFT JOIN solicitudes_credito s ON s.gestor_id = u.id
        WHERE u.activo = 1
          AND EXISTS (
            SELECT 1 FROM usuario_roles ur 
            INNER JOIN roles r ON ur.rol_id = r.id 
            WHERE ur.usuario_id = u.id AND r.nombre IN ('ROLE_GESTOR', 'ROLE_ADMIN')
          )
        GROUP BY u.id, u.nombre, u.apellido, u.email, s.estado
        ORDER BY u.apellido, u.nombre, s.estado
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $porUsuario = [];
    foreach ($rows as $r) {
        $id = $r['usuario_id'];
        if (!isset($porUsuario[$id])) {
            $porUsuario[$id] = [
                'usuario_id' => $id,
                'nombre' => trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
                'email' => $r['email'],
                'Nueva' => 0,
                'En Revisión Banco' => 0,
                'Aprobada' => 0,
                'Rechazada' => 0,
                'Completada' => 0,
                'Desistimiento' => 0,
                'total' => 0,
            ];
        }
        if (!empty($r['estado'])) {
            $porUsuario[$id][$r['estado']] = (int) $r['total'];
            $porUsuario[$id]['total'] += (int) $r['total'];
        }
    }
    return array_values($porUsuario);
}

function _dataReporteVendedores(PDO $pdo): array {
    $sql = "
        SELECT
            ev.id AS vendedor_id,
            ev.nombre,
            ev.email,
            s.estado,
            COUNT(s.id) AS total
        FROM ejecutivos_ventas ev
        LEFT JOIN solicitudes_credito s ON s.ejecutivo_ventas_id = ev.id
        WHERE COALESCE(ev.activo, 1) = 1
        GROUP BY ev.id, ev.nombre, ev.email, s.estado
        ORDER BY ev.nombre, s.estado
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $porVendedor = [];
    foreach ($rows as $r) {
        $id = $r['vendedor_id'];
        if (!isset($porVendedor[$id])) {
            $porVendedor[$id] = [
                'vendedor_id' => $id,
                'nombre' => trim((string)($r['nombre'] ?? '')),
                'email' => $r['email'] ?? '',
                'Nueva' => 0,
                'En Revisión Banco' => 0,
                'Aprobada' => 0,
                'Rechazada' => 0,
                'Completada' => 0,
                'Desistimiento' => 0,
                'total' => 0,
            ];
        }
        if (!empty($r['estado'])) {
            $porVendedor[$id][$r['estado']] = (int) $r['total'];
            $porVendedor[$id]['total'] += (int) $r['total'];
        }
    }
    return array_values($porVendedor);
}

function _dataReporteTiempo(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
        FROM solicitudes_credito s
        ORDER BY s.fecha_actualizacion DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['dias_en_estado_actual'] = null;
        $r['horas_en_estado_actual'] = null;
        if (!empty($r['fecha_actualizacion'])) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(DAY, ?, NOW()) as dias, TIMESTAMPDIFF(HOUR, ?, NOW()) as horas");
            $st->execute([$r['fecha_actualizacion'], $r['fecha_actualizacion']]);
            $d = $st->fetch(PDO::FETCH_ASSOC);
            $r['dias_en_estado_actual'] = (int) ($d['dias'] ?? 0);
            $r['horas_en_estado_actual'] = (int) ($d['horas'] ?? 0);
        }
    }
    unset($r);
    return $rows;
}

function _dataReporteBanco(PDO $pdo): array {
    $sql = "
        SELECT 
            s.id AS solicitud_id,
            s.nombre_cliente,
            s.cedula,
            s.estado,
            b.id AS banco_id,
            b.nombre AS banco_nombre,
            ubs.fecha_asignacion,
            MIN(eb.fecha_evaluacion) AS fecha_respuesta
        FROM solicitudes_credito s
        INNER JOIN usuarios_banco_solicitudes ubs ON ubs.solicitud_id = s.id
        INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
        LEFT JOIN bancos b ON b.id = u.banco_id
        LEFT JOIN evaluaciones_banco eb ON eb.solicitud_id = s.id AND eb.usuario_banco_id = ubs.id
        GROUP BY s.id, s.nombre_cliente, s.cedula, s.estado, b.id, b.nombre, ubs.id, ubs.fecha_asignacion
        ORDER BY ubs.fecha_asignacion DESC
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['dias_respuesta'] = null;
        $r['horas_respuesta'] = null;
        $r['pendiente'] = empty($r['fecha_respuesta']);
        if (!$r['pendiente'] && !empty($r['fecha_asignacion'])) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(DAY, ?, ?) AS dias, TIMESTAMPDIFF(HOUR, ?, ?) AS horas");
            $st->execute([$r['fecha_asignacion'], $r['fecha_respuesta'], $r['fecha_asignacion'], $r['fecha_respuesta']]);
            $d = $st->fetch(PDO::FETCH_ASSOC);
            $r['dias_respuesta'] = (int) ($d['dias'] ?? 0);
            $r['horas_respuesta'] = (int) ($d['horas'] ?? 0);
        }
    }
    unset($r);
    return $rows;
}

function _dataReporteEmails(PDO $pdo): array {
    $sql = "
        SELECT
            l.id, l.solicitud_id, l.usuario_banco_id, l.destinatario_email, l.tipo_envio, l.estado,
            l.provider, l.provider_message_id, l.mensaje, l.fecha_envio,
            s.nombre_cliente
        FROM email_resumen_banco_log l
        LEFT JOIN solicitudes_credito s ON s.id = l.solicitud_id
        ORDER BY l.fecha_envio DESC
        LIMIT 1000
    ";
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1146) {
            return [];
        }
        throw $e;
    }
}

function _fin_reg_has_telemetria_geo_columns(PDO $pdo): bool
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $memo = false;
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME IN (?, ?)'
        );
        $st->execute(['financiamiento_registros', 'telemetria_geo_country', 'telemetria_geo_city']);
        $memo = (int) $st->fetchColumn() >= 2;
    } catch (PDOException $e) {
        $memo = false;
    }

    return $memo;
}

/**
 * Guarda país/ciudad por IP en el registro solo si aún estaban vacíos (evita pisar datos existentes).
 */
function _telemetria_geo_persist_if_empty(PDO $pdo, int $id, string $country, string $city): void
{
    if ($id <= 0) {
        return;
    }
    if ($country === '' && $city === '') {
        return;
    }
    try {
        $st = $pdo->prepare(
            'UPDATE financiamiento_registros SET telemetria_geo_country = ?, telemetria_geo_city = ?
             WHERE id = ?
               AND (telemetria_geo_country IS NULL OR TRIM(telemetria_geo_country) = \'\')
               AND (telemetria_geo_city IS NULL OR TRIM(telemetria_geo_city) = \'\')'
        );
        $st->execute([$country, $city, $id]);
    } catch (PDOException $e) {
        // Columna inexistente u otro error: no fallar el reporte
    }
}

function _dataReporteTelemetria(PDO $pdo): array {
    $hasGeoCols = _fin_reg_has_telemetria_geo_columns($pdo);
    $geoSelect = $hasGeoCols ? ', telemetria_geo_country, telemetria_geo_city' : '';
    $sql = '
        SELECT
            id, fecha_creacion, cliente_nombre, cliente_id, celular_cliente, cliente_correo, ip,
            telemetria_session_id, telemetria_started_at, telemetria_submitted_at, telemetria_duracion_segundos,
            telemetria_paso_tiempos_json, telemetria_dispositivo_json
            ' . $geoSelect . '
        FROM financiamiento_registros
        WHERE telemetria_session_id IS NOT NULL
          AND TRIM(telemetria_session_id) <> \'\'
        ORDER BY fecha_creacion DESC
        LIMIT 5000
    ';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $lookupCount = 0;
    $lookupMax = 400; // solo IPs sin dato persistido ni caché archivo; la mayoría vendrán de BD tras primer reporte
    $geoCache = _geoCacheReadFromFile();
    $geoResultByIp = [];
    foreach ($rows as &$r) {
        $steps = json_decode((string)($r['telemetria_paso_tiempos_json'] ?? ''), true);
        if (!is_array($steps)) $steps = [];
        for ($i = 0; $i <= 4; $i++) {
            $ms = isset($steps[(string)$i]) && is_numeric($steps[(string)$i]) ? (int)$steps[(string)$i] : 0;
            $r['paso' . $i . '_seg'] = $ms > 0 ? (int) floor($ms / 1000) : 0;
        }
        $dev = json_decode((string)($r['telemetria_dispositivo_json'] ?? ''), true);
        if (!is_array($dev)) $dev = [];
        $r['platform'] = (string)($dev['platform'] ?? '');
        $r['timezone'] = (string)($dev['timezone'] ?? '');
        $r['viewport'] = (string)($dev['viewport'] ?? '');
        $r['screen'] = (string)($dev['screen'] ?? '');
        $ua = (string)($dev['user_agent'] ?? '');
        $info = _telemetriaDeviceInfoFromUa($ua);
        $r['device_os'] = $info['os'];
        $r['device_brand'] = $info['brand'];
        $r['device_model'] = $info['model'];
        $r['device_label'] = trim(implode(' ', array_filter([$r['device_os'], $r['device_brand'], $r['device_model']])));
        if ($r['device_label'] === '') {
            $r['device_label'] = trim((string)($r['platform'] ?? ''));
        }

        $r['geo_country'] = '';
        $r['geo_city'] = '';
        $ip = trim((string) ($r['ip'] ?? ''));
        $dbCountry = $hasGeoCols ? trim((string) ($r['telemetria_geo_country'] ?? '')) : '';
        $dbCity = $hasGeoCols ? trim((string) ($r['telemetria_geo_city'] ?? '')) : '';
        $rowHadStoredGeo = ($dbCountry !== '' || $dbCity !== '');

        if ($rowHadStoredGeo) {
            $r['geo_country'] = $dbCountry;
            $r['geo_city'] = $dbCity;
            if ($ip !== '' && _isPublicIpForGeo($ip)) {
                $geoResultByIp[$ip] = ['country' => $dbCountry, 'city' => $dbCity];
            }
        } elseif ($ip !== '' && _isPublicIpForGeo($ip)) {
            if (!isset($geoResultByIp[$ip])) {
                $geoResultByIp[$ip] = _geoLookupByIpCached($ip, $lookupCount < $lookupMax, $geoCache);
                if (!empty($geoResultByIp[$ip]['from_lookup'])) {
                    $lookupCount++;
                }
            }
            $geo = $geoResultByIp[$ip];
            if (!empty($geo['country'])) {
                $r['geo_country'] = (string) $geo['country'];
            }
            if (!empty($geo['city'])) {
                $r['geo_city'] = (string) $geo['city'];
            }
            if ($hasGeoCols && ($r['geo_country'] !== '' || $r['geo_city'] !== '')) {
                _telemetria_geo_persist_if_empty($pdo, (int) ($r['id'] ?? 0), $r['geo_country'], $r['geo_city']);
            }
        }
    }
    unset($r);
    return $rows;
}

function _isPublicIpForGeo(string $ip): bool {
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function _geoCacheFilePath(): string {
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'motus_geo_ip_cache.json';
}

/**
 * Lee la caché en disco (sin static): cada request debe usar un array mutable compartido por referencia
 * para que las IPs resueltas en esta petición queden visibles en el resto del bucle.
 */
function _geoCacheReadFromFile(): array {
    $path = _geoCacheFilePath();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function _geoCacheSave(array $cache): void {
    $path = _geoCacheFilePath();
    @file_put_contents($path, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * @param array<string, array<string, mixed>> $cache (por referencia) caché disco + entradas nuevas en esta petición
 * @return array{country:string,city:string,from_lookup?:bool}
 */
function _geoLookupByIpCached(string $ip, bool $allowLookup, array &$cache): array {
    $key = trim($ip);
    if ($key === '') {
        return ['country' => '', 'city' => ''];
    }
    $now = time();
    $ttl = 7 * 24 * 3600; // 7 días
    if (isset($cache[$key]) && is_array($cache[$key])) {
        $row = $cache[$key];
        $ts = isset($row['ts']) ? (int) $row['ts'] : 0;
        if ($ts > 0 && ($now - $ts) <= $ttl) {
            return [
                'country' => (string) ($row['country'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
            ];
        }
    }
    if (!$allowLookup) {
        return ['country' => '', 'city' => ''];
    }

    $url = 'https://ipwho.is/' . rawurlencode($key);
    $ctx = stream_context_create([
        'http' => ['timeout' => 2, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || trim($raw) === '') {
        return ['country' => '', 'city' => ''];
    }
    $d = json_decode($raw, true);
    if (!is_array($d) || empty($d['success'])) {
        return ['country' => '', 'city' => ''];
    }
    $country = trim((string) ($d['country'] ?? ''));
    $city = trim((string) ($d['city'] ?? ''));
    $cache[$key] = ['country' => $country, 'city' => $city, 'ts' => $now];
    _geoCacheSave($cache);

    return ['country' => $country, 'city' => $city, 'from_lookup' => true];
}

/**
 * Detección básica de OS/marca/modelo desde user-agent.
 * Sin librerías externas para mantener compatibilidad.
 *
 * @return array{os:string,brand:string,model:string}
 */
function _telemetriaDeviceInfoFromUa(string $ua): array {
    $ua = trim($ua);
    if ($ua === '') {
        return ['os' => '', 'brand' => '', 'model' => ''];
    }
    $os = '';
    if (stripos($ua, 'iPhone') !== false) {
        $os = 'iPhone (iOS)';
    } elseif (stripos($ua, 'iPad') !== false) {
        $os = 'iPad (iOS)';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    $brand = '';
    $model = '';
    if ($os === 'iPhone (iOS)' || $os === 'iPad (iOS)') {
        $brand = 'Apple';
        $model = $os === 'iPad (iOS)' ? 'iPad' : 'iPhone';
        return ['os' => $os, 'brand' => $brand, 'model' => $model];
    }
    if ($os === 'Android') {
        if (preg_match('/Android\s+[0-9\.]+;\s*([^;\)]+)/i', $ua, $m)) {
            $rawModel = trim((string)$m[1]);
            $model = preg_replace('/\s+Build.*$/i', '', $rawModel) ?? $rawModel;
        }
        $uaUp = strtoupper($ua);
        if (strpos($uaUp, 'SAMSUNG') !== false || strpos($uaUp, 'SM-') !== false) $brand = 'Samsung';
        elseif (strpos($uaUp, 'HUAWEI') !== false || strpos($uaUp, 'HONOR') !== false) $brand = 'Huawei/Honor';
        elseif (strpos($uaUp, 'XIAOMI') !== false || strpos($uaUp, 'REDMI') !== false || strpos($uaUp, 'MI ') !== false) $brand = 'Xiaomi';
        elseif (strpos($uaUp, 'MOTOROLA') !== false || strpos($uaUp, 'MOTO') !== false) $brand = 'Motorola';
        elseif (strpos($uaUp, 'ONEPLUS') !== false) $brand = 'OnePlus';
        elseif (strpos($uaUp, 'PIXEL') !== false || strpos($uaUp, 'GOOGLE') !== false) $brand = 'Google';
        elseif (strpos($uaUp, 'OPPO') !== false) $brand = 'OPPO';
        elseif (strpos($uaUp, 'VIVO') !== false) $brand = 'Vivo';
    }

    return ['os' => $os, 'brand' => $brand, 'model' => $model];
}

/**
 * Total de solicitudes por usuario (gestor) y por estado
 */
function reporteUsuarios() {
    global $pdo;
    try {
        $sql = "
            SELECT 
                u.id as usuario_id,
                u.nombre,
                u.apellido,
                u.email,
                s.estado,
                COUNT(s.id) as total
            FROM usuarios u
            LEFT JOIN solicitudes_credito s ON s.gestor_id = u.id
            WHERE u.activo = 1
            AND EXISTS (
                SELECT 1 FROM usuario_roles ur 
                INNER JOIN roles r ON ur.rol_id = r.id 
                WHERE ur.usuario_id = u.id AND r.nombre IN ('ROLE_GESTOR', 'ROLE_ADMIN')
            )
            GROUP BY u.id, u.nombre, u.apellido, u.email, s.estado
            ORDER BY u.apellido, u.nombre, s.estado
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agrupar por usuario con totales por estado
        $porUsuario = [];
        foreach ($rows as $r) {
            $id = $r['usuario_id'];
            if (!isset($porUsuario[$id])) {
                $porUsuario[$id] = [
                    'usuario_id' => $id,
                    'nombre' => $r['nombre'] . ' ' . $r['apellido'],
                    'email' => $r['email'],
                    'Nueva' => 0,
                    'En Revisión Banco' => 0,
                    'Aprobada' => 0,
                    'Rechazada' => 0,
                    'Completada' => 0,
                    'Desistimiento' => 0,
                    'total' => 0
                ];
            }
            if ($r['estado']) {
                $porUsuario[$id][$r['estado']] = (int)$r['total'];
                $porUsuario[$id]['total'] += (int)$r['total'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => array_values($porUsuario)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Total de solicitudes por vendedor (ejecutivo de ventas) y por estado
 */
function reporteVendedores() {
    global $pdo;
    try {
        echo json_encode(['success' => true, 'data' => _dataReporteVendedores($pdo)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Listado de solicitudes por gestor y estado (para el modal)
 */
function solicitudesPorUsuarioEstado() {
    global $pdo;
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');
    
    if (!$usuarioId || !$estado) {
        echo json_encode(['success' => false, 'message' => 'usuario_id y estado requeridos']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
            FROM solicitudes_credito s
            WHERE s.gestor_id = ? AND s.estado = ?
            ORDER BY s.fecha_actualizacion DESC
        ");
        $stmt->execute([$usuarioId, $estado]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Listado de solicitudes por vendedor y estado (para el modal)
 */
function solicitudesPorVendedorEstado() {
    global $pdo;
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');

    if (!$vendedorId || !$estado) {
        echo json_encode(['success' => false, 'message' => 'vendedor_id y estado requeridos']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
            FROM solicitudes_credito s
            WHERE s.ejecutivo_ventas_id = ? AND s.estado = ?
            ORDER BY s.fecha_creacion DESC
        ");
        $stmt->execute([$vendedorId, $estado]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Reporte tiempo: solicitudes con tiempo entre cambios de estado + columna acciones
 * Incluye datos para calcular tiempo en estado actual desde historial
 */
function reporteTiempo() {
    global $pdo;
    try {
        $solicitudes = $pdo->query("
            SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
            FROM solicitudes_credito s
            ORDER BY s.fecha_actualizacion DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($solicitudes as &$s) {
            $s['dias_en_estado_actual'] = null;
            $s['horas_en_estado_actual'] = null;
            if ($s['fecha_actualizacion']) {
                $stmt = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(DAY, ?, NOW()) as dias, TIMESTAMPDIFF(HOUR, ?, NOW()) as horas
                ");
                $stmt->execute([$s['fecha_actualizacion'], $s['fecha_actualizacion']]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $s['dias_en_estado_actual'] = (int)$r['dias'];
                $s['horas_en_estado_actual'] = (int)$r['horas'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Historial completo de una solicitud (para modal)
 */
function historialSolicitud() {
    global $pdo;
    $solicitudId = (int)($_GET['solicitud_id'] ?? 0);
    
    if (!$solicitudId) {
        echo json_encode(['success' => false, 'message' => 'solicitud_id requerido']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT h.id, h.tipo_accion, h.descripcion, h.estado_anterior, h.estado_nuevo, h.fecha_creacion,
                   u.nombre, u.apellido
            FROM historial_solicitud h
            LEFT JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.solicitud_id = ?
            ORDER BY h.fecha_creacion ASC
        ");
        $stmt->execute([$solicitudId]);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [
            'creacion' => 'Creación',
            'cambio_estado' => 'Cambio de estado',
            'documento_agregado' => 'Documento agregado',
            'asignacion_banco' => 'Asignación a banco',
            'actualizacion_datos' => 'Actualización de datos',
            'evaluacion_banco' => 'Evaluación del banco'
        ];
        
        foreach ($historial as &$h) {
            $h['tipo_label'] = $labels[$h['tipo_accion']] ?? $h['tipo_accion'];
            $h['usuario_nombre'] = trim(($h['nombre'] ?? '') . ' ' . ($h['apellido'] ?? ''));
        }
        
        echo json_encode(['success' => true, 'data' => $historial]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Rep. Banco: tiempo que tardan los bancos en dar respuesta a las solicitudes asignadas.
 * Desde fecha_asignacion (usuarios_banco_solicitudes) hasta primera fecha_evaluacion (evaluaciones_banco).
 */
function reporteBanco() {
    global $pdo;
    try {
        $sql = "
            SELECT 
                s.id AS solicitud_id,
                s.nombre_cliente,
                s.cedula,
                s.estado,
                b.id AS banco_id,
                b.nombre AS banco_nombre,
                ubs.fecha_asignacion,
                MIN(eb.fecha_evaluacion) AS fecha_respuesta
            FROM solicitudes_credito s
            INNER JOIN usuarios_banco_solicitudes ubs ON ubs.solicitud_id = s.id
            INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
            LEFT JOIN bancos b ON b.id = u.banco_id
            LEFT JOIN evaluaciones_banco eb ON eb.solicitud_id = s.id AND eb.usuario_banco_id = ubs.id
            GROUP BY s.id, s.nombre_cliente, s.cedula, s.estado, b.id, b.nombre, ubs.id, ubs.fecha_asignacion
            ORDER BY ubs.fecha_asignacion DESC
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as &$r) {
            $r['dias_respuesta'] = null;
            $r['horas_respuesta'] = null;
            $r['pendiente'] = empty($r['fecha_respuesta']);
            if (!empty($r['fecha_respuesta']) && !empty($r['fecha_asignacion'])) {
                $stmt2 = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(DAY, ?, ?) AS dias, TIMESTAMPDIFF(HOUR, ?, ?) AS horas
                ");
                $stmt2->execute([$r['fecha_asignacion'], $r['fecha_respuesta'], $r['fecha_asignacion'], $r['fecha_respuesta']]);
                $d = $stmt2->fetch(PDO::FETCH_ASSOC);
                $r['dias_respuesta'] = (int)$d['dias'];
                $r['horas_respuesta'] = (int)$d['horas'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Reporte de envíos de correos de resumen a banco.
 * Devuelve resumen (enviados/fallidos) y listado detallado.
 */
function reporteEmailsResumen() {
    global $pdo;
    try {
        $desde = trim((string)($_GET['desde'] ?? ''));
        $hasta = trim((string)($_GET['hasta'] ?? ''));
        $estado = trim((string)($_GET['estado'] ?? ''));

        $where = [];
        $params = [];

        if ($estado === 'enviado' || $estado === 'fallido') {
            $where[] = 'l.estado = ?';
            $params[] = $estado;
        }
        if ($desde !== '') {
            $where[] = 'DATE(l.fecha_envio) >= ?';
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where[] = 'DATE(l.fecha_envio) <= ?';
            $params[] = $hasta;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sqlResumen = "
            SELECT
                SUM(CASE WHEN l.estado = 'enviado' THEN 1 ELSE 0 END) AS enviados,
                SUM(CASE WHEN l.estado = 'fallido' THEN 1 ELSE 0 END) AS fallidos,
                COUNT(*) AS total
            FROM email_resumen_banco_log l
            {$whereSql}
        ";
        $stmtR = $pdo->prepare($sqlResumen);
        $stmtR->execute($params);
        $resumen = $stmtR->fetch(PDO::FETCH_ASSOC) ?: ['enviados' => 0, 'fallidos' => 0, 'total' => 0];

        $sqlDetalle = "
            SELECT
                l.id, l.solicitud_id, l.usuario_banco_id, l.destinatario_email, l.tipo_envio, l.estado,
                l.provider, l.provider_message_id, l.mensaje, l.fecha_envio,
                s.nombre_cliente
            FROM email_resumen_banco_log l
            LEFT JOIN solicitudes_credito s ON s.id = l.solicitud_id
            {$whereSql}
            ORDER BY l.fecha_envio DESC
            LIMIT 1000
        ";
        $stmtD = $pdo->prepare($sqlDetalle);
        $stmtD->execute($params);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'resumen' => [
                'enviados' => (int)($resumen['enviados'] ?? 0),
                'fallidos' => (int)($resumen['fallidos'] ?? 0),
                'total' => (int)($resumen['total'] ?? 0),
            ],
            'data' => $detalle
        ]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1146) {
            echo json_encode([
                'success' => true,
                'resumen' => ['enviados' => 0, 'fallidos' => 0, 'total' => 0],
                'data' => []
            ]);
            return;
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Resumen y detalle de encuestas (formulario público vendedores y proceso gestor)
 */
function reporteEncuestas() {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';

    $vendedor = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
    $gestor = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);

    echo json_encode([
        'success' => true,
        'vendedor' => $vendedor,
        'gestor' => $gestor,
    ]);
}

function reporteTelemetria() {
    global $pdo;
    try {
        $rows = _dataReporteTelemetria($pdo);
        $total = count($rows);
        $diasConRegistros = [];
        $dur = 0;
        $durN = 0;
        $sumPasos = [0, 0, 0, 0, 0];
        $sumPasosN = [0, 0, 0, 0, 0];
        $distDispositivo = [
            'iPhone' => 0,
            'Android' => 0,
            'Otros' => 0,
        ];
        $distUbicacion = [];
        $distResolucion = [];
        foreach ($rows as $r) {
            $fechaCreacion = (string)($r['fecha_creacion'] ?? '');
            if (strlen($fechaCreacion) >= 10) {
                $diasConRegistros[substr($fechaCreacion, 0, 10)] = true;
            }
            if (isset($r['telemetria_duracion_segundos']) && is_numeric($r['telemetria_duracion_segundos'])) {
                $dur += (int)$r['telemetria_duracion_segundos'];
                $durN++;
            }
            for ($i = 0; $i <= 4; $i++) {
                $k = 'paso' . $i . '_seg';
                if (isset($r[$k]) && is_numeric($r[$k])) {
                    $sumPasos[$i] += (int)$r[$k];
                    $sumPasosN[$i]++;
                }
            }

            $os = strtolower(trim((string)($r['device_os'] ?? '')));
            if (str_contains($os, 'iphone') || str_contains($os, 'ipad') || str_contains($os, 'ios')) {
                $distDispositivo['iPhone']++;
            } elseif (str_contains($os, 'android')) {
                $distDispositivo['Android']++;
            } else {
                $distDispositivo['Otros']++;
            }

            $city = trim((string)($r['geo_city'] ?? ''));
            $country = trim((string)($r['geo_country'] ?? ''));
            $ubicacion = trim(implode(', ', array_filter([$city, $country])));
            if ($ubicacion === '') {
                $ubicacion = 'Sin ubicación';
            }
            if (!isset($distUbicacion[$ubicacion])) {
                $distUbicacion[$ubicacion] = 0;
            }
            $distUbicacion[$ubicacion]++;

            $res = trim((string)($r['viewport'] ?? ''));
            if ($res === '') {
                $res = trim((string)($r['screen'] ?? ''));
            }
            if ($res === '') {
                $res = 'Sin resolución';
            }
            if (!isset($distResolucion[$res])) {
                $distResolucion[$res] = 0;
            }
            $distResolucion[$res]++;
        }

        arsort($distUbicacion);
        arsort($distResolucion);
        $distUbicacionTop = array_slice($distUbicacion, 0, 8, true);
        $distResolucionTop = array_slice($distResolucion, 0, 8, true);

        $durPromSeg = $durN > 0 ? round($dur / $durN, 2) : null;
        $pasoPromSeg = [
            0 => $sumPasosN[0] > 0 ? round($sumPasos[0] / $sumPasosN[0], 2) : null,
            1 => $sumPasosN[1] > 0 ? round($sumPasos[1] / $sumPasosN[1], 2) : null,
            2 => $sumPasosN[2] > 0 ? round($sumPasos[2] / $sumPasosN[2], 2) : null,
            3 => $sumPasosN[3] > 0 ? round($sumPasos[3] / $sumPasosN[3], 2) : null,
            4 => $sumPasosN[4] > 0 ? round($sumPasos[4] / $sumPasosN[4], 2) : null,
        ];
        $pasoPromMin = [];
        foreach ($pasoPromSeg as $k => $v) {
            $pasoPromMin[$k] = $v !== null ? round($v / 60, 2) : null;
        }
        $diasCount = count($diasConRegistros);

        echo json_encode([
            'success' => true,
            'resumen' => [
                'total_registros' => $total,
                'dias_con_registros' => $diasCount,
                'promedio_registros_diarios' => $diasCount > 0 ? round($total / $diasCount, 2) : null,
                'duracion_promedio_seg' => $durPromSeg,
                'duracion_promedio_min' => $durPromSeg !== null ? round($durPromSeg / 60, 2) : null,
                'paso_promedio_seg' => $pasoPromSeg,
                'paso_promedio_min' => $pasoPromMin,
                'distribucion_dispositivo' => $distDispositivo,
                'distribucion_ubicacion' => $distUbicacionTop,
                'distribucion_resolucion' => $distResolucionTop,
            ],
            'data' => $rows
        ]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1054) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan columnas de telemetría. Ejecute la migración de telemetría.'
            ]);
            return;
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function reporteFinPublicaDemografia(): void
{
    global $pdo;
    require_once __DIR__ . '/../includes/reportes_fin_demografia_data.php';
    try {
        echo json_encode(rep_fin_build_reporte_publica($pdo));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function reporteFinPublicaEnlazada(): void
{
    global $pdo;
    require_once __DIR__ . '/../includes/reportes_fin_demografia_data.php';
    try {
        echo json_encode(rep_fin_build_reporte_enlazada($pdo));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * @param array<int,string> $preguntas
 * @return array{resumen: ?array, filas: array, error: ?string, preguntas: array}
 */
function _reporteEncuestasBloque(PDO $pdo, string $table, array $preguntas) {
    $vacio = [
        'resumen' => null,
        'filas' => [],
        'error' => null,
        'preguntas' => $preguntas,
    ];

    $sqlResumen = "
        SELECT
            COUNT(*) AS total,
            AVG((puntuacion_1 + puntuacion_2 + puntuacion_3 + puntuacion_4 + puntuacion_5) / 5.0) AS promedio_global,
            AVG(puntuacion_1) AS promedio_p1,
            AVG(puntuacion_2) AS promedio_p2,
            AVG(puntuacion_3) AS promedio_p3,
            AVG(puntuacion_4) AS promedio_p4,
            AVG(puntuacion_5) AS promedio_p5,
            MIN(creado_en) AS desde,
            MAX(creado_en) AS hasta,
            SUM(
                CASE
                    WHEN recomendaciones IS NULL OR TRIM(recomendaciones) = '' THEN 0
                    ELSE 1
                END
            ) AS con_recomendacion
        FROM `{$table}`
    ";

    $sqlFilas = "
        SELECT
            id, nombre_completo, cargo,
            puntuacion_1, puntuacion_2, puntuacion_3, puntuacion_4, puntuacion_5,
            recomendaciones, creado_en
        FROM `{$table}`
        ORDER BY creado_en DESC
        LIMIT 2000
    ";

    try {
        $r = $pdo->query($sqlResumen)->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($r['total'] ?? 0);
        if ($total === 0) {
            $vacio['resumen'] = [
                'total' => 0,
                'promedio_global' => null,
                'promedios' => [1 => null, 2 => null, 3 => null, 4 => null, 5 => null],
                'desde' => null,
                'hasta' => null,
                'con_recomendacion' => 0,
            ];
        } else {
            $vacio['resumen'] = [
                'total' => $total,
                'promedio_global' => $r['promedio_global'] !== null
                    ? round((float) $r['promedio_global'], 2)
                    : null,
                'promedios' => [
                    1 => $r['promedio_p1'] !== null ? round((float) $r['promedio_p1'], 2) : null,
                    2 => $r['promedio_p2'] !== null ? round((float) $r['promedio_p2'], 2) : null,
                    3 => $r['promedio_p3'] !== null ? round((float) $r['promedio_p3'], 2) : null,
                    4 => $r['promedio_p4'] !== null ? round((float) $r['promedio_p4'], 2) : null,
                    5 => $r['promedio_p5'] !== null ? round((float) $r['promedio_p5'], 2) : null,
                ],
                'desde' => $r['desde'] ? (string) $r['desde'] : null,
                'hasta' => $r['hasta'] ? (string) $r['hasta'] : null,
                'con_recomendacion' => (int) ($r['con_recomendacion'] ?? 0),
            ];
        }

        $filas = $pdo->query($sqlFilas)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $p1 = (int) $f['puntuacion_1'];
            $p2 = (int) $f['puntuacion_2'];
            $p3 = (int) $f['puntuacion_3'];
            $p4 = (int) $f['puntuacion_4'];
            $p5 = (int) $f['puntuacion_5'];
            $f['promedio_fila'] = round(($p1 + $p2 + $p3 + $p4 + $p5) / 5.0, 2);
        }
        unset($f);
        $vacio['filas'] = $filas;
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
            $vacio['error'] = 'Aún no existe la tabla. Ejecute database/migracion_encuestas_satisfaccion.sql en la base de datos.';
        } else {
            $vacio['error'] = 'Error al leer las encuestas.';
        }
    }

    return $vacio;
}
