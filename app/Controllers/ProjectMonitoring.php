<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class ProjectMonitoring extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $this->requirePerm('view_project_monitoring');
        $db = \Config\Database::connect();
        $hasVendors = $db->tableExists('tb_accounts');
        $hasTargets = $db->tableExists('tb_vendor_targets');
        $hasVendorColOnTask = $db->fieldExists('account_id', 'tb_task');
        if (! $hasVendors || ! $hasTargets || ! $hasVendorColOnTask) {
            return redirect()->to('/tasks')->with('error', 'Monitoring vendor belum aktif. Jalankan migrasi database terlebih dahulu.');
        }

        $today       = date('Y-m-d');
        $monthStart  = date('Y-m-01');
        $monthStartDt = date('Y-m-01 00:00:00');
        $monthEndDt   = date('Y-m-d H:i:s', strtotime('first day of next month'));
        $vendorId    = (int) ($this->request->getGet('vendor_id') ?? 0);

        $vendorOptions = $db->table('tb_accounts')
            ->select('id, name, platform, status')
            ->where('type', 'vendor')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        if ($vendorId < 1 && ! empty($vendorOptions)) {
            $vendorId = (int) ($vendorOptions[0]['id'] ?? 0);
        }

        $selectedVendor = null;
        foreach ($vendorOptions as $v) {
            if ((int) $v['id'] === $vendorId) {
                $selectedVendor = $v;
                break;
            }
        }

        $escStart = $db->escape($monthStartDt);
        $escNext  = $db->escape($monthEndDt);
        $escToday = $db->escape($today);

        $rows = $db->table('tb_accounts va')
            ->select('va.id, va.name, va.platform, va.status, va.next_review_date, va.contract_end_date')
            ->select('COUNT(t.id) AS total_tasks', false)
            ->select("SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks", false)
            ->select(
                'SUM(CASE WHEN t.status = \'done\' AND t.updated_at >= '
                . $escStart . ' AND t.updated_at < ' . $escNext . ' THEN 1 ELSE 0 END) AS done_this_month',
                false
            )
            ->select("SUM(CASE WHEN t.status = 'on_progress' THEN 1 ELSE 0 END) AS on_progress_tasks", false)
            ->select("SUM(CASE WHEN t.status IN ('pending','cancelled') THEN 1 ELSE 0 END) AS waiting_tasks", false)
            ->select('SUM(CASE WHEN t.deadline IS NOT NULL AND t.deadline < ' . $escToday . " AND t.status <> 'done' THEN 1 ELSE 0 END) AS overdue_tasks", false)
            ->select('AVG(COALESCE(t.progress, 0)) AS avg_progress', false)
            ->where('va.type', 'vendor')
            ->join('tb_task t', 't.account_id = va.id AND t.deleted_at IS NULL', 'left', false)
            ->groupBy('va.id');

        if ($vendorId > 0) {
            $rows->where('va.id', $vendorId);
        }

        $rows = $rows
            ->get()
            ->getResultArray();

        $targetsQuery = $db->table('tb_vendor_targets')
            ->where('period_type', 'monthly')
            ->where('period_start', $monthStart);
        if ($vendorId > 0) {
            $targetsQuery->where('account_id', $vendorId);
        }
        $targets   = $targetsQuery->get()->getResultArray();
        $targetMap = [];
        foreach ($targets as $row) {
            $aid = (int) ($row['account_id'] ?? 0);
            if ($aid > 0) {
                $targetMap[$aid] = (int) $row['target_value'];
            }
        }

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['total_tasks']       = (int) ($row['total_tasks'] ?? 0);
            $row['done_tasks']        = (int) ($row['done_tasks'] ?? 0);
            $row['done_this_month']   = (int) ($row['done_this_month'] ?? 0);
            $row['on_progress_tasks'] = (int) ($row['on_progress_tasks'] ?? 0);
            $row['waiting_tasks']     = (int) ($row['waiting_tasks'] ?? 0);
            $row['overdue_tasks']     = (int) ($row['overdue_tasks'] ?? 0);
            $row['avg_progress']      = (int) round((float) ($row['avg_progress'] ?? 0));
            $row['target_value']      = $targetMap[$id] ?? 0;
            // Target bulanan vs selesai bulan ini (selaras tb_vendor_targets period_type monthly)
            $row['target_gap']        = $row['target_value'] - $row['done_this_month'];
        }
        unset($row);

        $assigneeRowsQuery = $db->table('tb_accounts va')
            ->select('va.name AS vendor_name, u.nickname, u.username, COUNT(t.id) AS total_tasks', false)
            ->select("SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks", false)
            ->select(
                'SUM(CASE WHEN t.status = \'done\' AND t.updated_at >= '
                . $escStart . ' AND t.updated_at < ' . $escNext . ' THEN 1 ELSE 0 END) AS done_this_month',
                false
            )
            ->select("SUM(CASE WHEN t.status = 'on_progress' THEN 1 ELSE 0 END) AS on_progress_tasks", false)
            ->select("SUM(CASE WHEN t.status IN ('pending','cancelled') THEN 1 ELSE 0 END) AS waiting_tasks", false)
            ->where('va.type', 'vendor')
            ->join('tb_task t', 't.account_id = va.id AND t.deleted_at IS NULL', 'left', false)
            ->join('tb_users u', 'u.id = t.user_id', 'left')
            ->groupBy('va.id, u.id');
        if ($vendorId > 0) {
            $assigneeRowsQuery->where('va.id', $vendorId);
        }
        $assigneeRows = $assigneeRowsQuery
            ->orderBy('total_tasks', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($assigneeRows as &$a) {
            $a['total_tasks']       = (int) ($a['total_tasks'] ?? 0);
            $a['done_tasks']        = (int) ($a['done_tasks'] ?? 0);
            $a['done_this_month']   = (int) ($a['done_this_month'] ?? 0);
            $a['on_progress_tasks'] = (int) ($a['on_progress_tasks'] ?? 0);
            $a['waiting_tasks']     = (int) ($a['waiting_tasks'] ?? 0);
            $a['progress_pct']      = $a['total_tasks'] > 0
                ? (int) round(($a['done_tasks'] / $a['total_tasks']) * 100)
                : 0;
        }
        unset($a);

        $summary = $rows[0] ?? [
            'id' => 0, 'name' => 'Vendor belum dipilih', 'platform' => '', 'status' => 'inactive',
            'total_tasks' => 0, 'done_tasks' => 0, 'done_this_month' => 0, 'on_progress_tasks' => 0, 'waiting_tasks' => 0,
            'overdue_tasks' => 0, 'avg_progress' => 0, 'target_value' => 0, 'target_gap' => 0,
            'next_review_date' => null, 'contract_end_date' => null,
        ];

        $d = [
            'title'            => 'Project Monitoring',
            'vendorOptions'    => $vendorOptions,
            'selectedVendorId' => $vendorId,
            'selectedVendor'   => $selectedVendor,
            'summary'          => $summary,
            'rows'             => $rows,
            'assigneeRows'     => $assigneeRows,
            'monthStart'       => $monthStart,
            'today'            => $today,
        ];

        return view('layouts/main', array_merge($d, ['content' => view('projects/monitoring', $d)]));
    }

    protected function requirePerm(string $perm): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }
}
