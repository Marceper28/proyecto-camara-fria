<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

$id_dispositivo = intval($datos['id_dispositivo'] ?? 0);
$temperatura    = isset($datos['temperatura']) ? floatval($datos['temperatura']) : null;

if ($id_dispositivo <= 0 || $temperatura === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "mensaje" => "Faltan datos de dispositivo o temperatura"]);
    exit;
}

try {
    // 1. Consultar configuración del dispositivo (límite y si la alarma fue silenciada desde Android)
    $stmt = $pdo->prepare("SELECT temp_max_alerta, alarma_silenciada FROM dispositivos WHERE id_dispositivo = ?");
    $stmt->execute([$id_dispositivo]);
    $disp = $stmt->fetch();

    if (!$disp) {
        http_response_code(404);
        echo json_encode(["status" => "error", "mensaje" => "Dispositivo no encontrado"]);
        exit;
    }

    // 2. Verificar si hay alerta de temperatura (RF08)
    $en_alerta = ($temperatura > floatval($disp['temp_max_alerta'])) ? 1 : 0;

    // 3. Guardar lectura en historial (RF04)
    $insert = $pdo->prepare("INSERT INTO lecturas_temperatura (id_dispositivo, temperatura, en_alerta) VALUES (?, ?, ?)");
    $insert->execute([$id_dispositivo, $temperatura, $en_alerta]);

    // 4. Actualizar última conexión para estado Online/Offline (RF10)
    $update = $pdo->prepare("UPDATE dispositivos SET ultima_conexion = NOW() WHERE id_dispositivo = ?");
    $update->execute([$id_dispositivo]);

    // 5. Decidir si el buzzer físico debe sonar (alerta activa Y no silenciada manualmente)
    $sonar_buzzer = ($en_alerta === 1 && intval($disp['alarma_silenciada']) === 0);

    echo json_encode([
        "status" => "ok",
        "en_alerta" => (bool)$en_alerta,
        "sonar_buzzer" => $sonar_buzzer,
        "temp_max_alerta" => floatval($disp['temp_max_alerta'])
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error al guardar lectura"]);
}