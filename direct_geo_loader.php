<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/javascript");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Get the requesting domain
$requestingDomain = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : 'unknown';

// Function to load environment variables from a file
function loadEnvFile($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $env = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignore comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse line as VAR=VALUE
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            $env[$name] = $value;
        }
    }
    
    return $env;
}

// Load environment variables
$env = loadEnvFile(__DIR__ . '/.env');
$domainsWhitelist = isset($env['DOMAINS_WHITELIST']) ? $env['DOMAINS_WHITELIST'] : 'sitios.smartyapz.com,example.com,localhost';
$debugMode = isset($env['DEBUG_MODE']) && strtolower($env['DEBUG_MODE']) === 'true';

// Read the JavaScript file
$js_content = file_get_contents(__DIR__ . '/direct_geo.js');

// Add a timestamp comment for cache busting
$timestamp = date('Y-m-d H:i:s');
$js_content = "/* Loaded for domain: $requestingDomain at $timestamp */\n" . $js_content;

// Add debug mode variable at the beginning of the script
$js_content = "// Debug mode setting from .env\nconst DEBUG_MODE = " . ($debugMode ? "true" : "false") . ";\n\n" . $js_content;

// Only add debug logs if debug mode is enabled
$debug_code = '';
if ($debugMode) {
    $debug_code = '';
    if ($debugMode) {
        $debug_code = <<<EOT
// Define debug helper function first
function debugLog(...args) {
    if (DEBUG_MODE === true) {
        console.log(...args);
    }
}

// Then use it for logging
console.log('DEBUG - Script cargado desde direct_geo_loader.php');
console.log('DEBUG - Dominio solicitante: "$requestingDomain"');
console.log('DEBUG - Timestamp: "$timestamp"');
console.log('DEBUG - Whitelist cargada desde .env: "$domainsWhitelist"');

EOT;
    }
}

// Replace the hardcoded whitelist with the one from .env
$whitelist_pattern = "/let\s+DOMAINS_WHITELIST\s*=\s*\[\s*'[^']*'\s*(,\s*'[^']*'\s*)*\]\s*;/";
$whitelist_replacement = "let DOMAINS_WHITELIST = ['" . str_replace(",", "','", $domainsWhitelist) . "'];";

$js_content = preg_replace($whitelist_pattern, $whitelist_replacement, $js_content);

// Insert the debug code at the beginning of the script, after any initial comments
$js_content = preg_replace('/(\/\*\*.*?\*\/\s*)/s', '$1' . $debug_code, $js_content, 1);
if (!preg_last_error()) {
    // If the regex replacement failed, just prepend the debug code
    $js_content = $debug_code . $js_content;
}

// Output the JavaScript content
echo $js_content;
?>