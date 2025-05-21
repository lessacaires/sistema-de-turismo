-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE saas;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'manager', 'receptionist', 'waiter', 'bartender', 'cashier') NOT NULL DEFAULT 'receptionist',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de funcionários
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    cpf VARCHAR(20) UNIQUE,
    birth_date DATE,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    position VARCHAR(50) NOT NULL,
    salary DECIMAL(10, 2),
    hire_date DATE NOT NULL,
    termination_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    cpf VARCHAR(20) UNIQUE,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    birth_date DATE,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
);

-- Tabela de categorias de produtos
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    type ENUM('food', 'beverage', 'tour', 'merchandise', 'service') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de produtos
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2),
    stock_quantity INT,
    min_stock_quantity INT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
);

-- Tabela de passeios
CREATE TABLE IF NOT EXISTS tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    duration VARCHAR(50),
    departure_time TIME,
    departure_location VARCHAR(100),
    max_participants INT,
    guide_info TEXT,
    included_items TEXT,
    requirements TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabela de agendamentos de passeios
CREATE TABLE IF NOT EXISTS tour_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tour_id INT NOT NULL,
    date DATE NOT NULL,
    available_spots INT NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE
);

-- Tabela de reservas de passeios
CREATE TABLE IF NOT EXISTS tour_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    customer_id INT,
    employee_id INT,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    num_participants INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50),
    notes TEXT,
    FOREIGN KEY (schedule_id) REFERENCES tour_schedules(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Tabela de mesas
CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(10) NOT NULL,
    capacity INT NOT NULL,
    location ENUM('restaurant', 'bar', 'outdoor') NOT NULL DEFAULT 'restaurant',
    status ENUM('available', 'occupied', 'reserved', 'maintenance') NOT NULL DEFAULT 'available'
);

-- Tabela de pedidos/comandas
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT,
    customer_id INT,
    employee_id INT,
    order_type ENUM('table', 'takeaway', 'delivery', 'tour') NOT NULL DEFAULT 'table',
    status ENUM('open', 'in_progress', 'ready', 'delivered', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP,
    total_amount DECIMAL(10, 2) DEFAULT 0,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Tabela de itens do pedido
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'preparing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabela de fornecedores
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    cnpj VARCHAR(20) UNIQUE,
    contact_name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de compras
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    employee_id INT,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_delivery_date DATE,
    delivery_date DATE,
    status ENUM('pending', 'partial', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Tabela de itens da compra
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'partial', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabela de movimentações de estoque
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    movement_type ENUM('purchase', 'sale', 'adjustment', 'loss', 'transfer') NOT NULL,
    reference_id INT,
    reference_type VARCHAR(50),
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    employee_id INT,
    notes TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Tabela de transações financeiras
CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_id INT,
    reference_type VARCHAR(50),
    employee_id INT,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Inserir usuário administrador padrão (senha: admin123)
INSERT INTO users (username, password, email, role, is_active) 
VALUES ('admin', '$2y$10$8zf0SXIRQlJJJGQPl5QF6.jkS.H9Vk3YKIzeNhXZKmAqXWDFU5vMi', 'admin@example.com', 'admin', 1);

-- Inserir funcionário administrador
INSERT INTO employees (user_id, full_name, position, hire_date)
VALUES (1, 'Administrador do Sistema', 'Administrador', CURDATE());

-- Inserir categorias de produtos padrão
INSERT INTO product_categories (name, description, type) VALUES 
('Passeios', 'Passeios turísticos', 'tour'),
('Pratos', 'Pratos do restaurante', 'food'),
('Bebidas', 'Bebidas do bar', 'beverage'),
('Souvenirs', 'Lembranças e produtos para venda', 'merchandise');

-- Inserir alguns produtos de exemplo
INSERT INTO products (category_id, name, description, price, stock_quantity, min_stock_quantity, is_active) VALUES
(2, 'Prato do Dia', 'Prato especial do chef', 35.90, 100, 10, 1),
(3, 'Água Mineral', 'Garrafa 500ml', 5.00, 50, 20, 1),
(3, 'Refrigerante', 'Lata 350ml', 6.00, 48, 24, 1),
(3, 'Cerveja', 'Long neck 355ml', 12.00, 60, 24, 1),
(4, 'Camiseta', 'Camiseta com logo', 49.90, 30, 5, 1);

-- Inserir um passeio de exemplo
INSERT INTO products (category_id, name, description, price, is_active) VALUES
(1, 'Passeio de Barco', 'Passeio de barco pelas praias', 150.00, 1);

INSERT INTO tours (product_id, duration, departure_time, departure_location, max_participants)
VALUES (LAST_INSERT_ID(), '4 horas', '09:00:00', 'Porto Principal', 20);

-- Inserir mesas de exemplo
INSERT INTO tables (number, capacity, location, status) VALUES
('1', 4, 'restaurant', 'available'),
('2', 4, 'restaurant', 'available'),
('3', 6, 'restaurant', 'available'),
('B1', 2, 'bar', 'available'),
('B2', 2, 'bar', 'available'),
('E1', 8, 'outdoor', 'available');

-- Inserir um fornecedor de exemplo
INSERT INTO suppliers (name, contact_name, email, phone, is_active)
VALUES ('Fornecedor Geral', 'João Silva', 'contato@fornecedor.com', '(11) 98765-4321', 1);
