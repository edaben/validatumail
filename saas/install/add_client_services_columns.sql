-- Agregar columnas para controlar servicios de cada cliente
-- Ejecutar este script para actualizar la tabla clients

USE geocontrol_saas;

-- Agregar columnas para controlar servicios individuales
ALTER TABLE clients 
ADD COLUMN email_verification_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar servicio de verificación de email',
ADD COLUMN geo_blocking_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar servicio de bloqueo geográfico';

-- Actualizar todos los clientes existentes para tener ambos servicios habilitados
UPDATE clients SET 
    email_verification_enabled = 1,
    geo_blocking_enabled = 1 
WHERE email_verification_enabled IS NULL OR geo_blocking_enabled IS NULL;

-- Verificar que las columnas se agregaron correctamente
SELECT 
    id, 
    name, 
    email, 
    plan, 
    email_verification_enabled, 
    geo_blocking_enabled,
    status
FROM clients 
LIMIT 10;

-- Mostrar estructura actualizada de la tabla
DESCRIBE clients;