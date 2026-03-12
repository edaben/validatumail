<?php
/**
 * Sistema de Email AWS SES Mejorado
 * 
 * Esta clase maneja el envío de emails usando AWS SES SMTP
 * Compatible con credenciales AWS SES
 */

class AwsSesMailer 
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    
    public function __construct() 
    {
        $this->host = MAIL_HOST;
        $this->port = MAIL_PORT;
        $this->username = MAIL_USERNAME;
        $this->password = MAIL_PASSWORD;
        $this->fromEmail = MAIL_FROM_EMAIL;
        $this->fromName = MAIL_FROM_NAME;
    }
    
    /**
     * Enviar email usando SMTP directo con AWS SES
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) 
    {
        try {
            // Crear conexión SMTP
            $socket = $this->createSmtpConnection();
            
            if (!$socket) {
                throw new Exception('No se pudo conectar al servidor SMTP');
            }
            
            // Proceso SMTP completo
            $this->smtpCommand($socket, null, '220'); // Banner de bienvenida
            $this->smtpCommand($socket, "EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost', '250');
            $this->smtpCommand($socket, "STARTTLS", '220');
            
            // Crear conexión TLS
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('No se pudo establecer conexión TLS');
            }
            
            // Re-EHLO después de TLS
            $this->smtpCommand($socket, "EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost', '250');
            
            // Autenticación
            $this->smtpCommand($socket, "AUTH LOGIN", '334');
            $this->smtpCommand($socket, base64_encode($this->username), '334');
            $this->smtpCommand($socket, base64_encode($this->password), '235');
            
            // Envío del email
            $this->smtpCommand($socket, "MAIL FROM:<{$this->fromEmail}>", '250');
            $this->smtpCommand($socket, "RCPT TO:<$to>", '250');
            $this->smtpCommand($socket, "DATA", '354');
            
            // Headers y contenido
            $email_content = $this->buildEmailContent($to, $subject, $htmlBody, $textBody);
            fputs($socket, $email_content . "\r\n.\r\n");
            
            $response = fgets($socket, 512);
            if (!preg_match('/^250/', $response)) {
                throw new Exception('Error enviando email: ' . $response);
            }
            
            // Cerrar conexión
            $this->smtpCommand($socket, "QUIT", '221');
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("AWS SES Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear conexión SMTP con AWS SES
     */
    private function createSmtpConnection() 
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}", 
            $errno, 
            $errstr, 
            30, 
            STREAM_CLIENT_CONNECT, 
            $context
        );
        
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno)");
            return false;
        }
        
        return $socket;
    }
    
    /**
     * Ejecutar comando SMTP y verificar respuesta
     */
    private function smtpCommand($socket, $command, $expected_code) 
    {
        if ($command !== null) {
            fputs($socket, $command . "\r\n");
        }
        
        $response = fgets($socket, 512);
        
        if (!preg_match('/^' . $expected_code . '/', $response)) {
            throw new Exception("SMTP Error: Expected $expected_code, got: $response");
        }
        
        return $response;
    }
    
    /**
     * Construir contenido completo del email
     */
    private function buildEmailContent($to, $subject, $htmlBody, $textBody = null) 
    {
        $messageId = '<' . uniqid() . '@' . parse_url($this->fromEmail, PHP_URL_HOST) . '>';
        
        $headers = [
            "Date: " . date('r'),
            "From: {$this->fromName} <{$this->fromEmail}>",
            "To: $to",
            "Subject: $subject",
            "Message-ID: $messageId",
            "MIME-Version: 1.0"
        ];
        
        if ($textBody) {
            // Email multipart (HTML + texto)
            $boundary = 'boundary_' . uniqid();
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
            $headers[] = "";
            
            $content = "--$boundary\r\n";
            $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $content .= $textBody . "\r\n\r\n";
            
            $content .= "--$boundary\r\n";
            $content .= "Content-Type: text/html; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $content .= $htmlBody . "\r\n\r\n";
            
            $content .= "--$boundary--";
        } else {
            // Solo HTML
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 7bit";
            $headers[] = "";
            $content = $htmlBody;
        }
        
        return implode("\r\n", $headers) . "\r\n" . $content;
    }
    
    /**
     * Método estático para envío rápido
     */
    public static function send($to, $subject, $htmlBody, $textBody = null) 
    {
        $mailer = new self();
        return $mailer->sendEmail($to, $subject, $htmlBody, $textBody);
    }
}

/**
 * Función helper para retrocompatibilidad
 */
function sendAwsEmail($to, $subject, $htmlBody, $textBody = null) 
{
    return AwsSesMailer::send($to, $subject, $htmlBody, $textBody);
}