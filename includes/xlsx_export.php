<?php
/**
 * Generación mínima de archivos XLSX (Office Open XML) sin dependencias externas.
 */

declare(strict_types=1);

function motus_output_xlsx_download(string $fileName, string $sheetName, array $headers, array $rows): void
{
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
    $sheetXml = motus_xlsx_build_sheet_xml($allRows);
    $safeSheetName = motus_xlsx_safe_sheet_name($sheetName);

    $zip->addFromString('[Content_Types].xml', motus_xlsx_content_types_xml());
    $zip->addFromString('_rels/.rels', motus_xlsx_root_rels_xml());
    $zip->addFromString('docProps/app.xml', motus_xlsx_app_xml());
    $zip->addFromString('docProps/core.xml', motus_xlsx_core_xml());
    $zip->addFromString('xl/workbook.xml', motus_xlsx_workbook_xml($safeSheetName));
    $zip->addFromString('xl/_rels/workbook.xml.rels', motus_xlsx_workbook_rels_xml());
    $zip->addFromString('xl/styles.xml', motus_xlsx_styles_xml());
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) filesize($xlsxPath));
    readfile($xlsxPath);
    @unlink($xlsxPath);
}

function motus_xlsx_safe_sheet_name(string $name): string
{
    $n = preg_replace('/[\\\\\\/*?:\\[\\]]/', ' ', $name) ?? 'Hoja1';
    $n = trim($n);
    if ($n === '') {
        $n = 'Hoja1';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($n, 0, 31);
    }

    return substr($n, 0, 31);
}

function motus_xlsx_esc(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function motus_xlsx_col_letter(int $col): string
{
    $letter = '';
    while ($col > 0) {
        $mod = ($col - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $col = intdiv($col - 1, 26);
    }

    return $letter;
}

function motus_xlsx_build_sheet_xml(array $rows): string
{
    $xmlRows = '';
    $rowNum = 1;
    foreach ($rows as $row) {
        $xmlCells = '';
        $colNum = 1;
        foreach ($row as $value) {
            $cellRef = motus_xlsx_col_letter($colNum) . $rowNum;
            $style = ($rowNum === 1) ? ' s="1"' : '';
            if ($value === null) {
                $value = '';
            }
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)))) {
                $num = is_string($value) ? str_replace(',', '.', trim($value)) : (string) $value;
                $xmlCells .= '<c r="' . $cellRef . '"' . $style . '><v>' . motus_xlsx_esc($num) . '</v></c>';
            } else {
                $xmlCells .= '<c r="' . $cellRef . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . motus_xlsx_esc((string) $value) . '</t></is></c>';
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

function motus_xlsx_content_types_xml(): string
{
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

function motus_xlsx_root_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function motus_xlsx_app_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Motus</Application>'
        . '</Properties>';
}

function motus_xlsx_core_xml(): string
{
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

function motus_xlsx_workbook_xml(string $sheetName): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . motus_xlsx_esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function motus_xlsx_workbook_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function motus_xlsx_styles_xml(): string
{
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
