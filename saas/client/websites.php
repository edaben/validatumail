<?php
/**
 * Gestión de Sitios Web del Cliente
 * 
 * Permite al cliente gestionar sus dominios protegidos
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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = SaasDatabase::getInstance();
        
        if ($action === 'add_website') {
            $domain = sanitizeInput($_POST['domain'] ?? '');
            
            if (empty($domain)) {
                throw new Exception('El dominio es obligatorio');
            }
            
            // Limpiar el dominio
            $clean_domain = preg_replace('/^https?:\/\//', '', $domain);
            $clean_domain = preg_replace('/^www\./', '', $clean_domain);
            $clean_domain = trim($clean_domain, '/');
            
            if (!validateDomain($clean_domain)) {
                throw new Exception('Formato de dominio inválido');
            }
            
            // Verificar límites del plan
            $plan_limits = getPlanLimits($client_plan);
            $current_websites = $db->fetchOne(
                "SELECT COUNT(*) as count FROM client_websites WHERE client_id = ?",
                [$client_id]
            )['count'];
            
            if ($plan_limits['websites_limit'] > 0 && $current_websites >= $plan_limits['websites_limit']) {
                throw new Exception('Has alcanzado el límite de sitios web de tu plan');
            }
            
            // Verificar si ya existe
            $existing = $db->fetchOne(
                "SELECT id FROM client_websites WHERE client_id = ? AND domain = ?",
                [$client_id, $clean_domain]
            );
            
            if ($existing) {
                throw new Exception('Este dominio ya está agregado');
            }
            
            // Insertar sitio web
            $db->query(
                "INSERT INTO client_websites (client_id, domain, is_verified, is_active, created_at) 
                 VALUES (?, ?, FALSE, TRUE, NOW())",
                [$client_id, $clean_domain]
            );
            
            logActivity('info', 'Sitio web agregado', [
                'domain' => $clean_domain
            ], $client_id);
            
            $message = "Sitio web agregado exitosamente: $clean_domain";
            $message_type = 'success';
            
        } elseif ($action === 'toggle_status') {
            $website_id = (int)($_POST['website_id'] ?? 0);
            
            $website = $db->fetchOne(
                "SELECT * FROM client_websites WHERE id = ? AND client_id = ?",
                [$website_id, $client_id]
            );
            
            if (!$website) {
                throw new Exception('Sitio web no encontrado');
            }
            
            $new_status = $website['is_active'] ? 0 : 1;
            
            $db->query(
                "UPDATE client_websites SET is_active = ? WHERE id = ?",
                [$new_status, $website_id]
            );
            
            $status_text = $new_status ? 'activado' : 'desactivado';
            $message = "Sitio web $status_text: {$website['domain']}";
            $message_type = 'success';
            
            logActivity('info', "Sitio web $status_text", [
                'domain' => $website['domain'],
                'new_status' => $new_status
            ], $client_id);
            
        } elseif ($action === 'delete_website') {
            $website_id = (int)($_POST['website_id'] ?? 0);
            
            $website = $db->fetchOne(
                "SELECT * FROM client_websites WHERE id = ? AND client_id = ?",
                [$website_id, $client_id]
            );
            
            if (!$website) {
                throw new Exception('Sitio web no encontrado');
            }
            
            $db->query(
                "DELETE FROM client_websites WHERE id = ?",
                [$website_id]
            );
            
            $message = "Sitio web eliminado: {$website['domain']}";
            $message_type = 'success';
            
            logActivity('info', 'Sitio web eliminado', [
                'domain' => $website['domain']
            ], $client_id);
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        
        logActivity('error', 'Error en gestión de sitios web', [
            'error' => $e->getMessage(),
            'action' => $action
        ], $client_id);
    }
}

try {
    $db = SaasDatabase::getInstance();
    
    // Obtener datos del cliente
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ?",
        [$client_id]
    );
    
    // Obtener sitios web del cliente
    $websites = $db->fetchAll(
        "SELECT w.*, 
                COUNT(al.id) as total_requests,
                COUNT(CASE WHEN al.access_result = 'granted' THEN 1 END) as granted_requests,
                COUNT(CASE WHEN al.access_result = 'denied' THEN 1 END) as denied_requests
         FROM client_websites w
         LEFT JOIN access_logs al ON w.domain = al.page_url AND w.client_id = al.client_id 
                                  AND MONTH(al.created_at) = MONTH(NOW()) 
                                  AND YEAR(al.created_at) = YEAR(NOW())
         WHERE w.client_id = ?
         GROUP BY w.id
         ORDER BY w.created_at DESC",
        [$client_id]
    );
    
    // Obtener límites del plan
    $plan_limits = getPlanLimits($client_plan);
    
} catch (Exception $e) {
    logActivity('error', 'Error cargando sitios web', [
        'error' => $e->getMessage(),
        'client_id' => $client_id
    ], $client_id);
    
    $error_message = 'Error al cargar los datos. Por favor, recarga la página.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Sitios Web - GeoControl SaaS</title>
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

        /* Alerts */
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Cards */
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

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-size: 0.9rem;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Forms */
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

        /* Website Table */
        .websites-table {
            width: 100%;
            border-collapse: collapse;
        }

        .websites-table th,
        .websites-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .websites-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .website-domain {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }

        .website-url {
            color: #667eea;
            text-decoration: none;
        }

        .website-url:hover {
            text-decoration: underline;
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

        .stats-mini {
            display: flex;
            gap: 15px;
            margin-top: 8px;
        }

        .stat-mini {
            font-size: 0.85rem;
            color: #666;
        }

        .stat-mini strong {
            color: #333;
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
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 1.1rem;
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

            .header {
                padding: 15px 20px;
            }

            .websites-table {
                font-size: 0.9rem;
            }

            .websites-table th,
            .websites-table td {
                padding: 10px;
            }

            .modal-content {
                margin: 5% auto;
                width: 95%;
            }
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
            <a href="websites.php" class="nav-item active">
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
        <!-- Header -->
        <div class="header">
            <div>
                <h1>🌐 Mis Sitios Web</h1>
                <p>Gestiona los dominios protegidos por GeoControl</p>
            </div>
            
            <div>
                <span class="plan-badge plan-<?php echo $client_plan; ?>">
                    Plan <?php echo ucfirst($client_plan); ?>
                </span>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Plan Limits Info -->
        <div class="card">
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Límite de sitios web:</strong> 
                        <?php echo count($websites); ?> de <?php echo $plan_limits['websites_limit'] == -1 ? '∞' : $plan_limits['websites_limit']; ?> utilizados
                    </div>
                    <button onclick="openAddModal()" class="btn btn-primary" 
                            <?php echo ($plan_limits['websites_limit'] > 0 && count($websites) >= $plan_limits['websites_limit']) ? 'disabled' : ''; ?>>
                        ➕ Agregar Sitio Web
                    </button>
                </div>
                
                <?php if ($plan_limits['websites_limit'] > 0 && count($websites) >= $plan_limits['websites_limit']): ?>
                <div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                    Has alcanzado el límite de sitios web de tu plan. <a href="billing.php">Actualizar plan</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Websites List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sitios Web Configurados</h3>
                <span style="color: #666; font-size: 0.9rem;">
                    <?php echo count($websites); ?> sitio<?php echo count($websites) != 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($websites)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🌐</div>
                        <h3>No tienes sitios web configurados</h3>
                        <p>Agrega tu primer sitio web para empezar a protegerlo con control geográfico</p>
                        <button onclick="openAddModal()" class="btn btn-primary">
                            ➕ Agregar Primer Sitio Web
                        </button>
                    </div>
                <?php else: ?>
                    <table class="websites-table">
                        <thead>
                            <tr>
                                <th>Dominio</th>
                                <th>Estado</th>
                                <th>Estadísticas (Este Mes)</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($websites as $website): ?>
                            <tr>
                                <td>
                                    <div class="website-domain">
                                        <a href="https://<?php echo htmlspecialchars($website['domain']); ?>" 
                                           target="_blank" class="website-url">
                                            <?php echo htmlspecialchars($website['domain']); ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $website['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $website['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stats-mini">
                                        <div class="stat-mini">
                                            <strong><?php echo number_format($website['total_requests']); ?></strong> total
                                        </div>
                                        <div class="stat-mini">
                                            <strong><?php echo number_format($website['granted_requests']); ?></strong> permitidos
                                        </div>
                                        <div class="stat-mini">
                                            <strong><?php echo number_format($website['denied_requests']); ?></strong> bloqueados
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($website['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="website_id" value="<?php echo $website['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $website['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $website['is_active'] ? '⏸️ Pausar' : '▶️ Activar'; ?>
                                            </button>
                                        </form>
                                        
                                        <button onclick="confirmDelete(<?php echo $website['id']; ?>, '<?php echo htmlspecialchars($website['domain']); ?>')" 
                                                class="btn btn-sm btn-danger">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Website Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">➕ Agregar Nuevo Sitio Web</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_website">
                    
                    <div class="form-group">
                        <label for="domain">Dominio del Sitio Web</label>
                        <input type="text" id="domain" name="domain" 
                               placeholder="ejemplo.com o https://www.ejemplo.com" 
                               required>
                        <small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">
                            Puedes incluir http://, https:// y www. - nosotros lo limpiaremos automáticamente
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        ➕ Agregar Sitio Web
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_website">
        <input type="hidden" name="website_id" id="deleteWebsiteId">
    </form>

    <script>
        // Add Website Modal
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('domain').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('domain').value = '';
        }

        // Delete Confirmation
        function confirmDelete(websiteId, domain) {
            if (confirm(`¿Estás seguro que deseas eliminar el sitio web "${domain}"?\n\nEsta acción no se puede deshacer.`)) {
                document.getElementById('deleteWebsiteId').value = websiteId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeAddModal();
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Mobile responsive button
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header');
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '☰';
            toggleBtn.onclick = toggleSidebar;
            toggleBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; cursor: pointer; margin-right: 15px;';
            header.firstElementChild.prepend(toggleBtn);
        }

        // Auto-cleanup domain input
        document.getElementById('domain').addEventListener('input', function() {
            let value = this.value.toLowerCase();
            // Remove protocols and www automatically
            value = value.replace(/^https?:\/\//, '');
            value = value.replace(/^www\./, '');
            value = value.replace(/\/$/, '');
            this.value = value;
        });
    </script>
</body>
</html>