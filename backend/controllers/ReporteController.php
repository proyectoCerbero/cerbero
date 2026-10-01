<?php

require_once __DIR__ . '/../config/db_connection.php';

class ReporteController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function incidencias(array $filters): array
    {
        $estado = strtolower(trim((string) ($filters['estado'] ?? 'todas')));
        $desde = trim((string) ($filters['desde'] ?? ''));
        $hasta = trim((string) ($filters['hasta'] ?? ''));
        $where = [];
        $params = [];

        if (in_array($estado, ['abiertas', 'cerradas'], true)) {
            $where[] = $estado === 'abiertas' ? "LOWER(i.estado) <> 'solucionado'" : "LOWER(i.estado) = 'solucionado'";
        }
        if ($this->validDate($desde)) { $where[] = 'DATE(i.fecha_hora_registro) >= ?'; $params[] = $desde; }
        if ($this->validDate($hasta)) { $where[] = 'DATE(i.fecha_hora_registro) <= ?'; $params[] = $hasta; }
        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = 'SELECT i.id_incidencia, i.descripcion, i.estado, i.ubicacion, i.fecha_hora_registro, i.fecha_hora_cierre, CONCAT(u.nombre, " ", u.apellido) AS vecino
                FROM incidencia i LEFT JOIN usuario u ON u.ci = i.ci' . $condition . ' ORDER BY i.fecha_hora_registro DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $summary = $this->pdo->query("SELECT COUNT(*) AS total, SUM(LOWER(estado) = 'solucionado') AS cerradas, SUM(LOWER(estado) <> 'solucionado') AS abiertas FROM incidencia")->fetch();
        $total = (int) ($summary['total'] ?? 0);
        $closed = (int) ($summary['cerradas'] ?? 0);

        return $this->response(true, 'Informe generado.', 200, [
            'resumen' => ['total' => $total, 'abiertas' => (int) ($summary['abiertas'] ?? 0), 'cerradas' => $closed, 'resolucion' => $total ? round($closed * 100 / $total, 1) : 0],
            'incidencias' => $stmt->fetchAll(),
            'contenedores_criticos' => $this->topContenedores(),
        ]);
    }

    private function topContenedores(): array
    {
        return $this->pdo->query(
            "SELECT c.id_contenedor, MAX(h.porcentaje_antes) AS maximo_llenado, COUNT(h.id_historial) AS registros
             FROM historial_llenado h INNER JOIN contenedor c ON c.id_contenedor = h.id_contenedor
             WHERE h.fecha_hora >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             GROUP BY c.id_contenedor
             HAVING MAX(h.porcentaje_antes) >= 80
             ORDER BY maximo_llenado DESC, registros DESC LIMIT 5"
        )->fetchAll();
    }

    private function validDate(string $date): bool
    {
        return $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
