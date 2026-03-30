<?php

namespace App\Controllers\Team;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\TeamModel;
use App\Models\NotificationModel;

class Users extends BaseController
{
    protected UserModel         $userModel;
    protected TeamModel         $teamModel;
    protected NotificationModel $notifModel;

    public function __construct()
    {
        $this->userModel  = new UserModel();
        $this->teamModel  = new TeamModel();
        $this->notifModel = new NotificationModel();
        helper(['url', 'cookie']);
    }

    // ── Index ─────────────────────────────────────────────────────────────

    public function index(): string
    {
        $this->_requireRole(['super_admin', 'admin']);

        $search = $this->request->getGet('search') ?? '';
        $role   = $this->request->getGet('role') ?? '';
        $status = $this->request->getGet('status') ?? '';

        $users = $this->userModel->getAllWithTeams();

        if ($search) {
            $s     = strtolower($search);
            $users = array_filter($users, fn($u) =>
                str_contains(strtolower($u['username'] ?? ''), $s) ||
                str_contains(strtolower($u['nickname'] ?? ''), $s) ||
                str_contains(strtolower($u['email'] ?? ''), $s) ||
                str_contains(strtolower($u['job_title'] ?? ''), $s)
            );
        }
        if ($role)   $users = array_filter($users, fn($u) => $u['role'] === $role);
        if ($status) $users = array_filter($users, fn($u) => $u['status'] === $status);

        $usersAll = array_values($users);
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $p        = table_paginate($usersAll, $page, 50);
        $users    = $p['items'];

        $statTotal   = count($usersAll);
        $statActive  = count(array_filter($usersAll, fn($u) => $u['status'] === 'active'));
        $statInactive = $statTotal - $statActive;
        $statAdmins  = count(array_filter($usersAll, fn($u) => in_array($u['role'], ['super_admin', 'admin'], true)));

        $loginAttempts = [];
        $db = \Config\Database::connect();
        foreach ($users as $u) {
            $loginAttempts[$u['id']] = $db->table('tb_auth_login_attempts')
                ->where('identifier', $u['email'])
                ->where('success', 0)
                ->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->countAllResults();
        }

        $d = [
            'title'         => 'User Management',
            'users'         => $users,
            'teams'         => $this->teamModel->findAll(),
            'roleLabels'    => UserModel::$roleLabels,
            'loginAttempts' => $loginAttempts,
            'search'        => $search,
            'filterRole'    => $role,
            'filterStatus'  => $status,
            'currentUserId' => (int) session()->get('user_id'),
            'currentRole'   => session()->get('user_role'),
            'statTotal'     => $statTotal,
            'statActive'    => $statActive,
            'statInactive'  => $statInactive,
            'statAdmins'    => $statAdmins,
            'pager'         => [
                'total'      => $p['total'],
                'page'       => $p['page'],
                'perPage'    => $p['perPage'],
                'totalPages' => $p['totalPages'],
            ],
            'pagerQuery'    => table_pagination_query_params($this->request),
            'pagerUriPath'  => table_pagination_uri_path(),
        ];
        return view('layouts/main', array_merge($d, ['content' => view('team/users/index', $d)]));
    }

    /**
     * JSON directory of active users (same source as /team/users, untuk PIC/assignee di UI lain).
     * Cukup sesi login; tidak memerlukan role admin.
     */
    public function directoryJson(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! session()->get('user_id')) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Unauthorized']);
        }

        $users = $this->userModel
            ->where('status', 'active')
            ->orderBy('nickname', 'ASC')
            ->findAll();

        $list = array_map(static function (array $u): array {
            return [
                'id'       => (int) ($u['id'] ?? 0),
                'username' => (string) ($u['username'] ?? ''),
                'nickname' => (string) ($u['nickname'] ?? ''),
                'avatar'   => $u['avatar'] ?? null,
            ];
        }, $users);

        return $this->response->setJSON([
            'ok'    => true,
            'users' => $list,
            'csrf'  => csrf_hash(),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function create(): string
    {
        $this->_requireRole(['super_admin', 'admin']);
        $d = ['title' => 'Tambah User', 'user' => null, 'teams' => $this->teamModel->findAll(), 'roleLabels' => UserModel::$roleLabels];
        return view('layouts/main', array_merge($d, ['content' => view('team/users/form', $d)]));
    }

    public function store(): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $rules = [
            'username'             => 'required|min_length[3]|max_length[80]|is_unique[tb_users.username]',
            'email'                => 'required|valid_email|is_unique[tb_users.email]',
            'password'             => 'required|regex_match[/^(?=.*[A-Za-z])(?=.*\d).{10,}$/]',
            'password_confirm'     => 'required|matches[password]',
            'role'                 => 'required|in_list[super_admin,admin,manager,member]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'username'      => $this->request->getPost('username'),
            'email'         => $this->request->getPost('email'),
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'nickname'      => $this->request->getPost('nickname') ?: null,
            'job_title'     => $this->request->getPost('job_title') ?: null,
            'role'          => $this->request->getPost('role'),
            'status'        => $this->request->getPost('status') ?? 'active',
        ];

        // Avatar upload
        $avatar = $this->request->getFile('avatar');
        if ($avatar && $avatar->isValid() && !$avatar->hasMoved()) {
            $ext      = $avatar->getClientExtension();
            $filename = 'avatar_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
            $avatar->move(FCPATH . 'uploads/avatars', $filename);
            $data['avatar'] = $filename;
        }

        $userId = $this->userModel->insert($data);

        // Assign teams
        $teamIds = $this->request->getPost('team_ids') ?? [];
        foreach ($teamIds as $teamId) {
            $this->teamModel->addMember((int)$teamId, (int)$userId);
        }

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'create_user',
            "Membuat user baru: {$data['username']}",
            'user',
            (int) $userId
        );

        $this->notifModel->send(
            (int) $userId,
            'user',
            'Selamat datang di TaskFlow!',
            'Akun Anda telah dibuat. Mulai kelola tugas Anda sekarang.'
        );

        return redirect()->to('/team/users')->with('success', 'User berhasil ditambahkan.');
    }

    // ── Edit ──────────────────────────────────────────────────────────────

    public function edit(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/team/users')->with('error', 'User tidak ditemukan.');
        }

        $user['teams'] = array_column($this->teamModel->getUserTeams($id), 'id');

        $d = ['title' => 'Edit User', 'user' => $user, 'teams' => $this->teamModel->findAll(), 'roleLabels' => UserModel::$roleLabels];
        return view('layouts/main', array_merge($d, ['content' => view('team/users/form', $d)]));
    }

    public function update(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/team/users')->with('error', 'User tidak ditemukan.');
        }

        $rules = [
            'username' => "required|min_length[3]|max_length[80]|is_unique[tb_users.username,id,{$id}]",
            'email'    => "required|valid_email|is_unique[tb_users.email,id,{$id}]",
            'role'     => 'required|in_list[super_admin,admin,manager,member]',
            'status'   => 'required|in_list[active,inactive,suspended]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Protect: only super_admin can change role of super_admin
        $currentRole = session()->get('user_role');
        $newRole     = $this->request->getPost('role');
        if ($user['role'] === 'super_admin' && $currentRole !== 'super_admin') {
            return redirect()->back()->with('error', 'Hanya Super Admin yang dapat mengubah role Super Admin.');
        }

        $data = [
            'username'  => $this->request->getPost('username'),
            'email'     => $this->request->getPost('email'),
            'nickname'  => $this->request->getPost('nickname') ?: null,
            'job_title' => $this->request->getPost('job_title') ?: null,
            'role'      => $newRole,
            'status'    => $this->request->getPost('status'),
        ];

        // Password change (optional)
        $newPass = $this->request->getPost('password');
        if ($newPass) {
            if (! preg_match('/^(?=.*[A-Za-z])(?=.*\d).{10,}$/', (string) $newPass)) {
                return redirect()->back()->withInput()->with('error', 'Password minimal 10 karakter, wajib huruf dan angka.');
            }
            $data['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT);
        }

        // Avatar upload
        $avatar = $this->request->getFile('avatar');
        if ($avatar && $avatar->isValid() && !$avatar->hasMoved()) {
            // Delete old avatar
            if ($user['avatar'] && file_exists(FCPATH . 'uploads/avatars/' . $user['avatar'])) {
                unlink(FCPATH . 'uploads/avatars/' . $user['avatar']);
            }
            $ext      = $avatar->getClientExtension();
            $filename = 'avatar_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
            $avatar->move(FCPATH . 'uploads/avatars', $filename);
            $data['avatar'] = $filename;
        }

        $this->userModel->update($id, $data);

        // Re-sync team memberships
        $db = \Config\Database::connect();
        $db->table('tb_team_members')->where('user_id', $id)->delete();
        $teamIds = $this->request->getPost('team_ids') ?? [];
        foreach ($teamIds as $teamId) {
            $this->teamModel->addMember((int)$teamId, $id);
        }

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'update_user',
            "Mengubah user: {$data['username']}",
            'user',
            $id
        );

        return redirect()->to('/team/users')->with('success', 'User berhasil diperbarui.');
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(int $id): mixed
    {
        $this->_requireRole(['super_admin']);

        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/team/users')->with('error', 'User tidak ditemukan.');
        }

        if ((int)session()->get('user_id') === $id) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        if ($user['role'] === 'super_admin') {
            $count = $this->userModel->where('role', 'super_admin')->countAllResults();
            if ($count <= 1) {
                return redirect()->back()->with('error', 'Minimal harus ada satu Super Admin.');
            }
        }

        // Delete avatar
        if ($user['avatar'] && file_exists(FCPATH . 'uploads/avatars/' . $user['avatar'])) {
            unlink(FCPATH . 'uploads/avatars/' . $user['avatar']);
        }

        $db = \Config\Database::connect();
        $db->table('tb_team_members')->where('user_id', $id)->delete();

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'delete_user',
            "Menghapus user: {$user['username']}",
            'user',
            $id
        );

        $this->userModel->delete($id);

        return redirect()->to('/team/users')->with('success', 'User berhasil dihapus.');
    }

    // ── Toggle status ─────────────────────────────────────────────────────

    public function toggleStatus(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $user = $this->userModel->find($id);
        if (!$user) {
            return $this->response->setJSON(['success' => false, 'message' => 'User tidak ditemukan.']);
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->userModel->update($id, ['status' => $newStatus]);

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'toggle_user_status',
            "Status user {$user['username']} diubah menjadi {$newStatus}",
            'user',
            $id
        );

        return $this->response->setJSON([
            'success' => true,
            'status'  => $newStatus,
            'message' => "Status user diubah menjadi {$newStatus}.",
        ]);
    }

    // ── Activity log ───────────────────────────────────────────────────────

    public function activity(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/team/users')->with('error', 'User tidak ditemukan.');
        }

        $activity     = $this->userModel->getActivity($id, 50);
        $loginHistory = $this->_getLoginHistory($user['email']);

        $d = [
            'title'        => 'Activity Log — ' . ($user['nickname'] ?? $user['username']),
            'user'         => $user,
            'activity'     => $activity,
            'loginHistory' => $loginHistory,
            'roleLabels'   => UserModel::$roleLabels,
        ];
        return view('layouts/main', array_merge($d, ['content' => view('team/users/activity', $d)]));
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function _requireRole(array $roles): void
    {
        $currentRole = session()->get('user_role');
        if (!in_array($currentRole, $roles, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }

    private function _getLoginHistory(string $email, int $limit = 20): array
    {
        return \Config\Database::connect()
            ->table('tb_auth_login_attempts')
            ->where('identifier', $email)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }
}
