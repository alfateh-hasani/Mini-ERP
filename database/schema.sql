-- Mini ERP Stocks & Sales (CI3) fresh-install schema
-- MySQL 8.0+, InnoDB, utf8mb4. All monetary amounts are Saudi Riyal (SAR).
CREATE DATABASE IF NOT EXISTS mini_erp_smart_life CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mini_erp_smart_life;

CREATE TABLE settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE warehouses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  address VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user_warehouse') NOT NULL,
  preferred_lang ENUM('ar','en') NOT NULL DEFAULT 'ar',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_warehouse (warehouse_id),
  CONSTRAINT fk_users_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT chk_users_role_warehouse CHECK ((role = 'admin') OR (warehouse_id IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(200) NOT NULL,
  name_ar VARCHAR(200) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  unit_ar VARCHAR(30) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  alert_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_code (code),
  KEY idx_products_category_status (category_id, status),
  KEY idx_products_search_code (code),
  KEY idx_products_search_name (name),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT chk_products_price CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_warehouse_product (warehouse_id, product_id),
  KEY idx_inventory_product_warehouse (product_id, warehouse_id),
  CONSTRAINT fk_inventory_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_phone (phone),
  UNIQUE KEY uq_customers_email (email),
  CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE invoice_counters (
  counter_date DATE NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (counter_date)
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(40) NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  status ENUM('issued','cancelled') NOT NULL DEFAULT 'issued',
  subtotal DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL,
  cancelled_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (invoice_number),
  KEY idx_invoices_warehouse_date (warehouse_id, created_at),
  KEY idx_invoices_customer_date (customer_id, created_at),
  CONSTRAINT fk_invoices_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_invoices_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id),
  CONSTRAINT chk_invoices_amounts CHECK (subtotal >= 0 AND discount_amount >= 0 AND total >= 0)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(50) NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  product_name_ar VARCHAR(200) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_invoice_items_invoice (invoice_id),
  KEY idx_invoice_items_product (product_id),
  CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_invoice_items_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_invoice_items_quantity CHECK (quantity > 0),
  CONSTRAINT chk_invoice_items_amounts CHECK (unit_price >= 0 AND discount_amount >= 0 AND line_total >= 0)
) ENGINE=InnoDB;

CREATE TABLE ci_sessions (
  id VARCHAR(128) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  timestamp INT UNSIGNED NOT NULL DEFAULT 0,
  data BLOB NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ci_sessions_timestamp (timestamp)
) ENGINE=InnoDB;