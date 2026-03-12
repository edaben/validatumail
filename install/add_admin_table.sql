-- Crear tabla de administradores
CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar administrador por defecto
INSERT INTO admin_users (username, email, password_hash, full_name) 
VALUES ('admin', 'admin@geocontrol.com', '$2y$12$dummy.hash.placeholder', 'Administrador Principal')
ON DUPLICATE KEY UPDATE username = username;

-- Actualizar con el hash real de admin2024!
UPDATE admin_users 
SET password_hash = '$2y$12$V5q8xJL6.fXZKoU8eoLjj.P8J.H3.WJ6XQkr8JgXFD2R.pM3kL/am'
WHERE username = 'admin';