<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppSettings extends Migration
{
    public function up(): void
    {
        $tables = $this->db->listTables();

        if (!in_array('tb_app_settings', $tables)) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'setting_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => false,
                ],
                'setting_value' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('setting_key');
            $this->forge->createTable('tb_app_settings');
        }

        // Seed default feature flags
        $defaults = [
            ['setting_key' => 'feature_progress', 'setting_value' => '1', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'feature_deadline', 'setting_value' => '1', 'updated_at' => date('Y-m-d H:i:s')],
        ];

        foreach ($defaults as $row) {
            $exists = $this->db->table('tb_app_settings')
                ->where('setting_key', $row['setting_key'])
                ->countAllResults();
            if (!$exists) {
                $this->db->table('tb_app_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_app_settings', true);
    }
}
