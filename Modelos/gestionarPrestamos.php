<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

function leerDatos()
{
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

function validarPrestamo($datos)
{
    $idAsociado = filter_var($datos["idAsociado"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $valorPrestamo = filter_var($datos["valorPrestamo"] ?? null, FILTER_VALIDATE_FLOAT);
    $tasaInteres = filter_var($datos["tasaInteres"] ?? null, FILTER_VALIDATE_FLOAT);
    $numeroCuotas = filter_var($datos["numeroCuotas"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $fechaPrestamo = trim((string)($datos["fechaPrestamo"] ?? ""));
    $estado = trim((string)($datos["estado"] ?? "Pendiente"));

    if ($idAsociado === false) {
        http_response_code(400);
        throw new Exception("Seleccione un asociado válido");
    }

    if ($valorPrestamo === false || $valorPrestamo <= 0 || $valorPrestamo > 9999999999.99) {
        http_response_code(400);
        throw new Exception("Ingrese un valor de préstamo válido");
    }

    if ($tasaInteres === false || $tasaInteres < 0 || $tasaInteres > 999.99) {
        http_response_code(400);
        throw new Exception("Ingrese una tasa de interés válida");
    }

    if ($numeroCuotas === false) {
        http_response_code(400);
        throw new Exception("Ingrese un número de cuotas válido");
    }

    $fechaObj = DateTime::createFromFormat("Y-m-d", $fechaPrestamo);
    if (!$fechaObj || $fechaObj->format("Y-m-d") !== $fechaPrestamo) {
        http_response_code(400);
        throw new Exception("Ingrese una fecha de préstamo válida (YYYY-MM-DD)");
    }

    // Ajusta estos valores según las opciones exactas de tu ENUM en MySQL
    $estadosPermitidos = ["Pendiente", "Aprobado", "Pagado", "Rechazado"];
    if (!in_array($estado, $estadosPermitidos, true)) {
        http_response_code(400);
        throw new Exception("El estado del préstamo no es válido");
    }

    return [
        "idAsociado"    => $idAsociado,
        "valorPrestamo" => number_format($valorPrestamo, 2, ".", ""),
        "tasaInteres"  => number_format($tasaInteres, 2, ".", ""),
        "numeroCuotas" => $numeroCuotas,
        "fechaPrestamo" => $fechaPrestamo,
        "estado"        => $estado
    ];
}

function validarId($id)
{
    $idPrestamo = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idPrestamo === false) {
        http_response_code(400);
        throw new Exception("El identificador del préstamo no es válido");
    }
    return $idPrestamo;
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los préstamos");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];

    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    if ($metodo !== "GET" && $_SESSION["rol"] !== "admin") {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/prestamos.php";
    $prestamosModelo = new Prestamos();

    switch ($metodo) {
        case "GET":
            if (isset($_GET["action"]) && $_GET["action"] === "asociados") {
                $resultado = [
                    "success"   => true,
                    "rol"       => $_SESSION["rol"],
                    "asociados" => $prestamosModelo->obtenerAsociados()
                ];
                http_response_code(200);
                break;
            }

            $resultado = [
                "success"   => true,
                "rol"       => $_SESSION["rol"],
                "prestamos" => $prestamosModelo->obtenerPrestamos()
            ];
            http_response_code(200);
            break;

        case "POST":
            $datos = validarPrestamo(leerDatos());
            $prestamosModelo->guardar($datos);
            $resultado = [
                "success" => true,
                "message" => "Préstamo creado exitosamente"
            ];
            http_response_code(201);
            break;

        case "PUT":
            $datos = leerDatos();
            $idPrestamo = validarId($datos["idPrestamo"] ?? null);
            $datosValidados = validarPrestamo($datos);
            $datosValidados["idPrestamo"] = $idPrestamo;

            if (!$prestamosModelo->editar($datosValidados)) {
                http_response_code(404);
                throw new Exception("No se encontró el préstamo");
            }

            $resultado = [
                "success" => true,
                "message" => "Préstamo actualizado exitosamente"
            ];
            http_response_code(200);
            break;

        case "DELETE":
            $datos = leerDatos();
            $idPrestamo = validarId($datos["idPrestamo"] ?? ($_GET["id"] ?? null));

            if (!$prestamosModelo->eliminar($idPrestamo)) {
                http_response_code(404);
                throw new Exception("No se encontró el préstamo");
            }

            $resultado = [
                "success" => true,
                "message" => "Préstamo eliminado exitosamente"
            ];
            http_response_code(200);
            break;
    }

    echo json_encode($resultado);
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