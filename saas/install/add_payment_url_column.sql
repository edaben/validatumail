-- Agregar columna para URL de pago personalizada
-- Ejecutar este script para permitir configurar URL de pago desde admin

USE geocontrol_saas;

-- Agregar columna para URL de pago personalizada
ALTER TABLE clients 
ADD COLUMN payment_url VARCHAR(255) DEFAULT NULL COMMENT 'URL personalizada para dirigir a clientes para pago';

-- Establecer una URL por defecto para todos los clientes
UPDATE clients SET 
    payment_url = 'https://tu-sitio-de-pagos.com/upgrade' 
WHERE payment_url IS NULL;

-- Verificar que la columna se agregó correctamente
SELECT 
    id, 
    name, 
    email, 
    plan, 
    payment_url
FROM clients 
LIMIT 5;

-- Mostrar estructura actualizada
DESCRIBE clients;