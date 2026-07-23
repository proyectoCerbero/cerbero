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
        $stmt = $this->pdo->prepare(
            'SELECT u.ci AS id, u.nombre, u.apellido, u.email, u.contraseña_hash AS password, u.fecha_registro, u.estado, u.id_rol, u.id_cuadrilla, r.nombre AS role FROM usuario u LEFT JOIN rol r ON u.id_rol = r.id_rol WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(array $data): array
    {
        $hashedPassword = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (ci, nombre, apellido, email, contraseña_hash, fecha_registro, estado, id_rol, id_cuadrilla) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
        );

        $estado = $data['estado'] ?? 'activo';
        $idRol = $data['id_rol'] ?? null;
        $idCuadrilla = $data['id_cuadrilla'] ?? null;

        $stmt->execute([
            trim($data['cedula'] ?? ''),
            trim($data['nombre'] ?? ''),
            trim($data['apellido'] ?? ''),
            strtolower(trim($data['email'] ?? '')),
            $hashedPassword,
            $estado,
            $idRol,
            $idCuadrilla
        ]);

        return [
            'id' => trim($data['cedula'] ?? ''),
            'nombre' => trim($data['nombre'] ?? ''),
            'apellido' => trim($data['apellido'] ?? ''),
            'email' => strtolower(trim($data['email'] ?? '')),
            'role' => $data['role'] ?? 'vecino'
        ];
    }
}
