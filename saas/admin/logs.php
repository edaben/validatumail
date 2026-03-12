
<?php
/**
 * Logs del Sistema - Panel Administrativo
 * Visualización completa de logs de acceso y actividad
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
    
    // Filtros
    $filter_client = $_GET['client'] ?? '';
    $filter_result = $_GET['result'] ?? '';
    $filter_country = $_GET['country'] ?? '';
    $filter_date = $_GET['date'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    // Construir WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($filter_client) {
        $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR al.client_uuid LIKE ?)";
        $params[] = "%$filter_client%";
        $params[] = "%$filter_client%";
        $params[] = "%$filter_client%";
    }
    
    if ($filter_result) {
        $where_conditions[] = "al.access_result = ?";
        $params[] = $filter_result;
    }
    
    if ($filter_country) {
        $where_conditions[] = "al.country_code = ?";
        $params[] = $filter_country;
    }
    
    if ($filter_date) {
        $where_conditions[] = "DATE(al.created_at) = ?";
        $params[] = $filter_date;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Obtener logs con paginación
    $logs_query = "
        SELECT 
            al.*,
            c.name as client_name,
            c.email as client_email,
            c.plan as client_plan
        FROM access_logs al
        LEFT JOIN clients c ON al.client_id = c.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $logs = $db->fetchAll($logs_query, $params);
    
    // Contar total para paginación
    $total_logs = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM access_logs al
        LEFT JOIN clients c ON al.client_id = c.id
        $where_clause
    ", $params)['count'];
    
    $total_pages = ceil($total_logs / $per_page);
    
    // Estadísticas rápidas
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_logs,
            COUNT(CASE WHEN access_result = 'granted' THEN 1 END) as granted_count,
            COUNT(CASE WHEN access_result = 'denied' THEN 1 END) as denied_count,
            COUNT(CASE WHEN access_result = 'error' THEN 1 END) as error_count,
            COUNT(DISTINCT client_id) as unique_clients,
            COUNT(DISTINCT country_code) as unique_countries
        FROM access_logs al
        LEFT JOIN clients c ON al.client_id = c.id
        $where_clause
    ", $params);
    
    // Top países en este filtro
    $countries_in_filter = $db->fetchAll("
        SELECT 
            al.country_code,
            al.country_name,
            COUNT(*) as access_count
        FROM access_logs al
        LEFT JOIN clients c ON al.client_id = c.id
        $where_clause
        GROUP BY al.country_code, al.country_name
        ORDER BY access_count DESC
        LIMIT 10
    ", $params);
    
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
    <title>Logs del Sistema - Admin GeoControl</title>
    
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 4px solid;
        }
        
        .stat-card.total { border-left-color: var(--info); }
        .stat-card.granted { border-left-color: var(--success); }
        .stat-card.denied { border-left-color: var(--danger); }
        .stat-card.error { border-left-color: var(--warning); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-label {
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
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
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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
            font-size: 0.85rem;
            text-transform: uppercase;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-primary { background: #cce7ff; color: #004085; }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
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
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
        }
        
        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Alert */
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
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px 25px;
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 0;
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
            <a href="clients.php" class="nav-item">
                <i>👥</i> Gestión de Clientes
            </a>
            <a href="plans.php" class="nav-item">
                <i>💎</i> Administrar Planes
            </a>
            <a href="analytics.php" class="nav-item">
                <i>📈</i> Análisis y Reportes
            </a>
            <a href="logs.php" class="nav-item active">
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
                <h1>📋 Logs del Sistema</h1>
                <p>Monitoreo completo de actividad y accesos</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
                <a href="?export=csv<?php echo http_build_query($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="btn btn-success">📤 Exportar CSV</a>
                <a href="?logout=1" class="btn btn-danger">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo number_format($stats['total_logs']); ?></div>
                <div class="stat-label">Total Logs</div>
            </div>
            
            <div class="stat-card granted">
                <div class="stat-number"><?php echo number_format($stats['granted_count']); ?></div>
                <div class="stat-label">Accesos Permitidos</div>
            </div>
            
            <div class="stat-card denied">
                <div class="stat-number"><?php echo number_format($stats['denied_count']); ?></div>
                <div class="stat-label">Accesos Bloqueados</div>
            </div>
            
            <div class="stat-card error">
                <div class="stat-number"><?php echo number_format($stats['error_count']); ?></div>
                <div class="stat-label">Errores</div>
            </div>
            
            <div class="stat-card" style="border-left-color: var(--info);">
                <div class="stat-number"><?php echo number_format($stats['unique_clients']); ?></div>
                <div class="stat-label">Clientes Únicos</div>
            </div>
            
            <div class="stat-card" style="border-left-color: var(--secondary);">
                <div class="stat-number"><?php echo number_format($stats['unique_countries']); ?></div>
                <div class="stat-label">Países Únicos</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <h3 style="margin-bottom: 20px;">🔍 Filtros de Búsqueda</h3>
            <form method="GET" class="filters-row">
                <div class="form-group">
                    <label>Cliente o Email</label>
                    <input type="text" name="client" placeholder="Buscar cliente..." 
                           value="<?php echo htmlspecialchars($filter_client); ?>">
                </div>
                <div class="form-group">
                    <label>Resultado</label>
                    <select name="result">
                        <option value="">Todos</option>
                        <option value="granted" <?php echo $filter_result === 'granted' ? 'selected' : ''; ?>>Permitidos</option>
                        <option value="denied" <?php echo $filter_result === 'denied' ? 'selected' : ''; ?>>Bloqueados</option>
                        <option value="error" <?php echo $filter_result === 'error' ? 'selected' : ''; ?>>Errores</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>País</label>
                    <select name="country">
                        <option value="">Todos los países</option>
                        <?php foreach ($countries_in_filter as $country): ?>
                        <option value="<?php echo $country['country_code']; ?>" 
                                <?php echo $filter_country === $country['country_code'] ? 'selected' : ''; ?>>
                            <?php echo $country['country_code']; ?> - <?php echo htmlspecialchars($country['country_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                </div>
            </form>
        </div>
        
        <!-- Logs Table -->
        <div class="card">
            <div class="card-header">
                📋 Logs de Acceso (Página <?php echo $page; ?> de <?php echo $total_pages; ?>) - Total: <?php echo number_format($total_logs); ?> registros
            </div>
            <div class="card-body">
                <?php if (!empty($logs)): ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Cliente</th>
                                <th>Resultado</th>
                                <th>Ubicación</th>
                                <th>IP</th>
                                <th>Seguridad</th>
                                <th>URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.9rem;">
                                        <?php echo date('d/m/Y', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['client_name']): ?>
                                    <div style="font-weight: 600; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($log['client_name']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo htmlspecialchars($log['client_email']); ?>
                                    </div>
                                    <span class="badge badge-primary"><?php echo strtoupper($log['client_plan']); ?></span>
                                    <?php else: ?>
                                    <div style="font-family: monospace; font-size: 0.8rem; color: #666;">
                                        <?php echo substr($log['client_uuid'], 0, 16); ?>...
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $log['access_result'] === 'granted' ? 'success' : 
                                            ($log['access_result'] === 'denied' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo strtoupper($log['access_result']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['country_code']): ?>
                                    <div style="font-weight: 600;">
                                        <?php echo $log['country_code']; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo htmlspecialchars($log['country_name']); ?>
                                    </div>
                                    <?php if ($log['city']): ?>
                                    <div style="font-size: 0.8rem; color: #999;">
                                        <?php echo htmlspecialchars($log['city']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-size: 0.8rem;">
                                        <?php echo $log['ip_address']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem;">
                                        <?php if ($log['is_vpn']): ?><span style="color: var(--warning);">🔒 VPN</span><br><?php endif; ?>
                                        <?php if ($log['is_tor']): ?><span style="color: var(--danger);">🧅 Tor</span><br><?php endif; ?>
                                        <?php if ($log['is_proxy']): ?><span style="color: var(--info);">🔄 Proxy</span><br><?php endif; ?>
                                        <?php if (!$log['is_vpn'] && !$log['is_tor'] && !$log['is_proxy']): ?>
                                        <span style="color: var(--success);">✅ Directo</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['page_url']): ?>
                                    <a href="<?php echo htmlspecialchars($log['page_url']); ?>" target="_blank" 
                                       style="color: var(--primary); text-decoration: none; font-size: 0.8rem;"
                                       title="<?php echo htmlspecialchars($log['page_url']); ?>">
                                        🔗 <?php echo parse_url($log['page_url'], PHP_URL_HOST); ?>
                                    </a>
                                    <?php else: ?>
                                    <span style="color: #999; font-size: 0.8rem;">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">Primera</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <?php if ($i === $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Siguiente →</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Última</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #666;">
                    <div style="font-size: 3rem; margin-bottom: 20px;">📭</div>
                    <h3>No se encontraron logs</h3>
                    <p>Intenta cambiar los filtros o verificar que el sistema esté recibiendo accesos.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 2 minutes
        setTimeout(() => {
            if (!window.location.search.includes('page=') || window.location.search.includes('page=1')) {
                window.location.reload();
            }
        }, 120000);
        
        // Real-time clock
        function updateClock() {
            const now = new Date();
            document.title = 'Logs - ' + now.toLocaleTimeString('es-ES');
        }
        
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>

<?php
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Remove export parameter for the query
    $export_params = $_GET;
    unset($export_params['export']);
    
    $filename = "geocontrol_logs_" . date('Y-m-d_H-i') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Fecha/Hora',
        'Cliente',
        'Email',
        'Plan',
        'Resultado',
        'País Código',
        'País Nombre',
        'Ciudad',
        'IP',
        'VPN',
        'Tor',
        'Proxy',
        'URL',
        'Referrer',
        'User Agent'
    ]);
    
    // Get all logs for export (no pagination)
    $export_logs = $db->fetchAll($logs_query, $params);
    
    foreach ($export_logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['client_name'] ?? 'N/A',
            $log['client_email'] ?? 'N/A',
            $log['client_plan'] ?? 'N/A',
            $log['access_result'],
            $log['country_code'] ?? 'N/A',
            $log['country_name'] ?? 'N/A',
            $log['city'] ?? 'N/A',
            $log['ip_address'],
            $log['is_vpn'] ? 'Sí' : 'No',
            $log['is_tor'] ? 'Sí' : 'No',
            $log['is_proxy'] ? 'Sí' : 'No',
            $log['page_url'] ?? 'N/A',
            $log['referrer'] ?? 'N/A',
            $log['user_agent'] ?? 'N/A'
        ]);
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