<?php

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

class ContenedorController
{
    private PDO $pdo;
    private const ESTADOS = ['en buen estado', 'lleno', 'roto'];

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.*, u.barrio, u.calle, u.numero, u.latitud, u.longitud,
                    tr.nombre AS tipo_residuo
             FROM contenedor c
             LEFT JOIN ubicacion u ON c.id_ubicacion = u.id_ubicacion
             LEFT JOIN tipo_residuo tr ON c.id_tipo_residuo = tr.id_tipo_residuo
             ORDER BY c.id_contenedor DESC'
        );

        return $this->response(true, 'Contenedores obtenidos con éxito.', 200, [
            'contenedores' => $stmt->fetchAll()
        ]);
    }

    public function create(array $input, array $actor = []): array
    {
        $capacidad = $this->numberInRange($input['capacidad'] ?? null, 1, 100000);
        $nivelLlenado = $this->numberInRange($input['nivel_llenado'] ?? 0, 0, 100);
        $idUbicacion = $this->nullablePositiveInt($input['id_ubicacion'] ?? null);
        $idTipoResiduo = $this->nullablePositiveInt($input['id_tipo_residuo'] ?? null);
        $estado = trim((string) ($input['estado'] ?? 'en buen estado'));

        if ($capacidad === null) {
            return $this->response(false, 'La capacidad debe ser mayor que cero.', 400);
        }
        if ($nivelLlenado === null) {
            return $this->response(false, 'El porcentaje de llenado debe estar entre 0 y 100.', 400);
        }
        if ($idUbicacion === null || $idTipoResiduo === null) {
            return $this->response(false, 'La ubicación y el tipo de residuo deben ser identificadores positivos.', 400);
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            return $this->response(false, 'El estado debe ser: en buen estado, lleno o roto.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO contenedor
                 (capacidad, nivel_llenado, fecha_instalacion, estado, id_ubicacion, id_tipo_residuo)
                 VALUES (?, ?, CURDATE(), ?, ?, ?)'
            );
            $stmt->execute([$capacidad, $nivelLlenado, $estado, $idUbicacion, $idTipoResiduo]);
            $id = (int) $this->pdo->lastInsertId();
            $this->guardarHistorial($id, null, $nivelLlenado, 'registro inicial', $this->actorCi($actor));
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Contenedor creado correctamente.', 201, ['id_contenedor' => $id]);
    }

    public function update(array $input, array $actor = []): array
    {
        $id = (int) ($input['id_contenedor'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'ID de contenedor no especificado.', 400);
        }
        if (array_key_exists('capacidad', $input)
            && $this->numberInRange($input['capacidad'], 1, 100000) === null) {
            return $this->response(false, 'La capacidad debe estar entre 1 y 100000 litros.', 400);
        }
        if (array_key_exists('nivel_llenado', $input)
            && $this->numberInRange($input['nivel_llenado'], 0, 100) === null) {
            return $this->response(false, 'El porcentaje de llenado debe estar entre 0 y 100.', 400);
        }
        if (array_key_exists('estado', $input) && !in_array(trim((string) $input['estado']), self::ESTADOS, true)) {
            return $this->response(false, 'El estado debe ser: en buen estado, lleno o roto.', 400);
        }
        foreach (['id_ubicacion', 'id_tipo_residuo'] as $idField) {
            if (array_key_exists($idField, $input) && !ValidationHelper::validPositiveInt($input[$idField])) {
                return $this->response(false, 'La ubicación y el tipo de residuo deben ser identificadores positivos.', 400);
            }
        }

        $map = ['capacidad', 'nivel_llenado', 'estado', 'id_ubicacion', 'id_tipo_residuo', 'fecha_ultima_recoleccion'];
        $fields = [];
        $params = [];
        foreach ($map as $column) {
            if (!array_key_exists($column, $input)) continue;
            $fields[] = "$column = ?";
            $params[] = $input[$column] === '' ? null : $input[$column];
        }
        if ($fields === []) {
            return $this->response(false, 'No hay datos para actualizar.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $anterior = $this->obtenerNivel($id, true);
            if ($anterior === null) {
                $this->pdo->rollBack();
                return $this->response(false, 'El contenedor no existe.', 404);
            }
            $params[] = $id;
            $stmt = $this->pdo->prepare(
                'UPDATE contenedor SET ' . implode(', ', $fields) . ' WHERE id_contenedor = ?'
            );
            $stmt->execute($params);

            if (array_key_exists('nivel_llenado', $input)) {
                $nuevo = (float) $input['nivel_llenado'];
                if (abs($nuevo - $anterior) > 0.001) {
                    $this->guardarHistorial($id, $anterior, $nuevo, 'actualización', $this->actorCi($actor));
                }
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $estadoNuevo = strtolower(trim((string) ($input['estado'] ?? '')));
        $nivelNuevo = isset($input['nivel_llenado']) ? (float) $input['nivel_llenado'] : null;
        if (in_array($estadoNuevo, ['lleno', 'roto'], true) || ($nivelNuevo !== null && $nivelNuevo >= 80)) {
            $motivo = $estadoNuevo === 'roto' ? 'fue marcado como roto' : ($estadoNuevo === 'lleno' ? 'fue marcado como lleno' : 'superó el 80 % de llenado');
            NotificationService::notifyRole($this->pdo, 'administrador municipal', 'Alerta de contenedor', "El contenedor #$id $motivo.");
            NotificationService::notifyRole($this->pdo, 'cuadrilla de recolección', 'Contenedor a atender', "El contenedor #$id $motivo.");
        }

        return $this->response(true, 'Contenedor actualizado correctamente.', 200);
    }

    public function clean(array $input, array $actor = []): array
    {
        $id = (int) ($input['id_contenedor'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'ID de contenedor no especificado.', 400);
        }

        $observadoInput = $input['nivel_llenado_antes'] ?? $input['nivel_llenado'] ?? null;
        if ($observadoInput !== null && $this->numberInRange($observadoInput, 0, 100) === null) {
            return $this->response(false, 'El porcentaje observado debe estar entre 0 y 100.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $nivelActual = $this->obtenerNivel($id, true);
            if ($nivelActual === null) {
                $this->pdo->rollBack();
                return $this->response(false, 'El contenedor no existe.', 404);
            }
            $nivelObservado = $observadoInput === null ? $nivelActual : (float) $observadoInput;
            $this->guardarHistorial($id, $nivelObservado, 0.0, 'limpieza', $this->actorCi($actor));

            $stmt = $this->pdo->prepare(
                "UPDATE contenedor
                 SET nivel_llenado = 0, fecha_ultima_recoleccion = CURDATE(), estado = 'en buen estado'
                 WHERE id_contenedor = ?"
            );
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Limpieza registrada. El contenedor quedó en 0 %.', 200);
    }

    public function history(int $id): array
    {
        if ($id <= 0) {
            return $this->response(false, 'ID de contenedor no especificado.', 400);
        }
        $stmt = $this->pdo->prepare(
            'SELECT h.id_historial, h.id_contenedor, h.porcentaje_antes,
                    h.porcentaje_despues, h.fecha_hora, h.accion, h.ci_usuario,
                    TRIM(CONCAT(COALESCE(u.nombre, ""), " ", COALESCE(u.apellido, ""))) AS usuario_nombre
             FROM historial_llenado h
             LEFT JOIN usuario u ON h.ci_usuario = u.ci
             WHERE h.id_contenedor = ?
             ORDER BY h.fecha_hora DESC, h.id_historial DESC'
        );
        $stmt->execute([$id]);
        return $this->response(true, 'Historial obtenido correctamente.', 200, [
            'historial' => $stmt->fetchAll()
        ]);
    }

    public function delete(array $input): array
    {
        $id = (int) ($input['id_contenedor'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'ID de contenedor no especificado.', 400);
        }
        $this->pdo->beginTransaction();
        try {
            if ($this->obtenerNivel($id, true) === null) {
                $this->pdo->rollBack();
                return $this->response(false, 'El contenedor no existe.', 404);
            }
            foreach (['incidencia', 'reparacion_contenedor'] as $tabla) {
                if ($this->columnExists($tabla, 'id_contenedor')) {
                    $stmt = $this->pdo->prepare("UPDATE $tabla SET id_contenedor = NULL WHERE id_contenedor = ?");
                    $stmt->execute([$id]);
                }
            }
            if ($this->columnExists('historial_llenado', 'id_contenedor')) {
                $stmt = $this->pdo->prepare('DELETE FROM historial_llenado WHERE id_contenedor = ?');
                $stmt->execute([$id]);
            }
            $stmt = $this->pdo->prepare('DELETE FROM contenedor WHERE id_contenedor = ?');
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return $this->response(true, 'Contenedor eliminado correctamente.', 200);
    }

    private function obtenerNivel(int $id, bool $bloquear = false): ?float
    {
        $sql = 'SELECT nivel_llenado FROM contenedor WHERE id_contenedor = ?' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : (float) $valor;
    }

    private function guardarHistorial(int $id, ?float $antes, float $despues, string $accion, ?string $ci): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO historial_llenado
             (id_contenedor, porcentaje_antes, porcentaje_despues, fecha_hora, accion, ci_usuario)
             VALUES (?, ?, ?, NOW(), ?, ?)'
        );
        $stmt->execute([$id, $antes, $despues, $accion, $ci]);
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

    private function actorCi(array $actor): ?string
    {
        $ci = trim((string) ($actor['ci'] ?? $actor['id'] ?? ''));
        return $ci !== '' ? $ci : null;
    }

    private function nullablePositiveInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number !== false && $number > 0 ? $number : null;
    }

    private function numberInRange($value, float $minimum, ?float $maximum): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $number = (float) $value;
        if ($number < $minimum || ($maximum !== null && $number > $maximum)) return null;
        return $number;
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
