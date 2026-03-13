<?php
/**
 * Generador de Código Personalizado
 * 
 * Permite al cliente generar el código JavaScript para implementar en su sitio
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

    if (!$client) {
        throw new Exception('Cliente no encontrado');
    }

    // Obtener sitios web del cliente
    $websites = $db->fetchAll(
        "SELECT * FROM client_websites WHERE client_id = ? AND is_active = 1 ORDER BY created_at ASC",
        [$client_id]
    );

} catch (Exception $e) {
    logActivity('error', 'Error cargando generador de código', [
        'error' => $e->getMessage(),
        'client_id' => $client_id
    ], $client_id);

    $error_message = 'Error al cargar los datos. Por favor, recarga la página.';
}

// Generar código JavaScript súper simple
function generateClientCode($client, $options = [])
{
    $script_url = SAAS_SITE_URL . '/api/v1/client_script.php?client=' . $client['client_id'];

    // SIEMPRE versión súper simple por defecto
    $code = '<!-- ZipGeo SaaS - Control de Acceso Geográfico -->' . "\n";
    $code .= '<!-- Cliente: ' . htmlspecialchars($client['name'] ?? '') . ' -->' . "\n";
    $code .= '<script src="' . $script_url . '"></script>';

    return $code;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Código - GeoControl SaaS</title>
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

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        /* Cards */
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #99d3ff;
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

        /* Code Box */
        .code-box {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            overflow-x: auto;
            position: relative;
            max-height: 500px;
            overflow-y: auto;
        }

        .code-box pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Copy Button */
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s;
        }

        .copy-btn:hover {
            background: #5a6fd8;
        }

        .copy-btn.copied {
            background: #28a745;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Checkboxes */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .checkbox-item:hover {
            background: #f8f9fa;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
        }

        .checkbox-label {
            flex: 1;
        }

        .checkbox-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .checkbox-desc {
            color: #666;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
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

        /* Steps */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .step {
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            transition: border-color 0.3s;
        }

        .step:hover {
            border-color: #667eea;
        }

        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            line-height: 40px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .step-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .step-desc {
            color: #666;
            font-size: 0.9rem;
        }

        /* Info boxes */
        .info-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #333;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
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

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .steps {
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
            <a href="code_generator.php" class="nav-item active">
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
            <h1>📋 Generar Código</h1>
            <p>Obtén el código JavaScript personalizado para implementar en tu sitio web</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($websites)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ No tienes sitios web configurados</strong><br>
                Antes de generar código, debes <a href="websites.php">agregar al menos un sitio web</a> a tu cuenta.
            </div>
        <?php else: ?>

            <!-- Steps -->
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-title">Configurar Países</div>
                    <div class="step-desc">Define qué países pueden acceder</div>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-title">Generar Código</div>
                    <div class="step-desc">Personaliza y copia el código JavaScript</div>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-title">Implementar</div>
                    <div class="step-desc">Pega el código en tu sitio web</div>
                </div>
            </div>

            <!-- Configuration -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">⚙️ Opciones de Personalización</h3>
                        <p class="card-subtitle">Personaliza el comportamiento del código</p>
                    </div>
                    <button onclick="generateCode()" class="btn btn-primary">🔄 Regenerar Código</button>
                </div>
                <div class="card-body">
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" id="show_loading" checked onchange="generateCode()">
                            <div class="checkbox-label">
                                <div class="checkbox-title">Mostrar Indicador de Carga</div>
                                <div class="checkbox-desc">Barra de progreso mientras se verifica la ubicación</div>
                            </div>
                        </label>

                        <label class="checkbox-item">
                            <input type="checkbox" id="debug_mode" onchange="generateCode()">
                            <div class="checkbox-label">
                                <div class="checkbox-title">Modo Debug</div>
                                <div class="checkbox-desc">Mostrar información de depuración en consola</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Generated Code -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">📋 Código Generado</h3>
                        <p class="card-subtitle">Copia y pega este código en tu sitio web</p>
                    </div>
                    <button onclick="copyCode()" class="btn btn-success copy-btn" id="copy-btn">📋 Copiar Código</button>
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <h4>🔧 Instrucciones de Implementación</h4>
                        <p>Pega este código justo antes del tag <code>&lt;/head&gt;</code> de tu sitio web. El código se
                            ejecutará automáticamente en todas las páginas donde lo incluyas.</p>
                    </div>

                    <div class="code-box">
                        <button onclick="copyCode()" class="copy-btn" id="copy-btn-box">📋 Copiar</button>
                        <pre id="generated-code"><?php echo htmlspecialchars(generateClientCode($client)); ?></pre>
                    </div>
                </div>
            </div>

            <!-- Current Configuration -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📄 Tu Configuración Actual</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <!-- Mode -->
                        <div class="info-box">
                            <h4>🎯 Modo de Control</h4>
                            <p>
                                <?php if ($client['access_control_mode'] === 'allowed'): ?>
                                    <strong>Lista Blanca</strong> - Solo países seleccionados pueden acceder
                                <?php else: ?>
                                    <strong>Lista Negra</strong> - Todos los países excepto los seleccionados
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Countries -->
                        <div class="info-box">
                            <h4>🌍 Países Configurados</h4>
                            <p>
                                <?php
                                $countries_list = '';
                                if ($client['access_control_mode'] === 'allowed' && $client['countries_allowed']) {
                                    $countries_count = count(explode(',', $client['countries_allowed']));
                                    $countries_list = "$countries_count países permitidos";
                                } elseif ($client['access_control_mode'] === 'denied' && $client['countries_denied']) {
                                    $countries_count = count(explode(',', $client['countries_denied']));
                                    $countries_list = "$countries_count países bloqueados";
                                } else {
                                    $countries_list = "Sin configuración específica";
                                }
                                echo $countries_list;
                                ?>
                                <br><small><a href="countries.php">Modificar configuración</a></small>
                            </p>
                        </div>

                        <!-- Advanced Options -->
                        <div class="info-box">
                            <h4>⚙️ Opciones Avanzadas</h4>
                            <p>
                                VPN: <?php echo $client['allow_vpn'] ? '✅ Permitido' : '❌ Bloqueado'; ?><br>
                                Proxy: <?php echo $client['allow_proxy'] ? '✅ Permitido' : '❌ Bloqueado'; ?><br>
                                Tor: <?php echo $client['allow_tor'] ? '✅ Permitido' : '❌ Bloqueado'; ?>
                            </p>
                        </div>

                        <!-- Action -->
                        <div class="info-box">
                            <h4>🚫 Acción de Bloqueo</h4>
                            <p>
                                <?php if ($client['access_denied_action'] === 'redirect'): ?>
                                    <strong>Redireccionar</strong> a: <?php echo htmlspecialchars($client['redirect_url']); ?>
                                <?php else: ?>
                                    <strong>Mostrar mensaje</strong> de acceso denegado
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Websites -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🌐 Sitios Web Protegidos</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($websites)): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <?php foreach ($websites as $website): ?>
                                <div class="info-box">
                                    <h4>🌐 <?php echo htmlspecialchars($website['domain']); ?></h4>
                                    <p>
                                        Estado: <strong>Activo</strong><br>
                                        Agregado: <?php echo date('d/m/Y', strtotime($website['created_at'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Testing -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🧪 Probar Implementación</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>💡 Consejos para Probar:</strong><br>
                        1. Implementa el código en una página de prueba<br>
                        2. Usa una VPN para simular diferentes países<br>
                        3. Revisa la consola del navegador para ver logs de debug<br>
                        4. Verifica las estadísticas en tu dashboard
                    </div>

                    <div class="info-box">
                        <h4>🔗 URL de Script Directo</h4>
                        <p>
                            Para implementaciones avanzadas, puedes usar directamente:<br>
                            <code
                                style="word-break: break-all; background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?php echo htmlspecialchars(SAAS_SITE_URL . '/api/v1/client_script.php?client=' . $client['client_id']); ?></code>
                        </p>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function generateCode() {
            // Convertimos la función PHP directamente a un string de JS para asegurar formato idéntico y evitar roturas de comillas
            const simpleCode = <?php echo json_encode(generateClientCode($client)); ?>;
            document.getElementById('generated-code').textContent = simpleCode;
        }

        // Copy code to clipboard - SIMPLE
        function copyCode() {
            const codeElement = document.getElementById('generated-code');
            const code = codeElement.textContent || codeElement.innerText;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    showCopiedFeedback();
                }).catch(() => {
                    fallbackCopy(code);
                });
            } else {
                fallbackCopy(code);
            }
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopiedFeedback();
        }

        function showCopiedFeedback() {
            const buttons = document.querySelectorAll('.copy-btn');
            buttons.forEach(btn => {
                const originalText = btn.textContent;
                btn.textContent = '✅ Copiado';
                btn.style.background = '#28a745';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '#667eea';
                }, 2000);
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', generateCode);
    </script>
</body>

</html>