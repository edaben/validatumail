/**
 * Sistema de Control de Acceso Basado en Geolocalización - Versión Directa2
 *
 * Esta versión hace la solicitud directamente a iplocate.io desde el cliente
 * para evitar problemas con el servidor PHP.
 * 
 * @charset UTF-8
 */

// Función autoejecutable para encapsular el código
(function() {
    // URL del servidor donde se encuentra la carpeta geo
    // IMPORTANTE: Esta URL debe coincidir con la ubicación real del script
    let SERVER_URL = 'https://validatumail.cc';
    
    // Función de ayuda para logs de depuración
    // Solo muestra mensajes si DEBUG_MODE es true
    function debugLog(...args) {
        if (typeof DEBUG_MODE !== 'undefined' && DEBUG_MODE === true) {
            console.log(...args);
        }
    }
    
    // Función para errores de depuración
    function debugError(...args) {
        if (typeof DEBUG_MODE !== 'undefined' && DEBUG_MODE === true) {
            console.error(...args);
        }
    }
    
    // Lista de dominios permitidos (se cargará desde la configuración del servidor)
    let DOMAINS_WHITELIST = [];
    
    // Verificar si el dominio actual está en la lista blanca
    function checkDomainWhitelist() {
        const currentDomain = window.location.hostname;
        
        debugLog('DEBUG - Dominio actual:', currentDomain);
        debugLog('DEBUG - Lista de dominios permitidos:', DOMAINS_WHITELIST);
        
        // Permitir archivos locales (file://) cuando el hostname está vacío
        if (!currentDomain || currentDomain === '') {
            debugLog('DEBUG - Archivo local detectado (file://), permitiendo acceso');
            return true;
        }
        
        // Si la lista está vacía, permitir localhost para desarrollo
        if (DOMAINS_WHITELIST.length === 0) {
            if (currentDomain === 'localhost') {
                debugLog('DEBUG - Lista de dominios vacía, permitiendo localhost para desarrollo');
                return true;
            }
            
            // Esperar a que se cargue la lista desde el servidor
            debugLog('DEBUG - Lista de dominios vacía, esperando carga desde servidor');
            return false;
        }
        
        // Comprobar si el dominio actual está en la lista de permitidos
        const isAllowed = DOMAINS_WHITELIST.some(domain => {
            // Verificar cada condición por separado para mejor depuración
            const exactMatch = currentDomain === domain;
            const subdomainMatch = currentDomain.endsWith('.' + domain) && !exactMatch;
            const localhostMatch = currentDomain === 'localhost' || currentDomain === '';
            
            // Combinar todas las condiciones
            const matches = exactMatch || subdomainMatch || localhostMatch;
            
            // Registrar información detallada sobre la coincidencia
            if (exactMatch) {
                debugLog(`DEBUG - Coincidencia exacta con: ${domain}`);
            } else if (subdomainMatch) {
                debugLog(`DEBUG - Coincidencia de subdominio con: ${domain}`);
            } else if (localhostMatch) {
                debugLog(`DEBUG - Coincidencia con localhost o archivo local`);
            }
            
            return matches;
        });
        
        debugLog('DEBUG - ¿Dominio permitido?', isAllowed);
        return isAllowed;
    }
    
    // Inicializar con una lista temporal que incluye dominios comunes para desarrollo
    // Esta lista será reemplazada por la configuración del servidor
    DOMAINS_WHITELIST = ['validatumail.cc', 'rastroseguro.com', 'localhost', ''];
    
    // Verificar si el dominio está permitido inicialmente
    debugLog('DEBUG - Iniciando verificación de dominio...');
    const isDomainAllowed = checkDomainWhitelist();
    
    if (!isDomainAllowed) {
        // Intentar cargar la configuración del servidor antes de bloquear el acceso
        debugLog('DEBUG - Dominio no en lista inicial, intentando cargar configuración del servidor...');
        
        // Hacer una solicitud síncrona para obtener la configuración
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `${SERVER_URL}/proxy.php?get_config=true`, false); // false = síncrono
        xhr.send();
        
        if (xhr.status === 200) {
            try {
                const config = JSON.parse(xhr.responseText);
                if (config.domains_whitelist) {
                    debugLog('DEBUG - Actualizando lista de dominios desde el servidor:', config.domains_whitelist);
                    DOMAINS_WHITELIST = config.domains_whitelist.split(',').map(d => d.trim());
                    
                    // Verificar nuevamente si el dominio está permitido
                    const isNowAllowed = checkDomainWhitelist();
                    if (isNowAllowed) {
                        debugLog('DEBUG - Dominio autorizado después de actualizar la lista, continuando ejecución...');
                        return; // Continuar con la ejecución
                    }
                }
            } catch (e) {
                debugError('DEBUG - Error al procesar la configuración:', e);
            }
        }
        
        console.error('Geo Access Control: Este dominio no está autorizado para usar este script.');
        // Detener completamente la ejecución del script
        throw new Error('Dominio no autorizado: ' + window.location.hostname);
    } else {
        debugLog('DEBUG - Dominio autorizado, continuando ejecución...');
    }
    
    
    // Variables de configuración que se cargarán desde el servidor (.env)
    // Estas variables serán sobrescritas con los valores del archivo .env
    let ALLOWED_COUNTRIES = []; // Se cargará desde .env
    let DENIED_COUNTRIES = []; // Se cargará desde .env
    let ACCESS_CONTROL_MODE = ''; // Se cargará desde .env
    
    // Configuración de privacidad - se cargará desde .env
    let ALLOW_VPN = '';
    let ALLOW_TOR = '';
    let ALLOW_PROXY = '';
    let ALLOW_HOSTING = '';
    
    // Configuración de comportamiento para acceso denegado
    let ACCESS_DENIED_ACTION = 'block_forms'; // 'redirect' o 'block_forms'
    let ACCESS_DENIED_REDIRECT_URL = 'access-denied.php';
    
    // Constantes para el almacenamiento en caché
    const CACHE_KEY_PREFIX = 'direct_geo_data_';
    const CACHE_KEYS_LIST_KEY = 'direct_geo_data_keys';
    const CACHE_DURATION = 24 * 60 * 60 * 1000; // 24 horas en milisegundos
    const IP_SEGMENTS_TO_USE = 3; // Usar solo los primeros 3 segmentos de la IP para el caché
    const CONFIG_VERSION_KEY = 'direct_geo_config_version'; // Clave para almacenar la versión de configuración
    
    // Estado de validación de email
    let emailValidationInProgress = false;
    let emailValidationCache = {}; // Caché en memoria para almacenar resultados de validación
    const EMAIL_VALIDATION_CACHE_KEY = 'email_validation_cache'; // Clave para localStorage
    const EMAIL_VALIDATION_CACHE_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 días en milisegundos
    
    // Cargar caché de validación de email desde localStorage
    function loadEmailValidationCache() {
        try {
            const cachedData = localStorage.getItem(EMAIL_VALIDATION_CACHE_KEY);
            if (cachedData) {
                const parsedData = JSON.parse(cachedData);
                
                // Verificar y limpiar entradas expiradas
                const now = new Date().getTime();
                let hasExpiredEntries = false;
                
                Object.keys(parsedData).forEach(email => {
                    if (parsedData[email].timestamp && now - parsedData[email].timestamp > EMAIL_VALIDATION_CACHE_DURATION) {
                        delete parsedData[email];
                        hasExpiredEntries = true;
                    }
                });
                
                // Si hay entradas expiradas, actualizar localStorage
                if (hasExpiredEntries) {
                    localStorage.setItem(EMAIL_VALIDATION_CACHE_KEY, JSON.stringify(parsedData));
                }
                
                // Actualizar caché en memoria
                emailValidationCache = parsedData;
                console.log('DEBUG - Caché de validación de email cargada desde localStorage:', Object.keys(emailValidationCache).length, 'entradas');
            }
        } catch (error) {
            console.error('DEBUG - Error al cargar caché de validación de email:', error);
            // Si hay error, limpiar la caché
            localStorage.removeItem(EMAIL_VALIDATION_CACHE_KEY);
            emailValidationCache = {};
        }
    }
    
    // Guardar caché de validación de email en localStorage
    function saveEmailValidationCache() {
        try {
            localStorage.setItem(EMAIL_VALIDATION_CACHE_KEY, JSON.stringify(emailValidationCache));
        } catch (error) {
            console.error('DEBUG - Error al guardar caché de validación de email:', error);
        }
    }
    
    // Variable para almacenar la versión de configuración actual
    let currentConfigVersion = localStorage.getItem(CONFIG_VERSION_KEY) || '0';
    
    // Función para manejar los datos de geolocalización
    function handleGeoData(data) {
        debugLog('DEBUG - handleGeoData: Procesando datos de geolocalización');
        
        // Verificar que los datos contienen la información necesaria
        if (!data || !data.country_code) {
            debugLog('DEBUG - handleGeoData: Datos incompletos, falta country_code');
            return data; // Devolver los datos sin procesar
        }
        
        debugLog('DEBUG - handleGeoData: País detectado:', data.country_code);
        debugLog('DEBUG - handleGeoData: Modo de control:', ACCESS_CONTROL_MODE);
        debugLog('DEBUG - handleGeoData: Países permitidos:', ALLOWED_COUNTRIES);
        debugLog('DEBUG - handleGeoData: Países denegados:', DENIED_COUNTRIES);
        
        // Asegurarse de que tenemos las listas de países más actualizadas
        debugLog(`DEBUG - handleGeoData: Asegurando listas de países actualizadas`);
        
        // Obtener las listas de países del localStorage (las más recientes)
        const storedAllowedCountries = localStorage.getItem('allowed_countries');
        const storedDeniedCountries = localStorage.getItem('denied_countries');
        
        if (storedAllowedCountries) {
            ALLOWED_COUNTRIES = storedAllowedCountries.split(',').map(c => c.trim());
            debugLog(`DEBUG - handleGeoData: Lista de países permitidos actualizada desde localStorage:`, ALLOWED_COUNTRIES);
        }
        
        if (storedDeniedCountries) {
            DENIED_COUNTRIES = storedDeniedCountries.split(',').map(c => c.trim());
            debugLog(`DEBUG - handleGeoData: Lista de países denegados actualizada desde localStorage:`, DENIED_COUNTRIES);
        }
        
        // Determinar si se permite el acceso según el modo y la lista de países
        let accessAllowed = false;
        
        // Normalizar el código de país para la comparación (eliminar espacios)
        const countryCode = data.country_code.trim();
        debugLog(`DEBUG - handleGeoData: Código de país normalizado: "${countryCode}"`);
        
        // Mostrar las listas de países para depuración
        debugLog(`DEBUG - handleGeoData: Lista de países permitidos:`, ALLOWED_COUNTRIES);
        debugLog(`DEBUG - handleGeoData: Lista de países denegados:`, DENIED_COUNTRIES);
        
        if (ACCESS_CONTROL_MODE === 'allowed') {
            // Verificar si el país está en la lista de permitidos usando includes
            debugLog(`DEBUG - handleGeoData: Verificando si ${countryCode} está en la lista de permitidos`);
            
            // Crear una versión optimizada de la lista para la comparación
            const trimmedAllowedCountries = ALLOWED_COUNTRIES.map(c => c.trim());
            
            // Usar includes para una comparación más eficiente
            accessAllowed = trimmedAllowedCountries.includes(countryCode);
            
            debugLog(`DEBUG - handleGeoData: Resultado final de la verificación: ${accessAllowed}`);
        } else {
            // Verificar si el país está en la lista de denegados usando includes
            debugLog(`DEBUG - handleGeoData: Verificando si ${countryCode} está en la lista de denegados`);
            
            // Crear una versión optimizada de la lista para la comparación
            const trimmedDeniedCountries = DENIED_COUNTRIES.map(c => c.trim());
            
            // Usar includes para una comparación más eficiente
            const isDenied = trimmedDeniedCountries.includes(countryCode);
            accessAllowed = !isDenied;
            
            debugLog(`DEBUG - handleGeoData: Resultado final de la verificación: ${accessAllowed}`);
        }
        
        // Verificar configuración de privacidad (VPN, Tor, Proxy, Hosting)
        if (accessAllowed && data.privacy) {
            // Verificar VPN
            if (data.privacy.is_vpn && ALLOW_VPN === 'no') {
                accessAllowed = false;
                data.access_denied_reason = 'VPN detectado y no permitido';
                
            }
            
            // Verificar Tor
            else if (data.privacy.is_tor && ALLOW_TOR === 'no') {
                accessAllowed = false;
                data.access_denied_reason = 'Tor detectado y no permitido';
                
            }
            
            // Verificar Proxy
            else if (data.privacy.is_proxy && ALLOW_PROXY === 'no') {
                accessAllowed = false;
                data.access_denied_reason = 'Proxy detectado y no permitido';
                
            }
            
            // Verificar Hosting
            else if (data.privacy.is_hosting && ALLOW_HOSTING === 'no') {
                accessAllowed = false;
                data.access_denied_reason = 'Hosting detectado y no permitido';
                
            }
        }
        
        // Añadir información de acceso a los datos
        data.access_allowed = accessAllowed;
        debugLog('DEBUG - handleGeoData: Decisión final de acceso:', accessAllowed);
        
        // Establecer la razón de la decisión de acceso
        if (data.access_denied_reason) {
            // Si ya se estableció una razón específica (VPN, Tor, etc.)
            data.access_decision_reason = data.access_denied_reason;
        } else {
            // Razón basada en el país
            data.access_decision_reason = ACCESS_CONTROL_MODE === 'allowed'
                ? `País ${accessAllowed ? "permitido" : "no permitido"} en la lista de permitidos`
                : `País ${accessAllowed ? "no" : ""} en la lista de denegados`;
        }
        debugLog('DEBUG - handleGeoData: Razón de la decisión:', data.access_decision_reason);
        
        // Crear una copia de los datos para guardar en caché
        // Incluir la decisión de acceso para acelerar futuras verificaciones
        const cacheData = { ...data };
        
        // Añadir información adicional para acelerar la redirección
        cacheData.access_allowed = accessAllowed;
        cacheData.access_decision_reason = data.access_decision_reason;
        if (data.access_denied_reason) {
            cacheData.access_denied_reason = data.access_denied_reason;
        }
        
        // Guardar los datos completos en caché, incluyendo la decisión de acceso
        saveToCache(cacheData);
        
        // Manejar formularios (independientemente de si el acceso está permitido o no)
        handleForms(data);
        
        // Verificar si se permite el acceso
        if (!accessAllowed) {
            debugLog('DEBUG - handleGeoData: Acceso denegado, verificando acción a tomar');
            debugLog('DEBUG - handleGeoData: ACCESS_DENIED_ACTION =', ACCESS_DENIED_ACTION);
            
            if (ACCESS_DENIED_ACTION === 'redirect') {
                debugLog('DEBUG - handleGeoData: Preparando redirección a página de acceso denegado');
                
                // Asegurarse de que data.country existe antes de usarlo
                const countryName = data.country || data.country_code || 'Desconocido';
                // Construir la URL con el parámetro country
                let redirectUrl = ACCESS_DENIED_REDIRECT_URL;
                // Añadir el parámetro country solo si la URL no es externa
                if (!redirectUrl.startsWith('http')) {
                    redirectUrl += (redirectUrl.includes('?') ? '&' : '?') + 'country=' + encodeURIComponent(countryName);
                }
                
                debugLog('DEBUG - handleGeoData: Redirigiendo a:', redirectUrl);
                // Redirigir a la página de acceso denegado
                window.location.href = redirectUrl;
                return;
            } else {
                
                // No redirigimos, nos quedamos en la página con los formularios deshabilitados
            }
        }
        
        // Devolver los datos para uso externo
        return data;
    }
    
    // Función para validar un email usando la API
    function validateEmail(email, emailField, submitButton, callback) {
        console.log('DEBUG - validateEmail: Iniciando validación para', email);
        debugLog('DEBUG - validateEmail: Iniciando validación para', email);
        
        // Verificar si el email ya ha sido validado
        if (emailValidationCache[email] !== undefined) {
            console.log('DEBUG - validateEmail: Usando resultado en caché para', email);
            
            // Verificar si la entrada en caché ha expirado
            const now = new Date().getTime();
            if (emailValidationCache[email].timestamp && now - emailValidationCache[email].timestamp > EMAIL_VALIDATION_CACHE_DURATION) {
                console.log('DEBUG - validateEmail: Entrada en caché expirada, validando de nuevo');
                delete emailValidationCache[email];
                saveEmailValidationCache();
            } else {
                // Actualizar UI según el resultado en caché
                updateEmailValidationUI(emailField, submitButton, emailValidationCache[email].isValid,
                                      emailValidationCache[email].message);
                
                // Llamar al callback si existe
                if (callback) callback(emailValidationCache[email].isValid, emailValidationCache[email].message);
                
                return;
            }
        }
        
        // Mostrar indicador de carga
        let loader = emailField.parentNode.querySelector('.email-validation-loader');
        if (!loader) {
            console.log('DEBUG - validateEmail: Creando loader');
            loader = document.createElement('span');
            loader.className = 'email-validation-loader';
            loader.textContent = 'validando email...';
            loader.style.display = 'none';
            loader.style.marginLeft = '10px';
            loader.style.fontSize = '14px';
            loader.style.color = '#7f8c8d';
            
            // Insertar después del campo de email
            if (emailField.nextSibling) {
                emailField.parentNode.insertBefore(loader, emailField.nextSibling);
            } else {
                emailField.parentNode.appendChild(loader);
            }
        }
        
        // Verificar si ya existe un mensaje de error
        let errorMessage = emailField.parentNode.querySelector('.email-validation-error');
        if (!errorMessage) {
            console.log('DEBUG - validateEmail: Creando mensaje de error');
            errorMessage = document.createElement('div');
            errorMessage.className = 'email-validation-error';
            errorMessage.style.color = '#e74c3c';
            errorMessage.style.fontSize = '14px';
            errorMessage.style.marginTop = '5px';
            errorMessage.style.display = 'none';
            
            // Insertar después del loader
            if (loader.nextSibling) {
                emailField.parentNode.insertBefore(errorMessage, loader.nextSibling);
            } else {
                emailField.parentNode.appendChild(errorMessage);
            }
        }
        
        // Verificar si ya existe el icono de validación
        let validationIcon = emailField.parentNode.querySelector('.email-validation-icon');
        if (!validationIcon) {
            console.log('DEBUG - validateEmail: Creando icono de validación');
            validationIcon = document.createElement('span');
            validationIcon.className = 'email-validation-icon';
            
            // Posicionar el icono dentro del input
            validationIcon.style.position = 'absolute';
            validationIcon.style.right = '10px';
            validationIcon.style.top = '50%';
            validationIcon.style.transform = 'translateY(-50%)';
            validationIcon.style.display = 'none';
            validationIcon.style.fontSize = '16px';
            validationIcon.style.width = '20px';
            validationIcon.style.height = '20px';
            validationIcon.style.borderRadius = '50%';
            validationIcon.style.textAlign = 'center';
            validationIcon.style.lineHeight = '20px';
            validationIcon.style.color = 'white';
            validationIcon.style.fontWeight = 'bold';
            validationIcon.style.zIndex = '100'; // Asegurar que esté por encima de otros elementos
            
            // Asegurarse de que el contenedor del campo tenga posición relativa
            emailField.parentNode.style.position = 'relative';
            
            // Ajustar el padding del campo de email para dejar espacio al icono
            emailField.style.paddingRight = '35px';
            
            // Insertar el icono dentro del contenedor del campo
            emailField.parentNode.appendChild(validationIcon);
            
            // Forzar el posicionamiento correcto
            setTimeout(function() {
                validationIcon.style.position = 'absolute';
                validationIcon.style.right = '10px';
                validationIcon.style.top = '50%';
                validationIcon.style.transform = 'translateY(-50%)';
            }, 100);
        }
        
        // Mostrar loader y ocultar mensaje de error
        console.log('DEBUG - validateEmail: Mostrando loader');
        loader.style.display = 'inline';
        errorMessage.style.display = 'none';
        
        // Marcar que la validación está en progreso
        emailValidationInProgress = true;
        
        console.log('DEBUG - validateEmail: Enviando petición a', `${SERVER_URL}/email_validator.php?email=${encodeURIComponent(email)}`);
        
        // Realizar la petición al servidor
        fetch(`${SERVER_URL}/email_validator.php?email=${encodeURIComponent(email)}`)
            .then(response => {
                console.log('DEBUG - validateEmail: Respuesta recibida', response);
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('DEBUG - validateEmail: Datos recibidos', data);
                debugLog('DEBUG - validateEmail: Respuesta recibida', data);
                
                // Ocultar loader
                loader.style.display = 'none';
                
                // Marcar que la validación ha terminado
                emailValidationInProgress = false;
                
                let isValid = false;
                let message = '';
                
                if (data.error) {
                    // Error en la validación
                    console.log('DEBUG - validateEmail: Error en la validación', data.error);
                    message = 'Error al validar el email. Por favor, inténtelo de nuevo.';
                    isValid = false;
                } else if (data.status === 'valid') {
                    // Email válido
                    console.log('DEBUG - validateEmail: Email válido');
                    isValid = true;
                } else {
                    // Email inválido
                    console.log('DEBUG - validateEmail: Email inválido');
                    message = 'El email es inválido o está mal escrito. Por favor, verifíquelo.';
                    isValid = false;
                }
                
                // Guardar el resultado en caché con timestamp
                emailValidationCache[email] = {
                    isValid: isValid,
                    message: message,
                    timestamp: new Date().getTime()
                };
                
                // Guardar en localStorage
                saveEmailValidationCache();
                
                // Actualizar la UI
                updateEmailValidationUI(emailField, submitButton, isValid, message);
                
                // Llamar al callback si existe
                if (callback) callback(isValid, message);
            })
            .catch(error => {
                console.log('DEBUG - validateEmail: Error en la petición', error);
                debugLog('DEBUG - validateEmail: Error', error);
                
                // Ocultar loader
                loader.style.display = 'none';
                
                // Marcar que la validación ha terminado
                emailValidationInProgress = false;
                
                const message = 'Error al validar el email. Por favor, inténtelo de nuevo.';
                
                // Guardar el resultado en caché
                emailValidationCache[email] = {
                    isValid: false,
                    message: message
                };
                
                // Actualizar la UI
                updateEmailValidationUI(emailField, submitButton, false, message);
                
                if (callback) callback(false, error.message);
            });
    }
    
    // Función para actualizar la UI según el resultado de la validación
    function updateEmailValidationUI(emailField, submitButton, isValid, message) {
        // Buscar elementos de UI
        const errorMessage = emailField.parentNode.querySelector('.email-validation-error');
        const validationIcon = emailField.parentNode.querySelector('.email-validation-icon');
        
        // Obtener la posición y dimensiones del campo de email para posicionar el icono correctamente
        const emailFieldRect = emailField.getBoundingClientRect();
        const parentRect = emailField.parentNode.getBoundingClientRect();
        
        // Calcular la posición relativa del campo dentro de su contenedor
        const relativeTop = emailFieldRect.top - parentRect.top;
        const iconTop = relativeTop + (emailFieldRect.height / 2);
        
        if (isValid) {
            // Email válido
            if (errorMessage) errorMessage.style.display = 'none';
            
            // Mostrar icono de validación correcto (círculo verde con ✓ blanco)
            if (validationIcon) {
                validationIcon.innerHTML = '✓';
                validationIcon.style.backgroundColor = '#2ecc71'; // Verde
                validationIcon.style.color = 'white';
                validationIcon.style.display = 'block';
                
                // Posicionar el icono en el centro vertical del campo de entrada
                validationIcon.style.position = 'absolute';
                validationIcon.style.right = '10px';
                validationIcon.style.top = iconTop + 'px';
                validationIcon.style.transform = 'translateY(-50%)';
            }
            
            // Habilitar botón
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
                submitButton.style.cursor = 'pointer';
            }
        } else {
            // Email inválido o error
            if (errorMessage) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
            }
            
            // Mostrar icono de validación incorrecto (círculo rojo con X blanca)
            if (validationIcon) {
                validationIcon.innerHTML = '✕';
                validationIcon.style.backgroundColor = '#e74c3c'; // Rojo
                validationIcon.style.color = 'white';
                validationIcon.style.display = 'block';
                
                // Posicionar el icono en el centro vertical del campo de entrada
                validationIcon.style.position = 'absolute';
                validationIcon.style.right = '10px';
                validationIcon.style.top = iconTop + 'px';
                validationIcon.style.transform = 'translateY(-50%)';
            }
            
            // Deshabilitar botón
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.style.opacity = '0.5';
                submitButton.style.cursor = 'not-allowed';
            }
        }
    }
    
    // Función para manejar formularios
    function handleForms(data) {
        console.log('DEBUG - handleForms: Iniciando manejo de formularios');
        debugLog('DEBUG - handleForms: Iniciando manejo de formularios');
        
        // Evitar múltiples ejecuciones de la lógica de geolocalización
        if (window.formsProcessed) {
            console.log('DEBUG - handleForms: Formularios ya procesados para geolocalización');
            debugLog('DEBUG - handleForms: Formularios ya procesados para geolocalización');
            
            // Aunque los formularios ya estén procesados para geolocalización,
            // aún necesitamos configurar la validación de email
            setupEmailValidation();
            
            return;
        }
        
        // Solución directa: deshabilitar el formulario específico por ID
        const testForm = document.getElementById('testForm');
        if (testForm) {
            console.log('DEBUG - handleForms: Formulario testForm encontrado');
            
            if (!data.access_allowed) {
                console.log('DEBUG - handleForms: Acceso no permitido, deshabilitando formulario');
                
                // Eliminar mensajes existentes para evitar duplicados
                const existingMessages = document.querySelectorAll('.access-denied-message');
                existingMessages.forEach(msg => msg.remove());
                
                // Deshabilitar todos los inputs
                const inputs = testForm.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    console.log('DEBUG - handleForms: Deshabilitando input', input);
                    input.disabled = true;
                });
                
                // Deshabilitar el botón
                const button = testForm.querySelector('button');
                if (button) {
                    console.log('DEBUG - handleForms: Deshabilitando botón', button);
                    button.disabled = true;
                }
                
                // Añadir mensaje (solo uno)
                const message = document.createElement('div');
                message.className = 'access-denied-message'; // Añadir clase para identificarlo
                message.style.backgroundColor = 'red';
                message.style.color = 'white';
                message.style.padding = '10px';
                message.style.marginTop = '10px';
                message.style.borderRadius = '4px';
                message.textContent = 'País / VPN no permitido';
                
                // Añadir mensaje después del botón
                if (button && button.parentNode) {
                    button.parentNode.insertBefore(message, button.nextSibling);
                } else {
                    // Si no hay botón, añadir al final del formulario
                    testForm.appendChild(message);
                }
            }
        } else {
            console.log('DEBUG - handleForms: Formulario testForm no encontrado, buscando cualquier formulario');
            
            // Intento alternativo: buscar cualquier formulario
            const forms = document.querySelectorAll('form');
            console.log('DEBUG - handleForms: Encontrados', forms.length, 'formularios');
            
            if (forms.length > 0 && !data.access_allowed) {
                // Procesar solo el primer formulario encontrado
                const form = forms[0];
                console.log('DEBUG - handleForms: Procesando primer formulario encontrado');
                
                // Eliminar mensajes existentes para evitar duplicados
                const existingMessages = document.querySelectorAll('.access-denied-message');
                existingMessages.forEach(msg => msg.remove());
                
                // Deshabilitar todos los inputs
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.disabled = true;
                });
                
                // Deshabilitar el botón
                const button = form.querySelector('button');
                if (button) {
                    button.disabled = true;
                }
                
                // Añadir mensaje (solo uno)
                const message = document.createElement('div');
                message.className = 'access-denied-message'; // Añadir clase para identificarlo
                message.style.backgroundColor = 'red';
                message.style.color = 'white';
                message.style.padding = '10px';
                message.style.marginTop = '10px';
                message.style.borderRadius = '4px';
                message.textContent = 'País / VPN no permitido';
                
                // Añadir mensaje después del botón
                if (button && button.parentNode) {
                    button.parentNode.insertBefore(message, button.nextSibling);
                } else {
                    // Si no hay botón, añadir al final del formulario
                    form.appendChild(message);
                }
            }
        }
        
        // Marcar como procesado para evitar múltiples ejecuciones de la lógica de geolocalización
        window.formsProcessed = true;
        
        // Configurar la validación de email
        setupEmailValidation();
    }
    
    // Función separada para configurar la validación de email
    function setupEmailValidation() {
        console.log('DEBUG - setupEmailValidation: Configurando validación de email');
        
        // Verificar si ya se ha configurado la validación de email
        if (window.emailValidationSetup) {
            console.log('DEBUG - setupEmailValidation: Validación de email ya configurada');
            return;
        }
        
        // Procesar todos los formularios para la validación de email
        const forms = document.querySelectorAll('form');
        console.log('DEBUG - setupEmailValidation: Encontrados', forms.length, 'formularios');
        
        forms.forEach((form, formIndex) => {
            // Buscar campos de email en el formulario
            const emailFields = form.querySelectorAll('input[type="email"]');
            console.log(`DEBUG - setupEmailValidation: Encontrado ${emailFields.length} campo(s) de email en formulario ${formIndex}`);
            
            if (emailFields.length > 0) {
                // Buscar el botón de envío
                const submitButton = form.querySelector('button[type="submit"], input[type="submit"], button');
                console.log('DEBUG - setupEmailValidation: Botón de envío encontrado:', submitButton);
                
                if (submitButton) {
                    // Deshabilitar el botón de envío por defecto hasta que el email sea validado
                    console.log('DEBUG - setupEmailValidation: Deshabilitando botón de envío por defecto');
                    submitButton.disabled = true;
                    
                    // Añadir estilo visual para que se note que está deshabilitado
                    submitButton.style.opacity = '0.5';
                    submitButton.style.cursor = 'not-allowed';
                    
                    emailFields.forEach((emailField, fieldIndex) => {
                        console.log(`DEBUG - setupEmailValidation: Configurando campo de email ${fieldIndex}`);
                        
                        // Función para validar el email cuando el usuario sale del campo
                        function validateEmailOnExit() {
                            console.log('DEBUG - Validando email al salir del campo');
                            const email = emailField.value.trim();
                            if (email) {
                                console.log('DEBUG - Validando email:', email);
                                // Verificar si ya tenemos el resultado en caché
                                if (emailValidationCache[email] !== undefined) {
                                    console.log('DEBUG - Usando resultado en caché para:', email);
                                    // Asegurarse de que el icono de validación exista
                                    let validationIcon = emailField.parentNode.querySelector('.email-validation-icon');
                                    if (!validationIcon) {
                                        console.log('DEBUG - Creando icono de validación para resultado en caché (blur)');
                                        validationIcon = document.createElement('span');
                                        validationIcon.className = 'email-validation-icon';
                                        
                                        // Posicionar el icono dentro del input
                                        validationIcon.style.position = 'absolute';
                                        validationIcon.style.right = '10px';
                                        validationIcon.style.top = emailValidationCache[email].isValid ? '75%' : '50%';
                                        validationIcon.style.transform = 'translateY(-50%)';
                                        validationIcon.style.display = 'none';
                                        validationIcon.style.fontSize = '16px';
                                        validationIcon.style.width = '20px';
                                        validationIcon.style.height = '20px';
                                        validationIcon.style.borderRadius = '50%';
                                        validationIcon.style.textAlign = 'center';
                                        validationIcon.style.lineHeight = '20px';
                                        validationIcon.style.color = 'white';
                                        validationIcon.style.fontWeight = 'bold';
                                        validationIcon.style.zIndex = '100';
                                        
                                        // Asegurarse de que el contenedor del campo tenga posición relativa
                                        emailField.parentNode.style.position = 'relative';
                                        
                                        // Ajustar el padding del campo de email para dejar espacio al icono
                                        emailField.style.paddingRight = '35px';
                                        
                                        // Insertar el icono dentro del contenedor del campo
                                        emailField.parentNode.appendChild(validationIcon);
                                    }
                                    
                                    // Usar el resultado en caché
                                    updateEmailValidationUI(emailField, submitButton,
                                        emailValidationCache[email].isValid,
                                        emailValidationCache[email].message);
                                } else {
                                    // Si no está en caché, validar
                                    validateEmail(email, emailField, submitButton);
                                }
                            } else {
                                console.log('DEBUG - Campo de email vacío, no se valida');
                            }
                        }
                        
                        // Añadir múltiples eventos para asegurar que se valide cuando el usuario sale del campo
                        
                        // 1. Evento blur - cuando el campo pierde el foco
                        emailField.addEventListener('blur', function() {
                            console.log('DEBUG - Evento blur detectado en campo de email');
                            validateEmailOnExit();
                        });
                        
                        // 2. Evento change - cuando el valor cambia y pierde el foco
                        emailField.addEventListener('change', function() {
                            console.log('DEBUG - Evento change detectado en campo de email');
                            validateEmailOnExit();
                        });
                        
                        // 3. Evento keyup para Enter - validar cuando presiona Enter
                        emailField.addEventListener('keyup', function(e) {
                            if (e.key === 'Enter') {
                                console.log('DEBUG - Tecla Enter detectada en campo de email');
                                validateEmailOnExit();
                            }
                        });
                        
                        // 4. Añadir evento click al documento para validar cuando se hace clic fuera del campo
                        document.addEventListener('click', function(e) {
                            // Solo validar si el campo tiene contenido y el clic no fue en el campo
                            if (emailField.value.trim() && e.target !== emailField && !emailField.contains(e.target)) {
                                console.log('DEBUG - Clic fuera del campo de email detectado');
                                validateEmailOnExit();
                            }
                        });
                        
                        // No validamos en el evento mouseenter para evitar validaciones repetidas
                        // Solo actualizamos la UI si ya tenemos un resultado en caché
                        submitButton.addEventListener('mouseenter', function() {
                            console.log('DEBUG - Mouse sobre botón de envío');
                            const email = emailField.value.trim();
                            
                            if (email && emailValidationCache[email] !== undefined) {
                                console.log('DEBUG - Email ya validado, usando resultado en caché');
                                // Usar el resultado en caché
                                updateEmailValidationUI(emailField, submitButton,
                                    emailValidationCache[email].isValid,
                                    emailValidationCache[email].message);
                            }
                        });
                    });
                }
            }
        });
        
        // Marcar que la validación de email ya ha sido configurada
        window.emailValidationSetup = true;
        console.log('DEBUG - setupEmailValidation: Validación de email configurada correctamente');
    }
    
    // Función para normalizar una IP para el caché (usar solo los primeros segmentos)
    function normalizeIpForCache(ip) {
        if (!ip) return '';
        const segments = ip.split('.');
        return segments.slice(0, IP_SEGMENTS_TO_USE).join('.');
    }
    
    // Función para guardar datos en caché para una IP específica
    function saveToCache(data) {
        if (!data || !data.ip) {
            debugLog('DEBUG - saveToCache: Datos inválidos, no se puede guardar en caché');
            return;
        }
        
        try {
            // Crear clave única usando los primeros segmentos de la IP
            const normalizedIp = normalizeIpForCache(data.ip);
            const ipCacheKey = CACHE_KEY_PREFIX + normalizedIp;
            
            debugLog(`DEBUG - saveToCache: Guardando datos para IP ${normalizedIp}`);
            
            // Añadir marca de tiempo para la expiración de la caché
            data.timestamp = new Date().getTime();
            
            // Añadir la versión de configuración actual
            data.config_version = currentConfigVersion;
            
            // Guardar datos para esta IP
            localStorage.setItem(ipCacheKey, JSON.stringify(data));
            
            // Actualizar lista de claves de caché
            let cacheKeys = [];
            const storedKeys = localStorage.getItem(CACHE_KEYS_LIST_KEY);
            
            if (storedKeys) {
                try {
                    cacheKeys = JSON.parse(storedKeys);
                } catch (e) {
                    
                }
            }
            
            // Añadir esta clave si no existe ya
            if (!cacheKeys.includes(ipCacheKey)) {
                cacheKeys.push(ipCacheKey);
                localStorage.setItem(CACHE_KEYS_LIST_KEY, JSON.stringify(cacheKeys));
            }
            
            
        } catch (e) {
            
        }
    }
    
    // Función para limpiar completamente la caché
    function clearCache() {
        debugLog('DEBUG - clearCache: Limpiando caché completamente...');
        
        // Método 1: Eliminar claves específicas de caché
        const storedKeys = localStorage.getItem(CACHE_KEYS_LIST_KEY);
        if (storedKeys) {
            try {
                const cacheKeys = JSON.parse(storedKeys);
                // Eliminar cada clave de caché
                cacheKeys.forEach(key => {
                    debugLog(`DEBUG - clearCache: Eliminando clave de caché: ${key}`);
                    localStorage.removeItem(key);
                });
            } catch (e) {
                debugError('DEBUG - clearCache: Error al limpiar caché específica:', e);
            }
        }
        
        // Método 2: Eliminar todas las claves que empiezan con nuestro prefijo
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('direct_geo_')) {
                keysToRemove.push(key);
            }
        }
        
        // Eliminar las claves encontradas
        keysToRemove.forEach(key => {
            debugLog(`DEBUG - clearCache: Eliminando clave adicional: ${key}`);
            localStorage.removeItem(key);
        });
        
        // Eliminar la lista de claves
        localStorage.removeItem(CACHE_KEYS_LIST_KEY);
        
        debugLog('DEBUG - clearCache: Caché limpiado completamente');
    }
    
    // Función para obtener datos en caché para una IP específica
    function getFromCache(ip) {
        return new Promise((resolve, reject) => {
            if (!ip) {
                debugLog('DEBUG - getFromCache: IP no proporcionada');
                resolve(null);
                return;
            }
            
            // Usar los primeros segmentos de la IP para buscar en caché
            const normalizedIp = normalizeIpForCache(ip);
            const ipCacheKey = CACHE_KEY_PREFIX + normalizedIp;
            
            debugLog(`DEBUG - getFromCache: Buscando en caché para IP ${normalizedIp}`);
            const cachedData = localStorage.getItem(ipCacheKey);
            
            if (!cachedData) {
                debugLog('DEBUG - getFromCache: No hay datos en caché');
                resolve(null);
                return;
            }
            
            try {
                const data = JSON.parse(cachedData);
                const cacheTime = data.timestamp;
                const now = new Date().getTime();
                
                // Verificar si los datos están expirados
                if (now - cacheTime >= CACHE_DURATION) {
                    debugLog('DEBUG - getFromCache: Datos expirados');
                    resolve(null);
                    return;
                }
                
                // Verificar si la versión de configuración ha cambiado desde que se almacenaron los datos
                if (!data.config_version) {
                    debugLog('DEBUG - getFromCache: No hay versión de configuración en caché, ignorando');
                    resolve(null);
                    return;
                }
                
                // Comparar las versiones como strings para evitar problemas de tipo
                const cachedVersion = String(data.config_version);
                const currentVersion = String(currentConfigVersion);
                
                if (cachedVersion !== currentVersion) {
                    debugLog('DEBUG - getFromCache: Versión de configuración cambiada, ignorando caché');
                    debugLog(`DEBUG - Versión en caché: ${cachedVersion}, Versión actual: ${currentVersion}`);
                    resolve(null);
                    return;
                }
                
                debugLog('DEBUG - getFromCache: Versión de configuración coincide, usando caché');
                
                debugLog('DEBUG - getFromCache: Datos encontrados en caché', {
                    ip: data.ip,
                    country: data.country,
                    country_code: data.country_code,
                    cache_age_minutes: Math.round((now - cacheTime) / 60000)
                });
                
                
                
                // Recalcular la decisión de acceso con la configuración actual
                // Esto asegura que se use la configuración más reciente del archivo .env
                
                
                // Determinar si se permite el acceso según el modo y la lista de países
                let accessAllowed = false;
                if (ACCESS_CONTROL_MODE === 'allowed') {
                    accessAllowed = ALLOWED_COUNTRIES.includes(data.country_code);
                } else {
                    accessAllowed = !DENIED_COUNTRIES.includes(data.country_code);
                }
                
                // Verificar configuración de privacidad (VPN, Tor, Proxy, Hosting)
                let accessDeniedReason = null;
                if (accessAllowed && data.privacy) {
                    // Verificar VPN
                    if (data.privacy.is_vpn && ALLOW_VPN === 'no') {
                        accessAllowed = false;
                        accessDeniedReason = 'VPN detectado y no permitido';
                    }
                    // Verificar Tor
                    else if (data.privacy.is_tor && ALLOW_TOR === 'no') {
                        accessAllowed = false;
                        accessDeniedReason = 'Tor detectado y no permitido';
                    }
                    // Verificar Proxy
                    else if (data.privacy.is_proxy && ALLOW_PROXY === 'no') {
                        accessAllowed = false;
                        accessDeniedReason = 'Proxy detectado y no permitido';
                    }
                    // Verificar Hosting
                    else if (data.privacy.is_hosting && ALLOW_HOSTING === 'no') {
                        accessAllowed = false;
                        accessDeniedReason = 'Hosting detectado y no permitido';
                    }
                }
                
                // Añadir información de acceso a los datos
                data.access_allowed = accessAllowed;
                
                // Establecer la razón de la decisión de acceso
                if (accessDeniedReason) {
                    data.access_denied_reason = accessDeniedReason;
                    data.access_decision_reason = accessDeniedReason;
                } else {
                    // Razón basada en el país
                    data.access_decision_reason = ACCESS_CONTROL_MODE === 'allowed'
                        ? `País ${accessAllowed ? "permitido" : "no permitido"} en la lista de permitidos`
                        : `País ${accessAllowed ? "no" : ""} en la lista de denegados`;
                }
                
                // Decisión de acceso recalculada
                
                resolve(data);
            } catch (e) {
                
                resolve(null);
            }
        });
    }
    
    // Función para obtener la IP del cliente
    function getClientIP() {
        return new Promise((resolve, reject) => {
            // Intentar obtener la IP del cliente usando múltiples servicios
            // Primero intentamos con nuestro propio servidor
            fetch(`${SERVER_URL}/proxy.php?get_ip=true`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.ip) {
                        resolve(data.ip);
                    } else {
                        throw new Error('No se pudo obtener la IP del servidor');
                    }
                })
                .catch(error => {
                    // Si falla, intentamos con ipify.org
                    return fetch('https://api.ipify.org?format=json')
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.ip) {
                                resolve(data.ip);
                            } else {
                                throw new Error('No se pudo obtener la IP de ipify');
                            }
                        });
                })
                .catch(error => {
                    // Si ambos fallan, intentamos con otro servicio
                    return fetch('https://ifconfig.me/ip')
                        .then(response => response.text())
                        .then(ip => {
                            if (ip && ip.match(/^\d+\.\d+\.\d+\.\d+$/)) {
                                resolve(ip.trim());
                            } else {
                                throw new Error('No se pudo obtener la IP de ifconfig.me');
                            }
                        });
                })
                .catch(error => {
                    // Si todo falla, usamos la IP del navegador (menos precisa)
                    resolve('0.0.0.0');
                });
        });
    }
    
    // Función para obtener la configuración del servidor
    function getServerConfig() {
        return new Promise((resolve, reject) => {
            // Usar la URL absoluta del servidor para la solicitud de configuración
            // Añadir un parámetro de tiempo para evitar la caché del navegador
            fetch(`${SERVER_URL}/proxy.php?get_config=true&t=${new Date().getTime()}`, {
                method: 'GET',
                cache: 'no-store'
            })
                .then(response => response.json())
                .then(config => {
                    debugLog('DEBUG - Configuración recibida del servidor');
                    
                    // PASO 1: Verificar si el timestamp ha cambiado
                    if (config.config_version) {
                        const serverTimestamp = String(config.config_version);
                        const cachedTimestamp = String(localStorage.getItem(CONFIG_VERSION_KEY) || '0');
                        
                        debugLog(`DEBUG - Timestamp del servidor: ${serverTimestamp}`);
                        debugLog(`DEBUG - Timestamp en caché: ${cachedTimestamp}`);
                        
                        // Si los timestamps son diferentes, limpiar todo y empezar de nuevo
                        if (serverTimestamp !== cachedTimestamp) {
                            debugLog('DEBUG - TIMESTAMPS DIFERENTES - LIMPIANDO CACHÉ COMPLETAMENTE');
                            
                            // Limpiar COMPLETAMENTE el localStorage
                            localStorage.clear();
                            debugLog('DEBUG - localStorage completamente limpiado');
                            
                            // Guardar el nuevo timestamp
                            localStorage.setItem(CONFIG_VERSION_KEY, serverTimestamp);
                            debugLog(`DEBUG - Nuevo timestamp guardado: ${serverTimestamp}`);
                            
                            // Guardar las nuevas listas de países
                            if (config.countries_allowed) {
                                localStorage.setItem('allowed_countries', config.countries_allowed);
                                debugLog(`DEBUG - Nuevos países permitidos guardados: ${config.countries_allowed}`);
                            }
                            
                            if (config.countries_denied) {
                                localStorage.setItem('denied_countries', config.countries_denied);
                                debugLog(`DEBUG - Nuevos países denegados guardados: ${config.countries_denied}`);
                            }
                            
                            // Actualizar la versión en memoria
                            currentConfigVersion = serverTimestamp;
                        } else {
                            debugLog('DEBUG - Timestamps iguales, usando configuración en caché');
                        }
                    }
                    
                    // Actualizar configuración local
                    if (config.countries_allowed) ALLOWED_COUNTRIES = config.countries_allowed.split(',').map(c => c.trim());
                    if (config.countries_denied) DENIED_COUNTRIES = config.countries_denied.split(',').map(c => c.trim());
                    if (config.access_control_mode) ACCESS_CONTROL_MODE = config.access_control_mode;
                    if (config.allow_vpn) ALLOW_VPN = config.allow_vpn;
                    if (config.allow_tor) ALLOW_TOR = config.allow_tor;
                    if (config.allow_proxy) ALLOW_PROXY = config.allow_proxy;
                    if (config.allow_hosting) ALLOW_HOSTING = config.allow_hosting;
                    if (config.access_denied_action) ACCESS_DENIED_ACTION = config.access_denied_action;
                    if (config.access_denied_redirect_url) ACCESS_DENIED_REDIRECT_URL = config.access_denied_redirect_url;
                    
                    // Guardar la URL del servidor
                    if (config.server_url) SERVER_URL = config.server_url;
                    
                    // Verificar si la versión de configuración ha cambiado
                    const configVersionChanged = config.config_version && config.config_version !== currentConfigVersion;
                    
                    // Verificar si los países permitidos o denegados han cambiado
                    const storedAllowedCountries = localStorage.getItem('allowed_countries') || '';
                    const storedDeniedCountries = localStorage.getItem('denied_countries') || '';
                    const currentAllowedCountries = config.countries_allowed || '';
                    const currentDeniedCountries = config.countries_denied || '';
                    
                    const countriesChanged =
                        storedAllowedCountries !== currentAllowedCountries ||
                        storedDeniedCountries !== currentDeniedCountries;
                    
                    // Actualizar las listas de países en memoria
                    if (config.countries_allowed) ALLOWED_COUNTRIES = config.countries_allowed.split(',').map(c => c.trim());
                    if (config.countries_denied) DENIED_COUNTRIES = config.countries_denied.split(',').map(c => c.trim());
                    
                    // Verificar si los timestamps son iguales (no necesitamos limpiar el caché)
                    // Obtener los timestamps nuevamente para comparar
                    const serverTimestampForCheck = String(config.config_version || '0');
                    const cachedTimestampForCheck = String(localStorage.getItem(CONFIG_VERSION_KEY) || '0');
                    const timestampsMatch = serverTimestampForCheck === cachedTimestampForCheck;
                    
                    // Si los timestamps coinciden, no deberíamos limpiar el caché
                    if ((configVersionChanged || countriesChanged) && !timestampsMatch) {
                        debugLog(`DEBUG - Configuración cambiada detectada`);
                        if (configVersionChanged) {
                            debugLog(`DEBUG - Versión de configuración cambiada: ${currentConfigVersion} -> ${config.config_version}`);
                        }
                        if (countriesChanged) {
                            debugLog(`DEBUG - Listas de países cambiadas`);
                            debugLog(`DEBUG - Países permitidos: ${storedAllowedCountries} -> ${currentAllowedCountries}`);
                            debugLog(`DEBUG - Países denegados: ${storedDeniedCountries} -> ${currentDeniedCountries}`);
                        }
                        
                        debugLog('DEBUG - Limpiando caché debido al cambio de configuración');
                        
                        // Actualizar la versión de configuración almacenada
                        if (config.config_version) {
                            currentConfigVersion = config.config_version;
                            localStorage.setItem(CONFIG_VERSION_KEY, currentConfigVersion);
                        }
                        
                        // Guardar las listas de países actuales
                        localStorage.setItem('allowed_countries', currentAllowedCountries);
                        localStorage.setItem('denied_countries', currentDeniedCountries);
                        
                        // Limpiar COMPLETAMENTE el localStorage para forzar una actualización total
                        debugLog('DEBUG - Limpiando COMPLETAMENTE localStorage debido al cambio de configuración');
                        
                        // Obtener todas las claves que pertenecen a nuestro script
                        const keysToRemove = [];
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            if (key && key.startsWith('direct_geo_')) {
                                keysToRemove.push(key);
                            }
                        }
                        
                        // Eliminar todas las claves relacionadas con nuestro script
                        keysToRemove.forEach(key => {
                            debugLog(`DEBUG - Eliminando clave: ${key}`);
                            localStorage.removeItem(key);
                        });
                        
                        // Guardar solo la nueva versión de configuración
                        localStorage.setItem(CONFIG_VERSION_KEY, currentConfigVersion);
                        localStorage.setItem('allowed_countries', currentAllowedCountries);
                        localStorage.setItem('denied_countries', currentDeniedCountries);
                        
                        debugLog('DEBUG - Configuración actualizada, continuando con datos frescos');
                    } else if (timestampsMatch) {
                        debugLog('DEBUG - Timestamps coinciden, no es necesario limpiar el caché');
                    }
                    
                    // Actualizar la lista de dominios permitidos
                    if (config.domains_whitelist) {
                        debugLog('DEBUG - Actualizando lista de dominios desde el servidor:', config.domains_whitelist);
                        DOMAINS_WHITELIST = config.domains_whitelist.split(',').map(d => d.trim());
                        
                        // Verificar nuevamente si el dominio está permitido con la lista actualizada
                        debugLog('DEBUG - Verificando dominio con la lista actualizada...');
                        const isDomainAllowed = checkDomainWhitelist();
                        
                        if (!isDomainAllowed) {
                            // Verificar si el dominio actual estaba en la lista inicial
                            const currentDomain = window.location.hostname;
                            const wasInInitialList = ['validatumail.cc', 'rastroseguro.com', 'localhost'].some(domain =>
                                currentDomain === domain || currentDomain.endsWith('.' + domain)
                            );
                            
                            // Si el dominio no estaba en la lista inicial pero ahora está permitido,
                            // recargar la página para permitir el acceso
                            if (!wasInInitialList && isDomainAllowed) {
                                debugLog('DEBUG - Dominio no estaba en la lista inicial pero ahora está permitido, recargando página...');
                                
                                // Mostrar mensaje de recarga
                                const reloadMsg = document.createElement('div');
                                reloadMsg.style.position = 'fixed';
                                reloadMsg.style.top = '0';
                                reloadMsg.style.left = '0';
                                reloadMsg.style.width = '100%';
                                reloadMsg.style.padding = '10px';
                                reloadMsg.style.backgroundColor = '#4CAF50';
                                reloadMsg.style.color = 'white';
                                reloadMsg.style.textAlign = 'center';
                                reloadMsg.style.zIndex = '9999';
                                reloadMsg.textContent = 'Dominio autorizado, recargando página...';
                                document.body.appendChild(reloadMsg);
                                
                                // Recargar la página después de un breve retraso
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                                
                                return;
                            }
                            
                            console.error('Geo Access Control: Este dominio no está autorizado para usar este script según la configuración del servidor.');
                            // Detener completamente la ejecución del script
                            throw new Error('Dominio no autorizado después de actualizar la lista: ' + window.location.hostname);
                        } else {
                            debugLog('DEBUG - Dominio autorizado después de actualizar la lista, continuando ejecución...');
                        }
                    }
                    
                    resolve(config);
                })
                .catch(error => {
                    
                    reject(error);
                });
        });
    }
    
    // Función para obtener datos de geolocalización
    function getGeoData(ip) {
        return new Promise((resolve, reject) => {
            debugLog('DEBUG - getGeoData: Iniciando para IP', ip);
            
            // Forzar actualización de caché si se solicita o si la configuración ha cambiado recientemente
            const forceRefresh = window.location.search.includes('force_refresh=true');
            const configChanged = localStorage.getItem('config_recently_changed') === 'true';
            
            if (forceRefresh || configChanged) {
                debugLog('DEBUG - getGeoData: Forzando actualización ' +
                    (forceRefresh ? '(force_refresh=true)' : '(configuración cambiada recientemente)'));
                clearCache();
                
                // Limpiar el indicador de cambio reciente de configuración
                
                // Función para verificar directamente el acceso basado en el país
                function checkDirectAccess(countryCode) {
                    debugLog('DEBUG - checkDirectAccess: Verificando acceso directo para', countryCode);
                    debugLog('DEBUG - checkDirectAccess: Modo de control:', ACCESS_CONTROL_MODE);
                    debugLog('DEBUG - checkDirectAccess: Países permitidos:', ALLOWED_COUNTRIES);
                    debugLog('DEBUG - checkDirectAccess: Países denegados:', DENIED_COUNTRIES);
                    
                    let accessAllowed = false;
                    
                    if (ACCESS_CONTROL_MODE === 'allowed') {
                        // Verificar si el país está en la lista de permitidos
                        for (const country of ALLOWED_COUNTRIES) {
                            if (countryCode === country.trim()) {
                                accessAllowed = true;
                                break;
                            }
                        }
                        debugLog(`DEBUG - checkDirectAccess: ¿${countryCode} está en la lista de permitidos?`, accessAllowed);
                    } else {
                        // Verificar si el país está en la lista de denegados
                        let isDenied = false;
                        for (const country of DENIED_COUNTRIES) {
                            if (countryCode === country.trim()) {
                                isDenied = true;
                                break;
                            }
                        }
                        accessAllowed = !isDenied;
                        debugLog(`DEBUG - checkDirectAccess: ¿${countryCode} está en la lista de denegados?`, isDenied);
                    }
                    
                    debugLog('DEBUG - checkDirectAccess: Acceso permitido:', accessAllowed);
                    
                    // Si el acceso está denegado y la acción es redirigir, hacerlo inmediatamente
                    if (!accessAllowed && ACCESS_DENIED_ACTION === 'redirect') {
                        debugLog('DEBUG - checkDirectAccess: Acceso denegado, redirigiendo...');
                        const redirectUrl = ACCESS_DENIED_REDIRECT_URL;
                        debugLog('DEBUG - checkDirectAccess: Redirigiendo a:', redirectUrl);
                        
                        // Crear un elemento visible para mostrar que se está redirigiendo
                        const redirectMsg = document.createElement('div');
                        redirectMsg.style.position = 'fixed';
                        redirectMsg.style.top = '0';
                        redirectMsg.style.left = '0';
                        redirectMsg.style.width = '100%';
                        redirectMsg.style.padding = '10px';
                        redirectMsg.style.backgroundColor = 'red';
                        redirectMsg.style.color = 'white';
                        redirectMsg.style.textAlign = 'center';
                        redirectMsg.style.zIndex = '9999';
                        redirectMsg.textContent = 'Acceso denegado - Redirigiendo...';
                        document.body.appendChild(redirectMsg);
                        
                        // Redirigir después de un breve retraso
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, 1000);
                        
                        return false;
                    }
                    
                    return accessAllowed;
                }
                if (configChanged) {
                    localStorage.removeItem('config_recently_changed');
                }
            }
            
            // Verificar si tenemos una versión de configuración en caché
            const cachedConfigVersion = localStorage.getItem(CONFIG_VERSION_KEY);
            
            // Si tenemos una versión de configuración en caché y no estamos forzando actualización,
            // podemos intentar usar directamente los datos en caché sin consultar al servidor
            if (cachedConfigVersion && !forceRefresh && !configChanged) {
                debugLog(`DEBUG - getGeoData: Usando configuración en caché (versión: ${cachedConfigVersion})`);
                
                // Verificar si hay datos en caché directamente - Optimización para mayor velocidad
                try {
                    // Intentar obtener datos directamente del localStorage sin promesas para mayor velocidad
                    const normalizedIp = normalizeIpForCache(ip);
                    const ipCacheKey = CACHE_KEY_PREFIX + normalizedIp;
                    const cachedDataStr = localStorage.getItem(ipCacheKey);
                    
                    if (cachedDataStr) {
                        debugLog(`DEBUG - getGeoData: Acceso rápido a caché para IP ${normalizedIp}`);
                        const data = JSON.parse(cachedDataStr);
                        
                        // Verificar si los datos están expirados
                        const cacheTime = data.timestamp;
                        const now = new Date().getTime();
                        if (now - cacheTime >= CACHE_DURATION) {
                            debugLog('DEBUG - getGeoData: Datos expirados, obteniendo del servidor');
                            fetchFromServer();
                            return;
                        }
                        
                        // Verificar si la versión de configuración coincide
                        if (!data.config_version || String(data.config_version) !== String(cachedConfigVersion)) {
                            debugLog('DEBUG - getGeoData: Versión de configuración no coincide, obteniendo del servidor');
                            fetchFromServer();
                            return;
                        }
                        
                        debugLog('DEBUG - getGeoData: Usando datos en caché (acceso rápido)');
                        
                        // Verificar si ya tenemos la decisión de acceso en caché
                        let accessAllowed = data.access_allowed;
                        
                        // Si no tenemos la decisión en caché, recalcularla
                        if (accessAllowed === undefined) {
                            if (ACCESS_CONTROL_MODE === 'allowed') {
                                accessAllowed = ALLOWED_COUNTRIES.includes(data.country_code);
                            } else {
                                accessAllowed = !DENIED_COUNTRIES.includes(data.country_code);
                            }
                            // Actualizar el estado de acceso
                            data.access_allowed = accessAllowed;
                        }
                        
                        // Verificar rápidamente si necesitamos redirigir
                        if (!accessAllowed && ACCESS_DENIED_ACTION === 'redirect') {
                            debugLog('DEBUG - getGeoData: Acceso denegado detectado en caché, redirigiendo inmediatamente...');
                            const countryName = data.country || data.country_code || 'Desconocido';
                            let redirectUrl = ACCESS_DENIED_REDIRECT_URL;
                            if (!redirectUrl.startsWith('http')) {
                                redirectUrl += (redirectUrl.includes('?') ? '&' : '?') + 'country=' + encodeURIComponent(countryName);
                            }
                            window.location.href = redirectUrl;
                            return;
                        }
                        
                        // Manejar formularios
                        handleForms(data);
                        
                        // Resolver con los datos en caché
                        resolve(data);
                    } else {
                        // Si no hay datos en caché, continuar con el flujo normal
                        debugLog('DEBUG - getGeoData: No hay datos en caché, obteniendo del servidor');
                        fetchFromServer();
                    }
                } catch (error) {
                    debugError('DEBUG - Error en acceso rápido a caché:', error);
                    // Si hay algún error, continuar con el flujo normal
                    fetchFromServer();
                }
            } else {
                // Si no hay configuración en caché o estamos forzando actualización, obtener del servidor
                fetchFromServer();
            }
            
            // Función para obtener datos del servidor
            function fetchFromServer() {
                // Si ya verificamos la configuración del servidor, no necesitamos hacerlo de nuevo
                const configPromise = serverConfigChecked ?
                    Promise.resolve() : // Si ya verificamos, devolver una promesa resuelta
                    getServerConfig();   // Si no, verificar la configuración
                
                // Continuar con el flujo normal
                configPromise
                    .then(() => {
                        debugLog('DEBUG - getGeoData: Verificando caché');
                        // Verificar si hay datos en caché
                        return getFromCache(ip);
                    })
                    .then(cachedData => {
                    if (cachedData) {
                        debugLog('DEBUG - getGeoData: Usando datos en caché');
                        debugLog('DEBUG - País detectado (caché):', cachedData.country_code);
                        debugLog('DEBUG - Lista de países permitidos:', ALLOWED_COUNTRIES);
                        debugLog('DEBUG - Lista de países denegados:', DENIED_COUNTRIES);
                        debugLog('DEBUG - Modo de control de acceso:', ACCESS_CONTROL_MODE);
                        
                        // Recalcular el acceso con la configuración actual
                        let accessAllowed = false;
                        if (ACCESS_CONTROL_MODE === 'allowed') {
                            accessAllowed = ALLOWED_COUNTRIES.includes(cachedData.country_code);
                            debugLog(`DEBUG - getGeoData: Recalculando acceso - ¿${cachedData.country_code} está en la lista de permitidos?`, accessAllowed);
                        } else {
                            accessAllowed = !DENIED_COUNTRIES.includes(cachedData.country_code);
                            debugLog(`DEBUG - getGeoData: Recalculando acceso - ¿${cachedData.country_code} está en la lista de denegados?`, !accessAllowed);
                        }
                        
                        // Actualizar el estado de acceso
                        cachedData.access_allowed = accessAllowed;
                        debugLog('DEBUG - getGeoData: Acceso recalculado:', accessAllowed);
                        
                        // Verificar si el acceso está permitido
                        if (!accessAllowed && ACCESS_DENIED_ACTION === 'redirect') {
                            debugLog('DEBUG - Acceso denegado, redirigiendo...');
                            
                            // Asegurarse de que data.country existe antes de usarlo
                            const countryName = cachedData.country || cachedData.country_code || 'Desconocido';
                            
                            // Construir la URL con el parámetro country
                            let redirectUrl = ACCESS_DENIED_REDIRECT_URL;
                            
                            // Añadir el parámetro country solo si la URL no es externa
                            if (!redirectUrl.startsWith('http')) {
                                redirectUrl += (redirectUrl.includes('?') ? '&' : '?') + 'country=' + encodeURIComponent(countryName);
                            }
                            
                            // Redirigir a la página de acceso denegado
                            window.location.href = redirectUrl;
                            return;
                        } else {
                            // Si no redirigimos, manejar formularios
                            
                            handleForms(cachedData);
                        }
                        
                        // Registrar en el servidor que se están usando datos en caché
                        const normalizedIp = normalizeIpForCache(ip);
                        // Usar siempre la URL absoluta del servidor
                        fetch(`${SERVER_URL}/proxy.php?debug_only=true&message=Usando_datos_en_cache_para_IP_${normalizedIp}`, {
                            method: 'GET',
                            cache: 'no-store'
                        }).catch(e => {});
                        
                        resolve(cachedData);
                        return null; // Indicar que no necesitamos continuar
                    }
                    
                    // Si no hay datos en caché, hacer una solicitud al servidor PHP
                    
                    
                    // Si llegamos aquí, necesitamos obtener datos de la API
                    // Usar la IP normalizada para la consulta
                    const normalizedIp = normalizeIpForCache(ip);
                    
                    
                    // Ahora hacer la solicitud a la API
                    // Usar siempre la URL absoluta del servidor
                    return fetch(`${SERVER_URL}/proxy.php?ip=${normalizedIp}`, {
                        method: 'GET',
                        cache: 'no-store',
                        // Aumentar el timeout para darle más tiempo a la API para responder
                        timeout: 10000
                    });
                })
                .then(response => {
                    if (response === null) {
                        // Si es null, significa que estamos usando datos en caché
                        return null;
                    }
                    
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data === null) {
                        // Si es null, significa que estamos usando datos en caché
                        return;
                    }
                    
                    // Añadir la IP consultada a los datos
                    data.queried_ip = ip;
                    
                    // Procesar y guardar los datos
                    const processedData = handleGeoData(data);
                    
                    // Asegurarse de que handleForms se llame con los datos de la API
                    
                    handleForms(processedData);
                    
                    resolve(processedData);
                })
                .catch(error => {
                    
                    
                    // Registrar el error en el servidor
                    // Usar siempre la URL absoluta del servidor
                    fetch(`${SERVER_URL}/proxy.php?debug_only=true&message=Error_en_cliente:_${error.message.replace(/\s+/g, '_')}`, {
                        method: 'GET',
                        cache: 'no-store'
                    }).catch(e => {});
                    
                    reject(error);
                });
            } // End of fetchFromServer function
        });
    }
    
    // Inicializar el sistema
    // Variable global para indicar que ya se verificó la configuración del servidor
    let serverConfigChecked = false;
    
    function init() {
        console.log('DEBUG - Inicializando sistema de geolocalización');
        debugLog('DEBUG - Inicializando sistema de geolocalización');
        
        // Cargar caché de validación de email
        loadEmailValidationCache();
        
        // SIEMPRE verificar la configuración del servidor primero
        console.log('DEBUG - Verificando configuración actual del servidor...');
        debugLog('DEBUG - Verificando configuración actual del servidor...');
        
        // Obtener la configuración del servidor
        getServerConfig()
            .then(config => {
                console.log('DEBUG - Verificación de configuración completada');
                debugLog('DEBUG - Verificación de configuración completada');
                
                // Marcar que ya se verificó la configuración
                serverConfigChecked = true;
                
                // Cargar países permitidos/denegados desde localStorage (ya actualizados si era necesario)
                const storedAllowedCountries = localStorage.getItem('allowed_countries');
                const storedDeniedCountries = localStorage.getItem('denied_countries');
                
                if (storedAllowedCountries) {
                    ALLOWED_COUNTRIES = storedAllowedCountries.split(',').map(c => c.trim());
                    console.log('DEBUG - Países permitidos cargados:', ALLOWED_COUNTRIES);
                    debugLog('DEBUG - Países permitidos cargados:', ALLOWED_COUNTRIES);
                }
                
                if (storedDeniedCountries) {
                    DENIED_COUNTRIES = storedDeniedCountries.split(',').map(c => c.trim());
                    console.log('DEBUG - Países denegados cargados:', DENIED_COUNTRIES);
                    debugLog('DEBUG - Países denegados cargados:', DENIED_COUNTRIES);
                }
                
                // Configurar la validación de email inmediatamente
                console.log('DEBUG - Configurando validación de email después de cargar configuración');
                setupEmailValidation();
                
                // Obtener la IP del cliente
                return getClientIP();
            })
            .then(ip => {
                console.log('DEBUG - IP obtenida:', ip);
                debugLog('DEBUG - IP obtenida:', ip);
                return getGeoData(ip);
            })
            .then(data => {
                if (data && data.access_allowed !== undefined) {
                    handleForms(data);
                }
                
                // Asegurarse de que la validación de email esté configurada
                console.log('DEBUG - Asegurando configuración de validación de email después de geolocalización');
                setupEmailValidation();
            })
            .catch(error => {
                console.error('DEBUG - Error en inicialización:', error);
                debugError('DEBUG - Error en inicialización:', error);
                
                // Incluso en caso de error, configurar la validación de email
                console.log('DEBUG - Configurando validación de email después de error');
                setupEmailValidation();
            });
    }
    
    // Iniciar el sistema cuando el DOM esté cargado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Exponer algunas funciones para uso externo
    window.directGeo = {
        getClientIP: getClientIP,
        getGeoData: getGeoData,
        validateEmail: function(email, callback) {
            // Versión pública de la función de validación de email
            // Esta versión solo devuelve el resultado a través del callback
            // y no modifica el DOM
            
            if (!email) {
                if (callback) callback(false, 'Email no proporcionado');
                return;
            }
            
            fetch(`${SERVER_URL}/email_validator.php?email=${encodeURIComponent(email)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        if (callback) callback(false, data.error);
                    } else if (data.status === 'valid') {
                        if (callback) callback(true, null);
                    } else {
                        if (callback) callback(false, 'Email inválido');
                    }
                })
                .catch(error => {
                    if (callback) callback(false, error.message);
                });
        },
        clearCache: function() {
            debugLog('DEBUG - Limpiando caché completamente...');
            
            // Método 1: Eliminar claves específicas de caché
            const storedKeys = localStorage.getItem(CACHE_KEYS_LIST_KEY);
            if (storedKeys) {
                try {
                    const cacheKeys = JSON.parse(storedKeys);
                    // Eliminar cada clave de caché
                    cacheKeys.forEach(key => {
                        debugLog(`DEBUG - Eliminando clave de caché: ${key}`);
                        localStorage.removeItem(key);
                    });
                } catch (e) {
                    debugError('DEBUG - Error al limpiar caché específica:', e);
                }
            }
            
            // Método 2: Eliminar todas las claves que empiezan con nuestro prefijo
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('direct_geo_')) {
                    keysToRemove.push(key);
                }
            }
            
            // Eliminar las claves encontradas
            keysToRemove.forEach(key => {
                debugLog(`DEBUG - Eliminando clave adicional: ${key}`);
                localStorage.removeItem(key);
            });
            
            // Eliminar la lista de claves
            localStorage.removeItem(CACHE_KEYS_LIST_KEY);
            
            debugLog('DEBUG - Caché limpiado completamente');

            debugLog('DEBUG - Caché limpiado correctamente');
            return {
                success: true,
                message: 'Caché limpiado correctamente'
            };
        },
        getCacheInfo: function() {
            debugLog('DEBUG - Obteniendo información de caché');
            const result = [];
            
            // Método 1: Usar la lista de claves almacenada
            const storedKeys = localStorage.getItem(CACHE_KEYS_LIST_KEY);
            if (storedKeys) {
                try {
                    const cacheKeys = JSON.parse(storedKeys);
                    cacheKeys.forEach(key => {
                        const data = localStorage.getItem(key);
                        if (data) {
                            try {
                                const parsedData = JSON.parse(data);
                                result.push({
                                    key: key,
                                    ip: parsedData.ip,
                                    country: parsedData.country,
                                    country_code: parsedData.country_code,
                                    timestamp: new Date(parsedData.timestamp).toLocaleString(),
                                    config_version: parsedData.config_version || 'no definida'
                                });
                            } catch (e) {
                                debugLog(`DEBUG - Error al parsear datos de caché para ${key}:`, e);
                            }
                        }
                    });
                } catch (e) {
                    debugLog('DEBUG - Error al parsear lista de claves:', e);
                }
            }
            
            // Método 2: Buscar todas las claves que empiezan con nuestro prefijo
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(CACHE_KEY_PREFIX) && !result.some(item => item.key === key)) {
                    const data = localStorage.getItem(key);
                    if (data) {
                        try {
                            const parsedData = JSON.parse(data);
                            result.push({
                                key: key,
                                ip: parsedData.ip,
                                country: parsedData.country,
                                country_code: parsedData.country_code,
                                timestamp: new Date(parsedData.timestamp).toLocaleString(),
                                config_version: parsedData.config_version || 'no definida'
                            });
                        } catch (e) {
                            debugLog(`DEBUG - Error al parsear datos adicionales de caché para ${key}:`, e);
                        }
                    }
                }
            }
            
            debugLog(`DEBUG - Encontradas ${result.length} entradas en caché`);
            return result;
        },
        getAllCachedIPs: function() {
            return this.getCacheInfo();
        },
        getConfigInfo: function() {
            return {
                version: currentConfigVersion,
                allowed_countries: ALLOWED_COUNTRIES,
                denied_countries: DENIED_COUNTRIES,
                access_control_mode: ACCESS_CONTROL_MODE,
                access_denied_action: ACCESS_DENIED_ACTION
            };
        }
    };
})();