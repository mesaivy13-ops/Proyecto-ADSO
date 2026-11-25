<?php

require_once __DIR__ . '/../Confi/conexion.php';

class Prestamos
{
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
        $sql = "INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado, estado)
                VALUES (:valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo, :idAsociado, :estado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ":valorPrestamo" => $datos['valorPrestamo'],
            ":tasaInteres"  => $datos['tasaInteres'],
            ":numeroCuotas" => $datos['numeroCuotas'],
            ":fechaPrestamo" => $datos['fechaPrestamo'],
            ":idAsociado"   => $datos['idAsociado'],
            ":estado"       => $datos['estado']
        ]);
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
        $sql = "UPDATE prestamos
                SET valorPrestamo = :valorPrestamo,
                    tasaInteres  = :tasaInteres,
                    numeroCuotas = :numeroCuotas,
                    fechaPrestamo = :fechaPrestamo,
                    idAsociado   = :idAsociado,
                    estado       = :estado
                WHERE idPrestamo = :idPrestamo";
        $consulta = $this->conexion->prepare($sql);
        $ejecutado = $consulta->execute([
            ":valorPrestamo" => $datos['valorPrestamo'],
            ":tasaInteres"  => $datos['tasaInteres'],
            ":numeroCuotas" => $datos['numeroCuotas'],
            ":fechaPrestamo" => $datos['fechaPrestamo'],
            ":idAsociado"   => $datos['idAsociado'],
            ":estado"       => $datos['estado'],
            ":idPrestamo"   => $datos['idPrestamo']
        ]);

        if (!$ejecutado) {
            return false;
        }

        if ($consulta->rowCount() > 0) {
            return true;
        }

        $verificar = $this->conexion->prepare("SELECT 1 FROM prestamos WHERE idPrestamo = :idPrestamo");
        $verificar->execute([':idPrestamo' => $datos['idPrestamo']]);

        return (bool)$verificar->fetchColumn();
    }
}