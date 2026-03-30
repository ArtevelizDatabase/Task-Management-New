<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Scoped field definitions: internal (project_id NULL) vs per-project (FK tb_projects).
 * Unique (logical scope, field_key) via generated field_scope_uid = IFNULL(project_id, 0).
 */
class AddProjectIdToFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_fields')) {
            return;
        }
        if ($this->db->fieldExists('project_id', 'tb_fields')) {
            return;
        }

        $after = 'status';
        $names = $this->db->getFieldNames('tb_fields');
        if (in_array('order_no', $names, true)) {
            $after = 'order_no';
        }

        $this->forge->addColumn('tb_fields', [
            'project_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => $after,
            ],
        ]);

        if ($this->db->tableExists('tb_projects')) {
            try {
                $this->db->query('ALTER TABLE tb_fields ADD CONSTRAINT fk_fields_project
                    FOREIGN KEY (project_id) REFERENCES tb_projects(id) ON DELETE CASCADE');
            } catch (\Throwable) {
                // ignore if engine cannot add FK
            }
        }

        if ($this->db->DBDriver === 'MySQLi') {
            try {
                $this->db->query(
                    'ALTER TABLE tb_fields ADD COLUMN field_scope_uid INT UNSIGNED
                    GENERATED ALWAYS AS (IFNULL(project_id, 0)) STORED'
                );
                $this->db->query(
                    'ALTER TABLE tb_fields ADD UNIQUE KEY uq_fields_scope_key (field_scope_uid, field_key)'
                );
            } catch (\Throwable $e) {
                log_message('warning', 'tb_fields.field_scope_uid migration skipped: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('tb_fields')) {
            return;
        }

        if ($this->db->DBDriver === 'MySQLi') {
            try {
                $this->db->query('ALTER TABLE tb_fields DROP INDEX uq_fields_scope_key');
            } catch (\Throwable) {
                // ignore
            }
            try {
                if ($this->db->fieldExists('field_scope_uid', 'tb_fields')) {
                    $this->forge->dropColumn('tb_fields', 'field_scope_uid');
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        try {
            $this->db->query('ALTER TABLE tb_fields DROP FOREIGN KEY fk_fields_project');
        } catch (\Throwable) {
            // ignore
        }

        if ($this->db->fieldExists('project_id', 'tb_fields')) {
            $this->forge->dropColumn('tb_fields', 'project_id');
        }
    }
}
