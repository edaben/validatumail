<?php
/**
 * Página de Login del SaaS
 * 
 * Permite a los clientes autenticarse en el sistema
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['client_id'])) {
    header('Location: ../client/dashboard.php');
    exit;
}

// Procesar login si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son obligatorios';
    } else {
        try {
            $db = SaasDatabase::getInstance();
            
            // Buscar cliente por email
            $client = $db->fetchOne(
                "SELECT id, name, email, password_hash, status, plan, last_login FROM clients WHERE email = ?",
                [$email]
            );
            
            if ($client && verifyPassword($password, $client['password_hash'])) {
                if ($client['status'] !== 'active') {
                    $error = 'Tu cuenta no está activa. Contacta a soporte.';
                } else {
                    // Login exitoso
                    $_SESSION['client_id'] = $client['id'];
                    $_SESSION['client_name'] = $client['name'];
                    $_SESSION['client_email'] = $client['email'];
                    $_SESSION['client_plan'] = $client['plan'];
                    $_SESSION['login_time'] = time();
                    
                    // Actualizar último login
                    $db->query(
                        "UPDATE clients SET last_login = NOW() WHERE id = ?",
                        [$client['id']]
                    );
                    
                    // Log de actividad
                    logActivity('info', 'Cliente inició sesión', [
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ], $client['id']);
                    
                    header('Location: ../client/dashboard.php');
                    exit;
                }
            } else {
                $error = 'Email o contraseña incorrectos';
                
                // Log de intento fallido
                logActivity('warning', 'Intento de login fallido', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        } catch (Exception $e) {
            $error = 'Error interno del servidor. Inténtalo de nuevo.';
            logActivity('error', 'Error en login', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
        }
    }
}

// Obtener mensaje de la URL si existe
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';
$prefill_email = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= SAAS_SITE_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #99d3ff;
        }

        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .login-footer p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .demo-credentials h4 {
            color: #495057;
            margin-bottom: 8px;
        }

        .demo-credentials p {
            color: #666;
            margin: 4px 0;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header, .login-form, .login-footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🌍 <?= SAAS_SITE_NAME ?></h1>
            <p>Accede a tu panel de control</p>
        </div>

        <div class="login-form">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>


            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($prefill_email) ?>"
                        required 
                        placeholder="tu@email.com"
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        placeholder="Tu contraseña"
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn-login">
                    🚀 Iniciar Sesión
                </button>

                <div class="forgot-password">
                    <a href="mailto:<?= MAIL_FROM_EMAIL ?>?subject=Recuperar contraseña">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>
        </div>

        <div class="login-footer">
            <p>¿No tienes cuenta?</p>
            <a href="index.php">Registrarse gratis</a>
        </div>
    </div>

    <script>
        // Auto-focus en el campo email si está vacío
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (emailField.value === '') {
                emailField.focus();
            } else {
                passwordField.focus();
            }
            
            // Limpiar parámetros de URL después de mostrar mensaje
            if (window.location.search) {
                const url = window.location.origin + window.location.pathname;
                history.replaceState(null, '', url);
            }
        });
    </script>
</body>
</html>