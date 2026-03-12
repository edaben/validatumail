<?php
/**
 * Sistema AWS SES SMTP Robusto
 * 
 * Implementación SMTP específica para AWS SES
 * Compatible con las credenciales que funcionan en otros servicios
 */

class AwsSesSmtp 
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $socket;
    
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
     * Enviar email usando protocolo SMTP compatible con AWS SES
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) 
    {
        try {
            // 1. Conectar al servidor
            $this->connect();
            
            // 2. Inicializar SMTP
            $this->smtpHelo();
            
            // 3. Iniciar TLS
            $this->startTls();
            
            // 4. Autenticar
            $this->authenticate();
            
            // 5. Enviar email
            $this->sendMessage($to, $subject, $htmlBody, $textBody);
            
            // 6. Cerrar conexión
            $this->disconnect();
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("AWS SES Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Conectar al servidor SMTP
     */
    private function connect() 
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        
        if (!$this->socket) {
            throw new Exception("No se pudo conectar a {$this->host}:{$this->port} - $errstr ($errno)");
        }
        
        // Leer banner de bienvenida
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '220')) {
            throw new Exception("Error en banner de bienvenida: $response");
        }
    }
    
    /**
     * Comando EHLO
     */
    private function smtpHelo() 
    {
        $hostname = $_SERVER['HTTP_HOST'] ?? 'validatumail.cc';
        $this->sendCommand("EHLO $hostname");
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '250')) {
            throw new Exception("Error en EHLO: $response");
        }
    }
    
    /**
     * Iniciar TLS
     */
    private function startTls() 
    {
        $this->sendCommand("STARTTLS");
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '220')) {
            throw new Exception("Error iniciando TLS: $response");
        }
        
        // Habilitar encriptación TLS
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("No se pudo habilitar TLS");
        }
        
        // EHLO después de TLS
        $hostname = $_SERVER['HTTP_HOST'] ?? 'validatumail.cc';
        $this->sendCommand("EHLO $hostname");
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '250')) {
            throw new Exception("Error en EHLO post-TLS: $response");
        }
    }
    
    /**
     * Autenticar con AWS SES
     */
    private function authenticate() 
    {
        // Iniciar autenticación LOGIN
        $this->sendCommand("AUTH LOGIN");
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '334')) {
            throw new Exception("Error iniciando AUTH LOGIN: $response");
        }
        
        // Enviar username
        $this->sendCommand(base64_encode($this->username));
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '334')) {
            throw new Exception("Error en username: $response");
        }
        
        // Enviar password
        $this->sendCommand(base64_encode($this->password));
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '235')) {
            throw new Exception("Error en autenticación: $response - Verifica credenciales AWS");
        }
    }
    
    /**
     * Enviar el mensaje completo
     */
    private function sendMessage($to, $subject, $htmlBody, $textBody = null) 
    {
        // MAIL FROM
        $this->sendCommand("MAIL FROM:<{$this->fromEmail}>");
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '250')) {
            throw new Exception("Error en MAIL FROM: $response");
        }
        
        // RCPT TO
        $this->sendCommand("RCPT TO:<$to>");
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '250')) {
            throw new Exception("Error en RCPT TO: $response");
        }
        
        // DATA
        $this->sendCommand("DATA");
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '354')) {
            throw new Exception("Error en DATA: $response");
        }
        
        // Construir y enviar email
        $emailContent = $this->buildEmail($to, $subject, $htmlBody, $textBody);
        $this->sendCommand($emailContent . "\r\n.");
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response, '250')) {
            throw new Exception("Error enviando mensaje: $response");
        }
    }
    
    /**
     * Construir email completo con headers correctos
     */
    private function buildEmail($to, $subject, $htmlBody, $textBody = null) 
    {
        $messageId = '<' . uniqid() . '@validatumail.cc>';
        $date = date('r');
        
        $email = "Date: $date\r\n";
        $email .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $email .= "To: $to\r\n";
        $email .= "Subject: $subject\r\n";
        $email .= "Message-ID: $messageId\r\n";
        $email .= "MIME-Version: 1.0\r\n";
        
        if ($textBody) {
            // Email multipart
            $boundary = 'boundary_' . uniqid();
            $email .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $email .= "\r\n";
            
            // Parte texto
            $email .= "--$boundary\r\n";
            $email .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $email .= "Content-Transfer-Encoding: 8bit\r\n";
            $email .= "\r\n";
            $email .= $textBody . "\r\n";
            
            // Parte HTML
            $email .= "--$boundary\r\n";
            $email .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email .= "Content-Transfer-Encoding: 8bit\r\n";
            $email .= "\r\n";
            $email .= $htmlBody . "\r\n";
            
            $email .= "--$boundary--\r\n";
        } else {
            // Solo HTML
            $email .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email .= "Content-Transfer-Encoding: 8bit\r\n";
            $email .= "\r\n";
            $email .= $htmlBody . "\r\n";
        }
        
        return $email;
    }
    
    /**
     * Enviar comando SMTP
     */
    private function sendCommand($command) 
    {
        fwrite($this->socket, $command . "\r\n");
        fflush($this->socket);
    }
    
    /**
     * Leer respuesta del servidor
     */
    private function readResponse() 
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            // Si la línea no continúa (no tiene - después del código), terminar
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return trim($response);
    }
    
    /**
     * Verificar si la respuesta es exitosa
     */
    private function isSuccessResponse($response, $expectedCode) 
    {
        return preg_match('/^' . $expectedCode . '[\s-]/', $response);
    }
    
    /**
     * Desconectar del servidor
     */
    private function disconnect() 
    {
        if ($this->socket) {
            $this->sendCommand("QUIT");
            fclose($this->socket);
        }
    }
    
    /**
     * Log de errores
     */
    private function logError($message) 
    {
        error_log($message);
        
        // Log en base de datos si es posible
        try {
            logActivity('error', 'AWS SES Error', ['message' => $message]);
        } catch (Exception $e) {
            // Ignorar errores de logging
        }
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
 * Función helper compatible
 */
function sendAwsSesEmail($to, $subject, $htmlBody, $textBody = null) 
{
    return AwsSesSmtp::send($to, $subject, $htmlBody, $textBody);
}