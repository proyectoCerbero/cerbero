<?php

require_once __DIR__ . '/../config/db_connection.php';

class CuadrillaModel
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id_cuadrilla, nombre, turno, estado FROM cuadrilla ORDER BY id_cuadrilla DESC'
        );

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_cuadrilla, nombre, turno, estado FROM cuadrilla WHERE id_cuadrilla = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $cuadrilla = $stmt->fetch();

        return $cuadrilla ?: null;
    }

    public function create(array $data): array
    {
        $attempts = 0;

        while ($attempts < 3) {
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO cuadrilla (nombre, turno, estado) VALUES (?, ?, ?)'
                );
                $stmt->execute([
                    $data['nombre'],
                    $data['turno'],
                    $data['estado'],
                ]);

                $id = (int) $this->pdo->lastInsertId();

                return $this->findById($id) ?? [];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '1213') !== false || strpos($e->getMessage(), 'deadlock') !== false) {
                    $attempts++;
                    usleep(250000);
                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException('No se pudo crear la cuadrilla después de varios intentos por conflictos de base de datos.');
    }
}
