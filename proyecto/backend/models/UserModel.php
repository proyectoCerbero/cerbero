<?php

require_once __DIR__ . '/../config/db_connection.php';

class UserModel
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
        $this->ensureDefaultAdminUser();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.ci AS id, u.nombre, u.apellido, u.email, u.contrasena_hash AS password, u.fecha_registro, u.estado, u.id_rol, u.id_cuadrilla, r.nombre AS role FROM usuario u LEFT JOIN rol r ON u.id_rol = r.id_rol WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function ensureDefaultAdminUser(): void
    {
        $adminEmail = 'adminejemplo@gmail.com';
        $existing = $this->findByEmail($adminEmail);

        if ($existing !== null) {
            return;
        }

        $roleId = $this->findRoleIdByName('administrador municipal');

        if ($roleId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (ci, nombre, apellido, email, contrasena_hash, fecha_registro, estado, id_rol, id_cuadrilla) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            '00000000',
            'Admin',
            'Ejemplo',
            $adminEmail,
            password_hash('admin1234', PASSWORD_BCRYPT),
            date('Y-m-d H:i:s'),
            'activo',
            $roleId,
            null
        ]);
    }

    public function findRoleIdByName(string $roleName): ?int
    {
        $normalizedRoleName = $this->normalizeRoleName($roleName);

        $stmt = $this->pdo->prepare('SELECT id_rol FROM rol WHERE nombre = ? LIMIT 1');
        $stmt->execute([$normalizedRoleName]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $descriptionsByRole = [
            'vecino' => 'Rol para vecinos que reportan incidencias y participan en el sistema.',
            'cuadrilla de recolección' => 'Rol para personal de cuadrilla de recolección y operaciones de campo.',
            'operario de centro' => 'Rol para operarios de centros de gestión y atención de residuos.',
            'administrador municipal' => 'Rol para administradores municipales con permisos de gestión completa.'
        ];

        $insertStmt = $this->pdo->prepare('INSERT INTO rol (nombre, descripcion) VALUES (?, ?)');
        $insertStmt->execute([$normalizedRoleName, $descriptionsByRole[$normalizedRoleName] ?? 'Rol creado automáticamente']);
        $newId = $this->pdo->lastInsertId();

        return $newId !== false ? (int) $newId : null;
    }

    public function normalizeRoleName(string $roleName): string
    {
        $normalized = trim($roleName);
        $aliases = [
            'admin' => 'administrador municipal',
            'administrador' => 'administrador municipal',
            'administrador municipal' => 'administrador municipal',
            'cuadrilla' => 'cuadrilla de recolección',
            'cuadrilla de recoleccion' => 'cuadrilla de recolección',
            'cuadrilla de recolección' => 'cuadrilla de recolección',
            'operario' => 'operario de centro',
            'operario de centro' => 'operario de centro',
            'vecino' => 'vecino',
        ];

        $key = strtolower($normalized);

        return $aliases[$key] ?? $normalized;
    }

    public function create(array $data): array
    {
        $hashedPassword = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (ci, nombre, apellido, email, contrasena_hash, fecha_registro, estado, id_rol, id_cuadrilla) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
        );

        $estado = $data['estado'] ?? 'activo';
        // Si no se especifica un rol, se asigna el rol 'vecino' por defecto (en vez
        // de dejar id_rol en NULL), para que el módulo de gestión de usuarios
        // pueda mostrar y cambiar el rol real de cada usuario.
        $roleName = $data['role'] ?? 'vecino';
        $idRol = $data['id_rol'] ?? $this->findRoleIdByName($roleName);
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
