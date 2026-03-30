<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDataSourceToFields extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_fields')) {
            return;
        }

        $columns = $this->db->getFieldNames('tb_fields');

        if (!in_array('data_source', $columns, true)) {
            $this->forge->addColumn('tb_fields', [
                'data_source' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'manual',
                    'null'       => false,
                    'after'      => 'scope',
                ],
            ]);
        }

        if (!in_array('source_config', $columns, true)) {
            $this->forge->addColumn('tb_fields', [
                'source_config' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'data_source',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('tb_fields')) {
            return;
        }

        $columns = $this->db->getFieldNames('tb_fields');
        if (in_array('source_config', $columns, true)) {
            $this->forge->dropColumn('tb_fields', 'source_config');
        }
        if (in_array('data_source', $columns, true)) {
            $this->forge->dropColumn('tb_fields', 'data_source');
        }
    }
}
