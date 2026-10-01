<?php

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

class NotificacionController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function listForUser(?array $user, string $role): array
    {
        $ci = trim((string) ($user['id'] ?? $user['ci'] ?? ''));
        if ($ci === '') {
            return $this->response(true, 'Notificaciones obtenidas.', 200, [
                'notificaciones' => [[
                    'id_notificacion' => 0,
                    'titulo' => 'Acceso público',
                    'mensaje' => 'Iniciá sesión para recibir notificaciones personales.',
                    'fecha_hora' => null,
                    'estado' => 'leída',
                ]],
                'no_leidas' => 0,
            ]);
        }

        NotificationService::initialForRole($this->pdo, $ci, $role);
        $stmt = $this->pdo->prepare(
            'SELECT id_notificacion, titulo, mensaje, fecha_hora, estado
             FROM notificacion
             WHERE ci = ?
             ORDER BY fecha_hora DESC, id_notificacion DESC
             LIMIT 12'
        );
        $stmt->execute([$ci]);
        $notifications = $stmt->fetchAll();
        $unread = array_reduce($notifications, static fn (int $total, array $item): int =>
            $total + (strtolower((string) ($item['estado'] ?? '')) !== 'leída' ? 1 : 0), 0);

        return $this->response(true, 'Notificaciones obtenidas.', 200, [
            'notificaciones' => $notifications,
            'no_leidas' => $unread,
        ]);
    }

    public function markAllRead(array $user): array
    {
        $ci = trim((string) ($user['id'] ?? $user['ci'] ?? ''));
        if ($ci === '') {
            return $this->response(false, 'Debés iniciar sesión para actualizar las notificaciones.', 401);
        }

        $stmt = $this->pdo->prepare('UPDATE notificacion SET estado = ? WHERE ci = ? AND estado <> ?');
        $stmt->execute(['leída', $ci, 'leída']);

        return $this->response(true, 'Notificaciones marcadas como leídas.', 200, ['no_leidas' => 0]);
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
