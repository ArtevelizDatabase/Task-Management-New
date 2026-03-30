<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-grup pivot: pakai platform (kolom EE/GR/CM) atau tidak; kombinasi dengan has_file_types.
 */
class AddHasPlatformProductGroups extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_product_groups')) {
            return;
        }
        if ($this->db->fieldExists('has_platform', 'tb_product_groups')) {
            return;
        }
        $this->forge->addColumn('tb_product_groups', [
            'has_platform' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('tb_product_groups')) {
            return;
        }
        if ($this->db->fieldExists('has_platform', 'tb_product_groups')) {
            $this->forge->dropColumn('tb_product_groups', 'has_platform');
        }
    }
}
