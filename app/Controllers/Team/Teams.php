<?php

namespace App\Controllers\Team;

use App\Controllers\BaseController;
use App\Models\TeamModel;
use App\Models\UserModel;

class Teams extends BaseController
{
    protected TeamModel $teamModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->teamModel = new TeamModel();
        $this->userModel = new UserModel();
        helper('url');
    }

    public function index(): string
    {
        $this->_requireRole(['super_admin', 'admin', 'manager']);

        $d = [
            'title'      => 'Team Management',
            'teams'      => $this->teamModel->getAllWithMembers(),
            'users'      => $this->userModel->getActiveUsers(),
            'roleLabels' => UserModel::$roleLabels,
        ];
        return view('layouts/main', array_merge($d, ['content' => view('team/teams/index', $d)]));
    }

    public function store(): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        if (!$this->validate(['name' => 'required|min_length[2]|max_length[120]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = trim($this->request->getPost('name'));
        $slug = $this->teamModel->generateSlug($name);

        $teamId = $this->teamModel->insert([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->request->getPost('description') ?: null,
            'created_by'  => session()->get('user_id'),
        ]);

        // Add initial members
        $memberIds = $this->request->getPost('member_ids') ?? [];
        foreach ($memberIds as $uid) {
            $this->teamModel->addMember((int)$teamId, (int)$uid);
        }

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'create_team',
            "Membuat tim baru: {$name}",
            'team',
            (int) $teamId
        );

        return redirect()->to('/team/teams')->with('success', "Tim \"{$name}\" berhasil dibuat.");
    }

    public function update(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $team = $this->teamModel->find($id);
        if (!$team) {
            return redirect()->to('/team/teams')->with('error', 'Tim tidak ditemukan.');
        }

        if (!$this->validate(['name' => 'required|min_length[2]|max_length[120]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = trim($this->request->getPost('name'));
        $this->teamModel->update($id, [
            'name'        => $name,
            'description' => $this->request->getPost('description') ?: null,
        ]);

        // Sync members
        $db = \Config\Database::connect();
        $db->table('tb_team_members')->where('team_id', $id)->delete();
        $memberIds = $this->request->getPost('member_ids') ?? [];
        foreach ($memberIds as $uid) {
            $this->teamModel->addMember($id, (int)$uid);
        }

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'update_team',
            "Memperbarui tim: {$name}",
            'team',
            $id
        );

        return redirect()->to('/team/teams')->with('success', "Tim \"{$name}\" berhasil diperbarui.");
    }

    public function delete(int $id): mixed
    {
        $this->_requireRole(['super_admin', 'admin']);

        $team = $this->teamModel->find($id);
        if (!$team) {
            return redirect()->to('/team/teams')->with('error', 'Tim tidak ditemukan.');
        }

        $db = \Config\Database::connect();
        $db->table('tb_team_members')->where('team_id', $id)->delete();
        $this->teamModel->delete($id);

        $this->userModel->logActivity(
            (int) session()->get('user_id'),
            'delete_team',
            "Menghapus tim: {$team['name']}",
            'team',
            $id
        );

        return redirect()->to('/team/teams')->with('success', "Tim \"{$team['name']}\" berhasil dihapus.");
    }

    public function addMember(int $teamId): mixed
    {
        $this->_requireRole(['super_admin', 'admin', 'manager']);

        $userId = (int) $this->request->getPost('user_id');
        if ($this->teamModel->addMember($teamId, $userId)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Member berhasil ditambahkan.']);
        }
        return $this->response->setJSON(['success' => false, 'message' => 'Member sudah ada di tim ini.']);
    }

    public function removeMember(int $teamId, int $userId): mixed
    {
        $this->_requireRole(['super_admin', 'admin', 'manager']);

        $this->teamModel->removeMember($teamId, $userId);
        return $this->response->setJSON(['success' => true, 'message' => 'Member berhasil dihapus.']);
    }

    private function _requireRole(array $roles): void
    {
        $currentRole = session()->get('user_role');
        if (!in_array($currentRole, $roles, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }
}
