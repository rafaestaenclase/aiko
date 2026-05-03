DROP DATABASE IF EXISTS qr_restaurant;
CREATE DATABASE qr_restaurant;
USE qr_restaurant;

CREATE TABLE tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    qr_code VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(6,2) NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    status ENUM('COOKING','DONE') DEFAULT 'COOKING',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    notes TEXT,
    status ENUM('PENDING','READY') DEFAULT 'PENDING',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE admin_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tables_qr     ON tables(qr_code);
CREATE INDEX idx_orders_table  ON orders(table_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_items_order   ON order_items(order_id);
CREATE INDEX idx_items_status  ON order_items(status);

INSERT INTO admin_tokens (token) VALUES
(SHA2('mi-clave-secreta-2024', 256));

INSERT INTO tables (name, qr_code) VALUES
('Mesa 1', 'QR-001'),
('Mesa 2', 'QR-002'),
('Mesa 3', 'QR-003'),
('Mesa 4', 'QR-004'),
('Barra',  'QR-005');

INSERT INTO categories (name, display_order) VALUES
('Ramen',   1),
('Extras',  2),
('Bebidas', 3);

INSERT INTO products (category_id, name, description, price, available) VALUES
(1, 'Ramen Tonkotsu',    'Caldo de hueso de cerdo, chashu, huevo marinado y nori', 13.90, TRUE),
(1, 'Ramen Shoyu',       'Caldo de soja con pollo, bambú y huevo',                 12.50, TRUE),
(1, 'Ramen Miso',        'Caldo de miso con maíz, mantequilla y setas',            13.50, TRUE),
(1, 'Ramen Spicy',       'Tonkotsu picante con pasta gochujang y chashu',          14.50, TRUE),
(1, 'Ramen Vegetariano', 'Caldo dashi con tofu, setas y maíz',                     12.00, FALSE),
(2, 'Huevo marinado',    'Huevo en salsa de soja y mirin',                          1.50, TRUE),
(2, 'Chashu extra',      'Dos lonchas de panceta estofada',                         2.90, TRUE),
(2, 'Fideos extra',      'Porción adicional de fideos',                             1.50, TRUE),
(2, 'Gyozas (4 pzs)',    'Empanadillas de cerdo a la plancha con salsa ponzu',      6.50, TRUE),
(3, 'Agua mineral',      '500ml con o sin gas',                                     1.80, TRUE),
(3, 'Té verde',          'Sencha japonés caliente o frío',                          2.50, TRUE),
(3, 'Cerveza Sapporo',   '330ml',                                                   3.50, TRUE),
(3, 'Refresco',          'Coca-Cola o Fanta 330ml',                                 2.50, TRUE),
(3, 'Sake frío',         '150ml',                                                   6.90, FALSE);

INSERT INTO orders (table_id, status, created_at) VALUES
(1, 'COOKING', '2024-06-01 13:05:00'),
(2, 'COOKING', '2024-06-01 13:20:00'),
(3, 'DONE',    '2024-06-01 12:10:00');

INSERT INTO order_items (order_id, product_id, quantity, notes, status, created_at) VALUES
(1, 1, 1, 'Sin cebollino', 'PENDING', '2024-06-01 13:06:00'),
(1, 6, 1, NULL,            'READY',   '2024-06-01 13:06:00'),
(1, 10, 2, NULL,           'READY',   '2024-06-01 13:06:00'),
(2, 3, 1, 'Extra picante', 'PENDING', '2024-06-01 13:21:00'),
(2, 9, 2, NULL,            'PENDING', '2024-06-01 13:21:00'),
(2, 13, 1, NULL,           'PENDING', '2024-06-01 13:21:00'),
(3, 2, 2, NULL,            'READY',   '2024-06-01 12:11:00'),
(3, 11, 2, NULL,           'READY',   '2024-06-01 12:11:00');