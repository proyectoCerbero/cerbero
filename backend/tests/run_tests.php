<?php

require_once __DIR__ . '/TestSuite.php';
require_once __DIR__ . '/unit_test.php';
require_once __DIR__ . '/crud_test.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db_connection.php';

$suite = new TestSuite();
registerUnitTests($suite);

try {
    $config = require __DIR__ . '/../config/database.php';
    cerbero_ensure_database($config);
    $pdo = Database::connect($config);
    registerCrudTests($suite, $config, $pdo);
} catch (Throwable $error) {
    $suite->run('Conexión e inicialización de la base de pruebas', function () use ($error): void {
        throw $error;
    });
}

exit($suite->finish());
