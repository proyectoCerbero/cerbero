<?php

require_once __DIR__ . '/../helpers/ValidationHelper.php';

class MaquinariaController
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

    /** Busca el id_tipo_maquinaria por nombre y lo crea si no existe (tabla de catálogo). */
    private function findOrCreateTipoId(string $tipoNombre): ?int
    {
        $tipoNombre = trim($tipoNombre);
        if ($tipoNombre === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id_tipo_maquinaria FROM tipo_maquinaria WHERE nombre = ? LIMIT 1');
        $stmt->execute([$tipoNombre]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO tipo_maquinaria (nombre) VALUES (?)');
        $insert->execute([$tipoNombre]);

        return (int) $this->pdo->lastInsertId();
    }

    public function list(): array
    {
        $sql = 'SELECT m.*, i.nombre AS instalacion_nombre, t.nombre AS tipo
                FROM maquinaria m
                LEFT JOIN instalacion i ON m.id_instalacion = i.id_instalacion
                LEFT JOIN tipo_maquinaria t ON m.id_tipo_maquinaria = t.id_tipo_maquinaria
                ORDER BY m.id_maquinaria DESC';
        $stmt = $this->pdo->query($sql);
        return [
            'success' => true,
            'message' => 'Maquinaria obtenida con éxito.',
            'status' => 200,
            'data' => ['maquinaria' => $stmt->fetchAll()]
        ];
    }

    public function create(array $input): array
    {
        $nombre = trim($input['nombre'] ?? $input['tipo'] ?? '');
        $tipo = trim($input['tipo'] ?? $input['tipo_maquinaria'] ?? '');
        $estado = $input['estado'] ?? 'operativa';
        $idInstalacion = !empty($input['id_instalacion']) ? (int) $input['id_instalacion'] : null;

        if ($nombre === '' || $tipo === '') {
            return ['success' => false, 'message' => 'El nombre y el tipo de maquinaria son obligatorios.', 'status' => 400, 'data' => []];
        }
        if (!ValidationHelper::validEntityName($nombre, 80) || !ValidationHelper::validEntityName($tipo, 60)) {
            return ['success' => false, 'message' => 'El nombre o tipo de maquinaria contienen caracteres no permitidos.', 'status' => 400, 'data' => []];
        }
        if (!in_array($estado, ['operativa', 'mantenimiento', 'inactiva'], true)) {
            return ['success' => false, 'message' => 'El estado de la maquinaria no es válido.', 'status' => 400, 'data' => []];
        }
        if ($idInstalacion !== null && !$this->instalacionExiste($idInstalacion)) {
            return ['success' => false, 'message' => 'El centro de acopio seleccionado no existe.', 'status' => 400, 'data' => []];
        }

        $idTipo = $this->findOrCreateTipoId($tipo);

        $stmt = $this->pdo->prepare('INSERT INTO maquinaria (nombre, id_tipo_maquinaria, estado, id_instalacion) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nombre, $idTipo, $estado, $idInstalacion]);

        return [
            'success' => true,
            'message' => 'Maquinaria registrada correctamente.',
            'status' => 201,
            'data' => ['id_maquinaria' => $this->pdo->lastInsertId()]
        ];
    }

    public function update(array $input): array
    {
        $id = $input['id_maquinaria'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de maquinaria no especificado.', 'status' => 400, 'data' => []];
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM maquinaria WHERE id_maquinaria = ?');
        $exists->execute([$id]);
        if ($exists->fetchColumn() === false) {
            return ['success' => false, 'message' => 'La maquinaria no existe.', 'status' => 404, 'data' => []];
        }
        if (isset($input['estado']) && !in_array(trim((string) $input['estado']), ['operativa', 'mantenimiento', 'inactiva'], true)) {
            return ['success' => false, 'message' => 'El estado de la maquinaria no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['nombre']) && !ValidationHelper::validEntityName((string) $input['nombre'], 80)) {
            return ['success' => false, 'message' => 'El nombre de la maquinaria no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['tipo']) && !ValidationHelper::validEntityName((string) $input['tipo'], 60)) {
            return ['success' => false, 'message' => 'El tipo de maquinaria no es válido.', 'status' => 400, 'data' => []];
        }
        if (isset($input['id_instalacion']) && $input['id_instalacion'] !== '' && !$this->instalacionExiste((int) $input['id_instalacion'])) {
            return ['success' => false, 'message' => 'El centro de acopio seleccionado no existe.', 'status' => 400, 'data' => []];
        }

        $fields = [];
        $params = [];

        if (array_key_exists('nombre', $input) && trim((string) $input['nombre']) !== '') {
            $fields[] = 'nombre = ?';
            $params[] = trim($input['nombre']);
        }
        if (array_key_exists('tipo', $input) && trim((string) $input['tipo']) !== '') {
            $fields[] = 'id_tipo_maquinaria = ?';
            $params[] = $this->findOrCreateTipoId($input['tipo']);
        }
        if (array_key_exists('estado', $input) && trim((string) $input['estado']) !== '') {
            $fields[] = 'estado = ?';
            $params[] = trim($input['estado']);
        }
        if (array_key_exists('id_instalacion', $input)) {
            $fields[] = 'id_instalacion = ?';
            $params[] = $input['id_instalacion'] !== '' ? $input['id_instalacion'] : null;
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No hay datos para actualizar.', 'status' => 400, 'data' => []];
        }

        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE maquinaria SET ' . implode(', ', $fields) . ' WHERE id_maquinaria = ?');
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Maquinaria actualizada correctamente.', 'status' => 200, 'data' => []];
    }

    public function delete(array $input): array
    {
        $id = $input['id_maquinaria'] ?? $input['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID de maquinaria no especificado.', 'status' => 400, 'data' => []];
        }

        $stmt = $this->pdo->prepare('DELETE FROM maquinaria WHERE id_maquinaria = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'La maquinaria no existe.', 'status' => 404, 'data' => []];
        }

        return ['success' => true, 'message' => 'Maquinaria eliminada correctamente.', 'status' => 200, 'data' => []];
    }

    private function instalacionExiste(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM instalacion WHERE id_instalacion = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }
}
