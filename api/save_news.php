<?php
session_start();
require_once '../config/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

// 1. Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

// 2. Validar sesión
if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

// 3. 🛡️ BARRERA DE SEGURIDAD CSRF PARA API
$client_token = $data->csrf_token ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Alerta de Seguridad: Token CSRF inválido o expirado.']);
    exit;
}

if (!empty($data->title) && !empty($data->slug)) {
    try {
        // Limpieza automática del Slug
        $clean_slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($data->slug)));
        $clean_slug = trim($clean_slug, '-');

        if (!empty($data->id)) {
            $query = "UPDATE news SET title = :title, slug = :slug, excerpt = :excerpt, content_html = :content_html, cover_image = :cover_image, status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $data->id);
        } else {
            $query = "INSERT INTO news (title, slug, excerpt, content_html, cover_image, status) VALUES (:title, :slug, :excerpt, :content_html, :cover_image, :status)";
            $stmt = $db->prepare($query);
        }

        // 🛠️ CORRECCIÓN: Usamos bindValue en lugar de bindParam para permitir funciones
        $stmt->bindValue(':title', trim($data->title));
        $stmt->bindValue(':slug', $clean_slug);
        $stmt->bindValue(':excerpt', trim($data->excerpt));
        $stmt->bindValue(':content_html', $data->content_html);
        $stmt->bindValue(':cover_image', $data->cover_image);
        $stmt->bindValue(':status', $data->status);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno al guardar los datos.']);
        }
    // Cambiamos a Exception general para capturar cualquier otro error y evitar que imprima HTML
} catch(Exception $e) {
        http_response_code(500);
        // MODO DEBUG: Imprimimos el error directo a la pantalla
        echo json_encode(['error' => 'DEBUG SQL: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'El título y el enlace (slug) son obligatorios.']);
}
?>