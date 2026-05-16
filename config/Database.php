<?php
// ========================================================================
// 1. BLINDAJE GLOBAL DE SESIONES (Seguridad contra secuestro y XSS)
// ========================================================================

// Solo intentamos modificar la configuración si la sesión NO ha sido iniciada aún.
if (session_status() === PHP_SESSION_NONE) {
    // Evita que JavaScript malicioso pueda leer la cookie de sesión (Bloquea ataques XSS)
    ini_set('session.cookie_httponly', 1);

    // Obliga a PHP a usar solo cookies para el ID de sesión (Evita fijación de sesión por URL)
    ini_set('session.use_only_cookies', 1);

    // Previene que el navegador envíe la cookie en peticiones cruzadas (Mitiga ataques CSRF)
    ini_set('session.cookie_samesite', 'Lax'); 
}

// ========================================================================
// 1.5 CABECERAS DE SEGURIDAD (Prevención XSS y Clickjacking)
// ========================================================================

if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
    // Evita que tu sitio sea incrustado en un <iframe> (Ataques de Clickjacking)
    header("X-Frame-Options: SAMEORIGIN");
    
    // Evita que el navegador intente adivinar el tipo de archivo (MIME Sniffing)
    header("X-Content-Type-Options: nosniff");
    
    // Filtro contra Cross-Site Scripting (XSS) en navegadores antiguos
    header("X-XSS-Protection: 1; mode=block");
    
    // Content Security Policy (CSP): La regla suprema. 
    // Le dice al navegador que solo cargue recursos de tu dominio, de Google Fonts, FontAwesome y Unsplash.
    // Bloquea cualquier script de un servidor hacker externo.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://images.unsplash.com https://*.unsplash.com;");
}
// ========================================================================
// 2. CLASE DE BASE DE DATOS PROTEGIDA
// ========================================================================
class Database {
    private $host = "localhost";
    private $db_name = "accion_honduras";
    private $username = "root";
    private $password = "1998"; 
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            
            // Modo de errores estricto para manejo interno
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // DEFENSA SQL AVANZADA: Desactiva la emulación de consultas.
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
            
            $this->conn->exec("set names utf8mb4");
            
        } catch(PDOException $exception) {
            error_log("Error crítico de BD: " . $exception->getMessage());
            die("Error de conexión al sistema. Por favor, intente más tarde.");
        }
        return $this->conn;
    }
}
?>