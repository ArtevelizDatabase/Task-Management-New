<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScopeToFields extends Migration
{
    public function up(): void
    {
        // Add scope column only if it doesn't exist yet (idempotent)
        $fields = $this->db->getFieldNames('tb_fields');
        if (!in_array('scope', $fields)) {
            $this->forge->addColumn('tb_fields', [
                'scope' => [
                    'type'       => 'ENUM',
                    'constraint' => ['task', 'setor', 'both'],
                    'default'    => 'task',
                    'null'       => false,
                    'after'      => 'submission_col',
                ],
            ]);

            // Auto-migrate: fields that already have submission_col → scope = both
            $this->db->query("
                UPDATE tb_fields
                SET scope = 'both'
                WHERE submission_col IS NOT NULL AND submission_col != ''
            ");
        }
    }

    public function down(): void
    {
        $fields = $this->db->getFieldNames('tb_fields');
        if (in_array('scope', $fields)) {
            $this->forge->dropColumn('tb_fields', 'scope');
        }
    }
}
