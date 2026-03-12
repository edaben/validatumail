<?php
/**
 * Endpoint de Logging para Eventos de Acceso
 * 
 * Recibe los logs del script JavaScript del cliente
 * para registrar eventos de acceso y estadísticas
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

// Headers CORS y de API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Método no permitido'], 405);
}

try {
    // Obtener datos JSON del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        sendJsonResponse(['error' => 'Datos JSON inválidos'], 400);
    }
    
    // Validar campos requeridos
    $required_fields = ['client_id', 'result'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJsonResponse(['error' => "Campo requerido: $field"], 400);
        }
    }
    
    // Validar client_id
    if (!preg_match('/^[a-f0-9]{32}$/', $data['client_id'])) {
        sendJsonResponse(['error' => 'Client ID inválido'], 400);
    }
    
    $db = SaasDatabase::getInstance();
    
    // Verificar que el cliente existe y está activo
    $client = $db->fetchOne(
        "SELECT id, name, status FROM clients WHERE client_id = ?",
        [$data['client_id']]
    );
    
    if (!$client) {
        sendJsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    if ($client['status'] !== 'active') {
        sendJsonResponse(['error' => 'Cliente no activo'], 403);
    }
    
    // Preparar datos para insertar
    $log_data = [
        'client_id' => $client['id'],
        'client_uuid' => $data['client_id'],
        'access_result' => $data['result'], // 'granted', 'denied', 'error'
        'country_code' => $data['country'] ?? null,
        'country_name' => $data['country_name'] ?? null,
        'city' => $data['city'] ?? null,
        'ip_address' => $data['ip'] ?? getRealIpAddress(),
        'is_vpn' => isset($data['is_vpn']) ? (int)$data['is_vpn'] : 0,
        'is_tor' => isset($data['is_tor']) ? (int)$data['is_tor'] : 0,
        'is_proxy' => isset($data['is_proxy']) ? (int)$data['is_proxy'] : 0,
        'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
        'page_url' => $data['url'] ?? '',
        'referrer' => $data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '',
        'error_message' => $data['error'] ?? null,
        'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s')
    ];
    
    // Insertar log de acceso
    $sql = "INSERT INTO access_logs (
        client_id, client_uuid, access_result, country_code, country_name, city,
        ip_address, is_vpn, is_tor, is_proxy, user_agent, page_url, referrer,
        error_message, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $log_data['client_id'],
        $log_data['client_uuid'],
        $log_data['access_result'],
        $log_data['country_code'],
        $log_data['country_name'],
        $log_data['city'],
        $log_data['ip_address'],
        $log_data['is_vpn'],
        $log_data['is_tor'],
        $log_data['is_proxy'],
        $log_data['user_agent'],
        $log_data['page_url'],
        $log_data['referrer'],
        $log_data['error_message']
    ];
    
    $db->query($sql, $params);
    
    // Actualizar estadísticas del cliente
    updateClientStats($client['id'], $log_data);
    
    // Respuesta exitosa
    sendJsonResponse(['status' => 'success', 'logged' => true]);
    
} catch (Exception $e) {
    logActivity('error', 'Error en endpoint de logging', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'client_id' => $data['client_id'] ?? 'unknown'
    ]);
    
    sendJsonResponse(['error' => 'Error interno del servidor'], 500);
}

/**
 * Actualizar estadísticas del cliente
 */
function updateClientStats($client_id, $log_data) {
    global $db;
    
    try {
        // Verificar límite antes de incrementar uso
        $client_stats = $db->fetchOne(
            "SELECT monthly_usage, monthly_limit, name, email, plan FROM clients WHERE id = ?",
            [$client_id]
        );
        
        $current_usage = $client_stats['monthly_usage'];
        $monthly_limit = $client_stats['monthly_limit'];
        
        // Solo incrementar si no ha alcanzado el límite
        if ($monthly_limit <= 0 || $current_usage < $monthly_limit) {
            // Incrementar contador de uso mensual
            $db->query(
                "UPDATE clients SET monthly_usage = monthly_usage + 1 WHERE id = ?",
                [$client_id]
            );
            
            logActivity('info', 'Uso incrementado correctamente', [
                'client_id' => $client_id,
                'client_name' => $client_stats['name'],
                'previous_usage' => $current_usage,
                'new_usage' => $current_usage + 1,
                'limit' => $monthly_limit,
                'plan' => $client_stats['plan'],
                'access_result' => $log_data['access_result'],
                'remaining' => $monthly_limit > 0 ? ($monthly_limit - $current_usage - 1) : 'unlimited'
            ], $client_id);
        } else {
            // Cliente ha alcanzado su límite - no incrementar
            logActivity('critical', '🚫 LÍMITE EXCEDIDO - Cliente bloqueado intentando usar servicio', [
                'client_id' => $client_id,
                'client_name' => $client_stats['name'],
                'client_email' => $client_stats['email'],
                'current_usage' => $current_usage,
                'limit' => $monthly_limit,
                'plan' => $client_stats['plan'],
                'over_limit_by' => $current_usage - $monthly_limit,
                'access_result' => $log_data['access_result'],
                'page_url' => $log_data['page_url'],
                'country' => $log_data['country_code'],
                'ip_address' => $log_data['ip_address']
            ], $client_id);
        }
        
        // Actualizar estadísticas diarias si no existe entrada para hoy
        $today = date('Y-m-d');
        $existing_stats = $db->fetchOne(
            "SELECT id FROM daily_stats WHERE client_id = ? AND date = ?",
            [$client_id, $today]
        );
        
        if (!$existing_stats) {
            // Crear entrada de estadísticas diarias
            $db->query(
                "INSERT INTO daily_stats (client_id, date, total_requests, granted_requests, denied_requests, unique_countries, created_at) 
                 VALUES (?, ?, 0, 0, 0, 0, NOW())",
                [$client_id, $today]
            );
        }
        
        // Actualizar contadores diarios
        $increment_field = '';
        switch ($log_data['access_result']) {
            case 'granted':
                $increment_field = 'granted_requests = granted_requests + 1,';
                break;
            case 'denied':
                $increment_field = 'denied_requests = denied_requests + 1,';
                break;
        }
        
        $db->query(
            "UPDATE daily_stats SET 
             total_requests = total_requests + 1,
             $increment_field
             updated_at = NOW()
             WHERE client_id = ? AND date = ?",
            [$client_id, $today]
        );
        
        // Actualizar países únicos si hay datos de país
        if ($log_data['country_code']) {
            updateUniqueCountries($client_id, $today, $log_data['country_code']);
        }
        
    } catch (Exception $e) {
        // Log del error pero no fallar el request principal
        logActivity('error', 'Error actualizando estadísticas', [
            'client_id' => $client_id,
            'error' => $e->getMessage()
        ], $client_id);
    }
}

/**
 * Actualizar contador de países únicos
 */
function updateUniqueCountries($client_id, $date, $country_code) {
    global $db;
    
    try {
        // Verificar si ya existe un registro para este país hoy
        $existing_country = $db->fetchOne(
            "SELECT id FROM country_stats WHERE client_id = ? AND date = ? AND country_code = ?",
            [$client_id, $date, $country_code]
        );
        
        if (!$existing_country) {
            // Insertar nuevo país para hoy
            $db->query(
                "INSERT INTO country_stats (client_id, date, country_code, requests, created_at) 
                 VALUES (?, ?, ?, 1, NOW())",
                [$client_id, $date, $country_code]
            );
            
            // Actualizar contador de países únicos en daily_stats
            $unique_countries = $db->fetchOne(
                "SELECT COUNT(DISTINCT country_code) as count FROM country_stats 
                 WHERE client_id = ? AND date = ?",
                [$client_id, $date]
            )['count'];
            
            $db->query(
                "UPDATE daily_stats SET unique_countries = ? WHERE client_id = ? AND date = ?",
                [$unique_countries, $client_id, $date]
            );
        } else {
            // Incrementar contador existente
            $db->query(
                "UPDATE country_stats SET requests = requests + 1 WHERE client_id = ? AND date = ? AND country_code = ?",
                [$client_id, $date, $country_code]
            );
        }
        
    } catch (Exception $e) {
        // Ignorar errores de estadísticas de países
    }
}

/**
 * Obtener la IP real del visitante
 */
function getRealIpAddress() {
    $ip_headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}