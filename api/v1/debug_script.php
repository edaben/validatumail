<?php
/**
 * Script de Debugging para client_script.php
 * Debugs paso a paso lo que está pasando
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG SCRIPT GENERADOR</h1>\n";
echo "<style>body{font-family:Arial;} .error{background:#f8d7da;padding:10px;border-radius:5px;color:#721c24;} .success{background:#d4edda;padding:10px;border-radius:5px;color:#155724;} .info{background:#cce7ff;padding:10px;border-radius:5px;color:#004085;}</style>\n";

try {
    echo "<h2>📋 PASO 1: Verificar includes</h2>\n";
    
    // Verificar archivos
    $config_file = '../../config/config.php';
    $db_file = '../../config/database.php';
    
    if (file_exists($config_file)) {
        echo "<div class='success'>✅ config.php encontrado</div>\n";
        require_once $config_file;
    } else {
        echo "<div class='error'>❌ config.php NO encontrado en: " . realpath($config_file) . "</div>\n";
        exit;
    }
    
    if (file_exists($db_file)) {
        echo "<div class='success'>✅ database.php encontrado</div>\n";
        require_once $db_file;
    } else {
        echo "<div class='error'>❌ database.php NO encontrado en: " . realpath($db_file) . "</div>\n";
        exit;
    }
    
    echo "<h2>🔗 PASO 2: Verificar conexión a base de datos</h2>\n";
    
    $db = SaasDatabase::getInstance();
    echo "<div class='success'>✅ Conexión a base de datos exitosa</div>\n";
    
    echo "<h2>📥 PASO 3: Obtener parámetros</h2>\n";
    
    $client_id = $_GET['client'] ?? '59f299a8a3dd1e28ac520b35c00a7169';
    echo "<div class='info'>Client ID: " . htmlspecialchars($client_id) . "</div>\n";
    
    // Validar formato
    if (!preg_match('/^[a-f0-9]{32}$/', $client_id)) {
        echo "<div class='error'>❌ Formato de client_id inválido</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Formato de client_id válido</div>\n";
    
    echo "<h2>🔍 PASO 4: Buscar cliente en base de datos</h2>\n";
    
    $sql = "SELECT id, name, status, plan, countries_allowed, countries_denied, 
            access_control_mode, allow_vpn, allow_tor, allow_proxy, allow_hosting,
            access_denied_action, redirect_url, monthly_usage, monthly_limit
            FROM clients WHERE client_id = ?";
    
    echo "<div class='info'>SQL: " . htmlspecialchars($sql) . "</div>\n";
    echo "<div class='info'>Parámetros: [" . htmlspecialchars($client_id) . "]</div>\n";
    
    $client = $db->fetchOne($sql, [$client_id]);
    
    if (!$client) {
        echo "<div class='error'>❌ Cliente no encontrado en base de datos</div>\n";
        
        // Mostrar clientes disponibles
        $all_clients = $db->fetchAll("SELECT client_id, name, email FROM clients LIMIT 5");
        echo "<h3>Clientes disponibles:</h3>\n";
        foreach ($all_clients as $c) {
            echo "<div class='info'>- " . $c['name'] . " (ID: " . $c['client_id'] . ")</div>\n";
        }
        exit;
    }
    
    echo "<div class='success'>✅ Cliente encontrado: " . htmlspecialchars($client['name']) . "</div>\n";
    
    echo "<h2>🔒 PASO 5: Verificar status y límites</h2>\n";
    
    if ($client['status'] !== 'active') {
        echo "<div class='error'>❌ Cliente no activo. Status: " . $client['status'] . "</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Cliente activo</div>\n";
    
    if ($client['monthly_usage'] >= $client['monthly_limit']) {
        echo "<div class='error'>❌ Límite mensual excedido: " . $client['monthly_usage'] . "/" . $client['monthly_limit'] . "</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Límite mensual OK: " . $client['monthly_usage'] . "/" . $client['monthly_limit'] . "</div>\n";
    
    echo "<h2>⚙️ PASO 6: Preparar configuración</h2>\n";
    
    $config = [
        'countries_allowed' => $client['countries_allowed'] ? explode(',', $client['countries_allowed']) : [],
        'countries_denied' => $client['countries_denied'] ? explode(',', $client['countries_denied']) : [],
        'access_control_mode' => $client['access_control_mode'],
        'allow_vpn' => (bool)$client['allow_vpn'],
        'allow_tor' => (bool)$client['allow_tor'],
        'allow_proxy' => (bool)$client['allow_proxy'],
        'allow_hosting' => (bool)$client['allow_hosting'],
        'access_denied_action' => $client['access_denied_action'],
        'access_denied_redirect_url' => $client['redirect_url'],
        'client_id' => $client_id,
        'service_url' => SAAS_SITE_URL . '/api/v1',
        'debug_mode' => false
    ];
    
    echo "<div class='success'>✅ Configuración preparada</div>\n";
    echo "<pre style='background:#f8f9fa;padding:15px;border-radius:5px;'>";
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>\n";
    
    echo "<h2>🚀 PASO 7: Generar JavaScript</h2>\n";
    
    $config_json = json_encode($config, JSON_UNESCAPED_SLASHES);
    $generated_time = date('Y-m-d H:i:s');
    
    echo "<div class='success'>✅ JavaScript generado exitosamente</div>\n";
    
    echo "<h3>📄 Contenido del JavaScript:</h3>\n";
    echo "<textarea style='width:100%;height:300px;font-family:monospace;font-size:12px;'>";
    
    echo "/**\n";
    echo " * ZipGeo - Servicio de Control de Acceso Geográfico\n";
    echo " * Cliente: {$config['client_id']}\n";
    echo " * Generado: {$generated_time}\n";
    echo " */\n\n";
    
    echo "(function() {\n";
    echo "    'use strict';\n\n";
    echo "    // Configuración del cliente (embebida)\n";
    echo "    const CLIENT_CONFIG = {$config_json};\n\n";
    echo "    console.log('ZipGeo iniciado para cliente:', CLIENT_CONFIG.client_id);\n";
    echo "    console.log('Configuración:', CLIENT_CONFIG);\n\n";
    echo "    // ... resto del código JavaScript ...\n";
    echo "})();\n";
    
    echo "</textarea>\n";
    
    echo "<h2>🎯 RESULTADO FINAL</h2>\n";
    echo "<div class='success'>";
    echo "<h3>✅ TODO FUNCIONANDO CORRECTAMENTE</h3>";
    echo "<p><strong>URL de Prueba:</strong> <a href='client_script.php?client=" . $client_id . "' target='_blank'>client_script.php?client=" . $client_id . "</a></p>";
    echo "<p><strong>URL .htaccess:</strong> <a href='../js/" . $client_id . ".js' target='_blank'>../js/" . $client_id . ".js</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR DETECTADO</h2>\n";
    echo "<div class='error'>";
    echo "<strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr><p><em>Debug ejecutado el: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>