<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersAuthTables extends Migration
{
    public function up(): void
    {
        // ── Users ──────────────────────────────────────────────────────────
        if (!$this->db->tableExists('tb_users')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'username'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'email'          => ['type' => 'VARCHAR', 'constraint' => 160],
                'password_hash'  => ['type' => 'VARCHAR', 'constraint' => 255],
                'nickname'       => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'job_title'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'avatar'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'role'           => ['type' => 'ENUM', 'constraint' => ['super_admin', 'admin', 'manager', 'member'], 'default' => 'member'],
                'status'         => ['type' => 'ENUM', 'constraint' => ['active', 'inactive', 'suspended'], 'default' => 'active'],
                'last_activity'  => ['type' => 'DATETIME', 'null' => true],
                'last_login_at'  => ['type' => 'DATETIME', 'null' => true],
                'last_login_ip'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('username');
            $this->forge->addUniqueKey('email');
            $this->forge->createTable('tb_users');

            // Default super admin (password: Admin@123)
            $this->db->table('tb_users')->insert([
                'username'      => 'superadmin',
                'email'         => 'superadmin@taskflow.local',
                'password_hash' => password_hash('Admin@123', PASSWORD_BCRYPT),
                'nickname'      => 'Super Admin',
                'job_title'     => 'System Administrator',
                'role'          => 'super_admin',
                'status'        => 'active',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        // ── Remember Me tokens ─────────────────────────────────────────────
        if (!$this->db->tableExists('tb_auth_remember_tokens')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'          => ['type' => 'INT', 'unsigned' => true],
                'selector'         => ['type' => 'VARCHAR', 'constraint' => 24],
                'hashed_validator' => ['type' => 'VARCHAR', 'constraint' => 255],
                'expires_at'       => ['type' => 'DATETIME'],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('selector');
            $this->forge->addKey('user_id');
            $this->forge->createTable('tb_auth_remember_tokens');
        }

        // ── Login attempts ─────────────────────────────────────────────────
        if (!$this->db->tableExists('tb_auth_login_attempts')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'identifier' => ['type' => 'VARCHAR', 'constraint' => 160],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'user_agent' => ['type' => 'TEXT', 'null' => true],
                'success'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('identifier');
            $this->forge->addKey('created_at');
            $this->forge->createTable('tb_auth_login_attempts');
        }

        // ── Impersonation logs ─────────────────────────────────────────────
        if (!$this->db->tableExists('tb_impersonation_logs')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'super_admin_id' => ['type' => 'INT', 'unsigned' => true],
                'target_user_id' => ['type' => 'INT', 'unsigned' => true],
                'action'         => ['type' => 'ENUM', 'constraint' => ['start', 'stop'], 'default' => 'start'],
                'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'user_agent'     => ['type' => 'TEXT', 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('super_admin_id');
            $this->forge->addKey('target_user_id');
            $this->forge->createTable('tb_impersonation_logs');
        }

        // ── User activity log ──────────────────────────────────────────────
        if (!$this->db->tableExists('tb_user_activity')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
                'action'      => ['type' => 'VARCHAR', 'constraint' => 80],
                'description' => ['type' => 'TEXT', 'null' => true],
                'entity_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'entity_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('user_id');
            $this->forge->addKey('created_at');
            $this->forge->createTable('tb_user_activity');
        }
    }

    public function down(): void
    {
        foreach ([
            'tb_user_activity',
            'tb_impersonation_logs',
            'tb_auth_login_attempts',
            'tb_auth_remember_tokens',
            'tb_users',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
