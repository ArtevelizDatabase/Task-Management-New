<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOfficeAccountsTable extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_office_accounts')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 140],
                'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
                'notes'      => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('name');
            $this->forge->addKey('status');
            $this->forge->createTable('tb_office_accounts');
        }

        $defaults = ['Annora', 'Gracnorine', 'Hayaroo'];
        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $name) {
            $exists = $this->db->table('tb_office_accounts')
                ->where('name', $name)
                ->countAllResults();
            if ((int) $exists > 0) {
                continue;
            }
            $this->db->table('tb_office_accounts')->insert([
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_office_accounts', true);
    }
}
