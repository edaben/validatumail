<?php
// Manejar CORS para dominios permitidos
function handleCORS() {
    // Leer el archivo .env para obtener la lista de dominios permitidos
    $envFile = __DIR__ . '/.env';
    $domainsWhitelist = [];
    
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $lines = explode("\n", $envContent);
        
        foreach ($lines as $line) {
            if (strpos($line, 'DOMAINS_WHITELIST=') === 0) {
                $domainsStr = substr($line, strlen('DOMAINS_WHITELIST='));
                $domainsWhitelist = array_map('trim', explode(',', $domainsStr));
                break;
            }
        }
    }
    
    // Si no se encuentra la lista en .env, usar una lista vacía
    // No usamos valores predeterminados para evitar hardcoding de dominios
    if (empty($domainsWhitelist)) {
        $domainsWhitelist = [];
        error_log("CORS Warning: No se encontró DOMAINS_WHITELIST en el archivo .env");
    }
    
    // Verificar si el origen está en la lista blanca
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $originDomain = parse_url($origin, PHP_URL_HOST);
        
        foreach ($domainsWhitelist as $domain) {
            // Permitir subdominios (ejemplo.com permite a.ejemplo.com)
            if ($originDomain === $domain || preg_match('/\.' . preg_quote($domain, '/') . '$/', $originDomain)) {
                header("Access-Control-Allow-Origin: $origin");
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                header("Access-Control-Allow-Headers: Content-Type");
                break;
            }
        }
    }
    
    // Manejar solicitudes preflight OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

// Aplicar CORS
handleCORS();

header('Content-Type: application/json');

// VERIFICAR SI ESTÁ SIENDO USADO POR UN CLIENTE DEL SaaS
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$is_saas_request = false;
$client_id = null;

// Verificar si el request viene de un sitio con script SaaS
if ($referrer) {
    // Incluir archivos del SaaS solo si es necesario
    try {
        require_once 'saas/config/config.php';
        require_once 'saas/config/database.php';
        
        $db = SaasDatabase::getInstance();
        
        // Buscar si hay algún cliente con un sitio web que coincida con el referrer
        $referrer_domain = parse_url($referrer, PHP_URL_HOST);
        
        $client = $db->fetchOne(
            "SELECT c.id, c.monthly_usage, c.monthly_limit, c.plan, c.status
             FROM clients c
             JOIN client_websites cw ON c.id = cw.client_id
             WHERE cw.domain LIKE ? AND cw.is_active = 1 AND c.status = 'active'",
            ["%$referrer_domain%"]
        );
        
        if ($client) {
            $is_saas_request = true;
            $client_id = $client['id'];
            
            // VERIFICAR LÍMITE MENSUAL
            if ($client['monthly_limit'] > 0 && $client['monthly_usage'] >= $client['monthly_limit']) {
                echo json_encode([
                    'error' => 'Límite mensual alcanzado',
                    'status' => 'limit_reached',
                    'message' => 'El sitio web ha alcanzado su límite mensual de validaciones',
                    'usage' => $client['monthly_usage'],
                    'limit' => $client['monthly_limit'],
                    'plan' => $client['plan']
                ]);
                exit;
            }
        }
    } catch (Exception $e) {
        // Si hay error con SaaS, continuar con validación normal
        error_log("Error verificando límites SaaS: " . $e->getMessage());
    }
}

// Obtener el email de la solicitud
$email = isset($_GET['email']) ? urlencode($_GET['email']) : '';

if (empty($email)) {
    echo json_encode(['error' => 'Email no proporcionado']);
    exit;
}

// Leer la clave de API desde el archivo .api_key
$apiKey = '';
$apiKeyFile = __DIR__ . '/.api_key';

if (file_exists($apiKeyFile)) {
    $apiKeyContent = file_get_contents($apiKeyFile);
    $lines = explode("\n", $apiKeyContent);
    
    foreach ($lines as $line) {
        if (strpos($line, 'RAPID_API_KEY=') === 0) {
            $apiKey = substr($line, strlen('RAPID_API_KEY='));
            break;
        }
    }
}

if (empty($apiKey)) {
    echo json_encode(['error' => 'API key no encontrada. Por favor, configure el archivo .api_key']);
    exit;
}

// Inicializar cURL
$curl = curl_init();

// Configurar la solicitud cURL
curl_setopt_array($curl, [
    CURLOPT_URL => "https://validect-email-verification-v1.p.rapidapi.com/v1/verify?email=" . $email,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "x-rapidapi-host: validect-email-verification-v1.p.rapidapi.com",
        "x-rapidapi-key: " . trim($apiKey)
    ],
]);

// Ejecutar la solicitud
$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

// Manejar errores o devolver la respuesta
if ($err) {
    echo json_encode(['error' => 'Error de cURL: ' . $err]);
} else {
    // Si es un request de SaaS y fue exitoso, incrementar contador
    if ($is_saas_request && $client_id) {
        try {
            require_once 'saas/config/config.php';
            require_once 'saas/config/database.php';
            
            $db = SaasDatabase::getInstance();
            
            // Verificar límite una vez más antes de incrementar
            $current_client = $db->fetchOne(
                "SELECT monthly_usage, monthly_limit FROM clients WHERE id = ?",
                [$client_id]
            );
            
            if ($current_client['monthly_limit'] <= 0 || $current_client['monthly_usage'] < $current_client['monthly_limit']) {
                $db->query(
                    "UPDATE clients SET monthly_usage = monthly_usage + 1 WHERE id = ?",
                    [$client_id]
                );
                
                // Log de uso
                $db->query(
                    "INSERT INTO system_logs (client_id, level, message, context, ip_address, created_at) VALUES (?, 'info', 'Email validation used', ?, ?, NOW())",
                    [$client_id, json_encode(['email' => substr($email, 0, 10) . '...', 'referrer' => $referrer]), $_SERVER['REMOTE_ADDR'] ?? '']
                );
            }
        } catch (Exception $e) {
            error_log("Error incrementando uso de email validator: " . $e->getMessage());
        }
    }
    
    echo $response;
}
?>