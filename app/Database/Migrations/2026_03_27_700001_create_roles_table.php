<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolesTable extends Migration
{
    public const ALL_PERMISSIONS = [
        'view_tasks', 'manage_tasks',
        'view_submissions',
        'view_users', 'manage_users',
        'view_teams', 'manage_teams',
        'view_roles', 'manage_roles',
        'manage_fields',
        'view_vendor_accounts', 'manage_vendor_accounts', 'manage_vendor_allocation',
        'view_project_monitoring',
    ];

    public function up(): void
    {
        if (!$this->db->tableExists('tb_roles')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'slug'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'description' => ['type' => 'TEXT', 'null' => true],
                'permissions' => ['type' => 'JSON', 'null' => true],
                'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'color'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#6b7280'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('tb_roles');

            $now = date('Y-m-d H:i:s');
            $all = self::ALL_PERMISSIONS;

            // Super Admin — everything
            $this->db->table('tb_roles')->insert([
                'name'        => 'Super Admin',
                'slug'        => 'super_admin',
                'description' => 'Akses penuh ke semua fitur dan pengaturan.',
                'permissions' => json_encode($all),
                'is_system'   => 1,
                'color'       => '#7e22ce',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // Admin — all except manage_roles
            $adminPerms = array_values(array_diff($all, ['manage_roles']));
            $this->db->table('tb_roles')->insert([
                'name'        => 'Admin',
                'slug'        => 'admin',
                'description' => 'Kelola task, user, tim, dan field. Tidak dapat mengubah konfigurasi role.',
                'permissions' => json_encode($adminPerms),
                'is_system'   => 1,
                'color'       => '#4f46e5',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // Manager — task ops + view users/teams
            $managerPerms = ['view_tasks', 'manage_tasks', 'view_submissions', 'view_users', 'view_teams'];
            $this->db->table('tb_roles')->insert([
                'name'        => 'Manager',
                'slug'        => 'manager',
                'description' => 'Kelola task dan lihat daftar user serta tim.',
                'permissions' => json_encode($managerPerms),
                'is_system'   => 1,
                'color'       => '#d97706',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // Member (Designer) — own tasks + submissions
            $memberPerms = ['view_tasks', 'manage_tasks', 'view_submissions'];
            $this->db->table('tb_roles')->insert([
                'name'        => 'Member',
                'slug'        => 'member',
                'description' => 'Kelola task sendiri dan lihat daftar setor.',
                'permissions' => json_encode($memberPerms),
                'is_system'   => 1,
                'color'       => '#6b7280',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_roles', true);
    }
}
