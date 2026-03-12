<?php
// Archivo de acciones para la administración del sistema de control geográfico
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: admin.php');
    exit;
}

// Función para escribir el archivo .env
function writeEnvFile($config) {
    $envContent = "# Modo de Control de Acceso\n";
    $envContent .= "# Establecer como \"allowed\" para usar la lista COUNTRIES_ALLOWED (bloquear todos los demás)\n";
    $envContent .= "# Establecer como \"denied\" para usar la lista COUNTRIES_DENIED (permitir todos los demás)\n";
    $envContent .= "ACCESS_CONTROL_MODE=" . ($config['ACCESS_CONTROL_MODE'] ?? 'allowed') . "\n";
    $envContent .= "# ACCESS_CONTROL_MODE=denied\n\n";
    
    $envContent .= "# Listas de Países (códigos ISO de 2 letras separados por comas)\n";
    $envContent .= "COUNTRIES_ALLOWED=" . ($config['COUNTRIES_ALLOWED'] ?? '') . "\n";
    $envContent .= "COUNTRIES_DENIED=" . ($config['COUNTRIES_DENIED'] ?? '') . "\n\n";
    
    $envContent .= "# Lista de Dominios Permitidos (dominios separados por comas donde el script puede ejecutarse)\n";
    $envContent .= "DOMAINS_WHITELIST=" . ($config['DOMAINS_WHITELIST'] ?? '') . "\n\n";
    
    $envContent .= "# URL del Servidor (donde se encuentra la carpeta geo)\n";
    $envContent .= "SERVER_URL=" . ($config['SERVER_URL'] ?? '') . "\n\n";
    
    $envContent .= "# Duración de la Caché (en horas)\n";
    $envContent .= "CACHE_DURATION=" . ($config['CACHE_DURATION'] ?? '168') . "\n\n";
    
    $envContent .= "# Control de Privacidad (yes/no)\n";
    $envContent .= "# Establecer como \"yes\" para permitir, \"no\" para bloquear\n";
    $envContent .= "ALLOW_VPN=" . ($config['ALLOW_VPN'] ?? 'no') . "\n";
    $envContent .= "ALLOW_TOR=" . ($config['ALLOW_TOR'] ?? 'no') . "\n";
    $envContent .= "ALLOW_PROXY=" . ($config['ALLOW_PROXY'] ?? 'no') . "\n";
    $envContent .= "ALLOW_HOSTING=" . ($config['ALLOW_HOSTING'] ?? 'no') . "\n\n";
    
    $envContent .= "# Comportamiento para Acceso Denegado\n";
    $envContent .= "# Establecer como \"redirect\" para redirigir a la página de acceso denegado\n";
    $envContent .= "# Establecer como \"block_forms\" para bloquear los formularios pero permitir la navegación\n\n";
    
    if (($config['ACCESS_DENIED_ACTION'] ?? 'block_forms') === 'redirect') {
        $envContent .= "ACCESS_DENIED_ACTION=redirect\n";
        $envContent .= "# ACCESS_DENIED_ACTION=block_forms\n\n";
    } else {
        $envContent .= "# ACCESS_DENIED_ACTION=redirect\n";
        $envContent .= "ACCESS_DENIED_ACTION=block_forms\n\n";
    }
    
    $envContent .= "# URL de Redirección para Acceso Denegado\n";
    $envContent .= "# Esta URL se utilizará cuando ACCESS_DENIED_ACTION=redirect\n\n";
    $envContent .= "# ACCESS_DENIED_REDIRECT_URL=https://www.google.com/\n";
    $envContent .= "ACCESS_DENIED_REDIRECT_URL=" . ($config['ACCESS_DENIED_REDIRECT_URL'] ?? '') . "\n\n";
    
    $envContent .= "# Modo de Depuración\n";
    $envContent .= "# Establecer como \"true\" para mostrar mensajes de depuración en la consola y el servidor\n";
    $envContent .= "# Establecer como \"false\" para desactivar todos los mensajes de depuración\n";
    $envContent .= "DEBUG_MODE=" . ($config['DEBUG_MODE'] ?? 'true') . "\n";
    
    return file_put_contents('.env', $envContent) !== false;
}

// Función para leer la configuración actual
function loadEnvConfig() {
    $config = [];
    if (file_exists('.env')) {
        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    return $config;
}

// Procesar formularios
$message = '';
$messageType = 'success';

try {
    // Cargar configuración actual
    $currentConfig = loadEnvConfig();
    
    // Verificar qué formulario se envió y procesar datos
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Configuración de países
        if (isset($_POST['access_mode'])) {
            $currentConfig['ACCESS_CONTROL_MODE'] = $_POST['access_mode'];
            $currentConfig['COUNTRIES_ALLOWED'] = trim($_POST['countries_allowed'] ?? '');
            $currentConfig['COUNTRIES_DENIED'] = trim($_POST['countries_denied'] ?? '');
            
            $message = 'Configuración de países actualizada exitosamente.';
        }
        
        // Configuración de dominios
        if (isset($_POST['domains_whitelist'])) {
            // Convertir lista de líneas a lista separada por comas
            $domains = $_POST['domains_whitelist'];
            $domains = str_replace("\n", ",", $domains);
            $domains = str_replace("\r", "", $domains);
            $domainsArray = explode(',', $domains);
            $domainsArray = array_map('trim', $domainsArray);
            $domainsArray = array_filter($domainsArray, function($domain) {
                return !empty($domain);
            });
            $currentConfig['DOMAINS_WHITELIST'] = implode(',', $domainsArray);
            
            $message = 'Lista de dominios actualizada exitosamente.';
        }
        
        // Configuración general
        if (isset($_POST['server_url'])) {
            $currentConfig['SERVER_URL'] = trim($_POST['server_url'] ?? '');
            $currentConfig['CACHE_DURATION'] = trim($_POST['cache_duration'] ?? '168');
            $currentConfig['ALLOW_VPN'] = $_POST['allow_vpn'] ?? 'no';
            $currentConfig['ALLOW_TOR'] = $_POST['allow_tor'] ?? 'no';
            $currentConfig['ALLOW_PROXY'] = $_POST['allow_proxy'] ?? 'no';
            $currentConfig['ALLOW_HOSTING'] = $_POST['allow_hosting'] ?? 'no';
            $currentConfig['ACCESS_DENIED_ACTION'] = $_POST['access_denied_action'] ?? 'block_forms';
            $currentConfig['ACCESS_DENIED_REDIRECT_URL'] = trim($_POST['access_denied_redirect_url'] ?? '');
            $currentConfig['DEBUG_MODE'] = $_POST['debug_mode'] ?? 'true';
            
            $message = 'Configuración general actualizada exitosamente.';
        }
        
        // Guardar configuración
        if (writeEnvFile($currentConfig)) {
            // Crear un timestamp para forzar la actualización de caché
            $currentConfig['CONFIG_VERSION'] = time();
            writeEnvFile($currentConfig);
            
            $message .= ' Los cambios se aplicarán en los sitios web inmediatamente.';
        } else {
            $message = 'Error al guardar la configuración. Verifique los permisos del archivo.';
            $messageType = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
}

// Redirigir de vuelta a admin.php con mensaje
$_SESSION['admin_message'] = $message;
$_SESSION['admin_message_type'] = $messageType;

header('Location: admin.php');
exit;
?>