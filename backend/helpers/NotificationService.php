<?php

class NotificationService
{
    public static function create(PDO $pdo, string $ci, string $title, string $message): void
    {
        if ($ci === '') {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO notificacion (titulo, mensaje, fecha_hora, estado, ci) VALUES (?, ?, NOW(), ?, ?)'
        );
        $stmt->execute([$title, $message, 'no leída', $ci]);
    }

    public static function notifyRole(PDO $pdo, string $role, string $title, string $message): void
    {
        $stmt = $pdo->prepare(
            'SELECT u.ci
             FROM usuario u
             INNER JOIN rol r ON r.id_rol = u.id_rol
             WHERE LOWER(r.nombre) = LOWER(?) AND LOWER(COALESCE(u.estado, "activo")) = "activo"'
        );
        $stmt->execute([$role]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ci) {
            self::create($pdo, (string) $ci, $title, $message);
        }
    }

    public static function initialForRole(PDO $pdo, string $ci, string $role): void
    {
        $count = $pdo->prepare('SELECT COUNT(*) FROM notificacion WHERE ci = ?');
        $count->execute([$ci]);
        if ((int) $count->fetchColumn() > 0) {
            return;
        }

        $messages = [
            'administrador municipal' => ['Panel administrativo', 'Revisá las incidencias y los contenedores que requieren atención.'],
            'cuadrilla de recolección' => ['Operación diaria', 'Consultá tu ruta y registrá las limpiezas realizadas durante el turno.'],
            'operario de centro' => ['Centro de acopio', 'Verificá los ingresos, la maquinaria y la capacidad disponible del centro.'],
            'vecino' => ['Seguimiento de incidencias', 'Desde Incidencias podés registrar un reporte y seguir sus cambios de estado.'],
        ];

        [$title, $message] = $messages[$role] ?? ['Bienvenido a Cerbero', 'Iniciá sesión para consultar las novedades del sistema.'];
        self::create($pdo, $ci, $title, $message);
    }
}
