<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init(['POST', 'GET', 'OPTIONS']);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new AuthController($config);

    $action = $_GET['action'] ?? 'login';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'register') {
        $response = $controller->register($input);
        if (!empty($response['success'])) {
            AuthHelper::login($response['data']['user']);
            $response['data']['csrf_token'] = AuthHelper::csrfToken();
        }
    } elseif ($action === 'logout') {
        AuthHelper::requireCsrfToken();
        AuthHelper::logout();
        $response = ['success' => true, 'message' => 'Sesión cerrada.', 'status' => 200, 'data' => []];
    } elseif ($action === 'me') {
        $user = AuthHelper::getLoggedUser();
        $response = [
            'success' => true,
            'message' => $user ? 'Sesión activa.' : 'Sin sesión iniciada.',
            'status' => 200,
            'data' => ['user' => $user, 'role' => AuthHelper::getCurrentRole(), 'csrf_token' => AuthHelper::csrfToken()]
        ];
    } else {
        $response = $controller->login($input);
        if (!empty($response['success'])) {
            AuthHelper::login($response['data']['user']);
            $response['data']['csrf_token'] = AuthHelper::csrfToken();
        }
    }
} catch (Throwable $e) {
    $response = ApiHelper::serverError($e);
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
