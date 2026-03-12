<?php
/**
 * Script temporal para actualizar configuración de cliente de prueba
 * Para testing del bloqueo de formularios
 */

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = SaasDatabase::getInstance();
    
    // Actualizar cliente Eduardo para denegar acceso desde Ecuador (testing)
    $sql = "UPDATE clients SET 
            countries_allowed = 'CO,PE',  -- Quitar EC (Ecuador) 
            access_denied_action = 'block_forms',
            allow_vpn = 0  -- Deshabilitar VPN también para testing
            WHERE email = 'eduardodavila9@gmail.com'";
    
    $db->query($sql);
    
    echo "<h1>✅ Cliente de prueba actualizado para testing</h1>\n";
    echo "<p><strong>Cambios aplicados:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>🌍 <strong>Países permitidos:</strong> CO,PE (Colombia, Perú) - Ecuador REMOVIDO</li>\n";
    echo "<li>🚫 <strong>Acción denegado:</strong> block_forms</li>\n";
    echo "<li>🔒 <strong>VPN:</strong> NO permitido</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Resultado esperado:</strong> Desde Ecuador = Acceso DENEGADO = Solo formularios bloqueados</p>\n";
    
    // Verificar configuración actualizada
    $client = $db->fetchOne(
        "SELECT countries_allowed, access_denied_action, allow_vpn FROM clients WHERE email = 'eduardodavila9@gmail.com'"
    );
    
    echo "<h2>📋 Configuración actual:</h2>\n";
    echo "<pre>" . print_r($client, true) . "</pre>\n";
    
    echo "<h2>🧪 URLs de testing:</h2>\n";
    echo "<ul>\n";
    echo "<li><a href='../test/test_syntax.html'>Test de Sintaxis</a></li>\n";
    echo "<li><a href='../test/final_test.html'>Test Final</a></li>\n";
    echo "<li><a href='verify_clients.php'>Verificar Clientes</a></li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h1 style='color:red;'>❌ Error</h1>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>