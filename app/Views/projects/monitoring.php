<?php
/** @var array $rows */
/** @var array $assigneeRows */
/** @var array $vendorOptions */
/** @var int $selectedVendorId */
/** @var array|null $selectedVendor */
/** @var array $summary */
/** @var string $monthStart */
/** @var string $today */
?>

<link rel="stylesheet" href="/assets/css/pages/project-monitoring.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Project Monitoring</h2>
    <p class="page-sub">Ringkasan progres vendor (periode <?= esc(date('M Y', strtotime($monthStart))) ?>).</p>
  </div>
  <div class="page-header-right">
    <form method="GET" action="/projects/monitoring" class="pm-filter-form">
      <select name="vendor_id" class="form-control form-control-sm pm-filter-select" onchange="this.form.requestSubmit()">
        <option value="">Pilih vendor</option>
        <?php foreach ($vendorOptions as $v): ?>
          <option value="<?= (int) $v['id'] ?>" <?= (int) $selectedVendorId === (int) $v['id'] ? 'selected' : '' ?>>
            <?= esc($v['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="card pm-hero mb-14">
  <div class="pm-hero-top">
    <div>
      <div class="pm-vendor-name"><?= esc($summary['name'] ?? '—') ?></div>
      <div class="pm-vendor-meta">
        <span><?= esc($summary['platform'] ?? '—') ?></span>
        <span class="pm-dot"></span>
        <span>Status: <?= esc($summary['status'] ?? 'inactive') ?></span>
        <span class="pm-dot"></span>
        <span>Review: <?= !empty($summary['next_review_date']) ? esc($summary['next_review_date']) : '—' ?></span>
        <span class="pm-dot"></span>
        <span>End: <?= !empty($summary['contract_end_date']) ? esc($summary['contract_end_date']) : '—' ?></span>
      </div>
    </div>
    <div class="pm-deadline-chip <?= !empty($summary['contract_end_date']) && $summary['contract_end_date'] < $today ? 'is-ended' : '' ?>">
      Deadline: <?= !empty($summary['contract_end_date']) ? esc($summary['contract_end_date']) : 'N/A' ?>
    </div>
  </div>
</div>

<div class="pm-stat-grid mb-14">
  <div class="card pm-stat-card">
    <div class="pm-stat-label">Scope Target</div>
    <div class="pm-stat-value"><?= (int) ($summary['target_value'] ?? 0) ?></div>
  </div>
  <div class="card pm-stat-card is-ready">
    <div class="pm-stat-label">Selesai bulan ini</div>
    <div class="pm-stat-value"><?= (int) ($summary['done_this_month'] ?? 0) ?></div>
    <div class="pm-stat-sub">Total selesai: <?= (int) ($summary['done_tasks'] ?? 0) ?></div>
  </div>
  <div class="card pm-stat-card">
    <div class="pm-stat-label">Sisa vs target</div>
    <div class="pm-stat-value"><?= (int) ($summary['target_gap'] ?? 0) ?></div>
    <div class="pm-stat-sub">Target − selesai bulan ini</div>
  </div>
  <div class="card pm-stat-card">
    <div class="pm-stat-label">On Progress</div>
    <div class="pm-stat-value"><?= (int) ($summary['on_progress_tasks'] ?? 0) ?></div>
  </div>
  <div class="card pm-stat-card">
    <div class="pm-stat-label">Waiting List</div>
    <div class="pm-stat-value"><?= (int) ($summary['waiting_tasks'] ?? 0) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header pm-card-head">
    <h3 class="card-title">Items Detail</h3>
    <span class="pm-head-note">Avg progress: <?= (int) ($summary['avg_progress'] ?? 0) ?>%</span>
  </div>
  <div class="pm-item-grid">
    <?php if (empty($assigneeRows)): ?>
      <div class="pm-empty-note">Belum ada distribusi kreator.</div>
    <?php else: ?>
      <?php foreach ($assigneeRows as $r): ?>
        <article class="pm-item-card">
          <div class="pm-item-top">
            <div class="pm-item-name"><?= esc($r['nickname'] ?: $r['username'] ?: 'Unassigned') ?></div>
            <div class="pm-item-total"><?= (int) $r['total_tasks'] ?></div>
          </div>
          <div class="pm-progress-wrap">
            <div class="pm-progress-bar">
              <div class="pm-progress-fill" style="width:<?= (int) $r['progress_pct'] ?>%"></div>
            </div>
            <div class="pm-progress-label"><?= (int) $r['progress_pct'] ?>%</div>
          </div>
          <div class="pm-item-stats">
            <span>Bulan ini: <?= (int) ($r['done_this_month'] ?? 0) ?></span>
            <span>Done total: <?= (int) $r['done_tasks'] ?></span>
            <span>On Progress: <?= (int) $r['on_progress_tasks'] ?></span>
            <span>Waiting: <?= (int) $r['waiting_tasks'] ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
