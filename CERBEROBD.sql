CREATE DATABASE IF NOT EXISTS cerbero;
USE cerbero;

CREATE TABLE IF NOT EXISTS rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
);

INSERT INTO rol (nombre, descripcion)
SELECT 'vecino', 'Rol para vecinos que reportan incidencias y participan en el sistema.'
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'vecino');

INSERT INTO rol (nombre, descripcion)
SELECT 'cuadrilla de recolección', 'Rol para personal de cuadrilla de recolección y operaciones de campo.'
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'cuadrilla de recolección');

INSERT INTO rol (nombre, descripcion)
SELECT 'operario de centro', 'Rol para operarios de centros de gestión y atención de residuos.'
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'operario de centro');

INSERT INTO rol (nombre, descripcion)
SELECT 'administrador municipal', 'Rol para administradores municipales con permisos de gestión completa.'
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'administrador municipal');

CREATE TABLE IF NOT EXISTS cuadrilla (
    id_cuadrilla INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    turno VARCHAR(30),
    estado VARCHAR(30)
);

CREATE TABLE IF NOT EXISTS usuario (
    ci CHAR(8) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    fecha_registro DATETIME,
    estado VARCHAR(30),
    id_rol INT,
    id_cuadrilla INT,
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol),
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id_cuadrilla)
);

INSERT INTO usuario (ci, nombre, apellido, email, contrasena_hash, fecha_registro, estado, id_rol, id_cuadrilla)
SELECT '00000000', 'Admin', 'Ejemplo', 'adminejemplo@gmail.com', '$2y$10$h2NqYvRuZ59Hf2wMpnWw/OoPJt1bVaX5xEMfQ9NE8QfW7T9Prqucu', NOW(), 'activo', r.id_rol, NULL
FROM rol r
WHERE r.nombre = 'administrador municipal'
AND NOT EXISTS (
    SELECT 1
    FROM usuario
    WHERE email = 'adminejemplo@gmail.com'
);

CREATE TABLE IF NOT EXISTS notificacion (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100),
    mensaje TEXT,
    fecha_hora DATETIME,
    estado VARCHAR(30),
    ci CHAR(8),
    FOREIGN KEY (ci) REFERENCES usuario(ci)
);

CREATE TABLE IF NOT EXISTS tipo_incidencia (
    id_tipo_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    descripcion VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS ubicacion (
    id_ubicacion INT AUTO_INCREMENT PRIMARY KEY,
    barrio VARCHAR(100),
    calle VARCHAR(100),
    numero VARCHAR(10),
    latitud DECIMAL(10,7),
    longitud DECIMAL(10,7)
);

CREATE TABLE IF NOT EXISTS tipo_residuo (
    id_tipo_residuo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS contenedor (
    id_contenedor INT AUTO_INCREMENT PRIMARY KEY,
    capacidad DECIMAL(8,2),
    nivel_llenado DECIMAL(5,2),
    fecha_instalacion DATE,
    fecha_ultima_recoleccion DATE,
    estado VARCHAR(30),
    id_ubicacion INT,
    id_tipo_residuo INT,
    FOREIGN KEY (id_ubicacion) REFERENCES ubicacion(id_ubicacion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES tipo_residuo(id_tipo_residuo)
);

CREATE TABLE IF NOT EXISTS incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    descripcion TEXT,
    fecha_hora_registro DATETIME,
    fecha_hora_cierre DATETIME,
    estado VARCHAR(30),
    id_tipo_incidencia INT,
    ci CHAR(8),
    id_cuadrilla INT,
    id_contenedor INT,
    FOREIGN KEY (id_tipo_incidencia) REFERENCES tipo_incidencia(id_tipo_incidencia),
    FOREIGN KEY (ci) REFERENCES usuario(ci),
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id_cuadrilla),
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id_contenedor)
);

CREATE TABLE IF NOT EXISTS camion (
    id_camion INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) UNIQUE,
    modelo VARCHAR(50),
    marca VARCHAR(50),
    anio YEAR,
    kilometraje INT,
    capacidad DECIMAL(8,2),
    estado VARCHAR(30),
    id_cuadrilla INT,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id_cuadrilla)
);

CREATE TABLE IF NOT EXISTS tipo_mantenimiento (
    id_tipo_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS mantenimiento (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    descripcion TEXT,
    fecha DATE,
    costo DECIMAL(10,2),
    estado VARCHAR(30),
    id_tipo_mantenimiento INT,
    id_camion INT,
    FOREIGN KEY (id_tipo_mantenimiento) REFERENCES tipo_mantenimiento(id_tipo_mantenimiento),
    FOREIGN KEY (id_camion) REFERENCES camion(id_camion)
);

CREATE TABLE IF NOT EXISTS instalacion (
    id_instalacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    calle VARCHAR(100),
    numero VARCHAR(10),
    telefono VARCHAR(20),
    horario VARCHAR(100),
    capacidad DECIMAL(10,2),
    estado VARCHAR(30)
);

CREATE TABLE IF NOT EXISTS traslado_residuo (
    id_traslado INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE,
    cantidad DECIMAL(10,2),
    observaciones TEXT,
    estado VARCHAR(30),
    id_camion INT,
    id_tipo_residuo INT,
    id_instalacion INT,
    FOREIGN KEY (id_camion) REFERENCES camion(id_camion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES tipo_residuo(id_tipo_residuo),
    FOREIGN KEY (id_instalacion) REFERENCES instalacion(id_instalacion)
);

CREATE TABLE IF NOT EXISTS repuesto (
    id_repuesto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    costo DECIMAL(10,2),
    stock_minimo INT,
    cantidad INT,
    id_instalacion INT,
    FOREIGN KEY (id_instalacion) REFERENCES instalacion(id_instalacion)
);

CREATE TABLE IF NOT EXISTS tipo_maquinaria (
    id_tipo_maquinaria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS maquinaria (
    id_maquinaria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    id_tipo_maquinaria INT,
    estado VARCHAR(30),
    id_instalacion INT,
    FOREIGN KEY (id_tipo_maquinaria) REFERENCES tipo_maquinaria(id_tipo_maquinaria),
    FOREIGN KEY (id_instalacion) REFERENCES instalacion(id_instalacion)
);

CREATE TABLE IF NOT EXISTS ruta_recoleccion (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    frecuencia VARCHAR(50),
    distancia DECIMAL(8,2),
    estado VARCHAR(30),
    hora_inicio TIME,
    hora_fin TIME,
    fecha DATE,
    id_cuadrilla INT,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id_cuadrilla)
);

CREATE TABLE IF NOT EXISTS tipo_reparacion (
    id_tipo_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS reparacion_contenedor (
    id_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(30),
    costo DECIMAL(10,2),
    observaciones TEXT,
    fecha_ingreso DATE,
    fecha_salida DATE,
    id_tipo_reparacion INT,
    id_contenedor INT,
    FOREIGN KEY (id_tipo_reparacion) REFERENCES tipo_reparacion(id_tipo_reparacion),
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id_contenedor)
);

CREATE TABLE IF NOT EXISTS tipo_prediccion (
    id_tipo_prediccion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    descripcion VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS prediccion (
    id_prediccion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_objetivo DATE,
    fecha_generacion DATE,
    valor_estimado DECIMAL(10,2),
    nivel_confianza DECIMAL(5,2),
    estado VARCHAR(30),
    id_tipo_prediccion INT,
    id_ubicacion INT,
    FOREIGN KEY (id_tipo_prediccion) REFERENCES tipo_prediccion(id_tipo_prediccion),
    FOREIGN KEY (id_ubicacion) REFERENCES ubicacion(id_ubicacion)
);

CREATE TABLE IF NOT EXISTS instalacion_tipo_residuo (
    id_instalacion INT,
    id_tipo_residuo INT,
    PRIMARY KEY (id_instalacion, id_tipo_residuo),
    FOREIGN KEY (id_instalacion) REFERENCES instalacion(id_instalacion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES tipo_residuo(id_tipo_residuo)
);

CREATE TABLE IF NOT EXISTS ruta_ubicacion (
    id_ruta INT,
    id_ubicacion INT,
    PRIMARY KEY (id_ruta, id_ubicacion),
    FOREIGN KEY (id_ruta) REFERENCES ruta_recoleccion(id_ruta),
    FOREIGN KEY (id_ubicacion) REFERENCES ubicacion(id_ubicacion)
);