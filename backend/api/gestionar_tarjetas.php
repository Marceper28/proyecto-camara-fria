<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id_tarjeta, uid_tarjeta, nombre_empleado, activa FROM tarjetas_rfid ORDER BY id_tarjeta DESC");
    echo json_encode(["status" => "ok", "tarjetas" => $stmt->fetchAll()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents("php://input"), true);
    $accion = $datos['accion'] ?? 'crear';

    try {
        if ($accion === 'crear') {
            $uid = strtoupper(trim($datos['uid_tarjeta'] ?? ''));
            $empleado = trim($datos['nombre_empleado'] ?? '');
            if ($uid === '' || $empleado === '') {
                http_response_code(400);
                echo json_encode(["status" => "error", "mensaje" => "UID y nombre son obligatorios"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO tarjetas_rfid (uid_tarjeta, nombre_empleado) VALUES (?, ?)");
            $stmt->execute([$uid, $empleado]);
            echo json_encode(["status" => "ok", "mensaje" => "Tarjeta registrada"]);
        } elseif ($accion === 'toggle') {
            $id_tarjeta = intval($datos['id_tarjeta'] ?? 0);
            $activa = $datos['activa'] ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE tarjetas_rfid SET activa = ? WHERE id_tarjeta = ?");
            $stmt->execute([$activa, $id_tarjeta]);
            echo json_encode(["status" => "ok", "mensaje" => "Estado de tarjeta actualizado"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "mensaje" => "Error al procesar tarjeta (verifica que el UID no esté duplicado)"]);
    }
}