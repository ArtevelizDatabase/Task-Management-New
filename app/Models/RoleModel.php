<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table        = 'tb_roles';
    protected $primaryKey   = 'id';
    protected $returnType   = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['name', 'slug', 'description', 'permissions', 'is_system', 'color'];

    public const PERMISSIONS = [
        'tasks' => [
            'label' => 'Task Management',
            'items' => [
                'view_tasks'   => 'Lihat daftar task',
                'manage_tasks' => 'Tambah, edit, hapus task',
            ],
        ],
        'submissions' => [
            'label' => 'Daftar Setor',
            'items' => [
                'view_submissions' => 'Lihat daftar setor',
            ],
        ],
        'users' => [
            'label' => 'User Management',
            'items' => [
                'view_users'   => 'Lihat daftar user',
                'manage_users' => 'Tambah, edit, hapus user',
            ],
        ],
        'teams' => [
            'label' => 'Team Management',
            'items' => [
                'view_teams'   => 'Lihat daftar tim',
                'manage_teams' => 'Tambah, edit, hapus tim',
            ],
        ],
        'roles' => [
            'label' => 'Role Management',
            'items' => [
                'view_roles'   => 'Lihat konfigurasi role',
                'manage_roles' => 'Tambah, edit, hapus role',
            ],
        ],
        'fields' => [
            'label' => 'Field Manager',
            'items' => [
                'manage_fields' => 'Kelola field task (tambah, edit, hapus, reorder)',
            ],
        ],
        'vendors' => [
            'label' => 'Vendor Accounts',
            'items' => [
                'view_vendor_accounts'    => 'Lihat daftar akun vendor',
                'manage_vendor_accounts'  => 'Kelola akun vendor',
                'manage_vendor_allocation'=> 'Atur alokasi vendor dan assignment default',
            ],
        ],
        'monitoring' => [
            'label' => 'Project Monitoring',
            'items' => [
                'view_project_monitoring' => 'Lihat monitoring target dan progres',
            ],
        ],
    ];

    // ── Finders ───────────────────────────────────────────────────────────

    public function findBySlug(string $slug): ?array
    {
        $role = $this->where('slug', $slug)->first();
        if ($role) {
            $role['permissions'] = $this->decodePermissions($role['permissions']);
        }
        return $role;
    }

    public function getAllWithUserCount(): array
    {
        $roles = $this->orderBy('id', 'ASC')->findAll();
        $db    = \Config\Database::connect();

        foreach ($roles as &$role) {
            $role['permissions']  = $this->decodePermissions($role['permissions']);
            $role['user_count']   = $db->table('tb_users')
                ->where('role', $role['slug'])
                ->countAllResults();
        }
        unset($role);

        return $roles;
    }

    public function getPermissionsForRole(string $slug): array
    {
        $role = $this->findBySlug($slug);
        return $role['permissions'] ?? [];
    }

    public function userHasPermission(array $user, string $permission): bool
    {
        $perms = $this->getPermissionsForRole($user['role']);
        // super_admin gets everything always
        if ($user['role'] === 'super_admin') {
            return true;
        }
        return in_array($permission, $perms, true);
    }

    // ── Mutations ─────────────────────────────────────────────────────────

    public function generateSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
        $slug = trim($slug, '_');
        $base = $slug;
        $i    = 1;
        while ($this->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '_' . $i++;
        }
        return $slug;
    }

    public function updatePermissions(int $id, array $permissions): void
    {
        $this->update($id, ['permissions' => json_encode($permissions)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function decodePermissions(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach (self::PERMISSIONS as $group) {
            foreach ($group['items'] as $key => $label) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
}
