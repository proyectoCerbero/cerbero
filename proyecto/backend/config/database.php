<<<<<<< HEAD
<?php

class Database
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? 'cerbero',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
=======
<?php

return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'cerbero',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];
>>>>>>> b90c71c8d22dc91fbb18d9ef11642352875cfa4e
