<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Remove_invoice_counters_table extends CI_Migration
{
    public function up()
    {
        $this->db->query('DROP TABLE IF EXISTS invoice_counters');
    }

    public function down()
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS invoice_counters (counter_date DATE NOT NULL, last_number INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (counter_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->db->query("INSERT INTO invoice_counters (counter_date, last_number)
            SELECT STR_TO_DATE(SUBSTRING_INDEX(SUBSTRING_INDEX(invoice_number, '-', 2), '-', -1), '%Y%m%d'),
                MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED))
            FROM invoices WHERE invoice_number REGEXP '^[A-Z0-9]+-[0-9]{8}-[0-9]+$'
            GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(invoice_number, '-', 2), '-', -1)
            ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))");
    }
}
