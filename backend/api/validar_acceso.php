<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);
$id_dispositivo = intval($datos['id_dispositivo'] ?? 1);
$uid_leido      = strtoupper(trim($datos['uid'] ?? ''));

if ($uid_leido === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "mensaje" => "UID de tarjeta vacío"]);
    exit;
}

try {
    // Buscar si la tarjeta existe y está activa
    $stmt = $pdo->prepare("SELECT id_tarjeta, nombre_empleado, activa FROM tarjetas_rfid WHERE uid_tarjeta = ?");
    $stmt->execute([$uid_leido]);
    $tarjeta = $stmt->fetch();

    $permitido = ($tarjeta && intval($tarjeta['activa']) === 1);
    $estado = $permitido ? 'PERMITIDO' : 'DENEGADO';
    $id_tarjeta = $tarjeta ? $tarjeta['id_tarjeta'] : null;

    // Guardar en bitácora de accesos
    $insert = $pdo->prepare("INSERT INTO registros_acceso (id_dispositivo, uid_leido, id_tarjeta, estado) VALUES (?, ?, ?, ?)");
    $insert->execute([$id_dispositivo, $uid_leido, $id_tarjeta, $estado]);

    // Actualizar última conexión del ESP32
    $pdo->prepare("UPDATE dispositivos SET ultima_conexion = NOW() WHERE id_dispositivo = ?")->execute([$id_dispositivo]);

    echo json_encode([
        "status" => "ok",
        "permitido" => $permitido,
        "empleado" => $permitido ? $tarjeta['nombre_empleado'] : "Desconocido"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error al validar tarjeta"]);
}