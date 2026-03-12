<?php
/**
 * Script de Prueba de Email AWS SES
 * 
 * Este script prueba si el email con AWS SES está funcionando correctamente
 */

require_once '../config/config.php';
require_once '../config/aws_ses_smtp.php';

echo "<h1>🧪 Prueba de Email AWS SES</h1>";

// Mostrar configuración actual
echo "<h2>📋 Configuración Actual:</h2>";
echo "<strong>Host:</strong> " . MAIL_HOST . "<br>";
echo "<strong>Puerto:</strong> " . MAIL_PORT . "<br>";
echo "<strong>Usuario:</strong> " . MAIL_USERNAME . "<br>";
echo "<strong>Password:</strong> " . str_repeat('*', strlen(MAIL_PASSWORD)) . "<br>";
echo "<strong>From Email:</strong> " . MAIL_FROM_EMAIL . "<br>";
echo "<strong>From Name:</strong> " . MAIL_FROM_NAME . "<br><br>";

// Verificar si se debe enviar email de prueba
if (isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        echo "<div style='color: red;'>❌ Error: Email de prueba requerido</div>";
    } else {
        echo "<h2>📤 Enviando Email de Prueba...</h2>";
        
        $subject = "🧪 Prueba de Email - " . SAAS_SITE_NAME;
        $message = "
        <html>
        <body>
            <h2>✅ Email de Prueba Exitoso</h2>
            <p>Si recibes este email, significa que AWS SES está configurado correctamente.</p>
            <p><strong>Enviado desde:</strong> " . MAIL_FROM_EMAIL . "</p>
            <p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>Servidor:</strong> " . MAIL_HOST . "</p>
        </body>
        </html>";
        
        // Crear versión de texto plano
        $textMessage = "✅ Email de Prueba Exitoso\n\n";
        $textMessage .= "Si recibes este email, significa que AWS SES está configurado correctamente.\n\n";
        $textMessage .= "Enviado desde: " . MAIL_FROM_EMAIL . "\n";
        $textMessage .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $textMessage .= "Servidor: " . MAIL_HOST;
        
        // Intentar enviar email usando AWS SES SMTP robusto
        if (AwsSesSmtp::send($test_email, $subject, $message, $textMessage)) {
            echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #e8f5e8;'>
                    ✅ <strong>Email enviado exitosamente a:</strong> $test_email<br>
                    📧 Revisa tu bandeja de entrada (y spam)<br>
                    ⏰ Puede tardar hasta 5 minutos en llegar
                  </div>";
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #ffe8e8;'>
                    ❌ <strong>Error enviando email</strong><br>
                    🔧 Revisa la configuración de AWS SES<br>
                    ⚠️ Verifica que el dominio esté verificado en AWS
                  </div>";
        }
    }
}

?>

<h2>🧪 Probar Email Ahora</h2>
<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
    <label for="test_email"><strong>Email de prueba:</strong></label><br>
    <input type="email" name="test_email" id="test_email" 
           placeholder="tu@email.com" required 
           style="width: 300px; padding: 8px; margin: 10px 0;">
    <br>
    <button type="submit" name="send_test" 
            style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        📤 Enviar Email de Prueba
    </button>
</form>

<h2>🔧 Problemas Comunes y Soluciones</h2>

<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;">
    <h3>⚠️ El email no llega:</h3>
    <ol>
        <li><strong>Verificar dominio en AWS:</strong> En AWS SES, debes verificar el dominio <code>validatumail.cc</code></li>
        <li><strong>Verificar email:</strong> O verificar el email <code>noreply@validatumail.cc</code> en AWS SES</li>
        <li><strong>Modo Sandbox:</strong> AWS SES podría estar en modo sandbox (solo emails verificados)</li>
        <li><strong>Región incorrecta:</strong> Verificar que uses us-east-1</li>
    </ol>
</div>

<div style="background: #cce7ff; padding: 15px; border-radius: 8px; margin: 10px 0;">
    <h3>🛠️ Pasos para Configurar AWS SES:</h3>
    <ol>
        <li>Ve a AWS Console → SES</li>
        <li>Selecciona región <strong>us-east-1</strong></li>
        <li>En "Verified identities" → "Create identity"</li>
        <li>Selecciona "Domain" y agrega <strong>validatumail.cc</strong></li>
        <li>Sigue las instrucciones para verificar con DNS</li>
        <li>Espera a que aparezca como "Verified"</li>
    </ol>
</div>

<div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;">
    <h3>✅ Alternativa Simple:</h3>
    <p>Si AWS SES es complicado, puedes cambiar temporalmente a Gmail:</p>
    <ol>
        <li>En <code>saas/config/config.php</code> línea 15:</li>
        <li>Cambiar <code>MAIL_HOST</code> a <code>smtp.gmail.com</code></li>
        <li>Cambiar <code>MAIL_USERNAME</code> a tu Gmail</li>
        <li>Usar contraseña de aplicación de Gmail</li>
    </ol>
</div>

<p><a href="../public/" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Volver al SaaS</a></p>