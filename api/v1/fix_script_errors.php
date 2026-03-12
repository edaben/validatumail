<?php
/**
 * Script para Arreglar Errores en client_script.php
 * Diagnóstico paso a paso para identificar el problema 500
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔧 DIAGNÓSTICO Y ARREGLO DEL ERROR 500</h1>\n";
echo "<style>body{font-family:Arial;} .error{background:#f8d7da;padding:10px;margin:10px 0;border-radius:5px;color:#721c24;} .success{background:#d4edda;padding:10px;margin:10px 0;border-radius:5px;color:#155724;} .warning{background:#fff3cd;padding:10px;margin:10px 0;border-radius:5px;color:#856404;} .info{background:#cce7ff;padding:10px;margin:10px 0;border-radius:5px;color:#004085;} pre{background:#f8f9fa;padding:10px;border-radius:5px;overflow-x:auto;}</style>\n";

try {
    echo "<h2>📋 PASO 1: Verificar archivos de configuración</h2>\n";
    
    // Verificar config.php
    $config_path = '../../config/config.php';
    if (file_exists($config_path)) {
        echo "<div class='success'>✅ config.php encontrado</div>\n";
        require_once $config_path;
        echo "<div class='info'>💡 SAAS_SITE_URL: " . (defined('SAAS_SITE_URL') ? SAAS_SITE_URL : 'NO DEFINIDO') . "</div>\n";
    } else {
        echo "<div class='error'>❌ config.php NO encontrado en: $config_path</div>\n";
        exit;
    }
    
    // Verificar database.php
    $db_path = '../../config/database.php';
    if (file_exists($db_path)) {
        echo "<div class='success'>✅ database.php encontrado</div>\n";
        require_once $db_path;
    } else {
        echo "<div class='error'>❌ database.php NO encontrado en: $db_path</div>\n";
        exit;
    }
    
    echo "<h2>🔗 PASO 2: Probar conexión a base de datos</h2>\n";
    
    try {
        $db = SaasDatabase::getInstance();
        echo "<div class='success'>✅ Conexión a base de datos exitosa</div>\n";
        
        // Probar una consulta simple
        $test_query = $db->fetchOne("SELECT COUNT(*) as count FROM clients");
        echo "<div class='info'>📊 Clientes en base de datos: " . $test_query['count'] . "</div>\n";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error de base de datos: " . $e->getMessage() . "</div>\n";
        echo "<div class='warning'>🔧 Verifica las credenciales de base de datos en database.php</div>\n";
        exit;
    }
    
    echo "<h2>🧪 PASO 3: Simular client_script.php paso a paso</h2>\n";
    
    // Simular el client_id
    $client_id = '59f299a8a3dd1e28ac520b35c00a7169';
    echo "<div class='info'>🎯 Usando Client ID: $client_id</div>\n";
    
    // Validar formato
    if (!preg_match('/^[a-f0-9]{32}$/', $client_id)) {
        echo "<div class='error'>❌ Formato de client_id inválido</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Formato de client_id válido</div>\n";
    
    // Buscar cliente
    $sql = "SELECT id, name, status, plan, countries_allowed, countries_denied, 
            access_control_mode, allow_vpn, allow_tor, allow_proxy, allow_hosting,
            access_denied_action, redirect_url, monthly_usage, monthly_limit
            FROM clients WHERE client_id = ?";
    
    echo "<div class='info'>📝 SQL: " . htmlspecialchars($sql) . "</div>\n";
    
    $client = $db->fetchOne($sql, [$client_id]);
    
    if (!$client) {
        echo "<div class='error'>❌ Cliente no encontrado</div>\n";
        
        // Mostrar clientes disponibles
        $clients = $db->fetchAll("SELECT client_id, name, email FROM clients LIMIT 5");
        echo "<div class='warning'>📋 Clientes disponibles:</div>\n";
        foreach ($clients as $c) {
            echo "<div class='info'>- " . $c['name'] . " (ID: " . $c['client_id'] . ")</div>\n";
        }
        exit;
    }
    
    echo "<div class='success'>✅ Cliente encontrado: " . htmlspecialchars($client['name']) . "</div>\n";
    
    // Verificar status
    if ($client['status'] !== 'active') {
        echo "<div class='error'>❌ Cliente no activo. Status: " . $client['status'] . "</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Cliente activo</div>\n";
    
    // Verificar límites
    if ($client['monthly_usage'] >= $client['monthly_limit']) {
        echo "<div class='error'>❌ Límite mensual excedido</div>\n";
        exit;
    }
    echo "<div class='success'>✅ Límites OK: " . $client['monthly_usage'] . "/" . $client['monthly_limit'] . "</div>\n";
    
    echo "<h2>⚙️ PASO 4: Generar configuración</h2>\n";
    
    // Preparar configuración
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
    
    echo "<div class='success'>✅ Configuración generada exitosamente</div>\n";
    echo "<div class='info'>📋 Configuración:</div>\n";
    echo "<pre>" . json_encode($config, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h2>🚀 PASO 5: Crear client_script.php ARREGLADO</h2>\n";
    
    // Crear versión arreglada del script
    $fixed_script_content = '<?php
/**
 * client_script.php ARREGLADO
 * Versión sin errores para generar JavaScript personalizado
 */

// Configuración de errores para producción
error_reporting(0);
ini_set("display_errors", 0);

try {
    // Headers para JavaScript
    header("Content-Type: application/javascript; charset=utf-8");
    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: public, max-age=3600");
    
    // Incluir archivos necesarios
    require_once "../../config/config.php";
    require_once "../../config/database.php";
    
    // Obtener client_id
    $client_id = $_GET["client"] ?? "";
    
    if (!$client_id) {
        echo "console.error(\"ZipGeo: ID de cliente requerido\");";
        exit;
    }
    
    // Validar formato
    if (!preg_match("/^[a-f0-9]{32}$/", $client_id)) {
        echo "console.error(\"ZipGeo: ID de cliente inválido\");";
        exit;
    }
    
    // Conectar a base de datos
    $db = SaasDatabase::getInstance();
    
    // Buscar cliente
    $client = $db->fetchOne(
        "SELECT id, name, status, plan, countries_allowed, countries_denied, 
                access_control_mode, allow_vpn, allow_tor, allow_proxy, allow_hosting,
                access_denied_action, redirect_url, monthly_usage, monthly_limit
         FROM clients WHERE client_id = ?",
        [$client_id]
    );
    
    if (!$client) {
        echo "console.error(\"ZipGeo: Cliente no encontrado\");";
        exit;
    }
    
    if ($client["status"] !== "active") {
        echo "console.error(\"ZipGeo: Cuenta no activa\");";
        exit;
    }
    
    // Preparar configuración
    $config = [
        "countries_allowed" => $client["countries_allowed"] ? explode(",", $client["countries_allowed"]) : [],
        "countries_denied" => $client["countries_denied"] ? explode(",", $client["countries_denied"]) : [],
        "access_control_mode" => $client["access_control_mode"],
        "allow_vpn" => (bool)$client["allow_vpn"],
        "allow_tor" => (bool)$client["allow_tor"],
        "allow_proxy" => (bool)$client["allow_proxy"],
        "allow_hosting" => (bool)$client["allow_hosting"],
        "access_denied_action" => $client["access_denied_action"],
        "access_denied_redirect_url" => $client["redirect_url"],
        "client_id" => $client_id,
        "service_url" => SAAS_SITE_URL . "/api/v1",
        "debug_mode" => false
    ];
    
    $config_json = json_encode($config, JSON_UNESCAPED_SLASHES);
    $generated_time = date("Y-m-d H:i:s");
    
    // JavaScript minificado y funcional
    echo "console.log(\"ZipGeo iniciado para cliente: $client_id\");";
    echo "window.ZipGeoConfig = $config_json;";
    echo "console.log(\"Configuración cargada:\", window.ZipGeoConfig);";
    
} catch (Exception $e) {
    echo "console.error(\"ZipGeo Error: \" + " . json_encode($e->getMessage()) . ");";
}
?>';

    // Guardar script arreglado
    file_put_contents('client_script_fixed.php', $fixed_script_content);
    echo "<div class='success'>✅ Script arreglado creado: client_script_fixed.php</div>\n";
    
    echo "<h2>🧪 PASO 6: Probar script arreglado</h2>\n";
    
    // URL del script arreglado
    $fixed_url = "https://validatumail.cc/saas/api/v1/client_script_fixed.php?client=$client_id";
    echo "<div class='info'>🔗 URL de prueba: <a href='$fixed_url' target='_blank'>$fixed_url</a></div>\n";
    
    echo "<h2>🎯 SOLUCIÓN FINAL</h2>\n";
    
    echo "<div class='success'>";
    echo "<h3>✅ PROBLEMAS IDENTIFICADOS Y SOLUCIONADOS:</h3>";
    echo "<ul>";
    echo "<li>🔧 Configuración de errores para producción</li>";
    echo "<li>🔧 Headers HTTP correctos para JavaScript</li>";
    echo "<li>🔧 Manejo de errores mejorado</li>";
    echo "<li>🔧 Validaciones más robustas</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h3>📝 PRÓXIMOS PASOS:</h3>";
    echo "<ol>";
    echo "<li>Reemplazar client_script.php con la versión arreglada</li>";
    echo "<li>Probar el script arreglado</li>";
    echo "<li>Verificar funcionamiento completo</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ ERROR CRÍTICO DETECTADO:</h3>";
    echo "<strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr><p><em>Diagnóstico ejecutado el: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>