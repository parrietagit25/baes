<?php
/**
 * Lee .xlsx / .csv de reservas/proformas por NOMBRE de encabezado (fila 1).
 * Soporta el layout A–AJ (Reservas Activas) y el layout A–AF (New Format con columnas vacías).
 * No depende del orden de columnas ni de letras fijas.
 */
class ReservasProformaExcelReader
{
    private const DATA_START_ROW = 2;

    /**
     * Alias normalizados (sin acentos, minúsculas, espacios colapsados) => campo interno.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'mov' => 'mov',
        'mov id' => 'mov_id',
        'movid' => 'mov_id',
        'fecha emision' => 'fecha_emision',
        'dia reserva' => 'dias_reserva',
        'dias reserva' => 'dias_reserva',
        'nombre sucursal' => 'nombre_sucursal',
        'nombre vendedor' => 'nombre_vendedor',
        'cliente' => 'cliente_codigo',
        'nombre cliente' => 'nombre_cliente',
        'cedula' => 'cedula',
        'correo cliente' => 'correo_cliente',
        'email cliente' => 'correo_cliente',
        'almacen' => 'almacen',
        'concepto' => 'concepto',
        'comentarios' => 'comentarios',
        'observaciones' => 'observaciones',
        'condicion' => 'condicion',
        'articulo' => 'articulo',
        'marca' => 'marca',
        'modelo' => 'modelo',
        'tipo auto' => 'tipo_auto',
        'cantidad' => 'cantidad',
        'anio' => 'anio',
        'ano' => 'anio',
        'ao' => 'anio',
        'km' => 'kilometraje',
        'kilometraje' => 'kilometraje',
        'precio marcado' => 'precio_marcado',
        'importe' => 'importe',
        'impuestos' => 'impuestos',
        'total' => 'precio_total',
        'precio total' => 'precio_total',
        'precio de cierre' => 'precio_total',
        'liberado' => 'liberado',
        'ctc completo' => 'ctc_completo',
        'banco' => 'banco',
        'prestamo' => 'prestamo',
        '# prestamo' => 'prestamo',
        'num prestamo' => 'prestamo',
        'nro prestamo' => 'prestamo',
        'mov liberacion' => 'mov_liberacion',
        'unidad' => 'unidad',
        'chasis' => 'chasis',
        'placas' => 'placas',
        'abono' => 'abono_monto',
        'saldo' => 'saldo',
        'piso' => 'piso',
        'accesorios' => 'accesorios',
    ];

    /** Campos internos esperados (vacíos si no hay columna). */
    private const CAMPOS = [
        'mov', 'mov_id', 'fecha_emision', 'dias_reserva', 'nombre_sucursal', 'nombre_vendedor',
        'cliente_codigo', 'nombre_cliente', 'cedula', 'correo_cliente', 'almacen', 'concepto',
        'comentarios', 'observaciones', 'condicion', 'articulo', 'marca', 'modelo', 'tipo_auto',
        'cantidad', 'anio', 'kilometraje', 'precio_marcado', 'importe', 'impuestos', 'precio_total',
        'liberado', 'ctc_completo', 'banco', 'prestamo', 'mov_liberacion', 'unidad', 'chasis',
        'placas', 'abono_monto', 'saldo', 'piso', 'accesorios',
    ];

    public static function leerArchivo(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return self::leerCsv($path);
        }
        if ($ext !== 'xlsx') {
            throw new InvalidArgumentException('Formato no soportado. Use .xlsx o .csv exportado desde Excel.');
        }
        return self::leerXlsx($path);
    }

    public static function normalizarHeader(string $raw): string
    {
        $h = trim($raw);
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;
        $h = mb_strtolower($h, 'UTF-8');
        // Quitar acentos de forma simple para aliases.
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];
        $h = strtr($h, $map);
        $h = preg_replace('/\s+/u', ' ', trim($h)) ?? $h;
        return $h;
    }

    /**
     * @param list<string> $headerCells
     * @return array{map: array<int, string>, layout: string, headers_encontrados: list<string>}
     */
    public static function construirMapaDesdeEncabezados(array $headerCells): array
    {
        $map = [];
        $encontrados = [];
        foreach ($headerCells as $idx => $raw) {
            $norm = self::normalizarHeader((string) $raw);
            if ($norm === '') {
                continue;
            }
            $campo = self::HEADER_ALIASES[$norm] ?? null;
            if ($campo === null) {
                continue;
            }
            // Primera coincidencia gana (evita sobrescribir si hay duplicados).
            if (!in_array($campo, $map, true)) {
                $map[(int) $idx] = $campo;
                $encontrados[] = $campo;
            }
        }

        $layout = 'desconocido';
        if (in_array('banco', $encontrados, true) || in_array('chasis', $encontrados, true) || in_array('tipo_auto', $encontrados, true)) {
            $layout = 'reservas_activas_aj';
        } elseif (in_array('piso', $encontrados, true) || in_array('accesorios', $encontrados, true)) {
            $layout = 'new_format_af';
        } elseif (in_array('mov_id', $encontrados, true) && in_array('nombre_cliente', $encontrados, true)) {
            $layout = 'compatible_por_encabezados';
        }

        return [
            'map' => $map,
            'layout' => $layout,
            'headers_encontrados' => $encontrados,
        ];
    }

    public static function leerCsv(string $path): array
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            throw new RuntimeException('No se pudo abrir el CSV');
        }
        $rows = [];
        $lineNum = 0;
        $mapa = null;
        while (($data = fgetcsv($fh)) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                $mapa = self::construirMapaDesdeEncabezados(array_map('strval', $data));
                if (($mapa['map'] ?? []) === []) {
                    fclose($fh);
                    throw new RuntimeException('No se reconocieron encabezados en la fila 1 del CSV.');
                }
                continue;
            }
            if ($lineNum < self::DATA_START_ROW) {
                continue;
            }
            $assoc = self::mapRowByHeaderMap($data, $mapa['map']);
            if (self::filaVacia($assoc)) {
                continue;
            }
            $assoc['fila_excel'] = $lineNum;
            $assoc['_layout'] = $mapa['layout'];
            $rows[] = $assoc;
        }
        fclose($fh);
        return $rows;
    }

    private static function leerXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Se requiere extensión ZipArchive de PHP para leer Excel');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo Excel');
        }

        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = @simplexml_load_string($ssXml);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $parts = '';
                        foreach ($si->r as $r) {
                            $parts .= (string) ($r->t ?? '');
                        }
                        $shared[] = $parts;
                    } else {
                        $shared[] = '';
                    }
                }
            }
        }

        $sheetPath = self::resolverHojaPreferida($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('No se encontró una hoja de datos en el Excel');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet || !isset($sheet->sheetData->row)) {
            return [];
        }

        $mapa = null;
        $out = [];
        foreach ($sheet->sheetData->row as $row) {
            $r = 0;
            $ref = (string) ($row['r'] ?? '');
            if (preg_match('/^(\d+)/', $ref, $m)) {
                $r = (int) $m[1];
            }
            $cells = [];
            foreach ($row->c as $c) {
                $cref = (string) $c['r'];
                if (!preg_match('/^([A-Z]+)/', $cref, $cm)) {
                    continue;
                }
                $colIdx = self::colIndex($cm[1]);
                $type = (string) ($c['t'] ?? '');
                $val = '';
                if ($type === 's') {
                    $idx = (int) ($c->v ?? 0);
                    $val = $shared[$idx] ?? '';
                } elseif (isset($c->v)) {
                    $val = (string) $c->v;
                } elseif (isset($c->is->t)) {
                    $val = (string) $c->is->t;
                }
                $cells[$colIdx] = $val;
            }
            if ($cells === []) {
                continue;
            }
            $maxIdx = max(array_keys($cells));
            $rowArr = [];
            for ($i = 0; $i <= $maxIdx; $i++) {
                $rowArr[] = $cells[$i] ?? '';
            }

            if ($r === 1 || ($mapa === null && $r > 0 && $r < self::DATA_START_ROW)) {
                $mapa = self::construirMapaDesdeEncabezados($rowArr);
                if (($mapa['map'] ?? []) === []) {
                    throw new RuntimeException(
                        'No se reconocieron encabezados en la fila 1. '
                        . 'Verifique nombres como Mov ID, Nombre Cliente, Cédula, Marca, Modelo, Unidad, etc.'
                    );
                }
                continue;
            }

            if ($r < self::DATA_START_ROW) {
                continue;
            }
            if ($mapa === null) {
                continue;
            }

            $assoc = self::mapRowByHeaderMap($rowArr, $mapa['map']);
            if (self::filaVacia($assoc)) {
                continue;
            }
            $assoc['fila_excel'] = $r;
            $assoc['_layout'] = $mapa['layout'];
            $out[] = $assoc;
        }

        if ($mapa === null) {
            throw new RuntimeException('No se encontró la fila de encabezados (fila 1) en el Excel.');
        }

        return $out;
    }

    /**
     * Prefiere hojas cuyo nombre sugiera proforma/reservas; si no, sheet1.
     */
    private static function resolverHojaPreferida(ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $wbXml = @simplexml_load_string($wb);
        $relsXml = @simplexml_load_string($rels);
        if (!$wbXml || !$relsXml) {
            return 'xl/worksheets/sheet1.xml';
        }

        $wbXml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $relsXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $idToTarget = [];
        foreach ($relsXml->Relationship as $rel) {
            $id = (string) ($rel['Id'] ?? '');
            $target = (string) ($rel['Target'] ?? '');
            if ($id === '' || $target === '') {
                continue;
            }
            $target = ltrim(str_replace('\\', '/', $target), '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }
            $idToTarget[$id] = $target;
        }

        $preferidas = [];
        $todas = [];
        foreach ($wbXml->sheets->sheet as $sheet) {
            $name = (string) ($sheet['name'] ?? '');
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string) ($attrs['id'] ?? '');
            if ($rid === '' || !isset($idToTarget[$rid])) {
                continue;
            }
            $path = $idToTarget[$rid];
            $todas[] = $path;
            $n = mb_strtolower($name, 'UTF-8');
            if (str_contains($n, 'proforma') || str_contains($n, 'reserva') || str_contains($n, 'venta')) {
                $preferidas[] = $path;
            }
        }

        if ($preferidas !== []) {
            return $preferidas[0];
        }
        if ($todas !== []) {
            return $todas[0];
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param list<string|null> $row
     * @param array<int, string> $map colIndex => campo
     * @return array<string, string>
     */
    private static function mapRowByHeaderMap(array $row, array $map): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo) {
            $out[$campo] = '';
        }
        foreach ($map as $idx => $campo) {
            $out[$campo] = trim((string) ($row[$idx] ?? ''));
        }
        return $out;
    }

    private static function filaVacia(array $assoc): bool
    {
        $keys = ['mov_id', 'cedula', 'correo_cliente', 'nombre_cliente', 'marca', 'modelo', 'unidad', 'mov', 'chasis'];
        foreach ($keys as $k) {
            if (($assoc[$k] ?? '') !== '') {
                return false;
            }
        }
        return true;
    }

    public static function colIndex(string $col): int
    {
        $col = strtoupper($col);
        $n = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($col[$i]) - 64);
        }
        return $n - 1;
    }
}
