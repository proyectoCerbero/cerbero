<?php

/**
 * Carga variables locales desde el archivo .env del proyecto.
 *
 * Está pensado para XAMPP: PHP no carga archivos .env por sí solo. Las
 * variables definidas por Apache o el sistema tienen prioridad sobre este
 * archivo local.
 */
function cerbero_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

cerbero_load_env();
