<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../controllers/CamionController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new CamionController($config);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $response = $controller->show((int) $_GET['id']);
        } else {
            $response = $controller->list();
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $controller->create($input);
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $e) {
    $response = [
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage(),
        'status' => 500,
        'data' => []
    ];
}

http_response_code($response['status']);
echo json_encode($response);
