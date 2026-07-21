<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

$config = require __DIR__ . '/../config/database.php';
$model = new UserModel($config);

$email = 'debug_' . time() . '@example.com';
$user = $model->create([
    'nombre' => 'Debug',
    'apellido' => 'User',
    'cedula' => '999',
    'email' => $email,
    'password' => 'abc123',
    'role' => 'vecino'
]);

$found = $model->findByEmail($email);

echo json_encode([
    'created' => $user,
    'found' => $found
], JSON_PRETTY_PRINT);
