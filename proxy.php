<?php
/**
 * Proxy seguro para la API de iplocate.io
 * 
 * Este script actúa como un intermediario seguro entre el cliente y la API de iplocate.io,
 * manteniendo la clave API segura en el servidor.
 */

// Iniciar temporizador para medir el rendimiento
$startTime = microtime(true);

// Crear directorio de logs si no existe
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Función para registrar mensajes de depuración con marcas de tiempo precisas
function proxyDebug($message) {
    global $startTime;
    $currentTime = microtime(true);
    $elapsedMs = round(($currentTime - $startTime) * 1000, 2); // Tiempo transcurrido en milisegundos
    $timestamp = date('Y-m-d H:i:s') . '.' . sprintf('%03d', round(microtime(true) - floor(microtime(true)), 3) * 1000);
    $logMessage = "[$timestamp] [+{$elapsedMs}ms] $message" . PHP_EOL;
    
    // Escribir en el archivo de registro
    $logFile = __DIR__ . '/logs/proxy_debug.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Registrar inicio de la solicitud
proxyDebug("=== INICIO DE SOLICITUD PROXY ===");
proxyDebug("Método: " . $_SERVER['REQUEST_METHOD'] . ", URI: " . $_SERVER['REQUEST_URI']);

// Configurar encabezados CORS para permitir solicitudes de dominios cruzados
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Función para cargar variables de entorno desde un archivo
function loadEnvFile($filePath) {
    proxyDebug("Cargando archivo: $filePath");
    $startLoad = microtime(true);
    
    if (!file_exists($filePath)) {
        proxyDebug("Archivo no encontrado: $filePath");
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Analizar la línea como VAR=VALOR
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Establecer la variable de entorno
        $_ENV[$name] = $value;
    }
    
    $loadTime = round((microtime(true) - $startLoad) * 1000, 2);
    proxyDebug("Archivo cargado en {$loadTime}ms: $filePath");
    return true;
}

// Cargar variables de entorno
proxyDebug("Iniciando carga de archivos de configuración");
$envLoaded = loadEnvFile(__DIR__ . '/.env');
$apiKeyLoaded = loadEnvFile(__DIR__ . '/.api_key');

if (!$envLoaded || !$apiKeyLoaded) {
    proxyDebug("ERROR: Archivos de configuración no encontrados");
    echo json_encode(['error' => 'Configuration files not found']);
    exit;
}

// Verificar si solo se solicita registrar un mensaje de depuración
if (isset($_GET['debug_only']) && $_GET['debug_only'] === 'true') {
    $message = isset($_GET['message']) ? $_GET['message'] : 'Mensaje de depuración no especificado';
    proxyDebug("DEPURACIÓN CLIENTE: " . str_replace('_', ' ', $message));
    echo json_encode(['status' => 'debug_recorded']);
    proxyDebug("=== FIN DE SOLICITUD PROXY (solo depuración) ===");
    exit;
}

// Verificar si se solicita obtener la IP del cliente
if (isset($_GET['get_ip']) && $_GET['get_ip'] === 'true') {
    proxyDebug("Solicitando IP del cliente");
    
    // Obtener la IP del cliente
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    
    proxyDebug("IP del cliente: $ip");
    echo json_encode(['ip' => $ip]);
    proxyDebug("=== FIN DE SOLICITUD PROXY (obtener IP) ===");
    exit;
}

// Verificar si se solicita la configuración completa
if (isset($_GET['get_config']) && $_GET['get_config'] === 'true') {
    proxyDebug("Solicitando configuración completa");
    
    // Obtener toda la configuración
    $accessControlMode = isset($_ENV['ACCESS_CONTROL_MODE']) ? $_ENV['ACCESS_CONTROL_MODE'] : 'allowed';
    $countriesAllowed = isset($_ENV['COUNTRIES_ALLOWED']) ? $_ENV['COUNTRIES_ALLOWED'] : '';
    $countriesDenied = isset($_ENV['COUNTRIES_DENIED']) ? $_ENV['COUNTRIES_DENIED'] : '';
    $allowVpn = isset($_ENV['ALLOW_VPN']) ? $_ENV['ALLOW_VPN'] : 'no';
    $allowTor = isset($_ENV['ALLOW_TOR']) ? $_ENV['ALLOW_TOR'] : 'no';
    $allowProxy = isset($_ENV['ALLOW_PROXY']) ? $_ENV['ALLOW_PROXY'] : 'no';
    $allowHosting = isset($_ENV['ALLOW_HOSTING']) ? $_ENV['ALLOW_HOSTING'] : 'no';
    $accessDeniedAction = isset($_ENV['ACCESS_DENIED_ACTION']) ? $_ENV['ACCESS_DENIED_ACTION'] : 'block_forms';
    $accessDeniedRedirectUrl = isset($_ENV['ACCESS_DENIED_REDIRECT_URL']) ? $_ENV['ACCESS_DENIED_REDIRECT_URL'] : 'access-denied.php';
    $serverUrl = isset($_ENV['SERVER_URL']) ? $_ENV['SERVER_URL'] : '';
    $domainsWhitelist = isset($_ENV['DOMAINS_WHITELIST']) ? $_ENV['DOMAINS_WHITELIST'] : '';
    
    // Obtener la fecha de modificación del archivo .env para usar como versión de configuración
    $configTimestamp = filemtime(__DIR__ . '/.env');
    
    $config = [
        'access_control_mode' => $accessControlMode,
        'countries_allowed' => $countriesAllowed,
        'countries_denied' => $countriesDenied,
        'allow_vpn' => $allowVpn,
        'allow_tor' => $allowTor,
        'allow_proxy' => $allowProxy,
        'allow_hosting' => $allowHosting,
        'access_denied_action' => $accessDeniedAction,
        'access_denied_redirect_url' => $accessDeniedRedirectUrl,
        'server_url' => $serverUrl,
        'domains_whitelist' => $domainsWhitelist,
        'config_version' => $configTimestamp
    ];
    
    proxyDebug("Enviando configuración completa: " . json_encode($config));
    echo json_encode($config);
    proxyDebug("=== FIN DE SOLICITUD PROXY (configuración completa) ===");
    exit;
}

// Para compatibilidad con versiones anteriores
if (isset($_GET['get_privacy_config']) && $_GET['get_privacy_config'] === 'true') {
    proxyDebug("Solicitando configuración de privacidad (método antiguo)");
    
    // Obtener configuración de privacidad
    $allowVpn = isset($_ENV['ALLOW_VPN']) ? $_ENV['ALLOW_VPN'] : 'no';
    $allowTor = isset($_ENV['ALLOW_TOR']) ? $_ENV['ALLOW_TOR'] : 'no';
    $allowProxy = isset($_ENV['ALLOW_PROXY']) ? $_ENV['ALLOW_PROXY'] : 'no';
    $allowHosting = isset($_ENV['ALLOW_HOSTING']) ? $_ENV['ALLOW_HOSTING'] : 'no';
    
    $config = [
        'allow_vpn' => $allowVpn,
        'allow_tor' => $allowTor,
        'allow_proxy' => $allowProxy,
        'allow_hosting' => $allowHosting
    ];
    
    proxyDebug("Enviando configuración de privacidad: " . json_encode($config));
    echo json_encode($config);
    proxyDebug("=== FIN DE SOLICITUD PROXY (configuración de privacidad) ===");
    exit;
}

// Obtener la IP a consultar
proxyDebug("Obteniendo IP del visitante");
$ip = isset($_GET['ip']) ? $_GET['ip'] : $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
}
proxyDebug("IP a consultar: $ip");

// Verificar si la IP es válida
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    proxyDebug("ERROR: IP inválida: $ip");
    echo json_encode(['error' => 'Invalid IP address']);
    exit;
}

// Determinar el método a usar
$method = isset($_GET['method']) ? $_GET['method'] : 'curl';
proxyDebug("Método de solicitud: $method");

// Obtener la clave API de forma segura
$apiKey = $_ENV['IPLOCATE_API_KEY'];
$url = "https://iplocate.io/api/lookup/{$ip}?apikey={$apiKey}";
proxyDebug("URL de la API: " . preg_replace('/apikey=([^&]+)/', 'apikey=XXXXX', $url));

// Registrar configuración de privacidad
$allowVpn = isset($_ENV['ALLOW_VPN']) ? $_ENV['ALLOW_VPN'] : 'no';
$allowTor = isset($_ENV['ALLOW_TOR']) ? $_ENV['ALLOW_TOR'] : 'no';
$allowProxy = isset($_ENV['ALLOW_PROXY']) ? $_ENV['ALLOW_PROXY'] : 'no';
$allowHosting = isset($_ENV['ALLOW_HOSTING']) ? $_ENV['ALLOW_HOSTING'] : 'no';

proxyDebug("Configuración de privacidad: VPN={$allowVpn}, Tor={$allowTor}, Proxy={$allowProxy}, Hosting={$allowHosting}");

if ($method === 'file_get_contents') {
    // Método alternativo usando file_get_contents
    proxyDebug("Iniciando solicitud con file_get_contents");
    $startRequest = microtime(true);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: GeoAccessControl/1.0',
                'Accept: application/json',
                'Connection: close'
            ],
            'timeout' => 1,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    $requestTime = round((microtime(true) - $startRequest) * 1000, 2);
    proxyDebug("Respuesta recibida en {$requestTime}ms");
    
    if ($response === false) {
        proxyDebug("ERROR: No se pudo obtener respuesta de la API");
        echo json_encode(['error' => 'Failed to get response from API']);
        exit;
    }
    
    // Verificar que la respuesta es JSON válido
    $data = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        proxyDebug("ERROR: Respuesta no es JSON válido: " . json_last_error_msg());
        proxyDebug("Respuesta recibida: " . substr($response, 0, 100) . (strlen($response) > 100 ? '...' : ''));
        echo json_encode(['error' => 'Invalid JSON response from API']);
        exit;
    }
    
    proxyDebug("Respuesta JSON válida recibida");
    if (isset($data->country_code)) {
        proxyDebug("País detectado: " . $data->country . " (" . $data->country_code . ")");
    }
    
    // Devolver la respuesta tal cual
    proxyDebug("=== FIN DE SOLICITUD PROXY (éxito) ===");
    echo $response;
    exit;
} else {
    // Método predeterminado usando cURL
    proxyDebug("Iniciando solicitud cURL");
    $startCurl = microtime(true);
    
    // Usar la IP del cliente, no la del servidor
    proxyDebug("Usando la IP del cliente para la consulta");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GeoAccessControl/1.0');
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Connection: close'
    ]);
    $response = curl_exec($ch);
    
    $curlTime = round((microtime(true) - $startCurl) * 1000, 2);
    proxyDebug("Respuesta de cURL recibida en {$curlTime}ms");
    
    if (curl_errno($ch)) {
        $errorMsg = 'API request failed: ' . curl_error($ch);
        proxyDebug("ERROR cURL: " . $errorMsg);
        echo json_encode([
            'error' => $errorMsg,
            'curl_error_code' => curl_errno($ch)
        ]);
        curl_close($ch);
        proxyDebug("=== FIN DE SOLICITUD PROXY (error cURL) ===");
        exit;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    proxyDebug("Código HTTP de respuesta: $httpCode");
    
    if ($httpCode != 200) {
        proxyDebug("ERROR: La API devolvió un código HTTP no exitoso: $httpCode");
        echo json_encode([
            'error' => 'API returned HTTP code ' . $httpCode
        ]);
        proxyDebug("=== FIN DE SOLICITUD PROXY (error HTTP) ===");
        exit;
    }
    
    // Verificar que la respuesta es JSON válido
    $data = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        proxyDebug("ERROR: Respuesta no es JSON válido: " . json_last_error_msg());
        proxyDebug("Respuesta recibida: " . substr($response, 0, 100) . (strlen($response) > 100 ? '...' : ''));
        echo json_encode(['error' => 'Invalid JSON response from API']);
        proxyDebug("=== FIN DE SOLICITUD PROXY (error JSON) ===");
        exit;
    }
    
    proxyDebug("Respuesta JSON válida recibida");
    if (isset($data->country_code)) {
        proxyDebug("País detectado: " . $data->country . " (" . $data->country_code . ")");
    }
    
    // Devolver la respuesta tal cual
    proxyDebug("=== FIN DE SOLICITUD PROXY (éxito) ===");
    echo $response;
    exit;
}