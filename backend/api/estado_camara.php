<?php
require_once __DIR__ . '/../conexion.php';

$id_dispositivo = intval($_GET['id_dispositivo'] ?? 1);

try {
    // 1. Datos del dispositivo y cálculo de estado Online/Offline (RF10: desconectado si > 120 seg sin datos)
    $stmt = $pdo->prepare("
        SELECT id_dispositivo, nombre, ubicacion, temp_max_alerta, alarma_silenciada, ultima_conexion,
               TIMESTAMPDIFF(SECOND, ultima_conexion, NOW()) AS segundos_sin_conexion
        FROM dispositivos
        WHERE id_dispositivo = ?
    ");
    $stmt->execute([$id_dispositivo]);
    $disp = $stmt->fetch();

    if (!$disp) {
        http_response_code(404);
        echo json_encode(["status" => "error", "mensaje" => "Dispositivo no encontrado"]);
        exit;
    }

    $en_linea = ($disp['segundos_sin_conexion'] !== null && intval($disp['segundos_sin_conexion']) <= 120);

    // 2. Últimas 20 lecturas para mostrar temperatura actual e historial/gráfica (RF05, RF06)
    $stmtLecturas = $pdo->prepare("
        SELECT id_lectura, temperatura, en_alerta, fecha_hora
        FROM lecturas_temperatura
        WHERE id_dispositivo = ?
        ORDER BY id_lectura DESC
        LIMIT 20
    ");
    $stmtLecturas->execute([$id_dispositivo]);
    $lecturas = $stmtLecturas->fetchAll();

    // 3. Últimos 15 accesos de personal con RFID
    $stmtAccesos = $pdo->prepare("
        SELECT r.id_registro, r.uid_leido, r.estado, r.fecha_hora, t.nombre_empleado
        FROM registros_acceso r
        LEFT JOIN tarjetas_rfid t ON r.id_tarjeta = t.id_tarjeta
        WHERE r.id_dispositivo = ?
        ORDER BY r.id_registro DESC
        LIMIT 15
    ");
    $stmtAccesos->execute([$id_dispositivo]);
    $accesos = $stmtAccesos->fetchAll();

    echo json_encode([
        "status" => "ok",
        "dispositivo" => [
            "id_dispositivo" => $disp['id_dispositivo'],
            "nombre" => $disp['nombre'],
            "ubicacion" => $disp['ubicacion'],
            "temp_max_alerta" => floatval($disp['temp_max_alerta']),
            "alarma_silenciada" => (bool)$disp['alarma_silenciada'],
            "en_linea" => $en_linea,
            "ultima_conexion" => $disp['ultima_conexion']
        ],
        "temperatura_actual" => count($lecturas) > 0 ? $lecturas[0] : null,
        "historial_temperaturas" => $lecturas,
        "historial_accesos" => $accesos
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error al consultar estado"]);
}