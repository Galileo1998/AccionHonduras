<?php
require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// 1. Capturamos el slug de la URL
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    // Si no hay slug, redirigimos al listado general
    header("Location: index.php?slug=noticias");
    exit;
}

// 2. Buscamos la noticia en la base de datos
$query = "SELECT * FROM news WHERE slug = :slug AND status = 'published' LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':slug', $slug);
$stmt->execute();
$n = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$n) {
    // Si la noticia no existe o está en borrador
    http_response_code(404);
    die("<!DOCTYPE html><html lang='es'><head><title>No encontrado</title><style>body{font-family:sans-serif;text-align:center;padding:100px;color:#1e293b;}</style></head><body><h1>404</h1><p>El artículo que buscas no existe o ha sido retirado.</p><a href='index.php'>Volver al inicio</a></body></html>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($n['title']); ?> | Acción Honduras</title>
    
    <meta property="og:title" content="<?php echo htmlspecialchars($n['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($n['excerpt']); ?>">
    <?php if ($n['cover_image']): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($n['cover_image']); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos específicos para la vista del artículo */
        .single-article { max-width: 800px; margin: 0 auto; padding: 60px 20px; min-height: 70vh; }
        .back-link { color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 40px; font-weight: 600; transition: color 0.2s; }
        .back-link:hover { color: #34859B; }
        
        .article-header { text-align: center; margin-bottom: 40px; }
        .article-date { color: #46B094; font-weight: 700; margin-bottom: 15px; display: block; text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem; }
        .article-title { font-size: 2.8rem; color: #1e293b; line-height: 1.2; margin-bottom: 20px; font-weight: 800; }
        .article-cover { width: 100%; max-height: 500px; object-fit: cover; border-radius: 16px; margin-bottom: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        
        /* Formato del contenido inyectado por el editor visual */
        .article-content { line-height: 1.8; color: #334155; font-size: 1.15rem; }
        .article-content h2 { color: #1e293b; margin: 40px 0 20px 0; font-size: 2rem; }
        .article-content h3 { color: #34859B; margin: 30px 0 15px 0; font-size: 1.5rem; }
        .article-content p { margin-bottom: 25px; }
        .article-content img { max-width: 100%; height: auto; border-radius: 12px; margin: 30px 0; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .article-content a { color: #34859B; text-decoration: underline; font-weight: 600; }
        .article-content blockquote { border-left: 4px solid #46B094; padding-left: 20px; font-style: italic; color: #475569; margin: 30px 0; background: #f8fafc; padding: 20px; border-radius: 0 8px 8px 0; }
        
        .social-share { margin-top: 60px; padding-top: 40px; border-top: 1px solid #e2e8f0; text-align: center; }
        .social-share-links { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
        .share-btn { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; text-decoration: none; font-size: 1.2rem; transition: transform 0.2s; }
        .share-btn:hover { transform: translateY(-3px); }
        .bg-fb { background: #1877F2; }
        .bg-tw { background: #1DA1F2; }
        .bg-wa { background: #25D366; }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: #f8fafc;">

    <?php include 'header.php'; ?>

    <main class="single-article">
        <a href="index.php?slug=noticias" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Volver a todas las noticias
        </a>

        <header class="article-header">
            <span class="article-date"><?php echo date('d M, Y', strtotime($n['created_at'])); ?></span>
            <h1 class="article-title"><?php echo htmlspecialchars($n['title']); ?></h1>
        </header>

        <?php if ($n['cover_image']): ?>
            <img src="<?php echo htmlspecialchars($n['cover_image']); ?>" class="article-cover" alt="Portada del artículo">
        <?php endif; ?>

        <div class="article-content">
            <?php echo $n['content_html']; ?>
        </div>
        
        <div class="social-share">
            <h3 style="color: #1e293b; font-size: 1.2rem;">Compartir este artículo</h3>
            <div class="social-share-links">
                <?php $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" target="_blank" class="share-btn bg-fb"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($n['title']); ?>" target="_blank" class="share-btn bg-tw"><i class="fa-brands fa-twitter"></i></a>
                <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($n['title'] . " " . $current_url); ?>" target="_blank" class="share-btn bg-wa"><i class="fa-brands fa-whatsapp"></i></a>
            </div>
        </div>
    </main>

    <?php 
        if (file_exists('footer.php')) {
            include 'footer.php'; 
        }
    ?>

</body>
</html>