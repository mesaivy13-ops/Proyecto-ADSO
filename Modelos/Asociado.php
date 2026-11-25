<?php

require_once __DIR__ . '/../Confi/conexion.php';

class Asociado{
    private $documento;
    private $nombres;
    private $apellidos;
    private $fechaNacimiento;
    private $genero;
    private $direccionResidencia;
    private $ciudad;
    private $telefono;
    private $email;
    private $contraseña;

    private $conexion;

    public function __construct($datos)
    {
        $this->documento = $datos['documento'];
        $this->nombres = $datos['nombres'];
        $this->apellidos = $datos['apellidos'];
        $this->fechaNacimiento = $datos['fechaNacimiento'];
        $this->genero = $datos['genero'];
        $this->direccionResidencia = $datos['direccionResidencia'];
        $this->ciudad = $datos['ciudad'];
        $this->email = $datos['email'];
        $this->telefono = $datos['telefono'];
        $this->contraseña = password_hash($datos['contraseña'], PASSWORD_DEFAULT);

        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }
    public function guardar(){
    $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, email, telefono, contraseña) VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :email, :telefono, :contrasena)";
    $consulta = $this->conexion->prepare($sql);
    return $consulta->execute([
        ":documento" => $this->documento,
        ":nombres" => $this->nombres,
        ":apellidos" => $this->apellidos,
        ":fechaNacimiento" => $this->fechaNacimiento,
        ":genero" => $this->genero,
        ":direccionResidencia" => $this->direccionResidencia,
        ":ciudad" => $this->ciudad,
        ":email" => $this->email,
        ":telefono" => $this->telefono,
        ":contrasena" => $this->contraseña
    ]);
}
    public function obtenerAsociados(){
        $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento, genero,
                       direccionResidencia, ciudad, telefono, email, rol
                FROM asociados
                ORDER BY nombres ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }
    public function obtenerPorDocumento($documento){
        $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento, genero,
                       direccionResidencia, ciudad, telefono, email, rol
                FROM asociados
                WHERE documento = :documento";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":documento" => $documento]);
        $asociado = $consulta->fetch();
        return $asociado ? [$asociado] : [];
    }
    public function eliminar($idAsociado){
        $sql = "DELETE FROM asociados WHERE idAsociado = :idAsociado";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idAsociado' => $idAsociado]);
        return $consulta->rowCount() > 0;
    }
    public function editar($datos){
        $sql = "UPDATE asociados
                SET documento = :documentoNuevo,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    fechaNacimiento = :fechaNacimiento,
                    genero = :genero,
                    direccionResidencia = :direccionResidencia,
                    ciudad = :ciudad,
                    telefono = :telefono,
                    email = :email,
                    rol = :rol
                WHERE documento = :documentoAnterior";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ":documentoNuevo" => $datos['documento'],
            ":nombres" => $datos['nombres'],
            ":apellidos" => $datos['apellidos'],
            ":fechaNacimiento" => $datos['fechaNacimiento'],
            ":genero" => $datos['genero'],
            ":direccionResidencia" => $datos['direccionResidencia'],
            ":ciudad" => $datos['ciudad'],
            ":telefono" => $datos['telefono'],
            ":email" => $datos['email'],
            ":rol" => $datos['rol'],
            ":documentoAnterior" => $datos['documentoAnterior']
        ]);

        if ($consulta->rowCount() > 0) {
            return true;
        }

        $verificar = $this->conexion->prepare(
            "SELECT 1 FROM asociados
             WHERE documento = :documentoAnterior
                OR documento = :documentoNuevo
             LIMIT 1"
        );
        $verificar->execute([
            ':documentoAnterior' => $datos['documentoAnterior'],
            ':documentoNuevo' => $datos['documento']
        ]);
        return (bool)$verificar->fetchColumn();
    }
}
?>