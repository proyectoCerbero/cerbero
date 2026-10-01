<?php

class AuthHelper
{
    /**
     * Roles reales tal cual se guardan en la tabla `rol`.
     * 'visitante' no existe en la BD: representa a alguien sin sesión iniciada.
     */
    public const ROL_VECINO = 'vecino';
    public const ROL_CUADRILLA = 'cuadrilla de recolección';
    public const ROL_OPERARIO = 'operario de centro';
    public const ROL_ADMIN = 'administrador municipal';
    public const ROL_VISITANTE = 'visitante';

    /** Alias cortos -> nombre real de rol, para poder llamar requireRole(['admin']) desde los endpoints */
    private static array $aliases = [
        'admin' => self::ROL_ADMIN,
        'administrador' => self::ROL_ADMIN,
        'administrador municipal' => self::ROL_ADMIN,
        'cuadrilla' => self::ROL_CUADRILLA,
        'cuadrilla de recoleccion' => self::ROL_CUADRILLA,
        'cuadrilla de recolección' => self::ROL_CUADRILLA,
        'miembro de cuadrilla' => self::ROL_CUADRILLA,
        'operario' => self::ROL_OPERARIO,
        'operario de centro' => self::ROL_OPERARIO,
        'operario de instalacion' => self::ROL_OPERARIO,
        'operario de instalación' => self::ROL_OPERARIO,
        'vecino' => self::ROL_VECINO,
        'visitante' => self::ROL_VISITANTE,
        'guest' => self::ROL_VISITANTE,
    ];

    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function normalizeRole(?string $role): string
    {
        $key = strtolower(trim((string) $role));

        return self::$aliases[$key] ?? self::ROL_VISITANTE;
    }

    public static function getLoggedUser(): ?array
    {
        self::initSession();
        return $_SESSION['user'] ?? null;
    }

    public static function getCurrentRole(): string
    {
        $user = self::getLoggedUser();
        return self::normalizeRole($user['role'] ?? null);
    }

    public static function login(array $user): void
    {
        self::initSession();
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        $_SESSION['user'] = $user;
    }

    public static function csrfToken(): string
    {
        self::initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function requireCsrfToken(): void
    {
        self::initSession();
        $received = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $expected = (string) ($_SESSION['csrf_token'] ?? '');

        if ($received === '' || $expected === '' || !hash_equals($expected, $received)) {
            self::deny('La sesión de seguridad venció. Actualice la página e intente nuevamente.', 419);
        }
    }

    public static function logout(): void
    {
        self::initSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Exige que haya una sesión iniciada (cualquier rol autenticado, es decir,
     * cualquiera menos 'visitante').
     */
    public static function requireLogin(): array
    {
        $user = self::getLoggedUser();

        if (!$user) {
            self::deny('Debe iniciar sesión para realizar esta acción.', 401);
        }

        return $user;
    }

    /**
     * Exige que el usuario logueado tenga uno de los roles permitidos.
     * Acepta tanto alias cortos ('admin') como nombres reales ('administrador municipal').
     * Si 'visitante' no está en la lista permitida, se deniega a quien no tenga sesión.
     */
    public static function requireRole(array $allowedRoles): array
    {
        self::initSession();

        $normalizedAllowed = array_map([self::class, 'normalizeRole'], $allowedRoles);
        $currentRole = self::getCurrentRole();

        if (!self::roleAllowed($currentRole, $normalizedAllowed)) {
            self::deny('Acceso denegado: no posee los permisos requeridos para esta acción.', 403);
        }

        return self::getLoggedUser() ?? [];
    }

    public static function roleAllowed(?string $role, array $allowedRoles): bool
    {
        $currentRole = self::normalizeRole($role);
        $normalizedAllowed = array_map([self::class, 'normalizeRole'], $allowedRoles);
        return in_array($currentRole, $normalizedAllowed, true);
    }

    private static function deny(string $message, int $status = 403): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'status' => $status,
            'data' => []
        ]);
        exit;
    }
}
