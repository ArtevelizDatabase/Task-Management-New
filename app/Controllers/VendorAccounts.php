<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use App\Models\AssignmentRuleModel;
use App\Models\TeamModel;
use App\Models\UserModel;
use App\Models\AccountModel;
use App\Models\VendorAllocationModel;
use App\Models\VendorTargetModel;

class VendorAccounts extends BaseController
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
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }

        $accounts = $this->accountModel
            ->where('type', 'vendor')
            ->orderBy('id', 'DESC')
            ->findAll();
        $users = $this->userModel->getActiveUsers();
        $teams = $this->teamModel->orderBy('name', 'ASC')->findAll();

        $targets = $this->vendorTargetModel->findAll();
        $targetByAccount = [];
        foreach ($targets as $row) {
            $k = (int) ($row['account_id'] ?? 0);
            if ($k <= 0) continue;
            $targetByAccount[$k][] = $row;
        }

        $allocRows = $this->vendorAllocationModel->findAll();
        $allocByAccount = [];
        $primaryByAccount = [];
        foreach ($allocRows as $row) {
            $k = (int) ($row['account_id'] ?? 0);
            if ($k <= 0) continue;
            $allocByAccount[$k][] = (int) $row['user_id'];
            if ((int) ($row['is_primary'] ?? 0) === 1) {
                $primaryByAccount[$k] = (int) $row['user_id'];
            }
        }

        $rules = $this->assignmentRuleModel->findAll();
        $ruleByAccount = [];
        foreach ($rules as $row) {
            $k = (int) ($row['account_id'] ?? 0);
            if ($k <= 0) continue;
            $ruleByAccount[$k] = $row;
        }

        $d = [
            'title' => 'Vendor Accounts',
            'accounts' => $accounts,
            'users' => $users,
            'teams' => $teams,
            'targetByAccount' => $targetByAccount,
            'allocByAccount' => $allocByAccount,
            'primaryByAccount' => $primaryByAccount,
            'ruleByAccount' => $ruleByAccount,
        ];

        return view('layouts/main', array_merge($d, ['content' => view('vendors/index', $d)]));
    }

    public function store()
    {
        $this->requirePerm('manage_vendor_accounts');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama akun vendor wajib diisi.');
        }

        $contractMode = in_array($this->request->getPost('contract_mode'), ['monthly', 'lifetime', 'on_demand'], true)
            ? $this->request->getPost('contract_mode')
            : 'monthly';
        $contractEndDate = ($this->request->getPost('contract_end_date') ?: null);
        if ($contractMode !== 'lifetime' && empty($contractEndDate)) {
            return redirect()->back()->with('error', 'Tanggal berakhir kontrak wajib diisi untuk mode monthly/on demand.')->withInput();
        }
        if (!empty($contractEndDate) && strtotime((string) $contractEndDate) === false) {
            return redirect()->back()->with('error', 'Format tanggal berakhir kontrak tidak valid.')->withInput();
        }

        $this->accountModel->insert([
            'contract_mode' => $contractMode,
            'name'          => $name,
            'type'          => 'vendor',
            'platform'      => trim((string) $this->request->getPost('platform')) ?: null,
            'owner_name'    => trim((string) $this->request->getPost('owner_name')) ?: null,
            'status'        => in_array($this->request->getPost('status'), ['active', 'inactive'], true) ? $this->request->getPost('status') : 'active',
            'next_review_date'  => ($this->request->getPost('next_review_date') ?: null),
            'contract_end_date' => ($contractMode === 'lifetime') ? null : $contractEndDate,
            'notes'         => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by'    => (int) (session()->get('user_id') ?? 0),
        ]);

        return redirect()->to('/vendors')->with('success', 'Akun vendor berhasil ditambahkan.');
    }

    public function update(int $id)
    {
        $this->requirePerm('manage_vendor_accounts');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }
        $acc = $this->accountModel->find($id);
        if (!$acc) {
            return redirect()->to('/vendors')->with('error', 'Akun vendor tidak ditemukan.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama akun vendor wajib diisi.');
        }

        $contractMode = in_array($this->request->getPost('contract_mode'), ['monthly', 'lifetime', 'on_demand'], true)
            ? $this->request->getPost('contract_mode')
            : 'monthly';
        $contractEndDate = ($this->request->getPost('contract_end_date') ?: null);
        if ($contractMode !== 'lifetime' && empty($contractEndDate)) {
            return redirect()->back()->with('error', 'Tanggal berakhir kontrak wajib diisi untuk mode monthly/on demand.')->withInput();
        }
        if (!empty($contractEndDate) && strtotime((string) $contractEndDate) === false) {
            return redirect()->back()->with('error', 'Format tanggal berakhir kontrak tidak valid.')->withInput();
        }

        $this->accountModel->update($id, [
            'name'       => $name,
            'platform'   => trim((string) $this->request->getPost('platform')),
            'owner_name' => trim((string) $this->request->getPost('owner_name')),
            'status'     => in_array($this->request->getPost('status'), ['active', 'inactive'], true) ? $this->request->getPost('status') : 'active',
            'contract_mode'    => $contractMode,
            'next_review_date' => ($this->request->getPost('next_review_date') ?: null),
            'contract_end_date' => ($contractMode === 'lifetime') ? null : $contractEndDate,
            'notes'      => trim((string) $this->request->getPost('notes')),
        ]);

        return redirect()->to('/vendors')->with('success', 'Akun vendor berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $this->requirePerm('manage_vendor_accounts');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }

        $this->vendorTargetModel->where('account_id', $id)->delete();
        $this->vendorAllocationModel->where('account_id', $id)->delete();
        $this->assignmentRuleModel->where('account_id', $id)->delete();
        $this->accountModel->delete($id);

        return redirect()->to('/vendors')->with('success', 'Akun vendor berhasil dihapus.');
    }

    public function setTarget(int $id)
    {
        $this->requirePerm('manage_vendor_accounts');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }
        $acc = $this->accountModel->find($id);
        if (!$acc) {
            return redirect()->to('/vendors')->with('error', 'Akun vendor tidak ditemukan.');
        }

        $periodType = (string) ($this->request->getPost('period_type') ?? 'monthly');
        if (!in_array($periodType, ['daily', 'weekly', 'monthly'], true)) {
            $periodType = 'monthly';
        }

        $periodStart = (string) ($this->request->getPost('period_start') ?? date('Y-m-01'));
        $targetValue = max(0, (int) ($this->request->getPost('target_value') ?? 0));

        $existing = $this->vendorTargetModel
            ->where('account_id', $id)
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->first();

        $payload = [
            'account_id'        => $id,
            'period_type'       => $periodType,
            'period_start'      => $periodStart,
            'target_value'      => $targetValue,
            'created_by'        => (int) (session()->get('user_id') ?? 0),
        ];

        if ($existing) {
            $this->vendorTargetModel->update((int) $existing['id'], $payload);
        } else {
            $this->vendorTargetModel->insert($payload);
        }

        return redirect()->to('/vendors')->with('success', 'Target akun vendor berhasil disimpan.');
    }

    public function setAllocation(int $id)
    {
        $this->requirePerm('manage_vendor_allocation');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }
        $acc = $this->accountModel->find($id);
        if (!$acc) {
            return redirect()->to('/vendors')->with('error', 'Akun vendor tidak ditemukan.');
        }

        $userIds = $this->request->getPost('user_ids') ?? [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $userIds))));
        $primary = (int) ($this->request->getPost('primary_user_id') ?? 0);

        $this->vendorAllocationModel->where('account_id', $id)->delete();
        foreach ($userIds as $uid) {
            $this->vendorAllocationModel->insert([
                'account_id'        => $id,
                'user_id'           => $uid,
                'is_primary'        => $uid === $primary ? 1 : 0,
                'created_by'        => (int) (session()->get('user_id') ?? 0),
            ]);
        }

        return redirect()->to('/vendors')->with('success', 'Alokasi akun vendor berhasil diperbarui.');
    }

    public function setRule(int $id)
    {
        $this->requirePerm('manage_vendor_allocation');
        if (!$this->vendorSchemaReady()) {
            return redirect()->to('/tasks')->with('error', 'Fitur Vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }
        $acc = $this->accountModel->find($id);
        if (!$acc) {
            return redirect()->to('/vendors')->with('error', 'Akun vendor tidak ditemukan.');
        }

        $payload = [
            'account_id'        => $id,
            'default_user_id'   => ($this->request->getPost('default_user_id') !== '') ? (int) $this->request->getPost('default_user_id') : null,
            'default_team_id'   => ($this->request->getPost('default_team_id') !== '') ? (int) $this->request->getPost('default_team_id') : null,
            'status'            => in_array($this->request->getPost('status'), ['active', 'inactive'], true) ? $this->request->getPost('status') : 'active',
            'priority'          => max(1, (int) ($this->request->getPost('priority') ?? 100)),
            'created_by'        => (int) (session()->get('user_id') ?? 0),
        ];

        $existing = $this->assignmentRuleModel->where('account_id', $id)->first();
        if ($existing) {
            $this->assignmentRuleModel->update((int) $existing['id'], $payload);
        } else {
            $this->assignmentRuleModel->insert($payload);
        }

        return redirect()->to('/vendors')->with('success', 'Rule assignment default berhasil diperbarui.');
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

    protected function vendorSchemaReady(): bool
    {
        $db = \Config\Database::connect();
        return $db->tableExists('tb_accounts')
            && $db->tableExists('tb_vendor_targets')
            && $db->tableExists('tb_vendor_allocations')
            && $db->tableExists('tb_assignment_rules');
    }
}
