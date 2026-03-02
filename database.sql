-- Fruit Shop Database Schema
-- Run this file to create the database and tables

CREATE DATABASE IF NOT EXISTS fruit_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fruit_shop;

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fruits table
CREATE TABLE IF NOT EXISTS fruits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    actual_price DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(50) DEFAULT 'General',
    in_stock BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Fruit images table (max 5 per fruit)
CREATE TABLE IF NOT EXISTS fruit_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fruit_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fruit_id) REFERENCES fruits(id) ON DELETE CASCADE
);

-- Default admin account
-- Username: admin | Password: admin123
INSERT INTO admins (username, email, password) VALUES
('admin', 'admin@fruitshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample fruits data
INSERT INTO fruits (name, description, actual_price, discount_percentage, category, in_stock) VALUES
('Red Apple', 'Fresh, crisp red apples sourced from the finest orchards. Rich in fiber and antioxidants. Perfect for snacking or baking.', 2.99, 10.00, 'Pome Fruits', TRUE),
('Banana', 'Sweet and creamy bananas, packed with potassium and natural energy. Great for smoothies or eating on the go.', 1.49, 0.00, 'Tropical', TRUE),
('Strawberry', 'Plump, juicy strawberries bursting with flavor. High in vitamin C and antioxidants. Perfect for desserts and salads.', 4.99, 20.00, 'Berries', TRUE),
('Mango', 'Exotic Alphonso mangoes with a rich, sweet aroma. Known as the king of fruits. A tropical delight.', 3.99, 15.00, 'Tropical', TRUE),
('Grapes', 'Seedless green grapes, sweet and refreshing. Perfect for snacking or adding to fruit platters.', 3.49, 5.00, 'Vine Fruits', TRUE),
('Orange', 'Juicy navel oranges loaded with vitamin C. Perfect for fresh juice or peeling as a healthy snack.', 1.99, 0.00, 'Citrus', TRUE);
