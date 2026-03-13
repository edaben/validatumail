<?php
/**
 * Configuración General del SaaS GeoControl
 * 
 * Este archivo contiene todas las configuraciones del sistema SaaS
 * Independiente del sistema actual
 */

// Configuración del sitio
define('SAAS_SITE_NAME', 'GeoControl SaaS');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";

if (isset($_SERVER['HTTP_HOST'])) {
    $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
} else {
    $base_url = getenv('SERVER_URL') ?: "https://validatumail.cc";
}

define('SAAS_SITE_URL', rtrim($base_url, '/') . '/saas');
define('SAAS_VERSION', '1.0.0');

// Configuración de email AWS SES
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'AKIAW2UZ3F4FGEDD63B2');
define('MAIL_PASSWORD', 'BEaqtBF0mRgvnZ33K2eXaiSklVWMgSlgHHpdhZ94N5QH');
define('MAIL_FROM_EMAIL', 'eduardo@rastroseguro.com');
define('MAIL_FROM_NAME', 'GeoControl SaaS');

// Configuración de planes basados en USO (validaciones mensuales)
$SAAS_PLANS = [
    'free' => [
        'name' => 'Gratuito',
        'price' => 0,
        'monthly_limit' => 30,
        'websites_limit' => 1,
        'features' => [
            '30 validaciones/mes',
            '1 sitio web protegido',
            'Control básico por países',
            'Email support',
            'Dashboard básico'
        ]
    ],
    'basic' => [
        'name' => 'Básico',
        'price' => 19,
        'monthly_limit' => 500,
        'websites_limit' => 3,
        'features' => [
            '500 validaciones/mes',
            '3 sitios web protegidos',
            'Todos los países disponibles',
            'Chat support prioritario',
            'Dashboard avanzado',
            'Estadísticas detalladas'
        ]
    ],
    'premium' => [
        'name' => 'Premium',
        'price' => 49,
        'monthly_limit' => 5000,
        'websites_limit' => 10,
        'features' => [
            '5,000 validaciones/mes',
            '10 sitios web protegidos',
            'Control VPN/Proxy/Tor avanzado',
            'Phone support',
            'API prioritaria',
            'Informes personalizados',
            'Configuración avanzada'
        ]
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 149,
        'monthly_limit' => -1, // Ilimitado
        'websites_limit' => -1, // Ilimitado
        'features' => [
            'Validaciones ILIMITADAS',
            'Sitios web ILIMITADOS',
            'API ultra-rápida dedicada',
            'Soporte 24/7 prioritario',
            'Implementación personalizada',
            'SLA garantizado 99.9%',
            'Consultoría incluida'
        ]
    ]
];

// Configuración de países (ISO codes)
$COUNTRIES = [
    'AD' => 'Andorra',
    'AE' => 'Emiratos Árabes Unidos',
    'AF' => 'Afganistán',
    'AG' => 'Antigua y Barbuda',
    'AI' => 'Anguila',
    'AL' => 'Albania',
    'AM' => 'Armenia',
    'AO' => 'Angola',
    'AQ' => 'Antártida',
    'AR' => 'Argentina',
    'AS' => 'Samoa Americana',
    'AT' => 'Austria',
    'AU' => 'Australia',
    'AW' => 'Aruba',
    'AX' => 'Islas Åland',
    'AZ' => 'Azerbaiyán',
    'BA' => 'Bosnia y Herzegovina',
    'BB' => 'Barbados',
    'BD' => 'Bangladesh',
    'BE' => 'Bélgica',
    'BF' => 'Burkina Faso',
    'BG' => 'Bulgaria',
    'BH' => 'Baréin',
    'BI' => 'Burundi',
    'BJ' => 'Benín',
    'BL' => 'San Bartolomé',
    'BM' => 'Bermudas',
    'BN' => 'Brunéi',
    'BO' => 'Bolivia',
    'BQ' => 'Bonaire',
    'BR' => 'Brasil',
    'BS' => 'Bahamas',
    'BT' => 'Bután',
    'BV' => 'Isla Bouvet',
    'BW' => 'Botsuana',
    'BY' => 'Bielorrusia',
    'BZ' => 'Belice',
    'CA' => 'Canadá',
    'CC' => 'Islas Cocos',
    'CD' => 'República Democrática del Congo',
    'CF' => 'República Centroafricana',
    'CG' => 'República del Congo',
    'CH' => 'Suiza',
    'CI' => 'Costa de Marfil',
    'CK' => 'Islas Cook',
    'CL' => 'Chile',
    'CM' => 'Camerún',
    'CN' => 'China',
    'CO' => 'Colombia',
    'CR' => 'Costa Rica',
    'CU' => 'Cuba',
    'CV' => 'Cabo Verde',
    'CW' => 'Curazao',
    'CX' => 'Isla de Navidad',
    'CY' => 'Chipre',
    'CZ' => 'República Checa',
    'DE' => 'Alemania',
    'DJ' => 'Yibuti',
    'DK' => 'Dinamarca',
    'DM' => 'Dominica',
    'DO' => 'República Dominicana',
    'DZ' => 'Argelia',
    'EC' => 'Ecuador',
    'EE' => 'Estonia',
    'EG' => 'Egipto',
    'EH' => 'Sáhara Occidental',
    'ER' => 'Eritrea',
    'ES' => 'España',
    'ET' => 'Etiopía',
    'FI' => 'Finlandia',
    'FJ' => 'Fiyi',
    'FK' => 'Islas Malvinas',
    'FM' => 'Micronesia',
    'FO' => 'Islas Feroe',
    'FR' => 'Francia',
    'GA' => 'Gabón',
    'GB' => 'Reino Unido',
    'GD' => 'Granada',
    'GE' => 'Georgia',
    'GF' => 'Guayana Francesa',
    'GG' => 'Guernsey',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GL' => 'Groenlandia',
    'GM' => 'Gambia',
    'GN' => 'Guinea',
    'GP' => 'Guadalupe',
    'GQ' => 'Guinea Ecuatorial',
    'GR' => 'Grecia',
    'GS' => 'Islas Georgias del Sur y Sandwich del Sur',
    'GT' => 'Guatemala',
    'GU' => 'Guam',
    'GW' => 'Guinea-Bisáu',
    'GY' => 'Guyana',
    'HK' => 'Hong Kong',
    'HM' => 'Islas Heard y McDonald',
    'HN' => 'Honduras',
    'HR' => 'Croacia',
    'HT' => 'Haití',
    'HU' => 'Hungría',
    'ID' => 'Indonesia',
    'IE' => 'Irlanda',
    'IL' => 'Israel',
    'IM' => 'Isla de Man',
    'IN' => 'India',
    'IO' => 'Territorio Británico del Océano Índico',
    'IQ' => 'Irak',
    'IR' => 'Irán',
    'IS' => 'Islandia',
    'IT' => 'Italia',
    'JE' => 'Jersey',
    'JM' => 'Jamaica',
    'JO' => 'Jordania',
    'JP' => 'Japón',
    'KE' => 'Kenia',
    'KG' => 'Kirguistán',
    'KH' => 'Camboya',
    'KI' => 'Kiribati',
    'KM' => 'Comoras',
    'KN' => 'San Cristóbal y Nieves',
    'KP' => 'Corea del Norte',
    'KR' => 'Corea del Sur',
    'KW' => 'Kuwait',
    'KY' => 'Islas Caimán',
    'KZ' => 'Kazajistán',
    'LA' => 'Laos',
    'LB' => 'Líbano',
    'LC' => 'Santa Lucía',
    'LI' => 'Liechtenstein',
    'LK' => 'Sri Lanka',
    'LR' => 'Liberia',
    'LS' => 'Lesoto',
    'LT' => 'Lituania',
    'LU' => 'Luxemburgo',
    'LV' => 'Letonia',
    'LY' => 'Libia',
    'MA' => 'Marruecos',
    'MC' => 'Mónaco',
    'MD' => 'Moldavia',
    'ME' => 'Montenegro',
    'MF' => 'San Martín',
    'MG' => 'Madagascar',
    'MH' => 'Islas Marshall',
    'MK' => 'Macedonia del Norte',
    'ML' => 'Malí',
    'MM' => 'Myanmar',
    'MN' => 'Mongolia',
    'MO' => 'Macao',
    'MP' => 'Islas Marianas del Norte',
    'MQ' => 'Martinica',
    'MR' => 'Mauritania',
    'MS' => 'Montserrat',
    'MT' => 'Malta',
    'MU' => 'Mauricio',
    'MV' => 'Maldivas',
    'MW' => 'Malaui',
    'MX' => 'México',
    'MY' => 'Malasia',
    'MZ' => 'Mozambique',
    'NA' => 'Namibia',
    'NC' => 'Nueva Caledonia',
    'NE' => 'Níger',
    'NF' => 'Isla Norfolk',
    'NG' => 'Nigeria',
    'NI' => 'Nicaragua',
    'NL' => 'Países Bajos',
    'NO' => 'Noruega',
    'NP' => 'Nepal',
    'NR' => 'Nauru',
    'NU' => 'Niue',
    'NZ' => 'Nueva Zelanda',
    'OM' => 'Omán',
    'PA' => 'Panamá',
    'PE' => 'Perú',
    'PF' => 'Polinesia Francesa',
    'PG' => 'Papúa Nueva Guinea',
    'PH' => 'Filipinas',
    'PK' => 'Pakistán',
    'PL' => 'Polonia',
    'PM' => 'San Pedro y Miquelón',
    'PN' => 'Islas Pitcairn',
    'PR' => 'Puerto Rico',
    'PS' => 'Palestina',
    'PT' => 'Portugal',
    'PW' => 'Palaos',
    'PY' => 'Paraguay',
    'QA' => 'Catar',
    'RE' => 'Reunión',
    'RO' => 'Rumania',
    'RS' => 'Serbia',
    'RU' => 'Rusia',
    'RW' => 'Ruanda',
    'SA' => 'Arabia Saudí',
    'SB' => 'Islas Salomón',
    'SC' => 'Seychelles',
    'SD' => 'Sudán',
    'SE' => 'Suecia',
    'SG' => 'Singapur',
    'SH' => 'Santa Elena',
    'SI' => 'Eslovenia',
    'SJ' => 'Svalbard y Jan Mayen',
    'SK' => 'Eslovaquia',
    'SL' => 'Sierra Leona',
    'SM' => 'San Marino',
    'SN' => 'Senegal',
    'SO' => 'Somalia',
    'SR' => 'Surinam',
    'SS' => 'Sudán del Sur',
    'ST' => 'Santo Tomé y Príncipe',
    'SV' => 'El Salvador',
    'SX' => 'San Martín',
    'SY' => 'Siria',
    'SZ' => 'Esuatini',
    'TC' => 'Islas Turcas y Caicos',
    'TD' => 'Chad',
    'TF' => 'Territorios Australes Franceses',
    'TG' => 'Togo',
    'TH' => 'Tailandia',
    'TJ' => 'Tayikistán',
    'TK' => 'Tokelau',
    'TL' => 'Timor Oriental',
    'TM' => 'Turkmenistán',
    'TN' => 'Túnez',
    'TO' => 'Tonga',
    'TR' => 'Turquía',
    'TT' => 'Trinidad y Tobago',
    'TV' => 'Tuvalu',
    'TW' => 'Taiwán',
    'TZ' => 'Tanzania',
    'UA' => 'Ucrania',
    'UG' => 'Uganda',
    'UM' => 'Islas Ultramarinas de Estados Unidos',
    'US' => 'Estados Unidos',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistán',
    'VA' => 'Ciudad del Vaticano',
    'VC' => 'San Vicente y las Granadinas',
    'VE' => 'Venezuela',
    'VG' => 'Islas Vírgenes Británicas',
    'VI' => 'Islas Vírgenes de los Estados Unidos',
    'VN' => 'Vietnam',
    'VU' => 'Vanuatu',
    'WF' => 'Wallis y Futuna',
    'WS' => 'Samoa',
    'YE' => 'Yemen',
    'YT' => 'Mayotte',
    'ZA' => 'Sudáfrica',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabue'
];

// Configuración de seguridad
define('ADMIN_PASSWORD', 'rastro2228'); // Contraseña del panel administrativo
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 7200); // 2 horas
define('TOKEN_EXPIRY', 86400); // 24 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutos

// Configuración de rate limiting
define('API_RATE_LIMIT_PER_HOUR', 1000);
define('API_RATE_LIMIT_PER_MINUTE', 60);

// Funciones de utilidad
function getSaasConfig($key = null)
{
    global $SAAS_PLANS, $COUNTRIES;

    $config = [
        'plans' => $SAAS_PLANS,
        'countries' => $COUNTRIES,
        'site_name' => SAAS_SITE_NAME,
        'site_url' => SAAS_SITE_URL,
        'version' => SAAS_VERSION
    ];

    if ($key) {
        return $config[$key] ?? null;
    }

    return $config;
}

function getPlanLimits($plan)
{
    global $SAAS_PLANS;
    return $SAAS_PLANS[$plan] ?? $SAAS_PLANS['free'];
}

function generateApiKey($prefix = 'geo_')
{
    return $prefix . bin2hex(random_bytes(16));
}

function generateClientId()
{
    return md5(uniqid(mt_rand(), true));
}

function generateSecureToken($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

function generateRandomPassword($length = 12)
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $password;
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateDomain($domain)
{
    // Remover protocolo si existe
    $domain = preg_replace('/^https?:\/\//', '', $domain);

    // Remover www si existe
    $domain = preg_replace('/^www\./', '', $domain);

    // Validar formato de dominio
    return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
}

function logActivity($level, $message, $context = [], $client_id = null)
{
    try {
        $db = SaasDatabase::getInstance();

        $sql = "INSERT INTO system_logs (client_id, level, message, context, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";

        $params = [
            $client_id,
            $level,
            $message,
            json_encode($context),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $db->query($sql, $params);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

function sendJsonResponse($data, $status_code = 200)
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirectToLogin($message = null)
{
    $url = '/saas/public/login.php';
    if ($message) {
        $url .= '?message=' . urlencode($message);
    }
    header('Location: ' . $url);
    exit;
}

// Inicializar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}