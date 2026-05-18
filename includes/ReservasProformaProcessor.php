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
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
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
            return ['success' => false, 'message' => 'No se encontraron filas de datos desde la fila 4'];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM reportes_reservas_lineas WHERE reporte_id = ?');
            $del->execute([$this->reporteId]);

            $ins = $this->pdo->prepare("
                INSERT INTO reportes_reservas_lineas (
                    reporte_id, fila_excel, mov, mov_id, fecha_emision, dias_reserva,
                    nombre_sucursal, nombre_vendedor, cliente_codigo, nombre_cliente,
                    cedula, cedula_norm, correo_cliente, correo_norm,
                    marca, modelo, anio, kilometraje, precio_total, abono_monto, abono_porcentaje,
                    unidad, chasis, placas, estado
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pendiente')
            ");

            foreach ($filas as $f) {
                $precio = self::parseNumero($f['precio_total'] ?? null);
                $abono = self::parseNumero($f['abono_monto'] ?? null);
                $pct = null;
                if ($precio !== null && $precio > 0 && $abono !== null) {
                    $pct = round(($abono / $precio) * 100, 2);
                }
                $ins->execute([
                    $this->reporteId,
                    (int) ($f['fila_excel'] ?? 0),
                    $f['mov'] ?: null,
                    $f['mov_id'] ?: null,
                    self::parseFecha($f['fecha_emision'] ?? null),
                    is_numeric($f['dias_reserva'] ?? '') ? (int) $f['dias_reserva'] : null,
                    $f['nombre_sucursal'] ?: null,
                    $f['nombre_vendedor'] ?: null,
                    $f['cliente_codigo'] ?: null,
                    $f['nombre_cliente'] ?: null,
                    $f['cedula'] ?: null,
                    self::normalizarCedula($f['cedula'] ?? '') ?: null,
                    $f['correo_cliente'] ?: null,
                    self::normalizarCorreo($f['correo_cliente'] ?? '') ?: null,
                    $f['marca'] ?: null,
                    $f['modelo'] ?: null,
                    is_numeric($f['anio'] ?? '') ? (int) $f['anio'] : null,
                    is_numeric($f['kilometraje'] ?? '') ? (int) preg_replace('/\D/', '', (string) $f['kilometraje']) : null,
                    $precio,
                    $abono,
                    $pct,
                    $f['unidad'] ?: null,
                    $f['chasis'] ?: null,
                    $f['placas'] ?: null,
                ]);
            }

            $this->pdo->prepare("
                UPDATE reportes_reservas
                SET filas_total = ?, estado = 'pendiente', filas_aplicadas = 0, filas_sin_coincidencia = 0, fecha_procesado = NULL
                WHERE id = ?
            ")->execute([count($filas), $this->reporteId]);

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => count($filas) . ' filas importadas. Ejecute Procesar para aplicar a solicitudes.',
                'stats' => ['filas_total' => count($filas)],
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
                    abono_monto = ?, abono_porcentaje = ?, apartado = 1, apartado_en = NOW(), mov_id_reserva = ?
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
                $movId ?: null,
                $vehiculoId,
            ]);
        } else {
            $orden = count($vehiculos) + 1;
            $ins = $this->pdo->prepare("
                INSERT INTO vehiculos_solicitud (
                    solicitud_id, marca, modelo, anio, kilometraje, precio,
                    abono_porcentaje, abono_monto, orden, apartado, apartado_en, mov_id_reserva
                ) VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),?)
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
