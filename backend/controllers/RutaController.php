<?php

require_once __DIR__ . '/../config/db_connection.php';

class RutaController
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = Database::connect($config);
    }

    public function generatorData(): array
    {
        $contenedores = $this->pdo->query(
            'SELECT c.id_contenedor AS id, c.id_ubicacion, c.nivel_llenado, c.estado,
                    u.barrio, u.calle, u.numero, u.latitud, u.longitud
             FROM contenedor c
             INNER JOIN ubicacion u ON u.id_ubicacion = c.id_ubicacion
             WHERE u.latitud IS NOT NULL AND u.longitud IS NOT NULL
             ORDER BY c.id_contenedor'
        )->fetchAll();

        $centros = $this->pdo->query(
            "SELECT id_instalacion AS id, nombre, calle, numero, latitud, longitud
             FROM instalacion
             WHERE nombre IN ('ECOCENTRO BUCEO', 'Reciclo NFU', 'Centro de Acopio Disco')
               AND latitud IS NOT NULL AND longitud IS NOT NULL
             ORDER BY id_instalacion"
        )->fetchAll();

        $rutas = $this->listarGeneradas();
        $numeroSugerido = count($rutas) + 1;

        return $this->response(true, 'Datos del generador obtenidos correctamente.', 200, [
            'contenedores' => $contenedores,
            'centros' => $centros,
            'rutas' => $rutas,
            'nombre_sugerido' => sprintf('Ruta generada %02d', $numeroSugerido),
        ]);
    }

    public function create(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $idCentro = (int) ($input['id_instalacion_base'] ?? 0);
        $distancia = is_numeric($input['distancia'] ?? null) ? round((float) $input['distancia'], 2) : 0;
        $duracionMinutos = is_numeric($input['duracion_minutos'] ?? null)
            ? round((float) $input['duracion_minutos'], 2)
            : 0;
        $geometria = is_array($input['geometria'] ?? null) ? $input['geometria'] : [];
        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($input['contenedores'] ?? null) ? $input['contenedores'] : []),
            static fn (int $id): bool => $id > 0
        )));

        if ($nombre === '') return $this->response(false, 'Ingresá un nombre para la ruta.', 400);
        if (count($ids) < 2) return $this->response(false, 'Seleccioná al menos dos contenedores.', 400);
        if ($idCentro <= 0) return $this->response(false, 'No se indicó un centro de salida.', 400);
        if ($distancia <= 0 || $duracionMinutos <= 0) {
            return $this->response(false, 'La ruta vial no contiene una distancia o duración válida.', 400);
        }
        if (!$this->geometriaValida($geometria)) {
            return $this->response(false, 'El trazado vial recibido no es válido.', 400);
        }
        $geometriaJson = json_encode($geometria, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($geometriaJson === false) {
            return $this->response(false, 'No se pudo guardar el trazado vial.', 400);
        }

        $duplicada = $this->pdo->prepare('SELECT 1 FROM ruta_recoleccion WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
        $duplicada->execute([$nombre]);
        if ($duplicada->fetchColumn() !== false) {
            return $this->response(false, 'Ya existe una ruta con ese nombre.', 409);
        }

        $centro = $this->pdo->prepare(
            'SELECT id_instalacion FROM instalacion WHERE id_instalacion = ? AND latitud IS NOT NULL AND longitud IS NOT NULL'
        );
        $centro->execute([$idCentro]);
        if ($centro->fetchColumn() === false) return $this->response(false, 'El centro seleccionado no existe.', 400);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id_contenedor, id_ubicacion FROM contenedor
             WHERE id_contenedor IN ($placeholders) AND id_ubicacion IS NOT NULL"
        );
        $stmt->execute($ids);
        $ubicaciones = [];
        foreach ($stmt->fetchAll() as $row) {
            $ubicaciones[(int) $row['id_contenedor']] = (int) $row['id_ubicacion'];
        }
        if (count($ubicaciones) !== count($ids)) {
            return $this->response(false, 'Uno o más contenedores seleccionados ya no están disponibles.', 409);
        }

        $this->pdo->beginTransaction();
        try {
            $crear = $this->pdo->prepare(
                "INSERT INTO ruta_recoleccion
                 (nombre, frecuencia, distancia, estado, id_instalacion_base, fecha_creacion,
                  duracion_minutos, geometria)
                 VALUES (?, 'A definir', ?, 'generada', ?, NOW(), ?, ?)"
            );
            $crear->execute([$nombre, $distancia, $idCentro, $duracionMinutos, $geometriaJson]);
            $idRuta = (int) $this->pdo->lastInsertId();

            $agregarPunto = $this->pdo->prepare(
                'INSERT INTO ruta_ubicacion (id_ruta, id_ubicacion, orden) VALUES (?, ?, ?)'
            );
            foreach ($ids as $indice => $idContenedor) {
                $agregarPunto->execute([$idRuta, $ubicaciones[$idContenedor], $indice + 1]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->response(true, 'Ruta confirmada y agregada al listado.', 201, [
            'id_ruta' => $idRuta,
            'ruta' => $this->obtenerGenerada($idRuta),
        ]);
    }

    private function listarGeneradas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT r.id_ruta, r.nombre, r.distancia, r.estado, r.fecha_creacion,
                    r.duracion_minutos, r.geometria, i.nombre AS centro_nombre,
                    (SELECT COUNT(*) FROM ruta_ubicacion ru WHERE ru.id_ruta = r.id_ruta) AS cantidad_puntos
             FROM ruta_recoleccion r
             LEFT JOIN instalacion i ON i.id_instalacion = r.id_instalacion_base
             WHERE LOWER(COALESCE(r.estado, '')) = 'generada'
             ORDER BY r.id_ruta DESC"
        );
        return $this->normalizarRutas($stmt->fetchAll());
    }

    private function obtenerGenerada(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id_ruta, r.nombre, r.distancia, r.estado, r.fecha_creacion,
                    r.duracion_minutos, r.geometria, i.nombre AS centro_nombre,
                    (SELECT COUNT(*) FROM ruta_ubicacion ru WHERE ru.id_ruta = r.id_ruta) AS cantidad_puntos
             FROM ruta_recoleccion r
             LEFT JOIN instalacion i ON i.id_instalacion = r.id_instalacion_base
             WHERE r.id_ruta = ?"
        );
        $stmt->execute([$id]);
        $rutas = $this->normalizarRutas($stmt->fetchAll());
        return $rutas[0] ?? null;
    }

    private function geometriaValida(array $geometria): bool
    {
        if (count($geometria) < 2 || count($geometria) > 100000) return false;
        foreach ($geometria as $coordenada) {
            if (!is_array($coordenada) || count($coordenada) !== 2) return false;
            if (!is_numeric($coordenada[0]) || !is_numeric($coordenada[1])) return false;
            $longitud = (float) $coordenada[0];
            $latitud = (float) $coordenada[1];
            if ($longitud < -180 || $longitud > 180 || $latitud < -90 || $latitud > 90) return false;
        }
        return true;
    }

    private function normalizarRutas(array $rutas): array
    {
        foreach ($rutas as &$ruta) {
            $geometria = json_decode((string) ($ruta['geometria'] ?? ''), true);
            $ruta['geometria'] = is_array($geometria) ? $geometria : [];
        }
        unset($ruta);
        return $rutas;
    }

    private function response(bool $success, string $message, int $status, array $data = []): array
    {
        return compact('success', 'message', 'status', 'data');
    }
}
