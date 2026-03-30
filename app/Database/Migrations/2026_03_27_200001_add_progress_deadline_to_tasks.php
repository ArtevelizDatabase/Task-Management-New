<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProgressDeadlineToTasks extends Migration
{
    public function up(): void
    {
        $cols = $this->db->getFieldNames('tb_task');

        if (!in_array('progress', $cols)) {
            $this->forge->addColumn('tb_task', [
                'progress' => [
                    'type'       => 'TINYINT',
                    'constraint' => 3,
                    'unsigned'   => true,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'status',
                ],
            ]);
        }

        if (!in_array('deadline', $cols)) {
            $this->forge->addColumn('tb_task', [
                'deadline' => [
                    'type'    => 'DATE',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'progress',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $cols = $this->db->getFieldNames('tb_task');
        if (in_array('progress', $cols))  $this->forge->dropColumn('tb_task', 'progress');
        if (in_array('deadline', $cols))  $this->forge->dropColumn('tb_task', 'deadline');
    }
}
