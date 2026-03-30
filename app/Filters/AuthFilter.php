<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    private const PERMS_CACHE_TTL   = 300;
    private const ACTIVITY_TOUCH_TTL = 300;

    public function before(RequestInterface $request, $arguments = null): mixed
    {
        helper(['cookie', 'url']);
        $session = session();

        // ── Remember Me auto-login ─────────────────────────────────────────
        if (!$session->has('user_id')) {
            $cookie = get_cookie('remember_me');
            if ($cookie && str_contains($cookie, ':')) {
                [$selector, $validator] = explode(':', $cookie, 2);
                $db    = \Config\Database::connect();
                $token = $db->table('tb_auth_remember_tokens')
                    ->where('selector', $selector)
                    ->where('expires_at >', date('Y-m-d H:i:s'))
                    ->get()->getRowArray();

                if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
                    $user = $db->table('tb_users')
                        ->where('id', $token['user_id'])
                        ->where('status', 'active')
                        ->get()->getRowArray();

                    if ($user) {
                        // Auto-login: rotate token
                        $db->table('tb_auth_remember_tokens')->where('id', $token['id'])->delete();

                        $newSelector  = bin2hex(random_bytes(12));
                        $newValidator = bin2hex(random_bytes(24));
                        $expires      = date('Y-m-d H:i:s', strtotime('+30 days'));

                        $db->table('tb_auth_remember_tokens')->insert([
                            'user_id'          => $user['id'],
                            'selector'         => $newSelector,
                            'hashed_validator' => hash('sha256', $newValidator),
                            'expires_at'       => $expires,
                            'created_at'       => date('Y-m-d H:i:s'),
                        ]);

                        set_cookie([
                            'name'     => 'remember_me',
                            'value'    => $newSelector . ':' . $newValidator,
                            'expire'   => 2592000,
                            'httponly' => true,
                            'secure'   => ENVIRONMENT === 'production',
                            'samesite' => ENVIRONMENT === 'production' ? 'Strict' : 'Lax',
                        ]);

                        $session->set([
                            'user_id'   => $user['id'],
                            'user_role' => $user['role'],
                            'user_name' => $user['nickname'] ?? $user['username'],
                        ]);

                        // Update last login
                        $db->table('tb_users')->where('id', $user['id'])->update([
                            'last_login_at' => date('Y-m-d H:i:s'),
                            'last_activity' => date('Y-m-d H:i:s'),
                        ]);
                        $session->set('last_activity_db_touch', time());
                        $session->remove(['user_perms_cached_at', 'user_perms_cached_for_role']);

                        return null; // proceed
                    }
                }
                // Invalid cookie — clear it
                delete_cookie('remember_me');
            }
        }

        if (!$session->has('user_id')) {
            return redirect()->to('/auth/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        // ── Permissions: cache in session (TTL) to avoid tb_roles read every request ──
        $userRole = (string) $session->get('user_role');
        if ($userRole !== '') {
            $cachedAt = (int) ($session->get('user_perms_cached_at') ?? 0);
            $cachedRole = (string) ($session->get('user_perms_cached_for_role') ?? '');
            $fresh = (time() - $cachedAt < self::PERMS_CACHE_TTL)
                && $cachedRole === $userRole
                && $session->has('user_perms');

            if (! $fresh) {
                if ($userRole === 'super_admin') {
                    $session->set('user_perms', \App\Models\RoleModel::allPermissionKeys());
                } else {
                    $db = \Config\Database::connect();
                    $roleRow = $db->table('tb_roles')->where('slug', $userRole)->get()->getRowArray();
                    $session->set('user_perms', $roleRow ? (json_decode($roleRow['permissions'] ?? '[]', true) ?: []) : []);
                }
                $session->set('user_perms_cached_at', time());
                $session->set('user_perms_cached_for_role', $userRole);
            }
        }

        // ── Check required role if specified ──────────────────────────────
        if (!empty($arguments)) {
            if (!in_array($userRole, $arguments, true) && $userRole !== 'super_admin') {
                return redirect()->back()->with('error', 'Anda tidak memiliki akses ke halaman ini.');
            }
        }

        // ── Touch last_activity (throttled; avoids write per AJAX) ───────
        $userId = (int) $session->get('user_id');
        if ($userId > 0) {
            $lastTouch = (int) ($session->get('last_activity_db_touch') ?? 0);
            if (time() - $lastTouch >= self::ACTIVITY_TOUCH_TTL) {
                $db = \Config\Database::connect();
                $db->table('tb_users')->where('id', $userId)->update([
                    'last_activity' => date('Y-m-d H:i:s'),
                ]);
                $session->set('last_activity_db_touch', time());
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
