-- ============================
-- CREAR BASE DE DATOS
-- ============================
CREATE DATABASE IF NOT EXISTS todo_camisetas_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE todo_camisetas_db;

-- ============================
-- TABLA: rol
-- ============================
CREATE TABLE rol (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO rol (nombre) VALUES ('Admin'), ('Editor'), ('Cliente');

-- ============================
-- TABLA: cliente (B2B)
-- ============================
CREATE TABLE cliente (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_comercial VARCHAR(150) NOT NULL UNIQUE,
  rut VARCHAR(30) NULL,
  direccion VARCHAR(255) NULL,
  categoria ENUM('Regular','Preferencial') NOT NULL DEFAULT 'Regular',
  contacto_nombre VARCHAR(120) NULL,
  contacto_email VARCHAR(120) NULL,
  porcentaje_descuento DECIMAL(5,2) NOT NULL DEFAULT 0.00, -- descuento en %
  creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================
-- TABLA: usuario
-- ============================
CREATE TABLE usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    fecha_nacimiento DATE,
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    rol_id INT NOT NULL,
    cliente_id INT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (rol_id) REFERENCES rol(id),
    FOREIGN KEY (cliente_id) REFERENCES cliente(id)
      ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_usr_activo CHECK (activo IN (0,1))
);

-- ============================
-- TABLA: producto (camisetas)
-- ============================
CREATE TABLE producto (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  club VARCHAR(150) NULL,
  pais VARCHAR(80) NULL,
  tipo VARCHAR(80) NULL, -- ej: Local, Visita, 3era, etc.
  color VARCHAR(120) NULL,
  precio DECIMAL(12,0) NOT NULL,        
  precio_oferta DECIMAL(12,0) DEFAULT NULL,
  detalles TEXT NULL,
  sku VARCHAR(80) NOT NULL UNIQUE,
  creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================
-- TABLA: talla y relación producto-talla (stock)
-- ============================
CREATE TABLE talla (
  id INT AUTO_INCREMENT PRIMARY KEY,
  talla VARCHAR(20) NOT NULL UNIQUE
);

CREATE TABLE producto_talla_stock (
  producto_id INT NOT NULL,
  talla_id INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  PRIMARY KEY (producto_id, talla_id),
  CONSTRAINT fk_pts_producto FOREIGN KEY (producto_id) REFERENCES producto(id) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pts_talla FOREIGN KEY (talla_id) REFERENCES talla(id) 
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ============================
-- DATOS DE EJEMPLO
-- ============================

-- Clientes
INSERT INTO cliente (nombre_comercial, rut, direccion, categoria, contacto_nombre, contacto_email, porcentaje_descuento)
VALUES
('90minutos','76.543.210-9','Providencia, Santiago','Preferencial','María Pérez','compras@90minutos.cl', 0.00),
('tdeportes','76.111.222-3','Ñuñoa, Santiago','Regular','Jorge Díaz','jorge@tdeportes.cl', 0.00),
('MayoristaSport','76.999.888-1','Estación Central, Santiago','Preferencial','Lucía Gómez','ventas@mayoristasport.cl', 5.00);

-- Usuarios
INSERT INTO usuario (nombre, apellido, email, contrasena, fecha_nacimiento, telefono, direccion, rol_id, cliente_id)
VALUES
('Carlos', 'González', 'carlos@example.com', '$2y$12$Vu7Zg8PlsZY2HeAaQpXQ5O70olRBViMCjU1910uJ.HegeAqj7jbV6', '1995-08-21', '+56912345678', 'Calle Falsa 123, Santiago', 1, NULL), -- Admin
('Ana', 'Ramírez', 'ana@example.com', '$2y$12$Vu7Zg8PlsZY2HeAaQpXQ5O70olRBViMCjU1910uJ.HegeAqj7jbV6', '1992-04-10', '+56987654321', 'Av. Libertad 456, Santiago', 2, NULL), -- Editor
('Juan', 'Pérez', 'juan@example.com', '$2y$12$Vu7Zg8PlsZY2HeAaQpXQ5O70olRBViMCjU1910uJ.HegeAqj7jbV6', '1998-11-05', '+56911112222', 'Calle Luna 789, Santiago', 3, 1), -- Cliente de 90minutos
('María', 'López', 'maria@example.com', '$2y$12$Vu7Zg8PlsZY2HeAaQpXQ5O70olRBViMCjU1910uJ.HegeAqj7jbV6', '2000-02-15', '+56933334444', 'Calle Sol 321, Santiago', 3, 2); -- Cliente de tdeportes

-- Talllas comunes
INSERT IGNORE INTO talla (talla) VALUES ('S'),('M'),('L'),('XL'),('XXL');

-- Productos
INSERT INTO producto (titulo, club, pais, tipo, color, precio, precio_oferta, detalles, sku)
VALUES
('Camiseta Local 2025 - Selección Chilena', 'Selección Chilena', 'Chile', 'Local', 'Rojo y Azul', 45000, 40000, 'Edición aniversario 2025', 'SCL2025L'),
('Camiseta Visita 2025 - Selección Chilena', 'Selección Chilena', 'Chile', 'Visita', 'Blanco con detalles azules', 45000, NULL, 'Versión visitante', 'SCL2025V'),
('Camiseta ClubX 3era 2024', 'Club X', 'España', '3era Camiseta', 'Negro y Blanco', 35000, 30000, 'Modelo 3ra temporada', 'CLUBX300');

-- Stock
INSERT INTO producto_talla_stock (producto_id, talla_id, stock)
VALUES
(1, 1, 10),(1,2,20),(1,3,15),(1,4,5),
(2,1,8),(2,2,12),(2,3,10),
(3,2,5),(3,3,7);
