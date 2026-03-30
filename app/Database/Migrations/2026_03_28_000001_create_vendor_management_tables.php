<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVendorManagementTables extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_vendor_accounts')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'         => ['type' => 'VARCHAR', 'constraint' => 140],
                'platform'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'owner_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'status'       => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
                'notes'        => ['type' => 'TEXT', 'null' => true],
                'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('name');
            $this->forge->addKey('status');
            $this->forge->createTable('tb_vendor_accounts');
        }

        if (!$this->db->tableExists('tb_vendor_targets')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'vendor_account_id' => ['type' => 'INT', 'unsigned' => true],
                'period_type'       => ['type' => 'ENUM', 'constraint' => ['daily', 'weekly', 'monthly'], 'default' => 'monthly'],
                'period_start'      => ['type' => 'DATE'],
                'period_end'        => ['type' => 'DATE', 'null' => true],
                'target_value'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('vendor_account_id');
            $this->forge->addKey(['period_type', 'period_start']);
            $this->forge->addUniqueKey(['vendor_account_id', 'period_type', 'period_start']);
            $this->forge->createTable('tb_vendor_targets');
        }

        if (!$this->db->tableExists('tb_vendor_allocations')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'vendor_account_id' => ['type' => 'INT', 'unsigned' => true],
                'user_id'           => ['type' => 'INT', 'unsigned' => true],
                'is_primary'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('vendor_account_id');
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey(['vendor_account_id', 'user_id']);
            $this->forge->createTable('tb_vendor_allocations');
        }

        if (!$this->db->tableExists('tb_assignment_rules')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'vendor_account_id' => ['type' => 'INT', 'unsigned' => true],
                'default_user_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'default_team_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'status'            => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
                'priority'          => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
                'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['vendor_account_id', 'status', 'priority']);
            $this->forge->addKey('default_user_id');
            $this->forge->addKey('default_team_id');
            $this->forge->createTable('tb_assignment_rules');
        }

        if ($this->db->tableExists('tb_task') && !$this->db->fieldExists('vendor_account_id', 'tb_task')) {
            $this->forge->addColumn('tb_task', [
                'vendor_account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'user_id'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('tb_task') && $this->db->fieldExists('vendor_account_id', 'tb_task')) {
            $this->forge->dropColumn('tb_task', 'vendor_account_id');
        }

        $this->forge->dropTable('tb_assignment_rules', true);
        $this->forge->dropTable('tb_vendor_allocations', true);
        $this->forge->dropTable('tb_vendor_targets', true);
        $this->forge->dropTable('tb_vendor_accounts', true);
    }
}
