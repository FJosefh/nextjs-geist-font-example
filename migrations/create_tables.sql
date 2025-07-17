-- Script de creación de tablas para el Sistema de Inventario
-- Ejecutar este script en MySQL para crear la base de datos

CREATE DATABASE IF NOT EXISTS inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_db;

-- Tabla de usuarios
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    group_role ENUM('Administrador', 'Will', 'Spare Parts', 'Global') NOT NULL,
    role_detail VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE
);

-- Tabla de almacenes
DROP TABLE IF EXISTS warehouses;
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT TRUE
);

-- Tabla de productos
DROP TABLE IF EXISTS products;
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    stock_callao INT DEFAULT 0,
    stock_spare_parts INT DEFAULT 0,
    stock_will INT DEFAULT 0,
    stock_will_aqp INT DEFAULT 0,
    total_used INT DEFAULT 0,
    technician_assigned INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (technician_assigned) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabla de movimientos de inventario (trazabilidad)
DROP TABLE IF EXISTS inventory_movements;
CREATE TABLE inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    action ENUM('entrada', 'salida', 'traslado', 'ajuste') NOT NULL,
    quantity INT NOT NULL,
    warehouse_origin VARCHAR(20),
    warehouse_destination VARCHAR(20),
    motive TEXT NOT NULL,
    reference_type ENUM('rs_order', 'sales_order', 'request', 'manual') NULL,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de órdenes de servicio (RS) - Solo Will
DROP TABLE IF EXISTS rs_orders;
CREATE TABLE rs_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rs_number VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('Instalación', 'Prueba') NOT NULL,
    technician_id INT NOT NULL,
    status ENUM('Pendiente', 'En Proceso', 'Completada', 'Cancelada') DEFAULT 'Pendiente',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de detalles de RS (productos asignados a cada RS)
DROP TABLE IF EXISTS rs_order_details;
CREATE TABLE rs_order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rs_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rs_order_id) REFERENCES rs_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de órdenes de venta - Solo Spare Parts
DROP TABLE IF EXISTS sales_orders;
CREATE TABLE sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    seller_id INT NOT NULL,
    customer_name VARCHAR(100),
    total_price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('Pendiente', 'Completada', 'Cancelada') DEFAULT 'Pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de detalles de órdenes de venta
DROP TABLE IF EXISTS sales_order_details;
CREATE TABLE sales_order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabla de solicitudes de repuestos
DROP TABLE IF EXISTS repuesto_requests;
CREATE TABLE repuesto_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    requester_id INT NOT NULL,
    group_origin ENUM('Will', 'Spare Parts') NOT NULL,
    motive ENUM('Instalación', 'Prueba', 'Falta de Stock') NOT NULL,
    status ENUM('Pendiente', 'Listo para recojo', 'Completada', 'Cancelada') DEFAULT 'Pendiente',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de detalles de solicitudes de repuestos
DROP TABLE IF EXISTS repuesto_request_details;
CREATE TABLE repuesto_request_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    assigned_quantity INT DEFAULT 0,
    FOREIGN KEY (request_id) REFERENCES repuesto_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabla de devoluciones por garantía
DROP TABLE IF EXISTS warranty_returns;
CREATE TABLE warranty_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    group_origin ENUM('Spare Parts', 'Will') NOT NULL,
    sales_order_id INT NULL,
    rs_order_id INT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (rs_order_id) REFERENCES rs_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insertar almacenes por defecto
INSERT INTO warehouses (name, code, description) VALUES
('Callao', 'callao', 'Almacén Central'),
('Spare Parts', 'spare_parts', 'Almacén de Ventas'),
('Will', 'will', 'Almacén de Soporte Técnico');

-- Insertar usuario administrador por defecto
INSERT INTO users (username, password, name, group_role, role_detail) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Administrador', 'Admin');
-- Contraseña por defecto: password

-- Insertar algunos productos de ejemplo
INSERT INTO products (sku, name, description, price, stock_callao, stock_spare_parts, stock_will) VALUES
('SKU001', 'Repuesto Motor A1', 'Motor principal para equipos serie A', 150.00, 10, 5, 3),
('SKU002', 'Filtro de Aire B2', 'Filtro de aire estándar', 25.50, 20, 8, 12),
('SKU003', 'Sensor de Temperatura C3', 'Sensor digital de temperatura', 75.00, 15, 6, 4),
('SKU004', 'Cable de Conexión D4', 'Cable de conexión 2 metros', 12.00, 50, 25, 15),
('SKU005', 'Placa Controladora E5', 'Placa controladora principal', 200.00, 8, 3, 2);
