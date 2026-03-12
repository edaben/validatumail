<?php
/**
 * Página de Prueba del Webhook de Autoresponder
 * 
 * Esta página permite:
 * - Probar el webhook manualmente
 * - Ver los logs del webhook
 * - Configurar la URL del webhook
 */

session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/saas/config/config.php';
require_once __DIR__ . '/saas/config/database.php';

// Sistema de autenticación simple
$admin_users = [
    'admin' => 'admin2024',
    'eduardo' => 'eduardo2024',
    'supervisor' => 'super2024'
];

$is_authenticated = isset($_SESSION['webhook_test_authenticated']) && $_SESSION['webhook_test_authenticated'] === true;

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($admin_users[$username]) && $admin_users[$username] === $password) {
        $_SESSION['webhook_test_authenticated'] = true;
        $_SESSION['webhook_test_username'] = $username;
        $is_authenticated = true;
    } else {
        $login_error = 'Usuario o contraseña incorrectos';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: test_webhook.php');
    exit;
}

// Función para enviar webhook de prueba
function sendTestWebhook($webhook_url, $test_data) {
    $webhook_data = [
        'source' => 'geocontrol_saas_test',
        'timestamp' => time(),
        'test_mode' => true,
        'lead' => [
            'nombre' => $test_data['nombre'],
            'telefono' => $test_data['telefono'],
            'email' => $test_data['email'],
            'sitio_web' => $test_data['sitio_web'],
            'client_id' => $test_data['client_id'],
            'api_key' => 'test_api_key_' . substr(md5(time()), 0, 10),
            'plan' => $test_data['plan'],
            'fecha_registro' => date('Y-m-d H:i:s')
        ],
        'metadata' => [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'test_ip',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'test_agent',
            'referrer' => 'webhook_test_page'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($webhook_data, JSON_PRETTY_PRINT),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: GeoControl-SaaS-Test/1.0',
            'X-Webhook-Source: geocontrol-saas-test',
            'X-Test-Mode: true'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [
        'success' => ($http_code >= 200 && $http_code < 300),
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error,
        'sent_data' => $webhook_data,
        'info' => $info
    ];
}

// Procesar envío de webhook de prueba
if ($is_authenticated && isset($_POST['send_test_webhook'])) {
    $webhook_url = $_POST['webhook_url'] ?? '';
    $test_data = [
        'nombre' => $_POST['test_nombre'] ?? 'Cliente de Prueba',
        'telefono' => $_POST['test_telefono'] ?? '+593999999999',
        'email' => $_POST['test_email'] ?? 'test@ejemplo.com',
        'sitio_web' => $_POST['test_website'] ?? 'https://ejemplo.com',
        'client_id' => 'test_' . substr(md5(time()), 0, 10),
        'plan' => $_POST['test_plan'] ?? 'free'
    ];
    
    $webhook_result = sendTestWebhook($webhook_url, $test_data);
}

if (!$is_authenticated) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Webhook - Acceso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-container h1 { color: #333; margin-bottom: 30px; font-size: 24px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .login-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .login-btn:hover { transform: translateY(-2px); }
        .error { color: #e74c3c; margin-top: 15px; padding: 10px; background: #ffeaea; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔗 Test Webhook</h1>
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required placeholder="admin, eduardo, supervisor">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Acceder</button>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo $login_error; ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
<?php
exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Webhook - Autoresponder</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 28px; font-weight: 600; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background 0.3s; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; text-decoration: none; display: inline-block; }
        .btn:hover { transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); }
        .alert { padding: 15px; margin: 20px 0; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .code-block { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 5px; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .section-title { font-size: 20px; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e1e5e9; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🔗 Test Webhook Autoresponder</h1>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: rgba(255,255,255,0.8);">👤 <?php echo $_SESSION['webhook_test_username'] ?? 'Admin'; ?></span>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="logout" class="logout-btn">🚪 Salir</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="alert alert-info">
            <strong>ℹ️ Información:</strong> Esta página te permite probar el webhook que se encuentra implementado en 
            <code>saas/public/register_process.php</code>. El webhook se ejecuta automáticamente cada vez que un nuevo 
            cliente se registra en el sistema SaaS.
        </div>

        <!-- Configuración del Webhook -->
        <div class="card">
            <h2 class="section-title">⚙️ Configuración del Webhook</h2>
            
            <div class="alert alert-warning">
                <strong>📋 Estado Actual del Webhook:</strong><br>
                • <strong>URL:</strong> https://tu-autoresponder.com/webhook/nuevo-registro<br>
                • <strong>Método:</strong> POST<br>
                • <strong>Formato:</strong> JSON<br>
                • <strong>Timeout:</strong> 10 segundos<br>
                • <strong>Se ejecuta:</strong> Automáticamente en cada registro de cliente
            </div>
            
            <p><strong>Para cambiar la URL del webhook:</strong> Edita la línea 395 en <code>saas/public/register_process.php</code></p>
        </div>

        <!-- Prueba Manual del Webhook -->
        <div class="card">
            <h2 class="section-title">🧪 Enviar Webhook de Prueba</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="webhook_url">URL del Webhook:</label>
                    <input type="url" id="webhook_url" name="webhook_url" 
                           value="https://webhook.site/unique-url" 
                           placeholder="https://tu-autoresponder.com/webhook/nuevo-registro"
                           required>
                    <small style="color: #666;">💡 Puedes usar https://webhook.site para crear un endpoint temporal de prueba</small>
                </div>
                
                <h3 style="margin: 20px 0 10px 0; color: #333;">📝 Datos de Prueba</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="test_nombre">Nombre:</label>
                        <input type="text" id="test_nombre" name="test_nombre" value="Cliente de Prueba" required>
                    </div>
                    <div class="form-group">
                        <label for="test_telefono">Teléfono:</label>
                        <input type="text" id="test_telefono" name="test_telefono" value="+593999888777" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="test_email">Email:</label>
                        <input type="email" id="test_email" name="test_email" value="test@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <label for="test_website">Sitio Web:</label>
                        <input type="url" id="test_website" name="test_website" value="https://ejemplo.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="test_plan">Plan:</label>
                    <select id="test_plan" name="test_plan">
                        <option value="free">Gratuito</option>
                        <option value="basic">Básico</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                
                <button type="submit" name="send_test_webhook" class="btn btn-success">
                    🚀 Enviar Webhook de Prueba
                </button>
            </form>
        </div>

        <?php if (isset($webhook_result)): ?>
        <!-- Resultados del Test -->
        <div class="card">
            <h2 class="section-title">📊 Resultados del Test</h2>
            
            <?php if ($webhook_result['success']): ?>
                <div class="alert alert-success">
                    <strong>✅ Webhook enviado exitosamente!</strong><br>
                    Código HTTP: <?php echo $webhook_result['http_code']; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    <strong>❌ Error en el webhook</strong><br>
                    Código HTTP: <?php echo $webhook_result['http_code']; ?><br>
                    <?php if ($webhook_result['error']): ?>
                        Error cURL: <?php echo htmlspecialchars($webhook_result['error']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h3>📤 Datos Enviados:</h3>
            <div class="code-block"><?php echo htmlspecialchars(json_encode($webhook_result['sent_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></div>
            
            <h3>📥 Respuesta del Servidor:</h3>
            <div class="code-block"><?php echo htmlspecialchars($webhook_result['response'] ?: 'Sin respuesta'); ?></div>
            
            <h3>🔍 Información Técnica:</h3>
            <div class="code-block">
URL: <?php echo htmlspecialchars($webhook_result['info']['url']); ?>
Código HTTP: <?php echo $webhook_result['info']['http_code']; ?>
Tiempo Total: <?php echo round($webhook_result['info']['total_time'], 3); ?>s
Tiempo de Conexión: <?php echo round($webhook_result['info']['connect_time'], 3); ?>s
Tamaño Descargado: <?php echo $webhook_result['info']['download_content_length']; ?> bytes
Content-Type: <?php echo $webhook_result['info']['content_type'] ?? 'N/A'; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guía de Implementación -->
        <div class="card">
            <h2 class="section-title">📚 Guía de Implementación del Webhook</h2>
            
            <h3>🎯 ¿Qué hace el webhook?</h3>
            <p>El webhook se ejecuta automáticamente cada vez que un nuevo cliente se registra en el sistema SaaS. Envía los datos del cliente a tu autoresponder o sistema de marketing.</p>
            
            <h3>📋 Datos que envía el webhook:</h3>
            <ul style="margin: 15px 0; padding-left: 30px;">
                <li><strong>Información del cliente:</strong> nombre, teléfono, email, sitio web</li>
                <li><strong>Credenciales:</strong> client_id, api_key</li>
                <li><strong>Plan seleccionado:</strong> free, basic, premium, enterprise</li>
                <li><strong>Metadata:</strong> IP, user agent, fecha de registro</li>
            </ul>
            
            <h3>🔧 Cómo configurar tu autoresponder:</h3>
            <ol style="margin: 15px 0; padding-left: 30px;">
                <li><strong>Crea un endpoint webhook</strong> en tu autoresponder (GetResponse, Mailchimp, etc.)</li>
                <li><strong>Edita la línea 395</strong> en <code>saas/public/register_process.php</code></li>
                <li><strong>Cambia la URL:</strong> <code>'https://tu-autoresponder.com/webhook/nuevo-registro'</code></li>
                <li><strong>Configura tu autoresponder</strong> para recibir JSON con los campos del webhook</li>
            </ol>
            
            <h3>🛠️ Herramientas de Prueba Recomendadas:</h3>
            <div style="margin: 15px 0;">
                <a href="https://webhook.site" target="_blank" class="btn" style="margin: 5px;">
                    🌐 Webhook.site (Crear endpoint temporal)
                </a>
                <a href="https://requestbin.com" target="_blank" class="btn" style="margin: 5px;">
                    📦 RequestBin (Inspeccionar requests)
                </a>
                <a href="https://ngrok.com" target="_blank" class="btn" style="margin: 5px;">
                    🔗 ngrok (Túnel para desarrollo local)
                </a>
            </div>
            
            <div class="alert alert-info">
                <strong>💡 Tip:</strong> Usa webhook.site para crear un endpoint temporal y ver exactamente qué datos se están enviando. 
                Copia la URL única que te genere y úsala en el formulario de arriba.
            </div>
        </div>

        <!-- Logs del Sistema -->
        <div class="card">
            <h2 class="section-title">📋 Ver Logs del Sistema</h2>
            
            <p>Para ver los logs reales del webhook en el sistema de producción:</p>
            
            <div style="margin: 20px 0;">
                <a href="saas/admin/logs.php" target="_blank" class="btn">
                    📋 Ver Logs del Sistema
                </a>
                <a href="admin.php" target="_blank" class="btn" style="margin-left: 10px;">
                    🔧 Panel de Administración
                </a>
            </div>
            
            <div class="alert alert-warning">
                <strong>⚠️ Nota:</strong> Los logs del webhook se guardan automáticamente en la tabla <code>system_logs</code> 
                cada vez que se ejecuta. Puedes revisar estos logs para ver el historial de webhooks enviados.
            </div>
        </div>

        <!-- Ejemplo de Código del Receptor -->
        <div class="card">
            <h2 class="section-title">💻 Ejemplo de Código para Recibir el Webhook</h2>
            
            <h3>PHP (ejemplo básico):</h3>
            <div class="code-block">&lt;?php
// webhook_receiver.php
header('Content-Type: application/json');

// Obtener datos del webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar que viene de GeoControl SaaS
if ($data['source'] !== 'geocontrol_saas') {
    http_response_code(400);
    echo json_encode(['error' => 'Fuente no válida']);
    exit;
}

// Obtener datos del cliente
$cliente = $data['lead'];
$nombre = $cliente['nombre'];
$email = $cliente['email'];
$telefono = $cliente['telefono'];
$plan = $cliente['plan'];

// Procesar el lead en tu sistema
// Ejemplo: agregar a lista de correo, CRM, etc.

// Responder con éxito
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Lead procesado correctamente',
    'client_id' => $cliente['client_id']
]);
?&gt;</div>

            <h3>Node.js/Express (ejemplo):</h3>
            <div class="code-block">const express = require('express');
const app = express();

app.use(express.json());

app.post('/webhook/nuevo-registro', (req, res) => {
    const { source, lead, metadata } = req.body;
    
    // Validar fuente
    if (source !== 'geocontrol_saas') {
        return res.status(400).json({ error: 'Fuente no válida' });
    }
    
    // Procesar datos del cliente
    console.log('Nuevo cliente:', lead);
    
    // Tu lógica aquí (guardar en BD, enviar a CRM, etc.)
    
    res.json({ 
        status: 'success', 
        message: 'Lead procesado correctamente' 
    });
});

app.listen(3000);</div>
        </div>
    </div>
</body>
</html>