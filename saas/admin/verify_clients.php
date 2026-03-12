<?php
/**
 * Script de Verificación de Clientes
 * Verifica los clientes existentes en la base de datos
 */

require_once '../config/config.php';
require_once '../config/database.php';

echo "<h1>🔍 VERIFICACIÓN DE CLIENTES SAAS</h1>\n";
echo "<style>body{font-family:Arial;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>\n";

try {
    $db = SaasDatabase::getInstance();
    
    // Verificar conexión
    echo "<h2>✅ Conexión a Base de Datos: EXITOSA</h2>\n";
    
    // Obtener todos los clientes
    $clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC");
    
    echo "<h2>👥 CLIENTES REGISTRADOS (" . count($clients) . ")</h2>\n";
    
    if (empty($clients)) {
        echo "<p style='color:orange;'>⚠️ No hay clientes registrados. Vamos a crear uno de prueba.</p>\n";
        
        // Crear cliente de prueba
        $test_password = generateRandomPassword(10);
        $test_client_id = generateClientId();
        $test_api_key = generateApiKey();
        
        $sql = "INSERT INTO clients (
            name, email, password_hash, api_key, client_id, status, plan,
            countries_allowed, access_control_mode, allow_vpn, allow_tor,
            access_denied_action, monthly_limit, email_verified_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            'Cliente Prueba',
            'prueba@test.com',
            hashPassword($test_password),
            $test_api_key,
            $test_client_id,
            'active',
            'basic',
            'EC,CO,PE,AR,MX,ES,US',  // Países permitidos
            'allowed',
            1, // allow_vpn
            0, // allow_tor
            'block_forms',
            10000
        ];
        
        $db->query($sql, $params);
        
        echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;'>";
        echo "<h3>🎉 CLIENTE DE PRUEBA CREADO</h3>";
        echo "<strong>Email:</strong> prueba@test.com<br>";
        echo "<strong>Password:</strong> " . $test_password . "<br>";
        echo "<strong>Client ID:</strong> " . $test_client_id . "<br>";
        echo "<strong>Países Permitidos:</strong> Ecuador, Colombia, Perú, Argentina, México, España, Estados Unidos<br>";
        echo "<strong>Modo:</strong> Lista Blanca (solo países seleccionados)<br>";
        echo "<strong>VPN:</strong> Permitido<br>";
        echo "</div>";
        
        // Volver a obtener clientes
        $clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC");
    }
    
    // Mostrar tabla de clientes
    echo "<table>\n";
    echo "<tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Email</th>
        <th>Client ID</th>
        <th>Status</th>
        <th>Plan</th>
        <th>Países Permitidos</th>
        <th>Modo Control</th>
        <th>VPN</th>
        <th>Uso Mensual</th>
        <th>Acción Denegado</th>
    </tr>\n";
    
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td>" . $client['id'] . "</td>";
        echo "<td>" . htmlspecialchars($client['name']) . "</td>";
        echo "<td>" . htmlspecialchars($client['email']) . "</td>";
        echo "<td style='font-family:monospace;font-size:12px;'>" . $client['client_id'] . "</td>";
        echo "<td>" . $client['status'] . "</td>";
        echo "<td>" . $client['plan'] . "</td>";
        echo "<td>" . ($client['countries_allowed'] ?: '<em>ninguno</em>') . "</td>";
        echo "<td>" . $client['access_control_mode'] . "</td>";
        echo "<td>" . ($client['allow_vpn'] ? '✅' : '❌') . "</td>";
        echo "<td>" . $client['monthly_usage'] . "/" . $client['monthly_limit'] . "</td>";
        echo "<td>" . $client['access_denied_action'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Verificar JavaScript generado para el primer cliente
    if (!empty($clients)) {
        $first_client = $clients[0];
        $client_id = $first_client['client_id'];
        
        echo "<h2>🚀 JAVASCRIPT GENERADO PARA CLIENT: " . $client_id . "</h2>\n";
        echo "<p><strong>URL del Script:</strong> <a href='https://validatumail.cc/saas/js/" . $client_id . ".js' target='_blank'>https://validatumail.cc/saas/js/" . $client_id . ".js</a></p>\n";
        
        // Generar configuración como lo hace client_script.php
        $config = [
            'countries_allowed' => $first_client['countries_allowed'] ? explode(',', $first_client['countries_allowed']) : [],
            'countries_denied' => $first_client['countries_denied'] ? explode(',', $first_client['countries_denied']) : [],
            'access_control_mode' => $first_client['access_control_mode'],
            'allow_vpn' => (bool)$first_client['allow_vpn'],
            'allow_tor' => (bool)$first_client['allow_tor'],
            'allow_proxy' => (bool)$first_client['allow_proxy'],
            'allow_hosting' => (bool)$first_client['allow_hosting'],
            'access_denied_action' => $first_client['access_denied_action'],
            'access_denied_redirect_url' => $first_client['redirect_url'],
            'client_id' => $client_id,
            'service_url' => SAAS_SITE_URL . '/api/v1',
            'debug_mode' => false
        ];
        
        echo "<h3>📋 CONFIGURACIÓN EMBEBIDA:</h3>\n";
        echo "<pre style='background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;'>";
        echo "const CLIENT_CONFIG = " . json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "</pre>\n";
        
        echo "<h3>📝 IMPLEMENTACIÓN PARA EL CLIENTE:</h3>\n";
        echo "<pre style='background:#e8f5e8;padding:15px;border-radius:5px;'>";
        echo htmlspecialchars('<script src="https://validatumail.cc/saas/js/' . $client_id . '.js"></script>');
        echo "</pre>\n";
    }
    
    // Verificar logs recientes
    $recent_logs = $db->fetchAll("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 10");
    echo "<h2>📊 LOGS DE ACCESO RECIENTES (" . count($recent_logs) . ")</h2>\n";
    
    if (!empty($recent_logs)) {
        echo "<table>\n";
        echo "<tr><th>Cliente</th><th>Resultado</th><th>País</th><th>IP</th><th>Fecha</th></tr>\n";
        foreach ($recent_logs as $log) {
            echo "<tr>";
            echo "<td>" . $log['client_uuid'] . "</td>";
            echo "<td>" . $log['access_result'] . "</td>";
            echo "<td>" . $log['country_code'] . "</td>";
            echo "<td>" . $log['ip_address'] . "</td>";
            echo "<td>" . $log['created_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No hay logs de acceso aún.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR</h2>\n";
    echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    
    // Mostrar información de diagnóstico
    echo "<h3>🔧 DIAGNÓSTICO:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</li>\n";
    echo "<li><strong>Archivo:</strong> " . $e->getFile() . "</li>\n";
    echo "<li><strong>Línea:</strong> " . $e->getLine() . "</li>\n";
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<h2>🔗 ENLACES ÚTILES:</h2>\n";
echo "<ul>\n";
echo "<li><a href='../public/index.php'>Página Principal del SaaS</a></li>\n";
echo "<li><a href='../public/login.php'>Login de Clientes</a></li>\n";
echo "<li><a href='../client/dashboard.php'>Dashboard (requiere login)</a></li>\n";
echo "</ul>\n";
echo "<p><em>Ejecutado el: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>