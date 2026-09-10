<?php

class ValidationHelper
{
    public const ROLES = [
        'vecino',
        'cuadrilla de recolección',
        'operario de centro',
        'administrador municipal',
    ];

    public const USER_STATES = ['activo', 'inactivo'];

    public static function validCi(string $ci): bool
    {
        return preg_match('/^[0-9]{7,8}$/', trim($ci)) === 1;
    }

    public static function validEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false
            && strlen(trim($email)) <= 100;
    }

    public static function validName(string $value): bool
    {
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        return $value !== '' && $length <= 50;
    }

    public static function validPassword(string $password): bool
    {
        $length = strlen($password);
        return $length >= 8 && $length <= 72;
    }

    public static function validRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function validUserState(string $state): bool
    {
        return in_array(strtolower(trim($state)), self::USER_STATES, true);
    }
}
