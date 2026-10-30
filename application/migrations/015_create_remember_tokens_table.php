<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_remember_tokens_table extends CI_Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS remember_tokens (
            selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            auth_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expires_at INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (selector),
            KEY idx_remember_tokens_user (user_id),
            KEY idx_remember_tokens_expiry (expires_at),
            CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void { $this->db->query('DROP TABLE IF EXISTS remember_tokens'); }
}
