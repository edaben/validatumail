<?php
/**
 * Bloqueo de Emergencia para Clientes que Exceden Límite
 * Este script se ejecuta automáticamente para bloquear clientes
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = SaasDatabase::getInstance();
    
    // Buscar todos los clientes que exceden su límite
    $over_limit_clients = $db->fetchAll(
        "SELECT id, name, email, plan, monthly_usage, monthly_limit, status 
         FROM clients 
         WHERE monthly_limit > 0 AND monthly_usage >= monthly_limit AND status = 'active'"
    );
    
    $blocked_count = 0;
    $results = [];
    
    foreach ($over_limit_clients as $client) {
        // Cambiar estado a 'over_limit' para bloqueo adicional
        $db->query(
            "UPDATE clients SET status = 'over_limit', updated_at = NOW() WHERE id = ?",
            [$client['id']]
        );
        
        logActivity('critical', 'BLOQUEO DE EMERGENCIA - Cliente suspendido por límite excedido', [
            'client_id' => $client['id'],
            'client_name' => $client['name'],
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit'],
            'plan' => $client['plan'],
            'previous_status' => $client['status']
        ], $client['id']);
        
        $blocked_count++;
        $results[] = [
            'id' => $client['id'],
            'name' => $client['name'],
            'email' => $client['email'],
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit'],
            'status' => 'blocked'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'blocked_clients' => $blocked_count,
        'message' => "Se bloquearon $blocked_count clientes que excedían su límite",
        'clients' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>