<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
ApiHelper::init(['GET', 'OPTIONS']);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../controllers/ReporteController.php';
$config = require __DIR__ . '/../config/database.php';
try {
    cerbero_ensure_database($config);
    AuthHelper::requireRole(['admin']);
    $response = (new ReporteController($config))->incidencias($_GET);
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}
http_response_code($response['status']);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
