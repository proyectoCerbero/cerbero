<?php

require_once __DIR__ . '/TestSuite.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/ContenedorController.php';
require_once __DIR__ . '/../controllers/CamionController.php';
require_once __DIR__ . '/../controllers/InstalacionController.php';
require_once __DIR__ . '/../controllers/MaquinariaController.php';

function registerCrudTests(TestSuite $suite, array $config, PDO $pdo): void
{
    $suite->run('Registro, login y CRUD de usuarios para todos los roles', function () use ($suite, $config, $pdo): void {
        $auth = new AuthController($config);
        $usuarios = new UsuarioController($config);
        $base = random_int(70000000, 79999980);
        $created = [];

        try {
            $registerCi = (string) $base;
            $registerEmail = "vecino.$base@cerbero.test";
            $registered = $auth->register([
                'nombre' => 'Vecino',
                'apellido' => 'Prueba',
                'cedula' => $registerCi,
                'email' => $registerEmail,
                'password' => 'Cerbero2026!',
            ]);
            $suite->assertTrue($registered['success'], 'El registro de vecino debe completarse.');
            $created[] = $registerCi;

            $login = $auth->login(['email' => $registerEmail, 'password' => 'Cerbero2026!']);
            $suite->assertTrue($login['success'], 'El usuario registrado debe poder iniciar sesión.');

            $invalid = $auth->register([
                'nombre' => 'Dato', 'apellido' => 'Inválido', 'cedula' => 'ABC',
                'email' => 'incorrecto', 'password' => '123',
            ]);
            $suite->assertSame(400, $invalid['status'], 'El registro debe validar los datos de entrada.');

            $idCuadrilla = (int) $pdo->query('SELECT id_cuadrilla FROM cuadrilla ORDER BY id_cuadrilla LIMIT 1')->fetchColumn();
            $roles = ['vecino', 'cuadrilla de recolección', 'operario de centro', 'administrador municipal'];
            foreach ($roles as $index => $role) {
                $ci = (string) ($base + $index + 1);
                $result = $usuarios->create([
                    'ci' => $ci,
                    'nombre' => 'Usuario',
                    'apellido' => 'Rol',
                    'email' => "rol.$ci@cerbero.test",
                    'password' => 'Cerbero2026!',
                    'role' => $role,
                    'estado' => 'activo',
                    'id_cuadrilla' => $role === 'cuadrilla de recolección' ? $idCuadrilla : null,
                ]);
                $suite->assertTrue($result['success'], "Debe crear el rol $role.");
                $created[] = $ci;
            }

            $updated = $usuarios->update([
                'ci' => $registerCi,
                'nombre' => 'Vecino actualizado',
                'email' => "actualizado.$base@cerbero.test",
            ]);
            $suite->assertTrue($updated['success'], 'Debe modificar un usuario.');

            $disabled = $usuarios->updateEstado(['ci' => $registerCi, 'estado' => 'inactivo']);
            $suite->assertTrue($disabled['success'], 'Debe desactivar un usuario.');
            $blockedLogin = $auth->login(['email' => "actualizado.$base@cerbero.test", 'password' => 'Cerbero2026!']);
            $suite->assertSame(403, $blockedLogin['status'], 'Una cuenta inactiva no debe iniciar sesión.');

            $listed = $usuarios->list();
            $suite->assertTrue(count($listed['data']['usuarios']) >= count($created), 'El listado debe devolver los usuarios creados.');
        } finally {
            foreach (array_reverse($created) as $ci) {
                $usuarios->delete(['ci' => $ci], ['ci' => '00000000']);
            }
        }
    });

    $suite->run('CRUD de contenedores e historial de llenado', function () use ($suite, $config, $pdo): void {
        $controller = new ContenedorController($config);
        $location = (int) $pdo->query('SELECT id_ubicacion FROM ubicacion ORDER BY id_ubicacion LIMIT 1')->fetchColumn();
        $wasteType = (int) $pdo->query('SELECT id_tipo_residuo FROM tipo_residuo ORDER BY id_tipo_residuo LIMIT 1')->fetchColumn();
        $id = 0;

        try {
            $created = $controller->create([
                'capacidad' => 1100,
                'nivel_llenado' => 35,
                'estado' => 'en buen estado',
                'id_ubicacion' => $location,
                'id_tipo_residuo' => $wasteType,
            ], ['ci' => '00000000']);
            $suite->assertTrue($created['success'], 'Debe crear un contenedor.');
            $id = (int) $created['data']['id_contenedor'];

            $suite->assertTrue($controller->update(['id_contenedor' => $id, 'nivel_llenado' => 72], ['ci' => '00000000'])['success'], 'Debe modificar el llenado.');
            $suite->assertTrue($controller->clean(['id_contenedor' => $id, 'nivel_llenado_antes' => 72], ['ci' => '00000000'])['success'], 'Debe registrar la limpieza.');
            $history = $controller->history($id);
            $suite->assertTrue(count($history['data']['historial']) >= 3, 'Debe conservar el historial inicial, la actualización y la limpieza.');
        } finally {
            if ($id > 0) $controller->delete(['id_contenedor' => $id]);
        }
    });

    $suite->run('CRUD de camiones', function () use ($suite, $config): void {
        $controller = new CamionController($config);
        $id = 0;
        try {
            $created = $controller->create([
                'matricula' => 'TST-' . random_int(1000, 9999),
                'marca' => 'Mercedes-Benz',
                'modelo' => 'Atego',
                'anio' => 2024,
                'kilometraje' => 15000,
                'capacidad' => 12.5,
                'estado' => 'Operativo',
            ]);
            $suite->assertTrue($created['success'], 'Debe registrar un camión.');
            $id = (int) $created['data']['id_camion'];
            $suite->assertTrue($controller->update(['id_camion' => $id, 'estado' => 'Respaldo'])['success'], 'Debe modificar un camión.');
            $suite->assertSame('Respaldo', $controller->show($id)['data']['camion']['estado'], 'Debe persistir el nuevo estado.');
        } finally {
            if ($id > 0) $controller->delete(['id_camion' => $id]);
        }
    });

    $suite->run('CRUD de centros de acopio y maquinaria', function () use ($suite, $config): void {
        $centros = new InstalacionController($config);
        $maquinaria = new MaquinariaController($config);
        $idCentro = 0;
        $idMaquinaria = 0;

        try {
            $createdCenter = $centros->create([
                'nombre' => 'Centro de prueba',
                'calle' => 'Prueba',
                'numero' => '2026',
                'telefono' => '29000000',
                'horario' => '08:00-16:00',
                'capacidad' => 25,
                'estado' => 'activo',
            ]);
            $suite->assertTrue($createdCenter['success'], 'Debe crear un centro de acopio.');
            $idCentro = (int) $createdCenter['data']['id_instalacion'];
            $suite->assertTrue($centros->update(['id_instalacion' => $idCentro, 'capacidad' => 30])['success'], 'Debe modificar el centro.');

            $createdMachine = $maquinaria->create([
                'nombre' => 'Compactadora de prueba',
                'tipo' => 'Compactadora',
                'estado' => 'operativa',
                'id_instalacion' => $idCentro,
            ]);
            $suite->assertTrue($createdMachine['success'], 'Debe crear maquinaria.');
            $idMaquinaria = (int) $createdMachine['data']['id_maquinaria'];
            $suite->assertTrue($maquinaria->update(['id_maquinaria' => $idMaquinaria, 'estado' => 'mantenimiento'])['success'], 'Debe modificar la maquinaria.');
        } finally {
            if ($idMaquinaria > 0) $maquinaria->delete(['id_maquinaria' => $idMaquinaria]);
            if ($idCentro > 0) $centros->delete(['id_instalacion' => $idCentro]);
        }
    });
}
