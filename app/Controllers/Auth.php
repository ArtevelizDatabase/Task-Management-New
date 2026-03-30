<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\NotificationModel;
use CodeIgniter\Controller;

class Auth extends Controller
{
    protected UserModel         $userModel;
    protected NotificationModel $notifModel;

    public function __construct()
    {
        $this->userModel  = new UserModel();
        $this->notifModel = new NotificationModel();
        helper(['cookie', 'url']);
    }

    // ── Login page ────────────────────────────────────────────────────────

    public function login(): mixed
    {
        if (session()->has('user_id')) {
            return redirect()->to('/tasks');
        }

        return view('auth/login', ['title' => 'Login']);
    }

    // ── Handle login ──────────────────────────────────────────────────────

    public function doLogin(): mixed
    {
        $rules = [
            'identifier' => 'required|min_length[3]',
            'password'   => 'required',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $identifier = trim($this->request->getPost('identifier'));
        $password   = $this->request->getPost('password');
        $rememberMe = (bool) $this->request->getPost('remember_me');

        // ── Rate limiting: max 5 failed attempts / 15 minutes ────────────
        $attempts = $this->userModel->getLoginAttempts($identifier);
        if ($attempts >= 5) {
            $this->_logAttempt($identifier, false);
            return redirect()->back()->withInput()
                ->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.');
        }

        $user = $this->userModel->findByEmailOrUsername($identifier);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->_logAttempt($identifier, false);
            return redirect()->back()->withInput()
                ->with('error', 'Email/username atau password salah.');
        }

        if ($user['status'] !== 'active') {
            $this->_logAttempt($identifier, false);
            return redirect()->back()->withInput()
                ->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        $this->_logAttempt($identifier, true);

        session()->regenerate(true);

        // ── Create session ────────────────────────────────────────────────
        session()->set([
            'user_id'   => $user['id'],
            'user_role' => $user['role'],
            'user_name' => $user['nickname'] ?? $user['username'],
        ]);
        session()->set('last_activity_db_touch', time());

        // ── Update last login ─────────────────────────────────────────────
        $db = \Config\Database::connect();
        $db->table('tb_users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $this->request->getIPAddress(),
            'last_activity' => date('Y-m-d H:i:s'),
        ]);

        $this->userModel->logActivity((int)$user['id'], 'login', 'User login dari ' . $this->request->getIPAddress());

        // ── Remember Me ───────────────────────────────────────────────────
        if ($rememberMe) {
            $this->_setRememberMeToken((int) $user['id']);
        }

        return redirect()->to('/tasks')->with('success', 'Selamat datang, ' . ($user['nickname'] ?? $user['username']) . '!');
    }

    // ── Logout ────────────────────────────────────────────────────────────

    public function logout(): mixed
    {
        $userId = (int) session()->get('user_id');
        if ($userId) {
            $this->userModel->logActivity($userId, 'logout', 'User logout');
            $this->_deleteRememberMeTokens($userId);
        }

        delete_cookie('remember_me');
        session()->destroy();

        return redirect()->to('/auth/login')->with('success', 'Anda berhasil logout.');
    }

    // ── Impersonation (Super Admin only) ──────────────────────────────────

    public function impersonate(int $userId): mixed
    {
        $currentUser = $this->_requireSuperAdmin();
        if ($currentUser instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $currentUser;
        }

        $target = $this->userModel->find($userId);
        if (!$target || $target['status'] !== 'active') {
            return redirect()->back()->with('error', 'User tidak ditemukan atau tidak aktif.');
        }

        if ($target['id'] === $currentUser['id']) {
            return redirect()->back()->with('error', 'Tidak dapat impersonate diri sendiri.');
        }

        session()->regenerate(true);

        // Save original admin in session
        session()->set([
            'original_admin_id'   => $currentUser['id'],
            'original_admin_name' => $currentUser['nickname'] ?? $currentUser['username'],
            'original_admin_role' => $currentUser['role'],
            'is_impersonating'    => true,
            'user_id'             => $target['id'],
            'user_role'           => $target['role'],
            'user_name'           => $target['nickname'] ?? $target['username'],
        ]);

        $this->_logImpersonation((int)$currentUser['id'], $userId, 'start');

        return redirect()->to('/tasks')
            ->with('success', 'Kini Anda melihat sebagai ' . ($target['nickname'] ?? $target['username']) . '.');
    }

    public function stopImpersonation(): mixed
    {
        if (!session()->get('is_impersonating')) {
            return redirect()->to('/tasks');
        }

        $originalId   = (int) session()->get('original_admin_id');
        $targetUserId = (int) session()->get('user_id');
        $originalName = session()->get('original_admin_name');
        $originalRole = session()->get('original_admin_role');

        $this->_logImpersonation($originalId, $targetUserId, 'stop');

        // Restore original session
        session()->remove(['original_admin_id', 'original_admin_name', 'original_admin_role', 'is_impersonating']);
        session()->regenerate(true);
        session()->set([
            'user_id'   => $originalId,
            'user_role' => $originalRole,
            'user_name' => $originalName,
        ]);

        return redirect()->to('/team/users')
            ->with('success', 'Impersonation dihentikan. Kembali sebagai ' . $originalName . '.');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function _requireSuperAdmin(): mixed
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return redirect()->to('/auth/login');
        }
        $user = $this->userModel->find($userId);
        if (!$user || $user['role'] !== 'super_admin') {
            return redirect()->back()->with('error', 'Hanya Super Admin yang dapat melakukan impersonation.');
        }
        return $user;
    }

    private function _setRememberMeToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(24));
        $expires   = date('Y-m-d H:i:s', strtotime('+30 days'));

        $db = \Config\Database::connect();
        $db->table('tb_auth_remember_tokens')->insert([
            'user_id'          => $userId,
            'selector'         => $selector,
            'hashed_validator' => hash('sha256', $validator),
            'expires_at'       => $expires,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        set_cookie([
            'name'     => 'remember_me',
            'value'    => $selector . ':' . $validator,
            'expire'   => 2592000,
            'httponly' => true,
            'secure'   => ENVIRONMENT === 'production',
            'samesite' => ENVIRONMENT === 'production' ? 'Strict' : 'Lax',
        ]);
    }

    private function _deleteRememberMeTokens(int $userId): void
    {
        $db = \Config\Database::connect();
        $db->table('tb_auth_remember_tokens')->where('user_id', $userId)->delete();
    }

    private function _logAttempt(string $identifier, bool $success): void
    {
        $db = \Config\Database::connect();
        $db->table('tb_auth_login_attempts')->insert([
            'identifier' => $identifier,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
            'success'    => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function _logImpersonation(int $adminId, int $targetId, string $action): void
    {
        $db = \Config\Database::connect();
        $db->table('tb_impersonation_logs')->insert([
            'super_admin_id' => $adminId,
            'target_user_id' => $targetId,
            'action'         => $action,
            'ip_address'     => $this->request->getIPAddress(),
            'user_agent'     => $this->request->getUserAgent()->getAgentString(),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
