<?php

require_once __DIR__ . '/../config/Database.php';

class CamionModel
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.id_camion, c.matricula, c.modelo, c.marca, c.anio, c.kilometraje,
                    c.capacidad, c.estado, c.id_cuadrilla, cu.nombre AS cuadrilla_nombre
             FROM camion c
             LEFT JOIN cuadrilla cu ON c.id_cuadrilla = cu.id_cuadrilla
             ORDER BY c.id_camion DESC'
        );

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id_camion, c.matricula, c.modelo, c.marca, c.anio, c.kilometraje,
                    c.capacidad, c.estado, c.id_cuadrilla, cu.nombre AS cuadrilla_nombre
             FROM camion c
             LEFT JOIN cuadrilla cu ON c.id_cuadrilla = cu.id_cuadrilla
             WHERE c.id_camion = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $camion = $stmt->fetch();

        return $camion ?: null;
    }

    public function findByMatricula(string $matricula): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id_camion FROM camion WHERE matricula = ? LIMIT 1');
        $stmt->execute([$matricula]);
        $camion = $stmt->fetch();

        return $camion ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO camion (matricula, modelo, marca, anio, kilometraje, capacidad, estado, id_cuadrilla)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['matricula'],
            $data['modelo'],
            $data['marca'],
            $data['anio'],
            $data['kilometraje'],
            $data['capacidad'],
            $data['estado'],
            $data['id_cuadrilla'],
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? [];
    }
}
