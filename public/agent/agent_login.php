<?php
session_start();

// --- ESTE ES EL CAMBIO CLAVE ---
// Debes incluir explícitamente el archivo donde se define la clase Database.
// La ruta que uses aquí dependerá de la ubicación de tu archivo Database.php
// en relación con este script de login.php.
// Por ejemplo, si login.php está en 'public_html/' y Database.php está en 'src/SCI/Chat/Core/Config/Database.php',
// y 'src' está al mismo nivel que 'public_html', la ruta sería:
require_once __DIR__ . '/../../src/Core/Config/Database.php';

// Ahora que el archivo ha sido incluido, puedes usar el namespace y la clase.
use SCI\Chat\Core\Config\Database;

$login_error = '';

// Si el agente ya está logueado, redirigir al dashboard
if (isset($_SESSION['agent_id'])) {
    header("Location: ./");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $login_error = "Por favor, ingrese email y contraseña.";
    } else {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);

        $conn = null; // Inicializa $conn a null
        try {
            // Intenta obtener la conexión a la base de datos
            $conn = Database::getConnection();

            $sql = "SELECT id, name, email, password_hash, profile_picture_path, is_active FROM agents WHERE email = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $email);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows == 1) {
                        $agent = $result->fetch_assoc();
                        if ($agent['is_active']) {
                            if (password_verify($password, $agent['password_hash'])) {
                                // Contraseña correcta, iniciar sesión
                                $_SESSION['agent_id'] = $agent['id'];
                                $_SESSION['agent_name'] = $agent['name'];
                                $_SESSION['agent_email'] = $agent['email'];
                                $_SESSION['agent_profile_picture'] = $agent['profile_picture_path'];
                                // Redirigir al dashboard
                                header("Location: ./");
                                exit;
                            } else {
                                $login_error = "La contraseña ingresada no es válida.";
                            }
                        } else {
                            $login_error = "Esta cuenta de agente está desactivada.";
                        }
                    } else {
                        $login_error = "No se encontró ninguna cuenta con ese email.";
                    }
                } else {
                    $login_error = "Oops! Algo salió mal. Por favor, inténtelo de nuevo más tarde.";
                    error_log("Error en login execute: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $login_error = "Oops! Error al preparar la consulta. Por favor, inténtelo de nuevo.";
                error_log("Error en login prepare: " . $conn->error);
            }
        } catch (Exception $e) {
            // Captura la excepción lanzada por Database::getConnection()
            $login_error = $e->getMessage();
            error_log("Error de conexión/DB en login.php: " . $e->getMessage());
        } finally {
            // Asegura que la conexión se cierre si se abrió, incluso si ocurre un error.
            if ($conn) {
                Database::closeConnection();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login de Agente - SCI Chat</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 350px; text-align: center; }
        .login-container h1 { color: #333; margin-bottom: 25px; font-size: 1.8em; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: bold; }
        .form-group input[type="email"], .form-group input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .btn-login { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; width: 100%; transition: background-color 0.3s ease; }
        .btn-login:hover { background-color: #0056b3; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9em; }
        .logo { max-width: 100px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="images/logos/sci-logo-icono.png" class="logo">
        <?php
            if(!empty($login_error)){
                echo '<div class="error-message">' . htmlspecialchars($login_error) . '</div>';
            }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="email">e-mail</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <input type="submit" class="btn-login" value="Ingresar">
            </div>
        </form>
    </div>
</body>
</html>