<?php

namespace App\Controllers\Team;

use App\Controllers\BaseController;
use App\Models\RoleModel;
use App\Models\UserModel;

class Roles extends BaseController
{
    protected RoleModel $roleModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->roleModel = new RoleModel();
        $this->userModel = new UserModel();
        helper('url');
    }

    // ── Index ─────────────────────────────────────────────────────────────

    public function index(): string
    {
        $this->_requireRole(['super_admin', 'admin']);

        $d = [
            'title'       => 'Role Configuration',
            'roles'       => $this->roleModel->getAllWithUserCount(),
            'permissions' => RoleModel::PERMISSIONS,
            'allKeys'     => RoleModel::allPermissionKeys(),
            'currentRole' => session()->get('user_role'),
        ];
        return view('layouts/main', array_merge($d, ['content' => view('team/roles/index', $d)]));
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function store(): mixed
    {
        $this->_requireRole(['super_admin']);

        if (!$this->validate(['name' => 'required|min_length[2]|max_length[100]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name  = trim($this->request->getPost('name'));
        $slug  = $this->roleModel->generateSlug($name);
        $perms = $this->request->getPost('permissions') ?? [];
        $color = $this->request->getPost('color') ?? '#6b7280';

        // Sanitise: only accept known permission keys
        $validKeys = RoleModel::allPermissionKeys();
        $perms     = array_values(array_filter($perms, fn($p) => in_array($p, $validKeys, true)));

        $this->roleModel->insert([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->request->getPost('description') ?: null,
            'permissions' => json_encode($perms),
            'is_system'   => 0,
            'color'       => $color,
        ]);

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'create_role',
            "Membuat role baru: {$name}"
        );

        return redirect()->to('/team/roles')->with('success', "Role \"{$name}\" berhasil dibuat.");
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(int $id): mixed
    {
        $this->_requireRole(['super_admin']);

        $role = $this->roleModel->find($id);
        if (!$role) {
            return redirect()->to('/team/roles')->with('error', 'Role tidak ditemukan.');
        }

        // Block editing system roles' slugs/names entirely (only permissions)
        $name  = $role['is_system'] ? $role['name'] : (trim($this->request->getPost('name')) ?: $role['name']);
        $perms = $this->request->getPost('permissions') ?? [];
        $color = $this->request->getPost('color') ?? $role['color'];

        $validKeys = RoleModel::allPermissionKeys();
        $perms     = array_values(array_filter($perms, fn($p) => in_array($p, $validKeys, true)));

        // super_admin always keeps all permissions
        if ($role['slug'] === 'super_admin') {
            $perms = RoleModel::allPermissionKeys();
        }

        $updateData = [
            'permissions' => json_encode($perms),
            'color'       => $color,
        ];
        if (!$role['is_system']) {
            $updateData['name']        = $name;
            $updateData['description'] = $this->request->getPost('description') ?: null;
        }

        $this->roleModel->update($id, $updateData);

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'update_role',
            "Memperbarui role: {$role['name']}"
        );

        return redirect()->to('/team/roles')->with('success', "Role \"{$role['name']}\" berhasil diperbarui.");
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(int $id): mixed
    {
        $this->_requireRole(['super_admin']);

        $role = $this->roleModel->find($id);
        if (!$role) {
            return redirect()->to('/team/roles')->with('error', 'Role tidak ditemukan.');
        }

        if ($role['is_system']) {
            return redirect()->back()->with('error', 'Role sistem tidak dapat dihapus.');
        }

        // Check if any users have this role
        $userCount = $this->userModel->where('role', $role['slug'])->countAllResults();
        if ($userCount > 0) {
            return redirect()->back()->with('error', "Tidak dapat menghapus role karena masih ada {$userCount} user dengan role ini.");
        }

        $this->roleModel->delete($id);

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'delete_role',
            "Menghapus role: {$role['name']}"
        );

        return redirect()->to('/team/roles')->with('success', "Role \"{$role['name']}\" berhasil dihapus.");
    }

    // ── AJAX: get role details ─────────────────────────────────────────────

    public function show(int $id): mixed
    {
        $role = $this->roleModel->find($id);
        if (!$role) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }
        $role['permissions'] = $this->roleModel->decodePermissions($role['permissions']);
        return $this->response->setJSON($role);
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function _requireRole(array $roles): void
    {
        $currentRole = session()->get('user_role');
        if (!in_array($currentRole, $roles, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }
}
