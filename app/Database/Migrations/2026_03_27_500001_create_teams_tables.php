<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTeamsTables extends Migration
{
    public function up(): void
    {
        // ── Teams ──────────────────────────────────────────────────────────
        if (!$this->db->tableExists('tb_teams')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'slug'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'description' => ['type' => 'TEXT', 'null' => true],
                'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('tb_teams');
        }

        // ── Team members ───────────────────────────────────────────────────
        if (!$this->db->tableExists('tb_team_members')) {
            $this->forge->addField([
                'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'team_id'   => ['type' => 'INT', 'unsigned' => true],
                'user_id'   => ['type' => 'INT', 'unsigned' => true],
                'joined_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('team_id');
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey(['team_id', 'user_id']);
            $this->forge->createTable('tb_team_members');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_team_members', true);
        $this->forge->dropTable('tb_teams', true);
    }
}
