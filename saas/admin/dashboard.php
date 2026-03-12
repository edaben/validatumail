<?php
/**
 * Panel Administrativo Principal - GeoControl SaaS
 * Dashboard completo para administrar el negocio
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Autenticación básica de administrador (puedes mejorarlo después)
session_start();
$admin_password = 'admin2024!'; // Cambia esta contraseña

if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        if ($password === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Contraseña incorrecta';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Admin Login - GeoControl SaaS</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; height: 100vh; display: flex; align-items: center; justify-content: center; }
                .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); text-align: center; max-width: 400px; }
                .login-container h1 { color: #333; margin-bottom: 30px; }
                .form-group { margin-bottom: 20px; }
                .form-group input { width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; }
                .btn { background: #667eea; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
                .btn:hover { background: #5a6fd8; }
                .error { color: #dc3545; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h1>🔐 Admin Panel</h1>
                <p>Acceso al panel administrativo</p>
                <form method="POST">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Contraseña de administrador" required>
                    </div>
                    <button type="submit" class="btn">🚀 Acceder</button>
                    <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

try {
    $db = SaasDatabase::getInstance();
    
    // Obtener estadísticas generales
    $total_clients = $db->fetchOne("SELECT COUNT(*) as count FROM clients")['count'];
    $active_clients = $db->fetchOne("SELECT COUNT(*) as count FROM clients WHERE status = 'active'")['count'];
    $total_usage_today = $db->fetchOne("SELECT COALESCE(SUM(total_requests), 0) as total FROM daily_stats WHERE date = CURDATE()")['total'];
    $total_usage_month = $db->fetchOne("SELECT COALESCE(SUM(monthly_usage), 0) as total FROM clients")['total'];
    
    // Clientes por plan
    $plans_stats = $db->fetchAll("SELECT plan, COUNT(*) as count FROM clients GROUP BY plan");
    $plans_counts = [];
    foreach ($plans_stats as $stat) {
        $plans_counts[$stat['plan']] = $stat['count'];
    }
    
    // Ingresos mensuales proyectados
    $revenue_data = $db->fetchAll("
        SELECT c.plan, COUNT(*) as count, p.price 
        FROM clients c
        LEFT JOIN (
            SELECT 'free' as plan, 0 as price UNION ALL
            SELECT 'basic' as plan, 19 as price UNION ALL
            SELECT 'premium' as plan, 49 as price UNION ALL
            SELECT 'enterprise' as plan, 149 as price
        ) p ON c.plan = p.plan
        WHERE c.status = 'active'
        GROUP BY c.plan, p.price
    ");
    
    $monthly_revenue = 0;
    foreach ($revenue_data as $revenue) {
        $monthly_revenue += $revenue['count'] * $revenue['price'];
    }
    
    // Clientes recientes
    $recent_clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC LIMIT 5");
    
    // Top países por accesos
    $top_countries = $db->fetchAll("
        SELECT country_code, country_name, COUNT(*) as accesses 
        FROM access_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY country_code, country_name 
        ORDER BY accesses DESC 
        LIMIT 10
    ");
    
    // Logs recientes
    $recent_logs = $db->fetchAll("
        SELECT al.*, c.name as client_name 
        FROM access_logs al 
        LEFT JOIN clients c ON al.client_id = c.id 
        ORDER BY al.created_at DESC 
        LIMIT 15
    ");
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - GeoControl SaaS</title>
    
    <!-- Google Fonts -->
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
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .sidebar-header p {
            opacity: 0.9;
            font-size: 0.95rem;
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
            font-weight: 500;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
            padding-left: 35px;
        }
        
        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
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
        
        .header h1 {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
        }
        
        .logout-btn {
            background: var(--danger);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.info::before { background: var(--info); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .stat-label {
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            opacity: 0.1;
        }
        
        /* Content Cards */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
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
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-primary { background: #cce7ff; color: #004085; }
        
        /* Action Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .quick-action {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .quick-action:hover {
            transform: translateY(-3px);
        }
        
        .quick-action a {
            text-decoration: none;
            color: inherit;
        }
        
        /* Progress Bars */
        .progress {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--success);
            transition: width 0.3s;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .content-grid {
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
            <a href="dashboard.php" class="nav-item active">
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
            <a href="settings.php" class="nav-item">
                <i>⚙️</i> Configuración
            </a>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="../public/index.php" class="nav-item" target="_blank">
                <i>🌐</i> Ver Sitio Público
            </a>
            <a href="../client/dashboard.php" class="nav-item" target="_blank">
                <i>👤</i> Ver Panel Cliente
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>📊 Dashboard Administrativo</h1>
                <p>Gestiona tu negocio SaaS GeoControl</p>
            </div>
            <div>
                <a href="?logout=1" class="logout-btn">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            ❌ Error: <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action">
                <a href="clients.php?action=add">
                    <div style="font-size: 2rem; margin-bottom: 10px;">➕</div>
                    <strong>Agregar Cliente</strong>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">Crear nueva cuenta</p>
                </a>
            </div>
            <div class="quick-action">
                <a href="plans.php">
                    <div style="font-size: 2rem; margin-bottom: 10px;">💎</div>
                    <strong>Cambiar Planes</strong>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">Actualizar manualmente</p>
                </a>
            </div>
            <div class="quick-action">
                <a href="analytics.php">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📈</div>
                    <strong>Ver Reportes</strong>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">Estadísticas detalladas</p>
                </a>
            </div>
            <div class="quick-action">
                <a href="logs.php">
                    <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
                    <strong>Revisar Logs</strong>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">Actividad del sistema</p>
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo number_format($total_clients); ?></div>
                <div class="stat-label">Total Clientes</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo number_format($active_clients); ?></div>
                <div class="stat-label">Clientes Activos</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$<?php echo number_format($monthly_revenue); ?></div>
                <div class="stat-label">Ingresos Mensuales</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo number_format($total_usage_month); ?></div>
                <div class="stat-label">Validaciones Este Mes</div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Clients -->
            <div class="card">
                <div class="card-header">
                    👥 Clientes Recientes
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_clients)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Uso</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_clients as $client): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($client['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo strtoupper($client['plan']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $usage_percent = $client['monthly_limit'] > 0 ? ($client['monthly_usage'] / $client['monthly_limit']) * 100 : 0;
                                    ?>
                                    <div style="font-size: 0.9rem;"><?php echo $client['monthly_usage']; ?>/<?php echo $client['monthly_limit'] == -1 ? '∞' : $client['monthly_limit']; ?></div>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo min(100, $usage_percent); ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $client['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($client['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="clients.php?id=<?php echo $client['id']; ?>" class="btn btn-primary btn-sm">Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No hay clientes registrados aún.</p>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="clients.php" class="btn btn-primary">Ver Todos los Clientes</a>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Breakdown -->
            <div class="card">
                <div class="card-header">
                    💰 Distribución de Ingresos
                </div>
                <div class="card-body">
                    <?php
                    $plan_colors = [
                        'free' => '#6c757d',
                        'basic' => '#28a745', 
                        'premium' => '#ffc107',
                        'enterprise' => '#dc3545'
                    ];
                    ?>
                    
                    <?php foreach ($revenue_data as $revenue): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 600; text-transform: capitalize;">
                                <?php echo $revenue['plan']; ?> (<?php echo $revenue['count']; ?> clientes)
                            </span>
                            <span style="font-weight: 700; color: var(--success);">
                                $<?php echo number_format($revenue['count'] * $revenue['price']); ?>/mes
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" 
                                 style="width: <?php echo $monthly_revenue > 0 ? (($revenue['count'] * $revenue['price']) / $monthly_revenue) * 100 : 0; ?>%; 
                                        background: <?php echo $plan_colors[$revenue['plan']] ?? '#667eea'; ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">
                            Total: $<?php echo number_format($monthly_revenue); ?>/mes
                        </div>
                        <small style="color: #666;">Ingresos recurrentes mensuales</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                📋 Actividad Reciente del Sistema
            </div>
            <div class="card-body">
                <?php if (!empty($recent_logs)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Resultado</th>
                            <th>País</th>
                            <th>IP</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_logs, 0, 10) as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['client_name']): ?>
                                <strong><?php echo htmlspecialchars($log['client_name']); ?></strong><br>
                                <small style="color: #666; font-family: monospace;"><?php echo substr($log['client_uuid'], 0, 8); ?>...</small>
                                <?php else: ?>
                                <small style="color: #666; font-family: monospace;"><?php echo substr($log['client_uuid'], 0, 12); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $log['access_result'] === 'granted' ? 'success' : ($log['access_result'] === 'denied' ? 'danger' : 'warning'); ?>">
                                    <?php echo strtoupper($log['access_result']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['country_code']): ?>
                                <strong><?php echo $log['country_code']; ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($log['country_name']); ?></small>
                                <?php else: ?>
                                <small style="color: #999;">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="font-family: monospace;"><?php echo $log['ip_address']; ?></small>
                            </td>
                            <td>
                                <small><?php echo date('d/m H:i', strtotime($log['created_at'])); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No hay actividad reciente.</p>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="logs.php" class="btn btn-primary">Ver Todos los Logs</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh cada 30 segundos
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        
        // Mostrar timestamp de última actualización
        const timestamp = new Date().toLocaleString('es-ES');
        document.title = 'Admin Panel - Actualizado: ' + timestamp;
    </script>
</body>
</html>

<?php
// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dashboard.php');
    exit;
}
?>
