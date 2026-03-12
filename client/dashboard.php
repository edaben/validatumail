<?php
/**
 * Dashboard Principal del Cliente SaaS
 * 
 * Panel de control donde los clientes gestionan sus configuraciones
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['client_id'])) {
    redirectToLogin('Debes iniciar sesión para acceder');
}

$client_id = $_SESSION['client_id'];

try {
    $db = SaasDatabase::getInstance();
    
    // Obtener datos del cliente SIEMPRE de la BD (no de sesión)
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ?",
        [$client_id]
    );
    
    if (!$client) {
        session_destroy();
        redirectToLogin('Sesión inválida');
    }
    
    // Actualizar sesión con datos frescos de la BD
    $_SESSION['client_name'] = $client['name'];
    $_SESSION['client_plan'] = $client['plan'];
    $_SESSION['client_email'] = $client['email'];
    
    // Usar datos de la BD (no de sesión)
    $client_name = $client['name'];
    $client_plan = $client['plan'];
    
    // Obtener sitios web del cliente
    $websites = $db->fetchAll(
        "SELECT * FROM client_websites WHERE client_id = ? ORDER BY created_at DESC",
        [$client_id]
    );
    
    // Obtener uso mensual actual (de todas las tablas)
    $api_usage = $db->fetchOne(
        "SELECT COUNT(*) as count FROM api_requests
         WHERE client_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        [$client_id]
    )['count'];
    
    $access_usage = $db->fetchOne(
        "SELECT COUNT(*) as count FROM access_logs
         WHERE client_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        [$client_id]
    )['count'];
    
    $monthly_usage = $api_usage + $access_usage;
    
    // Obtener estadísticas de acceso del mes desde access_logs
    $access_stats = $db->fetchAll(
        "SELECT access_result, COUNT(*) as count
         FROM access_logs
         WHERE client_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
         GROUP BY access_result",
        [$client_id]
    );
    
    // Obtener países más visitados
    $country_stats = $db->fetchAll(
        "SELECT country_code, country_name, access_result, COUNT(*) as count
         FROM access_logs
         WHERE client_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
         AND country_code IS NOT NULL
         GROUP BY country_code, access_result
         ORDER BY count DESC
         LIMIT 10",
        [$client_id]
    );
    
    // Calcular porcentaje de uso
    $plan_limits = getPlanLimits($client['plan']);
    $usage_percentage = $plan_limits['monthly_limit'] > 0 
        ? round(($monthly_usage / $plan_limits['monthly_limit']) * 100, 1) 
        : 0;
    
} catch (Exception $e) {
    logActivity('error', 'Error al cargar dashboard', [
        'error' => $e->getMessage(),
        'client_id' => $client_id
    ], $client_id);
    
    $error_message = 'Error al cargar el dashboard. Por favor, recarga la página.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GeoControl SaaS</title>
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
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

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .plan-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .plan-free { background: #e9ecef; color: #495057; }
        .plan-basic { background: #d4edda; color: #155724; }
        .plan-premium { background: #d1ecf1; color: #0c5460; }
        .plan-enterprise { background: #f8d7da; color: #721c24; }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.usage::before { background: #667eea; }
        .stat-card.websites::before { background: #28a745; }
        .stat-card.allowed::before { background: #17a2b8; }
        .stat-card.blocked::before { background: #dc3545; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.7;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .usage-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
        }

        .usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.6s ease;
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
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .card-body {
            padding: 25px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
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
            transform: translateY(-1px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Website List */
        .website-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .website-item:last-child {
            border-bottom: none;
        }

        .website-info {
            flex-grow: 1;
        }

        .website-domain {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .website-status {
            font-size: 0.85rem;
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 15px 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            <a href="dashboard.php" class="nav-item active">
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
            <a href="settings.php" class="nav-item">
                <i>⚙️</i> Configuración
            </a>
            <a href="billing.php" class="nav-item">
                <i>💳</i> Facturación
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="header">
            <div>
                <h1>¡Hola, <?php echo htmlspecialchars($client_name); ?>! 👋</h1>
                <p>Bienvenido a tu panel de control de GeoControl SaaS</p>
            </div>
            
            <div class="user-info">
                <span class="plan-badge plan-<?php echo $client_plan; ?>">
                    Plan <?php echo ucfirst($client_plan); ?>
                </span>
                <a href="../public/logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card usage">
                <div class="stat-header">
                    <span class="stat-title">Uso Mensual</span>
                    <span class="stat-icon">📊</span>
                </div>
                <div class="stat-number"><?php echo number_format($monthly_usage); ?></div>
                <div class="stat-label">
                    de <?php echo $plan_limits['monthly_limit'] == -1 ? '∞' : number_format($plan_limits['monthly_limit']); ?> validaciones
                </div>
                <?php if ($plan_limits['monthly_limit'] > 0): ?>
                <div class="usage-bar">
                    <div class="usage-fill" style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card websites">
                <div class="stat-header">
                    <span class="stat-title">Sitios Web</span>
                    <span class="stat-icon">🌐</span>
                </div>
                <div class="stat-number"><?php echo count($websites); ?></div>
                <div class="stat-label">
                    de <?php echo $plan_limits['websites_limit'] == -1 ? '∞' : $plan_limits['websites_limit']; ?> permitidos
                </div>
            </div>

            <div class="stat-card allowed">
                <div class="stat-header">
                    <span class="stat-title">Accesos Permitidos</span>
                    <span class="stat-icon">✅</span>
                </div>
                <div class="stat-number">
                    <?php
                    $allowed_count = 0;
                    foreach ($access_stats as $stat) {
                        if ($stat['access_result'] === 'granted') {
                            $allowed_count = $stat['count'];
                            break;
                        }
                    }
                    echo number_format($allowed_count);
                    ?>
                </div>
                <div class="stat-label">este mes</div>
            </div>

            <div class="stat-card blocked">
                <div class="stat-header">
                    <span class="stat-title">Accesos Bloqueados</span>
                    <span class="stat-icon">🚫</span>
                </div>
                <div class="stat-number">
                    <?php 
                    $blocked_count = 0;
                    foreach ($access_stats as $stat) {
                        if ($stat['access_result'] === 'denied') {
                            $blocked_count = $stat['count'];
                            break;
                        }
                    }
                    echo number_format($blocked_count);
                    ?>
                </div>
                <div class="stat-label">este mes</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sitios Web -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mis Sitios Web</h3>
                    <a href="websites.php" class="btn btn-primary btn-sm">Gestionar</a>
                </div>
                <div class="card-body">
                    <?php if (empty($websites)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            🌐 Aún no has agregado ningún sitio web.<br>
                            <a href="websites.php" class="btn btn-primary" style="margin-top: 10px;">Agregar Primer Sitio</a>
                        </p>
                    <?php else: ?>
                        <?php foreach (array_slice($websites, 0, 3) as $website): ?>
                        <div class="website-item">
                            <div class="website-info">
                                <div class="website-domain"><?php echo htmlspecialchars($website['domain']); ?></div>
                                <div class="website-status">
                                    Agregado el <?php echo date('d/m/Y', strtotime($website['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $website['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $website['is_active'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($websites) > 3): ?>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="websites.php" class="btn btn-info btn-sm">
                                Ver todos (<?php echo count($websites); ?>)
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Acciones Rápidas</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="websites.php" class="action-card">
                            <div class="action-icon">🌐</div>
                            <strong>Agregar Sitio</strong>
                            <p>Proteger nuevo sitio web</p>
                        </a>
                        
                        <a href="countries.php" class="action-card">
                            <div class="action-icon">🌍</div>
                            <strong>Configurar Países</strong>
                            <p>Permitir/bloquear países</p>
                        </a>
                        
                        <a href="code_generator.php" class="action-card">
                            <div class="action-icon">📋</div>
                            <strong>Generar Código</strong>
                            <p>Obtener código JS</p>
                        </a>
                        
                        <a href="statistics.php" class="action-card">
                            <div class="action-icon">📈</div>
                            <strong>Ver Estadísticas</strong>
                            <p>Analizar el tráfico</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Warning -->
        <?php if ($usage_percentage >= 80 && $plan_limits['monthly_limit'] > 0): ?>
        <div class="card" style="border-left: 4px solid #dc3545;">
            <div class="card-body">
                <h4 style="color: #dc3545; margin-bottom: 10px;">⚠️ Aviso de Uso</h4>
                <p>Has usado el <strong><?php echo $usage_percentage; ?>%</strong> de tu límite mensual. 
                <?php if ($usage_percentage >= 100): ?>
                    Tu servicio está temporalmente limitado.
                <?php endif; ?>
                </p>
                <a href="billing.php" class="btn btn-primary" style="margin-top: 10px;">
                    Actualizar Plan
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            // Solo refrescar las estadísticas, no toda la página
            fetch(window.location.href + '?ajax=stats')
                .then(response => response.json())
                .then(data => {
                    // Actualizar números de estadísticas
                    if (data.monthly_usage !== undefined) {
                        document.querySelector('.stat-card.usage .stat-number').textContent = 
                            new Intl.NumberFormat().format(data.monthly_usage);
                    }
                })
                .catch(error => console.log('Error refreshing stats:', error));
        }, 30000);

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Agregar botón de toggle para móvil
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header');
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '☰';
            toggleBtn.onclick = toggleSidebar;
            toggleBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; cursor: pointer; margin-right: 15px;';
            header.firstElementChild.prepend(toggleBtn);
        }
    </script>
</body>
</html>