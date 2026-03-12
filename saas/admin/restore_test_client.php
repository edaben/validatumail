<?php
/**
 * Script para restaurar configuración original del cliente de prueba
 */

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = SaasDatabase::getInstance();
    
    // Restaurar configuración original de Eduardo
    $sql = "UPDATE clients SET 
            countries_allowed = 'CO,EC,PE',  -- Restaurar Ecuador
            access_denied_action = 'block_forms',
            allow_vpn = 1  -- Permitir VPN nuevamente
            WHERE email = 'eduardodavila9@gmail.com'";
    
    $db->query($sql);
    
    echo "<h1>✅ Configuración restaurada</h1>\n";
    echo "<p><strong>Configuración original restaurada:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>🌍 <strong>Países permitidos:</strong> CO,EC,PE (Colombia, Ecuador, Perú)</li>\n";
    echo "<li>🚫 <strong>Acción denegado:</strong> block_forms</li>\n";
    echo "<li>🔒 <strong>VPN:</strong> Permitido</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Resultado:</strong> Desde Ecuador = Acceso PERMITIDO = Funcionamiento normal</p>\n";
    
    // Verificar configuración restaurada
    $client = $db->fetchOne(
        "SELECT countries_allowed, access_denied_action, allow_vpn FROM clients WHERE email = 'eduardodavila9@gmail.com'"
    );
    
    echo "<h2>📋 Configuración actual:</h2>\n";
    echo "<pre>" . print_r($client, true) . "</pre>\n";
    
} catch (Exception $e) {
    echo "<h1 style='color:red;'>❌ Error</h1>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>