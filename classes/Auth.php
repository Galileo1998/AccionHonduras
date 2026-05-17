<?php
class Auth {
    private $conn;
    private $table_name = "users";
    
    // 1. Declaramos la propiedad para guardar la conexión
    private $db;

    // 2. CONSTRUCTOR: Recibe la conexión a la base de datos cuando hacemos "new Auth($db)"
    public function __construct($db_connection = null) {
        $this->db = $db_connection;
    }

    // =======================================================
    // FUNCIONES DE SESIÓN NORMALES
    // =======================================================
    // ... (Aquí siguen las funciones que ya tienes: generateCSRF, checkCSRF, isIpBlocked, etc.)
public function login($email, $password) {
        // Consulta preparada para evitar SQL Injection
        $query = "SELECT id, name, password, role FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        
        // 🛠️ CORRECCIÓN AQUÍ: Usamos $this->db
        $stmt = $this->db->prepare($query);
        
        // Limpiar el email
        $email = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar si la contraseña ingresada coincide con el Hash de la BD
            if(password_verify($password, $row['password'])) {
                // Prevenir ataques de fijación de sesión (Session Fixation)
                session_regenerate_id(true); 
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['logged_in'] = true;
                
                // 🛠️ Limpiamos los intentos fallidos al tener éxito
                $this->resetLoginAttempts();
                
                return true;
            } else {
                // 🛠️ Registramos el fallo si la contraseña es incorrecta
                $this->recordFailedLogin($email);
            }
        } else {
            // 🛠️ Registramos el fallo si el correo no existe
            $this->recordFailedLogin($email);
        }
        return false;
    }

    // Método para verificar en cada archivo del administrador si hay sesión activa
    public function requireLogin() {
        if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header("Location: login.php");
            exit;
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    // =======================================================
    // SEGURIDAD CSRF (Falsificación de Petición)
    // =======================================================
    
    // 1. Genera un token único y lo guarda en la sesión
    public static function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            // Creamos una cadena de 64 caracteres aleatorios indescifrables
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // 2. Compara el token que llega del formulario con el de la sesión
    public static function checkCSRF($post_token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $post_token)) {
            // Si no coinciden, detenemos el sistema inmediatamente (Posible ataque detectado)
            http_response_code(403);
            die("
                <div style='background:#fee2e2; color:#991b1b; padding:20px; font-family:sans-serif; text-align:center; border:2px solid #ef4444; border-radius:8px; max-width:600px; margin:50px auto;'>
                    <h2 style='margin-top:0;'><i class='fa-solid fa-shield-halved'></i> Alerta de Seguridad (CSRF)</h2>
                    <p>La petición ha sido bloqueada. El token de seguridad es inválido, ha expirado, o proviene de una fuente no autorizada.</p>
                    <a href='index.php' style='color:#991b1b; text-decoration:underline;'>Volver de forma segura</a>
                </div>
            ");
        }
        return true;
    }

    // =======================================================
    // SEGURIDAD CONTRA FUERZA BRUTA (Login)
    // =======================================================
    
    // 1. Verifica si la IP actual está bloqueada
    public function isIpBlocked() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "SELECT attempt_count, last_attempt FROM login_attempts WHERE ip_address = :ip LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['ip' => $ip]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fallos = (int)$row['attempt_count'];
            $ultimo_intento = strtotime($row['last_attempt']);
            $tiempo_actual = time();
            
            // Si tiene 5 o más fallos, verificamos el tiempo
            if ($fallos >= 5) {
                $tiempo_transcurrido = $tiempo_actual - $ultimo_intento;
                // Si han pasado menos de 15 minutos (900 segundos), sigue bloqueado
                if ($tiempo_transcurrido < 900) {
                    return true; 
                } else {
                    // Ya pasó el castigo de 15 minutos, reseteamos sus fallos
                    $this->resetLoginAttempts();
                    return false;
                }
            }
        }
        return false;
    }

    // 2. Registra un intento fallido
    public function recordFailedLogin($email) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $fecha_actual = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO login_attempts (ip_address, email_attempt, last_attempt, attempt_count) 
                  VALUES (:ip, :email, :fecha, 1) 
                  ON DUPLICATE KEY UPDATE 
                  email_attempt = :email, 
                  last_attempt = :fecha, 
                  attempt_count = attempt_count + 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['ip' => $ip, 'email' => $email, 'fecha' => $fecha_actual]);
    }

    // 3. Limpia el historial de fallos cuando loguea exitosamente
    public function resetLoginAttempts() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "DELETE FROM login_attempts WHERE ip_address = :ip";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['ip' => $ip]);
    }

    // =======================================================
    // SANEAMIENTO EXTREMO DE SUBIDAS (Anti-Malware)
    // =======================================================
    
    public static function secureImageUpload($file_array, $upload_dir) {
        // 1. Validar que no haya errores de transmisión PHP
        if ($file_array['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Error en la transmisión del archivo.'];
        }

        // 2. Validar peso máximo (Ejemplo: 2MB = 2097152 bytes)
        // Evita ataques DDoS por saturación de disco duro
        if ($file_array['size'] > 2097152) {
            return ['success' => false, 'error' => 'La imagen es demasiado pesada. Máximo 2MB permitidos.'];
        }

        // 3. Inspección profunda de MIME Type (Lee el ADN del archivo, no la extensión)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file_array['tmp_name']);
        
        // Diccionario estricto de formatos permitidos
        $allowed_mimes = [
            'image/jpeg' => '.jpg', 
            'image/png' => '.png', 
            'image/webp' => '.webp'
        ];
        
        if (!array_key_exists($mime_type, $allowed_mimes)) {
            return ['success' => false, 'error' => 'Alerta de Seguridad: El formato del archivo es inválido o está corrupto.'];
        }

        // 4. Destrucción del nombre original y generación de Hash
        $extension = $allowed_mimes[$mime_type];
        // Crea un nombre como: a1b2c3d4e5_1715875200.jpg
        $new_filename = bin2hex(random_bytes(8)) . '_' . time() . $extension;
        $destination = $upload_dir . $new_filename;

        // 5. Traslado seguro
        if (move_uploaded_file($file_array['tmp_name'], $destination)) {
            return ['success' => true, 'filename' => $new_filename, 'path' => $destination];
        } else {
            return ['success' => false, 'error' => 'Error de permisos al guardar en el servidor.'];
        }
    }
}
?>