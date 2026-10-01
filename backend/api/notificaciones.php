<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init(['GET', 'POST', 'OPTIONS']);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/NotificacionController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new NotificacionController($config);
    $user = AuthHelper::getLoggedUser();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $response = $controller->listForUser($user, AuthHelper::getCurrentRole());
    } elseif ($method === 'POST') {
        $authenticated = AuthHelper::requireLogin();
        AuthHelper::requireCsrfToken();
        $response = $controller->markAllRead($authenticated);
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}

http_response_code($response['status']);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
