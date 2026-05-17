<?php
// admin/sidebar.php
// Detectamos el nombre del archivo actual (ej: 'editar_pagina.php')
$current_script = basename($_SERVER['PHP_SELF']);
?>

<style>
    .sidebar { width: 280px; background: #0f172a; color: white; display: flex; flex-direction: column; flex-shrink: 0; min-height: 100vh; }
    .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 12px; }
    .sidebar-content { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; }
    .nav-link { color: #cbd5e1; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 10px 0; transition: 0.2s; font-size: 0.95rem; }
    .nav-link:hover, .nav-link.active { color: white; }
    .nav-link.active i { color: #46B094; /* Color de acento */ }
</style>
<aside class="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-layer-group" style="color: var(--ah-accent); font-size: 1.4rem;"></i>
        <span style="font-weight: 600; font-size: 1.1rem;">AH Admin Pro</span>
    </div>
    <div class="sidebar-content">
        
        <a href="index.php" class="nav-link <?php echo ($current_script == 'index.php' || $current_script == 'editar_pagina.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-lines" style="width:20px;"></i> Páginas
        </a>
        
        <a href="noticias.php" class="nav-link <?php echo ($current_script == 'noticias.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-newspaper" style="width:20px;"></i> Noticias
        </a>
        
        <a href="socios.php" class="nav-link <?php echo ($current_script == 'socios.php' || $current_script == 'proyectos.php' || $current_script == 'editar_proyecto.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-handshake" style="width:20px;"></i> Socios y Proyectos
        </a>
        <a href="cursos.php" class="nav-link <?php echo ($current_script == 'cursos.php' || $current_script == 'constructor_curso.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-graduation-cap" style="width:20px;"></i> Academia Virtual
        </a>
        <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 15px 0;"></div>
        
        <a href="apariencia.php" class="nav-link <?php echo ($current_script == 'apariencia.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-bars" style="width:20px;"></i> Menú
        </a>
        
        <a href="configuracion.php" class="nav-link <?php echo ($current_script == 'configuracion.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gears" style="width:20px;"></i> Configuración
        </a>
        
    </div>
    <div style="padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 0.9rem;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Salir</a>
    </div>
</aside>