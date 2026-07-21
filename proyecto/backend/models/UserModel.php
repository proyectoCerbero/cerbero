<?php

class UserModel
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? 'cerbero',
            $config['charset'] ?? 'utf8mb4'
        );

        $this->pdo = new PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(array $data): array
    {
        $hashedPassword = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, cedula, email, password, role) VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            trim($data['nombre'] ?? ''),
            trim($data['apellido'] ?? ''),
            trim($data['cedula'] ?? ''),
            strtolower(trim($data['email'] ?? '')),
            $hashedPassword,
            $data['role'] ?? 'vecino'
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'nombre' => trim($data['nombre'] ?? ''),
            'apellido' => trim($data['apellido'] ?? ''),
            'cedula' => trim($data['cedula'] ?? ''),
            'email' => strtolower(trim($data['email'] ?? '')),
            'password' => $hashedPassword,
            'role' => $data['role'] ?? 'vecino'
        ];
    }
}
