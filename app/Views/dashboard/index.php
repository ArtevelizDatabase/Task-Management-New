<?php
/** @var array $summary */
/** @var array $overdue */
/** @var array $monthActivity */
/** @var array $teamProgress */
/** @var string $monthLabel */
$by = $summary['by_status'] ?? [];
$ma = $monthActivity ?? ['created_this_month' => 0, 'completed_this_month' => 0];
?>

<link rel="stylesheet" href="/assets/css/pages/dashboard.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Dashboard</h2>
    <p class="page-sub">Ringkasan task dalam scope akses Anda. Periode: <strong><?= esc($monthLabel ?? '') ?></strong>.</p>
  </div>
</div>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Dibuat bulan ini</div>
    <div class="stat-value"><?= (int) ($ma['created_this_month'] ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Selesai bulan ini</div>
    <div class="stat-value stat-value-success"><?= (int) ($ma['completed_this_month'] ?? 0) ?></div>
  </div>
</div>

<div class="stats-row">
  <a class="stat-card dash-stat-link dash-stat-link--accent" href="/tasks">
    <div class="stat-label">Total task</div>
    <div class="stat-value"><?= (int) ($summary['total'] ?? 0) ?></div>
  </a>
  <a class="stat-card dash-stat-link" href="/tasks?status=pending">
    <div class="stat-label">Pending</div>
    <div class="stat-value"><?= (int) ($by['pending'] ?? 0) ?></div>
  </a>
  <a class="stat-card dash-stat-link" href="/tasks?status=on_progress">
    <div class="stat-label">On progress</div>
    <div class="stat-value"><?= (int) ($by['on_progress'] ?? 0) ?></div>
  </a>
  <a class="stat-card dash-stat-link" href="/tasks?status=done">
    <div class="stat-label">Done</div>
    <div class="stat-value stat-value-success"><?= (int) ($by['done'] ?? 0) ?></div>
  </a>
  <a class="stat-card dash-stat-link" href="/tasks?status=cancelled">
    <div class="stat-label">Cancelled</div>
    <div class="stat-value"><?= (int) ($by['cancelled'] ?? 0) ?></div>
  </a>
  <a class="stat-card dash-stat-link dash-stat-link--warn" href="/tasks?deadline_filter=overdue">
    <div class="stat-label">Overdue</div>
    <div class="stat-value stat-value-danger"><?= (int) ($summary['overdue'] ?? 0) ?></div>
  </a>
  <div class="stat-card dash-stat-static">
    <div class="stat-label">Avg progress</div>
    <div class="stat-value"><?= (int) ($summary['avg_progress'] ?? 0) ?>%</div>
  </div>
  <a class="stat-card dash-stat-link" href="/tasks?setor=1">
    <div class="stat-label">Punya setor</div>
    <div class="stat-value"><?= (int) ($summary['with_submission'] ?? 0) ?></div>
  </a>
</div>

<div class="card dash-card mb-4">
  <div class="card-head">
    <span>Progress tim <span class="dash-card-head-hint">(dalam scope)</span></span>
  </div>
  <p class="dash-card-desc">Per PIC: total task, rata-rata progress, dan selesai bulan ini (status Done, <code>updated_at</code> bulan berjalan).</p>
  <div class="table-wrap dash-team-wrap">
    <table class="dash-team-table">
      <thead>
        <tr>
          <th scope="col">Nama</th>
          <th scope="col" class="text-right">Task</th>
          <th scope="col" class="text-right">Avg progress</th>
          <th scope="col" class="text-right">Selesai (bulan ini)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($teamProgress)): ?>
          <tr>
            <td colspan="4" class="dash-table-empty">Tidak ada data tim dalam scope ini.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($teamProgress as $row): ?>
            <?php
              $nick = trim((string) ($row['nickname'] ?? ''));
              $user = trim((string) ($row['username'] ?? ''));
              $label = $nick !== '' ? $nick : ($user !== '' ? $user : 'User #' . (int) ($row['user_id'] ?? 0));
            ?>
            <tr>
              <td><?= esc($label) ?></td>
              <td class="text-right"><?= (int) ($row['total_tasks'] ?? 0) ?></td>
              <td class="text-right"><?= (int) round((float) ($row['avg_progress'] ?? 0)) ?>%</td>
              <td class="text-right"><?= (int) ($row['done_this_month'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card dash-card mb-4">
  <div class="card-head">
    <span>Perlu perhatian</span>
  </div>
  <p class="dash-card-desc">Overdue (deadline lewat), status belum Done.</p>
  <?php if (empty($overdue)): ?>
    <p class="dash-muted dash-card-body-pad">Tidak ada task overdue dalam scope ini.</p>
  <?php else: ?>
    <ul class="dash-overdue-list">
      <?php foreach ($overdue as $row): ?>
        <li>
          <a href="/tasks/<?= (int) ($row['id'] ?? 0) ?>" class="dash-overdue-link">
            Task #<?= (int) ($row['id'] ?? 0) ?>
          </a>
          <span class="dash-overdue-meta">
            <?= esc($row['deadline'] ?? '') ?>
            &middot; <?= esc($row['status'] ?? '') ?>
            &middot; <?= (int) ($row['progress'] ?? 0) ?>%
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card dash-card dash-tips">
  <div class="card-head">
    <span>Saran penggunaan</span>
  </div>
  <ul class="dash-tips-list">
    <li>Sinkronkan Account di task dengan master <code>tb_accounts</code> (format <code>account:ID</code>) agar filter vendor dan monitoring akurat.</li>
    <li>Jalankan <code>php spark data:sync-task-accounts --force</code> sekali untuk backfill <code>account_id</code> dari EAV, lalu rutin <code>php spark data:cleanup</code> untuk trash/orphan (gunakan <code>--force</code> saat siap menghapus).</li>
    <li>Bandingkan angka <em>Selesai bulan ini</em> dengan target di <a href="/projects/monitoring">Project Monitoring</a> bila perlu.</li>
    <li>Untuk beban besar di <code>/tasks</code>, pertimbangkan indeks DB pada pola filter yang sering dipakai setelah ada bukti query lambat.</li>
  </ul>
</div>
