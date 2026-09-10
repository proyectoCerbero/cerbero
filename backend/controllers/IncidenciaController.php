<?php

require_once __DIR__ . '/../config/db_connection.php';

class IncidenciaController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function listByUser(string $ci): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_incidencia, descripcion, fecha_hora_registro, fecha_hora_cierre, estado, ubicacion, foto
             FROM incidencia
             WHERE ci = ?
             ORDER BY id_incidencia DESC'
        );
        $stmt->execute([$ci]);

        return [
            'success' => true,
            'message' => 'Incidencias obtenidas correctamente.',
            'status' => 200,
            'data' => ['incidencias' => $stmt->fetchAll()]
        ];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT i.id_incidencia, i.descripcion, i.fecha_hora_registro,
                    i.fecha_hora_cierre, i.estado, i.ubicacion, i.foto, i.ci,
                    u.nombre, u.apellido, u.email
             FROM incidencia i
             LEFT JOIN usuario u ON i.ci = u.ci
             ORDER BY i.id_incidencia DESC'
        );

        return [
            'success' => true,
            'message' => 'Incidencias obtenidas correctamente.',
            'status' => 200,
            'data' => ['incidencias' => $stmt->fetchAll()]
        ];
    }

    public function create(array $input, array $file, string $ci): array
    {
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $ubicacion = trim((string) ($input['ubicacion'] ?? ''));

        if ($descripcion === '' || $ubicacion === '') {
            return $this->response(false, 'La descripción y la ubicación son obligatorias.', 400);
        }

        if (strlen($descripcion) > 500) {
            return $this->response(false, 'La descripción no puede superar los 500 caracteres.', 400);
        }

        if (strlen($ubicacion) > 255) {
            return $this->response(false, 'La ubicación no puede superar los 255 caracteres.', 400);
        }

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->response(false, 'Tenés que agregar una foto de la incidencia.', 400);
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $this->response(false, 'No se pudo subir la foto.', 400);
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return $this->response(false, 'La foto no puede pesar más de 5 MB.', 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $imageInfo = $tmpName !== '' ? @getimagesize($tmpName) : false;
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($extensions[$mime])) {
            return $this->response(false, 'La foto debe ser JPG, PNG o WEBP.', 400);
        }

        $uploadDirectory = __DIR__ . '/../uploads/incidencias';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return $this->response(false, 'No se pudo preparar la carpeta para guardar la foto.', 500);
        }

        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $absolutePath = $uploadDirectory . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return $this->response(false, 'No se pudo guardar la foto enviada.', 500);
        }

        $relativePath = 'uploads/incidencias/' . $fileName;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO incidencia (descripcion, fecha_hora_registro, fecha_hora_cierre, estado, ubicacion, foto, id_tipo_incidencia, ci, id_cuadrilla, id_contenedor)
                 VALUES (?, NOW(), NULL, ?, ?, ?, NULL, ?, NULL, NULL)'
            );
            $stmt->execute([$descripcion, 'a solucionar', $ubicacion, $relativePath, $ci]);
            $id = (int) $this->pdo->lastInsertId();
        } catch (Throwable $error) {
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
            throw $error;
        }

        return $this->response(true, 'Incidencia registrada correctamente.', 201, [
            'incidencia' => [
                'id_incidencia' => $id,
                'descripcion' => $descripcion,
                'ubicacion' => $ubicacion,
                'foto' => $relativePath,
                'estado' => 'a solucionar',
                'fecha_hora_registro' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    public function updateStatus(array $input): array
    {
        $id = (int) ($input['id_incidencia'] ?? $input['id'] ?? 0);
        $estado = strtolower(trim((string) ($input['estado'] ?? '')));
        $allowed = ['a solucionar', 'solucionado'];

        if ($id <= 0 || !in_array($estado, $allowed, true)) {
            return $this->response(false, 'Incidencia o estado inválido.', 400);
        }

        $fechaCierre = $estado === 'solucionado' ? date('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare(
            'UPDATE incidencia SET estado = ?, fecha_hora_cierre = ? WHERE id_incidencia = ?'
        );
        $stmt->execute([$estado, $fechaCierre, $id]);

        return $this->response(true, 'Estado actualizado correctamente.', 200);
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'status' => $status,
            'data' => $data
        ];
    }
}
