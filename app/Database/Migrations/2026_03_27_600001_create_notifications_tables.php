<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTables extends Migration
{
    public function up(): void
    {
        // ── Notifications ──────────────────────────────────────────────────
        if (!$this->db->tableExists('tb_notifications')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
                'type'        => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'info'],
                'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
                'message'     => ['type' => 'TEXT', 'null' => true],
                'data'        => ['type' => 'JSON', 'null' => true],
                'is_read'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'read_at'     => ['type' => 'DATETIME', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('user_id');
            $this->forge->addKey(['user_id', 'is_read']);
            $this->forge->createTable('tb_notifications');
        }

        // ── Notification preferences ───────────────────────────────────────
        if (!$this->db->tableExists('tb_notification_preferences')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'           => ['type' => 'INT', 'unsigned' => true],
                'notification_type' => ['type' => 'VARCHAR', 'constraint' => 80],
                'is_enabled'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey(['user_id', 'notification_type']);
            $this->forge->createTable('tb_notification_preferences');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_notification_preferences', true);
        $this->forge->dropTable('tb_notifications', true);
    }
}
