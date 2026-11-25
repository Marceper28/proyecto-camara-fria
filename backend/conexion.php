<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Si es una petición preflight OPTIONS, respondemos 200 de inmediato
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Falta el archivo .env en el servidor"]);
    exit;
}

$env = parse_ini_file($envPath);

try {
    $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión a la base de datos"]);
    exit;
}