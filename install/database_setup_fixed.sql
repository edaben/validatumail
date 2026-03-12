-- Script de Instalación de Base de Datos SaaS CORREGIDO
-- Este script crea todas las tablas necesarias para el sistema SaaS
-- Ejecutar una sola vez durante la instalación

CREATE DATABASE IF NOT EXISTS geocontrol_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE geocontrol_saas;

-- Tabla de clientes del SaaS
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(2),
    company VARCHAR(100),
    website VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    client_id VARCHAR(32) UNIQUE NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'cancelled') DEFAULT 'pending',
    plan ENUM('free', 'basic', 'premium', 'enterprise') DEFAULT 'free',
    countries_allowed TEXT,
    countries_denied TEXT,
    access_control_mode ENUM('allowed', 'denied') DEFAULT 'allowed',
    allow_vpn BOOLEAN DEFAULT FALSE,
    allow_tor BOOLEAN DEFAULT FALSE,
    allow_proxy BOOLEAN DEFAULT FALSE,
    allow_hosting BOOLEAN DEFAULT FALSE,
    access_denied_action ENUM('block_forms', 'redirect') DEFAULT 'block_forms',
    redirect_url VARCHAR(255),
    monthly_usage INT DEFAULT 0,
    monthly_limit INT DEFAULT 1000,
    email_verified_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_api_key (api_key),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_plan (plan)
) ENGINE=InnoDB;

-- Tabla de sitios web por cliente
CREATE TABLE IF NOT EXISTS client_websites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(64),
    verification_method ENUM('dns', 'file', 'meta') DEFAULT 'file',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_domain (client_id, domain),
    INDEX idx_domain (domain),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB;

-- Tabla de leads/prospectos
CREATE TABLE IF NOT EXISTS leads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(2),
    company VARCHAR(100),
    website VARCHAR(255),
    message TEXT,
    source VARCHAR(50) DEFAULT 'website',
    status ENUM('new', 'contacted', 'qualified', 'converted', 'rejected') DEFAULT 'new',
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    contacted_at TIMESTAMP NULL,
    converted_at TIMESTAMP NULL,
    notes TEXT,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_source (source),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Tabla de solicitudes de API (para estadísticas y límites)
CREATE TABLE IF NOT EXISTS api_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    domain VARCHAR(255),
    ip_address VARCHAR(45),
    country_code VARCHAR(2),
    country_name VARCHAR(100),
    access_result ENUM('allowed', 'denied') NOT NULL,
    denial_reason VARCHAR(100),
    user_agent TEXT,
    referrer VARCHAR(255),
    response_time_ms INT DEFAULT 0,
    request_type ENUM('api', 'script', 'log') DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_date (client_id, created_at),
    INDEX idx_api_key (api_key),
    INDEX idx_domain (domain),
    INDEX idx_country (country_code),
    INDEX idx_result (access_result)
) ENGINE=InnoDB;

-- Tabla de tokens de verificación
CREATE TABLE IF NOT EXISTS verification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    type ENUM('email_verification', 'password_reset', 'domain_verification') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_type (type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Tabla de configuraciones del sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Tabla de logs del sistema
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NULL,
    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_level (level),
    INDEX idx_client (client_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Tabla de logs de acceso (para el sistema JavaScript)
CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    client_uuid VARCHAR(32) NOT NULL,
    access_result ENUM('granted', 'denied', 'error') NOT NULL,
    country_code VARCHAR(2),
    country_name VARCHAR(100),
    city VARCHAR(100),
    ip_address VARCHAR(45),
    is_vpn TINYINT(1) DEFAULT 0,
    is_tor TINYINT(1) DEFAULT 0,
    is_proxy TINYINT(1) DEFAULT 0,
    user_agent TEXT,
    page_url TEXT,
    referrer TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_client_uuid (client_uuid),
    INDEX idx_access_result (access_result),
    INDEX idx_country_code (country_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Tabla de estadísticas diarias
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    date DATE NOT NULL,
    total_requests INT DEFAULT 0,
    granted_requests INT DEFAULT 0,
    denied_requests INT DEFAULT 0,
    unique_countries INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_date (client_id, date),
    INDEX idx_client_id (client_id),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Tabla de estadísticas por país
CREATE TABLE IF NOT EXISTS country_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    date DATE NOT NULL,
    country_code VARCHAR(2) NOT NULL,
    requests INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_date_country (client_id, date, country_code),
    INDEX idx_client_id (client_id),
    INDEX idx_date (date),
    INDEX idx_country_code (country_code)
) ENGINE=InnoDB;

-- Insertar configuraciones por defecto
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'GeoControl SaaS', 'string', 'Nombre del sitio web'),
('site_email', 'admin@tuservidor.com', 'string', 'Email de contacto principal'),
('default_plan', 'free', 'string', 'Plan por defecto para nuevos usuarios'),
('free_plan_limit', '1000', 'integer', 'Límite mensual del plan gratuito'),
('basic_plan_limit', '10000', 'integer', 'Límite mensual del plan básico'),
('premium_plan_limit', '100000', 'integer', 'Límite mensual del plan premium'),
('enterprise_plan_limit', '-1', 'integer', 'Límite mensual del plan enterprise (-1 = ilimitado)'),
('email_verification_required', 'true', 'boolean', 'Requiere verificación de email'),
('domain_verification_required', 'false', 'boolean', 'Requiere verificación de dominio'),
('registration_enabled', 'true', 'boolean', 'Permitir nuevos registros'),
('maintenance_mode', 'false', 'boolean', 'Modo de mantenimiento'),
('api_rate_limit', '1000', 'integer', 'Límite de requests por hora por cliente')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Crear usuario administrador por defecto
INSERT INTO clients (
    name, 
    email, 
    password_hash, 
    api_key, 
    client_id,
    status, 
    plan, 
    monthly_limit,
    countries_allowed,
    access_control_mode,
    email_verified_at
) VALUES (
    'Administrador SaaS',
    'admin@tuservidor.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password
    'geo_admin_master_key_123456789',
    MD5(CONCAT('admin@tuservidor.com', UNIX_TIMESTAMP())),
    'active',
    'enterprise',
    -1,
    'AR,BO,CL,CO,CR,CU,DO,EC,ES,GQ,GT,HN,MX,NI,PA,PE,PY,SV,UY,VE,US,CA',
    'allowed',
    NOW()
) ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Crear índices adicionales para performance (CORREGIDOS)
CREATE INDEX idx_clients_created_plan ON clients(created_at, plan);
CREATE INDEX idx_leads_conversion ON leads(status, created_at);

-- Crear índices para fechas (compatible con todas las versiones de MySQL)
CREATE INDEX idx_api_requests_client_month ON api_requests(client_id, created_at);
CREATE INDEX idx_access_logs_client_month ON access_logs(client_id, created_at);

-- Trigger para resetear usage mensual (se ejecutará el primer día de cada mes)
DELIMITER //
CREATE TRIGGER reset_monthly_usage 
BEFORE UPDATE ON clients
FOR EACH ROW
BEGIN
    -- Si es un nuevo mes, resetear el contador
    IF MONTH(NOW()) != MONTH(OLD.updated_at) OR YEAR(NOW()) != YEAR(OLD.updated_at) THEN
        SET NEW.monthly_usage = 0;
    END IF;
END//
DELIMITER ;

-- Vista para estadísticas rápidas
CREATE VIEW client_stats AS
SELECT 
    c.id,
    c.name,
    c.email,
    c.plan,
    c.monthly_usage,
    c.monthly_limit,
    CASE 
        WHEN c.monthly_limit > 0 THEN ROUND((c.monthly_usage / c.monthly_limit) * 100, 2)
        ELSE 0
    END as usage_percentage,
    COUNT(DISTINCT w.id) as websites_count,
    COUNT(DISTINCT ar.id) as total_requests,
    c.created_at,
    c.last_login
FROM clients c
LEFT JOIN client_websites w ON c.id = w.client_id AND w.is_active = TRUE
LEFT JOIN api_requests ar ON c.id = ar.client_id 
    AND ar.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
GROUP BY c.id;

-- Procedimiento para limpiar datos antiguos
DELIMITER //
CREATE PROCEDURE CleanOldData()
BEGIN
    -- Limpiar logs más antiguos de 90 días
    DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Limpiar tokens expirados
    DELETE FROM verification_tokens WHERE expires_at < NOW();
    
    -- Limpiar requests de API más antiguos de 1 año (mantener solo para estadísticas)
    DELETE FROM api_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
    
    -- Limpiar logs de acceso más antiguos de 6 meses
    DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    -- Limpiar leads rechazados más antiguos de 6 meses
    DELETE FROM leads WHERE status = 'rejected' AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END//
DELIMITER ;

COMMIT;

-- Mostrar resumen de la instalación
SELECT 'Base de datos geocontrol_saas creada exitosamente' as status;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'geocontrol_saas';
SELECT 'Usuario administrador creado con email: admin@tuservidor.com' as admin_info;
SELECT 'Contraseña por defecto: password (cambiar inmediatamente)' as security_note;