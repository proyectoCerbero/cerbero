<?php

require_once __DIR__ . '/../config/db_connection.php';

class DashboardController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function metrics(): array
    {
        return [
            'success' => true,
            'message' => 'Estadísticas obtenidas correctamente.',
            'status' => 200,
            'data' => [
                'incidencias_abiertas' => $this->countByStatus('incidencia', ['abierta', 'abierto', 'pendiente', 'nuevo', 'en proceso', 'a solucionar']),
                'contenedores_activos' => $this->countByStatus('contenedor', ['activo', 'operativo', 'funcional', 'disponible', 'en buen estado']),
                'camiones_operativos' => $this->countByStatus('camion', ['activo', 'operativo', 'disponible']),
                'toneladas_recolectadas' => $this->sumCantidad('traslado_residuo'),
                'recent_activity' => $this->recentActivity(),
                'contenedores' => $this->latestContenedores(),
                'camiones' => $this->latestCamiones(),
                'centros_acopio' => $this->centrosAcopio(),
                'rutas_centros' => $this->rutasCentros(),
            ]
        ];
    }

    private function countByStatus(string $table, array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE LOWER(COALESCE(estado, "")) IN (%s)', $table, $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map('strtolower', $statuses));

        return (int) $stmt->fetchColumn();
    }

    private function sumCantidad(string $table): float
    {
        $stmt = $this->pdo->query(sprintf('SELECT COALESCE(SUM(COALESCE(cantidad, 0)), 0) FROM %s', $table));

        return (float) $stmt->fetchColumn();
    }

    private function recentActivity(): array
    {
        $activities = [];

        $stmt = $this->pdo->query('SELECT nombre, apellido, fecha_registro FROM usuario ORDER BY fecha_registro DESC, ci DESC LIMIT 2');
        while ($row = $stmt->fetch()) {
            $activities[] = [
                'type' => 'usuario',
                'text' => sprintf('Nuevo usuario registrado: %s %s', trim($row['nombre']), trim($row['apellido'])),
                'date' => $row['fecha_registro'],
            ];
        }

        $stmt = $this->pdo->query('SELECT id_incidencia, descripcion, fecha_hora_registro, estado FROM incidencia ORDER BY fecha_hora_registro DESC, id_incidencia DESC LIMIT 2');
        while ($row = $stmt->fetch()) {
            $activities[] = [
                'type' => 'incidencia',
                'text' => sprintf('Incidencia %d (%s): %s', (int) $row['id_incidencia'], strtoupper((string) $row['estado']), substr((string) $row['descripcion'], 0, 70)),
                'date' => $row['fecha_hora_registro'],
            ];
        }

        $stmt = $this->pdo->query('SELECT id_contenedor, estado FROM contenedor ORDER BY id_contenedor DESC LIMIT 2');
        while ($row = $stmt->fetch()) {
            $activities[] = [
                'type' => 'contenedor',
                'text' => sprintf('Contenedor #%d actualizado con estado %s', (int) $row['id_contenedor'], (string) $row['estado']),
                'date' => null,
            ];
        }

        $stmt = $this->pdo->query('SELECT id_camion, matricula, estado FROM camion ORDER BY id_camion DESC LIMIT 2');
        while ($row = $stmt->fetch()) {
            $activities[] = [
                'type' => 'camion',
                'text' => sprintf('Camión %s actualizado con estado %s', (string) $row['matricula'], (string) $row['estado']),
                'date' => null,
            ];
        }

        usort($activities, function (array $a, array $b): int {
            $dateA = (string) ($a['date'] ?? '');
            $dateB = (string) ($b['date'] ?? '');
            return strcmp($dateB, $dateA);
        });

        return array_slice($activities, 0, 4);
    }

    private function latestContenedores(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.id_contenedor, c.capacidad, c.nivel_llenado, c.estado,
                    u.barrio, u.calle, u.numero, u.latitud, u.longitud
             FROM contenedor c
             INNER JOIN ubicacion u ON c.id_ubicacion = u.id_ubicacion
             WHERE u.latitud IS NOT NULL AND u.longitud IS NOT NULL
             ORDER BY c.id_contenedor DESC
             LIMIT 50'
        );

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id_contenedor'],
                'zona' => trim((string) ($row['barrio'] ?? 'Sin zona')) . (trim((string) ($row['calle'] ?? '')) !== '' ? ' - ' . trim((string) $row['calle']) : ''),
                'estado' => (string) ($row['estado'] ?? '-'),
                'capacidad' => (float) ($row['capacidad'] ?? 0),
                'nivel_llenado' => (float) ($row['nivel_llenado'] ?? 0),
                'latitud' => $row['latitud'] !== null ? (float) $row['latitud'] : null,
                'longitud' => $row['longitud'] !== null ? (float) $row['longitud'] : null,
            ];
        }, $stmt->fetchAll());
    }

    private function latestCamiones(): array
    {
        $stmt = $this->pdo->query('SELECT id_camion, matricula, estado FROM camion ORDER BY id_camion DESC LIMIT 3');

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id_camion'],
                'matricula' => (string) ($row['matricula'] ?? '-'),
                'estado' => (string) ($row['estado'] ?? '-'),
            ];
        }, $stmt->fetchAll());
    }

    private function centrosAcopio(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_instalacion AS id, nombre, calle, numero, latitud, longitud
             FROM instalacion
             WHERE nombre IN ('ECOCENTRO BUCEO', 'Reciclo NFU', 'Centro de Acopio Disco')
               AND latitud IS NOT NULL AND longitud IS NOT NULL
             ORDER BY id_instalacion"
        );
        return $stmt->fetchAll();
    }

    private function rutasCentros(): array
    {
        $stmt = $this->pdo->query(
            "SELECT r.nombre AS ruta_nombre, i.id_instalacion AS id, i.nombre,
                    i.calle, i.numero, i.latitud, i.longitud
             FROM ruta_recoleccion r
             INNER JOIN instalacion i ON i.id_instalacion = r.id_instalacion_base
             WHERE r.nombre IN ('Ruta 1', 'Ruta 2', 'Ruta 3', 'Ruta 4', 'Ruta 5')
             ORDER BY CAST(SUBSTRING(r.nombre, 6) AS UNSIGNED)"
        );
        return $stmt->fetchAll();
    }
}
