<?php

session_start();
header("Content-Type: application/json; charset=utf-8");

function leerJson() {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400);
        throw new Exception("Datos JSON inválidos");
    }
    return $data;
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para gestionar pagos");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "POST", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    require_once __DIR__ . "/../Confi/conexion.php";
    require_once __DIR__ . "/prestamos.php";
    $conexionDB = new Conexion();
    $conexion = $conexionDB->conectar();

    $rol = $_SESSION["rol"];
    $documentoSesion = $_SESSION["documento"];

    if ($metodo === "GET") {
        $sqlPrestamos = "
            SELECT
                p.idPrestamo,
                p.valorPrestamo AS montoPrestamo,
                p.tasaInteres,
                p.numeroCuotas,
                p.fechaPrestamo,
                p.estado,
                a.documento,
                CONCAT(a.nombres, ' ', a.apellidos) AS nombreAsociado,
                COALESCE(SUM(pg.valorCuota), 0) AS totalPagado,
                COUNT(pg.idPago) AS cuotasPagadas
            FROM prestamos p
            INNER JOIN asociados a ON a.idAsociado = p.idAsociado
            LEFT JOIN pagos_cuotas pg ON pg.idPrestamo = p.idPrestamo
        ";

        $params = [];
        if ($rol !== "admin") {
            $sqlPrestamos .= " WHERE a.documento = :documento ";
            $params[":documento"] = $documentoSesion;
        }

        $sqlPrestamos .= "
            GROUP BY p.idPrestamo, p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo,
                     p.estado, a.documento, a.nombres, a.apellidos
            ORDER BY p.idPrestamo DESC
        ";

        $stmtP = $conexion->prepare($sqlPrestamos);
        $stmtP->execute($params);
        $prestamos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        foreach ($prestamos as &$pr) {
            $pr["montoPrestamo"] = (float)$pr["montoPrestamo"];
            $pr["totalPagado"] = (float)$pr["totalPagado"];
            $pr["cuotasPagadas"] = (int)$pr["cuotasPagadas"];
            $pr["tasaInteres"] = (float)$pr["tasaInteres"];
            $pr["saldoPendiente"] = Prestamos::calcularSaldoPendiente(
                $pr["montoPrestamo"],
                $pr["tasaInteres"],
                (int)$pr["numeroCuotas"],
                $pr["cuotasPagadas"]
            );
            $pr["valorCuotaEsperada"] = $pr["cuotasPagadas"] < (int)$pr["numeroCuotas"]
                ? Prestamos::calcularCuota(
                    $pr["montoPrestamo"],
                    $pr["tasaInteres"],
                    (int)$pr["numeroCuotas"],
                    $pr["cuotasPagadas"] + 1
                )
                : 0;
        }
        unset($pr);

        $sqlPagos = "
            SELECT
                pg.idPago,
                pg.idPrestamo,
                pg.valorCuota,
                pg.fechaPago,
                a.documento,
                CONCAT(a.nombres, ' ', a.apellidos) AS nombreAsociado
            FROM pagos_cuotas pg
            INNER JOIN prestamos p ON p.idPrestamo = pg.idPrestamo
            INNER JOIN asociados a ON a.idAsociado = p.idAsociado
        ";

        if ($rol !== "admin") {
            $sqlPagos .= " WHERE a.documento = :documento ";
        }

        $sqlPagos .= " ORDER BY pg.fechaPago DESC, pg.idPago DESC";

        $stmtPg = $conexion->prepare($sqlPagos);
        $stmtPg->execute($params);
        $pagos = $stmtPg->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "rol" => $rol,
            "prestamos" => $prestamos,
            "pagos" => $pagos
        ]);
        exit;
    }

    if ($metodo === "POST") {
        if ($rol === "admin") {
            http_response_code(403);
            throw new Exception("Solo el asociado puede registrar pagos");
        }

        $datos = leerJson();

        $idPrestamo = filter_var($datos["idPrestamo"] ?? null, FILTER_VALIDATE_INT);
        $valorCuota = filter_var($datos["valorCuota"] ?? null, FILTER_VALIDATE_FLOAT);
        $fechaPago = trim((string)($datos["fechaPago"] ?? ""));

        if (!$idPrestamo || $idPrestamo <= 0) {
            http_response_code(400);
            throw new Exception("Selecciona un préstamo válido");
        }
        if ($valorCuota === false || $valorCuota <= 0) {
            http_response_code(400);
            throw new Exception("El valor de la cuota debe ser mayor a 0");
        }
        $fechaObj = DateTime::createFromFormat("Y-m-d", $fechaPago);
        if (!$fechaObj || $fechaObj->format("Y-m-d") !== $fechaPago) {
            http_response_code(400);
            throw new Exception("Ingresa una fecha de pago válida");
        }

        $conexion->beginTransaction();

        $sqlCheck = "
            SELECT p.idPrestamo, p.valorPrestamo AS montoPrestamo,
                   p.tasaInteres, p.numeroCuotas, p.estado, a.documento
            FROM prestamos p
            INNER JOIN asociados a ON a.idAsociado = p.idAsociado
            WHERE p.idPrestamo = :idPrestamo
            FOR UPDATE
        ";
        $stmtCheck = $conexion->prepare($sqlCheck);
        $stmtCheck->execute([":idPrestamo" => $idPrestamo]);
        $prestamo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$prestamo) {
            $conexion->rollBack();
            http_response_code(404);
            throw new Exception("El préstamo no existe");
        }

        if ($prestamo["documento"] !== $documentoSesion) {
            $conexion->rollBack();
            http_response_code(403);
            throw new Exception("No puedes registrar pagos en un préstamo que no es tuyo");
        }

        if ($prestamo["estado"] !== "Aprobado") {
            $conexion->rollBack();
            http_response_code(409);
            throw new Exception("Solo puedes pagar préstamos aprobados");
        }

        $stmtCuotas = $conexion->prepare(
            "SELECT COUNT(*) FROM pagos_cuotas WHERE idPrestamo = :idPrestamo"
        );
        $stmtCuotas->execute([":idPrestamo" => $idPrestamo]);
        $cuotasPagadas = (int)$stmtCuotas->fetchColumn();
        $numeroCuotas = (int)$prestamo["numeroCuotas"];
        if ($cuotasPagadas >= $numeroCuotas) {
            $conexion->rollBack();
            http_response_code(409);
            throw new Exception("Este préstamo ya tiene todas sus cuotas pagadas");
        }
        $cuotaEsperada = Prestamos::calcularCuota(
            $prestamo["montoPrestamo"],
            $prestamo["tasaInteres"],
            $numeroCuotas,
            $cuotasPagadas + 1
        );
        if (abs(round($valorCuota, 2) - $cuotaEsperada) > 0.001) {
            $conexion->rollBack();
            http_response_code(400);
            throw new Exception(
                "El valor de la cuota debe ser $" . number_format($cuotaEsperada, 2, ",", ".")
            );
        }

        $stmtIns = $conexion->prepare("
            INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
            VALUES (:valorCuota, :fechaPago, :idPrestamo)
        ");
        $stmtIns->execute([
            ":valorCuota" => $cuotaEsperada,
            ":fechaPago" => $fechaPago,
            ":idPrestamo" => $idPrestamo
        ]);

        if ($cuotasPagadas + 1 === $numeroCuotas) {
            $stmtEstado = $conexion->prepare(
                "UPDATE prestamos SET estado = 'Pagado' WHERE idPrestamo = :idPrestamo"
            );
            $stmtEstado->execute([":idPrestamo" => $idPrestamo]);
        }

        $conexion->commit();

        echo json_encode([
            "success" => true,
            "message" => "Pago registrado exitosamente"
        ]);
        exit;
    }

    if ($metodo === "DELETE") {
        if ($rol !== "admin") {
            http_response_code(403);
            throw new Exception("Solo el administrador puede eliminar pagos");
        }

        $datos = leerJson();
        $idPago = filter_var($datos["idPago"] ?? null, FILTER_VALIDATE_INT);

        if (!$idPago || $idPago <= 0) {
            http_response_code(400);
            throw new Exception("ID de pago inválido");
        }

        $stmtDel = $conexion->prepare("DELETE FROM pagos_cuotas WHERE idPago = :idPago");
        $stmtDel->execute([":idPago" => $idPago]);

        if ($stmtDel->rowCount() === 0) {
            http_response_code(404);
            throw new Exception("No se encontró el pago");
        }

        echo json_encode([
            "success" => true,
            "message" => "Pago eliminado"
        ]);
    }
} catch (PDOException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
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