<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new DashboardController($config);
    $response = $controller->metrics();
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
