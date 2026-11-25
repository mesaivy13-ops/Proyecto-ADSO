<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

function leerDatosAsociado(){
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Datos inválidos");
    }
    return $datos;
}

function validarDocumento($documento){
    $documento = trim((string)($documento ?? ""));
    if ($documento === "" || strlen($documento) > 100) {
        http_response_code(400);
        throw new Exception("Documento inválido");
    }
    return $documento;
}

function validarId($id){
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        http_response_code(400);
        throw new Exception("ID de asociado inválido");
    }
    return $id;
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los asociados");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }
    if ($metodo !== "GET" && $_SESSION["rol"] !== "admin") {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__."/Asociado.php";
    $asociado = new Asociado([
        "documento" => "",
        "nombres" => "",
        "apellidos" => "",
        "fechaNacimiento" => "",
        "genero" => "",
        "direccionResidencia" => "",
        "ciudad" => "",
        "telefono" => "",
        "email" => "",
        "contraseña" => ""
    ]);

    if ($metodo === "GET") {
        echo json_encode([
            "success" => true,
            "rol" => $_SESSION["rol"],
            "asociados" => $asociado->obtenerAsociados()
        ]);
        exit;
    }

    $datos = leerDatosAsociado();

    if ($metodo === "DELETE") {
        $idAsociado = validarId($datos["idAsociado"] ?? null);
        if (!$asociado->eliminar($idAsociado)) {
            http_response_code(404);
            throw new Exception("No se encontró el asociado");
        }

        echo json_encode([
            "success" => true,
            "message" => "Asociado eliminado exitosamente"
        ]);
        exit;
    }

    $documentoAnterior = validarDocumento($datos["documentoAnterior"] ?? null);
    $documento = validarDocumento($datos["documento"] ?? null);

    $camposTexto = [
        "nombres" => "Ingrese nombres válidos",
        "apellidos" => "Ingrese apellidos válidos",
        "fechaNacimiento" => "Ingrese una fecha de nacimiento válida",
        "genero" => "Seleccione un género válido",
        "direccionResidencia" => "Ingrese una dirección válida",
        "ciudad" => "Ingrese una ciudad válida",
        "telefono" => "Ingrese un teléfono válido",
        "email" => "Ingrese un correo válido",
        "rol" => "Seleccione un rol válido"
    ];

    foreach ($camposTexto as $campo => $mensaje) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === "") {
            http_response_code(400);
            throw new Exception($mensaje);
        }
    }

    if (!filter_var($datos["email"], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("Correo electrónico inválido");
    }

    $datos["rol"] = strtolower(trim((string)$datos["rol"]));
    if (!in_array($datos["rol"], ["admin", "asociado"], true)) {
        http_response_code(400);
        throw new Exception("Rol inválido");
    }

    $datos["documentoAnterior"] = $documentoAnterior;
    $datos["documento"] = $documento;

    if (!$asociado->editar($datos)) {
        http_response_code(404);
        throw new Exception("No se encontró el asociado");
    }

    echo json_encode([
        "success" => true,
        "message" => "Asociado actualizado exitosamente"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
