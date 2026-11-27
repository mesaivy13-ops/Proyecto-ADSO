<?php

require_once __DIR__ . '/../Confi/conexion.php';

class Prestamos
{
    private const MAX_PRESTAMOS_ACTIVOS = 3;
    private const MAX_SALDO_ACTIVO = 80000000;

    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerPrestamos()
    {
        $sql = "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado, estado
                FROM prestamos
                ORDER BY fechaPrestamo DESC, idPrestamo DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function obtenerPrestamosPorDocumento($documento)
    {
        $sql = "SELECT p.idPrestamo, p.valorPrestamo, p.tasaInteres, p.numeroCuotas,
                       p.fechaPrestamo, p.idAsociado, p.estado,
                       CONCAT(a.nombres, ' ', a.apellidos) AS nombreAsociado
                FROM prestamos p
                INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                WHERE a.documento = :documento
                ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":documento" => $documento]);
        return $consulta->fetchAll();
    }

    public function obtenerAsociados()
    {
        $sql = "SELECT idAsociado, nombres, apellidos
                FROM asociados
                ORDER BY idAsociado ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function obtenerAsociadoPorDocumento($documento)
    {
        $sql = "SELECT idAsociado, nombres, apellidos
                FROM asociados
                WHERE documento = :documento";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":documento" => $documento]);
        $asociado = $consulta->fetch();
        return $asociado ? [$asociado] : [];
    }

    public function guardar($datos)
    {
        $this->conexion->beginTransaction();
        try {
            $this->bloquearAsociados([$datos['idAsociado']]);
            $this->validarLimitesActivos($datos);

            $sql = "INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado, estado)
                    VALUES (:valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo, :idAsociado, :estado)";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":valorPrestamo" => $datos['valorPrestamo'],
                ":tasaInteres"  => $datos['tasaInteres'],
                ":numeroCuotas" => $datos['numeroCuotas'],
                ":fechaPrestamo" => $datos['fechaPrestamo'],
                ":idAsociado"   => $datos['idAsociado'],
                ":estado"       => $datos['estado']
            ]);
            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function eliminar($idPrestamo)
    {
        $sql = "DELETE FROM prestamos WHERE idPrestamo = :idPrestamo";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idPrestamo' => $idPrestamo]);
        return $consulta->rowCount() > 0; // Retorna true si se eliminó alguna fila
    }

    public function editar($datos)
    {
        $this->conexion->beginTransaction();
        try {
            $actual = $this->conexion->prepare(
                "SELECT idAsociado FROM prestamos WHERE idPrestamo = :idPrestamo FOR UPDATE"
            );
            $actual->execute([":idPrestamo" => $datos['idPrestamo']]);
            $idAsociadoActual = $actual->fetchColumn();
            if ($idAsociadoActual === false) {
                $this->conexion->rollBack();
                return false;
            }

            $this->bloquearAsociados([(int)$idAsociadoActual, $datos['idAsociado']]);
            $this->validarLimitesActivos($datos, $datos['idPrestamo']);

            $sql = "UPDATE prestamos
                    SET valorPrestamo = :valorPrestamo,
                        tasaInteres  = :tasaInteres,
                        numeroCuotas = :numeroCuotas,
                        fechaPrestamo = :fechaPrestamo,
                        idAsociado   = :idAsociado,
                        estado       = :estado
                    WHERE idPrestamo = :idPrestamo";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":valorPrestamo" => $datos['valorPrestamo'],
                ":tasaInteres"  => $datos['tasaInteres'],
                ":numeroCuotas" => $datos['numeroCuotas'],
                ":fechaPrestamo" => $datos['fechaPrestamo'],
                ":idAsociado"   => $datos['idAsociado'],
                ":estado"       => $datos['estado'],
                ":idPrestamo"   => $datos['idPrestamo']
            ]);
            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public static function calcularCuota($valorPrestamo, $tasaInteres, $numeroCuotas, $numeroCuota)
    {
        $tasaMensual = (float)$tasaInteres / 100;
        $cuotaRegular = $tasaMensual === 0.0
            ? round((float)$valorPrestamo / $numeroCuotas, 2)
            : round(
                (float)$valorPrestamo * $tasaMensual /
                    (1 - pow(1 + $tasaMensual, -$numeroCuotas)),
                2
            );

        if ($numeroCuota < $numeroCuotas) {
            return $cuotaRegular;
        }

        $saldoAntesDeLaUltima = self::calcularCapitalPendiente(
            $valorPrestamo,
            $tasaMensual,
            $numeroCuotas,
            $numeroCuotas - 1
        );
        $interesFinal = round($saldoAntesDeLaUltima * $tasaMensual, 2);
        return round($saldoAntesDeLaUltima + $interesFinal, 2);
    }

    public static function calcularSaldoPendiente($valorPrestamo, $tasaInteres, $numeroCuotas, $cuotasPagadas)
    {
        if ($cuotasPagadas >= $numeroCuotas) {
            return 0;
        }

        $cuotasRegularesRestantes = max(0, $numeroCuotas - 1 - $cuotasPagadas);
        $cuotaRegular = self::calcularCuota(
            $valorPrestamo,
            $tasaInteres,
            $numeroCuotas,
            1
        );
        $ultimaCuota = self::calcularCuota(
            $valorPrestamo,
            $tasaInteres,
            $numeroCuotas,
            $numeroCuotas
        );

        return round(($cuotaRegular * $cuotasRegularesRestantes) + $ultimaCuota, 2);
    }

    private static function calcularCapitalPendiente($valorPrestamo, $tasaMensual, $numeroCuotas, $cuotasPagadas)
    {
        if ($cuotasPagadas <= 0) {
            return round((float)$valorPrestamo, 2);
        }

        $cuotaRegular = $tasaMensual === 0.0
            ? round((float)$valorPrestamo / $numeroCuotas, 2)
            : round(
                (float)$valorPrestamo * $tasaMensual /
                    (1 - pow(1 + $tasaMensual, -$numeroCuotas)),
                2
            );

        if ($tasaMensual === 0.0) {
            return max(0, round((float)$valorPrestamo - ($cuotaRegular * $cuotasPagadas), 2));
        }

        $factor = pow(1 + $tasaMensual, -($numeroCuotas - $cuotasPagadas));
        return max(0, round($cuotaRegular * (1 - $factor) / $tasaMensual, 2));
    }

    private function bloquearAsociados($idsAsociados)
    {
        $idsAsociados = array_values(array_unique(array_map('intval', $idsAsociados)));
        sort($idsAsociados);
        $placeholders = implode(",", array_fill(0, count($idsAsociados), "?"));
        $consulta = $this->conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE idAsociado IN ($placeholders) ORDER BY idAsociado FOR UPDATE"
        );
        $consulta->execute($idsAsociados);
        if (count($consulta->fetchAll()) !== count($idsAsociados)) {
            throw new InvalidArgumentException("No se encontró el asociado seleccionado");
        }
    }

    private function validarLimitesActivos($datos, $idPrestamoExcluir = null)
    {
        if (!in_array($datos['estado'], ["Pendiente", "Aprobado"], true)) {
            return;
        }

        $sql = "SELECT p.valorPrestamo, p.tasaInteres, p.numeroCuotas,
                       (SELECT COUNT(*) FROM pagos_cuotas pg WHERE pg.idPrestamo = p.idPrestamo) AS cuotasPagadas
                FROM prestamos p
                WHERE p.idAsociado = :idAsociado
                  AND p.estado IN ('Pendiente', 'Aprobado')";
        $parametros = [":idAsociado" => $datos['idAsociado']];
        if ($idPrestamoExcluir !== null) {
            $sql .= " AND p.idPrestamo <> :idPrestamo";
            $parametros[":idPrestamo"] = $idPrestamoExcluir;
        }

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        $prestamosActivos = $consulta->fetchAll();

        if (count($prestamosActivos) >= self::MAX_PRESTAMOS_ACTIVOS) {
            throw new LimitePrestamoException("El asociado ya tiene 3 préstamos activos");
        }

        $saldoActivo = 0;
        foreach ($prestamosActivos as $prestamoActivo) {
            $saldoActivo += self::calcularSaldoPendiente(
                $prestamoActivo['valorPrestamo'],
                $prestamoActivo['tasaInteres'],
                (int)$prestamoActivo['numeroCuotas'],
                (int)$prestamoActivo['cuotasPagadas']
            );
        }

        $saldoNuevo = self::calcularSaldoPendiente(
            $datos['valorPrestamo'],
            $datos['tasaInteres'],
            $datos['numeroCuotas'],
            0
        );
        if (round($saldoActivo + $saldoNuevo, 2) > self::MAX_SALDO_ACTIVO) {
            throw new LimitePrestamoException(
                "El saldo total de los préstamos activos no puede superar $80.000.000"
            );
        }
    }
}

class LimitePrestamoException extends RuntimeException
{
}