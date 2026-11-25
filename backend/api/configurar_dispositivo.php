<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);
$id_dispositivo = intval($datos['id_dispositivo'] ?? 1);

try {
    if (isset($datos['temp_max_alerta'])) {
        $limite = floatval($datos['temp_max_alerta']);
        $stmt = $pdo->prepare("UPDATE dispositivos SET temp_max_alerta = ? WHERE id_dispositivo = ?");
        $stmt->execute([$limite, $id_dispositivo]);
    }

    if (isset($datos['alarma_silenciada'])) {
        $silenciar = $datos['alarma_silenciada'] ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE dispositivos SET alarma_silenciada = ? WHERE id_dispositivo = ?");
        $stmt->execute([$silenciar, $id_dispositivo]);
    }

    echo json_encode(["status" => "ok", "mensaje" => "Configuración actualizada"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error al actualizar dispositivo"]);
}