<?php

require_once __DIR__ . '/../helpers/ApiHelper.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../config/bootstrap.php';

ApiHelper::init(['GET', 'OPTIONS']);

try {
    $config = require __DIR__ . '/../config/database.php';
    cerbero_ensure_database($config);
    $pdo = Database::connect($config);
    $pdo->query('SELECT 1 FROM usuario LIMIT 1');
    $response = [
        'success' => true,
        'message' => 'Cerbero y la base de datos están disponibles.',
        'status' => 200,
        'data' => ['service' => 'cerbero'],
    ];
} catch (Throwable $error) {
    $response = ApiHelper::serverError($error);
}

http_response_code($response['status']);
echo json_encode($response);
