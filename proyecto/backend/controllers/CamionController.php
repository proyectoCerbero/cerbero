<?php

require_once __DIR__ . '/../models/CamionModel.php';

class CamionController
{
    private CamionModel $camionModel;

    public function __construct(array $config)
    {
        $this->camionModel = new CamionModel($config);
    }

    public function list(): array
    {
        $camiones = $this->camionModel->findAll();

        return $this->jsonResponse(true, 'Camiones obtenidos correctamente.', 200, [
            'camiones' => $camiones
        ]);
    }

    public function show(int $id): array
    {
        $camion = $this->camionModel->findById($id);

        if (!$camion) {
            return $this->jsonResponse(false, 'Camión no encontrado.', 404);
        }

        return $this->jsonResponse(true, 'Camión obtenido correctamente.', 200, [
            'camion' => $camion
        ]);
    }

    public function create(array $input): array
    {
        $matricula = strtoupper(trim($input['matricula'] ?? ''));
        $modelo = trim($input['modelo'] ?? '');
        $marca = trim($input['marca'] ?? '');
        $anio = $input['anio'] ?? null;

        if ($matricula === '' || $modelo === '' || $marca === '' || $anio === null) {
            return $this->jsonResponse(
                false,
                'matricula, modelo, marca y anio son obligatorios.',
                400
            );
        }

        if (!is_numeric($anio) || (int) $anio < 1950 || (int) $anio > (int) date('Y') + 1) {
            return $this->jsonResponse(false, 'El campo anio no es válido.', 400);
        }

        if ($this->camionModel->findByMatricula($matricula)) {
            return $this->jsonResponse(false, 'Ya existe un camión con esa matrícula.', 409);
        }

        $kilometraje = isset($input['kilometraje']) ? (int) $input['kilometraje'] : 0;
        $capacidad = isset($input['capacidad']) ? (float) $input['capacidad'] : 0;
        $estado = trim($input['estado'] ?? '') ?: 'activo';
        $idCuadrilla = isset($input['id_cuadrilla']) && $input['id_cuadrilla'] !== ''
            ? (int) $input['id_cuadrilla']
            : null;

        try {
            $camion = $this->camionModel->create([
                'matricula' => $matricula,
                'modelo' => $modelo,
                'marca' => $marca,
                'anio' => (int) $anio,
                'kilometraje' => $kilometraje,
                'capacidad' => $capacidad,
                'estado' => $estado,
                'id_cuadrilla' => $idCuadrilla,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return $this->jsonResponse(false, 'La cuadrilla indicada (id_cuadrilla) no existe.', 400);
            }
            throw $e;
        }

        return $this->jsonResponse(true, 'Camión creado correctamente.', 201, [
            'camion' => $camion
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
