<?php

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';

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

        if (!ValidationHelper::validRole($normalizedRoleName)) {
            return null;
        }

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
        $insertStmt->execute([$normalizedRoleName, $descriptionsByRole[$normalizedRoleName]]);
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

        return $aliases[$key] ?? '';
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
        if ($idRol === null) {
            throw new InvalidArgumentException('El rol indicado no es válido.');
        }
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

    public function squadExists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM cuadrilla WHERE id_cuadrilla = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
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

    /**
     * Actualiza los datos editables de un usuario (nombre, apellido, email y,
     * opcionalmente, rol/cuadrilla/estado si vienen presentes en $data).
     */
    public function update(string $ci, array $data): bool
    {
        $fields = [];
        $params = [];

        if (array_key_exists('nombre', $data) && trim((string) $data['nombre']) !== '') {
            $fields[] = 'nombre = ?';
            $params[] = trim($data['nombre']);
        }
        if (array_key_exists('apellido', $data) && trim((string) $data['apellido']) !== '') {
            $fields[] = 'apellido = ?';
            $params[] = trim($data['apellido']);
        }
        if (array_key_exists('email', $data) && trim((string) $data['email']) !== '') {
            $fields[] = 'email = ?';
            $params[] = strtolower(trim($data['email']));
        }
        if (array_key_exists('estado', $data) && trim((string) $data['estado']) !== '') {
            $fields[] = 'estado = ?';
            $params[] = trim($data['estado']);
        }
        if (!empty($data['password'])) {
            $fields[] = 'contrasena_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (array_key_exists('role', $data) && trim((string) $data['role']) !== '') {
            $idRol = $this->findRoleIdByName($data['role']);
            if ($idRol !== null) {
                $fields[] = 'id_rol = ?';
                $params[] = $idRol;
            }
        }
        if (array_key_exists('id_cuadrilla', $data)) {
            $fields[] = 'id_cuadrilla = ?';
            $params[] = $data['id_cuadrilla'] !== '' ? $data['id_cuadrilla'] : null;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $ci;
        $stmt = $this->pdo->prepare('UPDATE usuario SET ' . implode(', ', $fields) . ' WHERE ci = ?');
        $stmt->execute($params);

        return $stmt->rowCount() >= 0;
    }

    public function delete(string $ci): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM usuario WHERE ci = ? FOR UPDATE');
            $stmt->execute([$ci]);
            if ($stmt->fetchColumn() === false) {
                $this->pdo->rollBack();
                return false;
            }

            if ($this->columnExists('notificacion', 'ci')) {
                $stmt = $this->pdo->prepare('DELETE FROM notificacion WHERE ci = ?');
                $stmt->execute([$ci]);
            }
            if ($this->columnExists('incidencia', 'ci')) {
                $stmt = $this->pdo->prepare('UPDATE incidencia SET ci = NULL WHERE ci = ?');
                $stmt->execute([$ci]);
            }
            if ($this->columnExists('historial_llenado', 'ci_usuario')) {
                $stmt = $this->pdo->prepare('UPDATE historial_llenado SET ci_usuario = NULL WHERE ci_usuario = ?');
                $stmt->execute([$ci]);
            }
            $stmt = $this->pdo->prepare('DELETE FROM usuario WHERE ci = ?');
            $stmt->execute([$ci]);
            $eliminado = $stmt->rowCount() > 0;
            $this->pdo->commit();
            return $eliminado;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    }
}
