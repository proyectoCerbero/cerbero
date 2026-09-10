<?php

require_once __DIR__ . '/../config/db_connection.php';

class CuadrillaController
{
    private PDO $pdo;

    private const TURNOS = [
        '00:00-08:00' => ['00:00:00', '08:00:00'],
        '08:00-16:00' => ['08:00:00', '16:00:00'],
        '16:00-00:00' => ['16:00:00', '00:00:00'],
    ];

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.id_cuadrilla, c.nombre, c.turno, c.estado,
                    cr.id_ruta, r.nombre AS ruta_nombre,
                    cr.hora_inicio, cr.hora_fin
             FROM cuadrilla c
             LEFT JOIN cuadrilla_ruta cr
               ON cr.id_cuadrilla = c.id_cuadrilla
              AND cr.fecha = (
                    SELECT MAX(cr2reit.fecha)
                    FROM cuadrilla_ruta cr2reit
                    WHERE cr2reit.id_cuadrilla = c.id_cuadrilla
              )
             LEFT JOIN ruta_recoleccion r ON cr.id_ruta = r.id_ruta
             ORDER BY
                CASE WHEN c.nombre REGEXP "^CN[0-9]+$" THEN 0 ELSE 1 END,
                CAST(SUBSTRING(c.nombre, 3) AS UNSIGNED),
                c.id_cuadrilla'
        );

        $rutas = $this->pdo->query(
            "SELECT id_ruta, nombre
             FROM ruta_recoleccion
             WHERE nombre IN ('Ruta 1', 'Ruta 2', 'Ruta 3', 'Ruta 4', 'Ruta 5')
             ORDER BY CAST(SUBSTRING(nombre, 6) AS UNSIGNED)"
        )->fetchAll();

        return $this->response(true, 'Cuadrillas obtenidas con éxito.', 200, [
            'cuadrillas' => $stmt->fetchAll(),
            'rutas' => $rutas,
        ]);
    }

    public function create(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $turno = trim((string) ($input['turno'] ?? ''));
        $estado = trim((string) ($input['estado'] ?? 'activa'));
        $idRuta = (int) ($input['id_ruta'] ?? 0);

        if ($nombre === '') {
            return $this->response(false, 'El nombre de la cuadrilla es obligatorio.', 400);
        }
        if (!isset(self::TURNOS[$turno])) {
            return $this->response(false, 'El turno seleccionado no es válido.', 400);
        }
        if (!$this->rutaExiste($idRuta)) {
            return $this->response(false, 'La ruta seleccionada no existe.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO cuadrilla (nombre, turno, estado) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $turno, $estado]);
            $id = (int) $this->pdo->lastInsertId();
            $this->asignarRuta($id, $idRuta, $turno);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Cuadrilla creada correctamente.', 201, ['id_cuadrilla' => $id]);
    }

    public function update(array $input): array
    {
        $id = (int) ($input['id_cuadrilla'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'ID de cuadrilla no especificado.', 400);
        }

        $actual = $this->obtener($id);
        if (!$actual) {
            return $this->response(false, 'La cuadrilla no existe.', 404);
        }

        $turno = trim((string) ($input['turno'] ?? $actual['turno'] ?? ''));
        $idRuta = (int) ($input['id_ruta'] ?? $actual['id_ruta'] ?? 0);
        if (!isset(self::TURNOS[$turno])) {
            return $this->response(false, 'El turno seleccionado no es válido.', 400);
        }
        if (!$this->rutaExiste($idRuta)) {
            return $this->response(false, 'La ruta seleccionada no existe.', 400);
        }

        $nombre = trim((string) ($input['nombre'] ?? $actual['nombre']));
        $estado = trim((string) ($input['estado'] ?? $actual['estado']));
        if ($nombre === '') {
            return $this->response(false, 'El nombre de la cuadrilla es obligatorio.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE cuadrilla SET nombre = ?, turno = ?, estado = ? WHERE id_cuadrilla = ?'
            );
            $stmt->execute([$nombre, $turno, $estado, $id]);
            $this->asignarRuta($id, $idRuta, $turno);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Cuadrilla actualizada correctamente.', 200);
    }

    public function delete(array $input): array
    {
        $id = (int) ($input['id_cuadrilla'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'ID de cuadrilla no especificado.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            if (!$this->obtener($id)) {
                $this->pdo->rollBack();
                return $this->response(false, 'La cuadrilla no existe.', 404);
            }

            foreach (['usuario', 'incidencia', 'camion', 'traslado_residuo'] as $tabla) {
                if ($this->columnExists($tabla, 'id_cuadrilla')) {
                    $stmt = $this->pdo->prepare("UPDATE $tabla SET id_cuadrilla = NULL WHERE id_cuadrilla = ?");
                    $stmt->execute([$id]);
                }
            }
            $stmt = $this->pdo->prepare('DELETE FROM cuadrilla_ruta WHERE id_cuadrilla = ?');
            $stmt->execute([$id]);
            $stmt = $this->pdo->prepare('DELETE FROM cuadrilla WHERE id_cuadrilla = ?');
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Cuadrilla eliminada correctamente.', 200);
    }

    private function obtener(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, cr.id_ruta
             FROM cuadrilla c
             LEFT JOIN cuadrilla_ruta cr ON cr.id_cuadrilla = c.id_cuadrilla
             WHERE c.id_cuadrilla = ?
             ORDER BY cr.fecha DESC
             LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function rutaExiste(int $idRuta): bool
    {
        if ($idRuta <= 0) return false;
        $stmt = $this->pdo->prepare('SELECT 1 FROM ruta_recoleccion WHERE id_ruta = ?');
        $stmt->execute([$idRuta]);
        return $stmt->fetchColumn() !== false;
    }

    private function asignarRuta(int $idCuadrilla, int $idRuta, string $turno): void
    {
        [$horaInicio, $horaFin] = self::TURNOS[$turno];
        $stmt = $this->pdo->prepare('DELETE FROM cuadrilla_ruta WHERE id_cuadrilla = ?');
        $stmt->execute([$idCuadrilla]);
        $stmt = $this->pdo->prepare(
            "INSERT INTO cuadrilla_ruta
             (id_cuadrilla, id_ruta, fecha, hora_inicio, hora_fin, estado)
             VALUES (?, ?, CURDATE(), ?, ?, 'asignada')"
        );
        $stmt->execute([$idCuadrilla, $idRuta, $horaInicio, $horaFin]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
