<?php
/**
 * Página de Procesamiento de Pagos
 * Simula el proceso de pago para planes de pago
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar parámetros requeridos
$client_id = $_GET['client_id'] ?? '';
$selected_plan = $_GET['plan'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';

if (!$client_id || !$selected_plan || !$payment_method) {
    header('Location: index.php?message=Parámetros de pago inválidos&type=error');
    exit;
}

// Validar plan
$valid_plans = ['basic', 'premium', 'enterprise'];
if (!in_array($selected_plan, $valid_plans)) {
    header('Location: index.php?message=Plan de pago inválido&type=error');
    exit;
}

try {
    $db = SaasDatabase::getInstance();
    
    // Buscar cliente por UUID
    $client = $db->fetchOne(
        "SELECT id, name, email, status FROM clients WHERE client_id = ?",
        [$client_id]
    );
    
    if (!$client) {
        header('Location: index.php?message=Cliente no encontrado&type=error');
        exit;
    }
    
    // Obtener configuración del plan
    $plan_config = getSaasConfig('plans')[$selected_plan];
    
} catch (Exception $e) {
    header('Location: index.php?message=Error interno del servidor&type=error');
    exit;
}

// Procesar pago simulado
$payment_processed = false;
$payment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // SIMULACIÓN DE PROCESAMIENTO DE PAGO
        // En producción aquí iría la integración real con PayPal, Stripe, etc.
        
        $cardholder_name = sanitizeInput($_POST['cardholder_name'] ?? '');
        $card_number = sanitizeInput($_POST['card_number'] ?? '');
        $expiry_date = sanitizeInput($_POST['expiry_date'] ?? '');
        $cvv = sanitizeInput($_POST['cvv'] ?? '');
        
        // Validaciones básicas
        if (empty($cardholder_name) || empty($card_number) || empty($expiry_date) || empty($cvv)) {
            throw new Exception('Todos los campos de pago son requeridos');
        }
        
        // Simular procesamiento exitoso (90% de probabilidad)
        $payment_success = rand(1, 10) <= 9;
        
        if ($payment_success) {
            // Activar plan pagado
            $db->query(
                "UPDATE clients SET plan = ?, monthly_limit = ?, updated_at = NOW() WHERE id = ?",
                [$selected_plan, $plan_config['monthly_limit'], $client['id']]
            );
            
            // Log de actividad
            logActivity('info', 'Plan actualizado por pago', [
                'previous_plan' => 'free',
                'new_plan' => $selected_plan,
                'payment_method' => $payment_method,
                'amount' => $plan_config['price']
            ], $client['id']);
            
            $payment_processed = true;
            
            // Redirigir al dashboard después de 3 segundos
            header('refresh:3;url=../client/dashboard.php');
            
        } else {
            throw new Exception('Error procesando el pago. Inténtalo de nuevo.');
        }
        
    } catch (Exception $e) {
        $payment_error = $e->getMessage();
        
        logActivity('error', 'Error procesando pago', [
            'client_id' => $client['id'],
            'plan' => $selected_plan,
            'payment_method' => $payment_method,
            'error' => $e->getMessage()
        ], $client['id']);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesamiento de Pago - Plan <?php echo htmlspecialchars($plan_config['name']); ?> | GeoControl SaaS</title>
    
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .payment-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px;
            margin: 40px auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--dark);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .plan-summary {
            background: var(--gradient-primary);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .plan-summary h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: 800;
            margin: 15px 0;
        }
        
        .payment-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #e1e5e9;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
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
            width: 100%;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            font-size: 1.3rem;
            padding: 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: var(--gradient-success);
            color: white;
        }
        
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin: 25px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 2px solid var(--success);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 2px solid var(--danger);
        }
        
        .security-badges {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .security-badge {
            font-size: 0.9rem;
            color: #666;
        }
        
        .success-animation {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 5rem;
            color: var(--success);
            animation: bounce 1s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-30px); }
            80% { transform: translateY(-15px); }
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-container {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <?php if ($payment_processed): ?>
            <!-- Página de éxito -->
            <div class="success-animation">
                <div class="success-icon">✅</div>
                <h1 style="color: var(--success); margin-bottom: 20px;">¡Pago Procesado Exitosamente!</h1>
                <p style="font-size: 1.2rem; margin-bottom: 20px;">
                    Tu plan <strong><?php echo htmlspecialchars($plan_config['name']); ?></strong> ha sido activado.
                </p>
                <p style="color: #666; margin-bottom: 30px;">
                    Redirigiendo a tu dashboard en unos segundos...
                </p>
                <a href="../client/dashboard.php" class="btn btn-success">
                    🚀 Ir al Dashboard
                </a>
            </div>
            
            <?php else: ?>
            <!-- Formulario de pago -->
            <div class="header">
                <h1>💳 Completar Pago</h1>
                <p>Cliente: <?php echo htmlspecialchars($client['name']); ?></p>
            </div>
            
            <!-- Resumen del plan -->
            <div class="plan-summary">
                <h2>Plan <?php echo htmlspecialchars($plan_config['name']); ?></h2>
                <div class="plan-price">$<?php echo $plan_config['price']; ?><span style="font-size: 1.5rem;">/mes</span></div>
                <p>Método: <?php echo ucfirst($payment_method); ?></p>
            </div>
            
            <?php if ($payment_error): ?>
            <div class="alert alert-error">
                ❌ <strong>Error:</strong> <?php echo htmlspecialchars($payment_error); ?>
            </div>
            <?php endif; ?>
            
            <form class="payment-form" method="POST" id="payment-form">
                <h3 style="margin-bottom: 20px;">💳 Información de Pago</h3>
                
                <div class="form-group">
                    <label for="cardholder_name">Nombre del Titular *</label>
                    <input type="text" id="cardholder_name" name="cardholder_name" required 
                           placeholder="Nombre como aparece en la tarjeta">
                </div>
                
                <div class="form-group">
                    <label for="card_number">Número de Tarjeta *</label>
                    <input type="text" id="card_number" name="card_number" required 
                           placeholder="1234 5678 9012 3456" maxlength="19">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiry_date">Fecha de Vencimiento *</label>
                        <input type="text" id="expiry_date" name="expiry_date" required 
                               placeholder="MM/AA" maxlength="5">
                    </div>
                    
                    <div class="form-group">
                        <label for="cvv">CVV *</label>
                        <input type="text" id="cvv" name="cvv" required 
                               placeholder="123" maxlength="4">
                    </div>
                </div>
                
                <div class="security-badges">
                    <div class="security-badge">🔒 SSL Seguro</div>
                    <div class="security-badge">💳 Visa, Mastercard</div>
                    <div class="security-badge">🛡️ Encriptado</div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="pay-btn">
                    💳 Pagar $<?php echo $plan_config['price']; ?> - Activar Plan
                </button>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Procesando pago seguro...</p>
                </div>
                
                <p style="text-align: center; margin-top: 20px; font-size: 0.9rem; color: #666;">
                    🔒 Tu información está protegida con encriptación SSL de 256 bits.<br>
                    📄 Facturación automática mensual. Cancela cuando quieras.
                </p>
            </form>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 10px; padding: 15px; margin-top: 20px; text-align: center;">
                <strong>💡 Nota de Demostración:</strong> 
                Este es un sistema de pago simulado para fines de demostración. 
                En producción se integraría con procesadores reales como PayPal o Stripe.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Formateo automático de campos
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
        
        document.getElementById('expiry_date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
        
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Manejar envío del formulario
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const payBtn = document.getElementById('pay-btn');
            const loading = document.getElementById('loading');
            
            payBtn.style.display = 'none';
            loading.style.display = 'block';
        });
    </script>
</body>
</html>