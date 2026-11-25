<?php
require_once __DIR__ . '/../conexion.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_temperatura_camara.csv"');

$salida = fopen('php://output', 'w');
fputcsv($salida, ['ID Lectura', 'Dispositivo', 'Temperatura (C)', 'En Alerta', 'Fecha y Hora']);

$stmt = $pdo->query("
    SELECT l.id_lectura, d.nombre, l.temperatura, 
           IF(l.en_alerta = 1, 'SI', 'NO') AS alerta, l.fecha_hora
    FROM lecturas_temperatura l
    INNER JOIN dispositivos d ON l.id_dispositivo = d.id_dispositivo
    ORDER BY l.id_lectura DESC
");

while ($fila = $stmt->fetch()) {
    fputcsv($salida, $fila);
}
fclose($salida);