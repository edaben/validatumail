<?php
/**
 * Configuración de Países del Cliente
 * 
 * Permite al cliente configurar qué países permitir/bloquear
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

// Procesar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = SaasDatabase::getInstance();
        
        $access_control_mode = sanitizeInput($_POST['access_control_mode'] ?? 'allowed');
        $selected_countries = $_POST['countries'] ?? [];
        $allow_vpn = isset($_POST['allow_vpn']) ? 1 : 0;
        $allow_tor = isset($_POST['allow_tor']) ? 1 : 0;
        $allow_proxy = isset($_POST['allow_proxy']) ? 1 : 0;
        $allow_hosting = isset($_POST['allow_hosting']) ? 1 : 0;
        $access_denied_action = sanitizeInput($_POST['access_denied_action'] ?? 'block_forms');
        $redirect_url = sanitizeInput($_POST['redirect_url'] ?? '');
        
        // Validar URL de redirección si es necesaria
        if ($access_denied_action === 'redirect' && empty($redirect_url)) {
            throw new Exception('La URL de redirección es obligatoria cuando seleccionas "Redirigir"');
        }
        
        if ($access_denied_action === 'redirect' && !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
            throw new Exception('La URL de redirección debe ser válida');
        }
        
        // Preparar listas de países
        $countries_str = implode(',', array_map('strtoupper', $selected_countries));
        
        if ($access_control_mode === 'allowed') {
            $countries_allowed = $countries_str;
            $countries_denied = '';
        } else {
            $countries_allowed = '';
            $countries_denied = $countries_str;
        }
        
        // Actualizar configuración del cliente
        $sql = "UPDATE clients SET 
                countries_allowed = ?,
                countries_denied = ?,
                access_control_mode = ?,
                allow_vpn = ?,
                allow_tor = ?,
                allow_proxy = ?,
                allow_hosting = ?,
                access_denied_action = ?,
                redirect_url = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $countries_allowed,
            $countries_denied,
            $access_control_mode,
            $allow_vpn,
            $allow_tor,
            $allow_proxy,
            $allow_hosting,
            $access_denied_action,
            $redirect_url,
            $client_id
        ];
        
        $db->query($sql, $params);
        
        logActivity('info', 'Configuración de países actualizada', [
            'access_control_mode' => $access_control_mode,
            'countries_count' => count($selected_countries),
            'allow_vpn' => $allow_vpn,
            'access_denied_action' => $access_denied_action
        ], $client_id);
        
        $message = '✅ Configuración de países guardada exitosamente';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        
        logActivity('error', 'Error actualizando configuración de países', [
            'error' => $e->getMessage()
        ], $client_id);
    }
}

try {
    $db = SaasDatabase::getInstance();
    
    // Obtener configuración actual del cliente
    $client = $db->fetchOne(
        "SELECT * FROM clients WHERE id = ?",
        [$client_id]
    );
    
    if (!$client) {
        throw new Exception('Cliente no encontrado');
    }
    
    // Preparar países seleccionados
    $selected_countries = [];
    if ($client['access_control_mode'] === 'allowed' && !empty($client['countries_allowed'])) {
        $selected_countries = explode(',', $client['countries_allowed']);
    } elseif ($client['access_control_mode'] === 'denied' && !empty($client['countries_denied'])) {
        $selected_countries = explode(',', $client['countries_denied']);
    }
    
    // Obtener lista de países
    $countries = getSaasConfig('countries');
    
} catch (Exception $e) {
    logActivity('error', 'Error cargando configuración de países', [
        'error' => $e->getMessage(),
        'client_id' => $client_id
    ], $client_id);
    
    $error_message = 'Error al cargar la configuración. Por favor, recarga la página.';
}

// Grupos de países comunes para facilitar selección
$country_groups = [
    'hispanos' => [
        'name' => 'Países de Habla Hispana',
        'countries' => ['AR', 'BO', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'ES', 'GQ', 'GT', 'HN', 'MX', 'NI', 'PA', 'PE', 'PY', 'SV', 'UY', 'VE']
    ],
    'norte_america' => [
        'name' => 'Norteamérica',
        'countries' => ['US', 'CA', 'MX']
    ],
    'europa' => [
        'name' => 'Europa Occidental',
        'countries' => ['ES', 'FR', 'IT', 'DE', 'GB', 'PT', 'NL', 'BE', 'CH', 'AT']
    ],
    'mercosur' => [
        'name' => 'Mercosur',
        'countries' => ['AR', 'BR', 'PY', 'UY', 'VE']
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Países - GeoControl SaaS</title>
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
        }

        .header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
        }

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

        .alert-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #99d3ff;
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
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .card-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .card-body {
            padding: 25px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Radio Buttons */
        .radio-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .radio-option {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .radio-option:hover {
            border-color: #667eea;
        }

        .radio-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .radio-option input[type="radio"] {
            margin-right: 10px;
        }

        .radio-option-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .radio-option-desc {
            color: #666;
            font-size: 0.9rem;
        }

        /* Country Groups */
        .country-groups {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .group-btn {
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.9rem;
        }

        .group-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .group-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        /* Countries Grid */
        .countries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
        }

        .country-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .country-item:hover {
            background: #f8f9fa;
        }

        .country-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
        }

        .country-item.selected {
            background: #e3f2fd;
        }

        /* Checkboxes */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .checkbox-item:hover {
            border-color: #667eea;
        }

        .checkbox-item.checked {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 12px;
            transform: scale(1.2);
        }

        .checkbox-label {
            flex: 1;
        }

        .checkbox-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .checkbox-desc {
            color: #666;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        /* Search */
        .search-box {
            margin-bottom: 15px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        /* Selection Summary */
        .selection-summary {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .selection-summary h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .selected-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: #667eea;
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

            .radio-group {
                grid-template-columns: 1fr;
            }

            .countries-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .country-groups {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
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
            <a href="websites.php" class="nav-item">
                <i>🌐</i> Mis Sitios Web
            </a>
            <a href="countries.php" class="nav-item active">
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
            <h1>🌍 Configurar Países</h1>
            <p>Define qué países pueden acceder a tus sitios web</p>
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

        <form method="POST">
            <!-- Mode Selection -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Modo de Control de Acceso</h3>
                    <p class="card-subtitle">Elige cómo quieres controlar el acceso geográfico</p>
                </div>
                <div class="card-body">
                    <div class="radio-group">
                        <label class="radio-option <?php echo $client['access_control_mode'] === 'allowed' ? 'selected' : ''; ?>">
                            <input type="radio" name="access_control_mode" value="allowed" 
                                   <?php echo $client['access_control_mode'] === 'allowed' ? 'checked' : ''; ?>>
                            <div>
                                <div class="radio-option-title">✅ Lista Blanca (Recomendado)</div>
                                <div class="radio-option-desc">Solo los países seleccionados pueden acceder. Más seguro.</div>
                            </div>
                        </label>
                        
                        <label class="radio-option <?php echo $client['access_control_mode'] === 'denied' ? 'selected' : ''; ?>">
                            <input type="radio" name="access_control_mode" value="denied"
                                   <?php echo $client['access_control_mode'] === 'denied' ? 'checked' : ''; ?>>
                            <div>
                                <div class="radio-option-title">🚫 Lista Negra</div>
                                <div class="radio-option-desc">Todos los países pueden acceder excepto los seleccionados.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Country Selection -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Seleccionar Países</h3>
                    <p class="card-subtitle">
                        <span id="mode-text">
                            <?php echo $client['access_control_mode'] === 'allowed' ? 'Países que SÍ pueden acceder' : 'Países que NO pueden acceder'; ?>
                        </span>
                    </p>
                </div>
                <div class="card-body">
                    <!-- Selection Summary -->
                    <div class="selection-summary">
                        <h4>Países seleccionados: <span class="selected-count" id="selected-count"><?php echo count($selected_countries); ?></span></h4>
                        <div id="selected-countries-preview"></div>
                    </div>

                    <!-- Quick Selection Groups -->
                    <h4 style="margin-bottom: 15px;">🚀 Selección Rápida</h4>
                    <div class="country-groups">
                        <?php foreach ($country_groups as $group_key => $group): ?>
                        <button type="button" class="group-btn" onclick="selectCountryGroup('<?php echo $group_key; ?>')">
                            <?php echo htmlspecialchars($group['name']); ?>
                        </button>
                        <?php endforeach; ?>
                        
                        <button type="button" class="group-btn" onclick="selectAllCountries()">
                            🌍 Todos los Países
                        </button>
                        
                        <button type="button" class="group-btn" onclick="clearAllCountries()">
                            ❌ Limpiar Selección
                        </button>
                    </div>

                    <!-- Search -->
                    <div class="search-box">
                        <input type="text" id="country-search" placeholder="🔍 Buscar país..." onkeyup="filterCountries()">
                    </div>

                    <!-- Countries Grid -->
                    <div class="countries-grid" id="countries-grid">
                        <?php foreach ($countries as $code => $name): ?>
                        <label class="country-item <?php echo in_array($code, $selected_countries) ? 'selected' : ''; ?>">
                            <input type="checkbox" name="countries[]" value="<?php echo $code; ?>" 
                                   <?php echo in_array($code, $selected_countries) ? 'checked' : ''; ?>
                                   onchange="updateSelection()">
                            <span><?php echo htmlspecialchars($name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Advanced Options -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚙️ Opciones Avanzadas</h3>
                    <p class="card-subtitle">Configuración adicional de seguridad</p>
                </div>
                <div class="card-body">
                    <div class="checkbox-group">
                        <label class="checkbox-item <?php echo $client['allow_vpn'] ? 'checked' : ''; ?>">
                            <input type="checkbox" name="allow_vpn" value="1" 
                                   <?php echo $client['allow_vpn'] ? 'checked' : ''; ?>
                                   onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <div class="checkbox-label">
                                <div class="checkbox-title">🔒 Permitir VPN</div>
                                <div class="checkbox-desc">Usuarios usando redes privadas virtuales</div>
                            </div>
                        </label>

                        <label class="checkbox-item <?php echo $client['allow_tor'] ? 'checked' : ''; ?>">
                            <input type="checkbox" name="allow_tor" value="1"
                                   <?php echo $client['allow_tor'] ? 'checked' : ''; ?>
                                   onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <div class="checkbox-label">
                                <div class="checkbox-title">🕵️ Permitir Tor</div>
                                <div class="checkbox-desc">Usuarios de la red Tor (anónima)</div>
                            </div>
                        </label>

                        <label class="checkbox-item <?php echo $client['allow_proxy'] ? 'checked' : ''; ?>">
                            <input type="checkbox" name="allow_proxy" value="1"
                                   <?php echo $client['allow_proxy'] ? 'checked' : ''; ?>
                                   onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <div class="checkbox-label">
                                <div class="checkbox-title">🌐 Permitir Proxy</div>
                                <div class="checkbox-desc">Usuarios usando servidores proxy</div>
                            </div>
                        </label>

                        <label class="checkbox-item <?php echo $client['allow_hosting'] ? 'checked' : ''; ?>">
                            <input type="checkbox" name="allow_hosting" value="1"
                                   <?php echo $client['allow_hosting'] ? 'checked' : ''; ?>
                                   onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <div class="checkbox-label">
                                <div class="checkbox-title">🖥️ Permitir Hosting</div>
                                <div class="checkbox-desc">IPs de centros de datos y hosting</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Access Denied Action -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🚫 Acción de Acceso Denegado</h3>
                    <p class="card-subtitle">Qué hacer cuando se bloquea a un usuario</p>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="access_denied_action">Acción a realizar:</label>
                        <select name="access_denied_action" id="access_denied_action" class="form-control" onchange="toggleRedirectUrl()">
                            <option value="block_forms" <?php echo $client['access_denied_action'] === 'block_forms' ? 'selected' : ''; ?>>
                                📝 Bloquear solo formularios (permitir navegación)
                            </option>
                            <option value="block_site" <?php echo $client['access_denied_action'] === 'block_site' ? 'selected' : ''; ?>>
                                🚫 Bloquear sitio web completo
                            </option>
                            <option value="redirect" <?php echo $client['access_denied_action'] === 'redirect' ? 'selected' : ''; ?>>
                                🔄 Redirigir a otra página
                            </option>
                        </select>
                    </div>

                    <div class="form-group" id="redirect-url-group" style="<?php echo $client['access_denied_action'] !== 'redirect' ? 'display: none;' : ''; ?>">
                        <label for="redirect_url">URL de redirección:</label>
                        <input type="url" name="redirect_url" id="redirect_url" class="form-control" 
                               value="<?php echo htmlspecialchars($client['redirect_url'] ?? ''); ?>"
                               placeholder="https://ejemplo.com/acceso-restringido">
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    💾 Guardar Configuración
                </button>
            </div>
        </form>
    </div>

    <script>
        // Country groups data
        const countryGroups = <?php echo json_encode($country_groups); ?>;
        
        // Update selection counter and preview
        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="countries[]"]:checked');
            const count = checkboxes.length;
            
            document.getElementById('selected-count').textContent = count;
            
            // Update visual selection
            document.querySelectorAll('.country-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                item.classList.toggle('selected', checkbox.checked);
            });
            
            // Update preview
            updateSelectedPreview();
        }
        
        function updateSelectedPreview() {
            const checkboxes = document.querySelectorAll('input[name="countries[]"]:checked');
            const preview = document.getElementById('selected-countries-preview');
            
            if (checkboxes.length === 0) {
                preview.innerHTML = '<em style="color: #666;">Ningún país seleccionado</em>';
                return;
            }
            
            const countries = Array.from(checkboxes).map(cb => {
                return cb.parentElement.querySelector('span').textContent;
            }).slice(0, 10); // Mostrar solo los primeros 10
            
            let html
            html = countries.join(', ');
            if (checkboxes.length > 10) {
                html += ` <em style="color: #666;">y ${checkboxes.length - 10} más...</em>`;
            }
            
            preview.innerHTML = html;
        }
        
        // Select country group
        function selectCountryGroup(groupKey) {
            if (!countryGroups[groupKey]) return;
            
            const countries = countryGroups[groupKey].countries;
            
            // Clear all first
            document.querySelectorAll('input[name="countries[]"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Select group countries
            countries.forEach(country => {
                const checkbox = document.querySelector(`input[name="countries[]"][value="${country}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            updateSelection();
        }
        
        // Select all countries
        function selectAllCountries() {
            document.querySelectorAll('input[name="countries[]"]').forEach(cb => {
                cb.checked = true;
            });
            updateSelection();
        }
        
        // Clear all countries
        function clearAllCountries() {
            document.querySelectorAll('input[name="countries[]"]').forEach(cb => {
                cb.checked = false;
            });
            updateSelection();
        }
        
        // Filter countries by search
        function filterCountries() {
            const search = document.getElementById('country-search').value.toLowerCase();
            const items = document.querySelectorAll('.country-item');
            
            items.forEach(item => {
                const countryName = item.querySelector('span').textContent.toLowerCase();
                const match = countryName.includes(search);
                item.style.display = match ? 'flex' : 'none';
            });
        }
        
        // Toggle redirect URL field
        function toggleRedirectUrl() {
            const action = document.getElementById('access_denied_action').value;
            const group = document.getElementById('redirect-url-group');
            const input = document.getElementById('redirect_url');
            
            if (action === 'redirect') {
                group.style.display = 'block';
                input.required = true;
            } else {
                group.style.display = 'none';
                input.required = false;
            }
        }
        
        // Update mode text when radio changes
        document.addEventListener('DOMContentLoaded', function() {
            updateSelection();
            
            // Radio button change handler
            document.querySelectorAll('input[name="access_control_mode"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const modeText = document.getElementById('mode-text');
                    if (this.value === 'allowed') {
                        modeText.textContent = 'Países que SÍ pueden acceder';
                    } else {
                        modeText.textContent = 'Países que NO pueden acceder';
                    }
                    
                    // Update radio option styling
                    document.querySelectorAll('.radio-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    this.closest('.radio-option').classList.add('selected');
                });
            });
            
            // Initialize checkbox styling
            document.querySelectorAll('.checkbox-item input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    this.parentElement.classList.toggle('checked', this.checked);
                });
                
                // Set initial state
                if (checkbox.checked) {
                    checkbox.parentElement.classList.add('checked');
                }
            });
        });
        
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
            toggleBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; cursor: pointer; position: absolute; left: 20px; top: 50%; transform: translateY(-50%);';
            header.style.position = 'relative';
            header.appendChild(toggleBtn);
        }
    </script>
</body>
</html>