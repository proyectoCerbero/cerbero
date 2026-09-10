<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init(['GET', 'OPTIONS']);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

$config = require __DIR__ . '/../config/database.php';

try {
    cerbero_ensure_database($config);
    $controller = new DashboardController($config);
    $response = $controller->metrics();
} catch (Throwable $e) {
    $response = ApiHelper::serverError($e);
}

http_response_code($response['status']);
echo json_encode($response);
