<?php

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

class RecoleccionController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function data(array $actor, string $role): array
    {
        $idCuadrilla = $role === 'cuadrilla de recolección'
            ? $this->actorCrewId((string) ($actor['id'] ?? $actor['ci'] ?? '')) : null;
        if ($role === 'cuadrilla de recolección' && !$idCuadrilla) {
            return $this->response(false, 'Tu cuenta no tiene una cuadrilla asignada.', 403);
        }

        $contenedores = 'SELECT c.id_contenedor, c.nivel_llenado, c.estado,
            (SELECT GROUP_CONCAT(DISTINCT ru.id_ruta ORDER BY ru.id_ruta) FROM ruta_ubicacion ru WHERE ru.id_ubicacion = c.id_ubicacion) AS rutas
            FROM contenedor c';
        $camiones = "SELECT id_camion, matricula, estado, id_cuadrilla FROM camion WHERE disponibilidad = 1 AND LOWER(COALESCE(estado, '')) IN ('operativo', 'respaldo')";
        $cuadrillas = "SELECT c.id_cuadrilla, c.nombre, c.turno, cr.id_ruta FROM cuadrilla c
            LEFT JOIN cuadrilla_ruta cr ON cr.id_cuadrilla = c.id_cuadrilla AND cr.fecha =
                (SELECT MAX(cr2.fecha) FROM cuadrilla_ruta cr2 WHERE cr2.id_cuadrilla = c.id_cuadrilla)
            WHERE LOWER(COALESCE(c.estado, 'activa')) = 'activa'";
        $rutas = 'SELECT id_ruta, nombre FROM ruta_recoleccion';
        if ($idCuadrilla) {
            $ruta = $this->assignedRoute($idCuadrilla);
            if (!$ruta) return $this->response(false, 'Tu cuadrilla no tiene ruta asignada.', 403);
            $contenedores .= ' WHERE EXISTS (SELECT 1 FROM ruta_ubicacion ru WHERE ru.id_ubicacion = c.id_ubicacion AND ru.id_ruta = ' . $ruta . ')';
            $camiones .= ' AND id_cuadrilla = ' . $idCuadrilla;
            $cuadrillas .= ' AND c.id_cuadrilla = ' . $idCuadrilla;
            $rutas .= ' WHERE id_ruta = ' . $ruta;
        }
        return $this->response(true, 'Datos de recolección obtenidos.', 200, [
            'contenedores' => $this->pdo->query($contenedores . ' ORDER BY c.id_contenedor')->fetchAll(),
            'camiones' => $this->pdo->query($camiones . ' ORDER BY matricula')->fetchAll(),
            'cuadrillas' => $this->pdo->query($cuadrillas . ' ORDER BY c.nombre')->fetchAll(),
            'rutas' => $this->pdo->query($rutas . ' ORDER BY id_ruta')->fetchAll(),
            'recolecciones' => $this->listRows($idCuadrilla),
        ]);
    }

    public function create(array $input, array $actor, string $role): array
    {
        $idCuadrilla = (int) ($input['id_cuadrilla'] ?? 0);
        $idCamion = (int) ($input['id_camion'] ?? 0);
        $idRuta = (int) ($input['id_ruta'] ?? 0);
        $cantidad = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
        $observaciones = trim((string) ($input['observaciones'] ?? ''));
        $contenedores = array_values(array_unique(array_filter(array_map('intval', (array) ($input['contenedores'] ?? [])), static fn (int $id): bool => $id > 0)));
        $actorCi = trim((string) ($actor['id'] ?? $actor['ci'] ?? ''));
        $actorCuadrilla = $role === 'cuadrilla de recolección' ? $this->actorCrewId($actorCi) : null;

        if ($idCuadrilla <= 0 || $idCamion <= 0 || $idRuta <= 0 || $cantidad === false || $cantidad < 0 || $cantidad > 100000 || $contenedores === []) {
            return $this->response(false, 'Completá cuadrilla, camión, ruta, cantidad y al menos un contenedor.', 422);
        }
        if ($role === 'cuadrilla de recolección' && (!$actorCuadrilla || $actorCuadrilla !== $idCuadrilla)) {
            return $this->response(false, 'Solo podés registrar la recolección de tu propia cuadrilla.', 403);
        }
        if (strlen($observaciones) > 500) {
            return $this->response(false, 'Las observaciones no pueden superar 500 caracteres.', 422);
        }

        $rutaAsignada = $this->assignedRoute($idCuadrilla);
        if (!$rutaAsignada || $rutaAsignada !== $idRuta) {
            return $this->response(false, 'La ruta seleccionada no está asignada a esa cuadrilla.', 422);
        }
        $comprobarContenedor = $this->pdo->prepare(
            'SELECT 1 FROM contenedor c INNER JOIN ruta_ubicacion ru ON ru.id_ubicacion = c.id_ubicacion
             WHERE c.id_contenedor = ? AND ru.id_ruta = ? LIMIT 1'
        );
        foreach ($contenedores as $idContenedor) {
            $comprobarContenedor->execute([$idContenedor, $idRuta]);
            if (!$comprobarContenedor->fetchColumn()) {
                return $this->response(false, 'Uno de los contenedores no pertenece a la ruta seleccionada.', 422);
            }
        }

        $this->pdo->beginTransaction();
        try {
            $checkTruck = $this->pdo->prepare('SELECT id_cuadrilla, estado, disponibilidad FROM camion WHERE id_camion = ? FOR UPDATE');
            $checkTruck->execute([$idCamion]);
            $truck = $checkTruck->fetch(PDO::FETCH_ASSOC);
            if (!$truck || (int) $truck['id_cuadrilla'] !== $idCuadrilla
                || (int) $truck['disponibilidad'] !== 1
                || !in_array(strtolower((string) $truck['estado']), ['operativo', 'respaldo'], true)) {
                $this->pdo->rollBack();
                return $this->response(false, 'El camión no está disponible para la cuadrilla seleccionada.', 422);
            }

            $insert = $this->pdo->prepare('INSERT INTO registro_recoleccion (fecha_hora, cantidad, observaciones, id_cuadrilla, id_camion, id_ruta, ci_usuario) VALUES (NOW(), ?, ?, ?, ?, ?, ?)');
            $insert->execute([$cantidad, $observaciones ?: null, $idCuadrilla, $idCamion, $idRuta > 0 ? $idRuta : null, $actorCi ?: null]);
            $idRecoleccion = (int) $this->pdo->lastInsertId();
            $getContainer = $this->pdo->prepare('SELECT nivel_llenado FROM contenedor WHERE id_contenedor = ? FOR UPDATE');
            $insertDetail = $this->pdo->prepare('INSERT INTO recoleccion_contenedor (id_recoleccion, id_contenedor, porcentaje_antes, porcentaje_despues) VALUES (?, ?, ?, 0)');
            $history = $this->pdo->prepare('INSERT INTO historial_llenado (id_contenedor, porcentaje_antes, porcentaje_despues, fecha_hora, accion, ci_usuario) VALUES (?, ?, 0, NOW(), ?, ?)');
            $clean = $this->pdo->prepare("UPDATE contenedor SET nivel_llenado = 0, fecha_ultima_recoleccion = CURDATE(), estado = 'en buen estado' WHERE id_contenedor = ?");

            foreach ($contenedores as $idContenedor) {
                $getContainer->execute([$idContenedor]);
                $nivel = $getContainer->fetchColumn();
                if ($nivel === false) throw new RuntimeException('Uno de los contenedores seleccionados no existe.');
                $insertDetail->execute([$idRecoleccion, $idContenedor, $nivel]);
                $history->execute([$idContenedor, $nivel, 'recolección', $actorCi ?: null]);
                $clean->execute([$idContenedor]);
            }
            NotificationService::notifyRole($this->pdo, 'administrador municipal', 'Recolección registrada', sprintf('La cuadrilla %d registró una recolección de %s kg.', $idCuadrilla, number_format((float) $cantidad, 2, ',', '.')));
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Recolección registrada y contenedores actualizados.', 201, ['id_recoleccion' => $idRecoleccion]);
    }

    private function actorCrewId(string $ci): ?int
    {
        if ($ci === '') return null;
        $stmt = $this->pdo->prepare('SELECT id_cuadrilla FROM usuario WHERE ci = ? LIMIT 1');
        $stmt->execute([$ci]);
        $id = (int) $stmt->fetchColumn();
        return $id > 0 ? $id : null;
    }

    private function assignedRoute(int $idCuadrilla): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT cr.id_ruta FROM cuadrilla c INNER JOIN cuadrilla_ruta cr ON cr.id_cuadrilla = c.id_cuadrilla
             WHERE c.id_cuadrilla = ? AND LOWER(COALESCE(c.estado, 'activa')) = 'activa'
             ORDER BY cr.fecha DESC, cr.id_ruta DESC LIMIT 1"
        );
        $stmt->execute([$idCuadrilla]);
        $id = (int) $stmt->fetchColumn();
        return $id > 0 ? $id : null;
    }

    private function listRows(?int $idCuadrilla): array
    {
        $where = $idCuadrilla === null ? '' : 'WHERE r.id_cuadrilla = ?';
        $stmt = $this->pdo->prepare(
            'SELECT r.id_recoleccion, r.fecha_hora, r.cantidad, r.observaciones, c.nombre AS cuadrilla, ca.matricula AS camion, ru.nombre AS ruta,
                    (SELECT COUNT(*) FROM recoleccion_contenedor rc WHERE rc.id_recoleccion = r.id_recoleccion) AS contenedores
             FROM registro_recoleccion r
             INNER JOIN cuadrilla c ON c.id_cuadrilla = r.id_cuadrilla
             INNER JOIN camion ca ON ca.id_camion = r.id_camion
             LEFT JOIN ruta_recoleccion ru ON ru.id_ruta = r.id_ruta
             ' . $where . '
             ORDER BY r.fecha_hora DESC, r.id_recoleccion DESC
             LIMIT 30'
        );
        $stmt->execute($idCuadrilla === null ? [] : [$idCuadrilla]);
        return $stmt->fetchAll();
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
