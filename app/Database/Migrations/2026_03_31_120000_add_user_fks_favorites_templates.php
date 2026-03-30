<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BUG-L1 / BUG-L2: FK user_id → tb_users untuk favorit & template.
 */
class AddUserFksFavoritesTemplates extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('tb_user_favorites') && $this->db->tableExists('tb_users')) {
            $this->db->query(
                'DELETE uf FROM tb_user_favorites uf
                 LEFT JOIN tb_users u ON u.id = uf.user_id
                 WHERE u.id IS NULL'
            );
            try {
                $this->db->query(
                    'ALTER TABLE tb_user_favorites
                     ADD CONSTRAINT fk_favorites_user
                     FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE'
                );
            } catch (\Throwable) {
                // constraint sudah ada atau engine tidak mendukung
            }
        }

        if ($this->db->tableExists('tb_task_templates') && $this->db->tableExists('tb_users')) {
            $this->db->query(
                'DELETE tt FROM tb_task_templates tt
                 LEFT JOIN tb_users u ON u.id = tt.created_by
                 WHERE u.id IS NULL'
            );
            try {
                $this->db->query(
                    'ALTER TABLE tb_task_templates
                     ADD CONSTRAINT fk_templates_created_by
                     FOREIGN KEY (created_by) REFERENCES tb_users(id) ON DELETE CASCADE'
                );
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('tb_user_favorites')) {
            try {
                $this->db->query('ALTER TABLE tb_user_favorites DROP FOREIGN KEY fk_favorites_user');
            } catch (\Throwable) {
            }
        }
        if ($this->db->tableExists('tb_task_templates')) {
            try {
                $this->db->query('ALTER TABLE tb_task_templates DROP FOREIGN KEY fk_templates_created_by');
            } catch (\Throwable) {
            }
        }
    }
}
