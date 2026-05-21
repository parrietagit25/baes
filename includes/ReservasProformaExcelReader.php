<?php
/**
 * Lee .xlsx / .csv: fila 1 = encabezados, datos desde fila 2, columnas A–AJ.
 */
class ReservasProformaExcelReader
{
    private const DATA_START_ROW = 2;
    private const DATA_START_COL = 'A';

    /** @var array<string, string> Campo interno => columna Excel */
    private const COL = [
        'mov' => 'A',
        'mov_id' => 'B',
        'fecha_emision' => 'C',
        'dias_reserva' => 'D',
        'nombre_sucursal' => 'E',
        'nombre_vendedor' => 'F',
        'cliente_codigo' => 'G',
        'nombre_cliente' => 'H',
        'cedula' => 'I',
        'correo_cliente' => 'J',
        'almacen' => 'K',
        'concepto' => 'L',
        'comentarios' => 'M',
        'observaciones' => 'N',
        'condicion' => 'O',
        'articulo' => 'P',
        'marca' => 'Q',
        'modelo' => 'R',
        'tipo_auto' => 'S',
        'cantidad' => 'T',
        'anio' => 'U',
        'kilometraje' => 'V',
        'precio_marcado' => 'W',
        'importe' => 'X',
        'impuestos' => 'Y',
        'precio_total' => 'Z',
        'liberado' => 'AA',
        'ctc_completo' => 'AB',
        'banco' => 'AC',
        'prestamo' => 'AD',
        'mov_liberacion' => 'AE',
        'unidad' => 'AF',
        'chasis' => 'AG',
        'placas' => 'AH',
        'abono_monto' => 'AI',
        'saldo' => 'AJ',
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
            throw new RuntimeException('No se pudo abrir el CSV');
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

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('No se encontró la hoja sheet1 en el Excel');
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
            $maxIdx = max(array_merge(array_keys($cells), [$startColIdx + self::colIndex('AJ')]));
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

        $out = [];
        foreach (self::COL as $key => $letter) {
            $out[$key] = $get($letter);
        }
        return $out;
    }

    private static function filaVacia(array $assoc): bool
    {
        $keys = ['mov_id', 'cedula', 'correo_cliente', 'nombre_cliente', 'marca', 'modelo', 'unidad', 'mov'];
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
