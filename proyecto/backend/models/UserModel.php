<<<<<<< HEAD
<?php

require_once __DIR__ . '/../config/Database.php';

class UserModel
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
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

    public function findRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id_rol FROM rol WHERE nombre = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function create(array $data): array
    {
        $hashedPassword = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (ci, nombre, apellido, email, contraseña_hash, fecha_registro, estado, id_rol, id_cuadrilla) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
        );

        $estado = $data['estado'] ?? 'activo';
        // Si no se especifica un rol, se asigna el rol 'vecino' por defecto (en vez
        // de dejar id_rol en NULL), para que el módulo de gestión de usuarios
        // pueda mostrar y cambiar el rol real de cada usuario.
        $idRol = $data['id_rol'] ?? $this->findRoleIdByName($data['role'] ?? 'vecino');
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

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.ci, u.nombre, u.apellido, u.email, u.fecha_registro, u.estado,
                    u.id_rol, r.nombre AS role, u.id_cuadrilla, c.nombre AS cuadrilla_nombre
             FROM usuario u
             LEFT JOIN rol r ON u.id_rol = r.id_rol
             LEFT JOIN cuadrilla c ON u.id_cuadrilla = c.id_cuadrilla
             ORDER BY u.fecha_registro DESC'
        );

        return $stmt->fetchAll();
    }

    public function findByCi(string $ci): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.ci, u.nombre, u.apellido, u.email, u.fecha_registro, u.estado,
                    u.id_rol, r.nombre AS role, u.id_cuadrilla, c.nombre AS cuadrilla_nombre
             FROM usuario u
             LEFT JOIN rol r ON u.id_rol = r.id_rol
             LEFT JOIN cuadrilla c ON u.id_cuadrilla = c.id_cuadrilla
             WHERE u.ci = ?
             LIMIT 1'
        );
        $stmt->execute([$ci]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function updateEstado(string $ci, string $estado): bool
    {
        $stmt = $this->pdo->prepare('UPDATE usuario SET estado = ? WHERE ci = ?');
        $stmt->execute([$estado, $ci]);

        return $stmt->rowCount() > 0;
    }

    public function updateRol(string $ci, int $idRol): bool
    {
        $stmt = $this->pdo->prepare('UPDATE usuario SET id_rol = ? WHERE ci = ?');
        $stmt->execute([$idRol, $ci]);

        return $stmt->rowCount() > 0;
    }
}
=======
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
>>>>>>> b90c71c8d22dc91fbb18d9ef11642352875cfa4e
