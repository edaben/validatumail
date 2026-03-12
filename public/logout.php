<?php
/**
 * Logout del Cliente SaaS
 * 
 * Cierra la sesión del cliente y lo redirige al login
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Log de actividad si hay sesión activa
if (isset($_SESSION['client_id'])) {
    try {
        logActivity('info', 'Cliente cerró sesión', [
            'email' => $_SESSION['client_email'] ?? 'unknown'
        ], $_SESSION['client_id']);
    } catch (Exception $e) {
        // Ignorar errores de logging en logout
        error_log('Error logging logout: ' . $e->getMessage());
    }
}

// Destruir toda la información de la sesión
session_destroy();

// Limpiar cookies si existen
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirigir al login con mensaje
header('Location: login.php?message=' . urlencode('Sesión cerrada exitosamente') . '&type=info');
exit;
?>