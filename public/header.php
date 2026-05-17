<?php
// public/header.php (O el archivo que incluyes en todas tus páginas públicas)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// =======================================================
// NUEVO: EXTRAER EL MENÚ DINÁMICO DESDE LA BASE DE DATOS
// =======================================================
$menu_items = [];
try {
    // Consultamos la tabla donde apariencia.php guarda los enlaces.
    // Asumimos que se llama 'menu_items' y tiene una columna 'sort_order' o 'id' para el orden.
    $query_menu = "SELECT label, url FROM menu_items ORDER BY id ASC"; 
    
    // NOTA DE INGENIERÍA: Si en tu base de datos tu tabla se llama de otra forma 
    // (por ejemplo 'navigation' o 'menus'), solo cambia el nombre aquí arriba.
    
    $stmt_menu = $db->query($query_menu);
    if ($stmt_menu) {
        $menu_items = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error al cargar el menú dinámico: " . $e->getMessage());
    // El arreglo quedará vacío y el HTML mostrará el menú de respaldo automáticamente
}
// Asegurarnos de tener la conexión a la BD para consultar las imágenes
require_once __DIR__ . '/../config/Database.php';
$database = new Database();
$db = $database->getConnection();

// 1. CONSTRUIR LA URL BASE ABSOLUTA (Fundamental para WhatsApp y Facebook)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/webah"; 
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// 2. VALORES POR DEFECTO (Globales de tu panel de Configuración)
$og_title = "Acción Honduras | Transformando el futuro";
$og_description = "Alianzas estratégicas que multiplican el impacto y transforman el futuro de las comunidades vulnerables en Honduras.";
// Aquí pones la ruta de la imagen de portada que subes en tu panel de "Configuraciones"
$og_image = $base_url . "/uploads/images/portada_default.jpg"; 

// 3. LÓGICA DINÁMICA (El cerebro que detecta dónde estamos)
$current_page = basename($_SERVER['SCRIPT_NAME']);
$current_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Forzar el slug de inicio si estamos en la raíz
if ($current_page == 'index.php' && empty($current_slug)) {
    // ⚠️ ATENCIÓN: Si el slug de tu página principal en el panel de control no es "inicio", 
    // cámbialo aquí abajo (ej: 'home', 'portada', 'index')
    $current_slug = 'inicio'; 
}

$debug_seo = "Buscando slug: " . ($current_slug ?: 'Ninguno');

if (!empty($current_slug)) {
    // Quitamos la restricción de "status='published'" temporalmente por si está como borrador
    $stmt_og = $db->prepare("SELECT title, meta_description, meta_image FROM pages WHERE slug = :slug LIMIT 1");
    $stmt_og->execute(['slug' => $current_slug]);
    $page_og = $stmt_og->fetch(PDO::FETCH_ASSOC);
    
    if ($page_og) {
        $debug_seo .= " | ¡Página encontrada en BD!";
        $og_title = $page_og['title'] . " | Acción Honduras";
        
        if (!empty($page_og['meta_description'])) {
            $og_description = $page_og['meta_description'];
            $debug_seo .= " | Descripción OK";
        } else {
            $debug_seo .= " | ⚠️ Descripción vacía en BD";
        }
        
        if (!empty($page_og['meta_image'])) {
            $clean_page_img = ltrim($page_og['meta_image'], '/');
            $og_image = $base_url . "/" . $clean_page_img;
            $debug_seo .= " | Imagen OK";
        } else {
            $debug_seo .= " | ⚠️ Imagen vacía en BD";
        }
    } else {
        $debug_seo .= " | ❌ ERROR: La página no existe en la base de datos.";
    }
} elseif ($current_page == 'noticia.php' && isset($_GET['id'])) {
    // ... [Tu código de noticias sigue igual] ...
    // ...
    // ... [resto del código de noticia] ...
    // Si es el mapa de un socio, halamos su logo y descripción
    $stmt_og = $db->prepare("SELECT name, description, logo_url FROM partners WHERE id = :id LIMIT 1");
    $stmt_og->execute(['id' => (int)$_GET['socio_id']]);
    $partner_og = $stmt_og->fetch(PDO::FETCH_ASSOC);
    
    if ($partner_og) {
        $og_title = "Impacto y Proyectos con " . $partner_og['name'] . " | Acción Honduras";
        $og_description = !empty($partner_og['description']) ? $partner_og['description'] : "Conoce nuestras zonas de intervención junto a " . $partner_og['name'];
        if (!empty($partner_og['logo_url'])) {
            $clean_logo_path = ltrim($partner_og['logo_url'], '/');
            $og_image = $base_url . "/" . $clean_logo_path;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($og_title); ?></title>


    <meta name="description" content="<?php echo htmlspecialchars($og_description); ?>">

    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>" />
    <meta property="og:site_name" content="Acción Honduras" />

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/style.css"> 
    </head>
<body>
    <header class="main-header" style="background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1000;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <div class="header-content" style="display: flex; justify-content: space-between; align-items: center; height: 80px;">
                
                <a href="index.php" class="logo-link">
                    <img src="../uploads/images/logo.png" alt="Acción Honduras" class="logo" style="max-height: 50px;">
                </a>
                
                <nav class="main-nav">
                    <?php if (empty($menu_items)): ?>
                        <a href="index.php" style="text-decoration: none; font-weight: 600; color: #1e293b; margin-left: 30px;">Inicio</a>
                        <a href="index.php?slug=noticia" style="text-decoration: none; font-weight: 600; color: #1e293b; margin-left: 30px;">Noticias</a>
                    <?php else: ?>
                        <?php foreach($menu_items as $item): ?>
                            <?php 
                                // Lógica para saber si este enlace es el de la página actual
                                $item_slug = str_replace('?slug=', '', $item['url']);
                                $item_slug = str_replace('index.php', '', $item_slug); // Limpieza extra
                                $is_active = ($current_slug === $item_slug);
                                $color = $is_active ? '#34859B' : '#1e293b'; 
                            ?>
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                               class="<?php echo $is_active ? 'active' : ''; ?>"
                               style="text-decoration: none; font-weight: 600; color: <?php echo $color; ?>; margin-left: 30px; transition: color 0.2s;">
                                <?php echo htmlspecialchars($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </nav>

                <div class="mobile-menu-toggle" style="display: none; cursor: pointer; font-size: 1.5rem; color: #34859B;">
                    <i class="fa-solid fa-bars"></i>
                </div>

            </div>
        </div>
    </header>