<?php
/**
 * SCI-CHAT-SYSTEM - Configuración de CORS
 *
 * @description Este script establece las cabeceras HTTP necesarias para permitir
 *              solicitudes de recursos de origen cruzado (CORS) desde los dominios
 *              frontend autorizados.
 */

// Lista de dominios de frontend permitidos.
// En producción, sé muy específico. Usa '*' solo para desarrollo si es necesario.
$allowed_origins = [
    'http://chat.test',             // Tu dominio de desarrollo
    'http://cliente.test',          // Tu dominio de desarrollo    
];

// Comprobar si el origen de la solicitud está en la lista de permitidos.
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    // Permitir el origen específico.
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    // Opcional: Si no está en la lista, podrías no enviar la cabecera o enviar una específica.
    // Para mayor seguridad, si no es un origen conocido, podrías abortar.
    // Por ahora, si no coincide, el navegador lo bloqueará de todos modos.
}

// Métodos HTTP permitidos (GET, POST, etc.).
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Cabeceras HTTP permitidas en la solicitud.
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Last-Event-ID");

// Permitir el envío de cookies o cabeceras de autorización si es necesario.
header("Access-Control-Allow-Credentials: true");

// Tiempo máximo (en segundos) que el resultado de una solicitud pre-vuelo (OPTIONS) puede ser cacheado.
header("Access-Control-Max-Age: 86400");

// Manejar las solicitudes de pre-vuelo (OPTIONS)
// El navegador envía una solicitud OPTIONS antes de un POST o GET "complejo" para verificar los permisos CORS.
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
    http_response_code(204); // 204 No Content
    exit;
}
?>