CREATE DATABASE IF NOT EXISTS cerbero;
USE cerbero;

CREATE TABLE ROL (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
);

CREATE TABLE CUADRILLA (
    id_cuadrilla INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    turno VARCHAR(30),
    estado VARCHAR(30)
);

CREATE TABLE USUARIO (
    ci CHAR(8) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contraseña_hash VARCHAR(255) NOT NULL,
    fecha_registro DATETIME,
    estado VARCHAR(30),
    id_rol INT,
    id_cuadrilla INT,
    FOREIGN KEY (id_rol) REFERENCES ROL(id_rol),
    FOREIGN KEY (id_cuadrilla) REFERENCES CUADRILLA(id_cuadrilla)
);

CREATE TABLE NOTIFICACION (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100),
    mensaje TEXT,
    fecha_hora DATETIME,
    estado VARCHAR(30),
    ci CHAR(8),
    FOREIGN KEY (ci) REFERENCES USUARIO(ci)
);

CREATE TABLE TIPO_INCIDENCIA (
    id_tipo_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    descripcion VARCHAR(255)
);

CREATE TABLE UBICACION (
    id_ubicacion INT AUTO_INCREMENT PRIMARY KEY,
    barrio VARCHAR(100),
    calle VARCHAR(100),
    numero VARCHAR(10),
    latitud DECIMAL(10,7),
    longitud DECIMAL(10,7)
);

CREATE TABLE TIPO_RESIDUO (
    id_tipo_residuo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE CONTENEDOR (
    id_contenedor INT AUTO_INCREMENT PRIMARY KEY,
    capacidad DECIMAL(8,2),
    nivel_llenado DECIMAL(5,2),
    fecha_instalacion DATE,
    fecha_ultima_recoleccion DATE,
    estado VARCHAR(30),
    id_ubicacion INT,
    id_tipo_residuo INT,
    FOREIGN KEY (id_ubicacion) REFERENCES UBICACION(id_ubicacion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES TIPO_RESIDUO(id_tipo_residuo)
);

CREATE TABLE INCIDENCIA (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    descripcion TEXT,
    fecha_hora_registro DATETIME,
    fecha_hora_cierre DATETIME,
    estado VARCHAR(30),
    id_tipo_incidencia INT,
    ci CHAR(8),
    id_cuadrilla INT,
    id_contenedor INT,
    FOREIGN KEY (id_tipo_incidencia) REFERENCES TIPO_INCIDENCIA(id_tipo_incidencia),
    FOREIGN KEY (ci) REFERENCES USUARIO(ci),
    FOREIGN KEY (id_cuadrilla) REFERENCES CUADRILLA(id_cuadrilla),
    FOREIGN KEY (id_contenedor) REFERENCES CONTENEDOR(id_contenedor)
);

CREATE TABLE CAMION (
    id_camion INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) UNIQUE,
    modelo VARCHAR(50),
    marca VARCHAR(50),
    anio YEAR,
    kilometraje INT,
    capacidad DECIMAL(8,2),
    estado VARCHAR(30),
    id_cuadrilla INT,
    FOREIGN KEY (id_cuadrilla) REFERENCES CUADRILLA(id_cuadrilla)
);

CREATE TABLE TIPO_MANTENIMIENTO (
    id_tipo_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE MANTENIMIENTO (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    descripcion TEXT,
    fecha DATE,
    costo DECIMAL(10,2),
    estado VARCHAR(30),
    id_tipo_mantenimiento INT,
    id_camion INT,
    FOREIGN KEY (id_tipo_mantenimiento) REFERENCES TIPO_MANTENIMIENTO(id_tipo_mantenimiento),
    FOREIGN KEY (id_camion) REFERENCES CAMION(id_camion)
);

CREATE TABLE INSTALACION (
    id_instalacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    calle VARCHAR(100),
    numero VARCHAR(10),
    telefono VARCHAR(20),
    horario VARCHAR(100),
    capacidad DECIMAL(10,2),
    estado VARCHAR(30)
);

CREATE TABLE TRASLADO_RESIDUO (
    id_traslado INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE,
    cantidad DECIMAL(10,2),
    observaciones TEXT,
    estado VARCHAR(30),
    id_camion INT,
    id_tipo_residuo INT,
    id_instalacion INT,
    FOREIGN KEY (id_camion) REFERENCES CAMION(id_camion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES TIPO_RESIDUO(id_tipo_residuo),
    FOREIGN KEY (id_instalacion) REFERENCES INSTALACION(id_instalacion)
);

CREATE TABLE REPUESTO (
    id_repuesto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    costo DECIMAL(10,2),
    stock_minimo INT,
    cantidad INT,
    id_instalacion INT,
    FOREIGN KEY (id_instalacion) REFERENCES INSTALACION(id_instalacion)
);

CREATE TABLE TIPO_MAQUINARIA (
    id_tipo_maquinaria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE MAQUINARIA (
    id_maquinaria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    id_tipo_maquinaria INT,
    estado VARCHAR(30),
    id_instalacion INT,
    FOREIGN KEY (id_tipo_maquinaria) REFERENCES TIPO_MAQUINARIA(id_tipo_maquinaria),
    FOREIGN KEY (id_instalacion) REFERENCES INSTALACION(id_instalacion)
);

CREATE TABLE RUTA_RECOLECCION (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    frecuencia VARCHAR(50),
    distancia DECIMAL(8,2),
    estado VARCHAR(30),
    hora_inicio TIME,
    hora_fin TIME,
    fecha DATE,
    id_cuadrilla INT,
    FOREIGN KEY (id_cuadrilla) REFERENCES CUADRILLA(id_cuadrilla)
);

CREATE TABLE TIPO_REPARACION (
    id_tipo_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)
);

CREATE TABLE REPARACION_CONTENEDOR (
    id_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(30),
    costo DECIMAL(10,2),
    observaciones TEXT,
    fecha_ingreso DATE,
    fecha_salida DATE,
    id_tipo_reparacion INT,
    id_contenedor INT,
    FOREIGN KEY (id_tipo_reparacion) REFERENCES TIPO_REPARACION(id_tipo_reparacion),
    FOREIGN KEY (id_contenedor) REFERENCES CONTENEDOR(id_contenedor)
);

CREATE TABLE TIPO_PREDICCION (
    id_tipo_prediccion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    descripcion VARCHAR(255)
);

CREATE TABLE PREDICCION (
    id_prediccion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_objetivo DATE,
    fecha_generacion DATE,
    valor_estimado DECIMAL(10,2),
    nivel_confianza DECIMAL(5,2),
    tipo_prediccion VARCHAR(50),
    estado VARCHAR(30),
    id_ubicacion INT,
    FOREIGN KEY (id_ubicacion) REFERENCES UBICACION(id_ubicacion)
);


CREATE TABLE INSTALACION_TIPO_RESIDUO (
    id_instalacion INT,
    id_tipo_residuo INT,
    PRIMARY KEY (id_instalacion, id_tipo_residuo),
    FOREIGN KEY (id_instalacion) REFERENCES INSTALACION(id_instalacion),
    FOREIGN KEY (id_tipo_residuo) REFERENCES TIPO_RESIDUO(id_tipo_residuo)
);

CREATE TABLE RUTA_UBICACION (
    id_ruta INT,
    id_ubicacion INT,
    PRIMARY KEY (id_ruta, id_ubicacion),
    FOREIGN KEY (id_ruta) REFERENCES RUTA_RECOLECCION(id_ruta),
    FOREIGN KEY (id_ubicacion) REFERENCES UBICACION(id_ubicacion)
);