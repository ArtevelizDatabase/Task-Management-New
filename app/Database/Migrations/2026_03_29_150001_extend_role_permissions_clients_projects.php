<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendRolePermissionsClientsProjects extends Migration
{
    private const PERMS_ADMIN = [
        'view_clients',
        'manage_clients',
        'view_projects',
        'manage_projects',
    ];

    private const PERMS_MANAGER_VIEW = [
        'view_clients',
        'view_projects',
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('tb_roles')) {
            return;
        }

        $db    = \Config\Database::connect();
        $roles = $db->table('tb_roles')->get()->getResultArray();

        foreach ($roles as $role) {
            $slug  = (string) ($role['slug'] ?? '');
            $perms = json_decode($role['permissions'] ?? '[]', true);
            $perms = is_array($perms) ? $perms : [];

            if (in_array($slug, ['super_admin', 'admin'], true)) {
                $perms = array_values(array_unique(array_merge($perms, self::PERMS_ADMIN)));
            } elseif ($slug === 'manager') {
                $perms = array_values(array_unique(array_merge($perms, self::PERMS_MANAGER_VIEW)));
            }

            $db->table('tb_roles')
                ->where('id', $role['id'])
                ->update(['permissions' => json_encode($perms), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('tb_roles')) {
            return;
        }

        $remove = array_merge(self::PERMS_ADMIN, self::PERMS_MANAGER_VIEW);
        $remove = array_values(array_unique($remove));

        $db    = \Config\Database::connect();
        $roles = $db->table('tb_roles')->get()->getResultArray();

        foreach ($roles as $role) {
            $perms = json_decode($role['permissions'] ?? '[]', true);
            $perms = is_array($perms) ? $perms : [];
            $perms = array_values(array_filter($perms, static fn(string $p): bool => ! in_array($p, $remove, true)));

            $db->table('tb_roles')
                ->where('id', $role['id'])
                ->update(['permissions' => json_encode($perms), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
