<?php
/**
 * Importa líneas del Excel, busca solicitudes y aplica vehículo + apartado.
 */
require_once __DIR__ . '/ReservasProformaExcelReader.php';

class ReservasProformaProcessor
{
    private PDO $pdo;
    private int $reporteId;
    private int $usuarioId;

    public function __construct(PDO $pdo, int $reporteId, int $usuarioId)
    {
        $this->pdo = $pdo;
        $this->reporteId = $reporteId;
        $this->usuarioId = $usuarioId;
    }

    public static function normalizarCedula(?string $cedula): string
    {
        $c = mb_strtoupper(trim((string) $cedula), 'UTF-8');
        return preg_replace('/[^A-Z0-9]/', '', $c) ?? '';
    }

    public static function normalizarCorreo(?string $email): string
    {
        return mb_strtolower(trim((string) $email), 'UTF-8');
    }

    public static function normalizarNombre(?string $nombre): string
    {
        $n = mb_strtoupper(trim((string) $nombre), 'UTF-8');
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;
        return $n;
    }

    public static function parseNumero(?string $v): ?float
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        $x = str_replace([',', '$', ' '], ['', '', ''], trim((string) $v));
        return is_numeric($x) ? (float) $x : null;
    }

    public static function parseFecha(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        // Serial de Excel (días desde 1899-12-30).
        if (is_numeric($v)) {
            $n = (float) $v;
            if ($n > 20000 && $n < 80000) {
                $unix = (int) round(($n - 25569) * 86400);
                if ($unix > 0) {
                    return gmdate('Y-m-d', $unix);
                }
            }
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    public static function normalizarMovId(?string $movId): string
    {
        $m = mb_strtoupper(trim((string) $movId), 'UTF-8');
        return preg_replace('/\s+/u', '', $m) ?? '';
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private function prepararLineaDesdeFila(array $f): array
    {
        $precio = self::parseNumero($f['precio_total'] ?? null);
        $abono = self::parseNumero($f['abono_monto'] ?? null);
        $pct = null;
        if ($precio !== null && $precio > 0 && $abono !== null) {
            $pct = round(($abono / $precio) * 100, 2);
        }
        $movId = trim((string) ($f['mov_id'] ?? ''));
        $movIdNorm = self::normalizarMovId($movId);

        return [
            'fila_excel' => (int) ($f['fila_excel'] ?? 0),
            'mov' => ($f['mov'] ?? '') !== '' ? $f['mov'] : null,
            'mov_id' => $movId !== '' ? $movId : null,
            'mov_id_norm' => $movIdNorm !== '' ? $movIdNorm : null,
            'fecha_emision' => self::parseFecha($f['fecha_emision'] ?? null),
            'dias_reserva' => is_numeric($f['dias_reserva'] ?? '') ? (int) $f['dias_reserva'] : null,
            'nombre_sucursal' => ($f['nombre_sucursal'] ?? '') !== '' ? $f['nombre_sucursal'] : null,
            'nombre_vendedor' => ($f['nombre_vendedor'] ?? '') !== '' ? $f['nombre_vendedor'] : null,
            'cliente_codigo' => ($f['cliente_codigo'] ?? '') !== '' ? $f['cliente_codigo'] : null,
            'nombre_cliente' => ($f['nombre_cliente'] ?? '') !== '' ? $f['nombre_cliente'] : null,
            'cedula' => ($f['cedula'] ?? '') !== '' ? $f['cedula'] : null,
            'cedula_norm' => self::normalizarCedula($f['cedula'] ?? '') ?: null,
            'correo_cliente' => ($f['correo_cliente'] ?? '') !== '' ? $f['correo_cliente'] : null,
            'correo_norm' => self::normalizarCorreo($f['correo_cliente'] ?? '') ?: null,
            'marca' => ($f['marca'] ?? '') !== '' ? $f['marca'] : null,
            'modelo' => ($f['modelo'] ?? '') !== '' ? $f['modelo'] : null,
            'anio' => is_numeric($f['anio'] ?? '') ? (int) $f['anio'] : null,
            'kilometraje' => is_numeric($f['kilometraje'] ?? '')
                ? (int) preg_replace('/\D/', '', (string) $f['kilometraje'])
                : null,
            'precio_total' => $precio,
            'abono_monto' => $abono,
            'abono_porcentaje' => $pct,
            'unidad' => ($f['unidad'] ?? '') !== '' ? $f['unidad'] : null,
            'chasis' => ($f['chasis'] ?? '') !== '' ? $f['chasis'] : null,
            'placas' => ($f['placas'] ?? '') !== '' ? $f['placas'] : null,
            'datos_excel_json' => $this->jsonDatosExtra($f),
        ];
    }

    /**
     * @param array<string, mixed> $f
     */
    private function jsonDatosExtra(array $f): ?string
    {
        if (!$this->columnaDatosExcelJsonExiste()) {
            return null;
        }
        $extra = [];
        $keys = [
            'almacen', 'concepto', 'comentarios', 'observaciones', 'condicion', 'articulo',
            'tipo_auto', 'cantidad', 'precio_marcado', 'importe', 'impuestos',
            'liberado', 'ctc_completo', 'banco', 'prestamo', 'mov_liberacion', 'saldo',
            'piso', 'accesorios',
        ];
        foreach ($keys as $k) {
            $v = trim((string) ($f[$k] ?? ''));
            if ($v !== '') {
                $extra[$k] = $v;
            }
        }
        return $extra === [] ? null : json_encode($extra, JSON_UNESCAPED_UNICODE);
    }

    private function columnaMovIdNormExiste(): bool
    {
        try {
            $this->pdo->query('SELECT mov_id_norm FROM reportes_reservas_lineas LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function columnaDatosExcelJsonExiste(): bool
    {
        try {
            $this->pdo->query('SELECT datos_excel_json FROM reportes_reservas_lineas LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @return array{success: bool, message: string, stats?: array}
     */
    public function importarDesdeArchivo(string $path): array
    {
        try {
            $filas = ReservasProformaExcelReader::leerArchivo($path);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (count($filas) === 0) {
            return ['success' => false, 'message' => 'No se encontraron filas de datos (revise encabezados en fila 1 y datos desde fila 2)'];
        }

        $layoutDetectado = (string) ($filas[0]['_layout'] ?? 'compatible_por_encabezados');
        $nombresLayout = [
            'reservas_activas_aj' => 'Reservas Activas (A–AJ)',
            'new_format_af' => 'New Format (A–AF)',
            'compatible_por_encabezados' => 'compatible por encabezados',
            'desconocido' => 'detectado por encabezados',
        ];
        $layoutTxt = $nombresLayout[$layoutDetectado] ?? $layoutDetectado;

        $upsert = $this->columnaMovIdNormExiste();
        if (!$upsert) {
            return [
                'success' => false,
                'message' => 'Ejecute database/migracion_reportes_reservas_layout_aj.sql para actualizar/insertar por Mov ID',
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $sel = $this->pdo->prepare('SELECT id FROM reportes_reservas_lineas WHERE mov_id_norm = ? LIMIT 1');
            $insColumnas = [
                'reporte_id', 'fila_excel', 'mov', 'mov_id', 'mov_id_norm', 'fecha_emision', 'dias_reserva',
                'nombre_sucursal', 'nombre_vendedor', 'cliente_codigo', 'nombre_cliente',
                'cedula', 'cedula_norm', 'correo_cliente', 'correo_norm',
                'marca', 'modelo', 'anio', 'kilometraje', 'precio_total', 'abono_monto', 'abono_porcentaje',
                'unidad', 'chasis', 'placas',
            ];
            $conJson = $this->columnaDatosExcelJsonExiste();
            if ($conJson) {
                $insColumnas[] = 'datos_excel_json';
            }
            $insColumnas[] = 'estado';
            $insPlaceholders = array_fill(0, count($insColumnas) - 1, '?');
            $insPlaceholders[] = "'pendiente'";
            $ins = $this->pdo->prepare(
                'INSERT INTO reportes_reservas_lineas (' . implode(', ', $insColumnas) . ') VALUES ('
                . implode(', ', $insPlaceholders) . ')'
            );

            $updSets = [
                'reporte_id = ?', 'fila_excel = ?', 'mov = ?', 'mov_id = ?', 'fecha_emision = ?', 'dias_reserva = ?',
                'nombre_sucursal = ?', 'nombre_vendedor = ?', 'cliente_codigo = ?', 'nombre_cliente = ?',
                'cedula = ?', 'cedula_norm = ?', 'correo_cliente = ?', 'correo_norm = ?',
                'marca = ?', 'modelo = ?', 'anio = ?', 'kilometraje = ?', 'precio_total = ?',
                'abono_monto = ?', 'abono_porcentaje = ?', 'unidad = ?', 'chasis = ?', 'placas = ?',
            ];
            if ($this->columnaDatosExcelJsonExiste()) {
                $updSets[] = 'datos_excel_json = ?';
            }
            $updSets[] = "estado = 'pendiente'";
            $updSets[] = 'solicitud_id = NULL';
            $updSets[] = 'vehiculo_id = NULL';
            $updSets[] = "match_por = 'ninguno'";
            $updSets[] = 'mensaje = NULL';
            $upd = $this->pdo->prepare('UPDATE reportes_reservas_lineas SET ' . implode(', ', $updSets) . ' WHERE id = ?');

            $insertadas = 0;
            $actualizadas = 0;

            foreach ($filas as $f) {
                $linea = $this->prepararLineaDesdeFila($f);
                $movNorm = $linea['mov_id_norm'];
                $existenteId = null;
                if ($movNorm !== null && $movNorm !== '') {
                    $sel->execute([$movNorm]);
                    $existenteId = $sel->fetchColumn();
                    $sel->closeCursor();
                }

                if ($existenteId) {
                    $params = [
                        $this->reporteId,
                        $linea['fila_excel'],
                        $linea['mov'],
                        $linea['mov_id'],
                        $linea['fecha_emision'],
                        $linea['dias_reserva'],
                        $linea['nombre_sucursal'],
                        $linea['nombre_vendedor'],
                        $linea['cliente_codigo'],
                        $linea['nombre_cliente'],
                        $linea['cedula'],
                        $linea['cedula_norm'],
                        $linea['correo_cliente'],
                        $linea['correo_norm'],
                        $linea['marca'],
                        $linea['modelo'],
                        $linea['anio'],
                        $linea['kilometraje'],
                        $linea['precio_total'],
                        $linea['abono_monto'],
                        $linea['abono_porcentaje'],
                        $linea['unidad'],
                        $linea['chasis'],
                        $linea['placas'],
                    ];
                    if ($this->columnaDatosExcelJsonExiste()) {
                        $params[] = $linea['datos_excel_json'];
                    }
                    $params[] = (int) $existenteId;
                    $upd->execute($params);
                    $actualizadas++;
                } else {
                    $params = [
                        $this->reporteId,
                        $linea['fila_excel'],
                        $linea['mov'],
                        $linea['mov_id'],
                        $linea['mov_id_norm'],
                        $linea['fecha_emision'],
                        $linea['dias_reserva'],
                        $linea['nombre_sucursal'],
                        $linea['nombre_vendedor'],
                        $linea['cliente_codigo'],
                        $linea['nombre_cliente'],
                        $linea['cedula'],
                        $linea['cedula_norm'],
                        $linea['correo_cliente'],
                        $linea['correo_norm'],
                        $linea['marca'],
                        $linea['modelo'],
                        $linea['anio'],
                        $linea['kilometraje'],
                        $linea['precio_total'],
                        $linea['abono_monto'],
                        $linea['abono_porcentaje'],
                        $linea['unidad'],
                        $linea['chasis'],
                        $linea['placas'],
                    ];
                    if ($this->columnaDatosExcelJsonExiste()) {
                        $params[] = $linea['datos_excel_json'];
                    }
                    $ins->execute($params);
                    $insertadas++;
                }
            }

            $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM reportes_reservas_lineas WHERE reporte_id = ?');
            $cnt->execute([$this->reporteId]);
            $totalReporte = (int) $cnt->fetchColumn();

            $this->pdo->prepare("
                UPDATE reportes_reservas
                SET filas_total = ?, estado = 'pendiente', filas_aplicadas = 0, filas_sin_coincidencia = 0, fecha_procesado = NULL
                WHERE id = ?
            ")->execute([$totalReporte, $this->reporteId]);

            $this->pdo->commit();
            $msg = sprintf(
                'Layout %s: %d filas en este reporte (%d nuevas, %d actualizadas por Mov ID). Ejecute Procesar para aplicar a solicitudes.',
                $layoutTxt,
                $totalReporte,
                $insertadas,
                $actualizadas
            );
            return [
                'success' => true,
                'message' => $msg,
                'stats' => [
                    'filas_total' => $totalReporte,
                    'filas_insertadas' => $insertadas,
                    'filas_actualizadas' => $actualizadas,
                    'layout' => $layoutDetectado,
                ],
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('ReservasProformaProcessor importar: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar filas: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string, stats?: array}
     */
    public function procesarCoincidencias(): array
    {
        if (!$this->columnaApartadoExiste()) {
            return [
                'success' => false,
                'message' => 'Ejecute database/migracion_reportes_reservas_lineas.sql (columna apartado en vehículos)',
            ];
        }

        $solicitudes = $this->cargarSolicitudesIndice();
        $stmt = $this->pdo->prepare('SELECT * FROM reportes_reservas_lineas WHERE reporte_id = ? ORDER BY fila_excel ASC');
        $stmt->execute([$this->reporteId]);
        $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $aplicadas = 0;
        $sinMatch = 0;
        $errores = 0;

        $this->pdo->prepare("UPDATE reportes_reservas SET estado = 'procesando' WHERE id = ?")->execute([$this->reporteId]);

        foreach ($lineas as $linea) {
            $lineaId = (int) $linea['id'];
            try {
                $match = $this->buscarSolicitud($linea, $solicitudes);
                if (!$match) {
                    $this->actualizarLinea($lineaId, [
                        'estado' => 'sin_coincidencia',
                        'match_por' => 'ninguno',
                        'mensaje' => 'No se encontró solicitud por cédula, correo ni nombre',
                    ]);
                    $sinMatch++;
                    continue;
                }

                $solicitudId = (int) $match['id'];
                $vehiculoId = $this->aplicarVehiculoYApartar($solicitudId, $linea);
                $this->actualizarLinea($lineaId, [
                    'estado' => 'aplicado',
                    'match_por' => $match['match_por'],
                    'solicitud_id' => $solicitudId,
                    'vehiculo_id' => $vehiculoId,
                    'mensaje' => 'Solicitud #' . $solicitudId . ' — vehículo ' . ($vehiculoId ? 'actualizado/apartado' : 'registrado y apartado'),
                ]);
                $aplicadas++;
            } catch (Throwable $e) {
                error_log('procesar linea ' . $lineaId . ': ' . $e->getMessage());
                $this->actualizarLinea($lineaId, [
                    'estado' => 'error',
                    'mensaje' => mb_substr($e->getMessage(), 0, 480),
                ]);
                $errores++;
            }
        }

        $this->pdo->prepare("
            UPDATE reportes_reservas
            SET estado = 'completado', filas_aplicadas = ?, filas_sin_coincidencia = ?, fecha_procesado = NOW()
            WHERE id = ?
        ")->execute([$aplicadas, $sinMatch, $this->reporteId]);

        return [
            'success' => true,
            'message' => "Proceso finalizado: {$aplicadas} aplicadas, {$sinMatch} sin coincidencia, {$errores} con error.",
            'stats' => [
                'filas_total' => count($lineas),
                'filas_aplicadas' => $aplicadas,
                'filas_sin_coincidencia' => $sinMatch,
                'filas_error' => $errores,
            ],
        ];
    }

    private function columnaApartadoExiste(): bool
    {
        try {
            $this->pdo->query('SELECT apartado FROM vehiculos_solicitud LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function columnaAnioSolicitud(): string
    {
        try {
            $this->pdo->query('SELECT ao_auto FROM solicitudes_credito LIMIT 1');
            return 'ao_auto';
        } catch (PDOException $e) {
            return 'año_auto';
        }
    }

  /**
     * @return list<array{id: int, cedula_norm: string, correo_norm: string, nombre_norm: string}>
     */
    private function cargarSolicitudesIndice(): array
    {
        $stmt = $this->pdo->query('SELECT id, cedula, email, nombre_cliente FROM solicitudes_credito');
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) $row['id'],
                'cedula_norm' => self::normalizarCedula($row['cedula'] ?? ''),
                'correo_norm' => self::normalizarCorreo($row['email'] ?? ''),
                'nombre_norm' => self::normalizarNombre($row['nombre_cliente'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $linea
     * @param list<array<string, mixed>> $solicitudes
     * @return array{id: int, match_por: string}|null
     */
    private function buscarSolicitud(array $linea, array $solicitudes): ?array
    {
        $cedulaNorm = (string) ($linea['cedula_norm'] ?? '');
        $correoNorm = (string) ($linea['correo_norm'] ?? '');
        $nombreNorm = self::normalizarNombre($linea['nombre_cliente'] ?? '');

        if ($cedulaNorm !== '') {
            foreach ($solicitudes as $s) {
                if ($s['cedula_norm'] !== '' && $s['cedula_norm'] === $cedulaNorm) {
                    return ['id' => $s['id'], 'match_por' => 'cedula'];
                }
            }
        }

        if ($correoNorm !== '') {
            foreach ($solicitudes as $s) {
                if ($s['correo_norm'] !== '' && $s['correo_norm'] === $correoNorm) {
                    return ['id' => $s['id'], 'match_por' => 'email'];
                }
            }
        }

        if ($nombreNorm !== '') {
            foreach ($solicitudes as $s) {
                if ($s['nombre_norm'] !== '' && $s['nombre_norm'] === $nombreNorm) {
                    return ['id' => $s['id'], 'match_por' => 'nombre'];
                }
            }
            // Coincidencia parcial: nombre del Excel contenido en solicitud o viceversa
            foreach ($solicitudes as $s) {
                if ($s['nombre_norm'] === '' || mb_strlen($nombreNorm) < 6) {
                    continue;
                }
                if (str_contains($s['nombre_norm'], $nombreNorm) || str_contains($nombreNorm, $s['nombre_norm'])) {
                    return ['id' => $s['id'], 'match_por' => 'nombre'];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $linea
     */
    private function aplicarVehiculoYApartar(int $solicitudId, array $linea): int
    {
        $marca = trim((string) ($linea['marca'] ?? ''));
        $modelo = trim((string) ($linea['modelo'] ?? ''));
        $anio = isset($linea['anio']) ? (int) $linea['anio'] : null;
        $km = isset($linea['kilometraje']) ? (int) $linea['kilometraje'] : null;
        $precio = $linea['precio_total'] !== null ? (float) $linea['precio_total'] : null;
        $abonoMonto = $linea['abono_monto'] !== null ? (float) $linea['abono_monto'] : null;
        $abonoPct = $linea['abono_porcentaje'] !== null ? (float) $linea['abono_porcentaje'] : null;
        if ($abonoPct === null && $precio !== null && $precio > 0 && $abonoMonto !== null) {
            $abonoPct = round(($abonoMonto / $precio) * 100, 2);
        }
        $movId = trim((string) ($linea['mov_id'] ?? ''));
        $unidad = trim((string) ($linea['unidad'] ?? ''));

        $stmt = $this->pdo->prepare('SELECT * FROM vehiculos_solicitud WHERE solicitud_id = ? ORDER BY orden ASC, id ASC');
        $stmt->execute([$solicitudId]);
        $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $vehiculoId = null;
        foreach ($vehiculos as $v) {
            if ($this->vehiculoCoincide($v, $marca, $modelo, $anio)) {
                $vehiculoId = (int) $v['id'];
                break;
            }
        }

        if ($vehiculoId) {
            $upd = $this->pdo->prepare("
                UPDATE vehiculos_solicitud
                SET marca = ?, modelo = ?, anio = ?, kilometraje = ?, precio = ?,
                    abono_monto = ?, abono_porcentaje = ?, unidad = ?, apartado = 1, apartado_en = NOW(), mov_id_reserva = ?
                WHERE id = ?
            ");
            $upd->execute([
                $marca ?: null,
                $modelo ?: null,
                $anio ?: null,
                $km,
                $precio,
                $abonoMonto,
                $abonoPct,
                $unidad !== '' ? $unidad : null,
                $movId ?: null,
                $vehiculoId,
            ]);
        } else {
            $orden = count($vehiculos) + 1;
            $ins = $this->pdo->prepare("
                INSERT INTO vehiculos_solicitud (
                    solicitud_id, marca, modelo, anio, kilometraje, precio,
                    abono_porcentaje, abono_monto, unidad, orden, apartado, apartado_en, mov_id_reserva
                ) VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(),?)
            ");
            $ins->execute([
                $solicitudId,
                $marca ?: null,
                $modelo ?: null,
                $anio ?: null,
                $km,
                $precio,
                $abonoPct,
                $abonoMonto,
                $unidad !== '' ? $unidad : null,
                $orden,
                $movId ?: null,
            ]);
            $vehiculoId = (int) $this->pdo->lastInsertId();
        }

        $colAnio = $this->columnaAnioSolicitud();
        $this->pdo->prepare("
            UPDATE solicitudes_credito
            SET marca_auto = ?, modelo_auto = ?, `{$colAnio}` = ?, kilometraje = ?,
                precio_especial = ?, abono_monto = ?, abono_porcentaje = ?
            WHERE id = ?
        ")->execute([
            $marca ?: null,
            $modelo ?: null,
            $anio,
            $km,
            $precio,
            $abonoMonto,
            $abonoPct,
            $solicitudId,
        ]);

        return $vehiculoId;
    }

    private function vehiculoCoincide(array $v, string $marca, string $modelo, ?int $anio): bool
    {
        if ($marca === '' && $modelo === '') {
            return false;
        }
        $m1 = mb_strtoupper(trim((string) ($v['marca'] ?? '')), 'UTF-8');
        $m2 = mb_strtoupper(trim((string) ($v['modelo'] ?? '')), 'UTF-8');
        $targetMarca = mb_strtoupper($marca, 'UTF-8');
        $targetModelo = mb_strtoupper($modelo, 'UTF-8');
        if ($targetMarca !== '' && $m1 !== $targetMarca) {
            return false;
        }
        if ($targetModelo !== '' && $m2 !== $targetModelo) {
            return false;
        }
        if ($anio !== null && (int) ($v['anio'] ?? 0) !== $anio) {
            return false;
        }
        return true;
    }

    private function actualizarLinea(int $lineaId, array $data): void
    {
        $sets = [];
        $vals = [];
        foreach ($data as $col => $val) {
            $sets[] = "`$col` = ?";
            $vals[] = $val;
        }
        $vals[] = $lineaId;
        $sql = 'UPDATE reportes_reservas_lineas SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->pdo->prepare($sql)->execute($vals);
    }
}
