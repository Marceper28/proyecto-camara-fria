<?php
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$id_dispositivo = intval($_GET['id_dispositivo'] ?? 1);

try {
    // 1. Datos del dispositivo (límite de alerta y estado de alarma)
    $stmtDisp = $pdo->prepare("SELECT temp_max_alerta, alarma_silenciada, ultima_conexion FROM dispositivos WHERE id_dispositivo = ?");
    $stmtDisp->execute([$id_dispositivo]);
    $dispositivo = $stmtDisp->fetch(PDO::FETCH_ASSOC);

    // 2. Últimas 10 lecturas de temperatura (ORDER BY 1 DESC ordena por la llave primaria sin importar su nombre)
    $stmtTemp = $pdo->prepare("SELECT * FROM lecturas_temperatura WHERE id_dispositivo = ? ORDER BY 1 DESC LIMIT 10");
    $stmtTemp->execute([$id_dispositivo]);
    $rawTemp = $stmtTemp->fetchAll(PDO::FETCH_ASSOC);

    $lecturas = [];
    foreach ($rawTemp as $row) {
        $lecturas[] = [
            "temperatura" => $row['temperatura'],
            "en_alerta"   => $row['en_alerta'] ?? 0,
            "fecha_hora"  => $row['fecha_hora'] ?? $row['fecha'] ?? $row['created_at'] ?? ''
        ];
    }

    // 3. Últimos 10 registros de acceso RFID (protegido por si la tabla tiene otro nombre)
    $accesos = [];
    try {
        $stmtAcc = $pdo->query("SELECT * FROM registros_acceso ORDER BY 1 DESC LIMIT 10");
        if ($stmtAcc) {
            foreach ($stmtAcc->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $accesos[] = [
                    "uid_tarjeta" => $row['uid_tarjeta'] ?? $row['uid'] ?? '',
                    "permitido"   => $row['permitido'] ?? $row['acceso_permitido'] ?? 0,
                    "fecha_hora"  => $row['fecha_hora'] ?? $row['fecha'] ?? $row['created_at'] ?? ''
                ];
            }
        }
    } catch (PDOException $e) {
        // Si la tabla de accesos se llama distinto, no rompe el JSON de temperatura
    }

    echo json_encode([
        "status"      => "ok",
        "dispositivo" => $dispositivo,
        "lecturas"    => $lecturas,
        "accesos"     => $accesos
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => $e->getMessage()]);
}