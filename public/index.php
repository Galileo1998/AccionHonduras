<?php
// public/index.php
require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// 1. Capturamos la URL solicitada
$slug = isset($_GET['slug']) ? strtolower(trim($_GET['slug'])) : '';

// ==========================================
// 2. ENRUTADOR DE MÓDULOS DINÁMICOS
// ==========================================
// Si el usuario quiere ver el blog, detenemos la carga de página y llamamos al módulo de noticias.
if ($slug === 'noticia' || $slug === 'noticias') {
    require 'noticias.php';
    exit;
}

// ==========================================
// 3. CARGADOR DE PÁGINAS DEL BUILDER
// ==========================================
$page = null;

if (empty($slug) || $slug === 'inicio') {
    // Si no hay slug, buscamos si hay una página definida como "Home" en la configuración
    $stmt_home = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'home_page_id'");
    $home_id = $stmt_home->fetchColumn();
    
    if ($home_id) {
        $query = "SELECT title, content_html FROM pages WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $home_id);
    } else {
        // Respaldo por defecto si no han configurado el home
        $query = "SELECT title, content_html FROM pages WHERE slug = 'inicio' LIMIT 1";
        $stmt = $db->prepare($query);
    }
} else {
    // Buscamos la página específica por su slug
    $query = "SELECT title, content_html FROM pages WHERE slug = :slug LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':slug', $slug);
}

$stmt->execute();
$page = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Manejo de Error 404 (Si la página no existe)
if (!$page) {
    http_response_code(404);
    $page = [
        'title' => 'Página no encontrada',
        'content_html' => '
        <div style="text-align:center; padding: 120px 20px; min-height: 50vh; display:flex; flex-direction:column; align-items:center; justify-content:center;">
            <i class="fa-solid fa-compass" style="font-size: 5rem; color: #cbd5e1; margin-bottom: 25px;"></i>
            <h1 style="color: #1e293b; font-size: 3rem; font-weight: 800; margin-bottom: 15px; letter-spacing: -1px;">404</h1>
            <h2 style="color: #34859B; font-size: 1.5rem; margin-bottom: 15px;">Parece que nos perdimos</h2>
            <p style="color: #64748b; font-size: 1.1rem; margin-bottom: 35px; max-width: 500px;">La página que estás buscando no existe, ha sido movida o la URL es incorrecta.</p>
            <a href="index.php" style="background: #46B094; color: white; padding: 14px 30px; text-decoration: none; border-radius: 50px; font-weight: 700; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 20px rgba(70, 176, 148, 0.3);">
                <i class="fa-solid fa-house" style="margin-right: 8px;"></i> Volver al Inicio
            </a>
        </div>'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<body>

    <?php 
        if (file_exists('header.php')) {
            include 'header.php'; 
        } else {
            echo "<div style='background:red; color:white; padding:10px; text-align:center;'>Falta el archivo header.php</div>";
        }
    ?>

    <main>
        <?php echo $page['content_html']; ?>
    </main>

    <?php 
        if (file_exists('footer.php')) {
            include 'footer.php'; 
        } else {
            echo "<div style='background:#0f172a; color:white; padding:40px; text-align:center;'>Falta el archivo footer.php</div>";
        }
    ?>

</body>
</html>