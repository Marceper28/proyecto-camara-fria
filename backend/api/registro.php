<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

$nombre   = trim($datos['nombre'] ?? '');
$correo   = trim($datos['correo'] ?? '');
$password = $datos['password'] ?? '';

if ($nombre === '' || $correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "mensaje" => "Todos los campos son obligatorios"]);
    exit;
}

try {
    // Verificar si el correo ya existe
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["status" => "error", "mensaje" => "Este correo ya está registrado"]);
        exit;
    }

    // Encriptar contraseña y guardar usuario
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare("INSERT INTO usuarios (nombre, correo, password_hash) VALUES (?, ?, ?)");
    $insert->execute([$nombre, $correo, $hash]);

    http_response_code(201);
    echo json_encode([
        "status" => "ok",
        "mensaje" => "Usuario registrado correctamente",
        "id_usuario" => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error al registrar usuario"]);
}