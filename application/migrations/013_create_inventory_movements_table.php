<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_inventory_movements_table extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS inventory_movements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            warehouse_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            movement_type ENUM('set','add','remove') NOT NULL,
            quantity_before INT UNSIGNED NOT NULL,
            quantity_change INT NOT NULL,
            quantity_after INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_inventory_movements_warehouse_product (warehouse_id, product_id),
            KEY idx_inventory_movements_created_at (created_at),
            CONSTRAINT fk_inventory_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_inventory_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_inventory_movements_user FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS inventory_movements');
    }
}
