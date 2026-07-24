<<<<<<< HEAD
<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$config = require __DIR__ . '/../config/database.php';
$controller = new AuthController($config);

$email = 'prueba_' . time() . '@example.com';
$cedula = 'CI' . time();

$registerResult = $controller->register([
    'nombre' => 'Ana',
    'apellido' => 'Pérez',
    'cedula' => $cedula,
    'email' => $email,
    'password' => '123456'
]);

$loginResult = $controller->login([
    'email' => $email,
    'password' => '123456'
]);

if ($registerResult['success'] !== true || $loginResult['success'] !== true) {
    fwrite(STDERR, json_encode(['register' => $registerResult, 'login' => $loginResult], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

echo "Auth flow OK\n";
=======
<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$config = require __DIR__ . '/../config/database.php';
$controller = new AuthController($config);

$email = 'prueba_' . time() . '@example.com';
$cedula = 'CI' . time();

$registerResult = $controller->register([
    'nombre' => 'Ana',
    'apellido' => 'Pérez',
    'cedula' => $cedula,
    'email' => $email,
    'password' => '123456'
]);

$loginResult = $controller->login([
    'email' => $email,
    'password' => '123456'
]);

if ($registerResult['success'] !== true || $loginResult['success'] !== true) {
    fwrite(STDERR, json_encode(['register' => $registerResult, 'login' => $loginResult], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

echo "Auth flow OK\n";
>>>>>>> b90c71c8d22dc91fbb18d9ef11642352875cfa4e
