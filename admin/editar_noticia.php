<?php
session_start();
require_once '../config/Database.php';
require_once '../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireLogin();

// Variables por defecto
$news_id = $_GET['id'] ?? "";
$title = "";
$slug = "";
$excerpt = "";
$content_html = "\n<p>Inicia tu redacción...</p>";
$cover_image = "";
$status = "published";

// Si estamos editando, cargamos los datos
if (is_numeric($news_id)) {
    $stmt = $db->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $news_id);
    $stmt->execute();
    $n = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($n) {
        $title = $n['title'];
        $slug = $n['slug'];
        $excerpt = $n['excerpt'];
        $content_html = $n['content_html'];
        $cover_image = $n['cover_image'];
        $status = $n['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Redactar Noticia | AH Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Fira+Code&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ah-primary: #34859B; --bg: #f8fafc; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* Top Bar */
        .top-bar { background: white; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); z-index: 100; }
        .btn { padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; border: none; font-size: 0.9rem; }
        .btn-save { background: var(--ah-primary); color: white; }
        
        .main-layout { display: flex; flex-grow: 1; overflow: hidden; }
        
        /* Panel Lateral de Ajustes (Formulario) */
        .sidebar-settings { width: 350px; background: white; border-right: 1px solid var(--border); padding: 25px; overflow-y: auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-family: inherit; font-size: 0.9rem; }
        
        .cover-preview { width: 100%; height: 150px; background: #f1f5f9; border-radius: 8px; margin-top: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 2px dashed #cbd5e1; cursor: pointer; }
        .cover-preview img { width: 100%; height: 100%; object-fit: cover; }

        /* Panel del Editor Visual */
        .editor-container { flex-grow: 1; display: flex; flex-direction: column; background: #e2e8f0; }
        .toolbar-top { background: #f1f5f9; padding: 10px; border-bottom: 1px solid var(--border); display: flex; gap: 10px; }
        .editor-frame { flex-grow: 1; width: 100%; border: none; background: white; }
    </style>
</head>
<body>

    <div class="top-bar">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="noticias.php" style="color:#64748b;"><i class="fa-solid fa-arrow-left"></i></a>
            <h2 style="font-size:1.1rem; color:#1e293b;">Redactor de Noticias</h2>
        </div>
        <button class="btn btn-save" onclick="guardarNoticia()" id="btn-save">
            <i class="fa-solid fa-paper-plane"></i> Guardar y Publicar
        </button>
    </div>

    <div class="main-layout">
        <aside class="sidebar-settings">
            <input type="hidden" id="news-id" value="<?php echo $news_id; ?>">
            
            <div class="form-group">
                <label>Título de la Noticia</label>
                <input type="text" id="news-title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" oninput="generateSlug(this.value)">
            </div>

            <div class="form-group">
                <label>Slug (URL)</label>
                <input type="text" id="news-slug" class="form-control" value="<?php echo htmlspecialchars($slug); ?>">
            </div>

            <div class="form-group">
                <label>Resumen Corto (Excerpt)</label>
                <textarea id="news-excerpt" class="form-control" rows="3" style="resize:none;"><?php echo htmlspecialchars($excerpt); ?></textarea>
            </div>

            <div class="form-group">
                <label>Imagen de Portada</label>
                <div class="cover-preview" onclick="document.getElementById('cover-uploader').click()">
                    <?php if($cover_image): ?>
                        <img src="<?php echo $cover_image; ?>" id="img-preview">
                    <?php else: ?>
                        <div id="placeholder"><i class="fa-solid fa-cloud-arrow-up"></i> Subir Portada</div>
                    <?php endif; ?>
                </div>
                <input type="file" id="cover-uploader" style="display:none" accept="image/*" onchange="uploadCover(this)">
                <input type="hidden" id="news-cover" value="<?php echo $cover_image; ?>">
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select id="news-status" class="form-control">
                    <option value="published" <?php if($status=='published') echo 'selected'; ?>>Publicado</option>
                    <option value="draft" <?php if($status=='draft') echo 'selected'; ?>>Borrador</option>
                </select>
            </div>
        </aside>

        <section class="editor-container">
            <div class="toolbar-top">
                <button class="btn" style="padding:5px 10px; font-size:0.75rem;" onclick="insertB('text')">+ Párrafo</button>
                <button class="btn" style="padding:5px 10px; font-size:0.75rem;" onclick="insertB('img')">+ Imagen</button>
                <div style="flex-grow:1"></div>
                <button class="btn" style="padding:5px 10px; font-size:0.75rem; background:#1e293b; color:white;" onclick="toggleCode()"><i class="fa-solid fa-code"></i> Ver Código</button>
            </div>
            <iframe id="editor-visual" class="editor-frame"></iframe>
            <textarea id="code-editor" style="display:none;" class="editor-frame"><?php echo htmlspecialchars($content_html); ?></textarea>
        </section>
    </div>

    <input type="file" id="media-uploader" style="display:none" accept="image/*">

    <script>
       // Imprimimos el token desde PHP para que JS pueda usarlo
        const CSRF_TOKEN = "<?php echo Auth::generateCSRF(); ?>";

        const visual = document.getElementById('editor-visual');
        const code = document.getElementById('code-editor');
        let upId = null;

        // 1. Inyectar motor visual en el iframe
        function updateVisual() {
            const htmlContent = code.value;
            const doc = `
                <html>
                <head>
                    <link rel="stylesheet" href="../public/css/style.css">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <style>
                        body { padding: 40px; font-family: 'Inter', sans-serif; line-height: 1.8; color: #334155; }
                        [contenteditable="true"]:hover { outline: 2px dashed #34859B; }
                        img { max-width: 100%; border-radius: 8px; margin: 20px 0; cursor: pointer; }
                    </style>
                </head>
                <body contenteditable="true">
                    ${htmlContent}
                    <script>
                        document.body.oninput = () => {
                            window.parent.updateCode(document.body.innerHTML);
                        };
                        document.querySelectorAll('img').forEach(img => {
                            img.ondblclick = () => {
                                if(!img.id) img.id = 'img-' + Date.now();
                                window.parent.triggerMedia(img.id);
                            };
                        });
                    <\/script>
                </body>
                </html>
            `;
            visual.srcdoc = doc;
        }

        window.updateCode = (h) => { code.value = h; };

        // 2. Subida de Portada
        async function uploadCover(input) {
            if(!input.files[0]) return;
            const fd = new FormData();
            fd.append('file', input.files[0]);
            
            // Si el API upload_media también requiere CSRF en el futuro, se enviaría aquí:
            // fd.append('csrf_token', CSRF_TOKEN); 

            const res = await fetch('../api/upload_media.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                document.getElementById('news-cover').value = data.url;
                document.querySelector('.cover-preview').innerHTML = `<img src="${data.url}">`;
            } else {
                alert("Error al subir portada: " + (data.error || 'Desconocido'));
            }
        }

        // 3. Subida de Imágenes dentro del contenido
        window.triggerMedia = (id) => { upId = id; document.getElementById('media-uploader').click(); };
        document.getElementById('media-uploader').onchange = async (e) => {
            if(!e.target.files[0]) return;
            const fd = new FormData(); 
            fd.append('file', e.target.files[0]);
            
            const res = await fetch('../api/upload_media.php', { method: 'POST', body: fd });
            const d = await res.json();
            if(d.success) {
                const img = visual.contentWindow.document.getElementById(upId);
                if(img) img.src = d.url;
                updateCode(visual.contentWindow.document.body.innerHTML);
            } else {
                alert("Error al subir imagen: " + (d.error || 'Desconocido'));
            }
        };

        // 4. Utilidades
        function generateSlug(t) {
            const s = t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            document.getElementById('news-slug').value = s;
        }

        function insertB(t) {
            if(t === 'text') code.value += `\n<h2>Subtítulo</h2><p>Nuevo párrafo para la noticia...</p>`;
            if(t === 'img') code.value += `\n<img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?q=80&w=800">`;
            updateVisual();
        }

        function toggleCode() {
            const isVisible = code.style.display === 'block';
            code.style.display = isVisible ? 'none' : 'block';
            visual.style.display = isVisible ? 'block' : 'none';
            if(isVisible) updateVisual();
        }

        // 5. GUARDAR NOTICIA (API) - AHORA CON CSRF INCORPORADO
        async function guardarNoticia() {
            const btn = document.getElementById('btn-save');
            
            // Creamos el paquete de datos, inyectando el CSRF_TOKEN global
            const payload = {
                id: document.getElementById('news-id').value,
                title: document.getElementById('news-title').value,
                slug: document.getElementById('news-slug').value,
                excerpt: document.getElementById('news-excerpt').value,
                cover_image: document.getElementById('news-cover').value,
                content_html: code.value,
                status: document.getElementById('news-status').value,
                csrf_token: CSRF_TOKEN // BARRERA DE SEGURIDAD INYECTADA
            };

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
            
            try {
                const res = await fetch('../api/save_news.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                
                if(data.success) {
                    alert("Noticia guardada con éxito.");
                    window.location.href = 'noticias.php';
                } else {
                    alert("Error: " + data.error);
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Guardar y Publicar';
                }
            } catch (error) {
                console.error("Error en la petición:", error);
                alert("Error de conexión. Revisa la consola para más detalles.");
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Guardar y Publicar';
            }
        }

        window.onload = updateVisual;
    </script>
</body>
</html>