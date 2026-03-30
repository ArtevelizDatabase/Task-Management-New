<?php
/**
 * @var array  $tasks
 * @var array  $fields
 * @var array  $countMap
 * @var int    $countSetor
 * @var string $statusFilter
 * @var string $setorFilter
 * @var array  $filters
 * @var bool   $showProgress
 * @var bool   $showDeadline
 * @var int    $filteredTaskCount
 * @var int    $statsAvgProgress
 * @var int    $statsOverdue
 * @var array  $bulkFields   field yang boleh diubah massal (text/select/…)
 * @var string $vendorFilter
 * @var array  $vendorAccounts
 * @var array  $userSourceOptionsByField
 * @var array  $accountSourceLabelMap
 */
$showProgress = $showProgress ?? true;
$showDeadline = $showDeadline ?? true;

$total         = (int) ($filteredTaskCount ?? count($tasks ?? []));
$avgProgress   = (int) ($statsAvgProgress ?? 0);
$overdue       = (int) ($statsOverdue ?? 0);
$today         = date('Y-m-d');
$bulkFields    = $bulkFields ?? [];
$vendorFilter  = (string) ($vendorFilter ?? '');
$vendorAccounts = $vendorAccounts ?? [];
$userSourceOptionsByField = $userSourceOptionsByField ?? [];
$accountSourceLabelMap = $accountSourceLabelMap ?? [];

// Detect first text field (for bulk-create title mapping)
$titleField = null;
foreach ($fields as $f) {
    if (in_array($f['type'], ['text', 'email', 'number']) && $f['field_key'] !== 'setor') {
        $titleField = $f;
        break;
    }
}

// Show ALL active fields in table (same order as Field Manager)
$tableFields = $fields;
// columns: bulk checkbox + dynamic fields + (progress?) + (deadline?) + actions
$tableColspan = 1 + count($tableFields) + ($showProgress ? 1 : 0) + ($showDeadline ? 1 : 0) + 1;

// ── Color palette for select badges (24 colors, auto-assigned by value hash) ──
$colorPalette = [
  ['bg'=>'var(--badge-1-bg)','text'=>'var(--badge-1-text)'],
  ['bg'=>'var(--badge-2-bg)','text'=>'var(--badge-2-text)'],
  ['bg'=>'var(--badge-3-bg)','text'=>'var(--badge-3-text)'],
  ['bg'=>'var(--badge-4-bg)','text'=>'var(--badge-4-text)'],
  ['bg'=>'var(--badge-5-bg)','text'=>'var(--badge-5-text)'],
  ['bg'=>'var(--badge-6-bg)','text'=>'var(--badge-6-text)'],
  ['bg'=>'var(--badge-7-bg)','text'=>'var(--badge-7-text)'],
  ['bg'=>'var(--badge-8-bg)','text'=>'var(--badge-8-text)'],
  ['bg'=>'var(--badge-9-bg)','text'=>'var(--badge-9-text)'],
  ['bg'=>'var(--badge-10-bg)','text'=>'var(--badge-10-text)'],
  ['bg'=>'var(--badge-11-bg)','text'=>'var(--badge-11-text)'],
  ['bg'=>'var(--badge-12-bg)','text'=>'var(--badge-12-text)'],
  ['bg'=>'var(--badge-13-bg)','text'=>'var(--badge-13-text)'],
  ['bg'=>'var(--badge-14-bg)','text'=>'var(--badge-14-text)'],
  ['bg'=>'var(--badge-15-bg)','text'=>'var(--badge-15-text)'],
  ['bg'=>'var(--badge-16-bg)','text'=>'var(--badge-16-text)'],
  ['bg'=>'var(--badge-17-bg)','text'=>'var(--badge-17-text)'],
  ['bg'=>'var(--badge-18-bg)','text'=>'var(--badge-18-text)'],
  ['bg'=>'var(--badge-19-bg)','text'=>'var(--badge-19-text)'],
  ['bg'=>'var(--badge-20-bg)','text'=>'var(--badge-20-text)'],
  ['bg'=>'var(--badge-21-bg)','text'=>'var(--badge-21-text)'],
  ['bg'=>'var(--badge-22-bg)','text'=>'var(--badge-22-text)'],
  ['bg'=>'var(--badge-23-bg)','text'=>'var(--badge-23-text)'],
  ['bg'=>'var(--badge-24-bg)','text'=>'var(--badge-24-text)'],
];
$paletteCount = count($colorPalette);
function optBadgeColor(string $val, array $palette): array {
  $h = 0;
  for ($i = 0, $l = strlen($val); $i < $l; $i++) $h = ($h * 31 + ord($val[$i])) % count($palette);
  return $palette[$h];
}
function optAccountBadgeColor(string $accountKeyOrName, array $palette): array {
  $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $accountKeyOrName)));
  return optBadgeColor($normalized, $palette);
}
?>

<link rel="stylesheet" href="/assets/css/pages/tasks-index.css" />

<?php
/* Count active server-side filters for badge */
$_activeFilters = 0;
foreach ($filters as $_fv) if (!empty($_fv)) $_activeFilters++;
if (!empty($_GET['progress_filter'])) $_activeFilters++;
if (!empty($_GET['deadline_filter'])) $_activeFilters++;
if (!empty($setorFilter))             $_activeFilters++;
?>

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Task internal</h2>
    <p class="page-sub" style="margin:0.25rem 0 0;">Operasional akun &amp; vendor. Task per klien ada di <a href="/projects">Projects</a>.</p>
    <p class="page-sub">Kelola task, filter, progress, dan daftar setor</p>
  </div>
  <div class="page-header-right">
    <button type="button" class="btn btn-primary" onclick="openModal('addTaskModal')">
      <i class="fa-solid fa-plus icon-xs" aria-hidden="true"></i> Tambah Task
    </button>
  </div>
</div>

<!-- STATS ROW -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Total Task</div>
    <div class="stat-value"><?= $total ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Sudah Setor</div>
    <div class="stat-value stat-value-success"><?= $countSetor ?></div>
  </div>
  <?php if ($showProgress): ?>
  <div class="stat-card">
    <div class="stat-label">Avg Progress</div>
    <div class="stat-value" style="display:inline-flex;align-items:center;gap:6px">
      <span class="stat-progress-ring" style="--p:<?= $avgProgress ?>">
        <svg viewBox="0 0 36 36" class="stat-ring-svg">
          <circle cx="18" cy="18" r="15" fill="none" stroke="var(--border)" stroke-width="3"/>
          <circle cx="18" cy="18" r="15" fill="none" stroke="var(--accent)" stroke-width="3"
                  stroke-dasharray="<?= round($avgProgress * 94.2 / 100, 1) ?> 94.2"
                  stroke-linecap="round" transform="rotate(-90 18 18)"/>
        </svg>
      </span>
      <?= $avgProgress ?>%
    </div>
  </div>
  <?php endif; ?>
  <?php if ($showDeadline && $overdue > 0): ?>
  <div class="stat-card stat-card-warn">
    <div class="stat-label">Overdue</div>
    <div class="stat-value stat-value-danger"><?= $overdue ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- FILTER BAR -->
<div class="filter-bar-row" id="filter-bar">

  <!-- Live search -->
  <div class="search-wrap search-wrap-grow">
    <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
    <input type="text" id="taskSearchInput" class="form-control search-input"
           placeholder="Cari task…" autocomplete="off">
    <button type="button" id="taskSearchClear" class="search-clear u-hidden" title="Clear search">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <!-- Filter dropdown -->
  <div class="flt-wrap" id="fltWrap">
    <button type="button" class="btn btn-ghost btn-sm flt-toggle<?= $_activeFilters ? ' flt-toggle--active' : '' ?>"
            id="fltToggleBtn" aria-expanded="false" aria-controls="fltPanel">
      <i class="fa-solid fa-sliders icon-xs" aria-hidden="true"></i>
      Filter
      <?php if ($_activeFilters): ?>
        <span class="flt-badge"><?= $_activeFilters ?></span>
      <?php endif; ?>
      <i class="fa-solid fa-chevron-down flt-chevron icon-xs" aria-hidden="true"></i>
    </button>

    <!-- Panel -->
    <div class="flt-panel" id="fltPanel" aria-hidden="true">
      <form method="GET" id="filterForm">
        <div class="flt-panel-body">

          <?php foreach (array_slice($fields, 0, 3) as $f): ?>
            <?php if (in_array($f['type'], ['text','email','number','textarea'])): ?>
              <div class="flt-row">
                <label class="flt-label"><?= esc($f['field_label']) ?></label>
                <input type="text" name="<?= esc($f['field_key']) ?>"
                       value="<?= esc($filters[$f['field_key']] ?? '') ?>"
                       placeholder="Ketik…"
                       class="form-control flt-input">
              </div>
            <?php elseif ($f['type'] === 'select'): ?>
              <?php $opts = $f['options'] ? json_decode($f['options'], true) : [] ?>
              <div class="flt-row">
                <label class="flt-label"><?= esc($f['field_label']) ?></label>
                <select name="<?= esc($f['field_key']) ?>" class="form-control flt-input">
                  <option value="">Semua</option>
                  <?php foreach ($opts as $o): ?>
                    <option <?= ($filters[$f['field_key']] ?? '') === $o ? 'selected' : '' ?>>
                      <?= esc($o) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="flt-row">
            <label class="flt-label">Setor</label>
            <select name="setor" class="form-control flt-input">
              <option value="">Semua</option>
              <option value="1" <?= ($setorFilter ?? '') === '1' ? 'selected' : '' ?>>Sudah setor</option>
              <option value="0" <?= ($setorFilter ?? '') === '0' ? 'selected' : '' ?>>Belum setor</option>
            </select>
          </div>

          <div class="flt-row">
            <label class="flt-label">Akun Vendor</label>
            <select name="vendor_account_id" class="form-control flt-input">
              <option value="">Semua</option>
              <?php foreach ($vendorAccounts as $va): ?>
                <option value="<?= (int) $va['id'] ?>" <?= $vendorFilter === (string) $va['id'] ? 'selected' : '' ?>>
                  <?= esc($va['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="flt-row">
            <label class="flt-label">Progress</label>
            <select name="progress_filter" id="progressFilter" class="form-control flt-input">
              <option value="">Semua</option>
              <option value="not_started" <?= ($_GET['progress_filter'] ?? '') === 'not_started' ? 'selected' : '' ?>>Belum mulai (0%)</option>
              <option value="in_progress"  <?= ($_GET['progress_filter'] ?? '') === 'in_progress'  ? 'selected' : '' ?>>Sedang berjalan</option>
              <option value="done"         <?= ($_GET['progress_filter'] ?? '') === 'done'         ? 'selected' : '' ?>>Selesai (100%)</option>
            </select>
          </div>

          <div class="flt-row">
            <label class="flt-label">Deadline</label>
            <select name="deadline_filter" id="deadlineFilter" class="form-control flt-input">
              <option value="">Semua</option>
              <option value="overdue"     <?= ($_GET['deadline_filter'] ?? '') === 'overdue'     ? 'selected' : '' ?>>Overdue</option>
              <option value="this_week"   <?= ($_GET['deadline_filter'] ?? '') === 'this_week'   ? 'selected' : '' ?>>Minggu ini</option>
              <option value="no_deadline" <?= ($_GET['deadline_filter'] ?? '') === 'no_deadline' ? 'selected' : '' ?>>Tanpa deadline</option>
            </select>
          </div>

        </div><!-- /flt-panel-body -->

        <div class="flt-panel-footer">
          <a href="/tasks" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-rotate-left icon-xs" aria-hidden="true"></i>
            Reset
          </a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-check icon-xs" aria-hidden="true"></i>
            Terapkan
          </button>
        </div>
      </form>
    </div><!-- /flt-panel -->
  </div><!-- /flt-wrap -->

  <!-- Bulk create -->
  <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('bulkCreateModal')" title="Bulk create">
    <i class="fa-solid fa-layer-group icon-xs" aria-hidden="true"></i>
    <span class="flt-bulk-label">Bulk</span>
  </button>

</div><!-- /filter-bar-row -->

<!-- Search result count -->
<div id="searchResultCount" class="search-result-count u-hidden"></div>

<script>
(function () {
  const btn   = document.getElementById('fltToggleBtn');
  const panel = document.getElementById('fltPanel');
  const wrap  = document.getElementById('fltWrap');
  if (!btn || !panel) return;

  function open() {
    panel.classList.add('flt-panel--open');
    btn.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
  }
  function close() {
    panel.classList.remove('flt-panel--open');
    btn.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
  }
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    panel.classList.contains('flt-panel--open') ? close() : open();
  });
  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
</script>

<!-- TASK TABLE -->
<div class="card">
  <?php if (empty($tasks)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i data-lucide="clipboard-list"></i></div>
      <div class="empty-title">Tidak ada task</div>
      <div class="empty-desc">Tambah task baru atau ubah filter.</div>
    </div>
  <?php else: ?>
    <form id="taskBulkForm" method="POST" action="/tasks/bulk" class="task-bulk-form">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" id="bulkActionHidden" value="">
      <div class="task-bulk-toolbar task-bulk-float u-hidden" id="taskBulkFloatBar">
        <label class="task-bulk-check-all">
          <input type="checkbox" id="checkAllTasks" title="Pilih semua di halaman ini" aria-label="Pilih semua">
        </label>
        <span class="task-bulk-count"><span id="taskBulkCount">0</span> dipilih</span>
        <select id="bulkActionSelect" class="form-control form-control-sm task-bulk-select">
          <option value="">— Aksi bulk —</option>
          <option value="status">Ubah status</option>
          <option value="field" <?= empty($bulkFields) ? 'disabled' : '' ?>>Ubah nilai field</option>
          <option value="delete">Hapus (arsip)</option>
        </select>
        <div id="bulkPanelStatus" class="task-bulk-panel u-hidden">
          <select name="bulk_status" class="form-control form-control-sm">
            <option value="">Status…</option>
            <option value="pending">Pending</option>
            <option value="on_progress">On progress</option>
            <option value="done">Done</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div id="bulkPanelField" class="task-bulk-panel u-hidden">
          <select name="bulk_field_key" id="bulkFieldKey" class="form-control form-control-sm">
            <option value="">Field…</option>
            <?php foreach ($bulkFields as $bf):
              $opts = $bf['options'] ?? [];
              if (is_string($opts)) {
                  $opts = json_decode($opts, true) ?: [];
              }
              if (!is_array($opts)) {
                  $opts = [];
              }
              ?>
            <option value="<?= esc($bf['field_key']) ?>"
                    data-type="<?= esc($bf['type']) ?>"
                    data-options="<?= esc(json_encode(array_values($opts)), 'attr') ?>">
              <?= esc($bf['field_label']) ?> (<?= esc($bf['field_key']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="bulk_field_value" id="bulkFieldValueText" class="form-control form-control-sm task-bulk-value-text"
                 placeholder="Nilai baru" autocomplete="off">
          <select id="bulkFieldValueSelect" class="form-control form-control-sm task-bulk-value-select u-hidden" aria-label="Pilih nilai"></select>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="taskBulkApplyBtn">Terapkan</button>
      </div>
    </form>

    <div class="table-wrap">
      <table class="task-table-fixed">
        <thead>
          <tr>
            <th class="task-bulk-col" title="Pilih baris"></th>
            <?php foreach ($tableFields as $f): ?>
              <?php $fkHead = (string) ($f['field_key'] ?? ''); ?>
              <th class="<?= $fkHead === 'date' ? 'col-date' : '' ?>"><?= esc($f['field_label']) ?></th>
            <?php endforeach; ?>
            <?php if ($showProgress): ?><th>Progress</th><?php endif; ?>
            <?php if ($showDeadline): ?><th>Deadline</th><?php endif; ?>
            <th class="text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr class="task-quick-add-trigger-row">
            <td colspan="<?= (int) $tableColspan ?>">
              <form id="quickAddTaskForm" method="POST" action="/tasks/store" class="task-quick-add-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="pending">
                <input type="hidden" name="quick_draft" value="1">
                <input type="hidden" name="vendor_account_id" value="<?= esc($vendorFilter) ?>">
                <?php foreach ($tableFields as $f): ?>
                  <?php if (($f['type'] ?? '') === 'date'): ?>
                    <input type="hidden" name="fields[<?= esc($f['field_key']) ?>]" class="task-quick-draft-date">
                  <?php endif; ?>
                <?php endforeach; ?>
                <button type="submit" id="quickAddSubmitBtn" class="task-quick-add-toggle">
                  <i class="fa-solid fa-plus icon-xs" aria-hidden="true"></i>
                  Tambah task kosong (tanggal hari ini)
                </button>
              </form>
            </td>
          </tr>
          <?php foreach ($tasks as $task): ?>
            <tr data-task-id="<?= (int) $task['id'] ?>">
              <td class="task-bulk-col">
                <input type="checkbox" form="taskBulkForm" name="task_ids[]" value="<?= (int) $task['id'] ?>"
                       class="task-bulk-check" aria-label="Pilih task #<?= (int) $task['id'] ?>">
              </td>

              <?php foreach ($tableFields as $f): ?>
                <td class="<?= (($f['field_key'] ?? '') === 'date') ? 'col-date' : '' ?>">
                  <?php
                    $fk      = $f['field_key'];
                    $val     = $task['fields'][$fk]['value'] ?? '';
                    $valUpdatedAt = $task['fields'][$fk]['updated_at'] ?? '';
                    $type    = $f['type'];
                    $dataSource = (string) ($f['data_source'] ?? 'manual');
                    $options = $f['options'] ? (is_array($f['options']) ? $f['options'] : json_decode($f['options'], true)) : [];
                    $inlineId = 'ifv_' . $task['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $fk);
                  ?>

                  <?php if ($fk === 'setor'): ?>
                    <?php
                      $isSetor = !empty($task['submission']);
                      $setorOn = $isSetor;
                      $picRaw = (string) ($task['fields']['pic_name']['value'] ?? ($task['fields']['pic']['value'] ?? ''));
                      $picOptsSetor = $userSourceOptionsByField['pic_name'] ?? ($userSourceOptionsByField['pic'] ?? []);
                      $picMapSetor = [];
                      foreach ($picOptsSetor as $optRow) {
                        $picMapSetor[(string) ($optRow['value'] ?? '')] = (string) ($optRow['label'] ?? '');
                      }
                      $setorPicVal = $picMapSetor[$picRaw] ?? $picRaw;
                      $accountRaw = (string) ($task['fields']['account']['value'] ?? '');
                      $setorAccountVal = (string) ($accountSourceLabelMap[$accountRaw] ?? $accountRaw);
                    ?>
                    <button class="setor-pill <?= $setorOn ? 'active' : '' ?>"
                            data-task-id="<?= $task['id'] ?>"
                            data-setor="<?= $setorOn ? '1' : '0' ?>"
                            data-setor-updated-at="<?= esc($valUpdatedAt) ?>"
                            data-theme="<?= esc($task['fields']['theme']['value'] ?? '') ?>"
                            data-pic="<?= esc($setorPicVal) ?>"
                            data-account="<?= esc($setorAccountVal) ?>"
                            data-date="<?= esc($task['fields']['date']['value'] ?? '') ?>"
                            title="<?= $setorOn ? 'Sudah setor — klik untuk edit' : 'Belum setor — klik untuk setor' ?>">
                      <?php if ($setorOn): ?>
                        <i class="fa-solid fa-check icon-xs" aria-hidden="true"></i>
                        Setor
                      <?php else: ?>
                        <i class="fa-solid fa-circle-exclamation icon-xs" aria-hidden="true"></i>
                        Belum
                      <?php endif; ?>
                    </button>

                  <?php elseif ($type === 'select' && !empty($options) && $dataSource !== 'account_sources'):
                    $c = $val !== '' ? optBadgeColor($val, $colorPalette) : null;
                  ?>
                    <div class="bsel"
                         data-task-id="<?= $task['id'] ?>"
                         data-field-key="<?= esc($fk) ?>"
                         data-value="<?= esc($val) ?>"
                         data-updated-at="<?= esc($valUpdatedAt) ?>"
                         data-options="<?= esc(json_encode($options)) ?>"
                         data-palette="<?= esc(json_encode(array_map(fn($o) => optBadgeColor($o, $colorPalette), $options))) ?>">
                      <span class="bsel-val"
                            style="<?= $c ? "background:{$c['bg']};color:{$c['text']}" : 'background:var(--surface-2);color:var(--text-3)' ?>">
                        <?= $c ? '<span class="bsel-dot"></span>' : '' ?>
                        <?= esc($val !== '' ? $val : '— pilih —') ?>
                        <i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>
                      </span>
                      <div class="bsel-drop"></div>
                    </div>

                  <?php elseif ($fk === 'priority' && $val !== ''): ?>
                    <span class="badge badge-<?= strtolower($val) ?>"><?= esc($val) ?></span>

                  <?php elseif (in_array($fk, ['pic_name', 'pic'], true)): ?>
                    <?php
                      $picOpts = $userSourceOptionsByField[$fk] ?? [];
                      $picMap = [];
                      foreach ($picOpts as $opt) {
                        $picMap[(string) ($opt['value'] ?? '')] = (string) ($opt['label'] ?? '');
                      }
                      $picText = trim((string) ($picMap[(string) $val] ?? $val));
                      $picInitial = $picText !== '' ? strtoupper(mb_substr($picText, 0, 1)) : '?';
                    ?>
                    <button type="button"
                            class="inline-cell-trigger pic-chip-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="user_select"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>"
                            data-options="<?= esc(json_encode($picOpts), 'attr') ?>">
                      <span class="pic-chip">
                        <span class="pic-chip-avatar"><?= esc($picInitial) ?></span>
                        <span class="pic-chip-text"><?= esc($picText !== '' ? $picText : '—') ?></span>
                      </span>
                    </button>

                  <?php elseif ($dataSource === 'account_sources'): ?>
                    <?php
                      $accountOpts = $userSourceOptionsByField[$fk] ?? [];
                      $accountMap = [];
                      foreach ($accountOpts as $opt) {
                        $accountMap[(string) ($opt['value'] ?? '')] = (string) ($opt['label'] ?? '');
                      }
                      $accountText = trim((string) ($accountMap[(string) $val] ?? $val));
                      $accountColorKey = trim((string) $val) !== '' ? (string) $val : $accountText;
                      $accColor = $accountText !== '' ? optAccountBadgeColor($accountColorKey, $colorPalette) : null;
                    ?>
                    <button type="button"
                            class="inline-cell-trigger account-chip-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="user_select"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>"
                            data-options="<?= esc(json_encode($accountOpts), 'attr') ?>">
                      <span class="bsel-val"
                            style="<?= $accColor ? "background:{$accColor['bg']};color:{$accColor['text']}" : 'background:var(--surface-2);color:var(--text-3)' ?>">
                        <?= $accColor ? '<span class="bsel-dot"></span>' : '' ?>
                        <?= esc($accountText !== '' ? $accountText : '— pilih —') ?>
                        <i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>
                      </span>
                    </button>

                  <?php elseif ($type === 'boolean'): ?>
                    <button type="button"
                            class="inline-cell-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="boolean"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>">
                      <?= ($val === '1' || $val === 'true' || $val === 'on')
                        ? '<span style="color:var(--success);display:inline-flex;align-items:center;gap:4px"><i data-lucide="check" style="width:12px;height:12px"></i> Ya</span>'
                        : '<span style="color:var(--text-3);display:inline-flex;align-items:center;gap:4px"><i data-lucide="minus" style="width:12px;height:12px"></i> Tidak</span>' ?>
                    </button>

                  <?php elseif ($type === 'date' && $val !== '' && $val !== null): ?>
                    <button type="button"
                            class="inline-cell-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="date"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>">
                      <?= esc(date('d M Y', strtotime($val))) ?>
                    </button>

                  <?php elseif ($type === 'date'): ?>
                    <button type="button"
                            class="inline-cell-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="date"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value=""
                            data-updated-at="<?= esc($valUpdatedAt) ?>">
                      <span class="text-muted">—</span>
                    </button>

                  <?php elseif ($type === 'textarea'): ?>
                    <button type="button"
                            class="inline-cell-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="textarea"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>">
                      <span title="<?= esc($val) ?>"><?= esc(mb_strimwidth($val ?? '', 0, 40, '…')) ?></span>
                    </button>

                  <?php elseif ($type === 'richtext'): ?>
                    <?php $rtBtnId = 'rt_cell_' . $task['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $fk); ?>
                    <button type="button"
                      id="<?= esc($rtBtnId) ?>"
                      class="rt-inline-btn <?= $val ? 'has-value' : '' ?>"
                      data-value="<?= esc($val) ?>"
                      onclick="openRtEditorInline(<?= (int) $task['id'] ?>, '<?= esc($fk) ?>', this.dataset.value || '', '<?= esc($rtBtnId) ?>')"
                      >
                      <i data-lucide="square-pen" class="icon-xs"></i> <?= $val ? 'Edit' : 'Add' ?>
                    </button>

                  <?php else: ?>
                    <button type="button"
                            class="inline-cell-trigger"
                            id="<?= esc($inlineId) ?>"
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-field-key="<?= esc($fk) ?>"
                            data-field-type="<?= esc($type) ?>"
                            data-field-label="<?= esc($f['field_label']) ?>"
                            data-value="<?= esc($val) ?>"
                            data-updated-at="<?= esc($valUpdatedAt) ?>">
                      <?= esc($val !== '' ? mb_strimwidth($val, 0, 40, '…') : '—') ?>
                    </button>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>

              <?php
                $prog     = (int) ($task['progress'] ?? 0);
                $deadline = $task['deadline'] ?? '';
                $dlClass  = '';
                $dlLabel  = '—';
                if ($deadline) {
                    $dlTs    = strtotime($deadline);
                    $diffDays = (int) (($dlTs - strtotime($today)) / 86400);
                    if ($diffDays < 0)     { $dlClass = 'dl-overdue';  $dlLabel = 'Overdue'; }
                    elseif ($diffDays <= 3){ $dlClass = 'dl-urgent';   $dlLabel = date('d M', $dlTs); }
                    elseif ($diffDays <= 7){ $dlClass = 'dl-soon';     $dlLabel = date('d M', $dlTs); }
                    else                   { $dlClass = 'dl-ok';       $dlLabel = date('d M Y', $dlTs); }
                }
              ?>

              <!-- Progress cell -->
              <?php if ($showProgress): ?>
              <td>
                <div class="task-progress-cell"
                     data-task-id="<?= (int) $task['id'] ?>"
                     data-progress="<?= $prog ?>"
                     title="Progress: <?= $prog ?>% — klik untuk ubah">
                  <div class="task-progress-bar">
                    <div class="task-progress-fill" style="width:<?= $prog ?>%"></div>
                  </div>
                  <span class="task-progress-label"><?= $prog ?>%</span>
                </div>
              </td>
              <?php endif; ?>

              <!-- Deadline cell -->
              <?php if ($showDeadline): ?>
              <td>
                <button type="button"
                        class="task-deadline-btn <?= $dlClass ?>"
                        data-task-id="<?= (int) $task['id'] ?>"
                        data-deadline="<?= esc($deadline) ?>"
                        title="<?= $deadline ? esc(date('d M Y', strtotime($deadline))) : 'Klik untuk set deadline' ?>">
                  <?php if ($deadline): ?>
                    <i class="fa-solid fa-calendar-days icon-xs" aria-hidden="true"></i>
                    <?= esc($dlLabel) ?>
                  <?php else: ?>
                    <span class="dl-empty"><i class="fa-regular fa-calendar icon-xs" aria-hidden="true"></i> —</span>
                  <?php endif; ?>
                </button>
              </td>
              <?php endif; ?>

              <td class="action-cell">
                <button type="button" class="btn-icon duplicate-task-btn" data-task-id="<?= (int) $task['id'] ?>" title="Duplikat task">
                  <i class="fa-solid fa-copy" aria-hidden="true"></i>
                </button>
                <form method="POST" action="/tasks/<?= $task['id'] ?>/delete" class="inline-form"
                      data-confirm="Hapus task ini?"
                      data-confirm-title="Hapus task?"
                      data-confirm-ok-text="Hapus"
                      data-confirm-ok-variant="danger">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn-icon btn-icon-danger" title="Hapus">
                    <i data-lucide="trash-2"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($pager)): ?>
      <?= view('components/table_pagination', [
          'pager'        => $pager,
          'queryParams'  => $pagerQuery ?? [],
          'uriPath'      => $pagerUriPath ?? '/tasks',
      ]) ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Inline Field Modal -->
<div id="inlineFieldModal" class="overlay-fixed overlay-dark">
  <div class="modal modal-inline-field">
    <div class="modal-header">
      <h3 id="ifmTitle" class="modal-title">Edit Field</h3>
      <button id="ifmClose" class="btn-icon btn-icon-md"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="modal-body">
      <div id="ifmInputWrap"></div>
    </div>
    <div class="modal-footer">
      <button id="ifmCancel" class="btn btn-ghost">Batal</button>
      <button id="ifmSave" class="btn btn-primary">Simpan</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- ADD TASK MODAL                                          -->
<!-- ═══════════════════════════════════════════════════════ -->
<div id="addTaskModal" class="modal-overlay modal-z-900">
  <div class="modal modal-task">
    <div class="modal-header">
      <h3 class="modal-title" id="taskModalTitle">Tambah Task Baru</h3>
      <button class="btn-icon btn-icon-md" onclick="closeTaskModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>

    <form method="POST" action="/tasks/store" id="addTaskForm">
      <?= csrf_field() ?>
      <input type="hidden" name="form_context" value="internal">
      <div class="modal-body modal-scroll">

        <input type="hidden" name="status" value="pending" id="atm-status">

        <div class="form-group">
          <label class="form-label">Akun Vendor</label>
          <select name="vendor_account_id" class="form-control">
            <option value="">Tanpa akun vendor</option>
            <?php foreach ($vendorAccounts as $va): ?>
              <option value="<?= (int) $va['id'] ?>" <?= $vendorFilter === (string) $va['id'] ? 'selected' : '' ?>>
                <?= esc($va['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php
        $dbTaskModal = \Config\Database::connect();
        if ($dbTaskModal->fieldExists('parent_id', 'tb_task')): ?>
        <div class="form-group">
          <label class="form-label">Parent task ID (sub-task, opsional)</label>
          <input type="number" name="parent_id" id="atm-parent-id" class="form-control" min="1" placeholder="Kosongkan jika task utama">
        </div>
        <?php endif; ?>

        <!-- Dynamic Fields -->
        <div class="form-grid">
          <?php foreach ($fields as $f):
            $key    = $f['field_key'];
            $type   = $f['type'];
            $ph     = esc($f['placeholder'] ?? '');
            $req    = $f['is_required'] ? 'required' : '';
            $reqStr = $f['is_required'] ? ' <span class="req">*</span>' : '';
            $span   = $type === 'textarea' ? 'grid-column:1/-1' : '';
            $syncHint = !empty($f['submission_col'])
              ? '<span style="font-size:10px;color:var(--success);margin-left:4px;display:inline-flex;align-items:center;gap:3px" title="Sync ke tb_submissions.'.$f['submission_col'].'"><i data-lucide="refresh-cw" style="width:10px;height:10px"></i> setor</span>'
              : '';
          ?>
            <div class="form-group" style="<?= $span ?>">
              <label class="form-label"><?= esc($f['field_label']) ?><?= $reqStr ?><?= $syncHint ?></label>

              <?php if (in_array($type, ['text','email','number'])): ?>
                <?php if (($f['data_source'] ?? 'manual') === 'team_users'): ?>
                  <?php $userOpts = $userSourceOptionsByField[$key] ?? []; ?>
                  <select name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="user_select" class="form-control" <?= $req ?>>
                    <option value=""><?= $ph ?: 'Pilih '.esc($f['field_label']) ?></option>
                    <?php foreach ($userOpts as $uOpt): ?>
                      <option value="<?= esc((string) ($uOpt['value'] ?? '')) ?>"><?= esc((string) ($uOpt['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif (($f['data_source'] ?? 'manual') === 'account_sources'): ?>
                  <?php $accountOpts = $userSourceOptionsByField[$key] ?? []; ?>
                  <?php
                    $groupedAccountOpts = [];
                    foreach ($accountOpts as $accOpt) {
                      $group = trim((string) ($accOpt['group'] ?? 'Lainnya'));
                      if ($group === '') {
                        $group = 'Lainnya';
                      }
                      $groupedAccountOpts[$group][] = $accOpt;
                    }
                  ?>
                  <select name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="user_select" class="form-control" <?= $req ?>>
                    <option value=""><?= $ph ?: 'Pilih '.esc($f['field_label']) ?></option>
                    <?php foreach ($groupedAccountOpts as $groupLabel => $groupOptions): ?>
                      <optgroup label="<?= esc($groupLabel) ?>">
                        <?php foreach ($groupOptions as $accOpt): ?>
                          <option value="<?= esc((string) ($accOpt['value'] ?? '')) ?>"><?= esc((string) ($accOpt['label'] ?? '')) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input type="<?= $type ?>" name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="<?= esc($type) ?>" placeholder="<?= $ph ?>" class="form-control" <?= $req ?>>
                <?php endif; ?>

              <?php elseif ($type === 'date'): ?>
                <input type="date" name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="date" class="form-control" <?= $req ?>>

              <?php elseif ($type === 'select'): ?>
                <?php if (($f['data_source'] ?? 'manual') === 'account_sources'): ?>
                  <?php $accountOpts = $userSourceOptionsByField[$key] ?? []; ?>
                  <?php
                    $groupedAccountOpts = [];
                    foreach ($accountOpts as $accOpt) {
                      $group = trim((string) ($accOpt['group'] ?? 'Lainnya'));
                      if ($group === '') {
                        $group = 'Lainnya';
                      }
                      $groupedAccountOpts[$group][] = $accOpt;
                    }
                  ?>
                  <select name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="user_select" class="form-control" <?= $req ?>>
                    <option value=""><?= $ph ?: 'Pilih '.esc($f['field_label']) ?></option>
                    <?php foreach ($groupedAccountOpts as $groupLabel => $groupOptions): ?>
                      <optgroup label="<?= esc($groupLabel) ?>">
                        <?php foreach ($groupOptions as $accOpt): ?>
                          <option value="<?= esc((string) ($accOpt['value'] ?? '')) ?>"><?= esc((string) ($accOpt['label'] ?? '')) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <select name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="select" class="form-control" <?= $req ?>>
                    <option value=""><?= $ph ?: 'Pilih '.esc($f['field_label']) ?></option>
                    <?php foreach ($f['options_array'] ?? [] as $opt): ?>
                      <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>

              <?php elseif ($type === 'textarea'): ?>
                <textarea name="fields[<?= $key ?>]" data-field-key="<?= esc($key) ?>" data-field-type="textarea" placeholder="<?= $ph ?>" class="form-control" <?= $req ?>></textarea>

              <?php elseif ($type === 'boolean'): ?>
                <div class="pt-4">
                  <?php if ($key === 'setor'): ?>
                    <label class="form-check">
                      <input type="hidden" name="fields[<?= $key ?>]" value="0">
                      <input type="checkbox" name="fields[<?= $key ?>]" value="1" id="atm-setor-cb" data-field-key="<?= esc($key) ?>" data-field-type="boolean">
                      <span class="font-medium">Setor ke Submission</span>
                    </label>
                  <?php else: ?>
                    <label class="form-check">
                      <input type="hidden" name="fields[<?= $key ?>]" value="0">
                      <input type="checkbox" name="fields[<?= $key ?>]" value="1" data-field-key="<?= esc($key) ?>" data-field-type="boolean">
                      <span><?= esc($f['field_label']) ?></span>
                    </label>
                  <?php endif; ?>
                </div>

              <?php elseif ($type === 'richtext'): ?>
                <?php $rtId = 'atm_rt_' . $key; ?>
                <input type="hidden" name="fields[<?= $key ?>]" id="<?= $rtId ?>" data-field-key="<?= esc($key) ?>" data-field-type="richtext" value="">
                <div class="rt-preview"
                     onclick="openRtEditor('<?= $key ?>', '<?= $rtId ?>')"
                     id="<?= $rtId ?>_preview" data-preview-key="<?= esc($key) ?>">
                  Klik untuk buka editor…
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeTaskModal()">Batal</button>
        <button type="submit" class="btn btn-primary" id="atm-submit">
          <i class="fa-solid fa-check icon-xs" aria-hidden="true"></i>
          <span id="atm-submit-text">Simpan Task</span>
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════ -->
<!-- SETOR MODAL                                             -->

<!-- ═══════════════════════════════════════════════════════ -->
<div id="setorModal" class="overlay-fixed overlay-dark">
  <div class="modal modal-setor">

    <!-- Header -->
    <div class="modal-header">
      <h3 id="setorModalTitle" class="modal-title">Edit Submission</h3>
      <button id="setorModalClose" class="btn-icon btn-icon-md">
        <i data-lucide="x" class="icon-sm"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="modal-body">
      <!-- Row 1: Product Name + Submission Link -->
      <div class="form-grid mb-14">
        <div class="form-group mb-0">
          <label class="form-label">Product Name</label>
          <input id="setorProductName" type="text" placeholder="Product name…" class="form-control">
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Submission Link</label>
          <input id="setorLink" type="url" placeholder="https://…" class="form-control">
        </div>
      </div>

      <!-- Row 2: Category -->
      <div class="form-group mb-18">
        <label class="form-label">Category</label>
        <input id="setorCategory" type="text" placeholder="Category…" class="form-control">
      </div>

      <!-- Row 3: Account | PIC | Date (read-only) -->
      <div class="setor-meta-grid">
        <div>
          <div class="stat-label">Account</div>
          <div id="setorAccount" class="setor-meta-value">—</div>
        </div>
        <div>
          <div class="stat-label">PIC</div>
          <div id="setorPic" class="setor-meta-value">—</div>
        </div>
        <div>
          <div class="stat-label">Date</div>
          <div id="setorDate" class="setor-meta-value">—</div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="modal-footer">
      <button id="setorCancelBtn" class="btn btn-ghost">Cancel</button>
      <button id="setorUnsetor" class="btn btn-danger hidden">Un-Setor</button>
      <button id="setorSubmitBtn" class="btn btn-primary">Update</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- BULK CREATE MODAL                                         -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="bulkCreateModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <span class="modal-title">
        <i class="fa-solid fa-layer-group icon-xs" aria-hidden="true"></i>
        Bulk Create Task
      </span>
      <button class="btn-icon" onclick="closeModal('bulkCreateModal')">
        <i class="fa-solid fa-xmark u-icon-sm" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--text-2);margin-bottom:12px">
        Masukkan satu nama task per baris.
        <?php if ($titleField): ?>
          Setiap baris akan mengisi field <strong><?= esc($titleField['field_label']) ?></strong>.
        <?php endif; ?>
      </p>
      <div class="form-group">
        <label class="form-label">Daftar Task <span class="req">*</span></label>
        <textarea id="bulkCreateLines" class="form-control" rows="8"
                  placeholder="Task pertama&#10;Task kedua&#10;Task ketiga&#10;…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Status Awal</label>
        <select id="bulkCreateStatus" class="form-control">
          <option value="pending">Pending</option>
          <option value="on_progress">On Progress</option>
        </select>
      </div>
      <div id="bulkCreateResult" class="u-hidden" style="font-size:12px;color:var(--success);margin-top:8px"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('bulkCreateModal')">Batal</button>
      <button type="button" class="btn btn-primary" id="bulkCreateSubmit">
        <i class="fa-solid fa-plus icon-xs" aria-hidden="true"></i>
        Buat Semua
      </button>
    </div>
  </div>
</div>

<!-- Core Field Inline Popover (progress / deadline) -->
<div id="corePopover" style="display:none;position:fixed;z-index:3000;
     background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
     box-shadow:var(--shadow-md);padding:14px 16px;min-width:220px;max-width:260px">
  <div id="corePopLabel" style="font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:10px"></div>
  <div id="corePopInputWrap"></div>
  <div style="display:flex;gap:6px;margin-top:12px;justify-content:flex-end">
    <button class="btn btn-ghost btn-sm" id="corePopCancel">Batal</button>
    <button class="btn btn-primary btn-sm" id="corePopSave">Simpan</button>
  </div>
</div>

<script>
// ── Badge-Select custom dropdown ──────────────────────────────────────
let _activeBsel = null;
let _activeInlinePicDrop = null;
const _fieldUpdateQueues = new Map();
const _fieldPendingValues = new Map();
const _fieldVersions = new Map();

function _applyCsrfFromResponse(data) {
  if (data?.csrf) {
    if (typeof updateAppCsrf === 'function') updateAppCsrf(data.csrf);
    else if (window.appCsrf) window.appCsrf.val = data.csrf;
  }
}

async function _postJson(url, payload) {
  const csrf = (typeof getAppCsrf === 'function')
    ? getAppCsrf()
    : (window.appCsrf || { key: '<?= csrf_token() ?>', val: '<?= csrf_hash() ?>' });
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ ...payload, [csrf.key]: csrf.val }),
  });
  let data = null;
  try { data = await res.json(); } catch (e) {}
  if (!res.ok) {
    const err = new Error(data?.message || `HTTP ${res.status}`);
    err.payload = data;
    throw err;
  }
  _applyCsrfFromResponse(data);
  return data;
}

function _queueFieldUpdate(taskId, fieldKey, value) {
  const qKey = `${taskId}::${fieldKey}`;
  _fieldPendingValues.set(qKey, value);
  if (_fieldUpdateQueues.get(qKey)) return _fieldUpdateQueues.get(qKey);

  const run = async () => {
    let lastResult = null;
    while (_fieldPendingValues.has(qKey)) {
      const nextValue = _fieldPendingValues.get(qKey);
      _fieldPendingValues.delete(qKey);
      lastResult = await _postJson(`/tasks/${taskId}/field-update`, {
        field_key: fieldKey,
        value: nextValue,
        expected_updated_at: _fieldVersions.get(qKey) || null,
      });
      if (lastResult?.server_updated_at) _fieldVersions.set(qKey, lastResult.server_updated_at);
    }
    _fieldUpdateQueues.delete(qKey);
    return lastResult;
  };

  const promise = run().catch((err) => {
    _fieldUpdateQueues.delete(qKey);
    throw err;
  });
  _fieldUpdateQueues.set(qKey, promise);
  return promise;
}

function _positionFixedDropdown(anchorEl, dropEl) {
  if (!anchorEl || !dropEl) return;
  const rect = anchorEl.getBoundingClientRect();
  dropEl.style.visibility = 'hidden';
  dropEl.classList.add('open');

  const desiredHeight = Math.min(dropEl.scrollHeight || 160, 240);
  const desiredWidth = Math.max(dropEl.offsetWidth || 150, rect.width);
  const viewportW = window.innerWidth;
  const viewportH = window.innerHeight;
  const gap = 6;
  const spaceBelow = viewportH - rect.bottom - gap;
  const spaceAbove = rect.top - gap;
  const openUp = spaceBelow < Math.min(140, desiredHeight) && spaceAbove > spaceBelow;

  let top = openUp ? (rect.top - desiredHeight - gap) : (rect.bottom + gap);
  let left = rect.left;

  if (left + desiredWidth > viewportW - 8) left = Math.max(8, viewportW - desiredWidth - 8);
  if (top < 8) top = 8;
  if (top + desiredHeight > viewportH - 8) top = Math.max(8, viewportH - desiredHeight - 8);

  const maxHeight = openUp ? Math.max(120, spaceAbove - 8) : Math.max(120, spaceBelow - 8);
  dropEl.style.top = `${Math.round(top)}px`;
  dropEl.style.left = `${Math.round(left)}px`;
  dropEl.style.minWidth = `${Math.round(Math.max(130, rect.width))}px`;
  dropEl.style.maxHeight = `${Math.round(Math.min(260, maxHeight))}px`;
  dropEl.style.visibility = '';
}

function _truncateText(val, max = 40) {
  const str = String(val ?? '');
  if (str.length <= max) return str;
  return str.slice(0, max) + '…';
}

function _renderPicChip(value) {
  const raw = String(value ?? '').trim();
  const initial = raw ? raw.charAt(0).toUpperCase() : '?';
  const safeText = _ifmEsc(raw || '—');
  const safeInitial = _ifmEsc(initial);
  return `<span class="pic-chip"><span class="pic-chip-avatar">${safeInitial}</span><span class="pic-chip-text">${safeText}</span></span>`;
}

function _pickUserLabel(options, value) {
  const val = String(value ?? '');
  if (!Array.isArray(options) || val === '') return val;
  const found = options.find((o) => String(o?.value ?? '') === val);
  return found?.label || val;
}

function _renderInlineSelectValue(btn, value) {
  const options = JSON.parse(btn?.dataset?.options || '[]');
  const text = _pickUserLabel(options, value);
  if (btn?.classList?.contains('pic-chip-trigger')) {
    return _renderPicChip(text);
  }
  if (btn?.classList?.contains('account-chip-trigger')) {
    const raw = String(text || '').trim();
    if (!raw) return `<span class="bsel-val" style="background:var(--surface-2);color:var(--text-3)">— pilih —<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i></span>`;
    const palette = Array.from({ length: 12 }).map((_, i) => ({
      bg: `var(--badge-${i + 1}-bg)`,
      text: `var(--badge-${i + 1}-text)`,
    }));
    let h = 0;
    for (let i = 0; i < raw.length; i++) h = (h * 31 + raw.charCodeAt(i)) % palette.length;
    const p = palette[h];
    return `<span class="bsel-val" style="background:${p.bg};color:${p.text}">${_ifmEsc(raw)}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i></span>`;
  }
  return _ifmEsc(text || '—');
}

async function _openInlineUserSelect(btn) {
  if (!btn) return;
  if (_activeInlinePicDrop && _activeInlinePicDrop.btn === btn) {
    closeBselDrop();
    return;
  }
  if (btn.dataset.editing === '1') return;
  closeBselDrop();
  const options = JSON.parse(btn.dataset.options || '[]');
  const currentValue = String(btn.dataset.value ?? '');
  btn.dataset.editing = '1';

  const drop = document.createElement('div');
  drop.className = 'bsel-drop';
  const rows = [{ value: '', label: '— pilih —' }, ...options];
  rows.forEach((row) => {
    const val = String(row?.value ?? '');
    const label = String(row?.label ?? row?.value ?? '');
    const item = document.createElement('div');
    item.className = 'bsel-opt';
    item.dataset.val = val;
    item.style.cssText = 'background:var(--surface-2);color:var(--text)';
    item.innerHTML = `<span class="bsel-opt-dot"></span>${_ifmEsc(label)}`;
    if (val === currentValue) item.style.outline = '2px solid var(--accent-2)';
    drop.appendChild(item);
  });
  document.body.appendChild(drop);
  _positionFixedDropdown(btn, drop);
  _activeInlinePicDrop = { btn, drop };

  const applySelection = async (newVal) => {
    if (newVal === currentValue) {
      closeBselDrop();
      return;
    }
    try {
      const qKey = `${btn.dataset.taskId}::${btn.dataset.fieldKey}`;
      if (!_fieldVersions.has(qKey)) _fieldVersions.set(qKey, btn.dataset.updatedAt || null);
      const data = await _queueFieldUpdate(btn.dataset.taskId, btn.dataset.fieldKey, newVal);
      if (!data?.success) {
        showToast('Gagal simpan', 'error');
        btn.dataset.value = currentValue;
      } else {
        btn.dataset.value = newVal;
        if (data?.server_updated_at) {
          btn.dataset.updatedAt = data.server_updated_at;
        }
        const savedLabel = btn.dataset.fieldLabel || btn.dataset.fieldKey || 'Field';
        showToast(`${savedLabel} disimpan`, 'success');
      }
    } catch (e) {
      if (e?.payload?.conflict) {
        await refreshTaskRow(btn.dataset.taskId);
        showToast('Data diubah user lain. Nilai terbaru dipakai.', 'error');
      } else {
        showToast('Network error', 'error');
      }
      btn.dataset.value = currentValue;
    } finally {
      closeBselDrop();
    }
  };

  drop.querySelectorAll('.bsel-opt').forEach((opt) => {
    opt.addEventListener('click', (e) => {
      e.stopPropagation();
      applySelection(String(opt.dataset.val ?? ''));
    });
  });
}

function _setSetorPillFromTask(row, task) {
  const pill = row.querySelector('.setor-pill');
  if (!pill) return;
  const fields = task?.fields || {};
  const isSetor = !!task?.submission;
  const setorUpdatedAt = fields?.setor?.updated_at || '';
  pill.dataset.setor = isSetor ? '1' : '0';
  pill.dataset.setorUpdatedAt = setorUpdatedAt;
  pill.dataset.theme = fields?.theme?.value || '';
  const picRaw = fields?.pic_name?.value || fields?.pic?.value || '';
  const picBtn = row.querySelector('.inline-cell-trigger[data-field-key="pic_name"], .inline-cell-trigger[data-field-key="pic"]');
  const picOptions = JSON.parse(picBtn?.dataset?.options || '[]');
  pill.dataset.pic = _pickUserLabel(picOptions, picRaw);
  const accountRaw = fields?.account?.value || '';
  const accountBtn = row.querySelector('.inline-cell-trigger[data-field-key="account"]');
  const accountOptions = JSON.parse(accountBtn?.dataset?.options || '[]');
  pill.dataset.account = _pickUserLabel(accountOptions, accountRaw);
  pill.dataset.date = fields?.date?.value || '';
  pill.classList.toggle('active', isSetor);
  pill.title = isSetor ? 'Sudah setor — klik untuk edit/batalkan' : 'Belum setor — klik untuk setor';
  pill.innerHTML = isSetor
    ? '<i class="fa-solid fa-check icon-xs" aria-hidden="true"></i> Setor'
    : '<i class="fa-solid fa-circle-exclamation icon-xs" aria-hidden="true"></i> Belum';
}

function _refreshBselFromTask(row, fields) {
  row.querySelectorAll('.bsel').forEach((bsel) => {
    const fieldKey = bsel.dataset.fieldKey;
    const field = fields?.[fieldKey] || {};
    const value = field?.value || '';
    const updatedAt = field?.updated_at || '';
    const valEl = bsel.querySelector('.bsel-val');
    const options = JSON.parse(bsel.dataset.options || '[]');
    const palettes = JSON.parse(bsel.dataset.palette || '[]');
    const idx = options.findIndex((o) => o === value);
    const p = idx >= 0 ? (palettes[idx] || { bg:'#f5f5f5', text:'#555' }) : null;

    bsel.dataset.value = value;
    bsel.dataset.updatedAt = updatedAt;
    _fieldVersions.set(`${bsel.dataset.taskId}::${fieldKey}`, updatedAt || null);
    valEl.style.background = p ? p.bg : 'var(--surface-2)';
    valEl.style.color = p ? p.text : 'var(--text-3)';
    valEl.innerHTML = `${p ? '<span class="bsel-dot"></span>' : ''}${value || '— pilih —'}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>`;

    const opts = bsel.querySelectorAll('.bsel-opt');
    opts.forEach((o) => { o.style.outline = ''; });
    if (idx >= 0 && opts[idx]) opts[idx].style.outline = `2px solid ${p.text}33`;
  });
}

function _refreshInlineButtonsFromTask(row, fields) {
  row.querySelectorAll('.inline-cell-trigger').forEach((btn) => {
    const key = btn.dataset.fieldKey;
    const type = btn.dataset.fieldType || 'text';
    const field = fields?.[key] || {};
    const value = field?.value || '';
    const updatedAt = field?.updated_at || '';
    btn.dataset.value = value;
    btn.dataset.updatedAt = updatedAt;
    _fieldVersions.set(`${btn.dataset.taskId}::${key}`, updatedAt || null);

    if (type === 'date') {
      btn.innerHTML = value ? _ifmFormatDate(value) : '<span class="text-muted">—</span>';
      return;
    }
    if (type === 'user_select') {
      btn.innerHTML = _renderInlineSelectValue(btn, value);
      return;
    }
    if (type === 'textarea') {
      btn.innerHTML = `<span title="${_ifmEsc(value)}">${_ifmEsc(_truncateText(value, 40) || '—')}</span>`;
      return;
    }
    if (type === 'boolean') {
      btn.innerHTML = _ifmRenderValue('boolean', value);
      return;
    }
    btn.textContent = value ? _truncateText(value, 40) : '—';
  });
  if (typeof refreshLucide === 'function') refreshLucide(row);
}

function _refreshRichtextButtonsFromTask(row, fields) {
  row.querySelectorAll('.rt-inline-btn').forEach((btn) => {
    const keyRaw = btn.getAttribute('onclick') || '';
    const m = keyRaw.match(/,\s*'([^']+)'/);
    if (!m) return;
    const fieldKey = m[1];
    const value = fields?.[fieldKey]?.value || '';
    btn.dataset.value = value;
    btn.classList.toggle('has-value', !!value);
    btn.innerHTML = `<i data-lucide="square-pen" class="icon-xs"></i> ${value ? 'Edit' : 'Add'}`;
  });
  if (typeof refreshLucide === 'function') refreshLucide(row);
}

async function refreshTaskRow(taskId) {
  const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
  if (!row) return;
  const res = await fetch(`/tasks/${taskId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const data = await res.json();
  if (!data?.success || !data?.task) throw new Error('Gagal mengambil data task terbaru');
  const task = data.task;
  const fields = task.fields || {};
  _setSetorPillFromTask(row, task);
  _refreshBselFromTask(row, fields);
  _refreshInlineButtonsFromTask(row, fields);
  _refreshRichtextButtonsFromTask(row, fields);
}

function closeBselDrop() {
  if (_activeBsel) {
    _activeBsel.querySelector('.bsel-drop').classList.remove('open');
    _activeBsel = null;
  }
  if (_activeInlinePicDrop) {
    const { btn, drop } = _activeInlinePicDrop;
    if (drop?.parentNode) drop.parentNode.removeChild(drop);
    if (btn) {
      btn.dataset.editing = '0';
      btn.innerHTML = _renderInlineSelectValue(btn, btn.dataset.value ?? '');
    }
    _activeInlinePicDrop = null;
  }
}

document.addEventListener('click', e => {
  const inBsel = _activeBsel && _activeBsel.contains(e.target);
  const inPicDrop = _activeInlinePicDrop?.drop?.contains(e.target);
  const onPicBtn = _activeInlinePicDrop?.btn?.contains(e.target);
  if (!inBsel && !inPicDrop && !onPicBtn) closeBselDrop();
});

document.querySelectorAll('.bsel').forEach(bsel => {
  const val0     = bsel.dataset.value;
  const options  = JSON.parse(bsel.dataset.options || '[]');
  const palettes = JSON.parse(bsel.dataset.palette || '[]');
  const drop     = bsel.querySelector('.bsel-drop');
  const valEl    = bsel.querySelector('.bsel-val');

  // Build option list once
  options.forEach((opt, i) => {
    const p   = palettes[i] || { bg:'#f5f5f5', text:'#555' };
    const div = document.createElement('div');
    div.className = 'bsel-opt';
    div.dataset.val = opt;
    div.style.cssText = `background:${p.bg};color:${p.text}`;
    div.innerHTML = `<span class="bsel-opt-dot"></span>${opt}`;
    if (opt === val0) div.style.outline = `2px solid ${p.text}33`;
    drop.appendChild(div);
  });

  // Toggle dropdown
  valEl.addEventListener('click', e => {
    e.stopPropagation();
    const isOpen = drop.classList.contains('open');
    closeBselDrop();
    if (isOpen) return;
    _positionFixedDropdown(valEl, drop);
    drop.classList.add('open');
    _activeBsel = bsel;
  });

  // Option click → AJAX save
  drop.querySelectorAll('.bsel-opt').forEach((opt, i) => {
    opt.addEventListener('click', async e => {
      e.stopPropagation();
      const prevVal = bsel.dataset.value || '';
      const newVal  = opt.dataset.val;
      const p       = palettes[i] || { bg:'#f5f5f5', text:'#555' };
      const taskId  = bsel.dataset.taskId;
      const fieldKey = bsel.dataset.fieldKey;
      const qKey = `${taskId}::${fieldKey}`;
      if (!_fieldVersions.has(qKey)) _fieldVersions.set(qKey, bsel.dataset.updatedAt || null);
      closeBselDrop();

      // Update badge display
      valEl.style.background = p.bg;
      valEl.style.color = p.text;
      valEl.innerHTML = `<span class="bsel-dot"></span>${newVal}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>`;
      bsel.dataset.value = newVal;

      // Remove highlight from all, add to selected
      drop.querySelectorAll('.bsel-opt').forEach(o => o.style.outline = '');
      opt.style.outline = `2px solid ${p.text}33`;

      try {
        const data = await _queueFieldUpdate(taskId, fieldKey, newVal);
        if (data?.server_updated_at) bsel.dataset.updatedAt = data.server_updated_at;
        showToast(data.success ? `${fieldKey} disimpan` : 'Gagal simpan', data.success ? 'success' : 'error');
      } catch(e) {
        const payload = e?.payload || {};
        if (payload?.conflict) {
          await refreshTaskRow(taskId);
          showToast('Data berubah oleh user lain. Nilai terbaru ditampilkan.', 'error');
          return;
        }
        // Revert UI when save failed.
        const prevIndex = options.findIndex((v) => v === prevVal);
        const prevPalette = palettes[prevIndex] || { bg: 'var(--surface-2)', text: 'var(--text-3)' };
        valEl.style.background = prevVal ? prevPalette.bg : 'var(--surface-2)';
        valEl.style.color = prevVal ? prevPalette.text : 'var(--text-3)';
        valEl.innerHTML = `${prevVal ? '<span class="bsel-dot"></span>' : ''}${prevVal || '— pilih —'}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>`;
        bsel.dataset.value = prevVal;
        drop.querySelectorAll('.bsel-opt').forEach(o => o.style.outline = '');
        if (prevIndex >= 0) drop.querySelectorAll('.bsel-opt')[prevIndex].style.outline = `2px solid ${prevPalette.text}33`;
        showToast('Gagal simpan, coba lagi', 'error');
      }
    });
  });
});

// ── Generic inline field edit modal ─────────────────────────────
const ifm = document.getElementById('inlineFieldModal');
const ifmClose = document.getElementById('ifmClose');
const ifmCancel = document.getElementById('ifmCancel');
const ifmSave = document.getElementById('ifmSave');
const ifmTitle = document.getElementById('ifmTitle');
const ifmInputWrap = document.getElementById('ifmInputWrap');
let _ifmState = null;

function _ifmOpen() { ifm.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function _ifmClose() { ifm.style.display = 'none'; document.body.style.overflow = ''; }
function _ifmEsc(text) {
  const d = document.createElement('div');
  d.textContent = text ?? '';
  return d.innerHTML;
}
function _ifmFormatDate(raw) {
  if (!raw) return '—';
  const dt = new Date(raw + 'T00:00:00');
  if (Number.isNaN(dt.getTime())) return raw;
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
function _ifmRenderValue(type, value) {
  if (type === 'date') return _ifmFormatDate(value);
  if (type === 'boolean') return (value === '1' || value === 'true' || value === 'on')
    ? '<span style="color:var(--success);display:inline-flex;align-items:center;gap:4px"><i data-lucide="check" style="width:12px;height:12px"></i> Ya</span>'
    : '<span style="color:var(--text-3);display:inline-flex;align-items:center;gap:4px"><i data-lucide="minus" style="width:12px;height:12px"></i> Tidak</span>';
  if (!value) return '—';
  return _ifmEsc(String(value).length > 40 ? String(value).slice(0, 40) + '…' : value);
}

document.querySelectorAll('.inline-cell-trigger').forEach(btn => {
  btn.addEventListener('click', () => {
    if ((btn.dataset.fieldType || '') === 'user_select') {
      _openInlineUserSelect(btn);
      return;
    }
    _ifmState = {
      taskId: btn.dataset.taskId,
      fieldKey: btn.dataset.fieldKey,
      type: btn.dataset.fieldType || 'text',
      label: btn.dataset.fieldLabel || btn.dataset.fieldKey,
      value: btn.dataset.value ?? '',
      updatedAt: btn.dataset.updatedAt || null,
      el: btn,
    };
    ifmTitle.textContent = `Edit ${_ifmState.label}`;

    if (_ifmState.type === 'textarea') {
      ifmInputWrap.innerHTML = `<textarea id="ifmValue" class="form-control ifm-textarea">${_ifmEsc(_ifmState.value)}</textarea>`;
    } else if (_ifmState.type === 'user_select') {
      const options = JSON.parse(btn.dataset.options || '[]');
      const optsHtml = options.map((o) => {
        const val = String(o?.value ?? '');
        const selected = val === String(_ifmState.value ?? '') ? 'selected' : '';
        return `<option value="${_ifmEsc(val)}" ${selected}>${_ifmEsc(String(o?.label ?? val))}</option>`;
      }).join('');
      ifmInputWrap.innerHTML = `<select id="ifmValue" class="form-control"><option value="">— pilih —</option>${optsHtml}</select>`;
    } else if (_ifmState.type === 'boolean') {
      const yes = (_ifmState.value === '1' || _ifmState.value === 'true' || _ifmState.value === 'on') ? 'selected' : '';
      const no  = yes ? '' : 'selected';
      ifmInputWrap.innerHTML = `<select id="ifmValue" class="form-control"><option value="1" ${yes}>Ya</option><option value="0" ${no}>Tidak</option></select>`;
    } else {
      const inputType = ['date', 'number', 'email'].includes(_ifmState.type) ? _ifmState.type : 'text';
      ifmInputWrap.innerHTML = `<input id="ifmValue" type="${inputType}" class="form-control" value="${_ifmEsc(_ifmState.value)}">`;
    }
    _ifmOpen();
    setTimeout(() => document.getElementById('ifmValue')?.focus(), 20);
  });
});

ifmClose.addEventListener('click', _ifmClose);
ifmCancel.addEventListener('click', _ifmClose);
ifm.addEventListener('click', e => { if (e.target === ifm) _ifmClose(); });

ifmSave.addEventListener('click', async () => {
  if (!_ifmState) return;
  const inp = document.getElementById('ifmValue');
  if (!inp) return;
  const newVal = inp.value ?? '';

  ifmSave.disabled = true;
  ifmSave.textContent = 'Menyimpan...';
  try {
    const qKey = `${_ifmState.taskId}::${_ifmState.fieldKey}`;
    if (!_fieldVersions.has(qKey)) _fieldVersions.set(qKey, _ifmState.updatedAt || null);
    const data = await _queueFieldUpdate(_ifmState.taskId, _ifmState.fieldKey, newVal);
    if (!data?.success) {
      showToast('Gagal simpan', 'error');
      return;
    }

    _ifmState.el.dataset.value = newVal;
    if (data?.server_updated_at) {
      _ifmState.el.dataset.updatedAt = data.server_updated_at;
      _ifmState.updatedAt = data.server_updated_at;
    }
    if (_ifmState.type === 'user_select') {
      _ifmState.el.innerHTML = _renderInlineSelectValue(_ifmState.el, newVal);
    } else {
      _ifmState.el.innerHTML = _ifmRenderValue(_ifmState.type, newVal);
    }
    if (typeof refreshLucide === 'function') refreshLucide();
    showToast(`${_ifmState.fieldKey} disimpan`, 'success');
    _ifmClose();
  } catch (e) {
    if (e?.payload?.conflict) {
      await refreshTaskRow(_ifmState.taskId);
      showToast('Data diubah user lain. Nilai terbaru dipakai.', 'error');
      _ifmClose();
    } else {
      showToast('Network error', 'error');
    }
  } finally {
    ifmSave.disabled = false;
    ifmSave.textContent = 'Simpan';
  }
});


// ── Inline status update ────────────────────────────────────────

document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', async function() {
    const id     = this.dataset.taskId;
    const status = this.value;
    const prevClass = this.className;
    this.className = 'status-select badge badge-' + status;
    try {
      const data = await _postJson(`/tasks/${id}/status`, { status });
      showToast(data.success ? 'Status diupdate' : 'Gagal update', data.success ? 'success' : 'error');
    } catch (e) {
      this.className = prevClass;
      showToast('Gagal update status', 'error');
    }
  });
});

// ── Setor Modal ──────────────────────────────────────────────────
const modal       = document.getElementById('setorModal');
const modalClose  = document.getElementById('setorModalClose');
const cancelBtn   = document.getElementById('setorCancelBtn');
const submitBtn   = document.getElementById('setorSubmitBtn');
const unsetor     = document.getElementById('setorUnsetor');

let _taskId  = null;

function openSetorModal()  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closeSetorModal() { modal.style.display = 'none'; document.body.style.overflow = ''; }

modalClose.addEventListener('click', closeSetorModal);
cancelBtn.addEventListener('click', closeSetorModal);
modal.addEventListener('click', e => { if (e.target === modal) closeSetorModal(); });

// Open modal on setor-pill click
document.querySelectorAll('.setor-pill').forEach(btn => {
  btn.addEventListener('click', async function() {
    _taskId = this.dataset.taskId;
    const isSetor = this.dataset.setor === '1';

    // Update title
    document.getElementById('setorModalTitle').textContent = isSetor ? 'Edit Submission' : 'Setor Task';

    // Show/hide un-setor button
    unsetor.classList.toggle('hidden', !isSetor);
    submitBtn.textContent = isSetor ? 'Update' : 'Setor';

    // Pre-fill from data attributes (fast, no fetch)
    document.getElementById('setorProductName').value = this.dataset.theme  || '';
    document.getElementById('setorCategory').value    = '';
    document.getElementById('setorLink').value        = '';
    document.getElementById('setorAccount').textContent = this.dataset.account || '—';
    document.getElementById('setorPic').textContent     = this.dataset.pic    || '—';
    document.getElementById('setorDate').textContent    = this.dataset.date   || '—';

    openSetorModal();

    // Fetch live data from server to fill Category + Link
    try {
      const res  = await fetch(`/tasks/${_taskId}/setor-data`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (data.success) {
        const f = data.fields;
        const s = data.sub;
        document.getElementById('setorProductName').value  = s.product_name || f.theme      || '';
        document.getElementById('setorLink').value         = s.link_setor   || f.link_setor  || '';
        document.getElementById('setorCategory').value     = s.category     || f.artboard    || '';
        document.getElementById('setorAccount').textContent = f.account  || s.account  || '—';
        document.getElementById('setorPic').textContent    = f.pic_name || s.pic_name || '—';
        document.getElementById('setorDate').textContent   = f.date     || s.date     || '—';
      }
    } catch(e) { /* keep prefilled values */ }
  });
});

// Submit (setor / update)
submitBtn.addEventListener('click', async () => {
  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving…';

  const extraData = {
    setor:        true,
    product_name: document.getElementById('setorProductName').value,
    link_setor:   document.getElementById('setorLink').value,
    category:     document.getElementById('setorCategory').value,
    expected_setor_updated_at: document.querySelector(`.setor-pill[data-task-id="${_taskId}"]`)?.dataset.setorUpdatedAt || null,
  };

  try {
    const data = await _postJson(`/tasks/${_taskId}/setor`, extraData);

    if (data.success) {
      showToast('Submission berhasil disimpan!', 'success');
      closeSetorModal();
      // Update pill UI
      const pill = document.querySelector(`.setor-pill[data-task-id="${_taskId}"]`);
      if (pill) {
        pill.dataset.setor = '1';
        if (data?.setor_updated_at) pill.dataset.setorUpdatedAt = data.setor_updated_at;
        pill.classList.add('active');
        pill.title = 'Sudah setor — klik untuk edit/batalkan';
        pill.innerHTML = '<i data-lucide="check" style="width:10px;height:10px"></i> Setor';
        if (typeof refreshLucide === 'function') refreshLucide();
        pill.dataset.theme = extraData.product_name;
      }
    } else {
      showToast('Gagal: ' + (data.message || 'Error'), 'error');
    }
  } catch(e) {
    if (e?.payload?.conflict) {
      await refreshTaskRow(_taskId);
      showToast('Status setor diubah user lain. Data terbaru ditampilkan.', 'error');
    } else {
      showToast('Network error', 'error');
    }
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Update';
  }
});

// Un-setor
unsetor.addEventListener('click', async () => {
  const ok = await appConfirm({
    head: 'Konfirmasi',
    title: 'Batalkan setor?',
    message: 'Batalkan setor untuk task ini?',
    okText: 'Batalkan',
    okVariant: 'danger',
  });
  if (!ok) return;
  unsetor.disabled = true;

  try {
    const pill = document.querySelector(`.setor-pill[data-task-id="${_taskId}"]`);
    const data = await _postJson(`/tasks/${_taskId}/setor`, {
      setor: false,
      expected_setor_updated_at: pill?.dataset.setorUpdatedAt || null,
    });

    if (data.success) {
      showToast('Submission dihapus.', 'success');
      closeSetorModal();
      const pill = document.querySelector(`.setor-pill[data-task-id="${_taskId}"]`);
      if (pill) {
        pill.dataset.setor = '0';
        if (data?.setor_updated_at) pill.dataset.setorUpdatedAt = data.setor_updated_at;
        pill.classList.remove('active');
        pill.title = 'Belum setor — klik untuk setor';
        pill.innerHTML = '<i data-lucide="circle" style="width:10px;height:10px"></i> Belum';
        if (typeof refreshLucide === 'function') refreshLucide();
      }
    } else {
      showToast('Gagal: ' + (data.message || ''), 'error');
    }
  } catch(e) {
    if (e?.payload?.conflict) {
      await refreshTaskRow(_taskId);
      showToast('Status setor diubah user lain. Data terbaru ditampilkan.', 'error');
    } else {
      showToast('Network error', 'error');
    }
  } finally {
    unsetor.disabled = false;
  }
});
const taskModal = document.getElementById('addTaskModal');
const taskModalTitle = document.getElementById('taskModalTitle');
const taskForm = document.getElementById('addTaskForm');
const taskStatusInput = document.getElementById('atm-status');
const taskSubmitText = document.getElementById('atm-submit-text');

function closeTaskModal() {
  closeModal('addTaskModal');
}

function _boolValue(raw) {
  return raw === '1' || raw === 'true' || raw === true || raw === 'on';
}

function _extractRichtextPreview(jsonRaw) {
  if (!jsonRaw) return '';
  try {
    const parsed = JSON.parse(jsonRaw);
    if (!Array.isArray(parsed?.blocks)) return '';
    return parsed.blocks
      .slice(0, 2)
      .map((b) => (b?.data?.text || '').replace(/<[^>]*>/g, ''))
      .join(' ')
      .trim();
  } catch (e) {
    return '';
  }
}

function prepareAddTaskModal() {
  if (!taskForm) return;
  taskForm.reset();
  taskForm.action = '/tasks/store';
  if (taskStatusInput) taskStatusInput.value = 'pending';
  if (taskModalTitle) taskModalTitle.textContent = 'Tambah Task Baru';
  if (taskSubmitText) taskSubmitText.textContent = 'Simpan Task';

  const atmProj = document.getElementById('atm-project-id');
  if (atmProj) atmProj.value = '';
  const atmPar = document.getElementById('atm-parent-id');
  if (atmPar) atmPar.value = '';

  taskForm.querySelectorAll('[data-field-type="richtext"]').forEach((hidden) => {
    hidden.value = '';
    const preview = document.getElementById(`${hidden.id}_preview`);
    if (preview) preview.innerHTML = 'Klik untuk buka editor…';
  });
  if (typeof refreshLucide === 'function') refreshLucide(taskModal);
}

async function openEditTaskModal(taskId) {
  try {
    const res = await fetch(`/tasks/${taskId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data?.success || !data?.task) {
      showToast('Data task tidak ditemukan', 'error');
      return;
    }

    const task = data.task;
    prepareAddTaskModal();
    taskForm.action = `/tasks/${taskId}/update`;
    if (taskStatusInput) taskStatusInput.value = task.status || 'pending';
    if (taskModalTitle) taskModalTitle.textContent = `Edit Task #${String(taskId).padStart(4, '0')}`;
    if (taskSubmitText) taskSubmitText.textContent = 'Update Task';

    taskForm.querySelectorAll('[data-field-key]').forEach((el) => {
      const key = el.getAttribute('data-field-key');
      const type = el.getAttribute('data-field-type');
      const raw = (task.fields?.[key]?.value ?? '');

      if (type === 'boolean') {
        el.checked = _boolValue(raw);
        return;
      }
      if (type === 'richtext') {
        el.value = raw || '';
        const preview = document.getElementById(`${el.id}_preview`);
        if (preview) {
          const previewText = _extractRichtextPreview(el.value);
          preview.innerHTML = previewText || '<span style="color:var(--text-3)">Klik untuk buka editor…</span>';
        }
        return;
      }
      el.value = raw || '';
    });

    const atmProj = document.getElementById('atm-project-id');
    if (atmProj) atmProj.value = task.project_id ? String(task.project_id) : '';
    const atmPar = document.getElementById('atm-parent-id');
    if (atmPar) atmPar.value = task.parent_id ? String(task.parent_id) : '';

    openModal('addTaskModal');
    if (typeof refreshLucide === 'function') refreshLucide(taskModal);
  } catch (e) {
    showToast('Gagal load data task', 'error');
  }
}

// Deep-link: /tasks?highlight_id=123 scrolls to and highlights the row.
const highlightTaskId = new URLSearchParams(window.location.search).get('highlight_id');
if (highlightTaskId) {
  const targetRow = document.querySelector(`tr[data-task-id="${highlightTaskId}"]`);
  if (targetRow) {
    // Remove param from URL without reload
    const url = new URL(window.location.href);
    url.searchParams.delete('highlight_id');
    history.replaceState(null, '', url.toString());

    // Scroll to row smoothly
    setTimeout(() => {
      targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Trigger highlight animation
      targetRow.querySelectorAll('td').forEach(td => {
        td.style.animation = 'none';
        td.offsetHeight; // reflow
        td.style.animation = '';
      });
      targetRow.classList.add('row-highlight');
      targetRow.addEventListener('animationend', () => {
        targetRow.classList.remove('row-highlight');
      }, { once: true });
    }, 300);
  }
}

// Deep-link: /tasks?edit_id=123 opens the edit task modal (e.g. from Daftar Setor).
const editTaskIdParam = new URLSearchParams(window.location.search).get('edit_id');
if (editTaskIdParam) {
  const editTid = parseInt(editTaskIdParam, 10);
  const urlEdit = new URL(window.location.href);
  urlEdit.searchParams.delete('edit_id');
  history.replaceState(null, '', urlEdit.toString());
  if (editTid > 0 && typeof openEditTaskModal === 'function') {
    setTimeout(() => openEditTaskModal(editTid), 350);
  }
}

// Add Task form submit loading state
const atmForm = document.getElementById('addTaskForm');
if (atmForm) {
  atmForm.addEventListener('submit', function() {
    const btn = document.getElementById('atm-submit');
    if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan…'; }
  });
}

// Quick add task (single action, no inline form row)
(function () {
  const quickForm = document.getElementById('quickAddTaskForm');
  const quickSubmit = document.getElementById('quickAddSubmitBtn');
  if (!quickForm || !quickSubmit) return;

  const quickDateInputs = () => [...quickForm.querySelectorAll('.task-quick-draft-date')];
  const todayStr = () => {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
  };
  const fillQuickDates = () => {
    const t = todayStr();
    quickDateInputs().forEach((el) => {
      el.value = t;
    });
  };

  quickForm.addEventListener('submit', function () {
    fillQuickDates();
    quickSubmit.disabled = true;
    quickSubmit.textContent = 'Menambahkan...';
  });
})();

// ══════════════════════════════════════════════════════════════
// FEATURE 1: Live Search
// ══════════════════════════════════════════════════════════════
(function () {
  const input     = document.getElementById('taskSearchInput');
  const clearBtn  = document.getElementById('taskSearchClear');
  const countEl   = document.getElementById('searchResultCount');
  if (!input) return;

  function doSearch() {
    const q = input.value.trim().toLowerCase();
    clearBtn.classList.toggle('u-hidden', q === '');
    const rows = document.querySelectorAll('tbody tr[data-task-id]');
    let visible = 0;
    rows.forEach((tr) => {
      const text = tr.textContent.toLowerCase();
      const show = q === '' || text.includes(q);
      tr.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (q === '') {
      countEl.classList.add('u-hidden');
    } else {
      countEl.classList.remove('u-hidden');
      countEl.textContent = `${visible} task ditemukan untuk "${input.value}"`;
    }
  }

  input.addEventListener('input', doSearch);
  clearBtn?.addEventListener('click', () => { input.value = ''; doSearch(); input.focus(); });
})();

// ══════════════════════════════════════════════════════════════
// FEATURE 2: Duplicate Task
// ══════════════════════════════════════════════════════════════
document.querySelectorAll('.duplicate-task-btn').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const taskId = btn.dataset.taskId;
    btn.disabled = true;
    try {
      const data = await _postJson(`/tasks/${taskId}/duplicate`, {});
      if (data.success) {
        showToast(`Task #${String(data.task_id).padStart(4,'0')} berhasil diduplikasi!`, 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        showToast(data.message || 'Duplikasi gagal', 'error');
      }
    } catch (e) {
      showToast('Duplikasi gagal', 'error');
    } finally {
      btn.disabled = false;
    }
  });
});

// ══════════════════════════════════════════════════════════════
// FEATURE 3: Bulk Create
// ══════════════════════════════════════════════════════════════
document.getElementById('bulkCreateSubmit')?.addEventListener('click', async () => {
  const textarea = document.getElementById('bulkCreateLines');
  const statusEl = document.getElementById('bulkCreateStatus');
  const resultEl = document.getElementById('bulkCreateResult');
  const submitBtn = document.getElementById('bulkCreateSubmit');

  const raw   = textarea.value.trim();
  const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
  if (!lines.length) { showToast('Masukkan minimal 1 task', 'error'); return; }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Membuat…';
  resultEl.classList.add('u-hidden');

  try {
    const data = await _postJson('/tasks/bulk-create', {
      lines,
      status: statusEl?.value || 'pending',
      title_field_key: <?= $titleField ? "'" . esc($titleField['field_key']) . "'" : 'null' ?>,
    });

    if (data.success) {
      resultEl.textContent = `✓ ${data.count} task berhasil dibuat.`;
      resultEl.classList.remove('u-hidden');
      textarea.value = '';
      showToast(`${data.count} task dibuat!`, 'success');
      setTimeout(() => { closeModal('bulkCreateModal'); location.reload(); }, 1200);
    } else {
      showToast(data.message || 'Gagal bulk create', 'error');
    }
  } catch (e) {
    showToast('Network error', 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Buat Semua';
  }
});

// ══════════════════════════════════════════════════════════════
// FEATURE 4 & 5: Progress & Deadline inline edit (Core Popover)
// ══════════════════════════════════════════════════════════════
const _cpop     = document.getElementById('corePopover');
const _cpopLbl  = document.getElementById('corePopLabel');
const _cpopWrap = document.getElementById('corePopInputWrap');
let   _cpopState = null;

function _closeCpop() { _cpop.style.display = 'none'; _cpopState = null; }
document.addEventListener('click', (e) => {
  if (_cpop && !_cpop.contains(e.target) &&
      !e.target.closest('.task-progress-cell') &&
      !e.target.closest('.task-deadline-btn')) {
    _closeCpop();
  }
});

function _openCpop(triggerEl, type, taskId, currentVal) {
  _cpopState = { type, taskId, triggerEl };
  _cpopLbl.textContent = type === 'progress' ? 'Progress (0–100%)' : 'Deadline';

  if (type === 'progress') {
    const p = parseInt(currentVal) || 0;
    _cpopWrap.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px">
        <input type="range" id="cpopRange" min="0" max="100" step="5" value="${p}"
               style="flex:1;accent-color:var(--accent)">
        <input type="number" id="cpopNum" min="0" max="100" value="${p}"
               class="form-control" style="width:64px;text-align:center">
      </div>`;
    const range = _cpopWrap.querySelector('#cpopRange');
    const num   = _cpopWrap.querySelector('#cpopNum');
    range.addEventListener('input', () => { num.value = range.value; });
    num.addEventListener('input', () => { range.value = Math.max(0, Math.min(100, parseInt(num.value)||0)); });
  } else {
    _cpopWrap.innerHTML = `<input type="date" id="cpopDate" class="form-control" value="${currentVal || ''}">`;
    _cpopWrap.querySelector('#cpopDate').focus();
  }

  // Position near trigger
  const rect = triggerEl.getBoundingClientRect();
  const popW = 260, popH = 150;
  let top  = rect.bottom + 6;
  let left = rect.left;
  if (left + popW > window.innerWidth - 12) left = window.innerWidth - popW - 12;
  if (top  + popH > window.innerHeight - 12) top  = rect.top - popH - 6;
  _cpop.style.top  = top  + 'px';
  _cpop.style.left = left + 'px';
  _cpop.style.display = 'block';
}

document.getElementById('corePopCancel')?.addEventListener('click', _closeCpop);

document.getElementById('corePopSave')?.addEventListener('click', async () => {
  if (!_cpopState) return;
  const { type, taskId, triggerEl } = _cpopState;

  let payload = {};
  if (type === 'progress') {
    const val = parseInt(_cpopWrap.querySelector('#cpopNum')?.value) || 0;
    payload.progress = Math.max(0, Math.min(100, val));
  } else {
    payload.deadline = _cpopWrap.querySelector('#cpopDate')?.value || '';
  }

  const saveBtn = document.getElementById('corePopSave');
  saveBtn.disabled = true;
  saveBtn.textContent = '…';

  try {
    const data = await _postJson(`/tasks/${taskId}/core-update`, payload);
    if (!data.success) { showToast('Gagal simpan', 'error'); return; }

    if (type === 'progress') {
      const p = data.data.progress ?? payload.progress;
      // Update progress cell
      const cell = triggerEl.closest ? triggerEl : triggerEl;
      const fill  = cell.querySelector('.task-progress-fill');
      const label = cell.querySelector('.task-progress-label');
      if (fill)  fill.style.width = p + '%';
      if (label) label.textContent = p + '%';
      cell.dataset.progress = p;
      cell.title = `Progress: ${p}% — klik untuk ubah`;
      showToast(`Progress diset ke ${p}%`, 'success');
    } else {
      const dl = data.data.deadline;
      // Update deadline button
      const btn = triggerEl;
      btn.dataset.deadline = dl || '';
      _renderDeadlineBtn(btn, dl);
      showToast('Deadline diperbarui', 'success');
    }
    _closeCpop();
  } catch (e) {
    showToast('Gagal simpan', 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = 'Simpan';
  }
});

function _renderDeadlineBtn(btn, dl) {
  if (!dl) {
    btn.className = 'task-deadline-btn';
    btn.innerHTML = '<span class="dl-empty"><i class="fa-regular fa-calendar icon-xs" aria-hidden="true"></i> —</span>';
    btn.title = 'Klik untuk set deadline';
    return;
  }
  const today   = new Date(); today.setHours(0,0,0,0);
  const dlDate  = new Date(dl + 'T00:00:00');
  const diffMs  = dlDate - today;
  const diffDays = Math.round(diffMs / 86400000);
  const fmtFull  = dlDate.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const fmtShort = dlDate.toLocaleDateString('en-GB', { day:'2-digit', month:'short' });
  let cls = '', label = '';
  if (diffDays < 0)    { cls = 'dl-overdue'; label = 'Overdue'; }
  else if (diffDays <= 3) { cls = 'dl-urgent';  label = fmtShort; }
  else if (diffDays <= 7) { cls = 'dl-soon';    label = fmtShort; }
  else                    { cls = 'dl-ok';       label = fmtFull; }
  btn.className = `task-deadline-btn ${cls}`;
  btn.title = fmtFull;
  btn.innerHTML = `<i class="fa-solid fa-calendar-days icon-xs" aria-hidden="true"></i> ${label}`;
}

// Attach progress click handlers
document.querySelectorAll('.task-progress-cell').forEach((cell) => {
  cell.addEventListener('click', (e) => {
    e.stopPropagation();
    _openCpop(cell, 'progress', cell.dataset.taskId, cell.dataset.progress);
  });
});

// Attach deadline click handlers
document.querySelectorAll('.task-deadline-btn').forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    _openCpop(btn, 'deadline', btn.dataset.taskId, btn.dataset.deadline);
  });
});

// ══════════════════════════════════════════════════════════════
// Bulk checkbox & bulk actions (status / field / delete)
// ══════════════════════════════════════════════════════════════
(function () {
  const form = document.getElementById('taskBulkForm');
  const checkAll = document.getElementById('checkAllTasks');
  const bulkApply = document.getElementById('taskBulkApplyBtn');
  const bulkSelect = document.getElementById('bulkActionSelect');
  const bulkHidden = document.getElementById('bulkActionHidden');
  const panelStatus = document.getElementById('bulkPanelStatus');
  const panelField = document.getElementById('bulkPanelField');
  const bulkCount = document.getElementById('taskBulkCount');
  const bulkFieldKey = document.getElementById('bulkFieldKey');
  const bulkValText = document.getElementById('bulkFieldValueText');
  const bulkValSel = document.getElementById('bulkFieldValueSelect');
  const floatBar = document.getElementById('taskBulkFloatBar');

  if (!form || !bulkApply) return;

  function getChecks() {
    return [...document.querySelectorAll('.task-bulk-check')];
  }

  function syncCount() {
    const checks = getChecks();
    const n = checks.filter((c) => c.checked).length;
    if (bulkCount) bulkCount.textContent = String(n);
    if (floatBar) floatBar.classList.toggle('u-hidden', n < 1);
    if (checkAll) {
      checkAll.checked = checks.length > 0 && checks.every((c) => c.checked);
      checkAll.indeterminate = n > 0 && n < checks.length;
    }
  }

  getChecks().forEach((c) => c.addEventListener('change', syncCount));
  if (checkAll) {
    checkAll.addEventListener('change', () => {
      getChecks().forEach((c) => { c.checked = checkAll.checked; });
      syncCount();
    });
  }

  function togglePanels() {
    const v = bulkSelect ? bulkSelect.value : '';
    if (panelStatus) panelStatus.classList.toggle('u-hidden', v !== 'status');
    if (panelField) panelField.classList.toggle('u-hidden', v !== 'field');
    if (v === 'field') syncBulkFieldValue();
  }
  if (bulkSelect) bulkSelect.addEventListener('change', togglePanels);

  function syncBulkFieldValue() {
    const opt = bulkFieldKey && bulkFieldKey.selectedOptions[0];
    if (!opt || !bulkValText || !bulkValSel) return;
    const typ = opt.getAttribute('data-type') || '';
    let opts = [];
    try {
      opts = JSON.parse(opt.getAttribute('data-options') || '[]');
    } catch (e) {
      opts = [];
    }
    bulkValSel.innerHTML = '';
    bulkValSel.appendChild(new Option('— Kosongkan —', ''));
    if (Array.isArray(opts)) {
      opts.forEach((o) => {
        const s = String(o);
        bulkValSel.appendChild(new Option(s, s));
      });
    }
    if (typ === 'select' && opts.length) {
      bulkValSel.classList.remove('u-hidden');
      bulkValText.classList.add('u-hidden');
      bulkValText.removeAttribute('name');
      bulkValText.disabled = true;
      bulkValSel.disabled = false;
      bulkValSel.setAttribute('name', 'bulk_field_value');
    } else {
      bulkValSel.classList.add('u-hidden');
      bulkValText.classList.remove('u-hidden');
      bulkValSel.removeAttribute('name');
      bulkValSel.disabled = true;
      bulkValText.setAttribute('name', 'bulk_field_value');
      bulkValText.disabled = false;
    }
  }

  if (bulkFieldKey) bulkFieldKey.addEventListener('change', syncBulkFieldValue);

  bulkApply.addEventListener('click', async () => {
    const action = bulkSelect ? bulkSelect.value : '';
    const checked = getChecks().filter((c) => c.checked);
    if (checked.length < 1) {
      alert('Pilih minimal 1 task.');
      return;
    }
    if (!action) {
      alert('Pilih aksi bulk.');
      return;
    }
    bulkHidden.value = action;

    if (action === 'delete') {
      const ok = await appConfirm({
        head: 'Bulk hapus',
        title: `Hapus ${checked.length} task?`,
        message: 'Task akan dipindahkan ke Trash (soft delete).',
        okText: 'Hapus',
        okVariant: 'danger',
      });
      if (!ok) return;
      form.requestSubmit();
      return;
    }

    if (action === 'status') {
      const st = form.querySelector('[name="bulk_status"]');
      if (!st || !st.value) {
        alert('Pilih status tujuan.');
        return;
      }
      form.requestSubmit();
      return;
    }

    if (action === 'field') {
      if (!bulkFieldKey || !bulkFieldKey.value) {
        alert('Pilih field yang akan diubah.');
        return;
      }
      syncBulkFieldValue();
      form.requestSubmit();
    }
  });

  syncCount();
})();
</script>

