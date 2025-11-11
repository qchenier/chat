<?php
/**
 * SCI-CHAT-SYSTEM - Core de Base de Datos
 */

// Define el namespace para esta clase.
namespace SCI\Chat\Core\Config;

use mysqli;
use Exception;

class Database {
    // Declara una propiedad estática privada para almacenar la única instancia de conexión.
    private static ?mysqli $connection = null; // Usamos `?mysqli` para indicar que puede ser mysqli o null (PHP 7.1+)

    // Define las constantes de conexión a la base de datos como privadas y estáticas.
    private const DB_SERVER = '127.0.0.1';
    private const DB_USERNAME = 'root';
    private const DB_PASSWORD = '';
    private const DB_NAME = 'live_chat_db';

    // Para evitar que la clase sea instanciada directamente (parte del patrón Singleton).
    private function __construct() { }

    // Para evitar que la clase sea clonada (parte del patrón Singleton).
    private function __clone() { }

    /**
     * Obtiene y devuelve la conexión única a la base de datos.
     * Si la conexión no existe, la crea. Si hay un error, lo registra y lanza una excepción.
     *
     * @return mysqli La instancia de conexión a la base de datos.
     * @throws Exception Si no se puede establecer la conexión o configurar el charset.
     */
    public static function getConnection(): mysqli {
        // Verifica si ya existe una conexión; si es así, la devuelve.
        if (self::$connection === null) {
            try {
                // Crea una nueva conexión usando las constantes de clase.
                self::$connection = new mysqli(self::DB_SERVER, self::DB_USERNAME, self::DB_PASSWORD, self::DB_NAME);

                // Comprueba si hay errores de conexión.
                if (self::$connection->connect_error) {
                    // Registra el error en los logs del servidor para depuración.
                    error_log("Error de conexión a la BD: " . self::$connection->connect_error);
                    // Lanza una excepción para que el código que llama pueda manejar el error.
                    throw new Exception("Error de conexión a la base de datos. Por favor, inténtelo de nuevo más tarde.");
                }

                // Establece el conjunto de caracteres a utf8mb4 para soportar emojis y otros caracteres especiales.
                if (!self::$connection->set_charset("utf8mb4")) {
                    error_log("Error al establecer el charset utf8mb4: " . self::$connection->error);
                    throw new Exception("Error al configurar la codificación de la base de datos.");
                }

            } catch (Exception $e) {
                // Captura cualquier excepción durante el proceso de conexión
                // y la relanza para una gestión centralizada de errores.
                throw $e; // Relanza la excepción original o una nueva con un mensaje más genérico.
            }
        }
        // Devuelve la conexión existente o la recién creada.
        return self::$connection;
    }

    /**
     * Cierra la conexión a la base de datos si está abierta.
     * Útil para scripts de larga duración o para asegurar el cierre explícito.
     */
    public static function closeConnection(): void {
        // Asegúrate de que $connection es realmente una instancia de mysqli antes de intentar cerrarla
        if (self::$connection instanceof mysqli) {
            self::$connection->close();
            self::$connection = null; // Reinicia la conexión a null para que pueda ser reabierta si es necesario
        }
    }
}