<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init();

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../helpers/AuthHelper.php';
    require_once __DIR__ . '/../controllers/RutaController.php';
    $config = require __DIR__ . '/../config/database.php';
    cerbero_ensure_database($config);
    AuthHelper::requireRole(['admin']);
    $controller = new RutaController($config);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $response = $controller->generatorData();
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        AuthHelper::requireCsrfToken();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $controller->create($input);
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
