<?php

require_once __DIR__ . '/../config/db_connection.php';

class DashboardController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function metrics(bool $includeRecentActivity = false): array
    {
        return [
            'success' => true,
            'message' => 'Estadísticas obtenidas correctamente.',
            'status' => 200,
            'data' => [
                'incidencias_abiertas' => $this->countByStatus('incidencia', ['abierta', 'abierto', 'pendiente', 'nuevo', 'en proceso', 'a solucionar']),
                'incidencias_solucionadas' => $this->countByStatus('incidencia', ['solucionado']),
                'contenedores_activos' => $this->countByStatus('contenedor', ['activo', 'operativo', 'funcional', 'disponible', 'en buen estado']),
                'camiones_operativos' => $this->countByStatus('camion', ['activo', 'operativo', 'disponible']),
                'toneladas_recolectadas' => $this->toneladasRecolectadas(),
                'recent_activity' => $includeRecentActivity ? $this->recentActivity() : [],
                'contenedores' => $this->latestContenedores(),
                'camiones' => $this->latestCamiones(),
                'centros_acopio' => $this->centrosAcopio(),
                'rutas_centros' => $this->rutasCentros(),
                'rutas_base' => $this->rutasBase(),
                'porcentaje_resolucion' => $this->resolutionRate(),
                'contenedores_criticos' => $this->topContenedores(),
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

    private function toneladasRecolectadas(): float
    {
        // registro_recoleccion guarda kilogramos; el panel muestra toneladas.
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(cantidad), 0) / 1000 FROM registro_recoleccion');
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function resolutionRate(): float
    {
        $row = $this->pdo->query("SELECT COUNT(*) AS total, SUM(LOWER(estado) = 'solucionado') AS solucionadas FROM incidencia")->fetch();
        $total = (int) ($row['total'] ?? 0);
        return $total ? round(((int) ($row['solucionadas'] ?? 0)) * 100 / $total, 1) : 0;
    }

    private function topContenedores(): array
    {
        return $this->pdo->query(
            "SELECT h.id_contenedor, MAX(h.porcentaje_antes) AS porcentaje
             FROM historial_llenado h
             WHERE h.fecha_hora >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             GROUP BY h.id_contenedor
             HAVING MAX(h.porcentaje_antes) >= 80
             ORDER BY porcentaje DESC LIMIT 5"
        )->fetchAll();
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

    private function rutasBase(): array
    {
        $stmt = $this->pdo->query(
            "SELECT r.nombre AS ruta, c.id_contenedor AS id, c.estado, c.nivel_llenado, c.capacidad,
                    u.barrio, u.calle, u.latitud, u.longitud
             FROM ruta_recoleccion r
             INNER JOIN ruta_ubicacion ru ON ru.id_ruta = r.id_ruta
             INNER JOIN ubicacion u ON u.id_ubicacion = ru.id_ubicacion
             INNER JOIN contenedor c ON c.id_ubicacion = u.id_ubicacion
             WHERE r.nombre IN ('Ruta 1','Ruta 2','Ruta 3','Ruta 4','Ruta 5')
             ORDER BY r.id_ruta, ru.orden, c.id_contenedor"
        );
        $routes = [];
        foreach ($stmt->fetchAll() as $row) {
            $routes[$row['ruta']][] = [
                'id' => (int) $row['id'],
                'zona' => trim((string) $row['barrio'] . ' - ' . (string) $row['calle']),
                'estado' => $row['estado'],
                'nivel_llenado' => (float) $row['nivel_llenado'],
                'capacidad' => (float) $row['capacidad'],
                'latitud' => (float) $row['latitud'],
                'longitud' => (float) $row['longitud'],
            ];
        }
        return $routes;
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
