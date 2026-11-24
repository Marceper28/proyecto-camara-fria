CREATE DATABASE IF NOT EXISTS camara_fria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE camara_fria_db;

-- Usuarios del sistema (Login y Registro)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'operador') DEFAULT 'operador',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dispositivos IoT, límite de temperatura, control y estado de conexión
CREATE TABLE dispositivos (
    id_dispositivo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(100) NOT NULL,
    temp_max_alerta DECIMAL(5,2) DEFAULT -10.00,
    alarma_silenciada TINYINT(1) DEFAULT 0,
    ultima_conexion TIMESTAMP NULL DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1
);

-- Historial de temperatura y alertas
CREATE TABLE lecturas_temperatura (
    id_lectura INT AUTO_INCREMENT PRIMARY KEY,
    id_dispositivo INT NOT NULL,
    temperatura DECIMAL(5,2) NOT NULL,
    en_alerta TINYINT(1) DEFAULT 0,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivos(id_dispositivo) ON DELETE CASCADE
);

-- Tarjetas RFID y bitácora de entradas
CREATE TABLE tarjetas_rfid (
    id_tarjeta INT AUTO_INCREMENT PRIMARY KEY,
    uid_tarjeta VARCHAR(32) NOT NULL UNIQUE,
    nombre_empleado VARCHAR(100) NOT NULL,
    activa TINYINT(1) DEFAULT 1
);

CREATE TABLE registros_acceso (
    id_registro INT AUTO_INCREMENT PRIMARY KEY,
    id_dispositivo INT NOT NULL,
    uid_leido VARCHAR(32) NOT NULL,
    id_tarjeta INT NULL,
    estado ENUM('PERMITIDO', 'DENEGADO') NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivos(id_dispositivo) ON DELETE CASCADE,
    FOREIGN KEY (id_tarjeta) REFERENCES tarjetas_rfid(id_tarjeta) ON DELETE SET NULL
);

-- Dispositivo inicial de prueba
INSERT INTO dispositivos (nombre, ubicacion, temp_max_alerta) 
VALUES ('ESP32-Congelador-01', 'Cámara Principal Pescadería', -10.00);