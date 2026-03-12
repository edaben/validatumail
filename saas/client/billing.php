<?php
/**
 * Facturación del Cliente
 * 
 * Muestra plan actual y opciones de actualización
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
    
    // Obtener datos del cliente SIEMPRE de la BD (incluyendo payment_url)
    $client = $db->fetchOne(
        "SELECT *, COALESCE(payment_url, 'https://tu-sitio-pagos.com/upgrade') as payment_url FROM clients WHERE id = ?",
        [$client_id]
    );
    
    if (!$client) {
        session_destroy();
        redirectToLogin('Sesión inválida');
    }
    
    // Actualizar sesión con datos frescos
    $_SESSION['client_name'] = $client['name'];
    $_SESSION['client_plan'] = $client['plan'];
    
    // Usar datos de la BD
    $client_name = $client['name'];
    $client_plan = $client['plan'];
    
    // Obtener límites del plan actual
    $current_plan = getPlanLimits($client['plan']);
    
    // Obtener todos los planes disponibles
    $all_plans = getSaasConfig('plans');
    
} catch (Exception $e) {
    $error_message = 'Error al cargar información de facturación';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - GeoControl SaaS</title>
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
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Plan Cards */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .plan-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }

        .plan-card.current {
            border-color: #28a745;
            background: #f8fff9;
        }

        .plan-card.popular {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .plan-price-period {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .plan-features {
            text-align: left;
            margin-bottom: 25px;
        }

        .plan-features li {
            margin: 8px 0;
            color: #666;
        }

        .current-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .popular-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .usage-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 10px;
            margin: 10px 0;
            overflow: hidden;
        }

        .usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .contact-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
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
            <a href="settings.php" class="nav-item">
                <i>⚙️</i> Configuración
            </a>
            <a href="billing.php" class="nav-item active">
                <i>💳</i> Facturación
            </a>
            <a href="upgrade_plan.php" class="nav-item">
                <i>🚀</i> Mejorar Plan
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>💳 Facturación</h1>
            <p>Gestiona tu plan y facturación</p>
        </div>

        <!-- Current Plan -->
        <div class="card">
            <div class="card-header">
                <h3>📊 Tu Plan Actual: <?php echo ucfirst($client['plan']); ?></h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h4>📈 Uso Mensual</h4>
                        <p>
                            <strong><?php echo number_format($client['monthly_usage']); ?></strong> de 
                            <strong><?php echo $client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit']); ?></strong> validaciones
                        </p>
                        
                        <?php if ($client['monthly_limit'] > 0): ?>
                        <?php $usage_percent = round(($client['monthly_usage'] / $client['monthly_limit']) * 100, 1); ?>
                        <div class="usage-bar">
                            <div class="usage-fill" style="width: <?php echo min($usage_percent, 100); ?>%"></div>
                        </div>
                        <small><?php echo $usage_percent; ?>% utilizado</small>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h4>🌐 Sitios Web</h4>
                        <p>
                            <strong><?php echo $current_plan['websites_limit'] == -1 ? '∞' : $current_plan['websites_limit']; ?></strong> sitios permitidos
                        </p>
                        
                        <h4 style="margin-top: 15px;">💰 Precio</h4>
                        <p>
                            <strong>$<?php echo $current_plan['price']; ?></strong> / mes
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Plans -->
        <div class="card">
            <div class="card-header">
                <h3>🚀 Planes Disponibles</h3>
            </div>
            <div class="card-body">
                <div class="plans-grid">
                    <?php foreach ($all_plans as $plan_key => $plan): ?>
                    <div class="plan-card <?php echo $plan_key === $client['plan'] ? 'current' : ''; ?> <?php echo $plan_key === 'premium' ? 'popular' : ''; ?>">
                        
                        <?php if ($plan_key === $client['plan']): ?>
                        <div class="current-badge">Plan Actual</div>
                        <?php elseif ($plan_key === 'premium'): ?>
                        <div class="popular-badge">Más Popular</div>
                        <?php endif; ?>
                        
                        <div class="plan-name"><?php echo $plan['name']; ?></div>
                        <div class="plan-price">$<?php echo $plan['price']; ?></div>
                        <div class="plan-price-period"><?php echo $plan['price'] > 0 ? 'por mes' : 'gratis'; ?></div>
                        
                        <ul class="plan-features">
                            <?php foreach ($plan['features'] as $feature): ?>
                            <li>✅ <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if ($plan_key !== $client['plan']): ?>
                        <?php
                        $payment_url = $client['payment_url'];
                        $redirect_url = $payment_url . '?plan=' . $plan_key . '&client_id=' . urlencode($client['client_id']) . '&client_name=' . urlencode($client['name']) . '&current_plan=' . $client['plan'] . '&price=' . $plan['price'];
                        ?>
                        <div class="contact-info">
                            <a href="<?php echo htmlspecialchars($redirect_url); ?>"
                               target="_blank"
                               style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;">
                                💰 Pagar <?php echo $plan['name']; ?>
                            </a>
                        </div>
                        <?php else: ?>
                        <div style="background: #28a745; color: white; padding: 12px; border-radius: 6px; font-weight: 600;">
                            ✅ Plan Actual
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Contact for Billing -->
        <div class="card">
            <div class="card-header">
                <h3>💬 Soporte de Facturación</h3>
            </div>
            <div class="card-body">
                <div class="contact-info">
                    <h4>¿Necesitas cambiar tu plan?</h4>
                    <p>Puedes mejorar tu plan directamente o contactarnos para consultas especiales.</p>
                    <br>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="upgrade_plan.php"
                           style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                            🚀 Ver Planes
                        </a>
                        <a href="mailto:eduardo@rastroseguro.com?subject=Consulta Facturación - <?php echo htmlspecialchars($client['name']); ?>"
                           style="background: #6c757d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                            📧 Contactar Soporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>