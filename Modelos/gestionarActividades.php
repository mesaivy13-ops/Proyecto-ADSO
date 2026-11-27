<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

function leerDatos(){
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}
function validarActividad($datos){
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento']?? null;
    $municipio = $datos['municipio']?? null;
    if (!is_string($nombre) || trim($nombre) === "" || strlen($nombre) > 100 || !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) {
        http_response_code(400);
        throw new Exception(
            "Ingrese nombre y municipio válidos"
        );
    }
    return [
        "nombreActividad"=>trim($nombre),
        "fechaEvento"=>$fecha,
        "municipio"=>trim($municipio)
    ];
} 

function validarId($id){
    $idActividad=filter_var($id,FILTER_VALIDATE_INT,["options" => ["min_range" => 1]]);
    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es válido");
        
    }
    return $idActividad;
}

function validarTotalRecaudado($total)
{
    if ($total === null || $total === "") {
        return null;
    }

    if (!is_numeric($total) || !is_finite((float)$total) || (float)$total < 0) {
        http_response_code(400);
        throw new Exception("Ingrese un total recaudado válido");
    }

    return number_format((float)$total, 2, ".", "");
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar las actividades");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    $accionParticipacion = ($_GET["action"] ?? "") === "participar";

    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }
    if ($metodo !== "GET" && $_SESSION["rol"] !== "admin" &&
        !($accionParticipacion && in_array($metodo, ["POST", "DELETE"], true))) {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__."/actividad.php";
    $actividad = new Actividad();

    switch($metodo){
        case "GET":
            $resultado=[
                "success" => true,
                "rol" => $_SESSION["rol"],
                "actividades" => $actividad->obtenerActividades($_SESSION["documento"])
            ];
            http_response_code(200);
            break;
        case "POST":
            if ($accionParticipacion) {
                $datos = leerDatos();
                $idActividad = validarId($datos["idActividad"] ?? null);
                if (!$actividad->cambiarParticipacion($idActividad, $_SESSION["documento"], true)) {
                    http_response_code(404);
                    throw new Exception("No se encontró la actividad");
                }
                $resultado = [
                    "success" => true,
                    "message" => "Participación registrada correctamente"
                ];
                http_response_code(200);
                break;
            }

            $datos = validarActividad(leerDatos());
            $actividad->guardar($datos);
            $resultado =[
                "success"=> true,
                "message" => "Actividad creada exitosamente"
            ];
            http_response_code(201);
            break;
        case "PUT":
            $datos=leerDatos();
            $datos["idActividad"]= validarId($datos["idActividad"]?? null);
            $datos = array_merge($datos,validarActividad($datos));
            $datos["totalRecaudado"] = validarTotalRecaudado($datos["totalRecaudado"] ?? null);
            if (!$actividad->editar($datos)) {
                http_response_code(404);
                throw new Exception("No se encontro la actividad");
            }
            $resultado =[
                "success"=> true,
                "message" => "Actividad actualizada exitosamente"
            ];
            http_response_code(200);
            break;
        case "DELETE":
            $datos=leerDatos();
            $datos["idActividad"]= validarId($datos["idActividad"]?? null);
            if ($accionParticipacion) {
                if (!$actividad->cambiarParticipacion($datos["idActividad"], $_SESSION["documento"], false)) {
                    http_response_code(404);
                    throw new Exception("No se encontró la actividad");
                }
                $resultado = [
                    "success" => true,
                    "message" => "Participación cancelada correctamente"
                ];
                http_response_code(200);
                break;
            }

            if (!$actividad->eliminar($datos["idActividad"])) {
                http_response_code(404);
                throw new Exception("No se encontro la actividad");
            }
            $resultado =[
                "success"=> true,
                "message" => "Actividad elimindada exitosamente"
            ];
            http_response_code(200);
            break;
            
            
    }
    echo json_encode($resultado);
} catch (DomainException $e) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Error en la base de datos: " . $e->getMessage()]);
} catch(Exception $e){
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()]);
}
