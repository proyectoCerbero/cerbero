<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init(['GET', 'POST', 'OPTIONS']);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/RecoleccionController.php';

$config = require __DIR__ . '/../config/database.php';
try {
    cerbero_ensure_database($config);
    $actor = AuthHelper::requireRole(['admin', 'cuadrilla']);
    $controller = new RecoleccionController($config);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $response = $controller->data($actor, AuthHelper::getCurrentRole());
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        AuthHelper::requireCsrfToken();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $controller->create($input, $actor, AuthHelper::getCurrentRole());
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}
http_response_code($response['status']);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
