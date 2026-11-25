<?php
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

$correo   = trim($datos['correo'] ?? '');
$password = $datos['password'] ?? '';

if ($correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "mensaje" => "Ingresa correo y contraseña"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id_usuario, nombre, correo, password_hash, rol FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "mensaje" => "Correo o contraseña incorrectos"]);
        exit;
    }

    echo json_encode([
        "status" => "ok",
        "mensaje" => "Inicio de sesión exitoso",
        "usuario" => [
            "id_usuario" => $usuario['id_usuario'],
            "nombre" => $usuario['nombre'],
            "correo" => $usuario['correo'],
            "rol" => $usuario['rol']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "mensaje" => "Error en el servidor"]);
}