<?php
/**
 * Configuración del Cliente
 * 
 * Permite al cliente cambiar su información personal y configuraciones
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['client_id'])) {
    redirectToLogin('Debes iniciar sesión para acceder');
}

$client_id = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'];
$client_plan = $_SESSION['client_plan'];

$message = '';
$message_type = '';

// Procesar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $db = SaasDatabase::getInstance();

        if ($action === 'update_profile') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $company = sanitizeInput($_POST['company'] ?? '');
            $website = sanitizeInput($_POST['website'] ?? '');

            if (empty($name)) {
                throw new Exception('El nombre es obligatorio');
            }

            $db->query(
                "UPDATE clients SET name = ?, phone = ?, company = ?, website = ?, updated_at = NOW() WHERE id = ?",
                [$name, $phone, $company, $website, $client_id]
            );

            $_SESSION['client_name'] = $name;

            $message = 'Perfil actualizado exitosamente';
            $message_type = 'success';

        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('Todos los campos de contraseña son obligatorios');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('Las contraseñas nuevas no coinciden');
            }

            if (strlen($new_password) < 8) {
                throw new Exception('La contraseña debe tener al menos 8 caracteres');
            }

            // Verificar contraseña actual
            $current_client = $db->fetchOne(
                "SELECT password_hash FROM clients WHERE id = ?",
                [$client_id]
            );

            if (!verifyPassword($current_password, $current_client['password_hash'])) {
                throw new Exception('La contraseña actual es incorrecta');
            }

            // Actualizar contraseña
            $new_hash = hashPassword($new_password);
            $db->query(
                "UPDATE clients SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$new_hash, $client_id]
            );

            $message = 'Contraseña cambiada exitosamente';
            $message_type = 'success';
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

try {
    $db = SaasDatabase::getInstance();

    // Obtener datos actuales del cliente
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ?",
        [$client_id]
    );

} catch (Exception $e) {
    $error_message = 'Error al cargar la configuración';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - GeoControl SaaS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-body {
            padding: 25px;
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

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🌍 GeoControl</h2>
            <p>Panel de Control</p>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i>📊</i> Dashboard
            </a>
            <a href="websites.php" class="nav-item">
                <i>🌐</i> Mis Sitios Web
            </a>
            <a href="countries.php" class="nav-item">
                <i>🌍</i> Configurar Países
            </a>
            <a href="code_generator.php" class="nav-item">
                <i>📋</i> Generar Código
            </a>
            <a href="statistics.php" class="nav-item">
                <i>📈</i> Estadísticas
            </a>
            <a href="settings.php" class="nav-item active">
                <i>⚙️</i> Configuración
            </a>
            <a href="billing.php" class="nav-item">
                <i>💳</i> Facturación
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>⚙️ Configuración</h1>
            <p>Gestiona tu cuenta y configuraciones</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="card">
            <div class="card-header">
                <h3>👤 Información Personal</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="name">Nombre completo</label>
                        <input type="text" id="name" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($client['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email (no modificable)</label>
                        <input type="email" class="form-control"
                            value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="phone">Teléfono</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                            value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="company">Empresa</label>
                        <input type="text" id="company" name="company" class="form-control"
                            value="<?php echo htmlspecialchars($client['company'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="website">Sitio web principal</label>
                        <input type="url" id="website" name="website" class="form-control"
                            value="<?php echo htmlspecialchars($client['website'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Actualizar Perfil</button>
                </form>
            </div>
        </div>

        <!-- Password Change -->
        <div class="card">
            <div class="card-header">
                <h3>🔒 Cambiar Contraseña</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Contraseña actual</label>
                        <input type="password" id="current_password" name="current_password" class="form-control"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Nueva contraseña</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="8"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar nueva contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-primary">🔒 Cambiar Contraseña</button>
                </form>
            </div>
        </div>

        <!-- Account Info -->
        <div class="card">
            <div class="card-header">
                <h3>📊 Información de Cuenta</h3>
            </div>
            <div class="card-body">
                <p><strong>Plan actual:</strong> <?php echo ucfirst($client['plan']); ?></p>
                <p><strong>Estado:</strong> <?php echo ucfirst($client['status']); ?></p>
                <p><strong>Uso mensual:</strong> <?php echo number_format($client['monthly_usage']); ?> /
                    <?php echo $client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit']); ?></p>
                <p><strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($client['created_at'])); ?></p>
                <p><strong>Último acceso:</strong>
                    <?php echo $client['last_login'] ? date('d/m/Y H:i', strtotime($client['last_login'])) : 'Nunca'; ?>
                </p>
            </div>
        </div>
    </div>
</body>

</html>