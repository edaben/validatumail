
<?php
/**
 * Análisis y Reportes del Negocio - Panel Administrativo
 * Estadísticas detalladas y reportes para tomar decisiones
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Autenticación básica de administrador
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

try {
    $db = SaasDatabase::getInstance();
    
    // Período de análisis (último mes por defecto)
    $period = $_GET['period'] ?? '30';
    $date_from = date('Y-m-d', strtotime("-$period days"));
    $date_to = date('Y-m-d');
    
    // Estadísticas generales
    $general_stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT c.id) as total_clients,
            COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_clients,
            COUNT(CASE WHEN c.created_at >= ? THEN 1 END) as new_clients_period,
            SUM(c.monthly_usage) as total_validations,
            AVG(c.monthly_usage) as avg_usage_per_client
        FROM clients c
        WHERE c.created_at <= ?
    ", [$date_from, $date_to]);
    
    // Ingresos por plan
    $revenue_analysis = $db->fetchAll("
        SELECT 
            c.plan,
            COUNT(*) as client_count,
            SUM(c.monthly_usage) as total_usage,
            (SELECT price FROM (
                SELECT 'free' as plan, 0 as price UNION ALL
                SELECT 'basic' as plan, 19 as price UNION ALL
                SELECT 'premium' as plan, 49 as price UNION ALL
                SELECT 'enterprise' as plan, 149 as price
            ) p WHERE p.plan = c.plan) as plan_price
        FROM clients c
        WHERE c.status = 'active'
        GROUP BY c.plan
        ORDER BY plan_price DESC
    ");
    
    // Top 10 países por accesos
    $top_countries = $db->fetchAll("
        SELECT 
            country_code,
            country_name,
            COUNT(*) as total_accesses,
            COUNT(CASE WHEN access_result = 'granted' THEN 1 END) as granted_accesses,
            COUNT(CASE WHEN access_result = 'denied' THEN 1 END) as denied_accesses,
            ROUND((COUNT(CASE WHEN access_result = 'granted' THEN 1 END) / COUNT(*)) * 100, 1) as success_rate
        FROM access_logs 
        WHERE created_at >= ? AND country_code IS NOT NULL
        GROUP BY country_code, country_name
        ORDER BY total_accesses DESC
        LIMIT 10
    ", [$date_from]);
    
    // Tendencia diaria de registros (últimos 30 días)
    $daily_registrations = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as registrations
        FROM clients 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    
    // Tendencia de uso (últimos 30 días)
    $daily_usage = $db->fetchAll("
        SELECT 
            date,
            SUM(total_requests) as total_requests,
            SUM(granted_requests) as granted_requests,
            SUM(denied_requests) as denied_requests
        FROM daily_stats 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY date
        ORDER BY date DESC
    ");
    
    // Clientes más activos
    $top_active_clients = $db->fetchAll("
        SELECT 
            c.name,
            c.email,
            c.plan,
            c.monthly_usage,
            c.monthly_limit,
            ROUND((c.monthly_usage / NULLIF(c.monthly_limit, 0)) * 100, 1) as usage_percentage
        FROM clients c
        WHERE c.status = 'active' AND c.monthly_usage > 0
        ORDER BY c.monthly_usage DESC
        LIMIT 10
    ");
    
    // Clientes cerca del límite
    $clients_near_limit = $db->fetchAll("
        SELECT 
            c.name,
            c.email,
            c.plan,
            c.monthly_usage,
            c.monthly_limit,
            ROUND((c.monthly_usage / c.monthly_limit) * 100, 1) as usage_percentage
        FROM clients c
        WHERE c.status = 'active' 
          AND c.monthly_limit > 0 
          AND (c.monthly_usage / c.monthly_limit) >= 0.8
        ORDER BY usage_percentage DESC
    ");
    
    // Calcular ingresos totales
    $total_monthly_revenue = 0;
    foreach ($revenue_analysis as $revenue) {
        $total_monthly_revenue += $revenue['client_count'] * $revenue['plan_price'];
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis y Reportes - Admin GeoControl</title>
    
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
        .stat-card.danger::before { background: var(--danger); }
        
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
        
        .stat-change {
            font-size: 0.8rem;
            margin-top: 8px;
            font-weight: 600;
        }
        
        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }
        
        /* Cards */
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
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
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
        
        /* Progress Bars */
        .progress {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-bar {
            height: 100%;
            transition: width 0.3s;
        }
        
        .progress-bar.success { background: var(--success); }
        .progress-bar.warning { background: var(--warning); }
        .progress-bar.danger { background: var(--danger); }
        
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
        
        /* Period Selector */
        .period-selector {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .period-btn {
            padding: 10px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .period-btn.active,
        .period-btn:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        /* Charts placeholder */
        .chart-container {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            color: #666;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .period-selector {
                flex-wrap: wrap;
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
            <a href="analytics.php" class="nav-item active">
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
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>📈 Análisis y Reportes</h1>
                <p>Estadísticas detalladas de tu negocio SaaS</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
                <a href="?logout=1" class="btn btn-danger">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <!-- Period Selector -->
        <div class="period-selector">
            <strong>📅 Período de análisis:</strong>
            <a href="?period=7" class="period-btn <?php echo $period === '7' ? 'active' : ''; ?>">7 días</a>
            <a href="?period=30" class="period-btn <?php echo $period === '30' ? 'active' : ''; ?>">30 días</a>
            <a href="?period=90" class="period-btn <?php echo $period === '90' ? 'active' : ''; ?>">90 días</a>
            <span style="color: #666; margin-left: auto;">
                📅 Desde: <?php echo date('d/m/Y', strtotime($date_from)); ?> hasta: <?php echo date('d/m/Y', strtotime($date_to)); ?>
            </span>
        </div>
        
        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-number"><?php echo number_format($general_stats['total_clients']); ?></div>
                <div class="stat-label">Total Clientes</div>
                <div class="stat-change positive">
                    +<?php echo $general_stats['new_clients_period']; ?> nuevos (<?php echo $period; ?> días)
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number"><?php echo number_format($general_stats['active_clients']); ?></div>
                <div class="stat-label">Clientes Activos</div>
                <div class="stat-change">
                    <?php echo $general_stats['total_clients'] > 0 ? round(($general_stats['active_clients'] / $general_stats['total_clients']) * 100, 1) : 0; ?>% del total
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number">$<?php echo number_format($total_monthly_revenue); ?></div>
                <div class="stat-label">Ingresos Mensuales</div>
                <div class="stat-change">
                    $<?php echo number_format($total_monthly_revenue * 12); ?> anuales proyectados
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-number"><?php echo number_format($general_stats['total_validations']); ?></div>
                <div class="stat-label">Validaciones Totales</div>
                <div class="stat-change">
                    Promedio: <?php echo number_format($general_stats['avg_usage_per_client'], 1); ?> por cliente
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-number"><?php echo count($clients_near_limit); ?></div>
                <div class="stat-label">Cerca del Límite</div>
                <div class="stat-change">
                    Clientes con +80% uso
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <!-- Revenue Breakdown -->
            <div class="card">
                <div class="card-header">
                    💰 Análisis de Ingresos por Plan
                </div>
                <div class="card-body">
                    <?php foreach ($revenue_analysis as $revenue): ?>
                    <?php 
                    $plan_revenue = $revenue['client_count'] * $revenue['plan_price'];
                    $revenue_percentage = $total_monthly_revenue > 0 ? ($plan_revenue / $total_monthly_revenue) * 100 : 0;
                    ?>
                    <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e9ecef;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div>
                                <strong style="text-transform: capitalize; font-size: 1.1rem;"><?php echo $revenue['plan']; ?></strong>
                                <span style="color: #666; margin-left: 10px;">(<?php echo $revenue['client_count']; ?> clientes)</span>
                            </div>
                            <div style="text-align: right;">
                                <strong style="color: var(--success); font-size: 1.2rem;">$<?php echo number_format($plan_revenue); ?></strong>
                                <div style="font-size: 0.9rem; color: #666;"><?php echo number_format($revenue_percentage, 1); ?>% del total</div>
                            </div>
                        </div>
                        <div style="background: #e9ecef; border-radius: 10px; height: 8px;">
                            <div style="background: var(--success); height: 100%; border-radius: 10px; width: <?php echo $revenue_percentage; ?>%;"></div>
                        </div>
                        <div style="font-size: 0.9rem; color: #666; margin-top: 8px;">
                            Uso promedio: <?php echo $revenue['client_count'] > 0 ? number_format($revenue['total_usage'] / $revenue['client_count']) : 0; ?> validaciones/cliente
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Top Countries -->
            <div class="card">
                <div class="card-header">
                    🌍 Top Países por Accesos (<?php echo $period; ?> días)
                </div>
                <div class="card-body">
                    <?php if (!empty($top_countries)): ?>
                    <?php foreach ($top_countries as $country): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <div>
                            <strong><?php echo $country['country_code']; ?></strong> - <?php echo htmlspecialchars($country['country_name']); ?>
                            <div style="font-size: 0.9rem; color: #666;">
                                ✅ <?php echo number_format($country['granted_accesses']); ?> permitidos | 
                                ❌ <?php echo number_format($country['denied_accesses']); ?> bloqueados
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 600; font-size: 1.1rem;"><?php echo number_format($country['total_accesses']); ?></div>
                            <div style="font-size: 0.9rem; color: <?php echo $country['success_rate'] > 50 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo $country['success_rate']; ?>% éxito
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">Sin datos de acceso en este período</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Active Clients and Usage -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <!-- Top Active Clients -->
            <div class="card">
                <div class="card-header">
                    🔥 Clientes Más Activos
                </div>
                <div class="card-body">
                    <?php if (!empty($top_active_clients)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_active_clients as $client): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($client['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo strtoupper($client['plan']); ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo number_format($client['monthly_usage']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo $client['usage_percentage'] ?? 0; ?>% del límite
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">Sin datos de uso aún</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Clients Near Limit -->
            <div class="card">
                <div class="card-header">
                    ⚠️ Clientes Cerca del Límite
                </div>
                <div class="card-body">
                    <?php if (!empty($clients_near_limit)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>% Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients_near_limit as $client): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($client['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-warning"><?php echo strtoupper($client['plan']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $progress_class = $client['usage_percentage'] >= 95 ? 'danger' : 'warning';
                                    ?>
                                    <div style="font-weight: 600; color: var(--<?php echo $progress_class; ?>);">
                                        <?php echo $client['usage_percentage']; ?>%
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $client['usage_percentage']; ?>%"></div>
                                    </div>
                                    <small style="color: #666;">
                                        <?php echo number_format($client['monthly_usage']); ?>/<?php echo number_format($client['monthly_limit']); ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; color: var(--success); padding: 30px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                        <h3>¡Excelente!</h3>
                        <p>Ningún cliente está cerca de su límite mensual.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Usage Trends -->
        <div class="card">
            <div class="card-header">
                📊 Tendencias de Uso (Últimos 30 Días)
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <div style="font-size: 3rem; margin-bottom: 20px;">📊</div>
                    <h3>Gráfico de Tendencias</h3>
                    <p>Aquí podrías integrar Chart.js o similar para mostrar gráficos interactivos</p>
                    <p style="margin-top: 15px; color: #666;">
                        <strong>Datos disponibles:</strong> 
                        <?php echo count($daily_usage); ?> días de estadísticas de uso, 
                        <?php echo count($daily_registrations); ?> días de registros
                    </p>
                </div>
                
                <!-- Simple data table as fallback -->
                <?php if (!empty($daily_usage)): ?>
                <div style="margin-top: 30px;">
                    <h4 style="margin-bottom: 20px;">📈 Resumen de
Últimos Días</h4>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Total Requests</th>
                                    <th>Permitidos</th>
                                    <th>Bloqueados</th>
                                    <th>% Éxito</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($daily_usage, 0, 10) as $day): ?>
                                <?php 
                                $success_rate = $day['total_requests'] > 0 ? ($day['granted_requests'] / $day['total_requests']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                    <td><strong><?php echo number_format($day['total_requests']); ?></strong></td>
                                    <td style="color: var(--success);"><?php echo number_format($day['granted_requests']); ?></td>
                                    <td style="color: var(--danger);"><?php echo number_format($day['denied_requests']); ?></td>
                                    <td>
                                        <span style="color: <?php echo $success_rate > 50 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo number_format($success_rate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Export and Actions -->
        <div class="card">
            <div class="card-header">
                📤 Exportar Datos y Acciones
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--info); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 15px;">📊</div>
                        <h4>Exportar Estadísticas</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Descargar CSV con datos completos</p>
                        <a href="?export=stats&period=<?php echo $period; ?>" class="btn btn-info">📊 Exportar Stats</a>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--success); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 15px;">👥</div>
                        <h4>Exportar Clientes</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Lista completa de clientes</p>
                        <a href="?export=clients" class="btn btn-success">👥 Exportar Clientes</a>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--warning); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 15px;">💰</div>
                        <h4>Reporte Financiero</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Análisis de ingresos detallado</p>
                        <a href="?export=revenue&period=<?php echo $period; ?>" class="btn btn-warning">💰 Exportar Ingresos</a>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--primary); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 15px;">📋</div>
                        <h4>Logs de Sistema</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Actividad completa del sistema</p>
                        <a href="logs.php" class="btn btn-primary">📋 Ver Logs</a>
                    </div>
                </div>
                
                <div style="background: #e8f5e8; border: 1px solid #c3e6cb; border-radius: 10px; padding: 20px; margin-top: 30px;">
                    <h4 style="margin-bottom: 15px;">📈 Recomendaciones de Negocio</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php if (count($clients_near_limit) > 0): ?>
                        <li style="margin-bottom: 10px;">
                            <strong>⚠️ Contactar a <?php echo count($clients_near_limit); ?> clientes</strong> que están cerca de su límite para ofrecerles upgrade.
                        </li>
                        <?php endif; ?>
                        
                        <?php if (($general_stats['active_clients'] / max(1, $general_stats['total_clients'])) < 0.8): ?>
                        <li style="margin-bottom: 10px;">
                            <strong>🔄 Reactivación:</strong> <?php echo $general_stats['total_clients'] - $general_stats['active_clients']; ?> clientes inactivos podrían reactivarse.
                        </li>
                        <?php endif; ?>
                        
                        <li style="margin-bottom: 10px;">
                            <strong>💰 Potencial:</strong> Si todos los clientes gratuitos pagaran el plan básico, tendrías 
                            $<?php echo number_format(($plans_counts['free'] ?? 0) * 19); ?> adicionales/mes.
                        </li>
                        
                        <li>
                            <strong>📊 Conversión:</strong> Proyección anual de $<?php echo number_format($total_monthly_revenue * 12); ?> 
                            basada en ingresos actuales.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000);
        
        // Update timestamp in title
        setInterval(() => {
            const now = new Date();
            document.title = 'Analytics - Actualizado: ' + now.toLocaleTimeString('es-ES');
        }, 60000);
    </script>
</body>
</html>

<?php
// Handle exports
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filename = "geocontrol_" . $export_type . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    switch ($export_type) {
        case 'clients':
            fputcsv($output, ['ID', 'Nombre', 'Email', 'Plan', 'Estado', 'Uso Mensual', 'Límite', 'Fecha Registro']);
            $all_clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC");
            foreach ($all_clients as $client) {
                fputcsv($output, [
                    $client['id'],
                    $client['name'],
                    $client['email'],
                    $client['plan'],
                    $client['status'],
                    $client['monthly_usage'],
                    $client['monthly_limit'],
                    $client['created_at']
                ]);
            }
            break;
            
        case 'stats':
            fputcsv($output, ['Fecha', 'Total Requests', 'Permitidos', 'Bloqueados', 'Tasa Éxito']);
            foreach ($daily_usage as $day) {
                $success_rate = $day['total_requests'] > 0 ? ($day['granted_requests'] / $day['total_requests']) * 100 : 0;
                fputcsv($output, [
                    $day['date'],
                    $day['total_requests'],
                    $day['granted_requests'],
                    $day['denied_requests'],
                    number_format($success_rate, 2) . '%'
                ]);
            }
            break;
            
        case 'revenue':
            fputcsv($output, ['Plan', 'Clientes', 'Precio Unitario', 'Ingresos Mensuales', 'Ingresos Anuales']);
            foreach ($revenue_analysis as $revenue) {
                $monthly = $revenue['client_count'] * $revenue['plan_price'];
                fputcsv($output, [
                    $revenue['plan'],
                    $revenue['client_count'],
                    '$' . $revenue['plan_price'],
                    '$' . number_format($monthly),
                    '$' . number_format($monthly * 12)
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dashboard.php');
    exit;
}
?>