<?php
/**
 * API Endpoint para Configuración de Clientes SaaS
 * 
 * Este endpoint proporciona la configuración de geolocalización
 * para cada cliente basado en su API key
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Headers CORS y de API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(['error' => 'Método no permitido'], 405);
}

try {
    // Obtener API key del header Authorization o parámetro
    $api_key = null;
    
    // Método 1: Header Authorization Bearer
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $api_key = $matches[1];
    }
    
    // Método 2: Header X-API-Key
    if (!$api_key) {
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    }
    
    // Método 3: Parámetro GET (menos seguro, pero compatible)
    if (!$api_key) {
        $api_key = $_GET['api_key'] ?? '';
    }
    
    if (!$api_key) {
        sendJsonResponse(['error' => 'API key requerida'], 401);
    }
    
    // Validar formato de API key
    if (!preg_match('/^geo_[a-f0-9]{32}$/', $api_key)) {
        sendJsonResponse(['error' => 'Formato de API key inválido'], 401);
    }
    
    $db = SaasDatabase::getInstance();
    
    // Buscar cliente por API key
    $client = $db->fetchOne(
        "SELECT id, name, email, status, plan, countries_allowed, countries_denied, 
                access_control_mode, allow_vpn, allow_tor, allow_proxy, allow_hosting,
                access_denied_action, redirect_url, monthly_usage, monthly_limit
         FROM clients WHERE api_key = ?",
        [$api_key]
    );
    
    if (!$client) {
        logActivity('warning', 'API key inválida utilizada', [
            'api_key' => substr($api_key, 0, 10) . '...',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        sendJsonResponse(['error' => 'API key inválida'], 401);
    }
    
    if ($client['status'] !== 'active') {
        if ($client['status'] === 'over_limit') {
            sendJsonResponse([
                'error' => 'Cuenta bloqueada por límite excedido',
                'message' => 'Tu cuenta ha sido suspendida por exceder el límite mensual',
                'status' => 'over_limit',
                'usage' => $client['monthly_usage'],
                'limit' => $client['monthly_limit'],
                'upgrade_url' => SAAS_SITE_URL . '/client/upgrade_plan.php'
            ], 402);
        } else {
            sendJsonResponse(['error' => 'Cuenta no activa', 'status' => $client['status']], 403);
        }
    }
    
    // Verificar límite mensual ANTES de todo
    if ($client['monthly_limit'] > 0 && $client['monthly_usage'] >= $client['monthly_limit']) {
        logActivity('critical', 'LÍMITE EXCEDIDO - Bloqueo aplicado en config.php', [
            'client_id' => $client['id'],
            'client_name' => $client['name'],
            'client_email' => $client['email'],
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit'],
            'plan' => $client['plan'],
            'percent_used' => round(($client['monthly_usage'] / $client['monthly_limit']) * 100, 1),
            'api_key' => substr($api_key, 0, 10) . '...',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ], $client['id']);
        
        sendJsonResponse([
            'error' => 'Límite mensual alcanzado',
            'message' => 'Has alcanzado el límite de ' . number_format($client['monthly_limit']) . ' validaciones para tu plan ' . ucfirst($client['plan']),
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit'],
            'plan' => $client['plan'],
            'blocked_at' => date('Y-m-d H:i:s'),
            'upgrade_url' => SAAS_SITE_URL . '/client/upgrade_plan.php'
        ], 402); // 402 Payment Required
    }
    
    // Verificar límites de uso (rate limiting)
    $rate_limit_exceeded = checkRateLimit($client['id'], $api_key);
    if ($rate_limit_exceeded) {
        sendJsonResponse(['error' => 'Límite de uso excedido'], 429);
    }
    
    // Obtener dominio del request (para validación y logging)
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $domain = '';
    if ($referrer) {
        $parsed = parse_url($referrer);
        $domain = $parsed['host'] ?? '';
    }
    
    // Obtener IP del visitor (no del cliente)
    $visitor_ip = getRealIpAddress();
    
    // Preparar configuración de respuesta
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
        'debug_mode' => false, // Siempre false en producción para clientes
        'server_url' => SAAS_SITE_URL,
        'config_version' => time(), // Para invalidación de caché
        'client_info' => [
            'plan' => $client['plan'],
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit']
        ]
    ];
    
    // Log del request para estadísticas
    logApiRequest($client['id'], $api_key, $domain, $visitor_ip);
    
    // Responder con la configuración
    sendJsonResponse($config);
    
} catch (Exception $e) {
    logActivity('error', 'Error en API endpoint', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'api_key' => isset($api_key) ? substr($api_key, 0, 10) . '...' : 'none'
    ]);
    
    sendJsonResponse(['error' => 'Error interno del servidor'], 500);
}

/**
 * Verificar límites de uso (rate limiting)
 */
function checkRateLimit($client_id, $api_key) {
    global $db;
    
    try {
        // Verificar límite por hora
        $hour_start = date('Y-m-d H:00:00');
        $requests_this_hour = $db->fetchOne(
            "SELECT COUNT(*) as count FROM api_requests 
             WHERE client_id = ? AND created_at >= ?",
            [$client_id, $hour_start]
        )['count'];
        
        if ($requests_this_hour >= API_RATE_LIMIT_PER_HOUR) {
            return true;
        }
        
        // Verificar límite por minuto
        $minute_start = date('Y-m-d H:i:00');
        $requests_this_minute = $db->fetchOne(
            "SELECT COUNT(*) as count FROM api_requests 
             WHERE client_id = ? AND created_at >= ?",
            [$client_id, $minute_start]
        )['count'];
        
        if ($requests_this_minute >= API_RATE_LIMIT_PER_MINUTE) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        // En caso de error, permitir el request (fail open)
        logActivity('error', 'Error verificando rate limit', [
            'client_id' => $client_id,
            'error' => $e->getMessage()
        ], $client_id);
        
        return false;
    }
}

/**
 * Obtener la IP real del visitante
 */
function getRealIpAddress() {
    // Verificar diferentes headers en orden de confiabilidad
    $ip_headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_REAL_IP',            // Nginx proxy
        'HTTP_X_FORWARDED_FOR',      // Proxy estándar
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // IP directa
    ];
    
    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validar que sea una IP válida
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback a REMOTE_ADDR si no encontramos nada válido
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Registrar request de API para estadísticas
 */
function logApiRequest($client_id, $api_key, $domain, $visitor_ip) {
    global $db;
    
    try {
        // Preparar datos del request
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $start_time = microtime(true);
        
        // Obtener información básica de geolocalización para el visitor_ip
        // En una implementación completa, aquí se haría un request a la API de geolocalización
        // Por ahora, solo registramos el request sin datos de geo
        
        $sql = "INSERT INTO api_requests (
            client_id, api_key, domain, ip_address, user_agent, referrer,
            response_time_ms, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        $params = [
            $client_id,
            $api_key,
            $domain,
            $visitor_ip,
            $user_agent,
            $referrer,
            $response_time
        ];
        
        $db->query($sql, $params);
        
        // Verificar límite antes de incrementar (doble verificación)
        $current_client = $db->fetchOne(
            "SELECT monthly_usage, monthly_limit FROM clients WHERE id = ?",
            [$client_id]
        );
        
        if ($current_client['monthly_limit'] <= 0 || $current_client['monthly_usage'] < $current_client['monthly_limit']) {
            // Actualizar contador mensual del cliente
            $db->query(
                "UPDATE clients SET monthly_usage = monthly_usage + 1 WHERE id = ?",
                [$client_id]
            );
            
            logActivity('info', 'API uso registrado', [
                'client_id' => $client_id,
                'previous_usage' => $current_client['monthly_usage'],
                'new_usage' => $current_client['monthly_usage'] + 1,
                'limit' => $current_client['monthly_limit']
            ], $client_id);
        } else {
            logActivity('warning', 'Límite alcanzado - no incrementando uso en API config', [
                'client_id' => $client_id,
                'usage' => $current_client['monthly_usage'],
                'limit' => $current_client['monthly_limit']
            ], $client_id);
        }
        
    } catch (Exception $e) {
        // Log del error pero no fallar el request principal
        logActivity('error', 'Error registrando API request', [
            'client_id' => $client_id,
            'error' => $e->getMessage()
        ], $client_id);
    }
}