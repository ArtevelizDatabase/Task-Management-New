<?php

namespace App\Controllers;

use App\Models\TaskModel;
use App\Models\VendorAllocationModel;
use CodeIgniter\Controller;

class Dashboard extends Controller
{
    protected TaskModel $taskModel;
    protected VendorAllocationModel $vendorAllocationModel;

    public function __construct()
    {
        $this->taskModel             = new TaskModel();
        $this->vendorAllocationModel = new VendorAllocationModel();
    }

    private function requirePerm(string $perm, bool $asJson = false): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        if ($role === 'member') {
            return;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            if ($asJson) {
                $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Akses ditolak.',
                ])->send();
            } else {
                redirect()->back()->with('error', 'Akses ditolak.')->send();
            }
            exit;
        }
    }

    public function index(): string
    {
        $this->requirePerm('view_tasks');

        $db = \Config\Database::connect();
        $hasVendorAllocs = $db->tableExists('tb_vendor_allocations');

        $currentRole   = session()->get('user_role') ?? 'member';
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        $scopeUserId   = ($currentRole === 'member') ? $currentUserId : null;
        $allowedVendorIds = [];
        if ($currentRole === 'member' && $hasVendorAllocs) {
            $rows = $this->vendorAllocationModel->where('user_id', $currentUserId)->findAll();
            $allowedVendorIds = array_values(array_map(static fn(array $r): int => (int) ($r['account_id'] ?? 0), $rows));
            $allowedVendorIds = array_values(array_filter($allowedVendorIds, static fn(int $v): bool => $v > 0));
        }

        $cacheScope = ($scopeUserId !== null ? 'u' . $scopeUserId : 'g') . '_' . substr(sha1(json_encode($allowedVendorIds)), 0, 24);

        $summary = cache()->remember(
            'dashboard_summary_' . $cacheScope,
            300,
            fn () => $this->taskModel->getDashboardSummary($scopeUserId, $allowedVendorIds)
        );
        $overdue = cache()->remember(
            'dashboard_overdue_' . $cacheScope,
            300,
            fn () => $this->taskModel->getDashboardOverduePreview($scopeUserId, $allowedVendorIds, 5)
        );
        $monthAct = cache()->remember(
            'dashboard_month_act_' . $cacheScope,
            300,
            fn () => $this->taskModel->getDashboardMonthActivity($scopeUserId, $allowedVendorIds)
        );
        $teamProgress = cache()->remember(
            'dashboard_team_prog_' . $cacheScope,
            300,
            fn () => $this->taskModel->getDashboardTeamMonthProgress($scopeUserId, $allowedVendorIds, 10)
        );

        $bulanId = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $monthLabel = $bulanId[(int) date('n')] . ' ' . date('Y');

        return view('layouts/main', [
            'title'   => 'Dashboard',
            'content' => view('dashboard/index', [
                'summary'       => $summary,
                'overdue'       => $overdue,
                'monthActivity' => $monthAct,
                'teamProgress'  => $teamProgress,
                'monthLabel'    => $monthLabel,
            ]),
        ]);
    }
}
