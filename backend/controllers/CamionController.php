<?php

require_once __DIR__ . '/../helpers/ValidationHelper.php';

class CamionController
{
    private PDO $pdo;
    private const ESTADOS = ['Operativo', 'Respaldo', 'En reparación', 'Inactivo'];

    public function __construct(array $config)
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function list(): array
    {
        $sql = 'SELECT c.*, q.nombre AS cuadrilla_nombre, q.turno AS cuadrilla_turno,
                       r.nombre AS ruta_nombre
                FROM camion c
                LEFT JOIN cuadrilla q ON c.id_cuadrilla = q.id_cuadrilla
                LEFT JOIN cuadrilla_ruta cr
                  ON cr.id_cuadrilla = q.id_cuadrilla
                 AND cr.fecha = (SELECT MAX(cr2.fecha) FROM cuadrilla_ruta cr2 WHERE cr2.id_cuadrilla = q.id_cuadrilla)
                LEFT JOIN ruta_recoleccion r ON r.id_ruta = cr.id_ruta
                ORDER BY c.id_camion DESC';
        $stmt = $this->pdo->query($sql);
        return [
            'success' => true,
            'message' => 'Camiones obtenidos con éxito.',
            'status' => 200,
            'data' => ['camiones' => $stmt->fetchAll()]
        ];
    }

    public function show(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, q.nombre AS cuadrilla_nombre FROM camion c LEFT JOIN cuadrilla q ON c.id_cuadrilla = q.id_cuadrilla WHERE c.id_camion = ?');
        $stmt->execute([$id]);
        $camion = $stmt->fetch();

        if (!$camion) {
            return ['success' => false, 'message' => 'Camión no encontrado.', 'status' => 404, 'data' => []];
        }

        return ['success' => true, 'message' => 'Camión obtenido con éxito.', 'status' => 200, 'data' => ['camion' => $camion]];
    }

    public function create(array $input): array
    {
        $matricula = strtoupper(trim($input['matricula'] ?? $input['patente'] ?? ''));
        $marca = trim($input['marca'] ?? '');
        $modelo = trim($input['modelo'] ?? '');
        $anio = !empty($input['anio']) ? (int) $input['anio'] : null;
        $kilometraje = isset($input['kilometraje']) && $input['kilometraje'] !== '' ? (int) $input['kilometraje'] : null;
        $capacidad = isset($input['capacidad']) ? (float) $input['capacidad'] : (isset($input['capacidad_carga']) ? (float) $input['capacidad_carga'] : null);
        $estado = trim((string) ($input['estado'] ?? 'Operativo'));
        $disponibilidad = isset($input['disponibilidad']) ? (bool) $input['disponibilidad'] : true;
        $idCuadrilla = !empty($input['id_cuadrilla']) ? (int) $input['id_cuadrilla'] : null;

        if ($matricula === '' || $marca === '' || $modelo === '') {
            return ['success' => false, 'message' => 'Matrícula, marca y modelo son obligatorios.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validPlate($matricula)) {
            return ['success' => false, 'message' => 'La matrícula debe tener un formato como ABC1234 o ABC-1234.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validEntityName($marca, 50) || !ValidationHelper::validEntityName($modelo, 50, 1)) {
            return ['success' => false, 'message' => 'La marca o el modelo contienen caracteres no permitidos.', 'status' => 400, 'data' => []];
        }
        if ($anio === null || $anio < 1950 || $anio > ((int) date('Y') + 1)) {
            return ['success' => false, 'message' => 'El año del camión no es válido.', 'status' => 400, 'data' => []];
        }
        if ($kilometraje !== null && $kilometraje < 0) {
            return ['success' => false, 'message' => 'El kilometraje no puede ser negativo.', 'status' => 400, 'data' => []];
        }
        if ($capacidad === null || $capacidad <= 0) {
            return ['success' => false, 'message' => 'La capacidad debe ser mayor que cero.', 'status' => 400, 'data' => []];
        }
        if ($capacidad > 1000 || ($kilometraje !== null && $kilometraje > 10000000)) {
            return ['success' => false, 'message' => 'La capacidad o el kilometraje superan el máximo permitido.', 'status' => 400, 'data' => []];
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            return ['success' => false, 'message' => 'El estado del camión no es válido.', 'status' => 400, 'data' => []];
        }
        if ($idCuadrilla !== null && !$this->cuadrillaExiste($idCuadrilla)) {
            return ['success' => false, 'message' => 'La cuadrilla seleccionada no existe.', 'status' => 400, 'data' => []];
        }
        if (!$this->matriculaDisponible($matricula)) {
            return ['success' => false, 'message' => 'Ya existe un camión con esa matrícula.', 'status' => 409, 'data' => []];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO camion (matricula, marca, modelo, anio, kilometraje, capacidad, estado, disponibilidad, id_cuadrilla)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$matricula, $marca, $modelo, $anio, $kilometraje, $capacidad, $estado, $disponibilidad, $idCuadrilla]);

        return [
            'success' => true,
            'message' => 'Camión registrado correctamente.',
            'status' => 201,
            'data' => ['id_camion' => $this->pdo->lastInsertId()]
        ];
    }

    public function update(array $input): array
    {
        $id = $input['id_camion'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de camión no especificado.', 'status' => 400, 'data' => []];
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM camion WHERE id_camion = ?');
        $exists->execute([$id]);
        if ($exists->fetchColumn() === false) {
            return ['success' => false, 'message' => 'El camión no existe.', 'status' => 404, 'data' => []];
        }

        if (isset($input['estado']) && !in_array(trim((string) $input['estado']), self::ESTADOS, true)) {
            return ['success' => false, 'message' => 'El estado del camión no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['matricula'])) {
            $input['matricula'] = strtoupper(trim((string) $input['matricula']));
            if (!ValidationHelper::validPlate($input['matricula'])) {
                return ['success' => false, 'message' => 'La matrícula debe tener un formato como ABC1234 o ABC-1234.', 'status' => 400, 'data' => []];
            }
        }
        if (isset($input['marca']) && !ValidationHelper::validEntityName((string) $input['marca'], 50)) {
            return ['success' => false, 'message' => 'La marca contiene caracteres no permitidos.', 'status' => 400, 'data' => []];
        }
        if (isset($input['modelo']) && !ValidationHelper::validEntityName((string) $input['modelo'], 50, 1)) {
            return ['success' => false, 'message' => 'El modelo contiene caracteres no permitidos.', 'status' => 400, 'data' => []];
        }
        if (isset($input['anio']) && ((int) $input['anio'] < 1950 || (int) $input['anio'] > ((int) date('Y') + 1))) {
            return ['success' => false, 'message' => 'El año del camión no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['kilometraje']) && $input['kilometraje'] !== '' && (int) $input['kilometraje'] < 0) {
            return ['success' => false, 'message' => 'El kilometraje no puede ser negativo.', 'status' => 400, 'data' => []];
        }
        if (isset($input['kilometraje']) && $input['kilometraje'] !== '' && (int) $input['kilometraje'] > 10000000) {
            return ['success' => false, 'message' => 'El kilometraje supera el máximo permitido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['capacidad']) && (!is_numeric($input['capacidad']) || (float) $input['capacidad'] <= 0)) {
            return ['success' => false, 'message' => 'La capacidad debe ser mayor que cero.', 'status' => 400, 'data' => []];
        }
        if (isset($input['capacidad']) && (float) $input['capacidad'] > 1000) {
            return ['success' => false, 'message' => 'La capacidad supera el máximo permitido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['id_cuadrilla']) && $input['id_cuadrilla'] !== '' && !$this->cuadrillaExiste((int) $input['id_cuadrilla'])) {
            return ['success' => false, 'message' => 'La cuadrilla seleccionada no existe.', 'status' => 400, 'data' => []];
        }
        if (isset($input['matricula']) && !$this->matriculaDisponible($input['matricula'], (int) $id)) {
            return ['success' => false, 'message' => 'Ya existe otro camión con esa matrícula.', 'status' => 409, 'data' => []];
        }

        $map = [
            'matricula' => 'matricula', 'marca' => 'marca', 'modelo' => 'modelo', 'anio' => 'anio',
            'kilometraje' => 'kilometraje', 'capacidad' => 'capacidad', 'estado' => 'estado',
            'disponibilidad' => 'disponibilidad', 'id_cuadrilla' => 'id_cuadrilla',
        ];

        $fields = [];
        $params = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $input)) {
                $fields[] = "$column = ?";
                $value = $input[$key];
                $params[] = ($value === '' && $key === 'id_cuadrilla') ? null : $value;
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No hay datos para actualizar.', 'status' => 400, 'data' => []];
        }

        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE camion SET ' . implode(', ', $fields) . ' WHERE id_camion = ?');
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Camión actualizado correctamente.', 'status' => 200, 'data' => []];
    }

    public function delete(array $input): array
    {
        $id = $input['id_camion'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de camión no especificado.', 'status' => 400, 'data' => []];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM camion WHERE id_camion = ? FOR UPDATE');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() === false) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'El camión no existe.', 'status' => 404, 'data' => []];
            }
            foreach (['mantenimiento', 'traslado_residuo'] as $tabla) {
                if ($this->columnExists($tabla, 'id_camion')) {
                    $stmt = $this->pdo->prepare("UPDATE $tabla SET id_camion = NULL WHERE id_camion = ?");
                    $stmt->execute([$id]);
                }
            }
            $stmt = $this->pdo->prepare('DELETE FROM camion WHERE id_camion = ?');
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return ['success' => true, 'message' => 'Camión eliminado correctamente.', 'status' => 200, 'data' => []];
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

    private function cuadrillaExiste(int $id): bool
    {
        if ($id <= 0) return false;
        $stmt = $this->pdo->prepare('SELECT 1 FROM cuadrilla WHERE id_cuadrilla = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    private function matriculaDisponible(string $matricula, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM camion WHERE UPPER(matricula) = UPPER(?)';
        $params = [trim($matricula)];
        if ($exceptId !== null) {
            $sql .= ' AND id_camion <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() === false;
    }
}
