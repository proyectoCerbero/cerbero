<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';

/**
 * Controlador de gestión de usuarios para el panel de administración.
 * Delega el acceso a datos en UserModel, que sí coincide con el
 * esquema real de la tabla `usuario` (ci, id_rol, id_cuadrilla, etc.).
 */
class UsuarioController
{
    private UserModel $userModel;

    public function __construct(array $config)
    {
        $this->userModel = new UserModel($config);
    }

    public function list(): array
    {
        $rows = $this->userModel->findAll();

        $usuarios = array_map(function (array $row): array {
            return [
                'ci' => $row['ci'],
                'id' => $row['ci'],
                'nombre' => $row['nombre'] ?? '',
                'apellido' => $row['apellido'] ?? '',
                'email' => $row['email'] ?? '',
                'estado' => $row['estado'] ?? 'activo',
                'rol' => $row['role'] ?? 'vecino',
                'role' => $row['role'] ?? 'vecino',
                'id_cuadrilla' => $row['id_cuadrilla'],
                'cuadrilla' => $row['cuadrilla_nombre'],
                'fecha_registro' => $row['fecha_registro'] ?? null,
            ];
        }, $rows);

        return ['success' => true, 'message' => 'Usuarios obtenidos correctamente.', 'status' => 200, 'data' => ['usuarios' => $usuarios]];
    }

    public function create(array $input): array
    {
        $nombre = trim($input['nombre'] ?? '');
        $apellido = trim($input['apellido'] ?? '');
        $cedula = trim($input['cedula'] ?? $input['ci'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $rol = $this->userModel->normalizeRoleName((string) ($input['rol'] ?? $input['role'] ?? 'vecino'));
        $estado = strtolower(trim((string) ($input['estado'] ?? 'activo')));
        $idCuadrilla = ($input['id_cuadrilla'] ?? '') !== '' ? (int) $input['id_cuadrilla'] : null;

        if ($nombre === '' || $email === '' || $cedula === '') {
            return ['success' => false, 'message' => 'Nombre, cédula y correo son obligatorios.', 'status' => 400, 'data' => []];
        }

        if (!ValidationHelper::validName($nombre) || ($apellido !== '' && !ValidationHelper::validName($apellido))) {
            return ['success' => false, 'message' => 'Nombre o apellido no válidos.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validCi($cedula)) {
            return ['success' => false, 'message' => 'La cédula debe contener entre 7 y 8 números.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validEmail($email)) {
            return ['success' => false, 'message' => 'El correo electrónico no es válido.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validPassword($password)) {
            return ['success' => false, 'message' => 'La contraseña debe tener entre 8 y 72 caracteres.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validRole($rol)) {
            return ['success' => false, 'message' => 'El rol seleccionado no es válido.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validUserState($estado)) {
            return ['success' => false, 'message' => 'El estado seleccionado no es válido.', 'status' => 400, 'data' => []];
        }
        if ($rol === 'cuadrilla de recolección' && $idCuadrilla !== null && !$this->userModel->squadExists($idCuadrilla)) {
            return ['success' => false, 'message' => 'La cuadrilla seleccionada no existe.', 'status' => 400, 'data' => []];
        }

        if ($this->userModel->findByCi($cedula)) {
            return ['success' => false, 'message' => 'Ya existe un usuario con esa cédula.', 'status' => 409, 'data' => []];
        }

        if ($this->userModel->findByEmail($email)) {
            return ['success' => false, 'message' => 'Ya existe un usuario con ese correo.', 'status' => 409, 'data' => []];
        }

        try {
            $this->userModel->create([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'cedula' => $cedula,
                'email' => $email,
                'password' => $password,
                'role' => $rol,
                'estado' => $estado,
                'id_cuadrilla' => $rol === 'cuadrilla de recolección' ? $idCuadrilla : null,
            ]);
        } catch (Throwable $e) {
            error_log('[CERBERO] Error al crear usuario: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo crear el usuario.', 'status' => 500, 'data' => []];
        }

        return ['success' => true, 'message' => 'Usuario registrado con éxito.', 'status' => 201, 'data' => []];
    }

    public function update(array $input): array
    {
        $ci = trim($input['ci'] ?? $input['cedula'] ?? $input['id_usuario'] ?? $input['id'] ?? '');

        if ($ci === '') {
            return ['success' => false, 'message' => 'CI de usuario no especificado.', 'status' => 400, 'data' => []];
        }

        if (!$this->userModel->findByCi($ci)) {
            return ['success' => false, 'message' => 'Usuario no encontrado.', 'status' => 404, 'data' => []];
        }

        if (isset($input['email']) && !ValidationHelper::validEmail((string) $input['email'])) {
            return ['success' => false, 'message' => 'El correo electrónico no es válido.', 'status' => 400, 'data' => []];
        }
        if (!empty($input['password']) && !ValidationHelper::validPassword((string) $input['password'])) {
            return ['success' => false, 'message' => 'La contraseña debe tener entre 8 y 72 caracteres.', 'status' => 400, 'data' => []];
        }
        if (isset($input['role'])) {
            $role = $this->userModel->normalizeRoleName((string) $input['role']);
            if (!ValidationHelper::validRole($role)) {
                return ['success' => false, 'message' => 'El rol seleccionado no es válido.', 'status' => 400, 'data' => []];
            }
            $input['role'] = $role;
        }
        if (isset($input['estado']) && !ValidationHelper::validUserState((string) $input['estado'])) {
            return ['success' => false, 'message' => 'El estado seleccionado no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['id_cuadrilla']) && $input['id_cuadrilla'] !== '' && !$this->userModel->squadExists((int) $input['id_cuadrilla'])) {
            return ['success' => false, 'message' => 'La cuadrilla seleccionada no existe.', 'status' => 400, 'data' => []];
        }

        if (isset($input['email'])) {
            $owner = $this->userModel->findByEmail((string) $input['email']);
            if ($owner && (string) ($owner['id'] ?? '') !== $ci) {
                return ['success' => false, 'message' => 'El correo ya pertenece a otro usuario.', 'status' => 409, 'data' => []];
            }
        }

        $this->userModel->update($ci, $input);

        return ['success' => true, 'message' => 'Usuario actualizado correctamente.', 'status' => 200, 'data' => []];
    }

    public function updateEstado(array $input): array
    {
        $ci = trim($input['ci'] ?? $input['id_usuario'] ?? $input['id'] ?? '');
        $estado = $input['estado'] ?? 'activo';

        if ($ci === '') {
            return ['success' => false, 'message' => 'CI de usuario no especificado.', 'status' => 400, 'data' => []];
        }

        if (!ValidationHelper::validUserState((string) $estado)) {
            return ['success' => false, 'message' => 'El estado seleccionado no es válido.', 'status' => 400, 'data' => []];
        }

        if (!$this->userModel->findByCi($ci)) {
            return ['success' => false, 'message' => 'Usuario no encontrado.', 'status' => 404, 'data' => []];
        }

        $this->userModel->updateEstado($ci, $estado);

        return ['success' => true, 'message' => 'Estado actualizado correctamente.', 'status' => 200, 'data' => []];
    }

    public function updateRol(array $input): array
    {
        $ci = trim($input['ci'] ?? $input['id_usuario'] ?? $input['id'] ?? '');
        $rol = $this->userModel->normalizeRoleName((string) ($input['rol'] ?? $input['role'] ?? 'vecino'));

        if ($ci === '') {
            return ['success' => false, 'message' => 'CI de usuario no especificado.', 'status' => 400, 'data' => []];
        }

        if (!ValidationHelper::validRole($rol)) {
            return ['success' => false, 'message' => 'Rol inválido.', 'status' => 400, 'data' => []];
        }

        if (!$this->userModel->findByCi($ci)) {
            return ['success' => false, 'message' => 'Usuario no encontrado.', 'status' => 404, 'data' => []];
        }

        $idRol = $this->userModel->findRoleIdByName($rol);

        if ($idRol === null) {
            return ['success' => false, 'message' => 'Rol inválido.', 'status' => 400, 'data' => []];
        }

        $this->userModel->updateRol($ci, $idRol);

        return ['success' => true, 'message' => 'Rol actualizado correctamente.', 'status' => 200, 'data' => []];
    }

    public function delete(array $input, array $actor = []): array
    {
        $ci = trim($input['ci'] ?? $input['id_usuario'] ?? $input['id'] ?? '');

        if ($ci === '') {
            return ['success' => false, 'message' => 'CI de usuario no especificado.', 'status' => 400, 'data' => []];
        }
        $ciActor = trim((string) ($actor['ci'] ?? $actor['id'] ?? ''));
        if ($ciActor !== '' && hash_equals($ciActor, $ci)) {
            return ['success' => false, 'message' => 'No podés eliminar la cuenta con la que tenés la sesión iniciada.', 'status' => 409, 'data' => []];
        }
        if ($ci === '00000000') {
            return ['success' => false, 'message' => 'La cuenta administrativa base está protegida.', 'status' => 409, 'data' => []];
        }

        if (!$this->userModel->delete($ci)) {
            return ['success' => false, 'message' => 'El usuario no existe.', 'status' => 404, 'data' => []];
        }

        return ['success' => true, 'message' => 'Usuario eliminado correctamente.', 'status' => 200, 'data' => []];
    }
}
