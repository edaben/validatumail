<?php
/**
 * client_script.php ARREGLADO
 * Versión sin errores para generar JavaScript personalizado
 */

// Configuración de errores para producción
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Headers para JavaScript
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=60'); // Reducir caché a 1 minuto
    
    // Incluir archivos necesarios
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    
    // Obtener client_id
    $client_id = $_GET['client'] ?? '';
    
    if (!$client_id) {
        echo 'console.error("ZipGeo: ID de cliente requerido");';
        exit;
    }
    
    // Validar formato
    if (!preg_match('/^[a-f0-9]{32}$/', $client_id)) {
        echo 'console.error("ZipGeo: ID de cliente inválido");';
        exit;
    }
    
    // Conectar a base de datos
    $db = SaasDatabase::getInstance();
    
    // Buscar cliente
    $client = $db->fetchOne(
        "SELECT id, name, status, plan, countries_allowed, countries_denied,
                access_control_mode, allow_vpn, allow_tor, allow_proxy, allow_hosting,
                access_denied_action, redirect_url, monthly_usage, monthly_limit
         FROM clients WHERE client_id = ?",
        [$client_id]
    );
    
    if (!$client) {
        echo 'console.error("ZipGeo: Cliente no encontrado");';
        exit;
    }
    
    if ($client['status'] !== 'active') {
        if ($client['status'] === 'over_limit') {
            echo 'console.error("🚫 ZipGeo: CUENTA BLOQUEADA - Límite mensual excedido");';
        } else {
            echo 'console.error("ZipGeo: Cuenta no activa - Estado: ' . $client['status'] . '");';
        }
        exit;
    }
    
    // VERIFICAR LÍMITE DE USO MENSUAL
    if ($client['monthly_limit'] > 0 && $client['monthly_usage'] >= $client['monthly_limit']) {
        // LOG DETALLADO del bloqueo
        logActivity('warning', 'LÍMITE EXCEDIDO - Generando script de bloqueo', [
            'client_id' => $client_id,
            'usage' => $client['monthly_usage'],
            'limit' => $client['monthly_limit'],
            'plan' => $client['plan'],
            'percent' => round(($client['monthly_usage'] / $client['monthly_limit']) * 100, 1),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ], $client['id']);
        
        // Forzar no-cache cuando límite alcanzado
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Cliente ha alcanzado su límite mensual - generar script de bloqueo
        $plan_name = ucfirst($client['plan']);
        $usage = number_format($client['monthly_usage']);
        $limit = number_format($client['monthly_limit']);
        
        echo <<<JAVASCRIPT
/**
 * ZipGeo - Límite de uso alcanzado
 * Cliente: {$client_id}
 */
(function() {
    console.error('🚫 ZipGeo: LÍMITE EXCEDIDO - {$usage}/{$limit} validaciones para plan {$plan_name}');
    console.error('🚫 ZipGeo: SERVICIO COMPLETAMENTE BLOQUEADO');
    
    // Mostrar mensaje de límite alcanzado
    function showLimitReachedMessage() {
        const existingMessage = document.getElementById('zipgeo-limit-reached');
        if (existingMessage) return;
        
        const message = document.createElement('div');
        message.id = 'zipgeo-limit-reached';
        message.innerHTML = \`
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.95);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 999999;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            ">
                <div style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 50px;
                    border-radius: 20px;
                    text-align: center;
                    max-width: 600px;
                    margin: 20px;
                    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
                ">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📊</div>
                    <h2 style="margin: 0 0 20px 0; font-size: 2.2rem; font-weight: 700;">
                        Límite de Validaciones Alcanzado
                    </h2>
                    <p style="font-size: 1.3rem; margin: 20px 0; opacity: 0.95; line-height: 1.6;">
                        Has utilizado <strong>{$usage} de {$limit}</strong> validaciones de tu plan <strong>{$plan_name}</strong> este mes.
                    </p>
                    <p style="font-size: 1.1rem; margin: 20px 0; opacity: 0.8;">
                        Para continuar usando el servicio, actualiza tu plan o contacta soporte.
                    </p>
                    <div style="margin-top: 40px;">
                        <a href="https://validatumail.cc/saas/client/upgrade_plan.php"
                           style="
                               display: inline-block;
                               background: #28a745;
                               color: white;
                               padding: 15px 30px;
                               text-decoration: none;
                               border-radius: 50px;
                               font-weight: 600;
                               font-size: 1.1rem;
                               margin: 10px;
                               transition: transform 0.3s;
                           "
                           onmouseover="this.style.transform='translateY(-2px)'"
                           onmouseout="this.style.transform='translateY(0)'">
                            🚀 Actualizar Plan
                        </a>
                        <a href="mailto:eduardo@rastroseguro.com?subject=Límite Alcanzado - Cliente {$client_id}"
                           style="
                               display: inline-block;
                               background: rgba(255,255,255,0.2);
                               color: white;
                               padding: 15px 30px;
                               text-decoration: none;
                               border-radius: 50px;
                               font-weight: 600;
                               font-size: 1.1rem;
                               margin: 10px;
                               border: 2px solid rgba(255,255,255,0.3);
                               transition: all 0.3s;
                           "
                           onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            📧 Contactar Soporte
                        </a>
                    </div>
                    <p style="font-size: 0.9rem; margin-top: 30px; opacity: 0.7;">
                        Cliente ID: {$client_id}<br>
                        El límite se restablece cada mes automáticamente.
                    </p>
                </div>
            </div>
        \`;
        document.body.appendChild(message);
        
        // Bloquear formularios
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('ZipGeo: Envío bloqueado - límite mensual alcanzado');
                return false;
            });
        });
        
        // Deshabilitar botones de envío
        const submitButtons = document.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.title = 'Límite mensual alcanzado - actualiza tu plan';
        });
    }
    
    // Mostrar mensaje inmediatamente
    showLimitReachedMessage();
    
    // También mostrar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showLimitReachedMessage);
    }
    
    // Y cuando la página esté completamente cargada
    window.addEventListener('load', showLimitReachedMessage);
    
    // API mínima para compatibilidad
    window.ZipGeo = {
        isReady: () => true,
        isAccessGranted: () => false,
        isLimitReached: () => true,
        getUserLocation: () => null,
        getUsage: () => ({
            current: {$client['monthly_usage']},
            limit: {$client['monthly_limit']},
            plan: '{$client['plan']}'
        })
    };
    
})();
JAVASCRIPT;
        exit;
    }
    
    // Preparar configuración
    $config = [
        'countries_allowed' => $client['countries_allowed'] ? explode(',', $client['countries_allowed']) : [],
        'countries_denied' => $client['countries_denied'] ? explode(',', $client['countries_denied']) : [],
        'access_control_mode' => $client['access_control_mode'],
        'allow_vpn' => (bool)$client['allow_vpn'],
        'allow_tor' => (bool)$client['allow_tor'],
        'allow_proxy' => (bool)$client['allow_proxy'],
        'allow_hosting' => (bool)$client['allow_hosting'],
        'access_denied_action' => $client['access_denied_action'],
        'access_denied_redirect_url' => $client['redirect_url'],
        'client_id' => $client_id,
        'service_url' => SAAS_SITE_URL . '/api/v1',
        'debug_mode' => false
    ];
    
    $config_json = json_encode($config, JSON_UNESCAPED_SLASHES);
    $generated_time = date('Y-m-d H:i:s');
    
    // JavaScript completo y funcional
    echo <<<JAVASCRIPT
/**
 * ZipGeo - Servicio de Control de Acceso Geográfico
 * Cliente: {$client_id}
 * Generado: {$generated_time}
 */

(function() {
    'use strict';
    
    // Configuración del cliente (embebida)
    const CLIENT_CONFIG = {$config_json};
    
    console.log('ZipGeo iniciado para cliente:', CLIENT_CONFIG.client_id);
    console.log('Configuración cargada:', CLIENT_CONFIG);
    
    // Variables globales
    let userLocation = null;
    let accessGranted = null;
    
    /**
     * Inicializar el sistema de control geográfico
     */
    function initGeoControl() {
        // Verificar si ya se ejecutó
        if (window.zipGeoInitialized) {
            return;
        }
        window.zipGeoInitialized = true;
        
        console.log('ZipGeo: Iniciando control geográfico...');
        
        // Obtener ubicación del usuario
        getUserLocation()
            .then(location => {
                userLocation = location;
                console.log('ZipGeo: Ubicación obtenida:', location);
                return checkAccess(location);
            })
            .then(granted => {
                accessGranted = granted;
                console.log('ZipGeo: Acceso evaluado:', granted ? 'PERMITIDO' : 'DENEGADO');
                
                if (!granted) {
                    // Ejecutar inmediatamente y también después de un delay
                    handleAccessDenied();
                    
                    // Re-ejecutar después de que el DOM esté completamente cargado
                    setTimeout(() => {
                        console.log('ZipGeo: Re-ejecutando bloqueo después de delay');
                        handleAccessDenied();
                    }, 500);
                    
                    // Tercer intento para páginas muy lentas
                    setTimeout(() => {
                        console.log('ZipGeo: Último intento de bloqueo');
                        handleAccessDenied();
                    }, 2000);
                } else {
                    console.log('ZipGeo: Acceso permitido - continuando navegación');
                    // Log de acceso exitoso
                    logAccess('granted', userLocation);
                    
                    // Configurar validación de emails SOLO cuando el acceso está permitido
                    console.log('ZipGeo: Configurando validación de emails (solo para países permitidos)');
                    setupEmailValidation();
                }
            })
            .catch(error => {
                console.error('ZipGeo Error:', error);
                // En caso de error, permitir acceso (fail open)
                accessGranted = true;
                logAccess('error', null, error.message);
            });
    }
    
    /**
     * Obtener ubicación del usuario
     */
    async function getUserLocation() {
        try {
            console.log('ZipGeo: Obteniendo ubicación del usuario...');
            
            // Usar múltiples servicios de geolocalización
            const services = [
                'https://ipapi.co/json/',
                'https://ip-api.com/json/',
                'https://ipinfo.io/json'
            ];
            
            for (const service of services) {
                try {
                    console.log('ZipGeo: Probando servicio:', service);
                    const response = await fetch(service);
                    const data = await response.json();
                    
                    // Normalizar respuesta según el servicio
                    let location = normalizeLocationData(data, service);
                    if (location && location.country) {
                        console.log('ZipGeo: Ubicación detectada desde', service, ':', location);
                        return location;
                    }
                } catch (e) {
                    console.warn('ZipGeo: Error con servicio', service, ':', e);
                    continue; // Probar siguiente servicio
                }
            }
            
            throw new Error('No se pudo obtener la ubicación desde ningún servicio');
            
        } catch (error) {
            throw new Error('Error obteniendo ubicación: ' + error.message);
        }
    }
    
    /**
     * Normalizar datos de ubicación según el servicio
     */
    function normalizeLocationData(data, service) {
        if (service.includes('ipapi.co')) {
            return {
                country: data.country_code,
                country_name: data.country_name,
                region: data.region,
                city: data.city,
                ip: data.ip,
                isp: data.org,
                is_vpn: data.threat?.is_anonymous || false,
                is_tor: data.threat?.is_tor || false,
                is_proxy: data.threat?.is_proxy || false
            };
        } else if (service.includes('ip-api.com')) {
            return {
                country: data.countryCode,
                country_name: data.country,
                region: data.regionName,
                city: data.city,
                ip: data.query,
                isp: data.isp,
                is_vpn: data.proxy || false,
                is_tor: false,
                is_proxy: data.proxy || false
            };
        } else if (service.includes('ipinfo.io')) {
            return {
                country: data.country,
                country_name: data.country,
                region: data.region,
                city: data.city,
                ip: data.ip,
                isp: data.org,
                is_vpn: false,
                is_tor: false,
                is_proxy: false
            };
        }
        
        return data;
    }
    
    /**
     * Verificar si el acceso está permitido
     */
    function checkAccess(location) {
        try {
            const country = location.country?.toUpperCase();
            
            console.log('ZipGeo: Evaluando acceso para país:', country);
            console.log('ZipGeo: Países permitidos:', CLIENT_CONFIG.countries_allowed);
            console.log('ZipGeo: Modo de control:', CLIENT_CONFIG.access_control_mode);
            
            if (!country) {
                console.warn('ZipGeo: No se pudo determinar el país');
                return false; // No se pudo determinar país
            }
            
            // Verificar VPN/Proxy/Tor si está configurado
            if (!CLIENT_CONFIG.allow_vpn && location.is_vpn) {
                console.log('ZipGeo: Acceso denegado - VPN no permitido');
                return false;
            }
            if (!CLIENT_CONFIG.allow_tor && location.is_tor) {
                console.log('ZipGeo: Acceso denegado - Tor no permitido');
                return false;
            }
            if (!CLIENT_CONFIG.allow_proxy && location.is_proxy) {
                console.log('ZipGeo: Acceso denegado - Proxy no permitido');
                return false;
            }
            
            // Verificar países según el modo de control
            if (CLIENT_CONFIG.access_control_mode === 'allowed') {
                // Modo lista blanca: solo países permitidos
                const allowed = CLIENT_CONFIG.countries_allowed.includes(country);
                console.log('ZipGeo: Modo lista blanca - País', country, allowed ? 'permitido' : 'denegado');
                return allowed;
            } else {
                // Modo lista negra: todos excepto denegados
                const denied = CLIENT_CONFIG.countries_denied.includes(country);
                console.log('ZipGeo: Modo lista negra - País', country, denied ? 'denegado' : 'permitido');
                return !denied;
            }
            
        } catch (error) {
            console.error('ZipGeo: Error verificando acceso:', error);
            return true; // Fail open
        }
    }
    
    /**
     * Manejar acceso denegado
     */
    function handleAccessDenied() {
        console.log('ZipGeo: Ejecutando acción de acceso denegado:', CLIENT_CONFIG.access_denied_action);
        
        // Log de acceso denegado (solo una vez)
        if (!window.zipGeoAccessLogged) {
            logAccess('denied', userLocation);
            window.zipGeoAccessLogged = true;
        }
        
        if (CLIENT_CONFIG.access_denied_action === 'redirect') {
            // Redireccionar a URL especificada
            if (CLIENT_CONFIG.access_denied_redirect_url) {
                console.log('ZipGeo: Redirigiendo a:', CLIENT_CONFIG.access_denied_redirect_url);
                window.location.href = CLIENT_CONFIG.access_denied_redirect_url;
                return;
            }
        } else if (CLIENT_CONFIG.access_denied_action === 'block_site') {
            // Bloquear sitio web completo (comportamiento tradicional)
            console.log('ZipGeo: Bloqueando sitio web completo');
            showAccessDeniedMessage();
            hidePageContent();
        } else if (CLIENT_CONFIG.access_denied_action === 'block_forms') {
            // Solo bloquear formularios, permitir navegación - SIN validación de emails
            console.log('ZipGeo: País BLOQUEADO - bloqueo directo de formularios (sin validación email)');
            blockFormsDirectly();
        } else {
            // Acción por defecto: bloquear formularios solamente - SIN validación de emails
            console.log('ZipGeo: País BLOQUEADO - bloqueo directo por defecto (sin validación email)');
            blockFormsDirectly();
        }
    }
    
    /**
     * Validar email usando múltiples métodos
     */
    async function validateEmailWithAPI(email) {
        try {
            console.log('ZipGeo: Validando email:', email);
            
            // 1. Validación básica de formato
            const basicFormatValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (!basicFormatValid) {
                console.log('ZipGeo: Email', email, 'INVÁLIDO - formato básico incorrecto');
                return false;
            }
            
            // 2. Verificar dominios comunes válidos
            const validDomains = [
                'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
                'icloud.com', 'aol.com', 'protonmail.com', 'tutanota.com',
                'rastroseguro.com', 'validatumail.cc', 'empresa.com'
            ];
            
            const domain = email.split('@')[1]?.toLowerCase();
            if (validDomains.includes(domain)) {
                console.log('ZipGeo: Email', email, 'VÁLIDO - dominio en lista de confianza');
                return true;
            }
            
            // 3. Verificar dominios desechables conocidos
            const disposableDomains = [
                '10minutemail.com', 'tempmail.org', 'guerrillamail.com',
                'mailinator.com', 'throwaway.email', 'temp-mail.org'
            ];
            
            if (disposableDomains.includes(domain)) {
                console.log('ZipGeo: Email', email, 'INVÁLIDO - dominio desechable');
                return false;
            }
            
            // 4. Verificación adicional con API externa (opcional)
            try {
                const response = await fetch('https://validatumail.cc/email_validator.php?email=' + encodeURIComponent(email));
                const data = await response.json();
                
                console.log('ZipGeo: Respuesta API validación:', data);
                
                if (data.error) {
                    console.warn('ZipGeo: Error API externa:', data.error);
                    // Si hay error en API, usar validación básica
                    console.log('ZipGeo: Email', email, 'VÁLIDO - fallback a validación básica');
                    return true;
                }
                
                // Si el API dice que es válido, aceptar
                if (data.status === 'valid') {
                    console.log('ZipGeo: Email', email, 'VÁLIDO - confirmado por API');
                    return true;
                }
                
                // Si el API dice inválido, rechazar
                if (data.status === 'invalid') {
                    console.log('ZipGeo: Email', email, 'INVÁLIDO - confirmado por API:', data.reason);
                    return false;
                }
                
                // Si el status es "unknown", verificar la razón
                if (data.status === 'unknown') {
                    // dns_error, timeout, etc. generalmente indican problemas graves
                    if (data.reason === 'dns_error' ||
                        data.reason === 'invalid_domain' ||
                        data.reason === 'rejected_email' ||
                        data.reason === 'timeout') {
                        console.log('ZipGeo: Email', email, 'INVÁLIDO - problema detectado por API:', data.reason);
                        return false;
                    }
                    
                    // Para otros casos "unknown", verificar si es dominio confiable
                    const trustedDomains = [
                        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
                        'icloud.com', 'aol.com', 'protonmail.com'
                    ];
                    
                    if (trustedDomains.includes(domain)) {
                        console.log('ZipGeo: Email', email, 'VÁLIDO - dominio confiable a pesar de status unknown');
                        return true;
                    } else {
                        console.log('ZipGeo: Email', email, 'INVÁLIDO - status unknown en dominio no confiable');
                        return false;
                    }
                }
                
            } catch (apiError) {
                console.warn('ZipGeo: Error API externa:', apiError);
                // Fallback a validación básica más estricta
            }
            
            // 5. Validación por defecto más estricta
            // Solo aceptar dominios muy conocidos si no hay respuesta de API
            const trustedDomains = [
                'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
                'icloud.com', 'aol.com', 'protonmail.com'
            ];
            
            if (trustedDomains.includes(domain)) {
                console.log('ZipGeo: Email', email, 'VÁLIDO - dominio de alta confianza');
                return true;
            }
            
            // Si no es un dominio de confianza y no tenemos respuesta de API, rechazar
            console.log('ZipGeo: Email', email, 'INVÁLIDO - dominio no verificado y API no disponible');
            return false;
            
        } catch (error) {
            console.error('ZipGeo: Error validando email:', error);
            return true; // En caso de error, permitir (fail open)
        }
    }
    
    /**
     * Configurar validación de emails en formularios
     */
    function setupEmailValidation() {
        console.log('ZipGeo: Configurando validación de emails en formularios');
        
        // Función para validar un formulario específico
        function addEmailValidationToForm(form) {
            if (form.dataset.zipgeoEmailValidation) return; // Ya configurado
            
            form.dataset.zipgeoEmailValidation = 'true';
            console.log('ZipGeo: Agregando validación de email al formulario:', form);
            
            // Buscar campos de email en el formulario
            const emailFields = form.querySelectorAll('input[type="email"], input[name*="email"], input[id*="email"]');
            
            if (emailFields.length === 0) {
                console.log('ZipGeo: No se encontraron campos de email en este formulario');
                return;
            }
            
            console.log('ZipGeo: Encontrados', emailFields.length, 'campos de email');
            
            // Buscar botones de submit
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])');
            console.log('ZipGeo: Encontrados', submitButtons.length, 'botones de envío');
            
            // Deshabilitar botones inicialmente
            function disableSubmitButtons() {
                submitButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                    console.log('ZipGeo: Botón deshabilitado hasta validación de email');
                });
            }
            
            // Habilitar botones cuando emails son válidos
            function enableSubmitButtons() {
                submitButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    console.log('ZipGeo: Botón habilitado - emails válidos');
                });
            }
            
            // Deshabilitar botones inicialmente
            disableSubmitButtons();
            
            // Validación en tiempo real para cada campo de email
            emailFields.forEach(emailField => {
                let validationTimeout;
                
                emailField.addEventListener('input', function() {
                    clearTimeout(validationTimeout);
                    
                    // Validar después de 500ms de no escribir
                    validationTimeout = setTimeout(async () => {
                        await validateSingleEmailField(emailField, submitButtons);
                    }, 500);
                });
                
                emailField.addEventListener('blur', function() {
                    clearTimeout(validationTimeout);
                    validateSingleEmailField(emailField, submitButtons);
                });
            });
            
            // Validación de un solo campo
            async function validateSingleEmailField(emailField, submitButtons) {
                const email = emailField.value.trim();
                
                if (!email) {
                    clearEmailError(emailField);
                    disableSubmitButtons();
                    return;
                }
                
                // Validación básica
                const basicValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                if (!basicValid) {
                    showEmailError(emailField, 'Formato de email inválido');
                    disableSubmitButtons();
                    return;
                }
                
                // Mostrar mensaje de validación
                showEmailValidating(emailField);
                
                // Validación con API
                const apiValid = await validateEmailWithAPI(email);
                
                if (apiValid) {
                    clearEmailError(emailField);
                    showEmailSuccess(emailField);
                    
                    // Verificar si todos los emails son válidos
                    const allValid = await checkAllEmailsValid(form);
                    if (allValid) {
                        enableSubmitButtons();
                    }
                } else {
                    showEmailError(emailField, 'Este email no es válido o no existe');
                    disableSubmitButtons();
                }
            }
            
            // Verificar si todos los emails en el formulario son válidos
            async function checkAllEmailsValid(form) {
                const emailFields = form.querySelectorAll('input[type="email"], input[name*="email"], input[id*="email"]');
                
                for (const field of emailFields) {
                    const email = field.value.trim();
                    if (!email) return false; // Email requerido
                    
                    const basicValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                    if (!basicValid) return false;
                    
                    // Verificar si ya está validado
                    if (!field.dataset.zipgeoEmailValid) return false;
                }
                
                return true;
            }
            
            // Interceptar envío del formulario (como fallback)
            form.addEventListener('submit', function(e) {
                const hasValidEmails = Array.from(emailFields).every(field =>
                    field.dataset.zipgeoEmailValid === 'true'
                );
                
                if (!hasValidEmails) {
                    e.preventDefault();
                    console.log('ZipGeo: Envío bloqueado - emails no válidos');
                    disableSubmitButtons();
                    return false;
                }
                
                console.log('ZipGeo: Envío permitido - todos los emails válidos');
                return true;
            });
        }
        
        // Configurar validación en formularios existentes
        function setupExistingForms() {
            const forms = document.querySelectorAll('form');
            console.log('ZipGeo: Configurando validación email en', forms.length, 'formularios');
            forms.forEach(addEmailValidationToForm);
        }
        
        // Ejecutar configuración inicial
        setupExistingForms();
        
        // Observer para formularios que se agreguen dinámicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Si el nodo agregado es un formulario
                        if (node.tagName === 'FORM') {
                            console.log('ZipGeo: Nuevo formulario detectado - agregando validación email');
                            addEmailValidationToForm(node);
                        }
                        
                        // Si el nodo contiene formularios
                        const subForms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        subForms.forEach(addEmailValidationToForm);
                    }
                });
            });
        });
        
        // Configurar observer
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Registrar observer
        window.zipGeoEmailObserver = observer;
        
        console.log('ZipGeo: Sistema de validación de emails configurado');
    }
    
    /**
     * Mostrar error de email
     */
    function showEmailError(emailField, message) {
        // Remover error previo
        clearEmailError(emailField);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'zipgeo-email-error';
        errorDiv.style.cssText =
            'color: #dc3545;' +
            'font-size: 12px;' +
            'margin-top: 5px;' +
            'padding: 5px;' +
            'background: #f8d7da;' +
            'border: 1px solid #f5c6cb;' +
            'border-radius: 3px;';
        errorDiv.textContent = '❌ ' + message;
        
        // Agregar después del campo de email
        emailField.parentNode.insertBefore(errorDiv, emailField.nextSibling);
        
        // Marcar campo como inválido
        emailField.style.borderColor = '#dc3545';
    }
    
    /**
     * Limpiar error de email
     */
    function clearEmailError(emailField) {
        // Usar la función más completa
        clearEmailMessages(emailField);
    }
    
    /**
     * Mostrar estado de validación en progreso
     */
    function showEmailValidating(emailField) {
        // Remover mensajes previos
        clearEmailMessages(emailField);
        
        const validatingDiv = document.createElement('div');
        validatingDiv.className = 'zipgeo-email-validating';
        validatingDiv.style.cssText =
            'color: #0066cc;' +
            'font-size: 12px;' +
            'margin-top: 5px;' +
            'padding: 5px;' +
            'background: #e3f2fd;' +
            'border: 1px solid #bbdefb;' +
            'border-radius: 3px;';
        validatingDiv.innerHTML = '🔄 Validando email...';
        
        // Agregar después del campo de email
        emailField.parentNode.insertBefore(validatingDiv, emailField.nextSibling);
        
        // Marcar campo como validando
        emailField.style.borderColor = '#0066cc';
        emailField.dataset.zipgeoEmailValid = 'false';
    }
    
    /**
     * Mostrar email válido
     */
    function showEmailSuccess(emailField) {
        // Remover mensajes previos
        clearEmailMessages(emailField);
        
        const successDiv = document.createElement('div');
        successDiv.className = 'zipgeo-email-success';
        successDiv.style.cssText =
            'color: #28a745;' +
            'font-size: 12px;' +
            'margin-top: 5px;' +
            'padding: 5px;' +
            'background: #d4edda;' +
            'border: 1px solid #c3e6cb;' +
            'border-radius: 3px;';
        successDiv.innerHTML = '✅ Email válido';
        
        // Agregar después del campo de email
        emailField.parentNode.insertBefore(successDiv, emailField.nextSibling);
        
        // Marcar campo como válido
        emailField.style.borderColor = '#28a745';
        emailField.dataset.zipgeoEmailValid = 'true';
    }
    
    /**
     * Limpiar todos los mensajes de email
     */
    function clearEmailMessages(emailField) {
        // Remover todos los mensajes de validación
        const messages = emailField.parentNode.querySelectorAll('.zipgeo-email-error, .zipgeo-email-validating, .zipgeo-email-success');
        messages.forEach(msg => msg.remove());
        
        // Restaurar borde normal
        emailField.style.borderColor = '';
        emailField.dataset.zipgeoEmailValid = 'false';
    }
    
    /**
     * Mostrar error general en formulario
     */
    function showFormError(form, message) {
        // Remover error previo
        const existingError = form.querySelector('.zipgeo-form-error');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'zipgeo-form-error';
        errorDiv.style.cssText =
            'color: #dc3545;' +
            'background: #f8d7da;' +
            'border: 1px solid #f5c6cb;' +
            'border-radius: 5px;' +
            'padding: 15px;' +
            'margin: 10px 0;' +
            'text-align: center;' +
            'font-weight: 600;' +
            'z-index: 10000;' +
            'position: relative;';
        errorDiv.innerHTML = '🚫 ' + message;
        
        // Agregar al inicio del formulario
        form.insertBefore(errorDiv, form.firstChild);
    }
    
    /**
     * Bloquear solo formularios con emails inválidos, permitir navegación
     */
    function blockFormsOnly() {
        console.log('ZipGeo: Implementando bloqueo inteligente de formularios (solo si emails inválidos)');
        
        // Función para agregar validación estricta a un formulario
        function addStrictEmailValidation(form) {
            if (form.dataset.zipgeoStrictValidation) return; // Ya configurado
            
            console.log('ZipGeo: Agregando validación estricta al formulario:', form);
            
            // Marcar como configurado
            form.dataset.zipgeoStrictValidation = 'true';
            
            // Agregar mensaje informativo (no bloqueo total)
            if (!form.querySelector('.zipgeo-form-notice')) {
                const noticeMessage = document.createElement('div');
                noticeMessage.className = 'zipgeo-form-notice';
                noticeMessage.innerHTML = '<div style="' +
                    'background: #e3f2fd;' +
                    'border: 1px solid #bbdefb;' +
                    'border-radius: 5px;' +
                    'padding: 12px;' +
                    'margin: 10px 0;' +
                    'color: #1565c0;' +
                    'font-size: 13px;' +
                    'text-align: center;' +
                    'z-index: 10000;' +
                    'position: relative;' +
                '">' +
                    '🌍 Control geográfico activo - se requiere validación estricta de emails' +
                    '<br><small>Ubicación: ' + (userLocation?.country_name || 'Detectando...') + '</small>' +
                '</div>';
                form.insertBefore(noticeMessage, form.firstChild);
            }
            
            // Interceptar envío del formulario
            form.addEventListener('submit', async function(e) {
                // Si ya está validado, permitir envío directo
                if (form.dataset.zipgeoStrictValidated === 'true') {
                    console.log('ZipGeo: Formulario ya validado estrictamente - permitiendo envío directo');
                    return true;
                }
                
                e.preventDefault();
                console.log('ZipGeo: Validación estricta - interceptando envío de formulario');
                
                // Buscar campos de email
                const emailFields = form.querySelectorAll('input[type="email"], input[name*="email"], input[id*="email"]');
                
                if (emailFields.length === 0) {
                    // No hay campos de email, bloquear completamente
                    console.log('ZipGeo: No hay campos de email - bloqueando formulario por ubicación geográfica');
                    showFormError(form, 'Este formulario no está disponible desde tu ubicación geográfica');
                    return;
                }
                
                // Validar todos los emails
                let allEmailsValid = true;
                let hasEmails = false;
                
                for (const emailField of emailFields) {
                    const email = emailField.value.trim();
                    
                    if (email) {
                        hasEmails = true;
                        console.log('ZipGeo: Validando email estrictamente:', email);
                        
                        // Validación básica
                        const basicValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                        if (!basicValid) {
                            showEmailError(emailField, 'Formato de email inválido');
                            allEmailsValid = false;
                            continue;
                        }
                        
                        // Validación con API
                        const apiValid = await validateEmailWithAPI(email);
                        if (!apiValid) {
                            showEmailError(emailField, 'Email no válido, no existe o es temporal - requerido desde tu ubicación');
                            allEmailsValid = false;
                            continue;
                        }
                        
                        // Email válido
                        clearEmailError(emailField);
                    }
                }
                
                if (!hasEmails) {
                    // No se proporcionaron emails, bloquear
                    console.log('ZipGeo: No se proporcionaron emails - bloqueando por ubicación geográfica');
                    showFormError(form, 'Se requiere un email válido para enviar este formulario desde tu ubicación');
                    return;
                }
                
                if (allEmailsValid) {
                    console.log('ZipGeo: Todos los emails válidos - permitiendo envío del formulario');
                    // Marcar formulario como validado y enviar
                    form.dataset.zipgeoStrictValidated = 'true';
                    form.submit();
                } else {
                    console.log('ZipGeo: Emails inválidos - bloqueando envío');
                }
            });
        }
        
        // Configurar validación estricta en formularios existentes
        function setupStrictValidation() {
            const forms = document.querySelectorAll('form');
            console.log('ZipGeo: Configurando validación estricta en', forms.length, 'formularios');
            forms.forEach(addStrictEmailValidation);
        }
        
        // Ejecutar configuración inicial
        setupStrictValidation();
        
        // Observer para formularios dinámicos
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Si es un formulario
                        if (node.tagName === 'FORM') {
                            console.log('ZipGeo: Nuevo formulario detectado - agregando validación estricta');
                            addStrictEmailValidation(node);
                        }
                        
                        // Si contiene formularios
                        const subForms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        subForms.forEach(addStrictEmailValidation);
                    }
                });
            });
        });
        
        // Configurar observer
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Registrar observer
        window.zipGeoStrictObserver = observer;
        
        console.log('ZipGeo: Sistema de validación estricta de formularios iniciado');
    }
    
    /**
     * Bloquear formularios directamente sin validación de emails (para países bloqueados)
     */
    function blockFormsDirectly() {
        console.log('ZipGeo: País BLOQUEADO - bloqueando formularios directamente sin validación de emails');
        
        // Función para bloquear un formulario específico
        function blockSingleForm(form) {
            if (form.dataset.zipgeoDirectBlocked) return; // Ya bloqueado
            
            form.dataset.zipgeoDirectBlocked = 'true';
            console.log('ZipGeo: Bloqueando formulario directamente:', form);
            
            // Agregar mensaje de bloqueo geográfico
            if (!form.querySelector('.zipgeo-geo-block-notice')) {
                const blockMessage = document.createElement('div');
                blockMessage.className = 'zipgeo-geo-block-notice';
                blockMessage.innerHTML = '<div style="' +
                    'background: #fff3cd;' +
                    'border: 2px solid #ffc107;' +
                    'border-radius: 8px;' +
                    'padding: 20px;' +
                    'margin: 15px 0;' +
                    'color: #856404;' +
                    'font-size: 14px;' +
                    'text-align: center;' +
                    'font-weight: 600;' +
                    'z-index: 10000;' +
                    'position: relative;' +
                '">' +
                    '🚫 <strong>Acceso Restringido</strong><br>' +
                    'Este formulario no está disponible desde tu ubicación geográfica.<br>' +
                    '<small>País detectado: ' + (userLocation?.country_name || 'Desconocido') + '</small>' +
                '</div>';
                form.insertBefore(blockMessage, form.firstChild);
            }
            
            // Deshabilitar todos los campos del formulario
            const inputs = form.querySelectorAll('input, textarea, select, button');
            inputs.forEach(input => {
                input.disabled = true;
                input.style.opacity = '0.5';
                input.style.cursor = 'not-allowed';
                input.title = 'No disponible desde tu ubicación geográfica';
            });
            
            // Interceptar envío del formulario - bloqueo total
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('ZipGeo: Envío bloqueado - país no permitido (bloqueo directo)');
                
                // Mostrar mensaje adicional si intenta enviar
                const existingAlert = form.querySelector('.zipgeo-submit-blocked');
                if (!existingAlert) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'zipgeo-submit-blocked';
                    alertDiv.style.cssText =
                        'background: #f8d7da;' +
                        'border: 2px solid #dc3545;' +
                        'border-radius: 8px;' +
                        'padding: 15px;' +
                        'margin: 15px 0;' +
                        'color: #721c24;' +
                        'font-weight: 600;' +
                        'text-align: center;' +
                        'animation: pulse 0.5s ease-in-out;';
                    alertDiv.innerHTML = '❌ <strong>Envío Bloqueado</strong><br>Este formulario no puede ser enviado desde tu ubicación.';
                    form.insertBefore(alertDiv, form.firstChild);
                    
                    // Remover el mensaje después de 5 segundos
                    setTimeout(() => alertDiv.remove(), 5000);
                }
                
                return false;
            }, true); // Use capture to ensure this runs first
        }
        
        // Bloquear todos los formularios existentes
        function blockExistingForms() {
            const forms = document.querySelectorAll('form');
            console.log('ZipGeo: Bloqueando directamente', forms.length, 'formularios (país bloqueado)');
            forms.forEach(blockSingleForm);
        }
        
        // Ejecutar bloqueo inicial
        blockExistingForms();
        
        // Observer para formularios que se agreguen dinámicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Si es un formulario
                        if (node.tagName === 'FORM') {
                            console.log('ZipGeo: Nuevo formulario detectado - bloqueando directamente');
                            blockSingleForm(node);
                        }
                        
                        // Si contiene formularios
                        const subForms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        subForms.forEach(blockSingleForm);
                    }
                });
            });
        });
        
        // Configurar observer
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Registrar observer
        window.zipGeoDirectBlockObserver = observer;
        
        console.log('ZipGeo: Sistema de bloqueo directo de formularios iniciado (sin validación email)');
    }
    
    /**
     * Mostrar mensaje de acceso denegado
     */
    function showAccessDeniedMessage() {
        const message = document.createElement('div');
        message.id = 'zipgeo-access-denied';
        message.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 999999;
                font-family: Arial, sans-serif;
            ">
                <div style="
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                ">
                    <h2 style="color: #d32f2f; margin-bottom: 20px;">
                        Acceso No Permitido
                    </h2>
                    <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                        Lo sentimos, el contenido de este sitio web no está disponible en tu ubicación geográfica.
                    </p>
                    <p style="color: #999; font-size: 14px;">
                        País detectado: ' + (userLocation?.country_name || 'Desconocido') + '
                    </p>
                </div>
            </div>
        `;
        
        document.body.appendChild(message);
    }
    
    /**
     * Ocultar contenido de la página
     */
    function hidePageContent() {
        // Ocultar el body pero mantener el mensaje
        document.body.style.overflow = 'hidden';
        
        // Ocultar todos los elementos del body excepto nuestro mensaje
        const children = Array.from(document.body.children);
        children.forEach(child => {
            if (child.id !== 'zipgeo-access-denied') {
                child.style.display = 'none';
            }
        });
    }
    
    /**
     * Registrar evento de acceso
     */
    async function logAccess(result, location, error = null) {
        try {
            const logData = {
                client_id: CLIENT_CONFIG.client_id,
                result: result,
                country: location?.country || null,
                country_name: location?.country_name || null,
                city: location?.city || null,
                ip: location?.ip || null,
                is_vpn: location?.is_vpn || false,
                is_tor: location?.is_tor || false,
                is_proxy: location?.is_proxy || false,
                user_agent: navigator.userAgent,
                url: window.location.href,
                referrer: document.referrer,
                error: error,
                timestamp: new Date().toISOString()
            };
            
            console.log('ZipGeo: Enviando log de acceso:', logData);
            
            // Enviar log al servidor (fire and forget)
            fetch(CLIENT_CONFIG.service_url + '/log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(logData)
            }).catch(e => console.warn('ZipGeo: Error enviando log:', e));
            
        } catch (e) {
            console.warn('ZipGeo: Error preparando log:', e);
        }
    }
    
    // API pública para el cliente
    window.ZipGeo = {
        isReady: function() {
            return accessGranted !== null;
        },
        isAccessGranted: function() {
            return accessGranted === true;
        },
        getUserLocation: function() {
            return userLocation;
        },
        getConfig: function() {
            return CLIENT_CONFIG;
        },
        validateEmail: async function(email) {
            return await validateEmailWithAPI(email);
        },
        enableEmailValidation: function() {
            console.log('ZipGeo: Activando validación de emails manualmente');
            setupEmailValidation();
        },
        disableEmailValidation: function() {
            console.log('ZipGeo: Desactivando validación de emails');
            if (window.zipGeoEmailObserver) {
                window.zipGeoEmailObserver.disconnect();
            }
            // Remover event listeners de formularios
            document.querySelectorAll('form[data-zipgeo-email-validation]').forEach(form => {
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
            });
        }
    };
    
    // Inicializar cuando el DOM esté listo - múltiples puntos de entrada
    function startInitialization() {
        console.log('ZipGeo: Iniciando proceso de inicialización');
        console.log('ZipGeo: Estado del documento:', document.readyState);
        
        // Inicializar inmediatamente
        initGeoControl();
        
        // También ejecutar cuando el DOM esté completamente listo (por si acaso)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                console.log('ZipGeo: DOMContentLoaded - re-inicializando');
                initGeoControl();
            });
        }
        
        // También ejecutar cuando la ventana esté completamente cargada
        if (document.readyState !== 'complete') {
            window.addEventListener('load', () => {
                console.log('ZipGeo: Window load complete - verificando bloqueos');
                // Si ya se determinó que el acceso está denegado, re-aplicar bloqueo
                if (accessGranted === false) {
                    setTimeout(() => {
                        console.log('ZipGeo: Re-aplicando bloqueo después de window load');
                        handleAccessDenied();
                    }, 100);
                }
            });
        }
    }
    
    // Ejecutar inicialización
    startInitialization();
    
})();
JAVASCRIPT;
    
} catch (Exception $e) {
    echo 'console.error("ZipGeo Error: " + ' . json_encode($e->getMessage()) . ');';
}
?>