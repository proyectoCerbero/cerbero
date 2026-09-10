<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init();

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../helpers/AuthHelper.php';
    require_once __DIR__ . '/../controllers/UsuarioController.php';

    $config = require __DIR__ . '/../config/database.php';

    cerbero_ensure_database($config);
    $controller = new UsuarioController($config);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Listar usuarios es información sensible: solo el administrador municipal puede verla.
    $actor = AuthHelper::requireRole(['admin']);

    if ($method === 'GET') {
        $response = $controller->list();
    } elseif ($method === 'POST') {
        AuthHelper::requireCsrfToken();
        $action = $_GET['action'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'estado') {
            $response = $controller->updateEstado($input);
        } elseif ($action === 'rol') {
            $response = $controller->updateRol($input);
        } elseif ($action === 'update' || $action === 'editar') {
            $response = $controller->update($input);
        } elseif ($action === 'delete') {
            $response = $controller->delete($input, $actor);
        } elseif ($action === 'create') {
            $response = $controller->create($input);
        } else {
            $response = ['success' => false, 'message' => 'Acción no válida.', 'status' => 400, 'data' => []];
        }
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $e) {
    $response = ApiHelper::serverError($e, ['usuarios' => []]);
}

http_response_code($response['status'] ?? 200);
echo json_encode($response);
