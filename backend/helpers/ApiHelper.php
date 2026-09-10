<?php

class ApiHelper
{
    public static function init(array $methods = ['GET', 'POST', 'OPTIONS']): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (getenv('CERBERO_ALLOWED_ORIGINS') ?: ''))
        )));

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public static function serverError(Throwable $error, array $emptyData = []): array
    {
        error_log(sprintf(
            '[CERBERO] %s en %s:%d',
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        ));

        return [
            'success' => false,
            'message' => 'Ocurrió un error interno. Intente nuevamente.',
            'status' => 500,
            'data' => $emptyData,
        ];
    }
}
