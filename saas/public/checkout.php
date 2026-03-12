<?php
/**
 * Página de Checkout con Plan Predefinido
 * Permite registro y pago directo desde botones de planes
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Obtener plan seleccionado
$selected_plan = $_GET['plan'] ?? 'free';
$valid_plans = ['free', 'basic', 'premium', 'enterprise'];

if (!in_array($selected_plan, $valid_plans)) {
    header('Location: index.php?message=Plan no válido&type=error');
    exit;
}

// Obtener configuración del plan
$plan_config = getSaasConfig('plans')[$selected_plan];
$countries = getSaasConfig('countries');

// Verificar si hay mensajes en la URL
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Plan <?php echo htmlspecialchars($plan_config['name']); ?> | GeoControl SaaS</title>
    <meta name="description" content="Completa tu registro y pago para el plan <?php echo htmlspecialchars($plan_config['name']); ?> de GeoControl SaaS">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-premium: linear-gradient(135deg, #ff6b6b 0%, #ffa726 100%);
            --gradient-enterprise: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            text-decoration: none;
            color: white;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .back-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            padding: 40px 0;
            min-height: calc(100vh - 80px);
        }
        
        .checkout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px;
            margin: 20px auto;
            max-width: 900px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .plan-summary {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .plan-summary h2 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .plan-price {
            font-size: 4rem;
            font-weight: 800;
            margin: 20px 0;
        }
        
        .plan-price span {
            font-size: 1.5rem;
            opacity: 0.8;
        }
        
        .plan-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .feature-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        
        .checkout-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            border: 1px solid #e1e5e9;
        }
        
        .form-section {
            margin-bottom: 40px;
        }
        
        .form-section h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
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
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
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
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .payment-method {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-method:hover,
        .payment-method.selected {
            border-color: var(--primary);
            background: #f8f9ff;
            transform: translateY(-2px);
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .payment-method .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .btn {
            display: inline-block;
            padding: 18px 35px;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.4s ease;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            width: 100%;
            font-size: 1.3rem;
            padding: 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-free {
            background: var(--gradient-success);
        }
        
        .btn-premium {
            background: var(--gradient-premium);
        }
        
        .btn-enterprise {
            background: var(--gradient-enterprise);
        }
        
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin: 25px 0;
            font-weight: 500;
            border-left: 5px solid;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-color: var(--success);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-color: var(--danger);
        }
        
        .alert-info {
            background: rgba(102, 126, 234, 0.1);
            color: #0c5460;
            border-color: var(--primary);
        }
        
        .security-notice {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .summary-box {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .checkout-container {
                margin: 10px;
                padding: 20px;
            }
            
            .plan-price {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav container">
            <a href="index.php" class="logo">🌍 GeoControl SaaS</a>
            <a href="index.php" class="back-link">← Volver al inicio</a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Mostrar mensaje si existe -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="checkout-container">
                <!-- Resumen del Plan -->
                <div class="plan-summary <?php echo $selected_plan === 'free' ? '' : 'btn-' . $selected_plan; ?>">
                    <h2>Plan <?php echo htmlspecialchars($plan_config['name']); ?></h2>
                    <div class="plan-price">
                        $<?php echo $plan_config['price']; ?><span>/mes</span>
                    </div>
                    
                    <div class="plan-features">
                        <?php foreach ($plan_config['features'] as $feature): ?>
                        <div class="feature-item">
                            ✨ <?php echo htmlspecialchars($feature); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Formulario de Checkout -->
                <form class="checkout-form" method="POST" action="register_process.php" id="checkout-form">
                    <input type="hidden" name="plan" value="<?php echo $selected_plan; ?>">
                    <input type="hidden" name="from_checkout" value="1">
                    
                    <!-- Información Personal -->
                    <div class="form-section">
                        <h3>📋 Información Personal</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nombre Completo *</label>
                                <input type="text" id="name" name="name" required placeholder="Tu nombre completo">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Corporativo *</label>
                                <input type="email" id="email" name="email" required placeholder="tu@empresa.com">
                                <div id="email-validation-message"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Teléfono</label>
                                <input type="tel" id="phone" name="phone" placeholder="+593 99 123 4567">
                            </div>
                            
                            <div class="form-group">
                                <label for="country">País *</label>
                                <select id="country" name="country" required>
                                    <option value="">Seleccionar país...</option>
                                    <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información de la Empresa -->
                    <div class="form-section">
                        <h3>🏢 Información de la Empresa</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="company">Empresa</label>
                                <input type="text" id="company" name="company" placeholder="Nombre de tu empresa">
                            </div>
                            
                            <div class="form-group">
                                <label for="website">Sitio Web a Proteger *</label>
                                <input type="url" id="website" name="website" placeholder="https://tuempresa.com" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">¿Qué quieres proteger? (Opcional)</label>
                            <textarea id="message" name="message" placeholder="Describe brevemente qué tipo de formularios o contenido quieres proteger..." rows="3"></textarea>
                        </div>
                    </div>
                    
                    <?php if ($selected_plan !== 'free'): ?>
                    <!-- Método de Pago -->
                    <div class="form-section">
                        <h3>💳 Método de Pago</h3>
                        
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="paypal" required>
                                <span class="icon">💰</span>
                                <strong>PayPal</strong>
                                <small>Pago seguro con PayPal</small>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="card" required>
                                <span class="icon">💳</span>
                                <strong>Tarjeta</strong>
                                <small>Visa, Mastercard, etc.</small>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="transfer" required>
                                <span class="icon">🏦</span>
                                <strong>Transferencia</strong>
                                <small>Transferencia bancaria</small>
                            </label>
                        </div>
                        
                        <div class="security-notice">
                            🔒 <strong>Pago 100% Seguro</strong><br>
                            Todos los pagos son procesados de forma segura. No almacenamos información de tarjetas de crédito.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Resumen del Pedido -->
                    <div class="summary-box">
                        <h3>📊 Resumen del Pedido</h3>
                        
                        <div class="summary-item">
                            <span>Plan Seleccionado:</span>
                            <span><?php echo htmlspecialchars($plan_config['name']); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Validaciones Mensuales:</span>
                            <span><?php echo $plan_config['monthly_limit'] == -1 ? 'Ilimitadas' : number_format($plan_config['monthly_limit']); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Sitios Web:</span>
                            <span><?php echo $plan_config['websites_limit'] == -1 ? 'Ilimitados' : $plan_config['websites_limit']; ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Total Mensual:</span>
                            <span>$<?php echo $plan_config['price']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Botón de Envío -->
                    <div style="margin-top: 40px;">
                        <button type="submit" class="btn btn-primary <?php echo 'btn-' . $selected_plan; ?>" id="submit-btn" disabled>
                            <?php if ($selected_plan === 'free'): ?>
                                🎯 Crear Cuenta Gratuita
                            <?php else: ?>
                                💳 Proceder al Pago - $<?php echo $plan_config['price']; ?>/mes
                            <?php endif; ?>
                        </button>
                        
                        <p style="text-align: center; margin-top: 20px; opacity: 0.8; font-size: 0.95rem;">
                            <?php if ($selected_plan === 'free'): ?>
                                ✨ Sin compromiso. Puedes actualizar tu plan cuando quieras.
                            <?php else: ?>
                                📄 Facturación mensual. Puedes cancelar en cualquier momento.<br>
                                🔒 Pago procesado de forma segura con encriptación SSL.
                            <?php endif; ?>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Validación en tiempo real del email
        const emailField = document.getElementById('email');
        const submitBtn = document.getElementById('submit-btn');
        const emailMessage = document.getElementById('email-validation-message');
        
        let emailValid = false;
        
        // Función para validar email
        async function validateEmail(email) {
            if (!email) return false;
            
            // Validación básica
            const basicValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (!basicValid) return false;
            
            try {
                const response = await fetch('https://validatumail.cc/email_validator.php?email=' + encodeURIComponent(email));
                const data = await response.json();
                
                if (data.error) return true; // Fallback en caso de error
                return data.status === 'valid';
            } catch (e) {
                return true; // Fallback en caso de error
            }
        }
        
        // Validación en tiempo real
        let validationTimeout;
        emailField.addEventListener('input', function() {
            clearTimeout(validationTimeout);
            
            validationTimeout = setTimeout(async () => {
                const email = emailField.value.trim();
                
                if (!email) {
                    emailMessage.innerHTML = '';
                    emailValid = false;
                    updateSubmitButton();
                    return;
                }
                
                emailMessage.innerHTML = '<div style="color: #0066cc; font-size: 0.9rem; margin-top: 5px;">🔄 Validando email...</div>';
                
                const isValid = await validateEmail(email);
                
                if (isValid) {
                    emailMessage.innerHTML = '<div style="color: #28a745; font-size: 0.9rem; margin-top: 5px;">✅ Email válido</div>';
                    emailField.style.borderColor = '#28a745';
                    emailValid = true;
                } else {
                    emailMessage.innerHTML = '<div style="color: #dc3545; font-size: 0.9rem; margin-top: 5px;">❌ Email no válido o no existe</div>';
                    emailField.style.borderColor = '#dc3545';
                    emailValid = false;
                }
                
                updateSubmitButton();
            }, 500);
        });
        
        // Función para actualizar estado del botón
        function updateSubmitButton() {
            const nameValid = document.getElementById('name').value.trim().length > 0;
            const countryValid = document.getElementById('country').value.length > 0;
            const websiteValid = document.getElementById('website').value.trim().length > 0;
            
            <?php if ($selected_plan !== 'free'): ?>
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            const allValid = nameValid && emailValid && countryValid && websiteValid && paymentSelected;
            <?php else: ?>
            const allValid = nameValid && emailValid && countryValid && websiteValid;
            <?php endif; ?>
            
            submitBtn.disabled = !allValid;
            submitBtn.style.opacity = allValid ? '1' : '0.5';
        }
        
        // Listeners para otros campos
        ['name', 'country', 'website'].forEach(fieldId => {
            document.getElementById(fieldId).addEventListener('input', updateSubmitButton);
        });
        
        <?php if ($selected_plan !== 'free'): ?>
        // Listener para métodos de pago
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remover selección anterior
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    
                    // Seleccionar actual
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                    
                    updateSubmitButton();
                });
            });
        });
        <?php endif; ?>
        
        // Actualizar estado inicial
        updateSubmitButton();
    </script>
</body>
</html>