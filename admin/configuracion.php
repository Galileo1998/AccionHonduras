<?php
session_start();
require_once '../config/Database.php';
require_once '../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireLogin();

// Generamos el token de seguridad para inyectarlo en los formularios de esta página
$csrf_token = Auth::generateCSRF();
$msg = "";

// ==========================================
// 1. PROCESAR GUARDADO DE CONFIGURACIONES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_settings') {
    
    // 🛡️ BARRERA DE SEGURIDAD CSRF ACTIVA
    Auth::checkCSRF($_POST['csrf_token'] ?? '');

    unset($_POST['action']);
    unset($_POST['csrf_token']); // Lo quitamos para que no intente guardarlo en BD
    
    foreach ($_POST as $key => $value) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    }
    $msg = "<div class='alert success'><i class='fa-solid fa-shield-check'></i> Configuraciones actualizadas de forma segura.</div>";
}

// ==========================================
// 2. PROCESAR CREACIÓN DE USUARIOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    
    // 🛡️ BARRERA DE SEGURIDAD CSRF ACTIVA
    Auth::checkCSRF($_POST['csrf_token'] ?? '');

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute(['email' => $email]);
    if ($check->rowCount() > 0) {
        $msg = "<div class='alert error'><i class='fa-solid fa-triangle-exclamation'></i> El correo ya está registrado.</div>";
    } else {
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (:n, :e, :p, :r)");
        $stmt->execute(['n' => $name, 'e' => $email, 'p' => $password, 'r' => $role]);
        $msg = "<div class='alert success'><i class='fa-solid fa-user-plus'></i> Usuario creado exitosamente.</div>";
    }
}

// ==========================================
// 3. PROCESAR ELIMINACIÓN DE USUARIOS
// ==========================================
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    
    // 🛡️ BARRERA CSRF PARA ENLACES (Vía GET)
    Auth::checkCSRF($_GET['token'] ?? '');

    if ($_GET['delete_user'] == $_SESSION['user_id']) {
        $msg = "<div class='alert error'><i class='fa-solid fa-ban'></i> No puedes eliminar tu propia cuenta.</div>";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $_GET['delete_user']]);
        $msg = "<div class='alert success'><i class='fa-solid fa-trash'></i> Usuario eliminado.</div>";
    }
}

// ==========================================
// OBTENER DATOS PARA MOSTRAR
// ==========================================
// Configuraciones
$stmt = $db->query("SELECT * FROM settings");
$settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Usuarios
$stmt = $db->query("SELECT id, name, email, role FROM users ORDER BY id ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Páginas (Para el selector de Home Page)
$stmt_pages = $db->query("SELECT id, title FROM pages ORDER BY title ASC");
$paginas_creadas = $stmt_pages->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración | Acción Honduras Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ah-primary: #34859B; --bg: #f8fafc; --text: #1e293b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; margin: 0; min-height: 100vh; }
        
        .sidebar { width: 280px; background: #0f172a; color: white; display: flex; flex-direction: column; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 12px; }
        .sidebar-content { padding: 24px; flex-grow: 1; }
        .nav-link { color: #cbd5e1; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 10px 0; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { color: white; }
        .nav-link.active i { color: #46B094; }
        
        .main { flex-grow: 1; padding: 40px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }

        .tabs-header { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 30px; }
        .tab-btn { background: none; border: none; padding: 15px 30px; font-size: 1rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.2s; }
        .tab-btn:hover { color: var(--ah-primary); }
        .tab-btn.active { color: var(--ah-primary); border-bottom-color: var(--ah-primary); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #475569; margin-bottom: 8px; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-family: inherit; font-size: 0.95rem; }
        .btn-save { background: var(--ah-primary); color: white; border: none; padding: 12px 25px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #2c7285; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { color: #64748b; text-transform: uppercase; font-size: 0.8rem; }
        .role-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: bold; }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-editor { background: #e0f2fe; color: #0369a1; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-layer-group" style="color: #46B094; font-size: 1.4rem;"></i>
            <span style="font-weight: 600; font-size: 1.1rem;">AH Admin Pro</span>
        </div>
        <div class="sidebar-content">
            <a href="index.php" class="nav-link"><i class="fa-solid fa-file-lines" style="width:20px;"></i> Páginas</a>
            <a href="noticias.php" class="nav-link"><i class="fa-solid fa-newspaper" style="width:20px;"></i> Noticias</a>
            <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 15px 0;"></div>
            <a href="apariencia.php" class="nav-link"><i class="fa-solid fa-bars" style="width:20px;"></i> Menú</a>
            <a href="configuracion.php" class="nav-link active"><i class="fa-solid fa-gears" style="width:20px;"></i> Configuración</a>
        </div>
        <div style="padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 0.9rem;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Salir</a>
        </div>
    </aside>

    <main class="main">
        <div class="page-header">
            <h1 style="margin-bottom: 5px;">Configuración del Sistema</h1>
            <p style="color: #64748b; margin: 0;">Administra enlaces globales, estructura principal y usuarios.</p>
        </div>

        <?php echo $msg; ?>

        <div class="tabs-header">
            <button class="tab-btn active" onclick="openTab('tab-general')"><i class="fa-solid fa-globe"></i> General y Redes</button>
            <button class="tab-btn" onclick="openTab('tab-users')"><i class="fa-solid fa-users-gear"></i> Usuarios y Roles</button>
        </div>

        <div id="tab-general" class="tab-content active">
            <div class="card">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <h3 style="margin-bottom: 20px; color: var(--ah-primary);"><i class="fa-solid fa-house"></i> Estructura del Sitio</h3>
                    <div class="form-group">
                        <label>Página Principal (Home)</label>
                        <select name="home_page_id" class="form-control">
                            <option value="">-- Seleccionar Página --</option>
                            <?php foreach($paginas_creadas as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (isset($settings['home_page_id']) && $settings['home_page_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; font-size: 0.85rem; margin-top: 5px; display: block;">Esta página se cargará por defecto cuando los visitantes entren al sitio sin un enlace específico.</small>
                    </div>

                    <h3 style="margin-top: 40px; margin-bottom: 20px; color: var(--ah-primary);"><i class="fa-solid fa-building"></i> Información de la Organización</h3>
                    <div class="form-group">
                        <label>Nombre del Sitio</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Correo Electrónico de Contacto</label>
                        <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                    </div>

                    <h3 style="margin-top: 40px; margin-bottom: 20px; color: var(--ah-primary);"><i class="fa-solid fa-share-nodes"></i> Redes Sociales</h3>
                    <div class="form-group">
                        <label><i class="fa-brands fa-facebook"></i> Enlace de Facebook</label>
                        <input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-brands fa-instagram"></i> Enlace de Instagram</label>
                        <input type="url" name="social_instagram" class="form-control" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-brands fa-x-twitter"></i> Enlace de Twitter / X</label>
                        <input type="url" name="social_twitter" class="form-control" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
                </form>
            </div>
        </div>

        <div id="tab-users" class="tab-content">
            <div class="card" style="background: #f8fafc; border: 1px solid var(--border);">
                <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-user-plus"></i> Añadir Nuevo Usuario</h3>
                <form method="POST" action="" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <input type="hidden" name="action" value="add_user">
                    
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group" style="margin:0;">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Correo</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Rol</label>
                        <select name="role" class="form-control">
                            <option value="editor">Editor (Crea contenido)</option>
                            <option value="admin">Administrador (Total)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save" style="background: #46B094;">Crear</button>
                </form>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo Electrónico</th>
                            <th>Nivel de Acceso</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td style="color: #94a3b8; font-weight: bold;">#<?php echo $u['id']; ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <?php if($u['role'] == 'admin'): ?>
                                    <span class="role-badge role-admin">Administrador</span>
                                <?php else: ?>
                                    <span class="role-badge role-editor">Editor</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete_user=<?php echo $u['id']; ?>&token=<?php echo $csrf_token; ?>" onclick="return confirm('¿Eliminar usuario definitivamente?')" style="color: #ef4444; text-decoration: none;"><i class="fa-solid fa-trash"></i> Eliminar</a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.8rem;">(Tú)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>