<<<<<<< HEAD
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
require_once __DIR__ . '/../controllers/AuthController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo preparar la base de datos: ' . $e->getMessage(),
        'status' => 500,
        'data' => []
    ]);
    exit;
}

$controller = new AuthController($config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';

    if ($action === 'register') {
        $response = $controller->register($input);
    } elseif ($action === 'login') {
        $response = $controller->login($input);
    } else {
        $response = ['success' => false, 'message' => 'Acción no válida.', 'status' => 400, 'data' => []];
    }

    http_response_code($response['status']);
    echo json_encode($response);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []]);
=======
<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$config = require __DIR__ . '/../config/database.php';
$controller = new AuthController($config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';

    if ($action === 'register') {
        $response = $controller->register($input);
    } elseif ($action === 'login') {
        $response = $controller->login($input);
    } else {
        $response = ['success' => false, 'message' => 'Acción no válida.', 'status' => 400, 'data' => []];
    }

    http_response_code($response['status']);
    echo json_encode($response);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'status' => 405, 'data' => []]);
>>>>>>> b90c71c8d22dc91fbb18d9ef11642352875cfa4e
