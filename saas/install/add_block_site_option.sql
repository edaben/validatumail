-- Script para agregar opción de bloqueo completo del sitio
-- Actualiza la columna access_denied_action para incluir 'block_site'

USE geocontrol_saas;

-- Agregar nueva opción 'block_site' al ENUM
ALTER TABLE clients 
MODIFY access_denied_action ENUM('block_forms', 'block_site', 'redirect') DEFAULT 'block_forms';

-- Mostrar resultado
SELECT 'Opción block_site agregada exitosamente' as status;