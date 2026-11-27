<?php

require_once __DIR__ . '/../Confi/conexion.php';

class Actividad {
    private $conexion;

    public function __construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerActividades($documento){
        $idAsociado = $this->obtenerIdAsociado($documento);
        if ($idAsociado === false) {
            throw new RuntimeException("No se encontró el asociado de la sesión");
        }

        $sql = "SELECT a.*,
                       (SELECT COUNT(DISTINCT aa.idAsociado)
                        FROM actividades_asociado aa
                        WHERE aa.idActividad = a.idActividad) AS cantidadParticipantes,
                       EXISTS (
                           SELECT 1
                           FROM actividades_asociado aa
                           WHERE aa.idActividad = a.idActividad
                             AND aa.idAsociado = :idAsociadoParticipacion
                       ) AS participa,
                       (SELECT COUNT(DISTINCT aa.idAsociado)
                        FROM actividades_asociado aa
                        WHERE aa.idActividad = a.idActividad
                          AND aa.idAsociado < :idAsociadoPosicion) AS posicionParticipante
                FROM actividades a
                ORDER BY a.fechaEvento ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ":idAsociadoParticipacion" => $idAsociado,
            ":idAsociadoPosicion" => $idAsociado
        ]);
        $actividades = $consulta->fetchAll();

        foreach ($actividades as &$actividad) {
            $actividad["fondoClub"] = null;
            $actividad["fondoParticipantes"] = null;
            $actividad["valorParticipante"] = null;
            $actividad["valorParticipanteMin"] = null;
            $actividad["valorParticipanteMax"] = null;

            if ($actividad["totalRecaudado"] !== null) {
                $totalCentavos = self::pesosACentavos($actividad["totalRecaudado"]);
                $fondoClubCentavos = intdiv($totalCentavos + 1, 2);
                $fondoParticipantesCentavos = $totalCentavos - $fondoClubCentavos;
                $cantidadParticipantes = (int)$actividad["cantidadParticipantes"];

                $actividad["fondoClub"] = self::centavosAPesos($fondoClubCentavos);
                $actividad["fondoParticipantes"] = self::centavosAPesos($fondoParticipantesCentavos);

                if ($cantidadParticipantes > 0) {
                    $valorBase = intdiv($fondoParticipantesCentavos, $cantidadParticipantes);
                    $centavosRestantes = $fondoParticipantesCentavos % $cantidadParticipantes;
                    $actividad["valorParticipanteMin"] = self::centavosAPesos($valorBase);
                    $actividad["valorParticipanteMax"] = self::centavosAPesos(
                        $valorBase + ($centavosRestantes > 0 ? 1 : 0)
                    );

                    if ((bool)$actividad["participa"]) {
                        $posicion = (int)$actividad["posicionParticipante"];
                        $centavoAdicional = $posicion < $centavosRestantes ? 1 : 0;
                        $actividad["valorParticipante"] = self::centavosAPesos($valorBase + $centavoAdicional);
                    }
                }
            }
        }
        unset($actividad);

        return $actividades;
    }
    public function guardar($datos){
        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio) 
        VALUES (:nombreActividad,:fechaEvento,:municipio) ";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ":nombreActividad" => $datos['nombreActividad'],
            ":fechaEvento" => $datos['fechaEvento'],
            ":municipio" => $datos['municipio']
        ]);
    }
    public function eliminar($idActividad){
        $this->conexion->beginTransaction();
        try {
            $consultaActividad = $this->conexion->prepare(
                "SELECT idActividad FROM actividades WHERE idActividad = :idActividad FOR UPDATE"
            );
            $consultaActividad->execute([":idActividad" => $idActividad]);
            if ($consultaActividad->fetchColumn() === false) {
                $this->conexion->rollBack();
                return false;
            }

            $consultaParticipaciones = $this->conexion->prepare(
                "DELETE FROM actividades_asociado WHERE idActividad = :idActividad"
            );
            $consultaParticipaciones->execute([":idActividad" => $idActividad]);

            $consulta = $this->conexion->prepare(
                "DELETE FROM actividades WHERE idActividad = :idActividad"
            );
            $consulta->execute([":idActividad" => $idActividad]);
            $this->conexion->commit();
            return $consulta->rowCount() > 0;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function cambiarParticipacion($idActividad, $documento, $participar)
    {
        $this->conexion->beginTransaction();
        try {
            $consultaActividad = $this->conexion->prepare(
                "SELECT totalRecaudado
                 FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consultaActividad->execute([":idActividad" => $idActividad]);
            $totalRecaudado = $consultaActividad->fetchColumn();

            if ($totalRecaudado === false) {
                $this->conexion->rollBack();
                return false;
            }
            if ($totalRecaudado !== null) {
                throw new DomainException("La actividad ya fue cerrada y no admite cambios de participación");
            }

            $idAsociado = $this->obtenerIdAsociado($documento);
            if ($idAsociado === false) {
                throw new RuntimeException("No se encontró el asociado de la sesión");
            }

            if ($participar) {
                $consultaExiste = $this->conexion->prepare(
                    "SELECT 1
                     FROM actividades_asociado
                     WHERE idActividad = :idActividad AND idAsociado = :idAsociado
                     LIMIT 1"
                );
                $consultaExiste->execute([
                    ":idActividad" => $idActividad,
                    ":idAsociado" => $idAsociado
                ]);
                if (!$consultaExiste->fetchColumn()) {
                    $consulta = $this->conexion->prepare(
                        "INSERT INTO actividades_asociado (idActividad, idAsociado)
                         VALUES (:idActividad, :idAsociado)"
                    );
                    $consulta->execute([
                        ":idActividad" => $idActividad,
                        ":idAsociado" => $idAsociado
                    ]);
                }
            } else {
                $consulta = $this->conexion->prepare(
                    "DELETE FROM actividades_asociado
                     WHERE idActividad = :idActividad AND idAsociado = :idAsociado"
                );
                $consulta->execute([
                    ":idActividad" => $idActividad,
                    ":idAsociado" => $idAsociado
                ]);
            }

            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function editar($datos){
        $this->conexion->beginTransaction();
        try {
            $consultaActual = $this->conexion->prepare(
                "SELECT totalRecaudado
                 FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consultaActual->execute([":idActividad" => $datos["idActividad"]]);
            $totalActual = $consultaActual->fetchColumn();

            if ($totalActual === false) {
                $this->conexion->rollBack();
                return false;
            }
            if ($totalActual !== null && $datos["totalRecaudado"] === null) {
                throw new DomainException("No se puede reabrir una actividad que ya fue cerrada");
            }

            $sql = "UPDATE actividades
                    SET nombreActividad = :nombreActividad,
                        fechaEvento = :fechaEvento,
                        municipio = :municipio,
                        totalRecaudado = :totalRecaudado
                    WHERE idActividad = :idActividad";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":nombreActividad" => $datos["nombreActividad"],
                ":fechaEvento" => $datos["fechaEvento"],
                ":municipio" => $datos["municipio"],
                ":totalRecaudado" => $datos["totalRecaudado"],
                ":idActividad" => $datos["idActividad"]
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

    private function obtenerIdAsociado($documento)
    {
        $consulta = $this->conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE documento = :documento"
        );
        $consulta->execute([":documento" => $documento]);
        return $consulta->fetchColumn();
    }

    private static function pesosACentavos($pesos)
    {
        $partes = explode(".", (string)$pesos, 2);
        $pesosEnteros = (int)$partes[0];
        $centavos = isset($partes[1]) ? (int)str_pad(substr($partes[1], 0, 2), 2, "0") : 0;
        return $pesosEnteros * 100 + $centavos;
    }

    private static function centavosAPesos($centavos)
    {
        return number_format($centavos / 100, 2, ".", "");
    }
}