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

    // ¿Ya existe la base de datos?
    $stmt = $pdo->prepare(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
    );
    $stmt->execute([$dbName]);

    if ($stmt->fetchColumn() !== false) {
        return; // Ya existe, no hacemos nada.
    }

    // No existe: la creamos importando el dump que viene con el proyecto.
    $sqlFile = __DIR__ . '/../../../CERBEROBD.sql';

    if (!file_exists($sqlFile)) {
        throw new RuntimeException(
            "La base de datos '$dbName' no existe y no se encontró el archivo " .
            "CERBEROBD.sql en $sqlFile para crearla automáticamente."
        );
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
}
