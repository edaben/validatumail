# Sistema de Control de Acceso Basado en Geolocalización

Este sistema permite controlar el acceso a sitios web basado en la ubicación geográfica del visitante. Funciona verificando el país de origen del usuario y aplicando reglas de acceso configurables, lo que te permite:

- **Restringir el acceso por país**: Bloquear o permitir visitantes según su ubicación geográfica
- **Proteger formularios**: Desactivar formularios para visitantes de países no autorizados
- **Detectar VPNs y proxies**: Identificar y bloquear usuarios que intentan ocultar su ubicación real

El script está optimizado para máximo rendimiento, utilizando un sistema de caché inteligente que minimiza las llamadas al servidor y proporciona respuestas casi instantáneas para visitantes recurrentes.

## Características

- **Verificación de IP altamente eficiente**: Determina el país de origen con precisión
- **Listas configurables**: Define países permitidos o denegados según tus necesidades
- **Modos de control flexibles**:
  - Modo "allowed" - Solo permite países específicos (bloquea todos los demás)
  - Modo "denied" - Bloquea países específicos (permite todos los demás)
- **Opciones de bloqueo configurables**:
  - Redirección a una página de acceso denegado
  - Desactivación de formularios manteniendo el resto del sitio accesible
- **Sistema de caché optimizado**: Respuestas en milisegundos para visitantes recurrentes
- **Seguridad por dominio**: Control estricto de dónde puede ejecutarse el script
- **Detección avanzada**: Identificación de VPN, Tor, proxies y servicios de hosting

## Requisitos

- Servidor web con PHP 7.0 o superior
- Cuenta y clave API de [iplocate.io](https://iplocate.io/)

## Instalación

1. Sube la carpeta `geo` a tu servidor web
2. Configura los archivos `.env` y `.api_key` con tus ajustes
3. Asegúrate de que el archivo `api.php` sea accesible vía HTTP/HTTPS
4. Incluye el script en tu sitio web

## Configuración

### Archivo `.api_key`

Este archivo contiene únicamente la clave API de iplocate.io:

```
IPLOCATE_API_KEY=tu_clave_api_aquí
```

### Archivo `.env`

Este archivo contiene el resto de la configuración:

```
# Modo de Control de Acceso
# Establecer como "allowed" para usar la lista COUNTRIES_ALLOWED (bloquear todos los demás)
# Establecer como "denied" para usar la lista COUNTRIES_DENIED (permitir todos los demás)
ACCESS_CONTROL_MODE=allowed

# Listas de Países (códigos ISO de 2 letras separados por comas)
COUNTRIES_ALLOWED=US,CA,GB,AU,ES,MX,PA
COUNTRIES_DENIED=RU,CN,IR,KP

# Lista de Dominios Permitidos (dominios separados por comas donde el script puede ejecutarse)
DOMAINS_WHITELIST=sitios.smartyapz.com,example.com,localhost

# URL del Servidor (donde se encuentra la carpeta geo)
SERVER_URL=https://sitios.smartyapz.com/geo

# Duración de la Caché (en horas)
CACHE_DURATION=24
```

## Uso

### Implementación en Cualquier Sitio Web

Para implementar el sistema de geolocalización en cualquier sitio web, sigue estos pasos:

1. **Añade este único script** a tu sitio web (en la sección `<head>` o antes del cierre de `</body>`):

```html
<script src="https://sitios.smartyapz.com/geo/direct_geo_loader.php"></script>
```

2. **¡Eso es todo!** El script se encargará automáticamente de:
   - Verificar la ubicación del visitante
   - Aplicar las reglas de acceso configuradas
   - Bloquear o permitir el acceso según corresponda
   - Desactivar formularios si es necesario

### ⚠️ IMPORTANTE: Whitelist de Dominios

Para que el script funcione en tu sitio web, **DEBES** añadir tu dominio a la lista de dominios permitidos:

1. Contacta al administrador del script (o edita el archivo `.env` si tienes acceso)
2. Añade tu dominio a la variable `DOMAINS_WHITELIST`
3. El script NO funcionará en dominios que no estén en esta lista

Ejemplo de configuración en `.env`:
```
DOMAINS_WHITELIST=sitios.smartyapz.com,example.com,localhost,tu-dominio.com
```

### URL Correcta del Servidor

El script debe conocer la ubicación exacta donde está alojado. La URL predeterminada es:

```
https://sitios.smartyapz.com/geo
```

Si estás alojando el script en una ubicación diferente, debes actualizar la variable `SERVER_URL` en el archivo `.env`.

### Funcionamiento

1. Cuando un visitante accede a tu sitio web, el script verifica si el dominio está en la lista blanca
2. Si el dominio está permitido, el script comprueba el almacenamiento local para datos en caché
3. Si no hay datos en caché o han expirado, el script realiza una solicitud al backend PHP
4. El backend PHP obtiene la IP del visitante y consulta la API de iplocate.io
5. Según la configuración y el país del visitante, se determina si se permite el acceso
6. Si el acceso está denegado, el visitante es redirigido a la página de acceso denegado
7. Si el acceso está permitido pero los formularios están bloqueados, los campos del formulario se desactivan

## Pruebas

Puedes usar la página `test.html` incluida para probar el sistema:

1. Accede a `https://sitios.smartyapz.com/geo/test.html` en tu navegador
2. La página mostrará los datos de geolocalización detectados para tu IP
3. Si tu país está bloqueado, serás redirigido a la página de acceso denegado
4. Si tu país está permitido pero los formularios están bloqueados, verás el formulario con campos desactivados

### Probar con Diferentes IPs

Para probar con diferentes IPs, puedes añadir el parámetro `test_ip` a la URL de la API:

```
https://sitios.smartyapz.com/geo/api.php?test_ip=185.197.192.118
```

Esto simulará una solicitud desde la IP especificada.

## Sistema de Caché Optimizado

### Rendimiento Ultrarrápido

El script utiliza un sistema de caché altamente optimizado que proporciona respuestas casi instantáneas:

1. **Primera visita**: ~1-2 segundos para obtener y procesar los datos de geolocalización
2. **Visitas posteriores**: ~100 milisegundos para recuperar y aplicar los datos en caché
3. **Detección de cambios**: Actualización automática cuando cambia la configuración

### Cómo Funciona el Sistema de Caché

El script almacena los datos de geolocalización en el localStorage del navegador:

1. **Primera visita**: Se realiza una solicitud al servidor para obtener la ubicación geográfica
2. **Almacenamiento eficiente**: Los datos se guardan en localStorage con un identificador basado en la IP
3. **Acceso directo**: En visitas posteriores, se accede directamente a los datos almacenados
4. **Verificación inteligente**: La decisión de acceso se recalcula con la configuración actual

### Sistema de Detección de Cambios de Configuración

El script implementa un sistema de timestamp que detecta automáticamente cuando cambia la configuración:

1. **Timestamp único**: Cada vez que se modifica el archivo `.env`, se genera un nuevo timestamp
2. **Comparación instantánea**: Al cargar, el script compara el timestamp del servidor con el almacenado
3. **Actualización automática**: Si los timestamps difieren, el script limpia la caché y obtiene datos frescos
4. **Sin intervención manual**: Los cambios de configuración se aplican automáticamente a todos los usuarios

Este sistema garantiza que cuando actualices la configuración (como añadir un país a la lista de permitidos), todos los usuarios obtendrán automáticamente los nuevos ajustes en su próxima visita.

### Forzar actualización de datos

Además del sistema automático, también puedes forzar una actualización manualmente:

1. **Usar el parámetro `force_refresh=true`**: Añade este parámetro a la URL para forzar una actualización ignorando la caché:
   ```
   https://tu-sitio.com/pagina.html?force_refresh=true
   ```

2. **Usar el botón "Forzar Actualización"** en la página de prueba: Este botón añade automáticamente el parámetro `force_refresh=true` a la URL.

3. **Limpiar manualmente el caché**: Puedes borrar el almacenamiento local del navegador o usar el botón "Limpiar Caché" en la página de prueba.

## Solución de Problemas

### El Script No Funciona en Mi Dominio

- Verifica que tu dominio esté incluido en la lista `DOMAINS_WHITELIST` en el archivo `.env`
- Comprueba la consola del navegador para ver si hay errores CORS
- Asegúrate de que el archivo `proxy.php` sea accesible desde tu dominio
- Si estás implementando el script en un dominio diferente, asegúrate de usar `direct_geo_loader.php` en lugar de `direct_geo.js` directamente
- **Verifica que la variable `SERVER_URL` al inicio del archivo `direct_geo.js` esté configurada correctamente** con la URL absoluta donde se encuentra la carpeta geo

### Errores de la API

- Verifica que tu clave API de iplocate.io sea válida
- Comprueba si has alcanzado el límite de solicitudes de tu plan
- Verifica la conexión a internet del servidor

## Seguridad

### Restricción de Dominios

El script incluye una estricta verificación de seguridad que impide completamente su ejecución en dominios no autorizados. Esta verificación se realiza en dos niveles:

1. **Verificación inicial**: Al cargar el script, se comprueba si el dominio actual está en la lista blanca definida en el archivo `.env`.
2. **Verificación dinámica**: Después de cargar la configuración del servidor, se verifica nuevamente con la misma lista.

#### Configuración de la Lista Blanca

La lista de dominios permitidos se define **únicamente** en el archivo `.env` mediante la variable `DOMAINS_WHITELIST`:

```
DOMAINS_WHITELIST=sitios.smartyapz.com,example.com,localhost,tu-nuevo-dominio.com
```

Cuando el script se carga a través de `direct_geo_loader.php`, este lee automáticamente la lista de dominios del archivo `.env` y la inyecta en el JavaScript, asegurando que siempre se use la misma lista en todas las verificaciones.

#### Qué sucede en dominios no autorizados

Si intentas cargar el script en un dominio no autorizado:
1. El script mostrará un mensaje de error en la consola del navegador
2. La ejecución se detendrá completamente
3. No se realizará ninguna solicitud a la API
4. Los formularios no se modificarán

#### Cómo funciona la verificación de dominios

La verificación de dominios comprueba si el dominio actual coincide con alguno de los siguientes criterios:
1. **Coincidencia exacta**: El dominio actual es exactamente igual a uno de los dominios en la lista blanca
2. **Coincidencia de subdominio**: El dominio actual es un subdominio de uno de los dominios en la lista blanca
3. **Localhost**: El dominio actual es 'localhost'

Por ejemplo, si `example.com` está en la lista blanca, tanto `example.com` como `subdomain.example.com` serán permitidos.

- La clave API nunca se expone al cliente
- El script solo funciona en dominios incluidos en la lista blanca
- Todas las llamadas a la API se realizan desde el servidor
- Los encabezados CORS están configurados para permitir solo dominios autorizados

## Personalización

### Modo de Depuración

El script incluye un modo de depuración que puedes activar o desactivar fácilmente:

```
# En el archivo .env
DEBUG_MODE=true   # Activa todos los mensajes de depuración
DEBUG_MODE=false  # Desactiva todos los mensajes de depuración
```

Cuando `DEBUG_MODE=false`:
- No se mostrarán mensajes de depuración en la consola del navegador
- No se enviarán mensajes de depuración al servidor
- El script funcionará silenciosamente en segundo plano

Esta configuración es especialmente útil para entornos de producción donde no quieres llenar la consola con mensajes de depuración.

### Página de Acceso Denegado

Puedes personalizar la página de acceso denegado (`access-denied.php`) según tus necesidades. La página actual muestra un mensaje simple, pero puedes añadir más información o estilos para que coincida con el diseño de tu sitio web.

### Mensajes de Formulario

Puedes modificar el mensaje que se muestra debajo de los botones de envío de formularios en el archivo `direct_geo.js`. Busca la línea:

```javascript
message.textContent = 'País / VPN no permitido';
```

Y cámbiala según tus preferencias.

## Mejores Prácticas y Sugerencias

### Configuración Recomendada

1. **Modo de Control**:
   - Usa `ACCESS_CONTROL_MODE=allowed` para mayor seguridad (solo permite países específicos)
   - Usa `ACCESS_CONTROL_MODE=denied` para mayor accesibilidad (bloquea solo países problemáticos)

2. **Listas de Países**:
   - Evita añadir el mismo país en ambas listas (allowed y denied)
   - Usa códigos ISO de 2 letras (US, CA, ES, etc.)
   - Separa los códigos con comas

3. **Detección de VPN/Proxy**:
   - Activa la detección de VPN si tu sitio maneja información sensible
   - Ten en cuenta que algunos usuarios legítimos pueden usar VPNs

### Optimización de Rendimiento

1. **Duración de Caché**:
   - Valor recomendado: 24 horas (predeterminado)
   - Aumenta para mayor rendimiento, reduce para actualizaciones más frecuentes

2. **Carga del Script**:
   - Coloca el script al final del `<body>` para no bloquear la carga de la página
   - Si necesitas bloquear acceso inmediato, colócalo en el `<head>`

### Casos de Uso Recomendados

1. **Protección de Formularios**:
   - Formularios de contacto
   - Formularios de registro
   - Sistemas de comentarios

2. **Restricción de Contenido**:
   - Contenido con restricciones geográficas
   - Ofertas específicas por país
   - Cumplimiento de normativas regionales

3. **Seguridad**:
   - Reducción de spam y ataques automatizados
   - Protección contra accesos no deseados
   - Cumplimiento de políticas de seguridad

### Limitaciones

- El sistema se basa en la IP del usuario, que puede ser enmascarada con VPNs o proxies
- La precisión de la geolocalización depende de la base de datos de iplocate.io
- El bloqueo ocurre en el lado del cliente, por lo que no es adecuado para proteger APIs o datos sensibles del servidor

## Soporte y Actualizaciones

Para obtener soporte o reportar problemas, contacta al administrador del sistema. El script se actualiza regularmente para mejorar su rendimiento y seguridad.