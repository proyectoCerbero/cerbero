<?php

/**
 * Se asegura de que la base de datos 'cerbero' exista en el MySQL local.
 * Si no existe (por ejemplo, primera vez que se corre el proyecto en una
 * computadora nueva), la crea automáticamente importando CERBEROBD.sql.
 *
 * Así, cualquier persona que baje el proyecto y lo corra con XAMPP no
 * necesita crear la base a mano en phpMyAdmin.
 */
function cerbero_ensure_database(array $config): void
{
    $host    = $config['host'] ?? '127.0.0.1';
    $port    = $config['port'] ?? 3306;
    $dbName  = $config['database'] ?? 'cerbero';
    $user    = $config['username'] ?? 'root';
    $pass    = $config['password'] ?? '';
    $charset = $config['charset'] ?? 'utf8mb4';

    // Nos conectamos al servidor MySQL SIN indicar base de datos,
    // porque en este punto puede que 'cerbero' todavía no exista.
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Necesario para poder ejecutar el dump .sql completo (varias
            // sentencias separadas por ';') en una sola llamada.
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException(
            'No se pudo conectar al servidor MySQL. Verificá que MySQL esté ' .
            'iniciado en XAMPP (Panel de Control -> Start). Detalle: ' . $e->getMessage()
        );
    }

    $stmt = $pdo->prepare(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
    );
    $stmt->execute([$dbName]);

    if ($stmt->fetchColumn() === false) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    $pdo->exec("USE `$dbName`");

    $tableCheck = $pdo->query('SHOW TABLES');
    $existingTables = array_map('strtolower', $tableCheck->fetchAll(PDO::FETCH_COLUMN));

    if (in_array('usuario', $existingTables, true)) {
        $columnCheck = $pdo->query('SHOW COLUMNS FROM usuario LIKE "contrasena_hash"');
        $contrasenaHashExists = $columnCheck->rowCount() > 0;

        if (!$contrasenaHashExists) {
            $legacyColumnCheck = $pdo->query('SHOW COLUMNS FROM usuario LIKE "contraseña_hash"');
            if ($legacyColumnCheck->rowCount() > 0) {
                $pdo->exec('ALTER TABLE usuario CHANGE contraseña_hash contrasena_hash VARCHAR(255) NOT NULL');
            }
        }
    }

    // Mantiene compatibles las bases creadas antes de incorporar fotos y
    // ubicación al módulo de incidencias.
    if (in_array('incidencia', $existingTables, true)) {
        $ubicacionCheck = $pdo->query('SHOW COLUMNS FROM incidencia LIKE "ubicacion"');
        if ($ubicacionCheck->rowCount() === 0) {
            $pdo->exec('ALTER TABLE incidencia ADD COLUMN ubicacion VARCHAR(255) NULL AFTER estado');
        }

        $fotoCheck = $pdo->query('SHOW COLUMNS FROM incidencia LIKE "foto"');
        if ($fotoCheck->rowCount() === 0) {
            $pdo->exec('ALTER TABLE incidencia ADD COLUMN foto VARCHAR(255) NULL AFTER ubicacion');
        }
    }

    $requiredTables = [
        'configuracion_sistema', 'rol', 'cuadrilla', 'usuario', 'notificacion', 'tipo_incidencia',
        'ubicacion', 'tipo_residuo', 'contenedor', 'historial_llenado', 'incidencia', 'camion',
        'tipo_mantenimiento', 'mantenimiento', 'instalacion', 'traslado_residuo',
        'repuesto', 'tipo_maquinaria', 'maquinaria', 'ruta_recoleccion',
        'cuadrilla_ruta', 'tipo_reparacion', 'reparacion_contenedor',
        'tipo_prediccion', 'prediccion', 'instalacion_tipo_residuo', 'ruta_ubicacion'
    ];

    if (array_diff($requiredTables, $existingTables) !== []) {
        $sqlCandidates = [
            dirname(__DIR__, 2) . '/CERBEROBD.sql',
            __DIR__ . '/../../../CERBEROBD.sql',
        ];
        $sqlFile = null;
        foreach ($sqlCandidates as $candidate) {
            if (is_file($candidate)) {
                $sqlFile = $candidate;
                break;
            }
        }

        if ($sqlFile === null) {
            throw new RuntimeException(
                'No se encontró el archivo CERBEROBD.sql para inicializar la base de datos.'
            );
        }

        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
    }

    // Compatibilidad con bases creadas por versiones anteriores del proyecto:
    // CREATE TABLE IF NOT EXISTS no agrega columnas nuevas a tablas existentes.
    foreach (['usuario', 'incidencia', 'camion', 'traslado_residuo'] as $tabla) {
        if (!cerbero_column_exists($pdo, $tabla, 'id_cuadrilla')) {
            $pdo->exec("ALTER TABLE $tabla ADD COLUMN id_cuadrilla INT NULL");
        }
    }
    $columnasNuevas = [
        ['camion', 'disponibilidad', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['instalacion', 'latitud', 'DECIMAL(10,7) NULL'],
        ['instalacion', 'longitud', 'DECIMAL(10,7) NULL'],
        ['ruta_recoleccion', 'id_instalacion_base', 'INT NULL'],
        ['ruta_recoleccion', 'fecha_creacion', 'DATETIME NULL'],
        ['ruta_recoleccion', 'duracion_minutos', 'DECIMAL(10,2) NULL'],
        ['ruta_recoleccion', 'geometria', 'LONGTEXT NULL'],
        ['ruta_ubicacion', 'orden', 'INT NULL'],
        ['incidencia', 'latitud', 'DECIMAL(10,7) NULL'],
        ['incidencia', 'longitud', 'DECIMAL(10,7) NULL'],
    ];
    foreach ($columnasNuevas as [$tabla, $columna, $definicion]) {
        if (!cerbero_column_exists($pdo, $tabla, $columna)) {
            $pdo->exec("ALTER TABLE $tabla ADD COLUMN $columna $definicion");
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS registro_recoleccion (
            id_recoleccion INT AUTO_INCREMENT PRIMARY KEY,
            fecha_hora DATETIME NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL DEFAULT 0,
            observaciones VARCHAR(500),
            id_cuadrilla INT NOT NULL,
            id_camion INT NOT NULL,
            id_ruta INT NULL,
            ci_usuario CHAR(8),
            FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id_cuadrilla),
            FOREIGN KEY (id_camion) REFERENCES camion(id_camion),
            FOREIGN KEY (id_ruta) REFERENCES ruta_recoleccion(id_ruta),
            FOREIGN KEY (ci_usuario) REFERENCES usuario(ci)
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recoleccion_contenedor (
            id_recoleccion INT NOT NULL,
            id_contenedor INT NOT NULL,
            porcentaje_antes DECIMAL(5,2) NOT NULL,
            porcentaje_despues DECIMAL(5,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id_recoleccion, id_contenedor),
            FOREIGN KEY (id_recoleccion) REFERENCES registro_recoleccion(id_recoleccion) ON DELETE CASCADE,
            FOREIGN KEY (id_contenedor) REFERENCES contenedor(id_contenedor)
        )'
    );

    cerbero_seed_montevideo_contenedores($pdo);
    cerbero_seed_cuadrillas_por_ruta($pdo);
    cerbero_seed_puntos_rutas_base($pdo);
    cerbero_seed_centros_y_bases_de_ruta($pdo);
    cerbero_seed_camiones_cerbero($pdo);
}

/** Guarda en la base los diez contenedores de cada ruta base, sin repetirlos. */
function cerbero_seed_puntos_rutas_base(PDO $pdo): void
{
    $clave = 'rutas_base_50_puntos_v1';
    $stmt = $pdo->prepare('SELECT 1 FROM configuracion_sistema WHERE clave = ? LIMIT 1');
    $stmt->execute([$clave]);
    if ($stmt->fetchColumn()) return;

    // Coincide con los 50 contenedores que aparecían en la vista de rutas.
    $contenedores = $pdo->query(
        'SELECT c.id_ubicacion, u.longitud FROM contenedor c
         INNER JOIN ubicacion u ON u.id_ubicacion = c.id_ubicacion
         WHERE u.latitud IS NOT NULL AND u.longitud IS NOT NULL
         ORDER BY c.id_contenedor DESC LIMIT 50'
    )->fetchAll();
    if (count($contenedores) < 50) return;
    usort($contenedores, static fn (array $a, array $b): int => (float) $a['longitud'] <=> (float) $b['longitud']);

    $buscarRuta = $pdo->prepare('SELECT id_ruta FROM ruta_recoleccion WHERE nombre = ? LIMIT 1');
    $contar = $pdo->prepare('SELECT COUNT(*) FROM ruta_ubicacion WHERE id_ruta = ?');
    $insertar = $pdo->prepare('INSERT IGNORE INTO ruta_ubicacion (id_ruta, id_ubicacion, orden) VALUES (?, ?, ?)');
    $pdo->beginTransaction();
    try {
        for ($numero = 1; $numero <= 5; $numero++) {
            $buscarRuta->execute(['Ruta ' . $numero]);
            $idRuta = (int) $buscarRuta->fetchColumn();
            if (!$idRuta) throw new RuntimeException('Falta una ruta base para asignar los puntos.');
            $contar->execute([$idRuta]);
            if ((int) $contar->fetchColumn() > 0) continue;
            foreach (array_slice($contenedores, ($numero - 1) * 10, 10) as $indice => $punto) {
                $insertar->execute([$idRuta, $punto['id_ubicacion'], $indice + 1]);
            }
        }
        $guardar = $pdo->prepare('INSERT INTO configuracion_sistema (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())');
        $guardar->execute([$clave, 'Cinco rutas base con diez puntos únicos cada una']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Crea una muestra idempotente de 50 contenedores georreferenciados para el
 * mapa de Montevideo.
 */
function cerbero_seed_montevideo_contenedores(PDO $pdo): void
{
    // No se reconstruye la muestra si un administrador elimina después uno de
    // sus contenedores: las ubicaciones actúan como marca de inicialización.
    $muestraCreada = (int) $pdo->query(
        "SELECT COUNT(*) FROM ubicacion WHERE calle LIKE 'Punto de recolección %'"
    )->fetchColumn();
    if ($muestraCreada >= 50) {
        return;
    }

    $puntos = [
        ['Centro', -34.9058, -56.1913],
        ['Ciudad Vieja', -34.9060, -56.2050],
        ['Cordón', -34.8990, -56.1780],
        ['Parque Rodó', -34.9140, -56.1700],
        ['Pocitos', -34.9100, -56.1500],
        ['Punta Carretas', -34.9230, -56.1590],
        ['Buceo', -34.9010, -56.1320],
        ['Malvín', -34.8920, -56.1000],
        ['Punta Gorda', -34.8900, -56.0800],
        ['Carrasco', -34.8800, -56.0600],
        ['La Blanqueada', -34.8900, -56.1600],
        ['Tres Cruces', -34.8950, -56.1680],
        ['Unión', -34.8790, -56.1350],
        ['Maroñas', -34.8600, -56.1300],
        ['Flor de Maroñas', -34.8450, -56.1200],
        ['Cerrito', -34.8500, -56.1700],
        ['Brazo Oriental', -34.8660, -56.1700],
        ['Atahualpa', -34.8670, -56.1870],
        ['Prado', -34.8600, -56.2000],
        ['Aguada', -34.8940, -56.1870],
        ['Reducto', -34.8800, -56.1900],
        ['Bella Vista', -34.8740, -56.2000],
        ['Capurro', -34.8730, -56.2200],
        ['La Teja', -34.8660, -56.2400],
        ['Cerro', -34.8880, -56.2500],
        ['Casabó', -34.8800, -56.2900],
        ['Paso de la Arena', -34.8350, -56.2700],
        ['Nuevo París', -34.8400, -56.2500],
        ['Belvedere', -34.8500, -56.2200],
        ['Sayago', -34.8350, -56.2100],
        ['Peñarol', -34.8150, -56.2050],
        ['Colón', -34.8000, -56.2200],
        ['Lezica', -34.7900, -56.2500],
        ['Manga', -34.8000, -56.1300],
        ['Piedras Blancas', -34.8150, -56.1400],
        ['Jardines del Hipódromo', -34.8400, -56.1350],
        ['Ituzaingó', -34.8450, -56.1550],
        ['Villa Española', -34.8700, -56.1500],
        ['Mercado Modelo', -34.8820, -56.1640],
        ['Jacinto Vera', -34.8750, -56.1800],
        ['La Comercial', -34.8880, -56.1800],
        ['Palermo', -34.9100, -56.1850],
        ['Barrio Sur', -34.9120, -56.1950],
        ['Malvín Norte', -34.8750, -56.1200],
        ['Parque Batlle', -34.8950, -56.1550],
        ['Villa Dolores', -34.9000, -56.1450],
        ['Carrasco Norte', -34.8600, -56.0700],
        ['Punta Rieles', -34.8300, -56.1000],
        ['Villa García', -34.7900, -56.0800],
        ['Santiago Vázquez', -34.7900, -56.3500],
    ];

    $pdo->exec(
        "INSERT INTO tipo_residuo (nombre)
         SELECT 'Residuos urbanos'
         WHERE NOT EXISTS (SELECT 1 FROM tipo_residuo WHERE nombre = 'Residuos urbanos')"
    );
    $tipoResiduo = (int) $pdo->query(
        "SELECT id_tipo_residuo FROM tipo_residuo WHERE nombre = 'Residuos urbanos' ORDER BY id_tipo_residuo LIMIT 1"
    )->fetchColumn();

    $buscarUbicacion = $pdo->prepare(
        'SELECT id_ubicacion FROM ubicacion WHERE latitud = ? AND longitud = ? LIMIT 1'
    );
    $crearUbicacion = $pdo->prepare(
        'INSERT INTO ubicacion (barrio, calle, numero, latitud, longitud) VALUES (?, ?, ?, ?, ?)'
    );
    $buscarContenedor = $pdo->prepare(
        'SELECT id_contenedor FROM contenedor WHERE id_ubicacion = ? LIMIT 1'
    );
    $crearContenedor = $pdo->prepare(
        "INSERT INTO contenedor
         (capacidad, nivel_llenado, fecha_instalacion, fecha_ultima_recoleccion, estado, id_ubicacion, id_tipo_residuo)
         VALUES (1100, ?, CURDATE(), NULL, 'en buen estado', ?, ?)"
    );

    foreach ($puntos as $indice => [$barrio, $latitud, $longitud]) {
        $buscarUbicacion->execute([$latitud, $longitud]);
        $idUbicacion = (int) ($buscarUbicacion->fetchColumn() ?: 0);

        if ($idUbicacion === 0) {
            $crearUbicacion->execute([
                $barrio,
                'Punto de recolección ' . ($indice + 1),
                (string) (100 + $indice),
                $latitud,
                $longitud,
            ]);
            $idUbicacion = (int) $pdo->lastInsertId();
        }

        $buscarContenedor->execute([$idUbicacion]);
        if ($buscarContenedor->fetchColumn() === false) {
            $nivelLlenado = ($indice * 17 + 12) % 96;
            $crearContenedor->execute([$nivelLlenado, $idUbicacion, $tipoResiduo ?: null]);
        }
    }
}

/**
 * Sustituye una sola vez las cuadrillas de ejemplo por 15 cuadrillas CN,
 * asignando tres turnos a cada una de las cinco rutas.
 */
function cerbero_seed_cuadrillas_por_ruta(PDO $pdo): void
{
    $claveMigracion = 'cuadrillas_cn_15_v1';
    $verificar = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
    $verificar->execute([$claveMigracion]);
    if ($verificar->fetchColumn() !== false) {
        return;
    }

    $turnos = [
        ['00:00-08:00', '00:00:00', '08:00:00'],
        ['08:00-16:00', '08:00:00', '16:00:00'],
        ['16:00-00:00', '16:00:00', '00:00:00'],
    ];

    $pdo->beginTransaction();
    try {
        $buscarRuta = $pdo->prepare('SELECT id_ruta FROM ruta_recoleccion WHERE nombre = ? ORDER BY id_ruta LIMIT 1');
        $crearRuta = $pdo->prepare(
            "INSERT INTO ruta_recoleccion (nombre, frecuencia, distancia, estado)
             VALUES (?, 'Diaria', NULL, 'activa')"
        );
        $rutas = [];
        for ($numeroRuta = 1; $numeroRuta <= 5; $numeroRuta++) {
            $nombreRuta = 'Ruta ' . $numeroRuta;
            $buscarRuta->execute([$nombreRuta]);
            $idRuta = (int) ($buscarRuta->fetchColumn() ?: 0);
            if ($idRuta === 0) {
                $crearRuta->execute([$nombreRuta]);
                $idRuta = (int) $pdo->lastInsertId();
            }
            $rutas[$numeroRuta] = $idRuta;
        }

        // Desvincula las dos cuadrillas anteriores (y cualquier dato de prueba)
        // antes de reemplazarlas, evitando errores por claves foráneas.
        foreach (['usuario', 'incidencia', 'camion', 'traslado_residuo'] as $tabla) {
            if (cerbero_column_exists($pdo, $tabla, 'id_cuadrilla')) {
                $pdo->exec("UPDATE $tabla SET id_cuadrilla = NULL WHERE id_cuadrilla IS NOT NULL");
            }
        }
        $pdo->exec('DELETE FROM cuadrilla_ruta');
        $pdo->exec('DELETE FROM cuadrilla');

        $crearCuadrilla = $pdo->prepare(
            "INSERT INTO cuadrilla (nombre, turno, estado) VALUES (?, ?, 'activa')"
        );
        $asignarRuta = $pdo->prepare(
            "INSERT INTO cuadrilla_ruta
             (id_cuadrilla, id_ruta, fecha, hora_inicio, hora_fin, estado)
             VALUES (?, ?, CURDATE(), ?, ?, 'asignada')"
        );

        $numeroCuadrilla = 1;
        for ($numeroRuta = 1; $numeroRuta <= 5; $numeroRuta++) {
            foreach ($turnos as [$turno, $horaInicio, $horaFin]) {
                $crearCuadrilla->execute(['CN' . $numeroCuadrilla, $turno]);
                $idCuadrilla = (int) $pdo->lastInsertId();
                $asignarRuta->execute([$idCuadrilla, $rutas[$numeroRuta], $horaInicio, $horaFin]);
                $numeroCuadrilla++;
            }
        }

        $guardarMigracion = $pdo->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())'
        );
        $guardarMigracion->execute([$claveMigracion, '15 cuadrillas CN distribuidas en 5 rutas']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Registra los tres centros indicados y los asigna como base de salida y
 * regreso de las cinco rutas de recolección.
 */
function cerbero_seed_centros_y_bases_de_ruta(PDO $pdo): void
{
    $claveMigracion = 'centros_acopio_rutas_v1';
    $verificar = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
    $verificar->execute([$claveMigracion]);
    if ($verificar->fetchColumn() !== false) return;

    $centros = [
        'ECOCENTRO BUCEO' => [
            'calle' => 'Av. Tomás Basañez', 'numero' => '1212', 'telefono' => '2900 0101',
            'horario' => '00:00', 'capacidad' => 75, 'latitud' => -34.9037911, 'longitud' => -56.1256730,
        ],
        'Reciclo NFU' => [
            'calle' => 'Sarandí', 'numero' => '648', 'telefono' => '2900 0102',
            'horario' => '00:00', 'capacidad' => 50, 'latitud' => -34.9067096, 'longitud' => -56.2021135,
        ],
        'Centro de Acopio Disco' => [
            'calle' => '4WJC+RWV, Cno. Pichincha', 'numero' => '12100', 'telefono' => '2900 0103',
            'horario' => '00:00', 'capacidad' => 90, 'latitud' => -34.8678779, 'longitud' => -56.0777015,
        ],
    ];
    $bases = [
        'Ruta 1' => 'Reciclo NFU',
        'Ruta 2' => 'Reciclo NFU',
        'Ruta 3' => 'ECOCENTRO BUCEO',
        'Ruta 4' => 'ECOCENTRO BUCEO',
        'Ruta 5' => 'Centro de Acopio Disco',
    ];

    $pdo->beginTransaction();
    try {
        $buscar = $pdo->prepare('SELECT id_instalacion FROM instalacion WHERE nombre = ? ORDER BY id_instalacion LIMIT 1');
        $crear = $pdo->prepare(
            "INSERT INTO instalacion
             (nombre, calle, numero, telefono, horario, capacidad, estado, latitud, longitud)
             VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, ?)"
        );
        $actualizar = $pdo->prepare(
            "UPDATE instalacion SET calle = ?, numero = ?, telefono = ?, horario = ?,
             capacidad = ?, estado = 'activo', latitud = ?, longitud = ? WHERE id_instalacion = ?"
        );
        $ids = [];
        foreach ($centros as $nombre => $centro) {
            $buscar->execute([$nombre]);
            $id = (int) ($buscar->fetchColumn() ?: 0);
            $valores = [
                $centro['calle'], $centro['numero'], $centro['telefono'], $centro['horario'],
                $centro['capacidad'], $centro['latitud'], $centro['longitud'],
            ];
            if ($id > 0) {
                $actualizar->execute([...$valores, $id]);
            } else {
                $crear->execute([$nombre, ...$valores]);
                $id = (int) $pdo->lastInsertId();
            }
            $ids[$nombre] = $id;
        }

        $asignarBase = $pdo->prepare(
            'UPDATE ruta_recoleccion SET id_instalacion_base = ? WHERE nombre = ?'
        );
        foreach ($bases as $ruta => $centro) {
            $asignarBase->execute([$ids[$centro], $ruta]);
        }

        $guardar = $pdo->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())'
        );
        $guardar->execute([$claveMigracion, '3 centros asignados como base de 5 rutas']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Agrega 15 camiones operativos, cinco respaldos y tres unidades en reparación.
 */
function cerbero_seed_camiones_cerbero(PDO $pdo): void
{
    $claveMigracion = 'camiones_cerbero_23_v1';
    $verificar = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = ? LIMIT 1');
    $verificar->execute([$claveMigracion]);
    if ($verificar->fetchColumn() !== false) return;

    $buscarCuadrilla = $pdo->prepare('SELECT id_cuadrilla FROM cuadrilla WHERE nombre = ? ORDER BY id_cuadrilla LIMIT 1');
    $cuadrillas = [];
    for ($numero = 1; $numero <= 15; $numero++) {
        $nombre = 'CN' . $numero;
        $buscarCuadrilla->execute([$nombre]);
        $id = (int) ($buscarCuadrilla->fetchColumn() ?: 0);
        if ($id === 0) throw new RuntimeException("No se encontró la cuadrilla $nombre para asignar los camiones.");
        $cuadrillas[$nombre] = $id;
    }

    $marcas = [
        ['Mercedes-Benz', 'Atego 1726'],
        ['Iveco', 'Tector 170E'],
        ['Volkswagen', 'Constellation 17.280'],
    ];
    $camiones = [];
    for ($numero = 1; $numero <= 15; $numero++) {
        [$marca, $modelo] = $marcas[($numero - 1) % count($marcas)];
        $camiones[] = [
            sprintf('CER-%03d', $numero), $marca, $modelo, 2020 + ($numero % 5),
            24000 + ($numero * 1750), 14 + ($numero % 3), 'Operativo', 1, $cuadrillas['CN' . $numero],
        ];
    }
    $respaldoCuadrillas = ['CN1', 'CN5', 'CN9', 'CN10', 'CN14'];
    foreach ($respaldoCuadrillas as $indice => $nombreCuadrilla) {
        [$marca, $modelo] = $marcas[$indice % count($marcas)];
        $camiones[] = [
            sprintf('CER-R%02d', $indice + 1), $marca, $modelo, 2019 + ($indice % 4),
            48000 + ($indice * 3200), 15, 'Respaldo', 1, $cuadrillas[$nombreCuadrilla],
        ];
    }
    for ($numero = 1; $numero <= 3; $numero++) {
        [$marca, $modelo] = $marcas[($numero - 1) % count($marcas)];
        $camiones[] = [
            sprintf('CER-T%02d', $numero), $marca, $modelo, 2018 + $numero,
            82000 + ($numero * 4500), 14, 'En reparación', 0, null,
        ];
    }

    $pdo->beginTransaction();
    try {
        $buscar = $pdo->prepare('SELECT 1 FROM camion WHERE matricula = ? LIMIT 1');
        $crear = $pdo->prepare(
            'INSERT INTO camion
             (matricula, marca, modelo, anio, kilometraje, capacidad, estado, disponibilidad, id_cuadrilla)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($camiones as $camion) {
            $buscar->execute([$camion[0]]);
            if ($buscar->fetchColumn() === false) $crear->execute($camion);
        }
        $guardar = $pdo->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())'
        );
        $guardar->execute([$claveMigracion, '23 camiones: 15 operativos, 5 respaldos y 3 en reparación']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Permite ejecutar migraciones sobre instalaciones antiguas sin asumir que
 * todas las tablas ya tienen las columnas de la versión actual.
 */
function cerbero_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() !== false;
}
