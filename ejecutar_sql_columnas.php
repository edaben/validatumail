<?php
/**
 * Script para agregar las columnas necesarias a la tabla clients
 * Ejecuta este archivo una sola vez para solucionar el problema
 */

// Incluir la conexión a la base de datos
require_once __DIR__ . '/saas/config/database.php';

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Ejecutar SQL - Agregar Columnas</title></head><body>";
echo "<h1>🔧 Agregando Columnas a la Tabla Clients</h1>";

try {
    $db = SaasDatabase::getInstance()->getConnection();
    
    echo "<p>✅ Conexión a base de datos establecida</p>";
    
    // Verificar si las columnas ya existen
    $checkQuery = "SHOW COLUMNS FROM clients LIKE '%verification%'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    $existingColumns = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($existingColumns)) {
        echo "<p style='color: green;'>✅ Las columnas ya existen. No es necesario ejecutar el script.</p>";
    } else {
        echo "<p>📝 Las columnas no existen. Agregando...</p>";
        
        // Agregar la columna email_verification_enabled
        $sql1 = "ALTER TABLE clients ADD COLUMN email_verification_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar servicio de verificación de email'";
        $db->exec($sql1);
        echo "<p>✅ Columna 'email_verification_enabled' agregada</p>";
        
        // Agregar la columna geo_blocking_enabled
        $sql2 = "ALTER TABLE clients ADD COLUMN geo_blocking_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar servicio de bloqueo geográfico'";
        $db->exec($sql2);
        echo "<p>✅ Columna 'geo_blocking_enabled' agregada</p>";
        
        // Actualizar todos los clientes existentes
        $sql3 = "UPDATE clients SET email_verification_enabled = 1, geo_blocking_enabled = 1 WHERE email_verification_enabled IS NULL OR geo_blocking_enabled IS NULL";
        $result = $db->exec($sql3);
        echo "<p>✅ Valores por defecto establecidos para $result clientes</p>";
    }
    
    // Verificar el resultado
    echo "<h2>📊 Verificación Final</h2>";
    
    $verifyQuery = "SELECT COUNT(*) as total FROM clients";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute();
    $totalClients = $verifyStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<p>📋 Total de clientes en la base de datos: <strong>$totalClients</strong></p>";
    
    // Mostrar estructura actualizada
    $structQuery = "DESCRIBE clients";
    $structStmt = $db->prepare($structQuery);
    $structStmt->execute();
    $structure = $structStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>🗂️ Estructura actualizada de la tabla 'clients':</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    foreach ($structure as $column) {
        $highlight = (strpos($column['Field'], 'verification') !== false || strpos($column['Field'], 'blocking') !== false) ? 'background: #d4edda;' : '';
        echo "<tr style='$highlight'>";
        echo "<td style='padding: 8px;'>" . $column['Field'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Type'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Null'] . "</td>";
        echo "<td style='padding: 8px;'>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #d4edda; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #c3e6cb;'>";
    echo "<h2 style='color: #155724; margin: 0 0 10px 0;'>🎉 ¡ÉXITO!</h2>";
    echo "<p style='color: #155724; margin: 0;'>Las columnas han sido agregadas correctamente. Ahora puedes acceder a <strong><a href='admin.php'>admin.php</a></strong> y verás todos tus clientes con los checkboxes funcionales.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #f5c6cb;'>";
    echo "<h2 style='color: #721c24; margin: 0 0 10px 0;'>❌ Error</h2>";
    echo "<p style='color: #721c24; margin: 0;'>Error al ejecutar el script: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<br><p><a href='admin.php' style='background: #667eea; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>🔗 Ir al Admin Panel</a></p>";
echo "</body></html>";
?>