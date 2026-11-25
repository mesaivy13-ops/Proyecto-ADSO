<?php

session_start();
header("Content-Type: application/json; charset=utf-8");

function leerDatosPerfil()
{
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        http_response_code(400);
        throw new Exception("Los datos enviados no son válidos");
    }
    return $datos;
}

function validarDatosPerfil($datos)
{
    $campos = [
        "documento" => 100,
        "nombres" => 100,
        "apellidos" => 100,
        "fechaNacimiento" => 10,
        "genero" => 20,
        "direccionResidencia" => 150,
        "ciudad" => 100,
        "telefono" => 20,
        "email" => 120
    ];

    if (array_diff(array_keys($datos), array_keys($campos))) {
        http_response_code(400);
        throw new Exception("Solo puedes actualizar tus datos personales");
    }

    $validados = [];
    foreach ($campos as $campo => $maximo) {
        $valor = $datos[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === "" || mb_strlen(trim($valor), "UTF-8") > $maximo) {
            http_response_code(400);
            throw new Exception("El campo " . $campo . " es obligatorio o no es válido");
        }
        $validados[$campo] = trim($valor);
    }

    $fecha = DateTime::createFromFormat("Y-m-d", $validados["fechaNacimiento"]);
    if (!$fecha || $fecha->format("Y-m-d") !== $validados["fechaNacimiento"]) {
        http_response_code(400);
        throw new Exception("La fecha de nacimiento no es válida");
    }

    if (!in_array($validados["genero"], ["Femenino", "Masculino", "Otro"], true)) {
        http_response_code(400);
        throw new Exception("Selecciona un género válido");
    }

    if (!filter_var($validados["email"], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("El correo electrónico no es válido");
    }

    return $validados;
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar tus datos");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "PUT"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    require_once __DIR__ . "/../Confi/conexion.php";
    $conexion = (new Conexion())->conectar();
    $campos = "documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, telefono, email";

    if ($metodo === "GET") {
        $consulta = $conexion->prepare("SELECT $campos FROM asociados WHERE documento = :documento");
        $consulta->execute([":documento" => $_SESSION["documento"]]);
        $datos = $consulta->fetch();
        if (!$datos) {
            http_response_code(404);
            throw new Exception("No se encontraron tus datos");
        }
        echo json_encode(["success" => true, "datos" => $datos]);
        exit;
    }

    $datos = validarDatosPerfil(leerDatosPerfil());
    $consulta = $conexion->prepare(
        "UPDATE asociados
         SET documento = :documentoNuevo,
             nombres = :nombres,
             apellidos = :apellidos,
             fechaNacimiento = :fechaNacimiento,
             genero = :genero,
             direccionResidencia = :direccionResidencia,
             ciudad = :ciudad,
             telefono = :telefono,
             email = :email
         WHERE documento = :documentoAnterior"
    );
    $consulta->execute([
        ":documentoNuevo" => $datos["documento"],
        ":nombres" => $datos["nombres"],
        ":apellidos" => $datos["apellidos"],
        ":fechaNacimiento" => $datos["fechaNacimiento"],
        ":genero" => $datos["genero"],
        ":direccionResidencia" => $datos["direccionResidencia"],
        ":ciudad" => $datos["ciudad"],
        ":telefono" => $datos["telefono"],
        ":email" => $datos["email"],
        ":documentoAnterior" => $_SESSION["documento"]
    ]);

    if ($consulta->rowCount() === 0) {
        $verificar = $conexion->prepare("SELECT 1 FROM asociados WHERE documento = :documento");
        $verificar->execute([":documento" => $_SESSION["documento"]]);
        if (!$verificar->fetchColumn()) {
            http_response_code(404);
            throw new Exception("No se encontraron tus datos");
        }
    }

    $_SESSION["documento"] = $datos["documento"];
    echo json_encode([
        "success" => true,
        "message" => "Datos actualizados correctamente",
        "datos" => $datos
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El documento o correo ya está registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error en la base de datos"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
