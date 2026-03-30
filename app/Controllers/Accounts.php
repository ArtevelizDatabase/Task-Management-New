<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\AssignmentRuleModel;
use App\Models\TeamModel;
use App\Models\UserModel;
use App\Models\VendorAllocationModel;
use App\Models\VendorTargetModel;
use CodeIgniter\HTTP\RedirectResponse;

class Accounts extends BaseController
{
    protected AccountModel $accountModel;
    protected VendorTargetModel $vendorTargetModel;
    protected VendorAllocationModel $vendorAllocationModel;
    protected AssignmentRuleModel $assignmentRuleModel;
    protected UserModel $userModel;
    protected TeamModel $teamModel;

    public function __construct()
    {
        $this->accountModel = new AccountModel();
        $this->vendorTargetModel = new VendorTargetModel();
        $this->vendorAllocationModel = new VendorAllocationModel();
        $this->assignmentRuleModel = new AssignmentRuleModel();
        $this->userModel = new UserModel();
        $this->teamModel = new TeamModel();
    }

    public function index(): string|RedirectResponse
    {
        $this->requirePerm('view_vendor_accounts');
        $db = \Config\Database::connect();
        if (!$db->tableExists('tb_accounts')) {
            return redirect()->to('/tasks')->with('error', 'Master Accounts belum aktif. Jalankan migrasi database terlebih dahulu.');
        }

        $type = (string) ($this->request->getGet('type') ?? '');
        $q    = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $this->accountModel->orderBy('type', 'ASC')->orderBy('name', 'ASC');
        if (in_array($type, ['office', 'vendor'], true)) {
            $builder->where('type', $type);
        }
        if ($q !== '') {
            $builder->like('name', $q);
        }
        $accounts = $builder->findAll();

        $users = [];
        $teams = [];
        $targetByAccount = [];
        $allocByAccount = [];
        $primaryByAccount = [];
        $ruleByAccount = [];

        // When viewing vendor accounts, also load vendor-management maps
        $isVendorView = ($type === 'vendor');
        if ($isVendorView) {
            $users = $this->userModel->getActiveUsers();
            $teams = $this->teamModel->orderBy('name', 'ASC')->findAll();

            $targets = $this->vendorTargetModel->findAll();
            foreach ($targets as $row) {
                $k = (int) ($row['account_id'] ?? 0);
                if ($k <= 0) continue;
                $targetByAccount[$k][] = $row;
            }

            $allocRows = $this->vendorAllocationModel->findAll();
            foreach ($allocRows as $row) {
                $k = (int) ($row['account_id'] ?? 0);
                if ($k <= 0) continue;
                $allocByAccount[$k][] = (int) $row['user_id'];
                if ((int) ($row['is_primary'] ?? 0) === 1) {
                    $primaryByAccount[$k] = (int) $row['user_id'];
                }
            }

            $rules = $this->assignmentRuleModel->findAll();
            foreach ($rules as $row) {
                $k = (int) ($row['account_id'] ?? 0);
                if ($k <= 0) continue;
                $ruleByAccount[$k] = $row;
            }
        }

        $d = [
            'title'    => 'Accounts',
            'accounts' => $accounts,
            'filters'  => ['type' => $type, 'q' => $q],
            'users' => $users,
            'teams' => $teams,
            'targetByAccount' => $targetByAccount,
            'allocByAccount' => $allocByAccount,
            'primaryByAccount' => $primaryByAccount,
            'ruleByAccount' => $ruleByAccount,
        ];

        return view('layouts/main', array_merge($d, ['content' => view('accounts/index', $d)]));
    }

    public function store(): RedirectResponse
    {
        $this->requirePerm('manage_vendor_accounts');

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama account wajib diisi.');
        }

        $type = (string) $this->request->getPost('type');
        if (!in_array($type, ['office', 'vendor'], true)) {
            $type = 'vendor';
        }

        $status = (string) $this->request->getPost('status');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $payload = [
            'name'       => $name,
            'type'       => $type,
            'status'     => $status,
            'platform'   => trim((string) $this->request->getPost('platform')) ?: null,
            'owner_name' => trim((string) $this->request->getPost('owner_name')) ?: null,
            'notes'      => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => (int) (session()->get('user_id') ?? 0),
        ];

        if ($type === 'vendor') {
            $contractMode = (string) $this->request->getPost('contract_mode');
            if (!in_array($contractMode, ['monthly', 'lifetime', 'on_demand'], true)) {
                $contractMode = 'monthly';
            }
            $payload['contract_mode'] = $contractMode;
            $payload['next_review_date'] = ($this->request->getPost('next_review_date') ?: null);
            $payload['contract_end_date'] = ($this->request->getPost('contract_end_date') ?: null);
            if ($contractMode === 'lifetime') {
                $payload['contract_end_date'] = null;
            }
        }

        $this->accountModel->insert($payload);
        return redirect()->to('/accounts')->with('success', 'Account berhasil ditambahkan.');
    }

    public function update(int $id): RedirectResponse
    {
        $this->requirePerm('manage_vendor_accounts');
        $acc = $this->accountModel->find($id);
        if (!$acc) {
            return redirect()->to('/accounts')->with('error', 'Account tidak ditemukan.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama account wajib diisi.');
        }

        $status = (string) $this->request->getPost('status');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = (string) ($acc['status'] ?? 'active');
        }

        $payload = [
            'name'       => $name,
            'status'     => $status,
            'platform'   => trim((string) $this->request->getPost('platform')) ?: null,
            'owner_name' => trim((string) $this->request->getPost('owner_name')) ?: null,
            'notes'      => trim((string) $this->request->getPost('notes')) ?: null,
        ];

        if (($acc['type'] ?? '') === 'vendor') {
            $contractMode = (string) $this->request->getPost('contract_mode');
            if (!in_array($contractMode, ['monthly', 'lifetime', 'on_demand'], true)) {
                $contractMode = (string) ($acc['contract_mode'] ?? 'monthly');
            }
            $payload['contract_mode'] = $contractMode;
            $payload['next_review_date'] = ($this->request->getPost('next_review_date') ?: null);
            $payload['contract_end_date'] = ($this->request->getPost('contract_end_date') ?: null);
            if ($contractMode === 'lifetime') {
                $payload['contract_end_date'] = null;
            }
        }

        $this->accountModel->update($id, $payload);
        return redirect()->to('/accounts')->with('success', 'Account berhasil diperbarui.');
    }

    public function delete(int $id): RedirectResponse
    {
        $this->requirePerm('manage_vendor_accounts');
        $this->accountModel->delete($id);
        return redirect()->to('/accounts')->with('success', 'Account berhasil dihapus.');
    }

    protected function requirePerm(string $perm): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        $perms = session()->get('user_perms') ?? [];
        if (!in_array($perm, (array) $perms, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }
}

