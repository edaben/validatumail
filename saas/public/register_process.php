<?php
/**
 * Procesador de Registro de Clientes SaaS
 * 
 * Este archivo maneja el registro de nuevos clientes:
 * - Valida datos del formulario
 * - Crea cuenta en la base de datos
 * - Genera credenciales automáticamente
 * - Envía email de bienvenida
 * - Envía los datos directamente a FluentCRM (sin n8n)
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/aws_ses_smtp.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?message=Método no permitido&type=error');
    exit;
}

try {
    // Obtener y sanitizar datos del formulario
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    
    // Valores por defecto
    $country = 'EC';
    $company = '';
    $message = '';
    $selected_plan = 'free';
    $from_checkout = false;
    $payment_method = '';

    // Validaciones
    $errors = [];
    if (empty($name)) $errors[] = 'El nombre es obligatorio';
    if (empty($phone)) $errors[] = 'El teléfono es obligatorio';
    if (empty($email) || !validateEmail($email)) $errors[] = 'Email válido es obligatorio';
    if (empty($website) || !filter_var($website, FILTER_VALIDATE_URL)) $errors[] = 'URL del sitio web válida es obligatoria';

    if (!empty($errors)) {
        $error_message = implode('. ', $errors);
        header('Location: index.php?message=' . urlencode($error_message) . '&type=error');
        exit;
    }

    // Conexión a DB
    $db = SaasDatabase::getInstance();

    // Verificar si ya existe
    $existing_user = $db->fetchOne("SELECT id FROM clients WHERE email = ?", [$email]);
    if ($existing_user) {
        header('Location: index.php?message=' . urlencode('Este email ya está registrado. ¿Intentas iniciar sesión?') . '&type=error');
        exit;
    }

    // Generar credenciales
    $api_key = generateApiKey();
    $client_id = generateClientId();
    $temporary_password = generateRandomPassword(12);
    $password_hash = hashPassword($temporary_password);

    // Limpiar dominio
    $clean_website = preg_replace('/^https?:\/\//', '', $website);
    $clean_website = preg_replace('/^www\./', '', $clean_website);
    $clean_website = trim($clean_website, '/');

    // Transacción
    $db->getConnection()->beginTransaction();

    try {
        $plan_config = getSaasConfig('plans')[$selected_plan];
        $monthly_limit = $plan_config['monthly_limit'];
        $websites_limit = $plan_config['websites_limit'];

        $default_countries = 'AR,BO,CL,CO,CR,CU,DO,EC,ES,GQ,GT,HN,MX,NI,PA,PE,PY,SV,UY,VE,US,CA';

        // Insertar cliente
        $client_sql = "INSERT INTO clients (
            name, email, phone, country, company, website,
            password_hash, api_key, client_id, status, plan,
            countries_allowed, access_control_mode, monthly_limit, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, 'allowed', ?, NOW())";

        $client_params = [
            $name, $email, $phone, $country, $company, $clean_website,
            $password_hash, $api_key, $client_id, $selected_plan,
            $default_countries, $monthly_limit
        ];
        $db->query($client_sql, $client_params);
        $client_db_id = $db->lastInsertId();

        // Insertar website
        $website_sql = "INSERT INTO client_websites (
            client_id, domain, is_verified, is_active, created_at
        ) VALUES (?, ?, FALSE, TRUE, NOW())";
        $db->query($website_sql, [$client_db_id, $clean_website]);

        // Insertar lead
        $lead_sql = "INSERT INTO leads (
            name, email, phone, country, company, website, message,
            source, status, ip_address, user_agent, created_at, converted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'website', 'converted', ?, ?, NOW(), NOW())";
        $lead_params = [
            $name, $email, $phone, $country, $company, $website, $message,
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        $db->query($lead_sql, $lead_params);

        // Confirmar
        $db->getConnection()->commit();

        // 🚀 NUEVO: Enviar directo a FluentCRM (Form Data)
        $fluentcrm_route_url = 'https://rastroseguro.com/?fluentcrm=1&route=contact&hash=35fe2bec-2850-484b-97d5-58e2d228d559';

        $fluent_sent = sendToFluentCRMRouteContact($fluentcrm_route_url, [
            'email'      => $email,
            'first_name' => $name,
            'telefono'   => $phone,
            'sitio_web'  => $website,
            'plan'       => $selected_plan,
            'client_id'  => $client_id,
            'source'     => 'geocontrol_saas'
        ]);

        logActivity('info', 'FluentCRM route=contact result', [
            'email' => $email,
            'success' => $fluent_sent ? 'yes' : 'no'
        ], $client_db_id);
        // 🚀 FIN NUEVO

        // Enviar email
        $email_sent = sendWelcomeEmail($client_db_id, $name, $email, $temporary_password, $api_key, $client_id, $selected_plan);

        if ($email_sent) {
            $plan_name = getSaasConfig('plans')[$selected_plan]['name'];
            $success_message = "¡Registro exitoso! Hemos enviado tus credenciales a $email. Empiezas con el plan Gratuito. Revisa tu bandeja de entrada (y spam) para acceder a tu cuenta.";
            $redirect_url = 'login.php';
        } else {
            $success_message = "Registro exitoso. Sin embargo, hubo un problema enviando el email. Contacta a soporte.";
            $redirect_url = 'login.php';
        }

        header('Location: ' . $redirect_url . '?message=' . urlencode($success_message) . '&type=success&email=' . urlencode($email));
        exit;

    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity('error', 'Error en registro de cliente', [
        'error' => $e->getMessage(),
        'email' => $email ?? 'no_email'
    ]);
    header('Location: index.php?message=' . urlencode('Error interno del servidor. Inténtalo de nuevo.') . '&type=error');
    exit;
}

/**
 * Función para enviar email de bienvenida (ya existente)
 */
function sendWelcomeEmail($client_id, $name, $email, $password, $api_key, $client_uuid, $plan = 'free') {
    try {
        $subject = "¡Bienvenido a GeoControl SaaS! - Tus credenciales de acceso";
        $htmlBody = generateWelcomeEmailHTML($name, $email, $password, $api_key, $client_uuid, $plan);
        $textBody = "¡Bienvenido a GeoControl SaaS!\n\nHola $name,\nTu cuenta ha sido creada exitosamente.\nUsuario: $email\nContraseña temporal: $password\nAPI Key: $api_key\n\nInicia sesión en: " . SAAS_SITE_URL . "/public/login.php\n\nSaludos,\nEl equipo de GeoControl SaaS";
        $email_sent = AwsSesSmtp::send($email, $subject, $htmlBody, $textBody);
        logActivity('info', 'Email de bienvenida enviado', [
            'client_id' => $client_id,
            'email' => $email,
            'success' => $email_sent
        ]);
        return $email_sent;
    } catch (Exception $e) {
        logActivity('error', 'Error enviando email de bienvenida', [
            'client_id' => $client_id,
            'email' => $email,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Generar HTML del email de bienvenida
 */
function generateWelcomeEmailHTML($name, $email, $password, $api_key, $client_uuid, $plan = 'free') {
    $login_url = SAAS_SITE_URL . '/public/login.php';
    return "<h2>Bienvenido, $name</h2><p>Tu cuenta ha sido creada exitosamente.</p><p><b>Usuario:</b> $email<br><b>Contraseña:</b> $password<br><b>API Key:</b> $api_key</p><p><a href='$login_url'>Iniciar Sesión</a></p>";
}

/**
 * Función para enviar contacto a FluentCRM (Form Data)
 */
function sendToFluentCRMRouteContact($url, $lead) {
    if (empty($lead['email'])) return false;

    $fields = [
        'email'      => $lead['email'],
        'first_name' => $lead['first_name'] ?? ($lead['nombre'] ?? ''),
        'last_name'  => $lead['last_name'] ?? '',
        'source'     => $lead['source'] ?? 'geocontrol_saas',
        'telefono'   => $lead['telefono'] ?? '',
        'sitio_web'  => $lead['sitio_web'] ?? '',
        'plan'       => $lead['plan'] ?? '',
        'client_id'  => $lead['client_id'] ?? ''
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: GeoControl-SaaS/1.0'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    logActivity('info', 'POST a FluentCRM route=contact', [
        'url' => $url,
        'http_code' => $http_code,
        'response' => substr((string)$response, 0, 200),
        'curl_error' => $error
    ]);

    return ($http_code >= 200 && $http_code < 300);
}
