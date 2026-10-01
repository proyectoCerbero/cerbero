# CERBERO / SiGeRU

Aplicación PHP, JavaScript y MySQL para gestionar residuos, rutas, recolecciones e incidencias.

## Abrir con XAMPP

1. Copiá la carpeta `proyecto` completa a `C:\xampp\htdocs\proyecto`.
2. Iniciá Apache y MySQL desde XAMPP.
3. Abrí `http://localhost/proyecto/frontend/index.html`. La aplicación crea o actualiza sus tablas al recibir las primeras peticiones a la API.
4. Para habilitar Gmail y el asistente, copiá `.env.example` a `.env` y completá tus claves locales según `CONFIGURACION_AYUDA.md`.

Al reemplazar una instalación anterior, conservá el archivo `.env`, la base de datos MySQL y las fotos de `backend/uploads/incidencias`. El ZIP no lleva contraseñas personales ni fotos subidas por usuarios.

## Abrir con Docker Desktop

Desde una terminal ubicada en la carpeta `proyecto`:

```sh
docker compose up --build -d
```

Abrí `http://localhost:8080/frontend/index.html`. Docker guarda MySQL y las fotos en volúmenes persistentes. Las opciones SMTP y de IA se leen del archivo `.env` local al iniciar Compose. Si agregás o cambiás esas opciones, reconstruí el servicio con `docker compose up --build -d`.

Para detenerlo sin borrar los datos:

```sh
docker compose down
```

## Comprobaciones disponibles

Las pruebas de código de la API se ejecutan con PHP y MySQL disponibles:

```sh
php backend/tests/run_tests.php
```

Con Docker:

```sh
docker compose exec web php backend/tests/run_tests.php
```

La vista de rutas por calles consulta el servicio público OSRM; los mapas base usan Leaflet y OpenStreetMap. Necesitás conexión a Internet para trazados y teselas. En incidencias, el vecino marca el punto antes de guardar; el administrador puede ubicar en el mapa los reportes antiguos sin coordenadas.
