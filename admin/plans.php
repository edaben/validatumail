
<?php
/**
 * Administración de Planes - Panel Administrativo
 * Permite modificar precios, límites y características de los planes
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
    
    // Procesar actualizaciones de planes
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_plan_config') {
            // Actualizar configuración de plan en el archivo config.php
            $plan_key = $_POST['plan_key'];
            $name = sanitizeInput($_POST['name']);
            $price = (float)$_POST['price'];
            $monthly_limit = (int)$_POST['monthly_limit'];
            $websites_limit = (int)$_POST['websites_limit'];
            $features = array_filter(array_map('trim', explode("\n", $_POST['features'])));
            
            // Construir nueva configuración
            $new_plan_config = [
                'name' => $name,
                'price' => $price,
                'monthly_limit' => $monthly_limit == -1 ? -1 : $monthly_limit,
                'websites_limit' => $websites_limit == -1 ? -1 : $websites_limit,
                'features' => $features
            ];
            
            $message = "Configuración del plan $name actualizada. Nota: Los cambios en el código requieren actualización manual del archivo config.php";
            $message_type = 'warning';
            
        } elseif ($action === 'mass_plan_update') {
            // Actualización masiva de planes de clientes
            $from_plan = $_POST['from_plan'];
            $to_plan = $_POST['to_plan'];
            $reset_usage = isset($_POST['reset_usage']);
            
            if ($from_plan === $to_plan) {
                throw new Exception('El plan origen y destino no pueden ser el mismo');
            }
            
            $plan_config = getSaasConfig('plans')[$to_plan];
            
            $sql = "UPDATE clients SET plan = ?, monthly_limit = ?";
            $params = [$to_plan, $plan_config['monthly_limit']];
            
            if ($reset_usage) {
                $sql .= ", monthly_usage = 0";
            }
            
            $sql .= " WHERE plan = ? AND status = 'active'";
            $params[] = $from_plan;
            
            $result = $db->query($sql, $params);
            $affected_rows = $db->getConnection()->rowCount();
            
            logActivity('info', 'Actualización masiva de planes por admin', [
                'from_plan' => $from_plan,
                'to_plan' => $to_plan,
                'affected_clients' => $affected_rows,
                'reset_usage' => $reset_usage
            ]);
            
            $message = "Se actualizaron $affected_rows clientes del plan $from_plan al plan $to_plan";
            $message_type = 'success';
            
        } elseif ($action === 'apply_plan_to_client') {
            // Aplicar plan específico a cliente específico
            $client_id = $_POST['client_id'];
            $new_plan = $_POST['new_plan'];
            $reset_usage = isset($_POST['reset_usage']);
            
            $plan_config = getSaasConfig('plans')[$new_plan];
            
            $sql = "UPDATE clients SET plan = ?, monthly_limit = ?";
            $params = [$new_plan, $plan_config['monthly_limit']];
            
            if ($reset_usage) {
                $sql .= ", monthly_usage = 0";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $client_id;
            
            $db->query($sql, $params);
            
            // Obtener nombre del cliente para el mensaje
            $client = $db->fetchOne("SELECT name FROM clients WHERE id = ?", [$client_id]);
            
            $message = "Plan de " . htmlspecialchars($client['name']) . " actualizado a " . $plan_config['name'];
            $message_type = 'success';
        }
    }
    
    // Obtener estadísticas de planes
    $plan_stats = $db->fetchAll("
        SELECT plan, COUNT(*) as client_count, SUM(monthly_usage) as total_usage
        FROM clients 
        WHERE status = 'active'
        GROUP BY plan
        ORDER BY FIELD(plan, 'free', 'basic', 'premium', 'enterprise')
    ");
    
    // Obtener clientes para asignación manual
    $all_clients = $db->fetchAll("SELECT id, name, email, plan FROM clients WHERE status = 'active' ORDER BY name");
    
    // Calcular ingresos totales
    $revenue_by_plan = [];
    $total_revenue = 0;
    foreach ($plan_stats as $stat) {
        $plan_price = getSaasConfig('plans')[$stat['plan']]['price'];
        $revenue = $stat['client_count'] * $plan_price;
        $revenue_by_plan[$stat['plan']] = $revenue;
        $total_revenue += $revenue;
    }
    
} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = 'error';
}

$plans = getSaasConfig('plans');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Planes - Admin GeoControl</title>
    
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
        
        /* Plan Cards */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .plan-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            position: relative;
            transition: all 0.3s;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .plan-card.featured {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }
        
        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .plan-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 15px 0;
        }
        
        .plan-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .features-list {
            list-style: none;
            text-align: left;
            margin: 20px 0;
        }
        
        .features-list li {
            padding: 5px 0;
            color: #666;
            font-size: 0.95rem;
        }
        
        .features-list li::before {
            content: "✅ ";
            margin-right: 8px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 700px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 25px 30px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .close {
            color: white;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            opacity: 0.7;
        }
        
        .close:hover {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .plans-grid {
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
            <a href="plans.php" class="nav-item active">
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
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>💎 Administración de Planes</h1>
                <p>Gestiona precios, límites y asignaciones de planes</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
                <a href="?logout=1" class="btn btn-danger">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                <h4>Actualización Masiva</h4>
                <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Cambiar plan a múltiples clientes</p>
                <button onclick="showMassUpdateModal()" class="btn btn-warning">Actualizar Masivamente</button>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 10px;">👤</div>
                <h4>Asignación Individual</h4>
                <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Cambiar plan a cliente específico</p>
                <button onclick="showIndividualPlanModal()" class="btn btn-info">Asignar Plan</button>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 10px;">💰</div>
                <h4>Ingresos Totales</h4>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">$<?php echo number_format($total_revenue); ?></div>
                <p style="font-size: 0.9rem; color: #666;">Ingresos mensuales recurrentes</p>
            </div>
        </div>
        
        <!-- Current Plans Configuration -->
        <div class="card">
            <div class="card-header">
                💎 Configuración Actual de Planes
            </div>
            <div class="card-body">
                <div class="plans-grid">
                    <?php foreach ($plans as $plan_key => $plan): ?>
                    <?php 
                    $plan_client_count = 0;
                    $plan_usage = 0;
                    foreach ($plan_stats as $stat) {
                        if ($stat['plan'] === $plan_key) {
                            $plan_client_count = $stat['client_count'];
                            $plan_usage = $stat['total_usage'];
                            break;
                        }
                    }
                    $plan_revenue = $revenue_by_plan[$plan_key] ?? 0;
                    ?>
                    <div class="plan-card <?php echo $plan_key === 'basic' ? 'featured' : ''; ?>">
                        <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <div class="plan-price">$<?php echo $plan['price']; ?><small>/mes</small></div>
                        
                        <div class="plan-stats">
                            <div><strong><?php echo $plan_client_count; ?></strong> clientes activos</div>
                            <div><strong><?php echo number_format($plan_usage); ?></strong> validaciones usadas</div>
                            <div style="color: var(--success); font-weight: 600;">
                                <strong>$<?php echo number_format($plan_revenue); ?>/mes</strong> ingresos
                            </div>
                        </div>
                        
                        <ul class="features-list">
                            <li><strong>Límite:</strong> <?php echo $plan['monthly_limit'] == -1 ? 'Ilimitado' : number_format($plan['monthly_limit']); ?> validaciones</li>
                            <li><strong>Sitios:</strong> <?php echo $plan['websites_limit'] == -1 ? 'Ilimitados' : $plan['websites_limit']; ?></li>
                            <?php foreach (array_slice($plan['features'], 0, 3) as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <button onclick="showPlanConfigModal('<?php echo $plan_key; ?>')" class="btn btn-primary" style="width: 100%;">
                            ⚙️ Configurar Plan
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Plan Statistics -->
        <div class="card">
            <div class="card-header">
                📊 Estadísticas de Planes
            </div>
            <div class="card-body">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Plan</th>
                            <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Clientes</th>
                            <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Uso Total</th>
                            <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Ingresos/Mes</th>
                            <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">% de Clientes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plan_stats as $stat): ?>
                        <?php 
                        $total_clients = array_sum(array_column($plan_stats, 'client_count'));
                        $percentage = $total_clients > 0 ? ($stat['client_count'] / $total_clients) * 100 : 0;
                        ?>
                        <tr>
                            <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                <span style="font-weight: 600; text-transform: capitalize;"><?php echo $stat['plan']; ?></span>
                            </td>
                            <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                <strong><?php echo number_format($stat['client_count']); ?></strong>
                            </td>
                            <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                <?php echo number_format($stat['total_usage']); ?> validaciones
                            </td>
                            <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                <strong style="color: var(--success);">$<?php echo number_format($revenue_by_plan[$stat['plan']] ?? 0); ?></strong>
                            </td>
                            <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="flex: 1; background: #e9ecef; border-radius: 10px; height: 8px;">
                                        <div style="background: var(--primary); height: 100%; border-radius: 10px; width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                    <span style="font-weight: 600;"><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Mass Plan Update -->
    <div id="massUpdateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🔄 Actualización Masiva de Planes</h2>
                <span class="close" onclick="closeModal('massUpdateModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="mass_plan_update">
                    
                    <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                        <strong>⚠️ Atención:</strong> Esta acción afectará a TODOS los clientes activos del plan seleccionado.
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="from_plan">Desde Plan:</label>
                            <select name="from_plan" id="from_plan" required>
                                <option value="free">Gratuito</option>
                                <option value="basic">Básico</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="to_plan">Hacia Plan:</label>
                            <select name="to_plan" id="to_plan" required>
                                <option value="free">Gratuito</option>
                                <option value="basic">Básico</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="reset_usage" style="margin-right: 8px;">
                            🔄 Resetear uso mensual de todos los clientes afectados
                        </label>
                    </div>
                    
                    <div id="mass-update-preview" style="background: #e9ecef; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        Selecciona los planes para ver el resumen de la actualización.
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end;">
                        <button type="button" onclick="closeModal('massUpdateModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('¿Estás seguro de realizar esta actualización masiva?')">
                            🔄 Actualizar Masivamente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Individual Plan Assignment -->
    <div id="individualPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>👤 Asignar Plan a Cliente Específico</h2>
                <span class="close" onclick="closeModal('individualPlanModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="apply_plan_to_client">
                    
                    <div class="form-group">
                        <label for="client_id">Seleccionar Cliente:</label>
                        <select name="client_id" id="individual_client_id" required>
                            <option value="">-- Seleccionar Cliente --</option>
                            <?php foreach ($all_clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?> - <?php echo htmlspecialchars($client['email']); ?> 
                                (Actual: <?php echo strtoupper($client['plan']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="individual_new_plan">Nuevo Plan:</label>
                        <select name="new_plan" id="individual_new_plan" required>
                            <option value="free">Gratuito - $0/mes (50 validaciones)</option>
                            <option value="basic">Básico - $19/mes (500 validaciones)</option>
                            <option value="premium">Premium - $49/mes (5,000 validaciones)</option>
                            <option value="enterprise">Enterprise - $149/mes (Ilimitado)</option>
                        </select>
                    </div>
                    
                
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="reset_usage" style="margin-right: 8px;">
                            🔄 Resetear uso mensual del cliente
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end;">
                        <button type="button" onclick="closeModal('individualPlanModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-success">👤 Asignar Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Plan Configuration -->
    <div id="planConfigModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚙️ Configurar Plan</h2>
                <span class="close" onclick="closeModal('planConfigModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                    <strong>💡 Nota:</strong> Los cambios en precios y límites requieren modificación manual del archivo config.php. 
                    Esta vista es para referencia y planificación.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_plan_config">
                    <input type="hidden" name="plan_key" id="config_plan_key">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="config_name">Nombre del Plan:</label>
                            <input type="text" name="name" id="config_name" required>
                        </div>
                        <div class="form-group">
                            <label for="config_price">Precio Mensual ($):</label>
                            <input type="number" name="price" id="config_price" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="config_monthly_limit">Límite Mensual:</label>
                            <input type="number" name="monthly_limit" id="config_monthly_limit" min="-1" 
                                   placeholder="-1 para ilimitado">
                        </div>
                        <div class="form-group">
                            <label for="config_websites_limit">Límite de Sitios Web:</label>
                            <input type="number" name="websites_limit" id="config_websites_limit" min="-1"
                                   placeholder="-1 para ilimitado">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="config_features">Características (una por línea):</label>
                        <textarea name="features" id="config_features" rows="6" 
                                  placeholder="Característica 1&#10;Característica 2&#10;Característica 3"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end;">
                        <button type="button" onclick="closeModal('planConfigModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-warning">⚙️ Actualizar Referencia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showMassUpdateModal() {
            document.getElementById('massUpdateModal').style.display = 'block';
        }
        
        function showIndividualPlanModal() {
            document.getElementById('individualPlanModal').style.display = 'block';
        }
        
        function showPlanConfigModal(planKey) {
            const plans = <?php echo json_encode($plans); ?>;
            const plan = plans[planKey];
            
            document.getElementById('config_plan_key').value = planKey;
            document.getElementById('config_name').value = plan.name;
            document.getElementById('config_price').value = plan.price;
            document.getElementById('config_monthly_limit').value = plan.monthly_limit;
            document.getElementById('config_websites_limit').value = plan.websites_limit;
            document.getElementById('config_features').value = plan.features.join('\n');
            
            document.getElementById('planConfigModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Preview for mass update
        document.getElementById('from_plan').addEventListener('change', updateMassPreview);
        document.getElementById('to_plan').addEventListener('change', updateMassPreview);
        
        function updateMassPreview() {
            const fromPlan = document.getElementById('from_plan').value;
            const toPlan = document.getElementById('to_plan').value;
            const preview = document.getElementById('mass-update-preview');
            
            if (fromPlan && toPlan) {
                const planStats = <?php echo json_encode(array_column($plan_stats, null, 'plan')); ?>;
                const plans = <?php echo json_encode($plans); ?>;
                
                const fromCount = planStats[fromPlan] ? planStats[fromPlan].client_count : 0;
                const fromPrice = plans[fromPlan].price;
                const toPrice = plans[toPlan].price;
                const revenueChange = fromCount * (toPrice - fromPrice);
                
                preview.innerHTML = `
                    <strong>📊 Resumen de la Actualización:</strong><br>
                    • <strong>${fromCount}</strong> clientes serán movidos de <strong>${plans[fromPlan].name}</strong> a <strong>${plans[toPlan].name}</strong><br>
                    • Cambio de ingresos: <strong style="color: ${revenueChange >= 0 ? 'green' : 'red'};">${revenueChange >= 0 ? '+' : ''}$${Math.abs(revenueChange)}/mes</strong><br>
                    • Nuevos límites: <strong>${plans[toPlan].monthly_limit === -1 ? 'Ilimitado' : plans[toPlan].monthly_limit.toLocaleString()}</strong> validaciones/mes
                `;
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
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