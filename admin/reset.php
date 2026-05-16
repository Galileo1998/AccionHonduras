<?php
require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Generamos el hash seguro de la contraseña 'admin123'
$nueva_clave = password_hash('admin123', PASSWORD_DEFAULT);

// Actualizamos al administrador en la base de datos
$query = "UPDATE users SET password = :password WHERE email = 'admin@accionhonduras.org'";
$stmt = $db->prepare($query);
$stmt->bindParam(':password', $nueva_clave);

if($stmt->execute()) {
    echo "<h2>¡Éxito! La contraseña se ha reiniciado.</h2>";
    echo "<p>Tu correo es: <b>admin@accionhonduras.org</b></p>";
    echo "<p>Tu contraseña es: <b>admin123</b></p>";
    echo "<a href='login.php'>Volver al Login</a>";
} else {
    echo "Hubo un error al actualizar.";
}
?>