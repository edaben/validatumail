<?php
/**
 * Diagnóstico Avanzado AWS SES
 * 
 * Este script diagnostica exactamente qué está pasando con AWS SES
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/aws_ses_smtp.php';

echo "<h1>🔍 Diagnóstico AWS SES</h1>";

// Mostrar configuración
echo "<h2>📋 Configuración Actual:</h2>";
echo "<strong>Host:</strong> " . MAIL_HOST . "<br>";
echo "<strong>Puerto:</strong> " . MAIL_PORT . "<br>";
echo "<strong>Usuario:</strong> " . MAIL_USERNAME . "<br>";
echo "<strong>Password:</strong> " . substr(MAIL_PASSWORD, 0, 10) . "..." . "<br>";
echo "<strong>From Email:</strong> " . MAIL_FROM_EMAIL . "<br>";
echo "<strong>From Name:</strong> " . MAIL_FROM_NAME . "<br><br>";

// Prueba de conectividad básica
echo "<h2>🔌 Prueba de Conectividad:</h2>";

$socket = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 10);
if ($socket) {
    echo "✅ <strong>Conexión al servidor AWS exitosa</strong><br>";
    
    $response = fgets($socket, 1024);
    echo "📥 Respuesta del servidor: " . htmlspecialchars($response) . "<br>";
    fclose($socket);
} else {
    echo "❌ <strong>Error conectando al servidor:</strong> $errstr ($errno)<br>";
}

echo "<br>";

// Verificar si se puede enviar email
if (isset($_POST['test_email']) && !empty($_POST['test_email'])) {
    echo "<h2>📤 Enviando Email de Prueba...</h2>";
    
    $test_email = $_POST['test_email'];
    $subject = "🧪 Prueba AWS SES - " . date('H:i:s');
    
    $htmlBody = "
    <html>
    <body>
        <h2>✅ Prueba AWS SES Exitosa</h2>
        <p>Si recibes este email, AWS SES está funcionando correctamente.</p>
        <p><strong>Enviado desde:</strong> " . MAIL_FROM_EMAIL . "</p>
        <p><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Host:</strong> " . MAIL_HOST . "</p>
        <p><strong>Usuario AWS:</strong> " . MAIL_USERNAME . "</p>
    </body>
    </html>";
    
    $textBody = "✅ Prueba AWS SES Exitosa\n\n";
    $textBody .= "Si recibes este email, AWS SES está funcionando correctamente.\n\n";
    $textBody .= "Enviado desde: " . MAIL_FROM_EMAIL . "\n";
    $textBody .= "Hora: " . date('Y-m-d H:i:s') . "\n";
    $textBody .= "Host: " . MAIL_HOST . "\n";
    $textBody .= "Usuario AWS: " . MAIL_USERNAME;
    
    try {
        $mailer = new AwsSesSmtp();
        $result = $mailer->sendEmail($test_email, $subject, $htmlBody, $textBody);
        
        if ($result) {
            echo "<div style='color: green; padding: 15px; border: 2px solid green; background: #e8f5e8; border-radius: 8px;'>
                    ✅ <strong>Email enviado exitosamente a:</strong> $test_email<br>
                    📧 Revisa tu bandeja de entrada y spam<br>
                    ⏰ Puede tardar 1-5 minutos en llegar<br><br>
                    🎉 <strong>¡AWS SES está funcionando correctamente!</strong>
                  </div>";
        } else {
            echo "<div style='color: red; padding: 15px; border: 2px solid red; background: #ffe8e8; border-radius: 8px;'>
                    ❌ <strong>Error enviando email</strong><br>
                    🔧 Revisa los logs de error más abajo
                  </div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 15px; border: 2px solid red; background: #ffe8e8; border-radius: 8px;'>
                ❌ <strong>Excepción capturada:</strong> " . htmlspecialchars($e->getMessage()) . "<br>
                📁 <strong>Archivo:</strong> " . $e->getFile() . "<br>
                📍 <strong>Línea:</strong> " . $e->getLine() . "
              </div>";
    }
}

// Mostrar logs de error de PHP
echo "<h2>📋 Logs de Error PHP:</h2>";
$error_log = error_get_last();
if ($error_log) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px;'>";
    echo "<strong>Último error PHP:</strong><br>";
    echo "<strong>Mensaje:</strong> " . htmlspecialchars($error_log['message']) . "<br>";
    echo "<strong>Archivo:</strong> " . htmlspecialchars($error_log['file']) . "<br>";
    echo "<strong>Línea:</strong> " . $error_log['line'] . "<br>";
    echo "</div>";
} else {
    echo "✅ No hay errores PHP recientes<br>";
}

echo "<br>";

// Formulario de prueba
?>

<h2>🧪 Enviar Email de Prueba</h2>
<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;">
    <label for="test_email"><strong>Email de destino:</strong></label><br>
    <input type="email" name="test_email" id="test_email" 
           placeholder="eduardodavila9@gmail.com" required 
           value="eduardodavila9@gmail.com"
           style="width: 400px; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px;">
    <br>
    <button type="submit" 
            style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
        📤 Enviar Prueba AWS SES
    </button>
</form>

<h2>🛠️ Verificaciones Adicionales</h2>
<div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 10px 0;">
    <h3>✅ Checklist AWS SES:</h3>
    <ul>
        <li>✅ <strong>Credenciales:</strong> Funcionan en FluentCRM</li>
        <li>✅ <strong>Dominio:</strong> rastroseguro.com verificado</li>
        <li>✅ <strong>Región:</strong> us-east-1</li>
        <li>❓ <strong>Sandbox Mode:</strong> ¿Estás fuera del sandbox?</li>
        <li>❓ <strong>Email Verified:</strong> ¿eduardo@rastroseguro.com está verificado?</li>
    </ul>
</div>

<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;">
    <h3>🔧 Si no funciona:</h3>
    <p><strong>1. Verificar en AWS Console:</strong></p>
    <ul>
        <li>Ve a AWS SES → Verified identities</li>
        <li>Verifica que <code>eduardo@rastroseguro.com</code> está verificado</li>
        <li>Verifica que <code>rastroseguro.com</code> está verificado</li>
    </ul>
    
    <p><strong>2. Salir de Sandbox:</strong></p>
    <ul>
        <li>En AWS SES → Account dashboard</li>
        <li>Si dice "Sandbox", solicita production access</li>
    </ul>
</div>

<p><a href="../public/" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Volver al SaaS</a></p>

<script>
// Auto-envío de prueba al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Focus en el email
    document.getElementById('test_email').focus();
});
</script>