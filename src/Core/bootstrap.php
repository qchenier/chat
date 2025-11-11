<?php
/**
 * bootstrap.php
 * Registra el autoloader PSR-4 para el namespace SCI\Chat.
 */
spl_autoload_register(function ($className) {
    
    // El prefijo del namespace de nuestro proyecto
    $namespace_prefix = 'SCI\\Chat\\';

    // Verificar si la clase que se intenta cargar pertenece a nuestro proyecto
    if (strpos($className, $namespace_prefix) !== 0) {
        return; // No es nuestra, que otro autoloader se encargue.
    }

    // El directorio base para nuestro namespace 'SCI\Chat\' es la carpeta '/src/'
    // Construimos la ruta absoluta a la carpeta /src/ desde la ubicación de este archivo (bootstrap.php)
    // __DIR__ es '/.../sci/src/Core'
    // dirname(__DIR__) es '/.../sci/src'
    $base_dir = dirname(__DIR__); 

    // Obtenemos la parte de la clase que viene después del prefijo
    // Ej: para 'SCI\Chat\Core\Models\Department', $relative_class será 'Core\Models\Department'
    $relative_class = substr($className, strlen($namespace_prefix));

    // Reemplazamos los separadores de namespace por separadores de directorio y añadimos .php
    // Ej: 'Core\Models\Department' -> '/Core/Models/Department.php'
    $file = $base_dir . '/' . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, lo incluimos. Si no, logueamos un error.
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Autoloader: No se pudo cargar la clase '{$className}'. Se buscó en la ruta: '{$file}'");
    }
});