<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'tb_users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'username', 'email', 'password_hash', 'nickname', 'job_title',
        'avatar', 'role', 'status', 'last_activity', 'last_login_at', 'last_login_ip',
    ];

    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[80]|is_unique[tb_users.username,id,{id}]',
        'email'    => 'required|valid_email|max_length[160]|is_unique[tb_users.email,id,{id}]',
        'role'     => 'in_list[super_admin,admin,manager,member]',
        'status'   => 'in_list[active,inactive,suspended]',
    ];

    protected $validationMessages = [
        'username' => ['is_unique' => 'Username sudah digunakan.'],
        'email'    => ['is_unique' => 'Email sudah digunakan.', 'valid_email' => 'Format email tidak valid.'],
    ];

    // ── Role labels & permissions ──────────────────────────────────────────

    public static array $roleLabels = [
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'manager'     => 'Manager',
        'member'      => 'Member',
    ];

    public static array $rolePermissions = [
        'super_admin' => ['*'],
        'admin'       => ['users.view', 'users.create', 'users.edit', 'teams.manage', 'tasks.manage', 'fields.manage'],
        'manager'     => ['users.view', 'teams.view', 'tasks.manage'],
        'member'      => ['tasks.view', 'tasks.create'],
    ];

    // ── Finders ───────────────────────────────────────────────────────────

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    public function findByEmailOrUsername(string $identifier): ?array
    {
        return $this->groupStart()
                    ->where('email', $identifier)
                    ->orWhere('username', $identifier)
                    ->groupEnd()
                    ->first();
    }

    public function getActiveUsers(): array
    {
        return $this->where('status', 'active')->orderBy('nickname', 'ASC')->findAll();
    }

    public function getAllWithTeams(): array
    {
        $users = $this->orderBy('created_at', 'DESC')->findAll();

        $db          = \Config\Database::connect();
        $memberships = $db->table('tb_team_members tm')
            ->select('tm.user_id, t.name AS team_name, t.id AS team_id')
            ->join('tb_teams t', 't.id = tm.team_id')
            ->get()->getResultArray();

        $teamMap = [];
        foreach ($memberships as $m) {
            $teamMap[$m['user_id']][] = ['id' => $m['team_id'], 'name' => $m['team_name']];
        }

        foreach ($users as &$user) {
            $user['teams'] = $teamMap[$user['id']] ?? [];
        }
        unset($user);

        return $users;
    }

    public function getLoginAttempts(string $identifier, int $minutes = 15): int
    {
        $db   = \Config\Database::connect();
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        return (int) $db->table('tb_auth_login_attempts')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('created_at >', $since)
            ->countAllResults();
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function can(array $user, string $permission): bool
    {
        $role  = $user['role'] ?? 'member';
        $perms = self::$rolePermissions[$role] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public function touchActivity(int $userId): void
    {
        $this->db->table('tb_users')->where('id', $userId)->update([
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
    }

    public function logActivity(int $userId, string $action, string $description = '', string $entityType = '', ?int $entityId = null): void
    {
        $this->db->table('tb_user_activity')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => service('request')->getIPAddress(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function getActivity(int $userId, int $limit = 30): array
    {
        return $this->db->table('tb_user_activity')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    // ── Avatar ────────────────────────────────────────────────────────────

    public static function avatarUrl(?string $avatar, string $name = 'U'): string
    {
        if ($avatar && file_exists(FCPATH . 'uploads/avatars/' . $avatar)) {
            return '/uploads/avatars/' . $avatar;
        }
        $initial = strtoupper(mb_substr($name, 0, 1));
        return 'https://ui-avatars.com/api/?name=' . urlencode($initial) . '&background=4f46e5&color=fff&size=80&bold=true';
    }
}
