<?php
session_start();
require_once '../config/Database.php';
require_once '../classes/Auth.php';

// Si ya está logueado, lo mandamos directo al panel
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. PRIMER FILTRO: ¿Está bloqueado por fuerza bruta?
    if ($auth->isIpBlocked()) {
        $msg = "<div class='alert error'>Por motivos de seguridad, tu acceso ha sido bloqueado temporalmente por demasiados intentos fallidos. Intenta nuevamente en 15 minutos.</div>";
    } else {
        // Si no está bloqueado, procedemos normal
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // (Aquí iría tu código normal que verifica si el correo existe en la base de datos)
        $query = "SELECT id, name, password, role FROM users WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificamos la contraseña
        if ($user && password_verify($password, $user['password'])) {
            // ¡LOGIN EXITOSO!
            
            // 2. Limpiamos cualquier registro de fallos anteriores
            $auth->resetLoginAttempts();
            
            // ... (Creas la sesión $_SESSION['user_id'] etc y rediriges a index.php) ...
            
        } else {
            // ¡LOGIN FALLIDO!
            
            // 3. Registramos el fallo para sumar a la cuenta
            $auth->recordFailedLogin($email);
            
            $msg = "<div class='alert error'>Correo o contraseña incorrectos.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso CMS - Acción Honduras</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }
        .login-box h2 {
            color: #466094; /* ah-primary-blue */
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Para que el padding no desborde */
        }
        .btn-login {
            background-color: #34859b; /* ah-secondary-teal */
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #266b7d;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Acceso al Sistema</h2>
    
    <?php if(!empty($error_msg)): ?>
        <div class="error"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>Correo Electrónico:</label>
            <input type="email" name="email" required placeholder="admin@accionhonduras.org">
        </div>
        <div class="form-group">
            <label>Contraseña:</label>
            <input type="password" name="password" required placeholder="Tu contraseña">
        </div>
        <button type="submit" class="btn-login">Entrar</button>
    </form>
</div>

</body>
</html>