<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendRolePermissionsForVendorFeatures extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_roles')) {
            return;
        }

        $db = \Config\Database::connect();
        $roles = $db->table('tb_roles')->get()->getResultArray();
        $extra = [
            'view_vendor_accounts',
            'manage_vendor_accounts',
            'manage_vendor_allocation',
            'view_project_monitoring',
        ];

        foreach ($roles as $role) {
            $slug = (string) ($role['slug'] ?? '');
            $perms = json_decode($role['permissions'] ?? '[]', true);
            $perms = is_array($perms) ? $perms : [];

            if (in_array($slug, ['super_admin', 'admin'], true)) {
                $perms = array_values(array_unique(array_merge($perms, $extra)));
            } elseif ($slug === 'manager') {
                $perms = array_values(array_unique(array_merge($perms, [
                    'view_vendor_accounts',
                    'view_project_monitoring',
                ])));
            }

            $db->table('tb_roles')
                ->where('id', $role['id'])
                ->update(['permissions' => json_encode($perms), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('tb_roles')) {
            return;
        }

        $db = \Config\Database::connect();
        $roles = $db->table('tb_roles')->get()->getResultArray();
        $remove = [
            'view_vendor_accounts',
            'manage_vendor_accounts',
            'manage_vendor_allocation',
            'view_project_monitoring',
        ];

        foreach ($roles as $role) {
            $perms = json_decode($role['permissions'] ?? '[]', true);
            $perms = is_array($perms) ? $perms : [];
            $perms = array_values(array_filter($perms, static fn(string $p): bool => !in_array($p, $remove, true)));

            $db->table('tb_roles')
                ->where('id', $role['id'])
                ->update(['permissions' => json_encode($perms), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
