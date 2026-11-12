<?php

require_once __DIR__ . '/../Confi/conexion.php';

class Actividad {
    private $conexion;

    public function__construct()
    {
        $conexionDB = new Conexion();
        $this->conexion = $conexion->conectar();
    }

    public function obtenerActividades(){
        $sql= "SELECT * FROM actividades ORDER BY fechaEvento ASC"
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }
    public function guardar(){
        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio) 
        VALUES (:nombreActividad,:fechaEvento,:municipio) ";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ":nombreActividad" => $datos['nombreActividad'],
            ":fechaEvento" => $datos['fechaEvento'],
            ":municipio" => $datos['municipio']
        ])
    }
    public function eliminar($idActividad){
        $sql = "DELETE FROM actividades WHERE idActividad= :idActividad";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->rowCount() > 0;//Retorna true si se elimino alguna fila
    }
    public function editar($datos){
    $sql = "UPDATE actividades SET nombreActividad = :nombreActividad, fechaEvento = :fechaEvento, municipio = :municipio, totalRecaudado = :totalRecaudado WHERE idActividad = :idActividad";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
         ":nombreActividad" => $datos['nombreActividad'],
         ":fechaEvento" => $datos['fechaEvento'],
         ":municipio" => $datos['municipio'],
         ":totalRecaudo" => $datos['totalRecaudo'],
         ":idActividad" => $datos['idActividad']
    ]);
    if ($consulta->rowCount() > 0) {
        return true;

    }
    $verificar = $this->conexion->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad")
    $verificar->execute([':idActividad' => $datos['idActividad']]);

    return (bool)$verificar->fetchColumn();
}
}