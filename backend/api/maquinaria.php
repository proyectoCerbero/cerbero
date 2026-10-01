<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init();

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/MaquinariaController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new MaquinariaController($config);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // La maquinaria de los centros de acopio es información interna del personal.
    AuthHelper::requireRole(['admin', 'operario']);

    if ($method === 'GET') {
        $response = $controller->list();
    } elseif ($method === 'POST') {
        AuthHelper::requireCsrfToken();
        $action = $_GET['action'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'delete') {
            AuthHelper::requireRole(['admin']);
            $response = $controller->delete($input);
        } elseif ($action === 'update' || $action === 'editar') {
            $response = $controller->update($input);
        } else {
            $response = $controller->create($input);
        }
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $e) {
    $response = ApiHelper::serverError($e);
}

http_response_code($response['status']);
echo json_encode($response);
