<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// 1. OBTENER EL CURSO POR SLUG
$slug = isset($_GET['curso']) ? trim($_GET['curso']) : '';
if (empty($slug)) {
    header("Location: academia.php");
    exit;
}

$stmt_c = $db->prepare("SELECT * FROM ah_courses WHERE slug = :slug AND status = 'published' LIMIT 1");
$stmt_c->execute(['slug' => $slug]);
$curso = $stmt_c->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    header("Location: academia.php");
    exit;
}

// 2. OBTENER MÓDULOS Y LECCIONES
$stmt_m = $db->prepare("SELECT * FROM ah_modules WHERE course_id = :cid ORDER BY sort_order ASC, id ASC");
$stmt_m->execute(['cid' => $curso['id']]);
$modulos = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

$lecciones_por_modulo = [];
$todas_las_lecciones = [];

if (!empty($modulos)) {
    $ids_modulos = implode(',', array_column($modulos, 'id'));
    $stmt_l = $db->query("SELECT * FROM ah_lessons WHERE module_id IN ($ids_modulos) ORDER BY sort_order ASC, id ASC");
    while ($row = $stmt_l->fetch(PDO::FETCH_ASSOC)) {
        $lecciones_por_modulo[$row['module_id']][] = $row;
        $todas_las_lecciones[$row['id']] = $row;
    }
}

// 3. DETERMINAR QUÉ LECCIÓN ESTAMOS VIENDO
$current_lesson_id = isset($_GET['leccion']) ? (int)$_GET['leccion'] : 0;
$leccion_actual = null;

if ($current_lesson_id > 0 && isset($todas_las_lecciones[$current_lesson_id])) {
    $leccion_actual = $todas_las_lecciones[$current_lesson_id];
} else {
    foreach ($modulos as $mod) {
        if (!empty($lecciones_por_modulo[$mod['id']])) {
            $leccion_actual = $lecciones_por_modulo[$mod['id']][0];
            $current_lesson_id = $leccion_actual['id'];
            break;
        }
    }
}

// Función auxiliar para formatear URLs embebidas de YouTube
function getYoutubeEmbedUrl($url) {
    $shortUrlRegex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
    $longUrlRegex = '/youtube.com\/((?:embed)|(?:watch))((?:\?v\=)|(?:\/))([a-zA-Z0-9_-]+)/i';
    if (preg_match($longUrlRegex, $url, $matches)) {
        $youtube_id = $matches[count($matches) - 1];
    }
    if (preg_match($shortUrlRegex, $url, $matches)) {
        $youtube_id = $matches[count($matches) - 1];
    }
    return isset($youtube_id) ? 'https://www.youtube.com/embed/' . $youtube_id : $url;
}

// Incluir cabecera global de la plataforma pública
require_once 'header.php';
?>

<style>
    body { background: #0f172a; } /* Fondo oscuro general para el enfoque cinematográfico */
    
    .aula-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        min-height: calc(100vh - 80px);
        max-width: 1600px;
        margin: 0 auto;
        background: #f8fafc;
    }

    /* ÁREA DE CONTENIDO (IZQUIERDA) */
    .aula-content {
        padding: 40px;
        background: white;
        overflow-y: auto;
    }

    .lesson-title { font-size: 2rem; color: #0f172a; font-weight: 800; margin-bottom: 10px; margin-top: 0; }
    .course-badge { display: inline-block; background: #e2e8f0; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; margin-bottom: 20px;}

    /* CONTENEDORES DE BLOQUES DINÁMICOS */
    .aula-blocks-wrapper {
        margin-top: 30px;
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .video-container {
        position: relative;
        padding-bottom: 56.25%; /* Relación de aspecto 16:9 */
        height: 0;
        overflow: hidden;
        border-radius: 12px;
        background: #0f172a;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }
    .video-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }

    .pdf-container {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #cbd5e1;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .pdf-header {
        background: #f1f5f9;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        border-bottom: 1px solid #cbd5e1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* BARRA LATERAL DEL TEMARIO (DERECHA) */
    .aula-sidebar {
        background: #f1f5f9;
        border-left: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow-y: auto;
    }

    .sidebar-header {
        padding: 20px;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .module-group {
        border-bottom: 1px solid #e2e8f0;
    }
    .module-title {
        padding: 15px 20px;
        font-weight: 700;
        color: #334155;
        font-size: 0.95rem;
        background: #f8fafc;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .lesson-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px 12px 30px;
        text-decoration: none;
        color: #475569;
        font-size: 0.9rem;
        transition: 0.2s;
        border-left: 3px solid transparent;
    }
    .lesson-link:hover {
        background: white;
        color: var(--ah-primary);
    }
    .lesson-link.active {
        background: white;
        color: var(--ah-primary);
        border-left-color: var(--ah-primary);
        font-weight: 600;
    }

    .icon-play { width: 24px; height: 24px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #64748b; flex-shrink: 0;}
    .lesson-link.active .icon-play { background: var(--ah-primary); color: white; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .aula-layout { grid-template-columns: 1fr; }
        .aula-sidebar { border-left: none; border-top: 1px solid #e2e8f0; }
        .aula-content { padding: 20px; }
    }
</style>

<div class="aula-layout">
    
    <main class="aula-content">
        <?php if(!$leccion_actual): ?>
            <div style="text-align:center; padding: 100px 20px;">
                <i class="fa-solid fa-cubes" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                <h2 style="color: #334155;">Este curso aún no tiene material cargado</h2>
                <p style="color: #64748b;">Regresa más tarde para revisar el contenido modular.</p>
            </div>
        <?php else: ?>
            
            <h1 class="lesson-title"><?php echo htmlspecialchars($leccion_actual['title']); ?></h1>
            <div class="course-badge">
                <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($curso['title']); ?>
            </div>

            <div class="aula-blocks-wrapper">
                <?php 
                // Intentar decodificar la estructura como JSON (Editor de Bloques nuevo)
                $bloques = json_decode($leccion_actual['content_html'], true);
                
                // Retrocompatibilidad si es un formato de texto o constructor anterior
                if (!is_array($bloques)) {
                    $bloques = [];
                    if ($leccion_actual['content_type'] == 'video') {
                        $bloques[] = ['type' => 'video', 'value' => $leccion_actual['media_url']];
                    } else {
                        $bloques[] = ['type' => 'text', 'value' => $leccion_actual['content_html']];
                    }
                }

                // Dibujar cada componente en la pantalla del estudiante
                foreach ($bloques as $b):
                    if (empty($b['value'])) continue;
                    
                    if ($b['type'] === 'text'): ?>
                        <div style="color: #334155; line-height: 1.8; font-size: 1.05rem; background: white; padding: 5px 0;">
                            <?php echo nl2br($b['value']); ?>
                        </div>
                    
                    <?php elseif ($b['type'] === 'video'): ?>
                        <div class="video-container">
                            <iframe src="<?php echo getYoutubeEmbedUrl($b['value']); ?>" allowfullscreen></iframe>
                        </div>

                    <?php elseif ($b['type'] === 'image'): ?>
                        <div style="text-align: center; margin: 10px 0;">
                            <img src="<?php echo htmlspecialchars($b['value']); ?>" style="max-width: 100%; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" alt="Recurso visual">
                        </div>

                    <?php elseif ($b['type'] === 'pdf'): ?>
                        <div class="pdf-container">
                            <div class="pdf-header">
                                <span><i class="fa-solid fa-file-pdf" style="color:#f43f5e; margin-right:6px;"></i> Lectura Complementaria (PDF)</span>
                                <a href="<?php echo htmlspecialchars($b['value']); ?>" target="_blank" style="color:var(--ah-primary); font-weight:bold; text-decoration:none;"><i class="fa-solid fa-download"></i> Descargar PDF</a>
                            </div>
                            <embed src="<?php echo htmlspecialchars($b['value']); ?>" type="application/pdf" width="100%" height="600px" />
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <aside class="aula-sidebar">
        <div class="sidebar-header">
            <h3 style="margin: 0; font-size: 1.1rem; color: #0f172a; font-weight: 800;">Contenido del Curso</h3>
        </div>

        <?php foreach($modulos as $mod): ?>
            <div class="module-group">
                <div class="module-title">
                    <i class="fa-solid fa-folder-open" style="color: var(--ah-accent);"></i> 
                    <?php echo htmlspecialchars($mod['title']); ?>
                </div>
                
                <?php if(!empty($lecciones_por_modulo[$mod['id']])): ?>
                    <?php foreach($lecciones_por_modulo[$mod['id']] as $lec): ?>
                        <?php $is_active = ($current_lesson_id == $lec['id']) ? 'active' : ''; ?>
                        
                        <a href="aula.php?curso=<?php echo urlencode($slug); ?>&leccion=<?php echo $lec['id']; ?>" class="lesson-link <?php echo $is_active; ?>">
                            <div class="icon-play">
                                <i class="fa-solid fa-cubes" style="font-size:0.65rem;"></i>
                            </div>
                            <?php echo htmlspecialchars($lec['title']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </aside>

</div>

</body>
</html>