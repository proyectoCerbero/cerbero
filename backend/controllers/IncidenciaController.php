<?php

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

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
            'SELECT id_incidencia, descripcion, fecha_hora_registro, fecha_hora_cierre, estado, ubicacion, foto, latitud, longitud
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
                    u.nombre, u.apellido, u.email, i.latitud, i.longitud
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
        $latitud = $this->coordinate($input['latitud'] ?? null, -35.1, -34.6);
        $longitud = $this->coordinate($input['longitud'] ?? null, -56.5, -55.8);

        if ($latitud === null || $longitud === null) {
            return $this->response(false, 'Marcá el lugar en el mapa o usá tu ubicación antes de enviar la incidencia.', 400);
        }

        $descripcionLength = function_exists('mb_strlen') ? mb_strlen($descripcion) : strlen($descripcion);
        $ubicacionLength = function_exists('mb_strlen') ? mb_strlen($ubicacion) : strlen($ubicacion);

        if ($descripcionLength < 10 || $ubicacionLength < 3) {
            return $this->response(false, 'La descripción debe tener al menos 10 caracteres y la ubicación al menos 3.', 400);
        }

        if ($descripcionLength > 500) {
            return $this->response(false, 'La descripción no puede superar los 500 caracteres.', 400);
        }

        if ($ubicacionLength > 255) {
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

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO incidencia (descripcion, fecha_hora_registro, fecha_hora_cierre, estado, ubicacion, foto, latitud, longitud, id_tipo_incidencia, ci, id_cuadrilla, id_contenedor)
                 VALUES (?, NOW(), NULL, ?, ?, ?, ?, ?, NULL, ?, NULL, NULL)'
            );
            $stmt->execute([$descripcion, 'a solucionar', $ubicacion, $relativePath, $latitud, $longitud, $ci]);
            $id = (int) $this->pdo->lastInsertId();
            NotificationService::notifyRole(
                $this->pdo,
                'administrador municipal',
                'Nueva incidencia IN' . str_pad((string) $id, 2, '0', STR_PAD_LEFT),
                'Un vecino registró una incidencia en ' . $ubicacion . '.'
            );
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
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
                'latitud' => $latitud,
                'longitud' => $longitud,
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

        $owner = $this->pdo->prepare('SELECT ci FROM incidencia WHERE id_incidencia = ? LIMIT 1');
        $owner->execute([$id]);
        $ciOwner = (string) ($owner->fetchColumn() ?: '');
        if ($ciOwner === '') {
            return $this->response(false, 'No se encontró la incidencia indicada.', 404);
        }

        $fechaCierre = $estado === 'solucionado' ? date('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare(
            'UPDATE incidencia SET estado = ?, fecha_hora_cierre = ? WHERE id_incidencia = ?'
        );
        $stmt->execute([$estado, $fechaCierre, $id]);
        NotificationService::create(
            $this->pdo,
            $ciOwner,
            'Actualización de incidencia IN' . str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            $estado === 'solucionado'
                ? 'Tu incidencia fue marcada como solucionada.'
                : 'Tu incidencia está nuevamente a solucionar.'
        );

        return $this->response(true, 'Estado actualizado correctamente.', 200);
    }

    public function delete(array $input): array
    {
        $id = (int) ($input['id_incidencia'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            return $this->response(false, 'La incidencia indicada no es válida.', 400);
        }

        $find = $this->pdo->prepare('SELECT foto FROM incidencia WHERE id_incidencia = ? LIMIT 1');
        $find->execute([$id]);
        $photo = $find->fetchColumn();
        if ($photo === false) {
            return $this->response(false, 'No se encontró la incidencia indicada.', 404);
        }

        $delete = $this->pdo->prepare('DELETE FROM incidencia WHERE id_incidencia = ?');
        $delete->execute([$id]);

        $fileName = basename((string) $photo);
        $uploadDirectory = __DIR__ . '/../uploads/incidencias';
        $absolutePath = $uploadDirectory . '/' . $fileName;
        if ($fileName !== '' && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        return $this->response(true, 'Incidencia eliminada correctamente.', 200);
    }

    public function updateLocation(array $input): array
    {
        $id = (int) ($input['id_incidencia'] ?? 0);
        $latitud = $this->coordinate($input['latitud'] ?? null, -35.1, -34.6);
        $longitud = $this->coordinate($input['longitud'] ?? null, -56.5, -55.8);
        if ($id <= 0 || $latitud === null || $longitud === null) {
            return $this->response(false, 'Indicá una incidencia y un punto válido dentro de Montevideo.', 400);
        }
        $stmt = $this->pdo->prepare('UPDATE incidencia SET latitud = ?, longitud = ? WHERE id_incidencia = ?');
        $stmt->execute([$latitud, $longitud, $id]);
        if ($stmt->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT 1 FROM incidencia WHERE id_incidencia = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) return $this->response(false, 'No se encontró la incidencia indicada.', 404);
        }
        return $this->response(true, 'Ubicación de la incidencia guardada.', 200, [
            'id_incidencia' => $id, 'latitud' => $latitud, 'longitud' => $longitud
        ]);
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

    private function coordinate($value, float $minimum, float $maximum): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        $number = (float) $value;
        return $number >= $minimum && $number <= $maximum ? $number : null;
    }
}
