<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init();

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/ContenedorController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new ContenedorController($config);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = strtolower(trim((string) ($_GET['action'] ?? '')));

    if ($method === 'GET') {
        if ($action === 'historial') {
            AuthHelper::requireRole(['admin', 'cuadrilla']);
            $response = $controller->history((int) ($_GET['id_contenedor'] ?? $_GET['id'] ?? 0));
        } else {
            AuthHelper::requireRole(['admin', 'cuadrilla', 'vecino']);
            $response = $controller->list();
        }
    } elseif ($method === 'POST') {
        // Solo el municipio y las cuadrillas de recolección gestionan contenedores.
        $actor = AuthHelper::requireRole(['admin', 'cuadrilla']);
        AuthHelper::requireCsrfToken();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'delete') {
            AuthHelper::requireRole(['admin']);
            $response = $controller->delete($input);
        } elseif ($action === 'limpiar' || $action === 'clean') {
            $response = $controller->clean($input, $actor);
        } elseif ($action === 'update' || $action === 'editar') {
            $response = $controller->update($input, $actor);
        } else {
            $response = $controller->create($input, $actor);
        }
    } else {
        $response = ['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []];
    }
} catch (Throwable $e) {
    $response = ApiHelper::serverError($e);
}

http_response_code($response['status']);
echo json_encode($response);
