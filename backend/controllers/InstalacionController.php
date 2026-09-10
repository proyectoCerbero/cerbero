<?php

class InstalacionController
{
    private PDO $pdo;

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
        $stmt = $this->pdo->query('SELECT * FROM instalacion ORDER BY id_instalacion DESC');
        return [
            'success' => true,
            'message' => 'Instalaciones obtenidas con éxito.',
            'status' => 200,
            'data' => ['instalaciones' => $stmt->fetchAll()]
        ];
    }

    public function create(array $input): array
    {
        $nombre = trim($input['nombre'] ?? '');
        $calle = trim($input['calle'] ?? '');
        $numero = trim($input['numero'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $horario = trim($input['horario'] ?? '');
        $capacidad = $input['capacidad'] ?? 0;
        $estado = $input['estado'] ?? 'activo';

        if ($nombre === '' || $calle === '' || $numero === '') {
            return ['success' => false, 'message' => 'Nombre, calle y número son obligatorios.', 'status' => 400, 'data' => []];
        }
        if (!is_numeric($capacidad) || (float) $capacidad < 0) {
            return ['success' => false, 'message' => 'La capacidad no es válida.', 'status' => 400, 'data' => []];
        }
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            return ['success' => false, 'message' => 'El estado de la instalación no es válido.', 'status' => 400, 'data' => []];
        }

        $stmt = $this->pdo->prepare('INSERT INTO instalacion (nombre, calle, numero, telefono, horario, capacidad, estado) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$nombre, $calle, $numero, $telefono, $horario, $capacidad, $estado]);

        return ['success' => true, 'message' => 'Instalación registrada correctamente.', 'status' => 201, 'data' => ['id_instalacion' => $this->pdo->lastInsertId()]];
    }

    public function update(array $input): array
    {
        $id = $input['id_instalacion'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de instalación no especificado.', 'status' => 400, 'data' => []];
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM instalacion WHERE id_instalacion = ?');
        $exists->execute([$id]);
        if ($exists->fetchColumn() === false) {
            return ['success' => false, 'message' => 'La instalación no existe.', 'status' => 404, 'data' => []];
        }
        if (isset($input['capacidad']) && (!is_numeric($input['capacidad']) || (float) $input['capacidad'] < 0)) {
            return ['success' => false, 'message' => 'La capacidad no es válida.', 'status' => 400, 'data' => []];
        }
        if (isset($input['estado']) && !in_array((string) $input['estado'], ['activo', 'inactivo'], true)) {
            return ['success' => false, 'message' => 'El estado de la instalación no es válido.', 'status' => 400, 'data' => []];
        }

        $map = ['nombre', 'calle', 'numero', 'telefono', 'horario', 'capacidad', 'estado'];
        $fields = [];
        $params = [];
        foreach ($map as $column) {
            if (array_key_exists($column, $input)) {
                $fields[] = "$column = ?";
                $params[] = $input[$column];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No hay datos para actualizar.', 'status' => 400, 'data' => []];
        }

        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE instalacion SET ' . implode(', ', $fields) . ' WHERE id_instalacion = ?');
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Instalación actualizada correctamente.', 'status' => 200, 'data' => []];
    }

    public function delete(array $input): array
    {
        $id = $input['id_instalacion'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de instalación no especificado.', 'status' => 400, 'data' => []];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM instalacion WHERE id_instalacion = ? FOR UPDATE');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() === false) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'La instalación no existe.', 'status' => 404, 'data' => []];
            }
            foreach (['traslado_residuo', 'repuesto', 'maquinaria'] as $tabla) {
                if ($this->columnExists($tabla, 'id_instalacion')) {
                    $stmt = $this->pdo->prepare("UPDATE $tabla SET id_instalacion = NULL WHERE id_instalacion = ?");
                    $stmt->execute([$id]);
                }
            }
            if ($this->columnExists('instalacion_tipo_residuo', 'id_instalacion')) {
                $stmt = $this->pdo->prepare('DELETE FROM instalacion_tipo_residuo WHERE id_instalacion = ?');
                $stmt->execute([$id]);
            }
            $stmt = $this->pdo->prepare('DELETE FROM instalacion WHERE id_instalacion = ?');
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return ['success' => true, 'message' => 'Instalación eliminada correctamente.', 'status' => 200, 'data' => []];
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
}
