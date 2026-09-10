<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init();

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/IncidenciaController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new IncidenciaController($config);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($action === 'todas' || $action === 'all') {
            AuthHelper::requireRole(['admin']);
            $response = $controller->listAll();
        } else {
            $user = AuthHelper::requireRole(['vecino']);
            $ci = (string) ($user['id'] ?? $user['ci'] ?? '');
            $response = $controller->listByUser($ci);
        }
    } elseif ($method === 'POST' && $action === 'estado') {
        AuthHelper::requireRole(['admin', 'cuadrilla', 'operario']);
        AuthHelper::requireCsrfToken();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $controller->updateStatus($input);
    } elseif ($method === 'POST') {
        $user = AuthHelper::requireRole(['vecino']);
        AuthHelper::requireCsrfToken();
        $ci = (string) ($user['id'] ?? $user['ci'] ?? '');
        $response = $controller->create($_POST, $_FILES['foto'] ?? [], $ci);
    } else {
        $response = [
            'success' => false,
            'message' => 'Método no permitido.',
            'status' => 405,
            'data' => []
        ];
    }
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}

http_response_code($response['status']);
echo json_encode($response);
