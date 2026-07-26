<?php

require_once __DIR__ . '/../models/UserModel.php';

class UsuarioController
{
    private UserModel $userModel;

    /** Roles válidos que puede asignar un administrador. */
    private const ROLES_VALIDOS = ['vecino', 'cuadrilla de recolección', 'operario de centro', 'administrador municipal', 'cuadrilla', 'operario', 'admin'];

    /** Estados válidos de una cuenta. */
    private const ESTADOS_VALIDOS = ['activo', 'inactivo', 'suspendido'];

    public function __construct(array $config)
    {
        $this->userModel = new UserModel($config);
    }

    public function list(): array
    {
        $usuarios = $this->userModel->findAll();

        return $this->jsonResponse(true, 'Usuarios obtenidos correctamente.', 200, [
            'usuarios' => $usuarios
        ]);
    }

    public function updateEstado(array $input): array
    {
        $ci = trim($input['ci'] ?? '');
        $estado = trim($input['estado'] ?? '');

        if ($ci === '' || $estado === '') {
            return $this->jsonResponse(false, 'ci y estado son obligatorios.', 400);
        }

        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            return $this->jsonResponse(
                false,
                'Estado inválido. Valores permitidos: ' . implode(', ', self::ESTADOS_VALIDOS) . '.',
                400
            );
        }

        if (!$this->userModel->findByCi($ci)) {
            return $this->jsonResponse(false, 'Usuario no encontrado.', 404);
        }

        $this->userModel->updateEstado($ci, $estado);

        return $this->jsonResponse(true, 'Estado actualizado correctamente.', 200, [
            'usuario' => $this->userModel->findByCi($ci)
        ]);
    }

    public function updateRol(array $input): array
    {
        $ci = trim($input['ci'] ?? '');
        $rol = trim($input['rol'] ?? '');
        $requesterRole = trim($input['requester_role'] ?? '');

        if ($ci === '' || $rol === '') {
            return $this->jsonResponse(false, 'ci y rol son obligatorios.', 400);
        }

        if (!$this->isAdminRole($requesterRole)) {
            return $this->jsonResponse(false, 'Solo un administrador puede cambiar roles.', 403);
        }

        $rolNormalizado = $this->userModel->normalizeRoleName($rol);

        if (!in_array($rol, self::ROLES_VALIDOS, true) && !in_array($rolNormalizado, self::ROLES_VALIDOS, true)) {
            return $this->jsonResponse(
                false,
                'Rol inválido. Valores permitidos: ' . implode(', ', self::ROLES_VALIDOS) . '.',
                400
            );
        }

        if (!$this->userModel->findByCi($ci)) {
            return $this->jsonResponse(false, 'Usuario no encontrado.', 404);
        }

        $idRol = $this->userModel->findRoleIdByName($rolNormalizado);

        if ($idRol === null) {
            return $this->jsonResponse(false, "El rol '$rol' no existe en la base de datos.", 500);
        }

        $this->userModel->updateRol($ci, $idRol);

        return $this->jsonResponse(true, 'Rol actualizado correctamente.', 200, [
            'usuario' => $this->userModel->findByCi($ci)
        ]);
    }

    private function isAdminRole(string $roleName): bool
    {
        $normalizedRole = $this->userModel->normalizeRoleName($roleName);

        return in_array($normalizedRole, ['administrador municipal', 'admin'], true);
    }

    private function jsonResponse(bool $success, string $message, int $status, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'status' => $status,
            'data' => $data
        ];
    }
}
