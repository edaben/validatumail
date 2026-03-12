<?php
/**
 * Estadísticas del Cliente
 * 
 * Muestra estadísticas detalladas de uso y acceso
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

try {
    $db = SaasDatabase::getInstance();

    // Obtener datos del cliente
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ?",
        [$client_id]
    );

    // Estadísticas generales
    $total_usage = $db->fetchOne(
        "SELECT COUNT(*) as total FROM access_logs WHERE client_id = ?",
        [$client_id]
    )['total'];

    $monthly_usage = $db->fetchOne(
        "SELECT COUNT(*) as total FROM access_logs 
         WHERE client_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        [$client_id]
    )['total'];

    // Estadísticas por país
    $country_stats = $db->fetchAll(
        "SELECT country_code, country_name, access_result, COUNT(*) as count
         FROM access_logs 
         WHERE client_id = ? AND country_code IS NOT NULL
         GROUP BY country_code, country_name, access_result 
         ORDER BY count DESC 
         LIMIT 20",
        [$client_id]
    );

    // Estadísticas diarias (últimos 7 días)
    $daily_stats = $db->fetchAll(
        "SELECT DATE(created_at) as date, 
                COUNT(*) as total,
                COUNT(CASE WHEN access_result = 'granted' THEN 1 END) as granted,
                COUNT(CASE WHEN access_result = 'denied' THEN 1 END) as denied
         FROM access_logs 
         WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date DESC",
        [$client_id]
    );

} catch (Exception $e) {
    $error_message = 'Error al cargar estadísticas';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - GeoControl SaaS</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
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
            <a href="statistics.php" class="nav-item active">
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
        <div class="header">
            <h1>📈 Estadísticas</h1>
            <p>Analiza el uso de tu control geográfico</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_usage); ?></div>
                <div class="stat-label">Total de accesos</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($monthly_usage); ?></div>
                <div class="stat-label">Este mes</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo count($country_stats); ?></div>
                <div class="stat-label">Países únicos</div>
            </div>
        </div>

        <!-- Countries Table -->
        <div class="card">
            <div class="card-header">
                <h3>🌍 Estadísticas por País</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>País</th>
                            <th>Resultado</th>
                            <th>Accesos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($country_stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['country_name'] ?: $stat['country_code']); ?></td>
                                <td>
                                    <?php if ($stat['access_result'] === 'granted'): ?>
                                        <span style="color: green;">✅ Permitido</span>
                                    <?php else: ?>
                                        <span style="color: red;">🚫 Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($stat['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Stats -->
        <div class="card">
            <div class="card-header">
                <h3>📅 Últimos 7 Días</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Permitidos</th>
                            <th>Bloqueados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_stats as $stat): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($stat['date'])); ?></td>
                                <td><?php echo number_format($stat['total']); ?></td>
                                <td style="color: green;"><?php echo number_format($stat['granted']); ?></td>
                                <td style="color: red;"><?php echo number_format($stat['denied']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>