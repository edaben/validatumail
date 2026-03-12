<?php
/**
 * Página de Upgrade de Plan
 * Permite a los clientes mejorar su plan actual
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['client_id'])) {
    redirectToLogin('Debes iniciar sesión para acceder');
}

$client_id = $_SESSION['client_id'];

$message = '';
$message_type = '';

// Obtener datos frescos del cliente SIEMPRE de la BD
try {
    $db = SaasDatabase::getInstance();
    $client = $db->fetchOne("SELECT *, COALESCE(payment_url, 'https://tu-sitio-pagos.com/upgrade') as payment_url FROM clients WHERE id = ?", [$client_id]);
    
    if (!$client) {
        session_destroy();
        redirectToLogin('Sesión inválida');
    }
    
    // Actualizar sesión con datos frescos
    $_SESSION['client_name'] = $client['name'];
    $_SESSION['client_plan'] = $client['plan'];
    
    // Usar datos de la BD
    $client_name = $client['name'];
    $current_plan = $client['plan'];
    
} catch (Exception $e) {
    $error_message = 'Error al cargar información del cliente';
}

// Procesar solicitud de upgrade de plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $new_plan = sanitizeInput($_POST['new_plan'] ?? '');
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $contact_info = sanitizeInput($_POST['contact_info'] ?? '');
        
        // Validar plan
        $available_plans = ['free', 'basic', 'premium', 'enterprise'];
        if (!in_array($new_plan, $available_plans)) {
            throw new Exception('Plan seleccionado no válido');
        }
        
        // Verificar si es realmente un upgrade
        $plan_hierarchy = ['free' => 0, 'basic' => 1, 'premium' => 2, 'enterprise' => 3];
        if ($plan_hierarchy[$new_plan] <= $plan_hierarchy[$current_plan]) {
            throw new Exception('Ya tienes un plan igual o superior. Para cambios contacta soporte.');
        }
        
        $plan_config = getSaasConfig('plans')[$new_plan];
        
        // ENVIAR EMAIL DE SOLICITUD DE UPGRADE en lugar de cambiar automáticamente
        $email_subject = "🚀 Solicitud de Upgrade - " . $client['name'] . " - Plan " . strtoupper($new_plan);
        
        $email_body = "
        <h2>🚀 Nueva Solicitud de Upgrade</h2>
        <hr>
        
        <h3>👤 Información del Cliente:</h3>
        <ul>
            <li><strong>Nombre:</strong> " . htmlspecialchars($client['name']) . "</li>
            <li><strong>Email:</strong> " . htmlspecialchars($client['email']) . "</li>
            <li><strong>Teléfono:</strong> " . htmlspecialchars($client['phone'] ?? 'No especificado') . "</li>
            <li><strong>Empresa:</strong> " . htmlspecialchars($client['company'] ?? 'No especificada') . "</li>
            <li><strong>Client ID:</strong> " . htmlspecialchars($client['client_id']) . "</li>
        </ul>
        
        <h3>📊 Detalles del Upgrade:</h3>
        <ul>
            <li><strong>Plan Actual:</strong> " . strtoupper($current_plan) . "</li>
            <li><strong>Plan Solicitado:</strong> " . strtoupper($new_plan) . "</li>
            <li><strong>Precio:</strong> $" . $plan_config['price'] . "/mes</li>
            <li><strong>Método de Pago Preferido:</strong> " . htmlspecialchars($payment_method) . "</li>
        </ul>
        
        <h3>💬 Información Adicional:</h3>
        <p>" . htmlspecialchars($contact_info ?: 'Sin información adicional') . "</p>
        
        <h3>📈 Beneficios del Plan " . strtoupper($new_plan) . ":</h3>
        <ul>";
        
        foreach ($plan_config['features'] as $feature) {
            $email_body .= "<li>✅ " . htmlspecialchars($feature) . "</li>";
        }
        
        $email_body .= "
        </ul>
        
        <hr>
        <p><strong>🔧 Acción Requerida:</strong> Contactar al cliente para procesar el pago y activar el plan.</p>
        <p><strong>📧 Responder a:</strong> " . htmlspecialchars($client['email']) . "</p>
        ";
        
        // Enviar email usando AWS SES
        require_once '../config/email.php';
        
        if (sendEmail(
            'eduardo@rastroseguro.com',
            $email_subject,
            $email_body,
            $client['email'] // Reply-to
        )) {
            // Guardar solicitud en logs
            logActivity('info', 'Solicitud de upgrade enviada', [
                'requested_plan' => $new_plan,
                'current_plan' => $current_plan,
                'payment_method' => $payment_method,
                'contact_info' => $contact_info
            ], $client_id);
            
            $message = "📧 ¡Solicitud enviada exitosamente! Te contactaremos pronto para procesar tu upgrade a " . $plan_config['name'] . ".";
            $message_type = 'success';
        } else {
            throw new Exception('Error al enviar la solicitud. Por favor, intenta nuevamente.');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        
        logActivity('error', 'Error enviando solicitud de upgrade', [
            'error' => $e->getMessage(),
            'attempted_plan' => $new_plan ?? 'none'
        ], $client_id);
    }
}

// Obtener configuraciones de planes
$plans = getSaasConfig('plans');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mejorar Plan - GeoControl SaaS</title>
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

        /* Plan Cards */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }

        .plan-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .plan-card:hover {
            transform: translateY(-5px);
        }

        .plan-card.current {
            border: 3px solid #28a745;
        }

        .plan-card.current::before {
            content: "✅ Plan Actual";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .plan-card.recommended {
            border: 3px solid #667eea;
        }

        .plan-card.recommended::before {
            content: "🔥 Recomendado";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #667eea;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #333;
        }

        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: #667eea;
            margin: 1rem 0;
        }

        .plan-price span {
            font-size: 1rem;
            color: #666;
        }

        .features-list {
            list-style: none;
            margin: 2rem 0;
            text-align: left;
        }

        .features-list li {
            padding: 8px 0;
            color: #666;
        }

        .features-list li::before {
            content: "✅ ";
            margin-right: 8px;
        }

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
            width: 100%;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .current-plan-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .usage-bar {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            height: 20px;
            margin: 15px 0;
            overflow: hidden;
        }

        .usage-fill {
            background: #28a745;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover, .payment-method.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .payment-method input[type="radio"] {
            display: none;
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
            <a href="billing.php" class="nav-item">
                <i>💳</i> Facturación
            </a>
            <a href="upgrade_plan.php" class="nav-item active">
                <i>🚀</i> Mejorar Plan
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>🚀 Mejorar Plan</h1>
            <p>Desbloquea más funcionalidades para tu negocio</p>
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

        <!-- Current Plan Info -->
        <div class="current-plan-info">
            <h2>📊 Tu Plan Actual: <?php echo htmlspecialchars($plans[$current_plan]['name']); ?></h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div>
                    <h4>💰 Precio: $<?php echo $plans[$current_plan]['price']; ?>/mes</h4>
                    <h4>🌐 Sitios: <?php echo $plans[$current_plan]['websites_limit'] == -1 ? 'Ilimitados' : $plans[$current_plan]['websites_limit']; ?></h4>
                </div>
                <div>
                    <h4>📊 Uso este mes: <?php echo $client['monthly_usage']; ?>/<?php echo $client['monthly_limit'] == -1 ? '∞' : $client['monthly_limit']; ?></h4>
                    <?php if ($client['monthly_limit'] > 0): ?>
                    <div class="usage-bar">
                        <div class="usage-fill" style="width: <?php echo min(100, ($client['monthly_usage'] / $client['monthly_limit']) * 100); ?>%"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Available Plans -->
        <h2 style="margin-bottom: 20px;">📈 Planes Disponibles</h2>
        
        <div class="plans-grid">
            <?php foreach ($plans as $plan_key => $plan): ?>
            <div class="plan-card <?php echo $plan_key === $current_plan ? 'current' : ''; ?> <?php echo $plan_key === 'basic' ? 'recommended' : ''; ?>">
                <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                <div class="plan-price">$<?php echo $plan['price']; ?><span>/mes</span></div>
                
                <ul class="features-list">
                    <?php foreach ($plan['features'] as $feature): ?>
                    <li><?php echo htmlspecialchars($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($plan_key === $current_plan): ?>
                    <button class="btn btn-success" disabled>✅ Plan Actual</button>
                <?php elseif ($plan_key === 'free'): ?>
                    <button class="btn btn-secondary" disabled>Plan Gratuito</button>
                <?php else: ?>
                    <?php
                    $payment_url = $client['payment_url'] ?? 'https://tu-sitio-pagos.com/upgrade';
                    $redirect_url = $payment_url . '?plan=' . $plan_key . '&client_id=' . urlencode($client['client_id']) . '&client_name=' . urlencode($client['name']) . '&current_plan=' . $current_plan . '&price=' . $plan['price'];
                    ?>
                    <a href="<?php echo htmlspecialchars($redirect_url); ?>"
                       target="_blank"
                       class="btn btn-primary"
                       style="text-decoration: none; display: inline-block; text-align: center;">
                        💰 Pagar <?php echo htmlspecialchars($plan['name']); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal de Solicitud de Upgrade -->
        <div id="upgradeModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
            <div style="background-color: white; margin: 5% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <!-- Header del modal -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 24px;">💰 Solicitar Upgrade</h2>
                    <button onclick="closeUpgradeModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">×</button>
                </div>
                
                <!-- Contenido del modal -->
                <div style="padding: 30px;">
                    <form method="POST">
                        <input type="hidden" id="modal_selected_plan" name="new_plan" value="">
                        
                        <div style="margin-bottom: 25px; text-align: center;">
                            <h3 id="modal_upgrade_summary" style="color: #333;"></h3>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">💳 Método de Pago Preferido:</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <label class="payment-method" style="border: 2px solid #e1e5e9; border-radius: 8px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s;">
                                    <input type="radio" name="payment_method" value="paypal" required style="display: none;">
                                    <div style="font-size: 20px; margin-bottom: 5px;">💰</div>
                                    <div>PayPal</div>
                                </label>
                                <label class="payment-method" style="border: 2px solid #e1e5e9; border-radius: 8px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s;">
                                    <input type="radio" name="payment_method" value="transferencia" required style="display: none;">
                                    <div style="font-size: 20px; margin-bottom: 5px;">🏦</div>
                                    <div>Transferencia Bancaria</div>
                                </label>
                                <label class="payment-method" style="border: 2px solid #e1e5e9; border-radius: 8px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s;">
                                    <input type="radio" name="payment_method" value="western_union" required style="display: none;">
                                    <div style="font-size: 20px; margin-bottom: 5px;">💸</div>
                                    <div>Western Union</div>
                                </label>
                                <label class="payment-method" style="border: 2px solid #e1e5e9; border-radius: 8px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s;">
                                    <input type="radio" name="payment_method" value="otro" required style="display: none;">
                                    <div style="font-size: 20px; margin-bottom: 5px;">💬</div>
                                    <div>Otro (Especificar)</div>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">📝 Información Adicional (Opcional):</label>
                            <textarea name="contact_info" placeholder="Comparte cualquier información adicional sobre tu preferencia de pago, preguntas, o solicitudes especiales..."
                                      style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; resize: vertical; height: 100px; font-family: inherit;"></textarea>
                        </div>
                        
                        <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 15px; margin: 20px 0; color: #0c5460;">
                            <strong>📧 Proceso:</strong> Tu solicitud será enviada por email. Nos pondremos en contacto contigo dentro de 24 horas para coordinar el pago y activar tu nuevo plan.
                        </div>
                        
                        <div style="display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                📧 Enviar Solicitud
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeUpgradeModal()" style="flex: 0 0 auto;">
                                ❌ Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-top: 30px;">
            <h2>💡 Información de Pagos</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div>
                    <h4>🔒 Seguridad</h4>
                    <p>Todos los pagos son procesados de forma segura. No almacenamos información de tarjetas de crédito.</p>
                </div>
                <div>
                    <h4>🔄 Facturación</h4>
                    <p>La facturación es mensual. Puedes cancelar en cualquier momento sin penalizaciones.</p>
                </div>
                <div>
                    <h4>📧 Soporte</h4>
                    <p>¿Necesitas ayuda? Contacta nuestro equipo de soporte: eduardo@rastroseguro.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para abrir modal de solicitud de upgrade
        function openUpgradeModal(planKey, planName, planPrice) {
            const plans = <?php echo json_encode($plans); ?>;
            const currentPlan = '<?php echo $current_plan; ?>';
            
            if (planKey === currentPlan) {
                alert('Ya tienes este plan activo.');
                return;
            }
            
            const selectedPlan = plans[planKey];
            const currentPlanData = plans[currentPlan];
            
            // Configurar modal
            document.getElementById('modal_selected_plan').value = planKey;
            
            // Actualizar resumen en modal
            const summary = document.getElementById('modal_upgrade_summary');
            summary.innerHTML = `
                📈 <strong>Upgrade:</strong> ${currentPlanData.name} → ${selectedPlan.name}<br>
                💰 <strong>Precio:</strong> $${selectedPlan.price}/mes<br>
                📊 <strong>Límites:</strong> ${selectedPlan.monthly_limit === -1 ? 'Ilimitado' : selectedPlan.monthly_limit.toLocaleString()} validaciones/mes
            `;
            
            // Mostrar modal
            document.getElementById('upgradeModal').style.display = 'block';
        }
        
        // Función para cerrar modal
        function closeUpgradeModal() {
            document.getElementById('upgradeModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('upgradeModal');
            if (event.target == modal) {
                closeUpgradeModal();
            }
        }
        
        // Manejar selección de método de pago
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remover selección anterior
                    paymentMethods.forEach(m => {
                        m.style.borderColor = '#e1e5e9';
                        m.style.background = 'white';
                    });
                    
                    // Seleccionar actual
                    this.style.borderColor = '#667eea';
                    this.style.background = '#f8f9ff';
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
        });
    </script>
</body>
</html>