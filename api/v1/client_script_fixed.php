<?php
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
?>