-- Run inside your created database (e.g. qrcore_db or if0_XXXXXXXX_qrcore_db)

CREATE TABLE IF NOT EXISTS USUARIO (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    intentos_fallidos TINYINT UNSIGNED DEFAULT 0,
    bloqueado_hasta DATETIME DEFAULT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CODIGO_QR (
    id_qr INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    alias_identificador VARCHAR(100) NOT NULL,
    url_destino TEXT NOT NULL,
    ruta_imagen VARCHAR(255) DEFAULT NULL,
    estado_activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ESTADISTICA_ESCANEO (
    id_escaneo INT AUTO_INCREMENT PRIMARY KEY,
    id_qr INT NOT NULL,
    direccion_ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    fecha_acceso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qr_fecha (id_qr, fecha_acceso),
    FOREIGN KEY (id_qr) REFERENCES CODIGO_QR(id_qr) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO USUARIO (id_usuario, nombre, email, password_hash) VALUES 
(1, 'Usuario YouTube', 'usuario@youtube.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO CODIGO_QR (id_qr, id_usuario, alias_identificador, url_destino, ruta_imagen, estado_activo) VALUES 
(1, 1, 'youtube', 'https://www.youtube.com', 'qrs/seed_qr_1.png', 1),
(2, 1, 'youtube music', 'https://music.youtube.com', 'qrs/seed_qr_2.png', 1);

INSERT INTO ESTADISTICA_ESCANEO (id_qr, direccion_ip, user_agent) VALUES 
(1, '192.168.1.10', 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0'),
(1, '192.168.1.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1');
