
<?php
/**
 * Landing Page Principal del SaaS - REDISEÑADA
 * 
 * Página moderna y atractiva para captar visitantes
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Verificar si hay mensajes en la URL
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌍 GeoControl SaaS - Control de Acceso Geográfico Inteligente</title>
    <meta name="description" content="Protege tus sitios web con control de acceso geográfico. Bloquea países, controla VPN y proxy. Fácil implementación con solo 2 líneas de código.">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Estilos CSS Modernos -->
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
            background: #0f0f23;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Particles Background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        
        .particle {
            position: absolute;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 5px rgba(102, 126, 234, 0.5); }
            50% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.8), 0 0 30px rgba(102, 126, 234, 0.6); }
        }
        
        /* Header con efecto glassmorphism */
        .header {
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 10;
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
            filter: drop-shadow(0 0 10px rgba(102, 126, 234, 0.5));
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .nav-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .nav-links a:hover {
            color: white;
            transform: translateY(-2px);
            background: rgba(255,255,255,0.1);
        }
        
        .btn-cta {
            background: var(--gradient-primary) !important;
            border: 2px solid transparent !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            animation: glow 2s ease-in-out infinite;
        }
        
        /* Hero Section Espectacular */
        .hero {
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
            text-align: center;
            padding: 150px 0 100px 0;
            margin-top: 70px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
            animation: slideInUp 1s ease-out;
        }
        
        .hero h1 {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: pulse 3s ease-in-out infinite;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .hero-buttons {
            display: flex;
            gap: 25px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 3rem;
        }
        
        .btn {
            display: inline-block;
            padding: 18px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.4s ease;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            animation: glow 3s ease-in-out infinite;
        }
        
        .btn-primary:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-4px);
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            position: relative;
        }
        
        .section-title {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 4rem;
            color: var(--dark);
            font-weight: 700;
            animation: slideInUp 0.8s ease-out;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-top: 4rem;
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            padding: 3rem 2rem;
            border-radius: 20px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.8s ease-out;
        }
        
        .feature-card:hover {
            transform: translateY(-10px) rotate(1deg);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 1);
        }
        
        .feature-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .feature-card h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        /* Pricing Section */
        .pricing {
            padding: 100px 0;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
            position: relative;
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 4rem;
            position: relative;
            z-index: 10;
        }
        
        .pricing-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            transition: all 0.4s ease;
        }
        
        .pricing-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.3);
        }
        
        .pricing-card.featured {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid var(--primary);
            transform: scale(1.08);
            animation: glow 3s ease-in-out infinite;
        }
        
        .pricing-card.featured::before {
            content: "🔥 Más Popular";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--gradient-primary);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }
        
        .price {
            font-size: 4rem;
            font-weight: 800;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 1.5rem 0;
            line-height: 1;
        }
        
        .price span {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .features-list {
            list-style: none;
            margin: 2.5rem 0;
            text-align: left;
        }
        
        .features-list li {
            padding: 12px 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .features-list li::before {
            content: "✨ ";
            margin-right: 12px;
            font-size: 1.2rem;
        }
        
        .plan-btn {
            width: 100%;
            font-size: 1.2rem;
            padding: 18px;
            margin-top: 2rem;
            background: var(--gradient-primary);
            border: none;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
        }
        
        .plan-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .plan-btn.btn-free {
            background: var(--gradient-success);
        }
        
        .plan-btn.btn-premium {
            background: var(--gradient-premium);
        }
        
        .plan-btn.btn-enterprise {
            background: var(--gradient-enterprise);
        }
        
        /* Registration Form */
        .registration {
            padding: 100px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }
        
        .registration-form {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            padding: 4rem;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            position: relative;
            z-index: 10;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            font-size: 1rem;
            background: rgba(255,255,255,0.9);
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: white;
            box-shadow: 0 0 20px rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        /* Alerts */
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
        
        /* Footer */
        .footer {
            background: #0f0f23;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            padding: 3rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                display: none;
            }
            
            .features-grid,
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .pricing-card.featured {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Particles Background -->
    <div class="particles" id="particles"></div>
    
    <!-- Header -->
    <header class="header">
        <nav class="nav container">
            <a href="#" class="logo">🌍 GeoControl SaaS</a>
            <div class="nav-links">
                <a href="#features">Características</a>
                <a href="#pricing">Precios</a>
                <a href="#register">Registro</a>
                <a href="login.php" class="btn-cta">Iniciar Sesión</a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Control de Acceso Geográfico Inteligente</h1>
                <p class="hero-subtitle">Protege tus sitios web bloqueando países específicos, VPN, Tor y proxy.<br>
                <strong>Implementación súper fácil con solo 2 líneas de código.</strong></p>
                
                <div class="hero-buttons">
                    <a href="#register" class="btn btn-primary">🚀 Empezar Gratis Ahora</a>
                    <a href="#features" class="btn btn-secondary">🔍 Ver Características</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Mostrar mensaje si existe -->
    <?php if ($message): ?>
    <div class="container">
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Value Proposition Section -->
    <section class="value-proposition" style="padding: 100px 0; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); position: relative;">
        <div class="container">
            <div style="text-align: center; margin-bottom: 60px;">
                <h2 style="font-size: 3rem; color: var(--dark); font-weight: 700; margin-bottom: 20px;">
                    ¡Convierte cada registro en una oportunidad real y protege tu reputación de envío!
                </h2>
                <h3 style="font-size: 2rem; color: var(--primary); font-weight: 600; margin-bottom: 30px;">
                    Validación de correos + Bloqueo inteligente de países y bots
                </h3>
                <p style="font-size: 1.3rem; color: #666; font-weight: 500;">
                    <strong>Evita correos basura, protege tus formularios y mantén tu autorresponder a salvo.</strong>
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 40px; margin: 60px 0;">
                <!-- ¿Por qué importa? -->
                <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="color: var(--danger); font-size: 1.8rem; margin-bottom: 25px; display: flex; align-items: center;">
                        ⚠️ ¿Por qué importa?
                    </h3>
                    <p style="margin-bottom: 20px; font-size: 1.1rem; line-height: 1.6;">
                        Si capturas correos inválidos o de robots:
                    </p>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--danger);">❌</span>
                            <strong>Bajan tus entregas</strong> (rebotes y quejas) y <strong>pueden suspender</strong> tu autorresponder
                        </li>
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--danger);">📈</span>
                            <strong>Se infla tu lista</strong> con contactos que <strong>no compran</strong> y te cuesta más mantener
                        </li>
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--danger);">📊</span>
                            <strong>Se distorsionan tus métricas</strong> y tomas malas decisiones
                        </li>
                    </ul>
                </div>
                
                <!-- Lo que hace nuestra solución -->
                <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="color: var(--success); font-size: 1.8rem; margin-bottom: 25px; display: flex; align-items: center;">
                        ✅ Lo que hace nuestra solución
                    </h3>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--success);">📧</span>
                            <strong>Valida el correo en tiempo real:</strong> detecta errores y correos desechables
                        </li>
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--success);">🌍</span>
                            <strong>Bloquea países no deseados:</strong> tú decides a quién mostrar el formulario
                        </li>
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--success);">🎯</span>
                            <strong>Modo "solo formulario" o "sitio entero":</strong> control granular del bloqueo
                        </li>
                        <li style="margin: 15px 0; padding-left: 30px; position: relative;">
                            <span style="position: absolute; left: 0; color: var(--success);">🤖</span>
                            <strong>Anti-bots:</strong> frena robots automáticos
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Beneficios inmediatos -->
            <div style="background: var(--gradient-primary); color: white; padding: 50px; border-radius: 25px; margin: 40px 0; text-align: center;">
                <h3 style="font-size: 2.2rem; margin-bottom: 30px; font-weight: 700;">
                    🎯 Beneficios Inmediatos
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 30px;">
                    <div style="background: rgba(255,255,255,0.1); padding: 25px; border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">💰</div>
                        <h4 style="margin-bottom: 10px;">Listas limpias y vendibles</h4>
                        <p>Más aperturas, más clics, más ventas</p>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 25px; border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">📬</div>
                        <h4 style="margin-bottom: 10px;">Mejor entregabilidad</h4>
                        <p>Menos rebotes, mejor reputación</p>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 25px; border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">💸</div>
                        <h4 style="margin-bottom: 10px;">Ahorro en costos</h4>
                        <p>No pagues por contactos que no convertirán</p>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 25px; border-radius: 15px;">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">📊</div>
                        <h4 style="margin-bottom: 10px;">Datos reales</h4>
                        <p>Métricas claras para optimizar</p>
                    </div>
                </div>
            </div>
            
            <!-- Cómo se usa -->
            <div style="background: white; padding: 50px; border-radius: 25px; margin: 40px 0;">
                <h3 style="color: var(--dark); font-size: 2.2rem; margin-bottom: 30px; text-align: center;">
                    🚀 ¿Cómo se usa?
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                    <div style="text-align: center; padding: 20px;">
                        <div style="background: var(--gradient-primary); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 20px;">1</div>
                        <h4 style="margin-bottom: 15px; color: var(--dark);">Instala un pequeño script</h4>
                        <p style="color: #666;">En tus páginas de captura, checkout o cualquier formulario</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="background: var(--gradient-primary); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 20px;">2</div>
                        <h4 style="margin-bottom: 15px; color: var(--dark);">Elige tus reglas</h4>
                        <p style="color: #666;">Países permitidos, validaciones de correo y nivel de bloqueo</p>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="background: var(--gradient-primary); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 20px;">3</div>
                        <h4 style="margin-bottom: 15px; color: var(--dark);">Activa y listo</h4>
                        <p style="color: #666;">Lo bueno entra, lo malo se queda fuera automáticamente</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2 class="section-title">🚀 Casos de Uso Principales</h2>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Landing Pages y Lead Magnets</h3>
                    <p>Asegura que solo emails reales entren a tus embudos de conversión y campañas de nutrición.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Registro de Webinars</h3>
                    <p>Evita registros falsos que inflan tus números pero no asisten. Solo audiencia real y comprometida.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🛒</div>
                    <h3>Checkout y Alta de Clientes</h3>
                    <p>Protege tu proceso de venta de bots y tráfico fraudulento que puede afectar tus métricas.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📞</div>
                    <h3>Formularios de Contacto</h3>
                    <p>Recibe solo consultas reales de clientes potenciales de países donde operas.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🌍</div>
                    <h3>Control Geográfico Inteligente</h3>
                    <p>Bloquea o permite países específicos. Ideal para cumplimiento GDPR o restricciones comerciales.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Implementación en 2 Minutos</h3>
                    <p>Un solo script, configuración visual desde el dashboard. No necesitas conocimientos técnicos.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <h2 class="section-title">💎 Planes para Todos los Tamaños</h2>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3 class="plan-name">Gratuito</h3>
                    <div class="price">$0<span>/mes</span></div>
                    <ul class="features-list">
                        <li>🎯 <strong>50 validaciones/mes</strong></li>
                        <li>1 sitio web protegido</li>
                        <li>Control básico por países</li>
                        <li>Email support</li>
                        <li>Dashboard básico</li>
                    </ul>
                    <button class="plan-btn btn-free" onclick="goToRegister()">🎯 Empezar Gratis</button>
                </div>
                
                <div class="pricing-card featured">
                    <h3 class="plan-name">Básico</h3>
                    <div class="price">$19<span>/mes</span></div>
                    <ul class="features-list">
                        <li>⚡ <strong>500 validaciones/mes</strong></li>
                        <li>3 sitios web protegidos</li>
                        <li>Todos los países disponibles</li>
                        <li>Chat support prioritario</li>
                        <li>Dashboard avanzado</li>
                        <li>Estadísticas detalladas</li>
                    </ul>
                    <button class="plan-btn" onclick="goToLogin()">💫 Iniciar Sesión</button>
                </div>
                
                <div class="pricing-card">
                    <h3 class="plan-name">Premium</h3>
                    <div class="price">$49<span>/mes</span></div>
                    <ul class="features-list">
                        <li>🚀 <strong>5,000 validaciones/mes</strong></li>
                        <li>10 sitios web protegidos</li>
                        <li>Control VPN/Proxy/Tor avanzado</li>
                        <li>Phone support</li>
                        <li>API prioritaria</li>
                        <li>Informes personalizados</li>
                    </ul>
                    <button class="plan-btn btn-premium" onclick="goToLogin()">🔥 Iniciar Sesión</button>
                </div>
                
                <div class="pricing-card">
                    <h3 class="plan-name">Enterprise</h3>
                    <div class="price">$149<span>/mes</span></div>
                    <ul class="features-list">
                        <li>⭐ <strong>Validaciones ILIMITADAS</strong></li>
                        <li>Sitios web ILIMITADOS</li>
                        <li>API ultra-rápida dedicada</li>
                        <li>Soporte 24/7 prioritario</li>
                        <li>Implementación personalizada</li>
                        <li>SLA garantizado 99.9%</li>
                    </ul>
                    <button class="plan-btn btn-enterprise" onclick="goToLogin()">👑 Iniciar Sesión</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Form -->
    <section id="register" class="registration">
        <div class="container">
            <h2 class="section-title">🎯 ¡Empieza Gratis Hoy Mismo!</h2>
            
            <form class="registration-form" method="POST" action="register_process.php">
                <div class="form-group">
                    <label for="name">Nombre *</label>
                    <input type="text" id="name" name="name" required placeholder="Tu nombre completo">
                </div>
                
                <div class="form-group">
                    <label for="phone">WhatsApp/Teléfono *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="Aqui Tu WhatsApp/teléfono">
                </div>
                
                <div class="form-group">
                    <label for="email">Correo Principal *</label>
                    <input type="email" id="email" name="email" required placeholder="Tu Correo Principal">
                </div>
                
                <div class="form-group">
                    <label for="website">Sitio Web a Proteger *</label>
                    <input type="url" id="website" name="website" placeholder="https://tudominio.com" required>
                </div>
                
                <!-- Campo oculto para plan gratuito por defecto -->
                <input type="hidden" name="plan" value="free">
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.3rem; padding: 20px;">
                        🚀 Crear Cuenta Gratuita
                    </button>
                </div>
                
                <p style="text-align: center; opacity: 0.9; font-size: 1rem; margin-top: 1rem;">
                    ✨ Al registrarte, recibirás tus credenciales por email.<br>
                    <strong>Sin compromiso. Cancela cuando quieras.</strong>
                </p>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> GeoControl SaaS. Todos los derechos reservados.</p>
            <p>🌍 Protege tus sitios web con tecnología inteligente de geolocalización.</p>
        </div>
    </footer>

    <!-- JavaScript Avanzado -->
    <script>
        // Particles Background
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Tamaño aleatorio
                const size = Math.random() * 4 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                
                // Posición al
                // Posición aleatoria
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                
                // Animación con delay aleatorio
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Auto-ocultar alertas
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 7000);

        // Función para actualizar información del plan
        function updatePlanInfo() {
            const planSelect = document.getElementById('plan');
            const planInfo = document.getElementById('plan-info');
            const btnText = document.getElementById('btn-text');
            
            const planData = {
                'free': {
                    info: '🎯 <strong>Plan Gratuito seleccionado:</strong> 50 validaciones/mes. Perfecto para empezar. Puedes actualizar cuando quieras.',
                    button: 'Crear Cuenta Gratuita',
                    class: 'alert-info'
                },
                'basic': {
                    info: '💫 <strong>Plan Básico seleccionado ($19/mes):</strong> 3 sitios, 500 validaciones/mes. Serás redirigido al pago.',
                    button: 'Empezar Plan Básico',
                    class: 'alert-success'
                },
                'premium': {
                    info: '🔥 <strong>Plan Premium seleccionado ($49/mes):</strong> 10 sitios, 5,000 validaciones/mes, control VPN/Proxy.',
                    button: 'Empezar Plan Premium',
                    class: 'alert-success'
                },
                'enterprise': {
                    info: '👑 <strong>Plan Enterprise seleccionado ($149/mes):</strong> Validaciones ILIMITADAS. Contactaremos contigo para configuración personalizada.',
                    button: 'Contactar Ventas Enterprise',
                    class: 'alert-success'
                }
            };
            
            const selectedPlan = planData[planSelect.value];
            
            if (selectedPlan) {
                planInfo.className = `alert ${selectedPlan.class}`;
                planInfo.innerHTML = selectedPlan.info;
                btnText.textContent = selectedPlan.button;
            }
        }
        
        // Función para ir a la sección de registro (plan gratuito)
        function goToRegister() {
            console.log('ZipGeo: Navegando a la sección de registro');
            
            // Efecto visual en el botón
            const button = event.target;
            const originalText = button.textContent;
            
            button.style.transform = 'scale(0.95)';
            button.textContent = '🔄 Cargando...';
            
            setTimeout(() => {
                button.textContent = originalText;
                button.style.transform = 'scale(1)';
                
                // Scroll suave a la sección de registro
                const registerSection = document.querySelector('#register');
                if (registerSection) {
                    registerSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 300);
        }
        
        // Función para redirigir al login - planes de pago requieren login primero
        function goToLogin() {
            console.log('ZipGeo: Redirigiendo al login para registro/acceso');
            
            // Efecto visual en el botón
            const button = event.target;
            const originalText = button.textContent;
            
            button.style.transform = 'scale(0.95)';
            button.textContent = '🔄 Redirigiendo...';
            
            setTimeout(() => {
                window.location.href = 'https://validatumail.cc/saas/public/login.php';
            }, 500);
        }
        
        // Función para ir directamente al checkout con plan predefinido (mantenemos para compatibilidad)
        function goToCheckout(planType) {
            // Ahora redirige al login en lugar del checkout
            goToLogin();
        }
        
        // Función para seleccionar plan desde tarjetas (mantener compatibilidad)
        function selectPlan(planType) {
            // Ahora redirige al login
            goToLogin();
        }
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            updatePlanInfo();
            
            // Efecto de escritura en el título
            const title = document.querySelector('.hero h1');
            if (title) {
                title.style.opacity = '1';
            }
        });
    </script>
</body>
</html>