
<?php
/**
 * Gestión de Clientes - Panel Administrativo
 * Permite administrar clientes y cambiar planes manualmente
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
$action = $_GET['action'] ?? 'list';
$client_id = $_GET['id'] ?? '';

try {
    $db = SaasDatabase::getInstance();
    
    // Procesar acciones POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = $_POST['action'] ?? '';
        
        switch ($post_action) {
            case 'update_plan':
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
                
                logActivity('info', 'Plan actualizado manualmente por admin', [
                    'client_id' => $client_id,
                    'new_plan' => $new_plan,
                    'reset_usage' => $reset_usage
                ], $client_id);
                
                $message = "Plan actualizado exitosamente a " . $plan_config['name'];
                $message_type = 'success';
                break;
                
            case 'update_status':
                $client_id = $_POST['client_id'];
                $new_status = $_POST['new_status'];
                
                $db->query("UPDATE clients SET status = ? WHERE id = ?", [$new_status, $client_id]);
                
                $message = "Estado del cliente actualizado a $new_status";
                $message_type = 'success';
                break;
                
            case 'reset_usage':
                $client_id = $_POST['client_id'];
                
                $db->query("UPDATE clients SET monthly_usage = 0 WHERE id = ?", [$client_id]);
                
                $message = "Uso mensual reseteado a 0";
                $message_type = 'success';
                break;
                
            case 'add_usage':
                $client_id = $_POST['client_id'];
                $add_amount = (int)$_POST['add_amount'];
                
                $db->query("UPDATE clients SET monthly_usage = monthly_usage + ? WHERE id = ?", [$add_amount, $client_id]);
                
                $message = "Se agregaron $add_amount validaciones al uso mensual";
                $message_type = 'success';
                break;
                
            case 'block_over_limit':
                // Buscar y bloquear clientes que exceden su límite
                $over_limit_clients = $db->fetchAll(
                    "SELECT id, name, email, monthly_usage, monthly_limit FROM clients
                     WHERE monthly_limit > 0 AND monthly_usage >= monthly_limit AND status = 'active'"
                );
                
                $blocked_count = 0;
                foreach ($over_limit_clients as $client) {
                    $db->query("UPDATE clients SET status = 'over_limit' WHERE id = ?", [$client['id']]);
                    
                    logActivity('critical', 'Cliente bloqueado automáticamente por límite excedido', [
                        'client_id' => $client['id'],
                        'client_name' => $client['name'],
                        'usage' => $client['monthly_usage'],
                        'limit' => $client['monthly_limit']
                    ], $client['id']);
                    
                    $blocked_count++;
                }
                
                $message = "Se bloquearon $blocked_count clientes que excedían su límite mensual";
                $message_type = $blocked_count > 0 ? 'success' : 'info';
                if ($blocked_count === 0) {
                    $message = "No hay clientes que excedan su límite mensual";
                }
                break;
                
            case 'delete_client':
                $client_id = $_POST['client_id'];
                $confirm_text = $_POST['confirm_delete'] ?? '';
                
                if ($confirm_text !== 'ELIMINAR') {
                    throw new Exception('Confirmación incorrecta. Debe escribir exactamente "ELIMINAR"');
                }
                
                // Obtener información del cliente antes de eliminar
                $client_info = $db->fetchOne("SELECT name, email FROM clients WHERE id = ?", [$client_id]);
                
                if (!$client_info) {
                    throw new Exception('Cliente no encontrado');
                }
                
                // Eliminar en orden correcto para evitar problemas de FK
                $db->query("DELETE FROM access_logs WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM api_requests WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM daily_stats WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM country_stats WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM client_websites WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM system_logs WHERE client_id = ?", [$client_id]);
                $db->query("DELETE FROM clients WHERE id = ?", [$client_id]);
                
                logActivity('warning', 'Cliente eliminado por admin', [
                    'deleted_client_id' => $client_id,
                    'deleted_client_name' => $client_info['name'],
                    'deleted_client_email' => $client_info['email'],
                    'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                $message = "Cliente '{$client_info['name']}' ({$client_info['email']}) eliminado completamente del sistema";
                $message_type = 'success';
                break;
                
            case 'create_client':
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $plan = sanitizeInput($_POST['plan']);
                $countries = sanitizeInput($_POST['countries']);
                
                // Verificar que el email no exista
                $existing = $db->fetchOne("SELECT id FROM clients WHERE email = ?", [$email]);
                if ($existing) {
                    throw new Exception('Este email ya está registrado');
                }
                
                // Generar credenciales
                $api_key = generateApiKey();
                $client_uuid = generateClientId();
                $temp_password = generateRandomPassword(12);
                $password_hash = hashPassword($temp_password);
                $plan_config = getSaasConfig('plans')[$plan];
                
                $sql = "INSERT INTO clients (
                    name, email, password_hash, api_key, client_id, status, plan,
                    countries_allowed, access_control_mode, monthly_limit, created_at
                ) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, 'allowed', ?, NOW())";
                
                $params = [
                    $name, $email, $password_hash, $api_key, $client_uuid,
                    $plan, $countries, $plan_config['monthly_limit']
                ];
                
                $db->query($sql, $params);
                
                $message = "Cliente creado exitosamente. Password temporal: $temp_password";
                $message_type = 'success';
                $action = 'list'; // Volver a la lista
                break;
        }
    }
    
    // Obtener datos según la acción
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $plan_filter = $_GET['plan'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($plan_filter) {
            $where_conditions[] = "plan = ?";
            $params[] = $plan_filter;
        }
        
        if ($status_filter) {
            $where_conditions[] = "status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $clients = $db->fetchAll("SELECT * FROM clients $where_clause ORDER BY created_at DESC", $params);
        
    } elseif ($action === 'view' && $client_id) {
        $client = $db->fetchOne("SELECT * FROM clients WHERE id = ?", [$client_id]);
        if (!$client) {
            $message = "Cliente no encontrado";
            $message_type = 'error';
            $action = 'list';
        } else {
            // Obtener estadísticas del cliente
            $client_stats = $db->fetchOne("
                SELECT 
                    COUNT(*) as total_accesses,
                    SUM(CASE WHEN access_result = 'granted' THEN 1 ELSE 0 END) as granted_accesses,
                    SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END) as denied_accesses
                FROM access_logs 
                WHERE client_id = ?
            ", [$client_id]);
            
            // Países más visitados
            $top_countries = $db->fetchAll("
                SELECT country_code, country_name, COUNT(*) as visits
                FROM access_logs 
                WHERE client_id = ? AND country_code IS NOT NULL
                GROUP BY country_code, country_name
                ORDER BY visits DESC
                LIMIT 5
            ", [$client_id]);
            
            // Sitios web del cliente
            $websites = $db->fetchAll("SELECT * FROM client_websites WHERE client_id = ?", [$client_id]);
        }
    }
    
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
    <title>Gestión de Clientes - Admin GeoControl</title>
    
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
        
        /* Forms */
        .form-group {
            margin-bottom: 25px;
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
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
        
        /* Buttons */
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
        .btn-info { background: var(--info); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
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
        
        /* Modal Styles */
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
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
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
            .filters-row {
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
            <a href="clients.php" class="nav-item active">
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
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>👥 Gestión de Clientes</h1>
                <p>Administra clientes y planes manualmente</p>
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
        
        <?php if ($action === 'list'): ?>
        <!-- Filters and Search -->
        <div class="filters">
            <form method="GET" class="filters-row">
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="search" placeholder="🔍 Buscar por nombre o email..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="plan">
                        <option value="">Todos los planes</option>
                        <option value="free" <?php echo ($_GET['plan'] ?? '') === 'free' ? 'selected' : ''; ?>>Gratuito</option>
                        <option value="basic" <?php echo ($_GET['plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Básico</option>
                        <option value="premium" <?php echo ($_GET['plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                        <option value="enterprise" <?php echo ($_GET['plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="status">
                        <option value="">Todos los estados</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                        <option value="suspended" <?php echo ($_GET['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspendidos</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>
        </div>
        
        <!-- Action Buttons -->
        <div style="margin-bottom: 25px; display: flex; gap: 15px;">
            <button onclick="showCreateClientModal()" class="btn btn-success">
                ➕ Crear Cliente Manualmente
            </button>
            <button onclick="blockOverLimitClients()" class="btn btn-danger">
                🚫 Bloquear Clientes con Límite Excedido
            </button>
            <a href="?action=export" class="btn btn-info">
                📤 Exportar Datos
            </a>
            <a href="analytics.php" class="btn btn-warning">
                📊 Ver Estadísticas
            </a>
        </div>
        
        <!-- Clients Table -->
        <div class="card">
            <div class="card-header">
                👥 Lista de Clientes (<?php echo count($clients); ?> encontrados)
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($clients)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Plan</th>
                            <th>Uso Mensual</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($client['email']); ?></small><br>
                                    <small style="color: #999; font-family: monospace;"><?php echo substr($client['client_id'], 0, 12); ?>...</small>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo strtoupper($client['plan']); ?></span><br>
                                <small style="color: #666;">$<?php echo getSaasConfig('plans')[$client['plan']]['price']; ?>/mes</small>
                            </td>
                            <td>
                                <?php 
                                $usage_percent = $client['monthly_limit'] > 0 ? ($client['monthly_usage'] / $client['monthly_limit']) * 100 : 0;
                                $progress_class = $usage_percent > 90 ? 'danger' : ($usage_percent > 70 ? 'warning' : 'success');
                                ?>
                                <div style="font-weight: 600;">
                                    <?php echo number_format($client['monthly_usage']); ?>/<?php echo $client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit']); ?>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo min(100, $usage_percent); ?>%"></div>
                                </div>
                                <small style="color: #666;"><?php echo number_format($usage_percent, 1); ?>% usado</small>
                            </td>
                            <td>
                                <?php if ($client['status'] === 'over_limit'): ?>
                                    <span class="badge badge-danger">
                                        🚫 BLOQUEADO
                                    </span>
                                    <br><small style="color: #dc3545; font-weight: 600;">Límite excedido</small>
                                <?php elseif ($client['monthly_limit'] > 0 && $client['monthly_usage'] >= $client['monthly_limit']): ?>
                                    <span class="badge badge-danger">
                                        ⚠️ LÍMITE ALCANZADO
                                    </span>
                                    <br><small style="color: #dc3545; font-weight: 600;">Requiere bloqueo</small>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo $client['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($client['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="?action=view&id=<?php echo $client['id']; ?>" class="btn btn-primary btn-sm">👁️ Ver</a>
                                    
                                    <?php if ($client['status'] === 'over_limit' || ($client['monthly_limit'] > 0 && $client['monthly_usage'] >= $client['monthly_limit'])): ?>
                                        <button onclick="reactivateClient(<?php echo $client['id']; ?>)" class="btn btn-success btn-sm">
                                            🔓 Reactivar
                                        </button>
                                        <button onclick="resetUsage(<?php echo $client['id']; ?>)" class="btn btn-info btn-sm">
                                            🔄 Reset Uso
                                        </button>
                                    <?php else: ?>
                                        <button onclick="showEditPlanModal(<?php echo $client['id']; ?>, '<?php echo $client['plan']; ?>', '<?php echo htmlspecialchars($client['name']); ?>')"
                                                class="btn btn-warning btn-sm">💎 Plan</button>
                                        <button onclick="showQuickActions(<?php echo $client['id']; ?>)" class="btn btn-info btn-sm">⚡ Acciones</button>
                                    <?php endif; ?>
                                    
                                    <button onclick="showDeleteClientModal(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>', '<?php echo htmlspecialchars($client['email']); ?>')"
                                            class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #666;">
                    <div style="font-size: 3rem; margin-bottom: 20px;">📭</div>
                    <h3>No se encontraron clientes</h3>
                    <p>Intenta cambiar los filtros o crear un nuevo cliente.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($action === 'view' && isset($client)): ?>
        <!-- Client Detail View -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Client Info -->
            <div class="card">
                <div class="card-header">
                    👤 Información del Cliente
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                        <div>
                            <h3 style="margin-bottom: 20px;">📋 Datos Básicos</h3>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                            <p><strong>País:</strong> <?php echo htmlspecialchars($client['country']); ?></p>
                            <p><strong>Empresa:</strong> <?php echo htmlspecialchars($client['company']); ?></p>
                            <p><strong>Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($client['created_at'])); ?></p>
                        </div>
                        <div>
                            <h3 style="margin-bottom: 20px;">⚙️ Configuración</h3>
                            <p><strong>Client ID:</strong> <code style="font-size: 0.8rem;"><?php echo $client['client_id']; ?></code></p>
                            <p><strong>Plan:</strong> <span class="badge badge-primary"><?php echo strtoupper($client['plan']); ?></span></p>
                            <p><strong>Estado:</strong> <span class="badge badge-<?php echo $client['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo strtoupper($client['status']); ?></span></p>
                            <p><strong>Modo Control:</strong> <?php echo $client['access_control_mode']; ?></p>
                            <p><strong>Acción Denegado:</strong> <?php echo $client['access_denied_action']; ?></p>
                        </div>
                    </div>
                    
                    <!-- Usage Progress -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px;">📊 Uso Mensual</h4>
                        <?php 
                        $usage_percent = $client['monthly_limit'] > 0 ? ($client['monthly_usage'] / $client['monthly_limit']) * 100 : 0;
                        $progress_class = $usage_percent > 90 ? 'danger' : ($usage_percent > 70 ? 'warning' : 'success');
                        ?>
                        <div style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">
                            <?php echo number_format($client['monthly_usage']); ?>/<?php echo $client['monthly_limit'] == -1 ? '∞' : number_format($client['monthly_limit']); ?> validaciones
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo min(100, $usage_percent); ?>%"></div>
                        </div>
                        <small style="color: #666;"><?php echo number_format($usage_percent, 1); ?>% del límite mensual utilizado</small>
                    </div>
                    
                    <!-- Countries Configuration -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px
; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px;">🌍 Configuración de Países</h4>
                        <p><strong>Modo:</strong> <?php echo $client['access_control_mode'] === 'allowed' ? 'Lista Blanca' : 'Lista Negra'; ?></p>
                        <p><strong>Países Permitidos:</strong> <?php echo $client['countries_allowed'] ?: 'Ninguno'; ?></p>
                        <p><strong>Países Denegados:</strong> <?php echo $client['countries_denied'] ?: 'Ninguno'; ?></p>
                        <p><strong>VPN:</strong> <?php echo $client['allow_vpn'] ? '✅ Permitido' : '❌ Bloqueado'; ?></p>
                        <p><strong>Proxy:</strong> <?php echo $client['allow_proxy'] ? '✅ Permitido' : '❌ Bloqueado'; ?></p>
                    </div>
                    
                    <!-- Quick Actions for this client -->
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button onclick="showEditPlanModal(<?php echo $client['id']; ?>, '<?php echo $client['plan']; ?>', '<?php echo htmlspecialchars($client['name']); ?>')"
                                class="btn btn-warning">💎 Cambiar Plan</button>
                        <button onclick="resetUsage(<?php echo $client['id']; ?>)" class="btn btn-info">🔄 Reset Uso</button>
                        <button onclick="toggleStatus(<?php echo $client['id']; ?>, '<?php echo $client['status']; ?>')"
                                class="btn btn-<?php echo $client['status'] === 'active' ? 'danger' : 'success'; ?>">
                            <?php echo $client['status'] === 'active' ? '⏸️ Suspender' : '▶️ Activar'; ?>
                        </button>
                        <button onclick="showDeleteClientModal(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>', '<?php echo htmlspecialchars($client['email']); ?>')"
                                class="btn btn-danger">🗑️ Eliminar Cliente</button>
                    </div>
                </div>
            </div>
            
            <!-- Client Statistics -->
            <div>
                <div class="card">
                    <div class="card-header">
                        📈 Estadísticas del Cliente
                    </div>
                    <div class="card-body">
                        <?php if ($client_stats): ?>
                        <div style="text-align: center; margin-bottom: 25px;">
                            <div style="font-size: 2rem; font-weight: 800; color: var(--primary);">
                                <?php echo number_format($client_stats['total_accesses']); ?>
                            </div>
                            <div style="color: #666; font-weight: 600;">Total Accesos</div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                            <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #155724;">
                                    <?php echo number_format($client_stats['granted_accesses']); ?>
                                </div>
                                <small style="color: #155724;">Permitidos</small>
                            </div>
                            <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #721c24;">
                                    <?php echo number_format($client_stats['denied_accesses']); ?>
                                </div>
                                <small style="color: #721c24;">Bloqueados</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Top Countries -->
                        <h4 style="margin-bottom: 15px;">🌍 Países Principales</h4>
                        <?php if (!empty($top_countries)): ?>
                        <?php foreach ($top_countries as $country): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span><?php echo $country['country_code']; ?> - <?php echo htmlspecialchars($country['country_name']); ?></span>
                            <span style="font-weight: 600;"><?php echo number_format($country['visits']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p style="color: #666; text-align: center;">Sin datos de países aún</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Websites -->
                <div class="card">
                    <div class="card-header">
                        🌐 Sitios Web Registrados
                    </div>
                    <div class="card-body">
                        <?php if (!empty($websites)): ?>
                        <?php foreach ($websites as $website): ?>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                            <div style="font-weight: 600; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($website['domain']); ?>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <span class="badge badge-<?php echo $website['is_verified'] ? 'success' : 'warning'; ?>">
                                    <?php echo $website['is_verified'] ? 'Verificado' : 'Sin Verificar'; ?>
                                </span>
                                <span class="badge badge-<?php echo $website['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $website['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p style="color: #666; text-align: center;">Sin sitios web registrados</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="clients.php" class="btn btn-primary">← Volver a Lista de Clientes</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Edit Plan -->
    <div id="editPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>💎 Cambiar Plan de Cliente</h2>
                <span class="close" onclick="closeModal('editPlanModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editPlanForm">
                    <input type="hidden" name="action" value="update_plan">
                    <input type="hidden" name="client_id" id="edit_client_id">
                    
                    <div class="form-group">
                        <label>Cliente:</label>
                        <div id="edit_client_name" style="font-weight: 600; color: var(--primary);"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_plan">Nuevo Plan:</label>
                        <select name="new_plan" id="new_plan" required>
                            <option value="free">Gratuito - $0/mes (50 validaciones)</option>
                            <option value="basic">Básico - $19/mes (500 validaciones)</option>
                            <option value="premium">Premium - $49/mes (5,000 validaciones)</option>
                            <option value="enterprise">Enterprise - $149/mes (Ilimitado)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="reset_usage" style="margin-right: 8px;">
                            🔄 Resetear uso mensual a 0
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end;">
                        <button type="button" onclick="closeModal('editPlanModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-success">💎 Actualizar Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Create Client -->
    <div id="createClientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Crear Cliente Manualmente</h2>
                <span class="close" onclick="closeModal('createClientModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_client">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_name">Nombre Completo *</label>
                            <input type="text" name="name" id="create_name" required>
                        </div>
                        <div class="form-group">
                            <label for="create_email">Email *</label>
                            <input type="email" name="email" id="create_email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_plan">Plan Inicial *</label>
                            <select name="plan" id="create_plan" required>
                                <option value="free">Gratuito</option>
                                <option value="basic">Básico</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_countries">Países Permitidos</label>
                            <input type="text" name="countries" id="create_countries" placeholder="EC,CO,US,ES (códigos ISO)" value="EC,CO,US,ES">
                        </div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <strong>💡 Nota:</strong> Se generará una contraseña temporal que será mostrada después de crear el cliente.
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end;">
                        <button type="button" onclick="closeModal('createClientModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-success">➕ Crear Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Quick Actions -->
    <div id="quickActionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚡ Acciones Rápidas</h2>
                <span class="close" onclick="closeModal('quickActionsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Reset Usage -->
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--info); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                        <h4>Resetear Uso Mensual</h4>
                        <p style="font-size: 0.9rem; color: #666; margin: 10px 0;">Poner contador a 0</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reset_usage">
                            <input type="hidden" name="client_id" id="quick_client_id_reset">
                            <button type="submit" class="btn btn-info">🔄 Resetear</button>
                        </form>
                    </div>
                    
                    <!-- Add Usage -->
                    <div style="text-align: center; padding: 20px; border: 2px solid var(--warning); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">➕</div>
                        <h4>Agregar Validaciones</h4>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="add_usage">
                            <input type="hidden" name="client_id" id="quick_client_id_add">
                            <input type="number" name="add_amount" placeholder="Cantidad" min="1" max="10000" required 
                                   style="width: 100%; padding: 8px; margin-bottom: 10px; text-align: center;">
                            <button type="submit" class="btn btn-warning">➕ Agregar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Client -->
    <div id="deleteClientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                <h2>🗑️ Eliminar Cliente - ACCIÓN IRREVERSIBLE</h2>
                <span class="close" onclick="closeModal('deleteClientModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div style="background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="color: #721c24; margin-bottom: 15px;">⚠️ ADVERTENCIA CRÍTICA</h3>
                    <p style="color: #721c24; font-weight: 600; margin-bottom: 10px;">
                        Esta acción eliminará PERMANENTEMENTE:
                    </p>
                    <ul style="color: #721c24; margin: 15px 0; padding-left: 20px;">
                        <li>El cliente y toda su información</li>
                        <li>Todos sus logs de acceso y estadísticas</li>
                        <li>Sus sitios web registrados</li>
                        <li>Todo el historial de API requests</li>
                        <li>Sus configuraciones de países</li>
                    </ul>
                    <p style="color: #721c24; font-weight: 600;">
                        <strong>Esta acción NO SE PUEDE DESHACER.</strong>
                    </p>
                </div>

                <form method="POST" id="deleteClientForm">
                    <input type="hidden" name="action" value="delete_client">
                    <input type="hidden" name="client_id" id="delete_client_id">
                    
                    <div class="form-group">
                        <label>Cliente a eliminar:</label>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">
                            <div style="font-weight: 700; font-size: 1.1rem; color: #dc3545;" id="delete_client_name"></div>
                            <div style="color: #666;" id="delete_client_email"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_delete">Para confirmar, escriba exactamente "ELIMINAR" (en mayúsculas):</label>
                        <input type="text" name="confirm_delete" id="confirm_delete"
                               placeholder="Escriba: ELIMINAR" required
                               style="border: 2px solid #dc3545; background: #fff5f5;">
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: end; margin-top: 30px;">
                        <button type="button" onclick="closeModal('deleteClientModal')" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-danger" onclick="return confirmDeleteClient()">
                            🗑️ ELIMINAR PERMANENTEMENTE
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showEditPlanModal(clientId, currentPlan, clientName) {
            document.getElementById('edit_client_id').value = clientId;
            document.getElementById('edit_client_name').textContent = clientName;
            document.getElementById('new_plan').value = currentPlan;
            document.getElementById('editPlanModal').style.display = 'block';
        }
        
        function showCreateClientModal() {
            document.getElementById('createClientModal').style.display = 'block';
        }
        
        function showQuickActions(clientId) {
            document.getElementById('quick_client_id_reset').value = clientId;
            document.getElementById('quick_client_id_add').value = clientId;
            document.getElementById('quickActionsModal').style.display = 'block';
        }
        
        function showDeleteClientModal(clientId, clientName, clientEmail) {
            document.getElementById('delete_client_id').value = clientId;
            document.getElementById('delete_client_name').textContent = clientName;
            document.getElementById('delete_client_email').textContent = clientEmail;
            document.getElementById('confirm_delete').value = ''; // Limpiar campo
            document.getElementById('deleteClientModal').style.display = 'block';
        }
        
        function confirmDeleteClient() {
            const confirmText = document.getElementById('confirm_delete').value;
            const clientName = document.getElementById('delete_client_name').textContent;
            
            if (confirmText !== 'ELIMINAR') {
                alert('ERROR: Debe escribir exactamente "ELIMINAR" para confirmar');
                return false;
            }
            
            return confirm(`ÚLTIMA CONFIRMACIÓN:\n\n¿Está absolutamente seguro de eliminar PERMANENTEMENTE al cliente "${clientName}" y TODOS sus datos?\n\nEsta acción NO SE PUEDE DESHACER.`);
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function resetUsage(clientId) {
            if (confirm('¿Estás seguro de resetear el uso mensual a 0?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_usage">
                    <input type="hidden" name="client_id" value="${clientId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleStatus(clientId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = currentStatus === 'active' ? 'suspender' : 'activar';
            
            if (confirm(`¿Estás seguro de ${action} este cliente?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="client_id" value="${clientId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function reactivateClient(clientId) {
            if (confirm('¿Estás seguro de reactivar este cliente? Esto le permitirá usar el servicio nuevamente.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="client_id" value="${clientId}">
                    <input type="hidden" name="new_status" value="active">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function blockOverLimitClients() {
            if (confirm('¿Bloquear automáticamente todos los clientes que hayan excedido su límite mensual?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="block_over_limit">`;
                document.body.appendChild(form);
                form.submit();
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
        
        // Auto-refresh every 2 minutes when viewing list
        <?php if ($action === 'list'): ?>
        setTimeout(() => {
            if (window.location.search.indexOf('action=list') !== -1 || window.location.search === '') {
                window.location.reload();
            }
        }, 120000);
        <?php endif; ?>
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