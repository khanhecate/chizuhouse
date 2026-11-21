CREATE DATABASE IF NOT EXISTS chizuhouse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chizuhouse;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) DEFAULT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Untuk database lama yang dibuat sebelum ada fitur admin (MariaDB)
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER full_name;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  category ENUM('Snack','Cheese','Meal') NOT NULL,
  price INT UNSIGNED NOT NULL,
  description TEXT,
  image VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Menu awal, hanya diisi kalau tabel products masih kosong
INSERT INTO products (id, name, category, price, description, image)
SELECT * FROM (
  SELECT 1 AS id, 'Lumpia Kornet Mozzarella' AS name, 'Snack' AS category, 20000 AS price,
         'Lumpia dengan isian kornet gurih dan mozzarella.' AS description,
         'assets/images/lumpia-kornet-mozzarella.jpg' AS image
  UNION ALL SELECT 3, 'Bruleebomb', 'Cheese', 20000,
         'Bola Bola Creamy Yg di adon dengan susu dan didalamnya ada irisan beef dengan keju mozzarella.',
         'assets/images/bruleebomb.jpg'
  UNION ALL SELECT 5, 'Chicken Cordon Blue', 'Meal', 20000,
         'Ayam yg didalamnya terdapat irisan beef dan mozzarella.',
         'assets/images/chicken-cordon-bleu.jpg'
  UNION ALL SELECT 6, 'Pizza Springroll', 'Snack', 20000,
         'Kulit spring roll di isi dengan irisan beef yg ditumis, 2 jenis keju dan saus gurih creamy dipadukan dengan chilli oil.',
         'assets/images/pizza-springroll.jpg'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM products);
