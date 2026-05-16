<?php
session_start();
header('Content-Type: application/json');

// Verificar seguridad
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Validar que sea una imagen
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['error' => 'Formato no permitido. Solo JPG, PNG, GIF, WEBP.']);
        exit;
    }

    // Crear la carpeta si no existe
    $upload_dir = '../uploads/images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generar un nombre único para evitar sobreescribir
    $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "-", basename($file['name']));
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Devolvemos la ruta relativa que se guardará en el HTML
        echo json_encode(['success' => true, 'url' => '../uploads/images/' . $filename]);
    } else {
        echo json_encode(['error' => 'Error al guardar el archivo en el servidor.']);
    }
} else {
    echo json_encode(['error' => 'No se recibió ningún archivo.']);
}
?>