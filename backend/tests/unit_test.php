<?php

require_once __DIR__ . '/TestSuite.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

function registerUnitTests(TestSuite $suite): void
{
    $suite->run('Validación de datos de usuario', function () use ($suite): void {
        $suite->assertTrue(ValidationHelper::validCi('51234567'), 'Debe aceptar una cédula numérica válida.');
        $suite->assertFalse(ValidationHelper::validCi('CI123'), 'Debe rechazar una cédula con letras.');
        $suite->assertTrue(ValidationHelper::validEmail('vecino@cerbero.uy'), 'Debe aceptar un correo válido.');
        $suite->assertFalse(ValidationHelper::validEmail('correo-invalido'), 'Debe rechazar un correo inválido.');
        $suite->assertTrue(ValidationHelper::validPassword('Cerbero2026!'), 'Debe aceptar una contraseña segura.');
        $suite->assertFalse(ValidationHelper::validPassword('1234567'), 'Debe rechazar una contraseña corta.');
        $suite->assertFalse(ValidationHelper::validRole('superusuario'), 'Debe rechazar roles fuera del catálogo.');
    });

    $suite->run('Matriz básica de permisos por rol', function () use ($suite): void {
        $suite->assertTrue(AuthHelper::roleAllowed('admin', ['admin']), 'El administrador debe acceder a endpoints administrativos.');
        $suite->assertTrue(AuthHelper::roleAllowed('miembro de cuadrilla', ['cuadrilla']), 'El alias de cuadrilla debe normalizarse.');
        $suite->assertFalse(AuthHelper::roleAllowed('vecino', ['admin']), 'El vecino no debe acceder a endpoints administrativos.');
        $suite->assertFalse(AuthHelper::roleAllowed(null, ['vecino']), 'Un visitante no debe pasar como vecino.');
    });
}
