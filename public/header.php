<?php
// Asegurarnos de tener la conexión a la base de datos (por si se llama directamente)
if (!isset($db)) {
    require_once '../config/Database.php';
    $database = new Database();
    $db = $database->getConnection();
}

// Capturar el slug actual para saber en qué página estamos (sirve para marcar el menú activo)
$current_slug = $_GET['slug'] ?? 'inicio';

// Consultar el menú dinámico desde la base de datos
$menu_items = [];
try {
    // Busca los enlaces y los ordena por su posición
    $query = "SELECT label, url FROM menu_items ORDER BY position ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la tabla menu_items aún no existe, ignoramos el error para que cargue el menú por defecto
}
?>
    <header class="main-header" style="background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1000;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <div class="header-content" style="display: flex; justify-content: space-between; align-items: center; height: 80px;">
                
                <a href="index.php" class="logo-link">
                    <img src="/webah/uploads/images/logo.png" alt="Acción Honduras" class="logo" style="max-height: 50px;">
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