<?php
/**
 * Script para agregar opción de bloqueo completo del sitio
 * Actualiza la base de datos para incluir 'block_site' como opción
 */

require_once '../config/config.php';
require_once '../config/database.php';

echo "<h1>🔧 ACTUALIZACIÓN DE BASE DE DATOS</h1>\n";
echo "<style>body{font-family:Arial;} .success{background:#d4edda;padding:15px;border-radius:5px;color:#155724;margin:10px 0;} .error{background:#f8d7da;padding:15px;border-radius:5px;color:#721c24;margin:10px 0;} .info{background:#cce7ff;padding:15px;border-radius:5px;color:#004085;margin:10px 0;}</style>\n";

try {
    echo "<h2>📋 Agregando nueva opción de bloqueo</h2>\n";
    
    $db = SaasDatabase::getInstance();
    
    echo "<div class='info'>🔍 Verificando estructura actual de la tabla...</div>\n";
    
    // Verificar estructura actual
    $current_structure = $db->fetchAll("DESCRIBE clients");
    
    $access_denied_action_found = false;
    foreach ($current_structure as $column) {
        if ($column['Field'] === 'access_denied_action') {
            $access_denied_action_found = true;
            echo "<div class='info'>✅ Columna access_denied_action encontrada: " . $column['Type'] . "</div>\n";
            break;
        }
    }
    
    if (!$access_denied_action_found) {
        throw new Exception("Columna access_denied_action no encontrada en la tabla clients");
    }
    
    echo "<div class='info'>🔄 Actualizando columna para incluir opción 'block_site'...</div>\n";
    
    // Actualizar la columna para incluir la nueva opción
    $sql = "ALTER TABLE clients 
            MODIFY access_denied_action ENUM('block_forms', 'block_site', 'redirect') DEFAULT 'block_forms'";
    
    $db->query($sql);
    
    echo "<div class='success'>✅ Columna actualizada exitosamente</div>\n";
    
    // Verificar la actualización
    $updated_structure = $db->fetchAll("DESCRIBE clients");
    foreach ($updated_structure as $column) {
        if ($column['Field'] === 'access_denied_action') {
            echo "<div class='success'>🎯 Nueva estructura: " . $column['Type'] . "</div>\n";
            break;
        }
    }
    
    echo "<h2>📊 Verificación de clientes existentes</h2>\n";
    
    // Verificar clientes existentes
    $clients = $db->fetchAll("SELECT id, name, access_denied_action FROM clients LIMIT 5");
    
    echo "<div class='info'>📋 Clientes actuales:</div>\n";
    echo "<table style='border-collapse:collapse;width:100%;margin:10px 0;'>\n";
    echo "<tr style='background:#f2f2f2;'><th style='border:1px solid #ddd;padding:8px;'>ID</th><th style='border:1px solid #ddd;padding:8px;'>Nombre</th><th style='border:1px solid #ddd;padding:8px;'>Acción Denegado</th></tr>\n";
    
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td style='border:1px solid #ddd;padding:8px;'>" . $client['id'] . "</td>";
        echo "<td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($client['name']) . "</td>";
        echo "<td style='border:1px solid #ddd;padding:8px;'>" . $client['access_denied_action'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h2>🎯 Opciones disponibles ahora</h2>\n";
    echo "<div class='success'>";
    echo "<h3>✅ ACTUALIZACIÓN COMPLETADA</h3>";
    echo "<p><strong>Nuevas opciones de bloqueo disponibles:</strong></p>";
    echo "<ul>";
    echo "<li><strong>block_forms:</strong> Solo bloquear formularios (permitir navegación)</li>";
    echo "<li><strong>block_site:</strong> Bloquear sitio web completo</li>";
    echo "<li><strong>redirect:</strong> Redirigir a otra URL</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>📝 Próximos pasos</h2>\n";
    echo "<div class='info'>";
    echo "<p><strong>Para completar la implementación:</strong></p>";
    echo "<ol>";
    echo "<li>✅ Base de datos actualizada</li>";
    echo "<li>⏳ Actualizar interfaz de cliente (countries.php)</li>";
    echo "<li>⏳ Actualizar JavaScript del SaaS</li>";
    echo "<li>⏳ Probar nuevas funcionalidades</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ ERROR</h3>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr><p><em>Actualización ejecutada el: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>