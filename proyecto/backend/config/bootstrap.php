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

    $existingTables = array_map('strtolower', $tableCheck->fetchAll(PDO::FETCH_COLUMN));

    if (in_array('rol', $existingTables, true) && in_array('cuadrilla', $existingTables, true)) {
        return;
    }

    $sqlFile = __DIR__ . '/../../../CERBEROBD.sql';

    if (!file_exists($sqlFile)) {
        throw new RuntimeException(
            "No se encontró el archivo CERBEROBD.sql en $sqlFile para inicializar la base de datos."
        );
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
}
