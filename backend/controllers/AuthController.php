<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class AuthController
{
    private UserModel $userModel;

    public function __construct(array $config)
    {
        $this->userModel = new UserModel($config);
    }

    public function register(array $input): array
    {
        $nombre = trim($input['nombre'] ?? '');
        $apellido = trim($input['apellido'] ?? '');
        $cedula = trim($input['cedula'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($nombre === '' || $apellido === '' || $cedula === '' || $email === '' || $password === '') {
            return $this->jsonResponse(false, 'Todos los campos son obligatorios.', 400);
        }

        if (!ValidationHelper::validName($nombre) || !ValidationHelper::validName($apellido)) {
            return $this->jsonResponse(false, 'Nombre y apellido no son válidos.', 400);
        }

        if (!ValidationHelper::validCi($cedula)) {
            return $this->jsonResponse(false, 'La cédula debe contener entre 7 y 8 números.', 400);
        }

        if (!ValidationHelper::validEmail($email)) {
            return $this->jsonResponse(false, 'El correo electrónico no es válido.', 400);
        }

        if (!ValidationHelper::validPassword($password)) {
            return $this->jsonResponse(false, 'La contraseña debe tener entre 8 y 72 caracteres.', 400);
        }

        if ($this->userModel->findByEmail($email)) {
            return $this->jsonResponse(false, 'El correo ya está registrado.', 409);
        }

        if ($this->userModel->findByCi($cedula)) {
            return $this->jsonResponse(false, 'La cédula ya está registrada.', 409);
        }

        $user = $this->userModel->create([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'cedula' => $cedula,
            'email' => $email,
            'password' => $password,
            'role' => 'vecino'
        ]);

        return $this->jsonResponse(true, 'Usuario registrado correctamente.', 201, [
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'role' => 'vecino'
            ],
            'redirect' => 'index.html'
        ]);
    }

    public function login(array $input): array
    {
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            return $this->jsonResponse(false, 'Correo y contraseña son obligatorios.', 400);
        }

        if (!ValidationHelper::validEmail($email)) {
            return $this->jsonResponse(false, 'El correo electrónico no es válido.', 400);
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'] ?? '')) {
            return $this->jsonResponse(false, 'Credenciales inválidas.', 401);
        }

        if (strtolower(trim((string) ($user['estado'] ?? 'activo'))) !== 'activo') {
            return $this->jsonResponse(false, 'La cuenta se encuentra inactiva. Contacte a un administrador.', 403);
        }

        return $this->jsonResponse(true, 'Inicio de sesión correcto.', 200, [
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'vecino'
            ],
            'redirect' => 'index.html'
        ]);
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
