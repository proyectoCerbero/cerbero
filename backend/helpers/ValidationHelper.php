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
        return $length >= 2
            && $length <= 50
            && preg_match("/^[\\p{L}][\\p{L} '\\x{2019}-]*$/u", $value) === 1;
    }

    public static function validEntityName(string $value, int $maximum = 80, int $minimum = 2): bool
    {
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        return $length >= $minimum
            && $length <= $maximum
            && preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} .,+#'()_\\-\/]*$/u", $value) === 1;
    }

    public static function validPlate(string $value): bool
    {
        return preg_match('/^[A-Z0-9]{2,4}-?[A-Z0-9]{3,4}$/', strtoupper(trim($value))) === 1;
    }

    public static function validPhone(string $value): bool
    {
        $value = trim($value);
        return $value === '' || preg_match('/^[+0-9 ()-]{7,25}$/', $value) === 1;
    }

    public static function validSchedule(string $value): bool
    {
        $value = trim($value);
        if ($value === '') return true;
        if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](?: ?- ?([01][0-9]|2[0-3]):[0-5][0-9])?$/', $value) !== 1) {
            return false;
        }
        return true;
    }

    public static function validPositiveInt($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0;
    }

    public static function validNumberRange($value, float $minimum, float $maximum): bool
    {
        return is_numeric($value) && (float) $value >= $minimum && (float) $value <= $maximum;
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
