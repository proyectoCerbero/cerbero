<?php

require_once __DIR__ . '/../models/CuadrillaModel.php';

class CuadrillaController
{
    private CuadrillaModel $cuadrillaModel;

    public function __construct(array $config)
    {
        $this->cuadrillaModel = new CuadrillaModel($config);
    }

    public function list(): array
    {
        $cuadrillas = $this->cuadrillaModel->findAll();

        return $this->jsonResponse(true, 'Cuadrillas obtenidas correctamente.', 200, [
            'cuadrillas' => $cuadrillas
        ]);
    }

    public function create(array $input): array
    {
        $nombre = trim($input['nombre'] ?? '');
        $turno = trim($input['turno'] ?? '');
        $estado = trim($input['estado'] ?? '') ?: 'activa';

        if ($nombre === '') {
            return $this->jsonResponse(false, 'El nombre de la cuadrilla es obligatorio.', 400);
        }

        $cuadrilla = $this->cuadrillaModel->create([
            'nombre' => $nombre,
            'turno' => $turno ?: null,
            'estado' => $estado,
        ]);

        return $this->jsonResponse(true, 'Cuadrilla creada correctamente.', 201, [
            'cuadrilla' => $cuadrilla
        ]);
    }

    private function jsonResponse(bool $success, string $message, int $status, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'status' => $status,
            'data' => $data
        ];
    }
}
