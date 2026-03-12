<?php
/**
 * Registro SIN Email - Solución Temporal
 * 
 * Esta versión muestra las credenciales en pantalla
 * para que el SaaS funcione inmediatamente
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?message=Método no permitido&type=error');
    exit;
}

try {
    // Obtener y sanitizar datos del formulario
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? '');
    $company = sanitizeInput($_POST['company'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    // Validaciones básicas
    $errors = [];

    if (empty($name)) {
        $errors[] = 'El nombre es obligatorio';
    }

    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Email válido es obligatorio';
    }

    if (empty($country)) {
        $errors[] = 'El país es obligatorio';
    }

    if (empty($website) || !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'URL del sitio web válida es obligatoria';
    }

    // Si hay errores, redirigir con mensaje
    if (!empty($errors)) {
        $error_message = implode('. ', $errors);
        header('Location: index.php?message=' . urlencode($error_message) . '&type=error');
        exit;
    }

    // Conectar a la base de datos
    $db = SaasDatabase::getInstance();

    // Verificar si el email ya existe
    $existing_user = $db->fetchOne(
        "SELECT id FROM clients WHERE email = ?", 
        [$email]
    );

    if ($existing_user) {
        header('Location: index.php?message=' . urlencode('Este email ya está registrado. ¿Intentas iniciar sesión?') . '&type=error');
        exit;
    }

    // Generar credenciales
    $api_key = generateApiKey();
    $client_id = generateClientId();
    $temporary_password = generateRandomPassword(12);
    $password_hash = hashPassword($temporary_password);

    // Limpiar y validar el sitio web
    $clean_website = preg_replace('/^https?:\/\//', '', $website);
    $clean_website = preg_replace('/^www\./', '', $clean_website);
    $clean_website = trim($clean_website, '/');

    // Iniciar transacción
    $db->getConnection()->beginTransaction();

    try {
        // Insertar cliente en la base de datos
        $client_sql = "INSERT INTO clients (
            name, email, phone, country, company, website,
            password_hash, api_key, client_id, status, plan,
            countries_allowed, access_control_mode,
            monthly_limit, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'free', ?, 'allowed', 1000, NOW())";

        // Configuración por defecto: permitir países de habla hispana + USA + Canadá
        $default_countries = 'AR,BO,CL,CO,CR,CU,DO,EC,ES,GQ,GT,HN,MX,NI,PA,PE,PY,SV,UY,VE,US,CA';

        $client_params = [
            $name,
            $email,
            $phone,
            $country,
            $company,
            $clean_website,
            $password_hash,
            $api_key,
            $client_id,
            $default_countries
        ];

        $db->query($client_sql, $client_params);
        $new_client_id = $db->lastInsertId();

        // Insertar el sitio web principal
        $website_sql = "INSERT INTO client_websites (
            client_id, domain, is_verified, is_active, created_at
        ) VALUES (?, ?, FALSE, TRUE, NOW())";

        $db->query($website_sql, [$new_client_id, $clean_website]);

        // Insertar lead en la tabla de leads (para seguimiento)
        $lead_sql = "INSERT INTO leads (
            name, email, phone, country, company, website, message,
            source, status, ip_address, user_agent, created_at, converted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'website', 'converted', ?, ?, NOW(), NOW())";

        $lead_params = [
            $name,
            $email,
            $phone,
            $country,
            $company,
            $website,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $db->query($lead_sql, $lead_params);

        // Confirmar transacción
        $db->getConnection()->commit();

        // Log de actividad exitosa
        logActivity('info', 'Nuevo cliente registrado (sin email)', [
            'client_id' => $new_client_id,
            'email' => $email,
            'country' => $country,
            'plan' => 'free'
        ], $new_client_id);

        // Redirigir a página de credenciales
        header('Location: credentials.php?id=' . base64_encode($new_client_id) . '&temp=1');
        exit;

    } catch (Exception $e) {
        // Rollback en caso de error
        $db->getConnection()->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Log del error
    logActivity('error', 'Error en registro de cliente sin email', [
        'error' => $e->getMessage(),
        'email' => $email ?? 'no_email',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // Redirigir con mensaje de error
    header('Location: index.php?message=' . urlencode('Error interno del servidor. Por favor, inténtalo de nuevo.') . '&type=error');
    exit;
}
?>