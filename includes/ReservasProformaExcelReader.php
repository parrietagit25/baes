<?php
/**
 * Lee .xlsx (primera hoja) desde fila 4, columna C en adelante.
 */
class ReservasProformaExcelReader
{
    private const DATA_START_ROW = 4;
    private const DATA_START_COL = 'C';

    /** @var array<string, int> Letra de columna Excel -> índice 0-based en fila */
    private const COL = [
        'mov' => 'C',
        'mov_id' => 'D',
        'fecha_emision' => 'E',
        'dias_reserva' => 'F',
        'nombre_sucursal' => 'G',
        'nombre_vendedor' => 'H',
        'cliente_codigo' => 'I',
        'nombre_cliente' => 'J',
        'cedula' => 'L',
        'correo_cliente' => 'M',
        'marca' => 'T',
        'modelo' => 'U',
        'anio' => 'X',
        'kilometraje' => 'Y',
        'precio_total' => 'AC',
        'abono_monto' => 'AL',
        'unidad' => 'AF',
        'chasis' => 'AG',
        'placas' => 'AH',
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

    public static function leerCsv(string $path): array
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            throw RuntimeException('No se pudo abrir el CSV');
        }
        $rows = [];
        $lineNum = 0;
        while (($data = fgetcsv($fh)) !== false) {
            $lineNum++;
            if ($lineNum < self::DATA_START_ROW) {
                continue;
            }
            $assoc = self::mapRowFromArray($data, self::colIndex(self::DATA_START_COL));
            if (self::filaVacia($assoc)) {
                continue;
            }
            $assoc['fila_excel'] = $lineNum;
            $rows[] = $assoc;
        }
        fclose($fh);
        return $rows;
    }

    private static function leerXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw RuntimeException('Se requiere extensión ZipArchive de PHP para leer Excel');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw RuntimeException('No se pudo abrir el archivo Excel');
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

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw RuntimeException('No se encontró la hoja sheet1 en el Excel');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet || !isset($sheet->sheetData->row)) {
            return [];
        }

        $startColIdx = self::colIndex(self::DATA_START_COL);
        $out = [];
        foreach ($sheet->sheetData->row as $row) {
            $r = 0;
            $ref = (string) ($row['r'] ?? '');
            if (preg_match('/^(\d+)/', $ref, $m)) {
                $r = (int) $m[1];
            }
            if ($r < self::DATA_START_ROW) {
                continue;
            }
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
                    continue;
                }
                $colLetter = $m[1];
                $colIdx = self::colIndex($colLetter);
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
            $rowArr = [];
            $maxIdx = max(array_merge(array_keys($cells), [$startColIdx]));
            for ($i = 0; $i <= $maxIdx; $i++) {
                $rowArr[] = $cells[$i] ?? '';
            }
            $assoc = self::mapRowFromArray($rowArr, $startColIdx);
            if (self::filaVacia($assoc)) {
                continue;
            }
            $assoc['fila_excel'] = $r;
            $out[] = $assoc;
        }
        return $out;
    }

    private static function mapRowFromArray(array $row, int $startColIdx): array
    {
        $get = static function (string $letter) use ($row, $startColIdx): string {
            $idx = self::colIndex($letter) - $startColIdx;
            return trim((string) ($row[$idx] ?? ''));
        };

        return [
            'mov' => $get(self::COL['mov']),
            'mov_id' => $get(self::COL['mov_id']),
            'fecha_emision' => $get(self::COL['fecha_emision']),
            'dias_reserva' => $get(self::COL['dias_reserva']),
            'nombre_sucursal' => $get(self::COL['nombre_sucursal']),
            'nombre_vendedor' => $get(self::COL['nombre_vendedor']),
            'cliente_codigo' => $get(self::COL['cliente_codigo']),
            'nombre_cliente' => $get(self::COL['nombre_cliente']),
            'cedula' => $get(self::COL['cedula']),
            'correo_cliente' => $get(self::COL['correo_cliente']),
            'marca' => $get(self::COL['marca']),
            'modelo' => $get(self::COL['modelo']),
            'anio' => $get(self::COL['anio']),
            'kilometraje' => $get(self::COL['kilometraje']),
            'precio_total' => $get(self::COL['precio_total']),
            'abono_monto' => $get(self::COL['abono_monto']),
            'unidad' => $get(self::COL['unidad']),
            'chasis' => $get(self::COL['chasis']),
            'placas' => $get(self::COL['placas']),
        ];
    }

    private static function filaVacia(array $assoc): bool
    {
        $keys = ['cedula', 'correo_cliente', 'nombre_cliente', 'mov_id', 'marca', 'modelo'];
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
