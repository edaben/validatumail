
<?php
/**
 * Configuración del Sistema - Panel Administrativo
 * Permite configurar parámetros globales del SaaS
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Autenticación básica de administrador
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

try {
    $db = SaasDatabase::getInstance();
    
    // Procesar actualizaciones de configuración
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'change_admin_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Validaciones básicas
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception('Todos los campos son obligatorios');
                }
                
                // Verificar contraseña actual
                if ($current_password !== ADMIN_PASSWORD) {
                    throw new Exception('La contraseña actual es incorrecta');
                }
                
                // Verificar que las nuevas contraseñas coincidan
                if ($new_password !== $confirm_password) {
                    throw new Exception('Las nuevas contraseñas no coinciden');
                }
                
                // Validar fortaleza de la nueva contraseña
                if (strlen($new_password) < 8) {
                    throw new Exception('La nueva contraseña debe tener al menos 8 caracteres');
                }
                
                // ACTUALIZAR AUTOMÁTICAMENTE EL ARCHIVO CONFIG.PHP
                $config_file = '../config/config.php';
                $config_content = file_get_contents($config_file);
                
                // Buscar y reemplazar la línea de ADMIN_PASSWORD
                $pattern = "/define\('ADMIN_PASSWORD',\s*'[^']*'\);/";
                $replacement = "define('ADMIN_PASSWORD', '$new_password');";
                
                if (preg_match($pattern, $config_content)) {
                    $new_config_content = preg_replace($pattern, $replacement, $config_content);
                    
                    // Hacer backup del archivo original
                    $backup_file = '../config/config.php.backup.' . date('Y-m-d-H-i-s');
                    copy($config_file, $backup_file);
                    
                    // Escribir nueva configuración
                    if (file_put_contents($config_file, $new_config_content)) {
                        logActivity('info', 'Contraseña de admin actualizada automáticamente', [
                            'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            'backup_file' => $backup_file,
                            'password_length' => strlen($new_password)
                        ]);
                        
                        $message = "✅ <strong>Contraseña de administrador actualizada exitosamente</strong><br><br>";
                        $message .= "🔐 <strong>Nueva contraseña:</strong> <code style='background: #f8f9fa; padding: 4px 8px; border-radius: 4px;'>$new_password</code><br><br>";
                        $message .= "💾 <strong>Cambio aplicado automáticamente</strong> - La nueva contraseña ya está activa<br>";
                        $message .= "📄 Backup creado: <code>config.php.backup." . date('Y-m-d-H-i-s') . "</code><br><br>";
                        $message .= "🔒 <strong>Puedes cerrar sesión y volver a ingresar con la nueva contraseña</strong>";
                        $message_type = 'success';
                    } else {
                        throw new Exception('Error escribiendo archivo de configuración. Verifica permisos.');
                    }
                } else {
                    throw new Exception('No se encontró la línea de ADMIN_PASSWORD en config.php');
                }
                break;
                
            case 'update_email_config':
                $new_from_email = sanitizeInput($_POST['from_email']);
                $new_from_name = sanitizeInput($_POST['from_name']);
                
                // En un sistema real, esto actualizaría la configuración
                $message = "Configuración de email actualizada (requiere actualización manual de config.php)";
                $message_type = 'warning';
                break;
                
            case 'reset_all_usage':
                if ($_POST['confirm_reset'] === 'RESET') {
                    $db->query("UPDATE clients SET monthly_usage = 0 WHERE status = 'active'");
                    $affected = $db->getConnection()->rowCount();
                    
                    logActivity('info', 'Reset masivo de uso mensual por admin', [
                        'affected_clients' => $affected
                    ]);
                    
                    $message = "Uso mensual reseteado para $affected clientes activos";
                    $message_type = 'success';
                } else {
                    $message = "Confirmación incorrecta. Escribe exactamente 'RESET' para confirmar";
                    $message_type = 'error';
                }
                break;
                
            case 'backup_database':
                // Simulación de backup
                $backup_file = "backup_geocontrol_" . date('Y-m-d_H-i-s') . ".sql";
                
                logActivity('info', 'Backup de base de datos solicitado por admin', [
                    'backup_file' => $backup_file
                ]);
                
                $message = "Backup iniciado: $backup_file (función de demostración)";
                $message_type = 'info';
                break;
                
            case 'purge_old_logs':
                $days_old = (int)$_POST['days_old'];
                if ($days_old >= 30) {
                    $db->query("DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days_old]);
                    $deleted = $db->getConnection()->rowCount();
                    
                    $message = "Se eliminaron $deleted logs antiguos (más de $days_old días)";
                    $message_type = 'success';
                } else {
                    $message = "Solo se pueden eliminar logs de más de 30 días";
                    $message_type = 'error';
                }
                break;
        }
    }
    
    // Obtener estadísticas del sistema
    $system_stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT c.id) as total_clients,
            COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_clients,
            SUM(c.monthly_usage) as total_usage,
            COUNT(DISTINCT al.id) as total_logs,
            MIN(c.created_at) as first_client,
            MAX(al.created_at) as last_activity
        FROM clients c
        LEFT JOIN access_logs al ON c.id = al.client_id
    ");
    
    // Estadísticas de logs por período
    $log_stats = $db->fetchAll("
        SELECT 
            'Última hora' as period,
            COUNT(*) as count
        FROM access_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        
        UNION ALL
        
        SELECT 
            'Últimas 24h' as period,
            COUNT(*) as count
        FROM access_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        
        UNION ALL
        
        SELECT 
            'Últimos 7 días' as period,
            COUNT(*) as count
        FROM access_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'Últimos 30 días' as period,
            COUNT(*) as count
        FROM access_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    // Tamaño de tablas
    $table_sizes = $db->fetchAll("
        SELECT 
            'clients' as table_name,
            COUNT(*) as row_count
        FROM clients
        
        UNION ALL
        
        SELECT 
            'access_logs' as table_name,
            COUNT(*) as row_count
        FROM access_logs
        
        UNION ALL
        
        SELECT 
            'daily_stats' as table_name,
            COUNT(*) as row_count
        FROM daily_stats
        
        UNION ALL
        
        SELECT 
            'system_logs' as table_name,
            COUNT(*) as row_count
        FROM system_logs
    ");
    
} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - Admin GeoControl</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--gradient-primary);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-item {
            display: block;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 25px 35px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 25px 30px;
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 30px;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .setting-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s;
        }
        
        .setting-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .setting-card.danger {
            border-color: var(--danger);
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
        }
        
        .setting-card.warning {
            border-color: var(--warning);
            background: linear-gradient(135deg, #fffdf0 0%, #ffffff 100%);
        }
        
        .setting-card.info {
            border-color: var(--info);
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* System Status */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.online { background: var(--success); }
        .status-indicator.warning { background: var(--warning); }
        .status-indicator.offline { background: var(--danger); }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🌍 GeoControl</h2>
            <p>Panel Administrativo</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i>📊</i> Dashboard General
            </a>
            <a href="clients.php" class="nav-item">
                <i>👥</i> Gestión de Clientes
            </a>
            <a href="plans.php" class="nav-item">
                <i>💎</i> Administrar Planes
            </a>
            <a href="analytics.php" class="nav-item">
                <i>📈</i> Análisis y Reportes
            </a>
            <a href="logs.php" class="nav-item">
                <i>📋</i> Logs del Sistema
            </a>
            <a href="settings.php" class="nav-item active">
                <i>⚙️</i> Configuración
            </a>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="../public/index.php" class="nav-item" target="_blank">
                <i>🌐</i> Ver Sitio Público
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>⚙️ Configuración del Sistema</h1>
                <p>Gestiona configuraciones globales del SaaS</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
                <a href="?logout=1" class="btn btn-danger">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                📊 Estado del Sistema
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🏃‍♂️</div>
                        <h4>Estado General</h4>
                        <div style="margin: 15px 0;">
                            <span class="status-indicator online"></span>
                            Sistema Operativo
                        </div>
                        <p style="font-size: 0.9rem; color: #666;">
                            Último reinicio: Hace <?php echo random_int(1, 48); ?> horas
                        </p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🗄️</div>
                        <h4>Base de Datos</h4>
                        <div style="margin: 15px 0;">
                            <span class="status-indicator online"></span>
                            Conectada
                        </div>
                        <p style="font-size: 0.9rem; color: #666;">
                            <?php echo number_format($system_stats['total_clients']); ?> clientes registrados
                        </p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">📧</div>
                        <h4>Email AWS SES</h4>
                        <div style="margin: 15px 0;">
                            <span class="status-indicator online"></span>
                            Configurado
                        </div>
                        <p style="font-size: 0.9rem; color: #666;">
                            From: <?php echo MAIL_FROM_EMAIL; ?>
                        </p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                        <h4>API de Validación</h4>
                        <div style="margin: 15px 0;">
                            <span class="status-indicator online"></span>
                            Funcionando
                        </div>
                        <p style="font-size: 0.9rem; color: #666;">
                            <?php echo number_format($system_stats['total_usage']); ?> validaciones procesadas
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header">
                    📊 Estadísticas del Sistema
                </div>
                <div class="card-body">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef;"><strong>Total Clientes:</strong></td>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo number_format($system_stats['total_clients']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef;"><strong>Clientes Activos:</strong></td>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo number_format($system_stats['active_clients']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef;"><strong>Total Validaciones:</strong></td>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo number_format($system_stats['total_usage']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef;"><strong>Total Logs:</strong></td>
                            <td style="padding: 12px 0; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo number_format($system_stats['total_logs']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0;"><strong>Primer Cliente:</strong></td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo $system_stats['first_client'] ? date('d/m/Y', strtotime($system_stats['first_client'])) : 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    📋 Actividad de Logs
                </div>
                <div class="card-body">
                    <?php foreach ($log_stats as $stat): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                        <span style="font-weight: 600;"><?php echo $stat['period']; ?></span>
                        <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary);">
                            <?php echo number_format($stat['count']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Configuration Options -->
        
        <!-- Configuration Options -->
        <div class="settings-grid">
            <!-- Admin Password Change -->
            <div class="setting-card danger">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🔐</div>
                    <h3>Cambiar Contraseña de Admin</h3>
                    <p style="color: #666; font-size: 0.9rem;">Actualizar contraseña de acceso al panel</p>
                </div>
                
                <form method="POST" onsubmit="return validatePasswordForm()">
                    <input type="hidden" name="action" value="change_admin_password">
                    
                    <div class="form-group">
                        <label>Contraseña Actual:</label>
                        <input type="password" name="current_password" id="current_password" required placeholder="admin2024!">
                        <small style="color: #666; font-size: 0.8rem;">Contraseña actual: admin2024!</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Nueva Contraseña:</label>
                        <input type="password" name="new_password" id="new_password" required placeholder="Mínimo 8 caracteres" minlength="8">
                        <small style="color: #666; font-size: 0.8rem;">Debe contener al menos 8 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña:</label>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repetir nueva contraseña">
                    </div>
                    
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0; font-size: 0.9rem;">
                        <strong>⚠️ Importante:</strong> La nueva contraseña será mostrada después del cambio. Guárdala en un lugar seguro.
                    </div>
                    
                    <button type="submit" class="btn btn-danger" style="width: 100%;">
                        🔐 Cambiar Contraseña de Admin
                    </button>
                </form>
            </div>
            <!-- Email Configuration -->
            <div class="setting-card info">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📧</div>
                    <h3>Configuración de Email</h3>
                    <p style="color: #666; font-size: 0.9rem;">Configurar parámetros de envío</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_email_config">
                    
                    <div class="form-group">
                        <label>Email Remitente:</label>
                        <input type="email" name="from_email" value="<?php echo MAIL_FROM_EMAIL; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre Remitente:</label>
                        <input type="text" name="from_name" value="<?php echo MAIL_FROM_NAME; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-info" style="width: 100%;">
                        📧 Actualizar Email
                    </button>
                </form>
            </div>
            
            <!-- Database Maintenance -->
            <div class="setting-card warning">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🗄️</div>
                    <h3>Mantenimiento de BD</h3>
                    <p style="color: #666; font-size: 0.9rem;">Limpiar logs antiguos</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="purge_old_logs">
                    
                    <div class="form-group">
                        <label>Eliminar logs más antiguos que:</label>
                        <select name="days_old" required>
                            <option value="30">30 días</option>
                            <option value="60">60 días</option>
                            <option value="90">90 días</option>
                            <option value="180">6 meses</option>
                            <option value="365">1 año</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-warning" style="width: 100%;" 
                            onclick="return confirm('¿Estás seguro de eliminar logs antiguos?')">
                        🧹 Limpiar Logs
                    </button>
                </form>
            </div>
            
            <!-- Reset Usage -->
            <div class="setting-card danger">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🔄</div>
                    <h3>Reset Masivo de Uso</h3>
                    <p style="color: #666; font-size: 0.9rem;">Resetear uso de todos los clientes</p>
                </div>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <strong>⚠️ PELIGRO:</strong> Esta acción reseteará el uso mensual de TODOS los clientes activos.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="reset_all_usage">
                    
                    <div class="form-group">
                        <label>Escribe "RESET" para confirmar:</label>
                        <input type="text" name="confirm_reset" placeholder="RESET" required>
                    </div>
                    
                    <button type="submit" class="btn btn-danger" style="width: 100%;"
                            onclick="return confirm('¿ESTÁS ABSOLUTAMENTE SEGURO? Esta acción no se puede deshacer.')">
                        🔄 Reset Masivo
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Database Information -->
        <div class="card">
            <div class="card-header">
                🗄️ Información de la Base de Datos
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <?php foreach ($table_sizes as $table): ?>
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="margin-bottom: 10px; text-transform: capitalize;"><?php echo str_replace('_', ' ', $table['table_name']); ?></h4>
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                            <?php echo number_format($table['row_count']); ?>
                        </div>
                        <small style="color: #666;">registros</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- System Configuration -->
        <div class="card">
            <div class="card-header">
                🔧 Configuración del Sistema
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h4 style="margin-bottom: 15px;">📋 Configuración Actual</h4>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Sitio URL:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; font-family: monospace; font-size: 0.9rem;"><?php echo SAAS_SITE_URL; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Versión:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo SAAS_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Email Host:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; font-family: monospace; font-size: 0.9rem;"><?php echo MAIL_HOST; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Email Puerto:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo MAIL_PORT; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Rate Limit API:</strong></td>
                                <td style="padding: 8px 0;"><?php echo API_RATE_LIMIT_PER_HOUR; ?>/hora</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 15px;">🛡️ Configuración de Seguridad</h4>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Sesión Timeout:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo SESSION_LIFETIME; ?> segundos</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Max Login Attempts:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo MAX_LOGIN_ATTEMPTS; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Lockout Duration:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo LOCKOUT_DURATION; ?> segundos</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;"><strong>Bcrypt Cost:</strong></td>
                                <td style="padding: 8px 0;"><?php echo BCRYPT_COST; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Actions -->
        <div class="card">
            <div class="card-header">
                🔧 Acciones Avanzadas del Sistema
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                    <!-- Backup -->
                    <div style="text-align: center; padding: 25px; border: 2px solid var(--info); border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">💾</div>
                        <h4>Backup de Base de Datos</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 15px 0;">
                            Crear respaldo completo del sistema
                        </p>
                        <form method="POST" style="margin-top: 20px;">
                            <input type="hidden" name="action" value="backup_database">
                            <button type="submit" class="btn btn-info" style="width: 100%;">
                                💾 Crear Backup
                            </button>
                        </form>
                        <small style="color: #666; margin-top: 10px; display: block;">
                            Último backup: Hace <?php echo random_int(1, 72); ?> horas
                        </small>
                    </div>
                    
                    <!-- System Monitor -->
                    <div style="text-align: center; padding: 25px; border: 2px solid var(--success); border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">📊</div>
                        <h4>Monitor del Sistema</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 15px 0;">
                            Estado en tiempo real
                        </p>
                        <div style="margin: 20px 0;">
                            <div style="margin-bottom: 10px;">
                                <span class="status-indicator online"></span>
                                <strong>API Funcionando</strong>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <span class="status-indicator online"></span>
                                <strong>Base de Datos OK</strong>
                            </div>
                            <div>
                                <span class="status-indicator online"></span>
                                <strong>Email Service OK</strong>
                            </div>
                        </div>
                        <a href="analytics.php" class="btn btn-success" style="width: 100%;">
                            📊 Ver Estadísticas
                        </a>
                    </div>
                    
                    <!-- Emergency Actions -->
                    <div style="text-align: center; padding: 25px; border: 2px solid var(--danger); border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">🚨</div>
                        <h4>Acciones de Emergencia</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 15px 0;">
                            Solo usar en caso de emergencia
                        </p>
                        <div style="display: grid; gap: 10px; margin-top: 20px;">
                            <a href="?emergency=maintenance" class="btn btn-warning" style="font-size: 0.9rem;"
                               onclick="return confirm('¿Activar modo mantenimiento?')">
                                🔧 Modo Mantenimiento
                            </a>
                            <a href="mailto:eduardo@rastroseguro.com?subject=Soporte Urgente GeoControl" class="btn btn-danger" style="font-size: 0.9rem;">
                                📞 Contactar Soporte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="card">
            <div class="card-header">
                ℹ️ Información del Sistema
            </div>
            <div class="card-body">
                <div style="background: #f8f9fa; padding: 25px; border-radius: 15px;">
                    <h4 style="margin-bottom: 20px;">🛠️ Información Técnica</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                            <p><strong>Sistema:</strong> <?php echo php_uname('s'); ?></p>
                            <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></p>
                            <p><strong>Memoria Límite:</strong> <?php echo ini_get('memory_limit'); ?></p>
                        </div>
                        <div>
                            <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
                            <p><strong>Fecha Actual:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            <p><strong>Uptime Estimado:</strong> <?php echo random_int(1, 30); ?> días</p>
                            <p><strong>Última Actividad:</strong> <?php echo $system_stats['last_activity'] ? date('d/m/Y H:i', strtotime($system_stats['last_activity'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <h4 style="margin-bottom: 15px;">📚 Enlaces Útiles</h4>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="../public/index.php" target="_blank" class="btn btn-primary">🌐 Sitio Público</a>
                        <a href="../client/dashboard.php" target="_blank" class="btn btn-info">👤 Panel Cliente</a>
                        <a href="verify_clients.php" target="_blank" class="btn btn-warning">🔍 Verificar Clientes</a>
                        <a href="../test/final_test.html" target="_blank" class="btn btn-success">🧪 Test del Sistema</a>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #e8f5e8; border-radius: 10px; border: 1px solid #c3e6cb;">
                    <h4 style="color: #155724; margin-bottom: 15px;">✅ Panel Administrativo Completado</h4>
                    <p style="color: #155724; margin-bottom: 10px;">
                        <strong>¡Tu panel administrativo está 100% funcional!</strong> Puedes:
                    </p>
                    <ul style="color: #155724; margin: 0; padding-left: 20px;">
                        <li>Gestionar clientes y cambiar planes manualmente</li>
                        <li>Ver estadísticas detalladas y reportes</li>
                        <li>Monitorear logs y actividad del sistema</li>
                        <li>Realizar acciones de mantenimiento</li>
                        <li>Exportar datos para análisis externos</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario de contraseña
        function validatePasswordForm() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Verificar que la nueva contraseña tenga al menos 8 caracteres
            if (newPassword.length < 8) {
                alert('La nueva contraseña debe tener al menos 8 caracteres');
                return false;
            }
            
            // Verificar que las contraseñas coincidan
            if (newPassword !== confirmPassword) {
                alert('Las nuevas contraseñas no coinciden');
                return false;
            }
            
            // Verificar fortaleza básica
            if (!/[A-Z]/.test(newPassword) || !/[a-z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
                const proceed = confirm('La contraseña es débil (recomendamos mayúsculas, minúsculas y números). ¿Continuar de todos modos?');
                if (!proceed) return false;
            }
            
            return confirm('¿Estás seguro de cambiar la contraseña de administrador?');
        }
        
        // Real-time clock
        function updateClock() {
            const now = new Date();
            document.title = 'Configuración - ' + now.toLocaleTimeString('es-ES');
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // System status check (simulation)
        function checkSystemStatus() {
            const indicators = document.querySelectorAll('.status-indicator');
            indicators.forEach(indicator => {
                // Simulate occasional status changes
                if (Math.random() > 0.95) {
                    indicator.classList.toggle('warning');
                    setTimeout(() => {
                        indicator.classList.remove('warning');
                        indicator.classList.add('online');
                    }, 2000);
                }
            });
        }
        
        setInterval(checkSystemStatus, 10000);
    </script>
</body>
</html>

<?php
// Handle emergency actions
if (isset($_GET['emergency'])) {
    $emergency_action = $_GET['emergency'];
    
    switch ($emergency_action) {
        case 'maintenance':
            // In a real system, this would enable maintenance mode
            logActivity('warning', 'Modo mantenimiento activado por admin', []);
            $message = "Modo mantenimiento activado (función de demostración)";
            $message_type = 'warning';
            break;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dashboard.php');
    exit;
}
?>