
<?php
// Página de Administración del Sistema de Control de Acceso Geográfico
session_start();

// Incluir conexión a la base de datos del SaaS
require_once __DIR__ . '/saas/config/database.php';
require_once __DIR__ . '/saas/config/aws_ses_smtp.php';
require_once __DIR__ . '/saas/config/email.php';

// Sistema de autenticación mejorado con usuario y contraseña
$admin_users = [
    'admin' => 'admin2024',
    'eduardo' => 'eduardo2024',
    'supervisor' => 'super2024'
];

// Mapeo de correos electrónicos a usuarios
$admin_emails = [
    'admin@validatumail.cc' => 'admin',
    'eduardo@validatumail.cc' => 'eduardo',
    'eduardodavila9@gmail.com' => 'eduardo',
    'supervisor@validatumail.cc' => 'supervisor'
];

$is_authenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

// Función para enviar email de recuperación de contraseña
function sendPasswordResetEmail($email, $username, $recovery_link) {
    try {
        $subject = "🔑 Recuperación de Contraseña - Panel de Administración";
        
        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Recuperación de Contraseña</title>
        </head>
        <body style='font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px;'>🔑 Recuperación de Contraseña</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Panel de Administración - ValidaTuMail.cc</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 30px;'>
                    <h2 style='color: #333; margin-bottom: 20px;'>Hola $username,</h2>
                    
                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>
                        Hemos recibido una solicitud para restablecer la contraseña de tu cuenta de administrador.
                        Si no realizaste esta solicitud, puedes ignorar este correo.
                    </p>
                    
                    <div style='background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 25px 0;'>
                        <p style='margin: 0; color: #333; font-weight: bold;'>🔒 Información de Seguridad:</p>
                        <ul style='color: #666; margin: 10px 0 0 0; padding-left: 20px;'>
                            <li>Este enlace es válido por <strong>1 hora</strong></li>
                            <li>Solo puede ser usado <strong>una vez</strong></li>
                            <li>Si no solicitaste este cambio, ignora este email</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$recovery_link'
                           style='display: inline-block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                                  color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px;
                                  font-weight: bold; font-size: 16px;'>
                            🔄 Restablecer Contraseña
                        </a>
                    </div>
                    
                    <p style='color: #999; font-size: 14px; text-align: center; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;'>
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href='$recovery_link' style='color: #667eea; word-break: break-all;'>$recovery_link</a>
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; color: #666; text-align: center; padding: 20px; font-size: 14px;'>
                    <p style='margin: 0;'>© 2024 ValidaTuMail.cc - Sistema de Control Geográfico</p>
                    <p style='margin: 5px 0 0 0;'>Este es un email automático, no responder.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $text_body = "
        Recuperación de Contraseña - Panel de Administración
        
        Hola $username,
        
        Hemos recibido una solicitud para restablecer la contraseña de tu cuenta de administrador.
        
        Para restablecer tu contraseña, haz clic en el siguiente enlace:
        $recovery_link
        
        INFORMACIÓN DE SEGURIDAD:
        - Este enlace es válido por 1 hora
        - Solo puede ser usado una vez
        - Si no solicitaste este cambio, ignora este email
        
        © 2024 ValidaTuMail.cc - Sistema de Control Geográfico
        ";
        
        return AwsSesMailer::send($email, $subject, $html_body, $text_body);
        
    } catch (Exception $e) {
        error_log("Error enviando email de recuperación: " . $e->getMessage());
        return false;
    }
}

// Manejar petición de recuperación de contraseña
if (isset($_POST['forgot_password'])) {
    $user_input = $_POST['forgot_username'] ?? '';
    $username = null;
    $user_email = null;
    
    // Verificar si es un nombre de usuario directo
    if (isset($admin_users[$user_input])) {
        $username = $user_input;
        // Buscar el email correspondiente
        foreach ($admin_emails as $email => $user) {
            if ($user === $username) {
                $user_email = $email;
                break;
            }
        }
    }
    // Verificar si es un correo electrónico
    elseif (isset($admin_emails[$user_input])) {
        $username = $admin_emails[$user_input];
        $user_email = $user_input;
    }
    // Verificar si el input es un correo que coincide con algún usuario
    else {
        foreach ($admin_emails as $email => $user) {
            if (strtolower($user_input) === strtolower($email)) {
                $username = $user;
                $user_email = $email;
                break;
            }
        }
    }
    
    if ($username && $user_email) {
        // Generar token de recuperación
        $recovery_token = bin2hex(random_bytes(32));
        $_SESSION['recovery_token'] = $recovery_token;
        $_SESSION['recovery_username'] = $username;
        $_SESSION['recovery_email'] = $user_email;
        $_SESSION['recovery_expires'] = time() + 3600; // 1 hora
        
        // Crear enlace de recuperación
        $recovery_link = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?action=reset_password&token=" . $recovery_token;
        
        // Enviar email de recuperación
        $email_sent = sendPasswordResetEmail($user_email, $username, $recovery_link);
        
        if ($email_sent) {
            $recovery_message = "Se ha enviado un enlace de recuperación al correo: " . $user_email;
        } else {
            $recovery_error = 'Error al enviar el correo de recuperación. Inténtelo más tarde.';
        }
    } else {
        $recovery_error = 'Usuario o correo electrónico no encontrado';
    }
}

// Manejar enlace de recuperación desde email
if (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['token'])) {
    $url_token = $_GET['token'];
    
    // Verificar si el token en la URL coincide con el de la sesión
    if (isset($_SESSION['recovery_token']) &&
        isset($_SESSION['recovery_expires']) &&
        $_SESSION['recovery_expires'] > time() &&
        hash_equals($_SESSION['recovery_token'], $url_token)) {
        
        $show_reset_form = true;
        $valid_token = $url_token;
    } else {
        $token_error = 'El enlace de recuperación es inválido o ha expirado. Solicite uno nuevo.';
    }
}

// Manejar cambio de contraseña
if (isset($_POST['reset_password'])) {
    $token = $_POST['recovery_token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (isset($_SESSION['recovery_token']) &&
        isset($_SESSION['recovery_expires']) &&
        $_SESSION['recovery_expires'] > time() &&
        hash_equals($_SESSION['recovery_token'], $token)) {
        
        if ($new_password === $confirm_password && strlen($new_password) >= 6) {
            $username = $_SESSION['recovery_username'];
            
            // Actualizar contraseña en el array (en producción, esto debería ir a una base de datos)
            $admin_users[$username] = $new_password;
            
            // Guardar la nueva contraseña en un archivo temporal (para persistencia)
            $password_file = __DIR__ . '/.admin_passwords';
            file_put_contents($password_file, serialize($admin_users));
            
            // Limpiar sesión de recuperación
            unset($_SESSION['recovery_token']);
            unset($_SESSION['recovery_username']);
            unset($_SESSION['recovery_expires']);
            
            $reset_success = 'Contraseña actualizada correctamente. Puede iniciar sesión con su nueva contraseña.';
        } else {
            $reset_error = 'Las contraseñas no coinciden o son muy cortas (mínimo 6 caracteres)';
        }
    } else {
        $reset_error = 'Token de recuperación inválido o expirado';
    }
}

// Cargar contraseñas guardadas si existen
$password_file = __DIR__ . '/.admin_passwords';
if (file_exists($password_file)) {
    $saved_passwords = unserialize(file_get_contents($password_file));
    if ($saved_passwords && is_array($saved_passwords)) {
        $admin_users = array_merge($admin_users, $saved_passwords);
    }
}

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($admin_users[$username]) && $admin_users[$username] === $password) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        $is_authenticated = true;
    } else {
        $login_error = 'Usuario o contraseña incorrectos';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Cargar configuración actual del .env
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

// Funciones para administrar clientes del SaaS
function getAllClients() {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Verificar qué columnas existen
        $serviceColumnsExist = checkServiceColumns();
        $paymentUrlExists = checkPaymentUrlColumn();
        
        // Construir consulta base
        $baseColumns = "id, name, email, phone, country, company, website, plan, monthly_usage, monthly_limit, status, created_at, client_id, api_key";
        
        // Agregar columnas según existan
        if ($paymentUrlExists) {
            $baseColumns .= ", COALESCE(payment_url, 'https://tu-sitio-pagos.com/upgrade') as payment_url";
        } else {
            $baseColumns .= ", 'https://tu-sitio-pagos.com/upgrade' as payment_url";
        }
        
        if ($serviceColumnsExist) {
            $baseColumns .= ", COALESCE(email_verification_enabled, 1) as email_verification_enabled";
            $baseColumns .= ", COALESCE(geo_blocking_enabled, 1) as geo_blocking_enabled";
        } else {
            $baseColumns .= ", 1 as email_verification_enabled";
            $baseColumns .= ", 1 as geo_blocking_enabled";
        }
        
        $query = "SELECT {$baseColumns} FROM clients ORDER BY created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log para verificar si se obtienen datos
        error_log("getAllClients() - Clientes encontrados: " . count($result) . " (Services: " . ($serviceColumnsExist ? 'YES' : 'NO') . ", Payment: " . ($paymentUrlExists ? 'YES' : 'NO') . ")");
        
        return $result;
    } catch (Exception $e) {
        error_log("Error en getAllClients(): " . $e->getMessage());
        return [];
    }
}

// Función para verificar si existen las columnas de servicios
function checkServiceColumns() {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        $query = "SHOW COLUMNS FROM clients LIKE '%verification%'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($columns);
    } catch (Exception $e) {
        return false;
    }
}

// Función para verificar si existe la columna payment_url
function checkPaymentUrlColumn() {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        $query = "SHOW COLUMNS FROM clients LIKE 'payment_url'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($columns);
    } catch (Exception $e) {
        return false;
    }
}

// Función para obtener detalles completos de un cliente
function getClientDetails($client_id) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Obtener datos del cliente
        $query = "SELECT * FROM clients WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            return null;
        }
        
        // Obtener websites del cliente
        $websitesQuery = "SELECT domain, is_verified, is_active FROM client_websites WHERE client_id = ? ORDER BY created_at DESC";
        $websitesStmt = $db->prepare($websitesQuery);
        $websitesStmt->execute([$client_id]);
        $websites = $websitesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener estadísticas de uso reciente
        $statsQuery = "SELECT COUNT(*) as total_requests FROM api_requests WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $statsStmt = $db->prepare($statsQuery);
        $statsStmt->execute([$client_id]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'client' => $client,
            'websites' => $websites,
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        error_log("Error en getClientDetails(): " . $e->getMessage());
        return null;
    }
}

function updateClientServices($client_id, $email_verification_enabled, $geo_blocking_enabled) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Primero verificar si las columnas existen
        $checkQuery = "SHOW COLUMNS FROM clients LIKE '%verification%'";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute();
        $columns = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($columns)) {
            // Las columnas no existen, crear query sin ellas
            error_log("Columnas de servicios no existen en la tabla clients");
            return false;
        }
        
        $query = "UPDATE clients SET
                  email_verification_enabled = ?,
                  geo_blocking_enabled = ?,
                  updated_at = NOW()
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        return $stmt->execute([$email_verification_enabled, $geo_blocking_enabled, $client_id]);
    } catch (Exception $e) {
        error_log("Error en updateClientServices(): " . $e->getMessage());
        return false;
    }
}

// Función para actualizar el plan de un cliente
function updateClientPlan($client_id, $new_plan) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Definir límites según el plan
        $plan_limits = [
            'free' => 1000,
            'basic' => 10000,
            'premium' => 100000,
            'enterprise' => -1
        ];
        
        $monthly_limit = $plan_limits[$new_plan] ?? 1000;
        
        $query = "UPDATE clients SET
                  plan = ?,
                  monthly_limit = ?,
                  updated_at = NOW()
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $success = $stmt->execute([$new_plan, $monthly_limit, $client_id]);
        
        if ($success) {
            // Obtener el client_id del cliente para buscar sesiones activas
            $clientQuery = "SELECT client_id FROM clients WHERE id = ?";
            $clientStmt = $db->prepare($clientQuery);
            $clientStmt->execute([$client_id]);
            $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientData) {
                // Log para debug
                error_log("Plan actualizado para cliente ID: $client_id, Client ID: {$clientData['client_id']}, Nuevo plan: $new_plan");
            }
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Error en updateClientPlan(): " . $e->getMessage());
        return false;
    }
}

// Función para actualizar URL de pago de un cliente
function updateClientPaymentUrl($client_id, $payment_url) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Primero verificar si la columna payment_url existe
        if (!checkPaymentUrlColumn()) {
            error_log("Columna payment_url no existe en la tabla clients");
            return false;
        }
        
        $query = "UPDATE clients SET
                  payment_url = ?,
                  updated_at = NOW()
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $success = $stmt->execute([$payment_url, $client_id]);
        
        if ($success) {
            error_log("URL de pago actualizada para cliente ID: $client_id, Nueva URL: $payment_url");
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Error en updateClientPaymentUrl(): " . $e->getMessage());
        return false;
    }
}

// Función para verificar si un cliente es administrador (no se puede eliminar)
function isAdminClient($client_id) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Obtener datos del cliente
        $query = "SELECT email, name, plan FROM clients WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            return false;
        }
        
        // Lista de emails de administradores que no se pueden eliminar
        $admin_emails = [
            'admin@validatumail.cc',
            'eduardo@validatumail.cc',
            'eduardodavila9@gmail.com',
            'supervisor@validatumail.cc'
        ];
        
        // Lista de nombres de administradores que no se pueden eliminar
        $admin_names = [
            'Administrador SaaS',
            'Eduardo Davila',
            'Admin Sistema',
            'Supervisor'
        ];
        
        // Verificar si es email de administrador
        if (in_array(strtolower($client['email']), array_map('strtolower', $admin_emails))) {
            return true;
        }
        
        // Verificar si es nombre de administrador
        if (in_array($client['name'], $admin_names)) {
            return true;
        }
        
        // Verificar si tiene plan enterprise y email de admin
        if ($client['plan'] === 'enterprise' && strpos(strtolower($client['email']), 'admin') !== false) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error en isAdminClient(): " . $e->getMessage());
        return true; // En caso de error, asumir que es admin por seguridad
    }
}

// Función para eliminar un cliente
function deleteClient($client_id) {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Verificar si es un cliente administrador
        if (isAdminClient($client_id)) {
            error_log("INTENTO DE ELIMINAR ADMIN BLOQUEADO - Client ID: $client_id");
            return false;
        }
        
        // Iniciar transacción para eliminar todos los datos relacionados
        $db->beginTransaction();
        
        // Eliminar sitios web del cliente
        $websitesQuery = "DELETE FROM client_websites WHERE client_id = ?";
        $websitesStmt = $db->prepare($websitesQuery);
        $websitesStmt->execute([$client_id]);
        
        // Eliminar requests del cliente
        $requestsQuery = "DELETE FROM api_requests WHERE client_id = ?";
        $requestsStmt = $db->prepare($requestsQuery);
        $requestsStmt->execute([$client_id]);
        
        // Eliminar el cliente
        $clientQuery = "DELETE FROM clients WHERE id = ?";
        $clientStmt = $db->prepare($clientQuery);
        $success = $clientStmt->execute([$client_id]);
        
        if ($success) {
            $db->commit();
            error_log("Cliente eliminado exitosamente - ID: $client_id");
            return true;
        } else {
            $db->rollback();
            return false;
        }
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error en deleteClient(): " . $e->getMessage());
        return false;
    }
}

// Función para debug de conexión a la base de datos
function debugDatabaseConnection() {
    try {
        $db = SaasDatabase::getInstance()->getConnection();
        
        // Probar conexión básica
        $query = "SELECT COUNT(*) as total FROM clients";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar estructura de la tabla
        $structQuery = "DESCRIBE clients";
        $structStmt = $db->prepare($structQuery);
        $structStmt->execute();
        $structure = $structStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total_clients' => $result['total'],
            'connection_ok' => true,
            'table_structure' => $structure
        ];
    } catch (Exception $e) {
        return [
            'connection_ok' => false,
            'error' => $e->getMessage()
        ];
    }
}

$config = loadEnvConfig();

// Manejar petición AJAX para detalles del cliente
if (isset($_GET['action']) && $_GET['action'] === 'get_client_details') {
    $client_id = $_GET['client_id'] ?? 0;
    $details = getClientDetails($client_id);
    
    if ($details) {
        $client = $details['client'];
        $websites = $details['websites'];
        $stats = $details['stats'];
        
        // Generar HTML de respuesta
        echo '<div style="padding: 0;">';
        
        // Información básica del cliente
        echo '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px;">';
        echo '<h3 style="margin: 0 0 15px 0; color: #333; display: flex; align-items: center; gap: 10px;">';
        echo '<span style="font-size: 24px;">👤</span> ' . htmlspecialchars($client['name']);
        echo '</h3>';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">';
        echo '<div><strong>📧 Email:</strong> ' . htmlspecialchars($client['email']) . '</div>';
        echo '<div><strong>📞 Teléfono:</strong> ' . htmlspecialchars($client['phone'] ?? 'No especificado') . '</div>';
        echo '<div><strong>🏢 Empresa:</strong> ' . htmlspecialchars($client['company'] ?? 'No especificada') . '</div>';
        echo '<div><strong>🌐 Sitio Web:</strong> ' . htmlspecialchars($client['website'] ?? 'No especificado') . '</div>';
        echo '<div><strong>📊 Plan:</strong> <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">' . strtoupper($client['plan']) . '</span></div>';
        echo '<div><strong>📈 Estado:</strong> <span style="color: ' . ($client['status'] === 'active' ? 'green' : 'orange') . ';">' . ucfirst($client['status']) . '</span></div>';
        echo '<div><strong>📅 Registrado:</strong> ' . date('d/m/Y', strtotime($client['created_at'])) . '</div>';
        echo '<div><strong>🔑 Client ID:</strong> <code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px;">' . htmlspecialchars($client['client_id']) . '</code></div>';
        echo '</div>';
        echo '</div>';
        
        // Configuración de países
        echo '<div style="background: #fff3cd; padding: 20px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #ffeaa7;">';
        echo '<h4 style="margin: 0 0 15px 0; color: #856404; display: flex; align-items: center; gap: 10px;">';
        echo '<span style="font-size: 20px;">🌍</span> Configuración de Países';
        echo '</h4>';
        
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">';
        
        // Modo de control
        echo '<div>';
        echo '<strong>🎯 Modo de Control:</strong><br>';
        echo '<span style="background: ' . ($client['access_control_mode'] === 'allowed' ? '#d4edda' : '#f8d7da') . '; ';
        echo 'color: ' . ($client['access_control_mode'] === 'allowed' ? '#155724' : '#721c24') . '; ';
        echo 'padding: 4px 8px; border-radius: 4px; font-size: 12px;">';
        echo $client['access_control_mode'] === 'allowed' ? '✅ SOLO PERMITIDOS' : '❌ BLOQUEAR ESPECÍFICOS';
        echo '</span>';
        echo '</div>';
        
        // Acción de bloqueo
        echo '<div>';
        echo '<strong>🚫 Acción de Bloqueo:</strong><br>';
        echo '<span style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 12px;">';
        echo $client['access_denied_action'] === 'block_forms' ? '📝 Bloquear Formularios' :
            ($client['access_denied_action'] === 'block_site' ? '🚫 Bloquear Sitio' : '🔄 Redirigir');
        echo '</span>';
        echo '</div>';
        echo '</div>';
        
        // Países permitidos/bloqueados
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
        
        if ($client['countries_allowed']) {
            echo '<div>';
            echo '<strong style="color: #28a745;">✅ Países Permitidos:</strong><br>';
            echo '<div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 5px; font-family: monospace; font-size: 12px;">';
            echo htmlspecialchars($client['countries_allowed']);
            echo '</div>';
            echo '</div>';
        }
        
        if ($client['countries_denied']) {
            echo '<div>';
            echo '<strong style="color: #dc3545;">❌ Países Bloqueados:</strong><br>';
            echo '<div style="background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 5px; font-family: monospace; font-size: 12px;">';
            echo htmlspecialchars($client['countries_denied']);
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        
        // Configuración de privacidad
        echo '<div style="background: #d1ecf1; padding: 20px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #bee5eb;">';
        echo '<h4 style="margin: 0 0 15px 0; color: #0c5460; display: flex; align-items: center; gap: 10px;">';
        echo '<span style="font-size: 20px;">🛡️</span> Control de Privacidad';
        echo '</h4>';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">';
        $privacy_settings = [
            'allow_vpn' => '🔒 VPN',
            'allow_tor' => '🧅 Tor',
            'allow_proxy' => '🌐 Proxy',
            'allow_hosting' => '🏢 Hosting'
        ];
        
        foreach ($privacy_settings as $setting => $label) {
            $allowed = $client[$setting] ? true : false;
            echo '<div style="text-align: center; padding: 10px; background: white; border-radius: 6px;">';
            echo '<div style="font-size: 24px; margin-bottom: 5px;">' . ($allowed ? '✅' : '❌') . '</div>';
            echo '<div style="font-size: 12px; font-weight: bold;">' . $label . '</div>';
            echo '<div style="font-size: 11px; color: #666;">' . ($allowed ? 'Permitido' : 'Bloqueado') . '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        
        // Estadísticas de uso
        echo '<div style="background: #e7f3ff; padding: 20px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #b8daff;">';
        echo '<h4 style="margin: 0 0 15px 0; color: #004085; display: flex; align-items: center; gap: 10px;">';
        echo '<span style="font-size: 20px;">📊</span> Estadísticas de Uso';
        echo '</h4>';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
        echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #667eea;">' . number_format($client['monthly_usage'] ?? 0) . '</div>';
        echo '<div style="font-size: 12px; color: #666;">Uso Este Mes</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #28a745;">' . ($client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit'])) . '</div>';
        echo '<div style="font-size: 12px; color: #666;">Límite Mensual</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #fd7e14;">' . number_format($stats['total_requests'] ?? 0) . '</div>';
        echo '<div style="font-size: 12px; color: #666;">Últimos 30 días</div>';
        echo '</div>';
        
        // Porcentaje de uso
        $usage_percentage = 0;
        if ($client['monthly_limit'] > 0) {
            $usage_percentage = round(($client['monthly_usage'] / $client['monthly_limit']) * 100, 1);
        }
        echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: ' . ($usage_percentage > 80 ? '#dc3545' : '#17a2b8') . ';">' . $usage_percentage . '%</div>';
        echo '<div style="font-size: 12px; color: #666;">% Usado</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Sitios web asociados
        if (!empty($websites)) {
            echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
            echo '<h4 style="margin: 0 0 15px 0; color: #333; display: flex; align-items: center; gap: 10px;">';
            echo '<span style="font-size: 20px;">🌐</span> Sitios Web Asociados (' . count($websites) . ')';
            echo '</h4>';
            
            foreach ($websites as $website) {
                echo '<div style="background: white; padding: 10px 15px; margin-bottom: 10px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">';
                echo '<div>';
                echo '<strong>' . htmlspecialchars($website['domain']) . '</strong>';
                echo '</div>';
                echo '<div style="display: flex; gap: 10px; align-items: center;">';
                echo '<span style="font-size: 12px; padding: 2px 6px; border-radius: 12px; background: ' .
                    ($website['is_verified'] ? '#d4edda; color: #155724' : '#fff3cd; color: #856404') . ';">';
                echo $website['is_verified'] ? '✅ Verificado' : '⏳ Pendiente';
                echo '</span>';
                echo '<span style="font-size: 12px; padding: 2px 6px; border-radius: 12px; background: ' .
                    ($website['is_active'] ? '#d4edda; color: #155724' : '#f8d7da; color: #721c24') . ';">';
                echo $website['is_active'] ? '🟢 Activo' : '🔴 Inactivo';
                echo '</span>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<div style="text-align: center; padding: 40px; color: #dc3545;">❌ No se pudieron cargar los detalles del cliente</div>';
    }
    exit;
}

// Procesar acciones de administración de clientes
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'update_client_services') {
        $client_id = $_POST['client_id'] ?? 0;
        $email_enabled = isset($_POST['email_verification_enabled']) ? 1 : 0;
        $geo_enabled = isset($_POST['geo_blocking_enabled']) ? 1 : 0;
        
        if (updateClientServices($client_id, $email_enabled, $geo_enabled)) {
            $_SESSION['admin_message'] = 'Servicios del cliente actualizados correctamente';
            $_SESSION['admin_message_type'] = 'success';
        } else {
            $_SESSION['admin_message'] = 'Error al actualizar servicios del cliente';
            $_SESSION['admin_message_type'] = 'error';
        }
        
        header('Location: admin.php#clients');
        exit;
    }
    
    if ($_POST['action'] === 'change_client_plan') {
        $client_id = $_POST['client_id'] ?? 0;
        $new_plan = $_POST['new_plan'] ?? '';
        
        if (updateClientPlan($client_id, $new_plan)) {
            $_SESSION['admin_message'] = 'Plan del cliente actualizado correctamente a ' . strtoupper($new_plan);
            $_SESSION['admin_message_type'] = 'success';
        } else {
            $_SESSION['admin_message'] = 'Error al actualizar el plan del cliente';
            $_SESSION['admin_message_type'] = 'error';
        }
        
        header('Location: admin.php#clients');
        exit;
    }
    
    if ($_POST['action'] === 'update_payment_url') {
        $client_id = $_POST['client_id'] ?? 0;
        $payment_url = $_POST['payment_url'] ?? '';
        
        // Debug logging
        error_log("DEBUG: Intentando actualizar URL de pago - Client ID: $client_id, URL: $payment_url");
        
        // Verificar si la columna existe
        if (!checkPaymentUrlColumn()) {
            // Si no existe, intentar crearla
            try {
                $db = SaasDatabase::getInstance()->getConnection();
                $query = "ALTER TABLE clients ADD COLUMN payment_url VARCHAR(500) DEFAULT 'https://tu-sitio-pagos.com/upgrade'";
                $db->exec($query);
                error_log("DEBUG: Columna payment_url creada exitosamente");
            } catch (Exception $e) {
                error_log("DEBUG: Error creando columna payment_url: " . $e->getMessage());
                $_SESSION['admin_message'] = 'Error: La columna payment_url no existe. Ejecuta el script SQL: saas/install/add_payment_url_column.sql';
                $_SESSION['admin_message_type'] = 'error';
                header('Location: admin.php#clients');
                exit;
            }
        }
        
        if (updateClientPaymentUrl($client_id, $payment_url)) {
            $_SESSION['admin_message'] = 'URL de pago actualizada correctamente para cliente ID: ' . $client_id;
            $_SESSION['admin_message_type'] = 'success';
            error_log("DEBUG: URL de pago actualizada exitosamente");
        } else {
            $_SESSION['admin_message'] = 'Error al actualizar la URL de pago. Revisa los logs para más detalles.';
            $_SESSION['admin_message_type'] = 'error';
            error_log("DEBUG: Error en updateClientPaymentUrl()");
        }
        
        header('Location: admin.php#clients');
        exit;
    }
    
    if ($_POST['action'] === 'delete_client') {
        $client_id = $_POST['client_id'] ?? 0;
        
        if ($client_id > 0) {
            // Verificar si es un cliente administrador antes de eliminar
            if (isAdminClient($client_id)) {
                $_SESSION['admin_message'] = 'ERROR: No se puede eliminar una cuenta de administrador del sistema SaaS';
                $_SESSION['admin_message_type'] = 'error';
            } else {
                if (deleteClient($client_id)) {
                    $_SESSION['admin_message'] = 'Cliente eliminado correctamente (ID: ' . $client_id . ')';
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    $_SESSION['admin_message'] = 'Error al eliminar el cliente. Revisa los logs para más detalles.';
                    $_SESSION['admin_message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['admin_message'] = 'ID de cliente inválido';
            $_SESSION['admin_message_type'] = 'error';
        }
        
        header('Location: admin.php#clients');
        exit;
    }
}

// Manejar mensajes de notificación
$message = '';
$messageType = '';
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    $messageType = $_SESSION['admin_message_type'] ?? 'success';
    unset($_SESSION['admin_message']);
    unset($_SESSION['admin_message_type']);
}

// Lista de todos los países disponibles
$all_countries = [
    'AD' => 'Andorra', 'AE' => 'Emiratos Árabes Unidos', 'AF' => 'Afganistán', 'AG' => 'Antigua y Barbuda',
    'AI' => 'Anguila', 'AL' => 'Albania', 'AM' => 'Armenia', 'AO' => 'Angola', 'AQ' => 'Antártida',
    'AR' => 'Argentina', 'AS' => 'Samoa Americana', 'AT' => 'Austria', 'AU' => 'Australia', 'AW' => 'Aruba',
    'AX' => 'Islas Åland', 'AZ' => 'Azerbaiyán', 'BA' => 'Bosnia y Herzegovina', 'BB' => 'Barbados',
    'BD' => 'Bangladesh', 'BE' => 'Bélgica', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Baréin',
    'BI' => 'Burundi', 'BJ' => 'Benín', 'BL' => 'San Bartolomé', 'BM' => 'Bermudas', 'BN' => 'Brunéi',
    'BO' => 'Bolivia', 'BQ' => 'Bonaire', 'BR' => 'Brasil', 'BS' => 'Bahamas', 'BT' => 'Bután',
    'BV' => 'Isla Bouvet', 'BW' => 'Botsuana', 'BY' => 'Bielorrusia', 'BZ' => 'Belice', 'CA' => 'Canadá',
    'CC' => 'Islas Cocos', 'CD' => 'República Democrática del Congo', 'CF' => 'República Centroafricana',
    'CG' => 'República del Congo', 'CH' => 'Suiza', 'CI' => 'Costa de Marfil', 'CK' => 'Islas Cook',
    'CL' => 'Chile', 'CM' => 'Camerún', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica',
    'CU' => 'Cuba', 'CV' => 'Cabo Verde', 'CW' => 'Curazao', 'CX' => 'Isla de Navidad', 'CY' => 'Chipre',
    'CZ' => 'República Checa', 'DE' => 'Alemania', 'DJ' => 'Yibuti', 'DK' => 'Dinamarca', 'DM' => 'Dominica',
    'DO' => 'República Dominicana', 'DZ' => 'Argelia', 'EC' => 'Ecuador', 'EE' => 'Estonia', 'EG' => 'Egipto',
    'EH' => 'Sáhara Occidental', 'ER' => 'Eritrea', 'ES' => 'España', 'ET' => 'Etiopía', 'FI' => 'Finlandia',
    'FJ' => 'Fiyi', 'FK' => 'Islas Malvinas', 'FM' => 'Micronesia', 'FO' => 'Islas Feroe', 'FR' => 'Francia',
    'GA' => 'Gabón', 'GB' => 'Reino Unido', 'GD' => 'Granada', 'GE' => 'Georgia', 'GF' => 'Guayana Francesa',
    'GG' => 'Guernsey', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GL' => 'Groenlandia', 'GM' => 'Gambia',
    'GN' => 'Guinea', 'GP' => 'Guadalupe', 'GQ' => 'Guinea Ecuatorial', 'GR' => 'Grecia', 'GS' => 'Islas Georgias del Sur y Sandwich del Sur',
    'GT' => 'Guatemala', 'GU' => 'Guam', 'GW' => 'Guinea-Bisáu', 'GY' => 'Guyana', 'HK' => 'Hong Kong',
    'HM' => 'Islas Heard y McDonald', 'HN' => 'Honduras', 'HR' => 'Croacia', 'HT' => 'Haití', 'HU' => 'Hungría',
    'ID' => 'Indonesia', 'IE' => 'Irlanda', 'IL' => 'Israel', 'IM' => 'Isla de Man', 'IN' => 'India',
    'IO' => 'Territorio Británico del Océano Índico', 'IQ' => 'Irak', 'IR' => 'Irán', 'IS' => 'Islandia',
    'IT' => 'Italia', 'JE' => 'Jersey', 'JM' => 'Jamaica', 'JO' => 'Jordania', 'JP' => 'Japón',
    'KE' => 'Kenia', 'KG' => 'Kirguistán', 'KH' => 'Camboya', 'KI' => 'Kiribati', 'KM' => 'Comoras',
    'KN' => 'San Cristóbal y Nieves', 'KP' => 'Corea del Norte', 'KR' => 'Corea del Sur', 'KW' => 'Kuwait',
    'KY' => 'Islas Caimán', 'KZ' => 'Kazajistán', 'LA' => 'Laos', 'LB' => 'Líbano', 'LC' => 'Santa Lucía',
    'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia', 'LS' => 'Lesoto', 'LT' => 'Lituania',
    'LU' => 'Luxemburgo', 'LV' => 'Letonia', 'LY' => 'Libia', 'MA' => 'Marruecos', 'MC' => 'Mónaco',
    'MD' => 'Moldavia', 'ME' => 'Montenegro', 'MF' => 'San Martín', 'MG' => 'Madagascar', 'MH' => 'Islas Marshall',
    'MK' => 'Macedonia del Norte', 'ML' => 'Malí', 'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao',
    'MP' => 'Islas Marianas del Norte', 'MQ' => 'Martinica', 'MR' => 'Mauritania', 'MS' => 'Montserrat',
    'MT' => 'Malta', 'MU' => 'Mauricio', 'MV' => 'Maldivas', 'MW' => 'Malaui', 'MX' => 'México',
    'MY' => 'Malasia', 'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NC' => 'Nueva Caledonia', 'NE' => 'Níger',
    'NF' => 'Isla Norfolk', 'NG' => 'Nigeria', 'NI' => 'Nicaragua', 'NL' => 'Países Bajos', 'NO' => 'Noruega',
    'NP' => 'Nepal', 'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'Nueva Zelanda', 'OM' => 'Omán',
    'PA' => 'Panamá', 'PE' => 'Perú', 'PF' => 'Polinesia Francesa', 'PG' => 'Papúa Nueva Guinea',
    'PH' => 'Filipinas', 'PK' => 'Pakistán', 'PL' => 'Polonia', 'PM' => 'San Pedro y Miquelón',
    'PN' => 'Islas Pitcairn', 'PR' => 'Puerto Rico', 'PS' => 'Palestina', 'PT' => 'Portugal',
    'PW' => 'Palaos', 'PY' => 'Paraguay', 'QA' => 'Catar', 'RE' => 'Reunión', 'RO' => 'Rumania',
    'RS' => 'Serbia', 'RU' => 'Rusia', 'RW' => 'Ruanda', 'SA' => 'Arabia Saudí', 'SB' => 'Islas Salomón',
    'SC' => 'Seychelles', 'SD' => 'Sudán', 'SE' => 'Suecia', 'SG' => 'Singapur', 'SH' => 'Santa Elena',
    'SI' => 'Eslovenia', 'SJ' => 'Svalbard y Jan Mayen', 'SK' => 'Eslovaquia', 'SL' => 'Sierra Leona',
    'SM' => 'San Marino', 'SN' => 'Senegal', 'SO' => 'Somalia', 'SR' => 'Surinam', 'SS' => 'Sudán del Sur',
    'ST' => 'Santo Tomé y Príncipe', 'SV' => 'El Salvador', 'SX' => 'San Martín', 'SY' => 'Siria',
    'SZ' => 'Esuatini', 'TC' => 'Islas Turcas y Caicos', 'TD' => 'Chad', 'TF' => 'Territorios Australes Franceses',
    'TG' => 'Togo', 'TH' => 'Tailandia', 'TJ' => 'Tayikistán', 'TK' => 'Tokelau', 'TL' => 'Timor Oriental',
    'TM' => 'Turkmenistán', 'TN' => 'Túnez', 'TO' => 'Tonga', 'TR' => 'Turquía', 'TT' => 'Trinidad y Tobago',
    'TV' => 'Tuvalu', 'TW' => 'Taiwán', 'TZ' => 'Tanzania', 'UA' => 'Ucrania', 'UG' => 'Uganda',
    'UM' => 'Islas Ultramarinas de Estados Unidos', 'US' => 'Estados Unidos', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistán',
    'VA' => 'Ciudad del Vaticano', 'VC' => 'San Vicente y las Granadinas', 'VE' => 'Venezuela', 'VG' => 'Islas Vírgenes Británicas',
    'VI' => 'Islas Vírgenes de los Estados Unidos', 'VN' => 'Vietnam', 'VU' => 'Vanuatu', 'WF' => 'Wallis y Futuna',
    'WS' => 'Samoa', 'YE' => 'Yemen', 'YT' => 'Mayotte', 'ZA' => 'Sudáfrica', 'ZM' => 'Zambia', 'ZW' => 'Zimbabue'
];

// Si hay un token válido desde el enlace de email, mostrar formulario de cambio de contraseña
if (isset($show_reset_form) && $show_reset_form) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Sistema de Control Geográfico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .reset-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .reset-container h1 { color: #333; margin-bottom: 20px; font-size: 24px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .reset-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .reset-btn:hover { transform: translateY(-2px); }
        .success { color: #28a745; margin-top: 15px; padding: 10px; background: #d4edda; border-radius: 5px; }
        .error { color: #e74c3c; margin-top: 15px; padding: 10px; background: #ffeaea; border-radius: 5px; }
        .info { color: #0c5460; margin-bottom: 20px; padding: 15px; background: #d1ecf1; border-radius: 5px; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1>🔄 Cambiar Contraseña</h1>
        
        <div class="info">
            <strong>🔒 Restablecimiento de Contraseña</strong><br>
            Ingrese su nueva contraseña a continuación.
        </div>
        
        <form method="POST">
            <input type="hidden" name="recovery_token" value="<?php echo htmlspecialchars($valid_token); ?>">
            
            <div class="form-group">
                <label for="new_password">Nueva Contraseña:</label>
                <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Mínimo 6 caracteres">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Repita la nueva contraseña">
            </div>
            
            <button type="submit" name="reset_password" class="reset-btn">🔄 Cambiar Contraseña</button>
            
            <?php if (isset($reset_error)): ?>
                <div class="error"><?php echo $reset_error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($reset_success)): ?>
                <div class="success">
                    <?php echo $reset_success; ?>
                    <br><br>
                    <a href="admin.php" style="color: #28a745; text-decoration: none; font-weight: bold;">👉 Ir al Login</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
<?php
exit;
}

if (!$is_authenticated) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Control Geográfico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-container h1 { color: #333; margin-bottom: 30px; font-size: 24px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .login-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .login-btn:hover { transform: translateY(-2px); }
        .error { color: #e74c3c; margin-top: 15px; padding: 10px; background: #ffeaea; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Control de Acceso</h1>
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required placeholder="admin, eduardo, supervisor">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Ingresar</button>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="#" onclick="showForgotPassword()" style="color: #667eea; text-decoration: none; font-size: 14px;">
                    🔑 ¿Olvidaste tu contraseña?
                </a>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($token_error)): ?>
                <div class="error"><?php echo $token_error; ?></div>
            <?php endif; ?>
        </form>
        
        <!-- Modal de recuperación de contraseña -->
        <div id="forgotPasswordModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div style="background-color: white; margin: 10% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 400px; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; text-align: center;">
                    <h2 style="margin: 0; font-size: 20px;">📧 Recuperar Contraseña</h2>
                </div>
                
                <div style="padding: 20px;">
                    <form method="POST" style="margin: 0;">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #555;">Usuario o Correo Electrónico:</label>
                            <input type="text" name="forgot_username" required
                                   placeholder="admin, eduardo, supervisor o admin@validatumail.cc"
                                   style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 5px;">
                            <small style="color: #666; display: block; margin-top: 5px;">
                                📧 Se enviará un enlace de recuperación a su correo electrónico
                            </small>
                        </div>
                        
                        <button type="submit" name="forgot_password"
                                style="width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            📤 Enviar Enlace de Recuperación
                        </button>
                        
                        <?php if (isset($recovery_error)): ?>
                            <div style="color: #e74c3c; margin-top: 10px; text-align: center;"><?php echo $recovery_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($recovery_message)): ?>
                            <div style="color: #28a745; margin-top: 10px; text-align: center; background: #d4edda; padding: 10px; border-radius: 5px;">
                                ✅ <?php echo $recovery_message; ?>
                                <br><small>Revise su bandeja de entrada y spam.</small>
                            </div>
                        <?php endif; ?>
                    </form>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button onclick="closeForgotPassword()"
                                style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'block';
        }
        
        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('forgotPasswordModal');
            if (event.target == modal) {
                closeForgotPassword();
            }
        }
        
        // Auto-mostrar modal si hay mensajes de recuperación
        <?php if (isset($recovery_message) || isset($recovery_error) || isset($reset_error) || isset($reset_success)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showForgotPassword();
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php
exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Control Geográfico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 28px; font-weight: 600; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background 0.3s; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .tabs { display: flex; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .tab { flex: 1; padding: 15px 20px; background: white; border: none; cursor: pointer; font-size: 16px; font-weight: 500; transition: all 0.3s; border-bottom: 3px solid transparent; }
        .tab.active { background: #f8f9fa; border-bottom-color: #667eea; color: #667eea; }
        .tab:hover { background: #f8f9fa; }
        .tab-content { display: none; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tab-content.active { display: block; }
        .section-title { font-size: 24px; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e1e5e9; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .save-btn { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .save-btn:hover { transform: translateY(-2px); }
        .code-output { background: #f8f9fa; border: 2px solid #e1e5e9; border-radius: 8px; padding: 20px; margin: 20px 0; position: relative; }
        .code-output pre { margin: 0; font-family: 'Courier New', monospace; font-size: 14px; color: #333; overflow-x: auto; }
        .copy-btn { position: absolute; top: 10px; right: 10px; background: #667eea; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .copy-btn:hover { background: #5a6fd8; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .current-config { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0; }
        .config-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e1e5e9; }
        .config-item:last-child { border-bottom: none; }
        .config-label { font-weight: 600; color: #333; }
        .config-value { color: #666; font-family: monospace; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🌍 Panel de Control Geográfico</h1>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: rgba(255,255,255,0.8);">👤 <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></span>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="logout" class="logout-btn">🚪 Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('clients')">👥 Administrar Clientes SaaS</button>
            <button class="tab" onclick="showTab('countries')">🌎 Gestión de Países</button>
            <button class="tab" onclick="showTab('domains')">🌐 Dominios Permitidos</button>
            <button class="tab" onclick="showTab('settings')">⚙️ Configuración</button>
            <button class="tab" onclick="showTab('code')">📋 Código para Sitios</button>
        </div>

        <!-- Tab: Administrar Clientes SaaS -->
        <div id="clients" class="tab-content active">
            <h2 class="section-title">Administración de Clientes SaaS</h2>
            
            <div class="alert alert-info">
                <strong>ℹ️ Información:</strong> Aquí puedes activar o desactivar los servicios de verificación de email y bloqueo geográfico para cada cliente del sistema SaaS.
            </div>

            <?php
            // Debug de conexión y consulta
            $debugInfo = debugDatabaseConnection();
            $clients = getAllClients();
            
            // Mostrar información de debug
            echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 8px; border: 1px solid #ffeaa7;'>";
            echo "<h4 style='margin: 0 0 10px 0; color: #856404;'>🔧 Información de Debug</h4>";
            
            if ($debugInfo['connection_ok']) {
                echo "<p style='color: #28a745;'>✅ Conexión a base de datos: <strong>EXITOSA</strong></p>";
                echo "<p>📊 Total clientes en BD: <strong>" . $debugInfo['total_clients'] . "</strong></p>";
                echo "<p>📋 Clientes obtenidos por getAllClients(): <strong>" . count($clients) . "</strong></p>";
                
                // Verificar si existen las columnas necesarias
                $hasEmailCol = false;
                $hasGeoCol = false;
                foreach ($debugInfo['table_structure'] as $column) {
                    if ($column['Field'] === 'email_verification_enabled') $hasEmailCol = true;
                    if ($column['Field'] === 'geo_blocking_enabled') $hasGeoCol = true;
                }
                
                echo "<p>📧 Columna email_verification_enabled: " . ($hasEmailCol ? '✅ Existe' : '❌ No existe') . "</p>";
                echo "<p>🌍 Columna geo_blocking_enabled: " . ($hasGeoCol ? '✅ Existe' : '❌ No existe') . "</p>";
                
                if (!$hasEmailCol || !$hasGeoCol) {
                    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
                    echo "<strong>⚠️ PROBLEMA DETECTADO:</strong> Faltan columnas en la tabla clients.<br>";
                    echo "<strong>Solución:</strong> Ejecutar el script SQL: <code>saas/install/add_client_services_columns.sql</code>";
                    echo "</div>";
                }
                
                // Mostrar estructura de tabla en un collapsible
                echo "<details style='margin: 10px 0;'>";
                echo "<summary style='cursor: pointer; color: #856404;'>👀 Ver estructura completa de tabla 'clients'</summary>";
                echo "<pre style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px; overflow-x: auto;'>";
                foreach ($debugInfo['table_structure'] as $column) {
                    echo sprintf("%-30s | %-20s | %-5s | %s\n",
                        $column['Field'],
                        $column['Type'],
                        $column['Null'],
                        $column['Default'] ?? 'NULL'
                    );
                }
                echo "</pre>";
                echo "</details>";
            } else {
                echo "<p style='color: #dc3545;'>❌ Error de conexión: " . htmlspecialchars($debugInfo['error']) . "</p>";
            }
            echo "</div>";
            
            if (empty($clients)):
            ?>
                <div class="alert alert-error">
                    <strong>⚠️ Sin clientes:</strong> No se encontraron clientes en la base de datos.
                    <br><br>
                    <strong>Pasos para solucionar:</strong>
                    <ol style="margin: 10px 0;">
                        <li>Ejecutar el script SQL: <code>saas/install/add_client_services_columns.sql</code></li>
                        <li>Verificar conexión a base de datos: <code>geocontrol_saas</code></li>
                        <li>Asegurarse de que existe al menos un cliente registrado</li>
                    </ol>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto; width: 100%; border: 1px solid #e1e5e9; border-radius: 8px; background: white;">
                    <table style="width: 1600px; border-collapse: collapse; margin: 0; table-layout: fixed;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #e1e5e9;">
                                <th style="padding: 8px; text-align: left; font-weight: 600; width: 50px; background: #f8f9fa;">ID</th>
                                <th style="padding: 8px; text-align: left; font-weight: 600; width: 180px; background: #f8f9fa;">Cliente</th>
                                <th style="padding: 8px; text-align: left; font-weight: 600; width: 180px; background: #f8f9fa;">Email</th>
                                <th style="padding: 8px; text-align: left; font-weight: 600; width: 120px; background: #f8f9fa;">Plan</th>
                                <th style="padding: 8px; text-align: center; font-weight: 600; width: 80px; background: #f8f9fa;">📧 Email</th>
                                <th style="padding: 8px; text-align: center; font-weight: 600; width: 80px; background: #f8f9fa;">🌍 Geo</th>
                                <th style="padding: 8px; text-align: center; font-weight: 600; width: 300px; background: #f8f9fa;">💰 URL de Pago</th>
                                <th style="padding: 8px; text-align: center; font-weight: 600; width: 300px; background: #e7f3ff; border-left: 2px solid #667eea; color: #667eea;">🎛️ ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr style="border-bottom: 1px solid #e1e5e9;">
                                <td style="padding: 8px; font-size: 12px;">#<?php echo $client['id']; ?></td>
                                <td style="padding: 8px; font-size: 12px;">
                                    <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                    <small style="color: #666; font-size: 10px;"><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></small>
                                </td>
                                <td style="padding: 8px; font-size: 12px; word-break: break-all;"><?php echo htmlspecialchars($client['email']); ?></td>
                                <td style="padding: 8px;">
                                    <form method="POST" style="margin: 0;" id="form-plan-<?php echo $client['id']; ?>">
                                        <input type="hidden" name="action" value="change_client_plan">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        
                                        <select name="new_plan" onchange="this.form.submit()"
                                                style="background: #667eea; color: white; border: none; padding: 2px 4px; border-radius: 8px; font-size: 10px; cursor: pointer; font-weight: bold; width: 100%;">
                                            <option value="free" <?php echo ($client['plan'] ?? 'free') === 'free' ? 'selected' : ''; ?>
                                                    style="background: white; color: #333;">FREE</option>
                                            <option value="basic" <?php echo ($client['plan'] ?? 'free') === 'basic' ? 'selected' : ''; ?>
                                                    style="background: white; color: #333;">BASIC</option>
                                            <option value="premium" <?php echo ($client['plan'] ?? 'free') === 'premium' ? 'selected' : ''; ?>
                                                    style="background: white; color: #333;">PREMIUM</option>
                                            <option value="enterprise" <?php echo ($client['plan'] ?? 'free') === 'enterprise' ? 'selected' : ''; ?>
                                                    style="background: white; color: #333;">ENTERPRISE</option>
                                        </select>
                                    </form>
                                    <small style="color: #666; display: block; margin-top: 1px; font-size: 9px;">
                                        <?php echo number_format($client['monthly_usage'] ?? 0); ?>/<?php
                                        echo $client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit'] ?? 1000); ?>
                                    </small>
                                </td>
                                <td style="padding: 5px; text-align: center;">
                                    <form method="POST" style="display: inline-block;" id="form-email-<?php echo $client['id']; ?>">
                                        <input type="hidden" name="action" value="update_client_services">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        <input type="hidden" name="geo_blocking_enabled" value="<?php echo ($client['geo_blocking_enabled'] ?? 1) ? '1' : '0'; ?>">
                                        
                                        <label style="display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                            <input type="checkbox" name="email_verification_enabled"
                                                   <?php echo ($client['email_verification_enabled'] ?? 1) ? 'checked' : ''; ?>
                                                   onchange="this.form.submit()"
                                                   style="transform: scale(1.1); margin-right: 4px;">
                                            <span style="font-size: 16px;">
                                                <?php echo ($client['email_verification_enabled'] ?? 1) ? '✅' : '❌'; ?>
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td style="padding: 5px; text-align: center;">
                                    <form method="POST" style="display: inline-block;" id="form-geo-<?php echo $client['id']; ?>">
                                        <input type="hidden" name="action" value="update_client_services">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        <input type="hidden" name="email_verification_enabled" value="<?php echo ($client['email_verification_enabled'] ?? 1) ? '1' : '0'; ?>">
                                        
                                        <label style="display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                            <input type="checkbox" name="geo_blocking_enabled"
                                                   <?php echo ($client['geo_blocking_enabled'] ?? 1) ? 'checked' : ''; ?>
                                                   onchange="this.form.submit()"
                                                   style="transform: scale(1.1); margin-right: 4px;">
                                            <span style="font-size: 16px;">
                                                <?php echo ($client['geo_blocking_enabled'] ?? 1) ? '✅' : '❌'; ?>
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td style="padding: 5px; text-align: center;">
                                    <form method="POST" style="margin: 0;" id="form-payment-<?php echo $client['id']; ?>">
                                        <input type="hidden" name="action" value="update_payment_url">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        
                                        <input type="url" name="payment_url"
                                               value="<?php echo htmlspecialchars($client['payment_url'] ?? 'https://tu-sitio-pagos.com/upgrade'); ?>"
                                               placeholder="https://tu-sitio-pagos.com/upgrade"
                                               style="width: 100%; max-width: 280px; padding: 3px 6px; border: 1px solid #e1e5e9; border-radius: 4px; font-size: 10px; box-sizing: border-box;"
                                               onchange="this.form.submit()"
                                               title="URL donde dirigir a clientes para procesar pagos">
                                    </form>
                                </td>
                                <td style="padding: 5px; text-align: center;">
                                    <div style="display: flex; gap: 2px; justify-content: center; flex-wrap: wrap;">
                                        <button onclick="showClientDetails(<?php echo $client['id']; ?>)"
                                                style="background: #17a2b8; color: white; padding: 3px 6px; border-radius: 3px; border: none; font-size: 9px; margin: 1px; cursor: pointer; white-space: nowrap;">
                                            📋 Detalles
                                        </button>
                                        <a href="saas/client/dashboard.php?admin_login=<?php echo $client['id']; ?>"
                                           target="_blank"
                                           style="background: #667eea; color: white; padding: 3px 6px; border-radius: 3px; text-decoration: none; font-size: 9px; margin: 1px; white-space: nowrap; display: inline-block;">
                                            👀 Panel
                                        </a>
                                        <a href="saas/client/countries.php?admin_login=<?php echo $client['id']; ?>"
                                           target="_blank"
                                           style="background: #28a745; color: white; padding: 3px 6px; border-radius: 3px; text-decoration: none; font-size: 9px; margin: 1px; white-space: nowrap; display: inline-block;">
                                            🌍 Países
                                        </a>
                                        <?php
                                        // Verificar si es administrador para no mostrar botón de eliminar
                                        $isAdmin = false;
                                        $admin_emails = ['admin@validatumail.cc', 'eduardo@validatumail.cc', 'eduardodavila9@gmail.com', 'supervisor@validatumail.cc'];
                                        $admin_names = ['Administrador SaaS', 'Eduardo Davila', 'Admin Sistema', 'Supervisor'];
                                        
                                        if (in_array(strtolower($client['email']), array_map('strtolower', $admin_emails)) ||
                                            in_array($client['name'], $admin_names) ||
                                            ($client['plan'] === 'enterprise' && strpos(strtolower($client['email']), 'admin') !== false)) {
                                            $isAdmin = true;
                                        }
                                        
                                        if ($isAdmin): ?>
                                            <span style="background: #6c757d; color: white; padding: 3px 6px; border-radius: 3px; font-size: 9px; margin: 1px; white-space: nowrap; display: inline-block;"
                                                  title="Las cuentas de administrador no se pueden eliminar">
                                                🛡️ Protegida
                                            </span>
                                        <?php else: ?>
                                            <button onclick="confirmDeleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name'], ENT_QUOTES); ?>')"
                                                    style="background: #dc3545; color: white; padding: 3px 6px; border-radius: 3px; border: none; font-size: 9px; margin: 1px; cursor: pointer; white-space: nowrap;">
                                                🗑️ Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; text-align: center;">
                    <small style="color: #856404;">
                        <strong>💡 Tip:</strong> La tabla tiene scroll horizontal. Desliza hacia la derecha para ver todas las columnas y los botones de acciones →
                    </small>
                </div>
                
                <div style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h3>📊 Resumen de Servicios</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;">
                                <?php echo count($clients); ?>
                            </div>
                            <div style="color: #666;">Total Clientes</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                                <?php echo count(array_filter($clients, function($c) { return ($c['email_verification_enabled'] ?? 1); })); ?>
                            </div>
                            <div style="color: #666;">Email Validation Activo</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #e74c3c;">
                                <?php echo count(array_filter($clients, function($c) { return ($c['geo_blocking_enabled'] ?? 1); })); ?>
                            </div>
                            <div style="color: #666;">Geo Blocking Activo</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #6f42c1;">
                                <?php echo count(array_filter($clients, function($c) { return ($c['email_verification_enabled'] ?? 1) && ($c['geo_blocking_enabled'] ?? 1); })); ?>
                            </div>
                            <div style="color: #666;">Ambos Servicios</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">🔧 Instrucciones de Configuración</h4>
                    <p style="margin: 0; color: #856404;">
                        <strong>1.</strong> Ejecuta el script SQL: <code>saas/install/add_client_services_columns.sql</code><br>
                        <strong>2.</strong> Los checkboxes permiten activar/desactivar servicios individualmente<br>
                        <strong>3.</strong> Los cambios se aplican inmediatamente al hacer clic
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Gestión de Países -->
        <div id="countries" class="tab-content">
            <h2 class="section-title">Gestión de Países</h2>
            
            <div class="current-config">
                <h3>📊 Configuración Actual</h3>
                <div class="config-item">
                    <span class="config-label">Modo de Control:</span>
                    <span class="config-value"><?php echo $config['ACCESS_CONTROL_MODE'] ?? 'No configurado'; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">Países Permitidos:</span>
                    <span class="config-value"><?php echo $config['COUNTRIES_ALLOWED'] ?? 'Ninguno'; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">Países Bloqueados:</span>
                    <span class="config-value"><?php echo $config['COUNTRIES_DENIED'] ?? 'Ninguno'; ?></span>
                </div>
            </div>

            <form method="POST" action="admin_actions.php">
                <div class="form-group">
                    <label for="access_mode">Modo de Control de Acceso:</label>
                    <select id="access_mode" name="access_mode">
                        <option value="allowed" <?php echo ($config['ACCESS_CONTROL_MODE'] ?? '') === 'allowed' ? 'selected' : ''; ?>>
                            Modo Permitidos (Solo países seleccionados pueden acceder)
                        </option>
                        <option value="denied" <?php echo ($config['ACCESS_CONTROL_MODE'] ?? '') === 'denied' ? 'selected' : ''; ?>>
                            Modo Bloqueados (Todos pueden acceder excepto países seleccionados)
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="countries_allowed">Países Permitidos (códigos ISO separados por comas):</label>
                    <textarea id="countries_allowed" name="countries_allowed" rows="3" placeholder="AR,BO,CL,CO,EC,ES,MX,PE,UY,VE"><?php echo $config['COUNTRIES_ALLOWED'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="countries_denied">Países Bloqueados (códigos ISO separados por comas):</label>
                    <textarea id="countries_denied" name="countries_denied" rows="3" placeholder="RU,CN,IR,KP"><?php echo $config['COUNTRIES_DENIED'] ?? ''; ?></textarea>
                </div>

                <div class="alert alert-info">
                    <strong>💡 Códigos de países más comunes:</strong><br>
                    AR=Argentina, BO=Bolivia, BR=Brasil, CA=Canadá, CL=Chile, CN=China, CO=Colombia, CR=Costa Rica, 
                    CU=Cuba, DO=Rep.Dominicana, EC=Ecuador, ES=España, GT=Guatemala, HN=Honduras, IR=Irán, 
                    KP=Corea del Norte, MX=México, NI=Nicaragua, PA=Panamá, PE=Perú, PY=Paraguay, RU=Rusia, 
                    SV=El Salvador, US=Estados Unidos, UY=Uruguay, VE=Venezuela
                </div>

                <button type="submit" class="save-btn">💾 Guardar Configuración de Países</button>
            </form>
        </div>

        <!-- Tab: Dominios -->
        <div id="domains" class="tab-content">
            <h2 class="section-title">Lista Blanca de Dominios</h2>
            
            <div class="alert alert-info">
                <strong>ℹ️ Información:</strong> Solo los dominios en esta lista podrán ejecutar el script de control geográfico. Esto evita que otros sitios usen tu sistema sin autorización.
            </div>

            <form method="POST" action="admin_actions.php">
                <div class="form-group">
                    <label for="domains_whitelist">Dominios Permitidos (uno por línea):</label>
                    <textarea id="domains_whitelist" name="domains_whitelist" rows="10" placeholder="ejemplo.com
app.ejemplo.com
subdominio.ejemplo.com"><?php echo str_replace(',', "\n", $config['DOMAINS_WHITELIST'] ?? ''); ?></textarea>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        💡 Ingresa cada dominio en una línea separada. No incluyas http:// o https://
                    </small>
                </div>

                <div class="current-config">
                    <h3>📋 Dominios Actualmente Permitidos</h3>
                    <?php
                    $current_domains = explode(',', $config['DOMAINS_WHITELIST'] ?? '');
                    foreach ($current_domains as $domain) {
                        $domain = trim($domain);
                        if ($domain) {
                            echo "<div class='config-item'>";
                            echo "<span class='config-label'>🌐 $domain</span>";
                            echo "<span class='config-value'>✅ Activo</span>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>

                <button type="submit" class="save-btn">💾 Guardar Lista de Dominios</button>
            </form>
        </div>

        <!-- Tab: Configuración -->
        <div id="settings" class="tab-content">
            <h2 class="section-title">Configuración General</h2>
            
            <form method="POST" action="admin_actions.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="server_url">URL del Servidor:</label>
                        <input type="url" id="server_url" name="server_url" value="<?php echo $config['SERVER_URL'] ?? ''; ?>" placeholder="https://tuservidor.com">
                    </div>
                    <div class="form-group">
                        <label for="cache_duration">Duración de Caché (horas):</label>
                        <input type="number" id="cache_duration" name="cache_duration" value="<?php echo $config['CACHE_DURATION'] ?? '168'; ?>" min="1" max="8760">
                    </div>
                </div>

                <h3 style="margin: 30px 0 15px 0; color: #333;">🛡️ Control de Privacidad</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="allow_vpn">Permitir VPN:</label>
                        <select id="allow_vpn" name="allow_vpn">
                            <option value="yes" <?php echo ($config['ALLOW_VPN'] ?? '') === 'yes' ? 'selected' : ''; ?>>Sí - Permitir</option>
                            <option value="no" <?php echo ($config['ALLOW_VPN'] ?? '') === 'no' ? 'selected' : ''; ?>>No - Bloquear</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="allow_tor">Permitir Tor:</label>
                        <select id="allow_tor" name="allow_tor">
                            <option value="yes" <?php echo ($config['ALLOW_TOR'] ?? '') === 'yes' ? 'selected' : ''; ?>>Sí - Permitir</option>
                            <option value="no" <?php echo ($config['ALLOW_TOR'] ?? '') === 'no' ? 'selected' : ''; ?>>No - Bloquear</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="allow_proxy">Permitir Proxy:</label>
                        <select id="allow_

                <div class="form-row">
                    <div class="form-group">
                        <label for="allow_proxy">Permitir Proxy:</label>
                        <select id="allow_proxy" name="allow_proxy">
                            <option value="yes" <?php echo ($config['ALLOW_PROXY'] ?? '') === 'yes' ? 'selected' : ''; ?>>Sí - Permitir</option>
                            <option value="no" <?php echo ($config['ALLOW_PROXY'] ?? '') === 'no' ? 'selected' : ''; ?>>No - Bloquear</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="allow_hosting">Permitir Hosting:</label>
                        <select id="allow_hosting" name="allow_hosting">
                            <option value="yes" <?php echo ($config['ALLOW_HOSTING'] ?? '') === 'yes' ? 'selected' : ''; ?>>Sí - Permitir</option>
                            <option value="no" <?php echo ($config['ALLOW_HOSTING'] ?? '') === 'no' ? 'selected' : ''; ?>>No - Bloquear</option>
                        </select>
                    </div>
                </div>

                <h3 style="margin: 30px 0 15px 0; color: #333;">🚫 Comportamiento de Acceso Denegado</h3>
                <div class="form-group">
                    <label for="access_denied_action">Acción cuando se niega el acceso:</label>
                    <select id="access_denied_action" name="access_denied_action" onchange="toggleRedirectUrl()">
                        <option value="block_forms" <?php echo ($config['ACCESS_DENIED_ACTION'] ?? '') === 'block_forms' ? 'selected' : ''; ?>>
                            Bloquear Formularios (Página visible pero formularios deshabilitados)
                        </option>
                        <option value="redirect" <?php echo ($config['ACCESS_DENIED_ACTION'] ?? '') === 'redirect' ? 'selected' : ''; ?>>
                            Redirigir (Enviar a otra página)
                        </option>
                    </select>
                </div>

                <div class="form-group" id="redirect_url_group" style="display: <?php echo ($config['ACCESS_DENIED_ACTION'] ?? '') === 'redirect' ? 'block' : 'none'; ?>;">
                    <label for="access_denied_redirect_url">URL de Redirección:</label>
                    <input type="url" id="access_denied_redirect_url" name="access_denied_redirect_url" value="<?php echo $config['ACCESS_DENIED_REDIRECT_URL'] ?? ''; ?>" placeholder="https://ejemplo.com/acceso-denegado">
                </div>

                <div class="form-group">
                    <label for="debug_mode">Modo de Depuración:</label>
                    <select id="debug_mode" name="debug_mode">
                        <option value="true" <?php echo ($config['DEBUG_MODE'] ?? '') === 'true' ? 'selected' : ''; ?>>Activado (Mostrar logs en consola)</option>
                        <option value="false" <?php echo ($config['DEBUG_MODE'] ?? '') === 'false' ? 'selected' : ''; ?>>Desactivado (Sin logs)</option>
                    </select>
                </div>

                <button type="submit" class="save-btn">💾 Guardar Configuración General</button>
            </form>
        </div>

        <!-- Tab: Código -->
        <div id="code" class="tab-content">
            <h2 class="section-title">Código para Implementar en tus Sitios Web</h2>
            
            <div class="alert alert-success">
                <strong>✅ ¡Listo para usar!</strong> Copia y pega el siguiente código en tus sitios web para activar el control geográfico.
            </div>

            <h3>📝 Código JavaScript Completo</h3>
            <div class="code-output">
                <button class="copy-btn" onclick="copyToClipboard('js-code')">📋 Copiar</button>
                <pre id="js-code">&lt;!-- Control de Acceso Geográfico --&gt;
&lt;script&gt;
    const DEBUG_MODE = true; // Cambiar a false en producción
&lt;/script&gt;
&lt;script src="<?php echo $config['SERVER_URL'] ?? 'https://tuservidor.com'; ?>/direct_geo.js" charset="UTF-8"&gt;&lt;/script&gt;</pre>
            </div>

            <h3>🎯 Implementación por Tipo de Sitio</h3>
            
            <div style="margin: 20px 0;">
                <h4>Para sitios HTML simples:</h4>
                <div class="code-output">
                    <button class="copy-btn" onclick="copyToClipboard('html-code')">📋 Copiar</button>
                    <pre id="html-code">&lt;!-- Agregar antes del cierre de &lt;/body&gt; --&gt;
&lt;script&gt;
    const DEBUG_MODE = false; // false para producción
&lt;/script&gt;
&lt;script src="<?php echo $config['SERVER_URL'] ?? 'https://tuservidor.com'; ?>/direct_geo.js" charset="UTF-8"&gt;&lt;/script&gt;</pre>
                </div>
            </div>

            <div style="margin: 20px 0;">
                <h4>Para WordPress (functions.php):</h4>
                <div class="code-output">
                    <button class="copy-btn" onclick="copyToClipboard('wp-code')">📋 Copiar</button>
                    <pre id="wp-code">// Agregar en functions.php del tema activo
function add_geo_control() {
    echo '&lt;script&gt;const DEBUG_MODE = false;&lt;/script&gt;';
    echo '&lt;script src="<?php echo $config['SERVER_URL'] ?? 'https://tuservidor.com'; ?>/direct_geo.js" charset="UTF-8"&gt;&lt;/script&gt;';
}
add_action('wp_footer', 'add_geo_control');</pre>
                </div>
            </div>

            <div style="margin: 20px 0;">
                <h4>Para sitios con formularios específicos:</h4>
                <div class="code-output">
                    <button class="copy-btn" onclick="copyToClipboard('form-code')">📋 Copiar</button>
                    <pre id="form-code">&lt;!-- Código cerca de tus formularios --&gt;
&lt;script&gt;
    const DEBUG_MODE = false;
&lt;/script&gt;
&lt;script src="<?php echo $config['SERVER_URL'] ?? 'https://tuservidor.com'; ?>/direct_geo.js" charset="UTF-8"&gt;&lt;/script&gt;

&lt;!-- Tu formulario --&gt;
&lt;form id="miFormulario"&gt;
    &lt;input type="text" name="nombre" required&gt;
    &lt;input type="email" name="email" required&gt;
    &lt;button type="submit"&gt;Enviar&lt;/button&gt;
&lt;/form&gt;</pre>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>🔧 Configuración Actual:</strong><br>
                • Servidor: <?php echo $config['SERVER_URL'] ?? 'No configurado'; ?><br>
                • Modo: <?php echo $config['ACCESS_CONTROL_MODE'] ?? 'No configurado'; ?><br>
                • Acción de bloqueo: <?php echo $config['ACCESS_DENIED_ACTION'] ?? 'No configurado'; ?><br>
                • Debug: <?php echo $config['DEBUG_MODE'] ?? 'No configurado'; ?>
            </div>
        </div>
    </div>

    <script>
        // Funciones JavaScript para la interfaz de administración
        function showTab(tabName) {
            // Ocultar todas las pestañas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => tab.classList.remove('active'));
            
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar la pestaña seleccionada
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function toggleRedirectUrl() {
            const select = document.getElementById('access_denied_action');
            const urlGroup = document.getElementById('redirect_url_group');
            
            if (select.value === 'redirect') {
                urlGroup.style.display = 'block';
            } else {
                urlGroup.style.display = 'none';
            }
        }

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent || element.innerText;
            
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar mensaje de éxito
                const button = element.parentNode.querySelector('.copy-btn');
                const originalText = button.textContent;
                button.textContent = '✅ Copiado!';
                button.style.background = '#28a745';
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = '#667eea';
                }, 2000);
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
                alert('Error al copiar al portapapeles');
            });
        }

        // Función para mostrar detalles del cliente en ventana modal
        function showClientDetails(clientId) {
            // Mostrar loading en el modal
            document.getElementById('clientDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px;"><div style="font-size: 24px;">⏳</div><p>Cargando información del cliente...</p></div>';
            document.getElementById('clientDetailsModal').style.display = 'block';
            
            // Hacer petición AJAX para obtener detalles del cliente
            fetch('admin.php?action=get_client_details&client_id=' + clientId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('clientDetailsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('clientDetailsContent').innerHTML =
                        '<div style="color: red; text-align: center; padding: 20px;">❌ Error al cargar los detalles del cliente</div>';
                });
        }
        
        // Función para cerrar el modal
        function closeClientDetails() {
            document.getElementById('clientDetailsModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('clientDetailsModal');
            if (event.target == modal) {
                closeClientDetails();
            }
        }

        // Función para confirmar eliminación de cliente
        function confirmDeleteClient(clientId, clientName) {
            const message = `¿Estás seguro de que deseas eliminar al cliente "${clientName}"?\n\n` +
                          `⚠️ ADVERTENCIA: Esta acción eliminará permanentemente:\n` +
                          `• Todos los datos del cliente\n` +
                          `• Sitios web asociados\n` +
                          `• Historial de requests/uso\n` +
                          `• Configuraciones personalizadas\n\n` +
                          `Esta acción NO se puede deshacer.`;
            
            if (confirm(message)) {
                // Crear formulario para enviar la petición de eliminación
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_client';
                
                const clientIdInput = document.createElement('input');
                clientIdInput.type = 'hidden';
                clientIdInput.name = 'client_id';
                clientIdInput.value = clientId;
                
                form.appendChild(actionInput);
                form.appendChild(clientIdInput);
                document.body.appendChild(form);
                
                form.submit();
            }
        }

        // Inicializar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar URL de redirección según la configuración actual
            toggleRedirectUrl();
        });
    </script>
    
    <!-- Modal para detalles del cliente -->
    <div id="clientDetailsModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 0; border-radius: 10px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <!-- Header del modal -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">📋 Detalles del Cliente</h2>
                <button onclick="closeClientDetails()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">×</button>
            </div>
            
            <!-- Contenido del modal -->
            <div id="clientDetailsContent" style="padding: 20px;">
                <!-- El contenido se cargará dinámicamente aquí -->
            </div>
        </div>
    </div>
</body>
</html>