<?php
/** @var array $project */
/** @var array $tasks */
/** @var array $fields */
/** @var array $pager */
/** @var array $pagerQuery */
/** @var string $pagerUriPath */
/** @var string $workItemPrefix */
/** @var string $titleFieldKey */
/** @var array $assigneesByTaskId */
/** @var bool $workItemAssigneesEnabled */
/** @var array $relationCountByTaskId */
/** @var bool $taskHasCreatedAt */
/** @var bool $taskHasUpdatedAt */
/** @var bool $taskHasDeadline */
/** @var int|null $initialPanelTaskId */
$project      = $project ?? [];
$tasks        = $tasks ?? [];
$fields       = $fields ?? [];
$pager        = $pager ?? [];
$pagerQuery   = $pagerQuery ?? [];
$pagerUriPath = $pagerUriPath ?? '/projects';
$stats        = $project['stats'] ?? [];
$sessionUserPerms = session()->get('user_perms') ?? [];
$canManage = (session()->get('user_role') === 'super_admin')
    || in_array('manage_projects', (array) $sessionUserPerms, true);

$workItemPrefix        = $workItemPrefix ?? 'PRJ';
$titleFieldKey         = $titleFieldKey ?? 'judul';
$assigneesByTaskId          = $assigneesByTaskId ?? [];
$workItemAssigneesEnabled   = $workItemAssigneesEnabled ?? false;
$relationCountByTaskId = $relationCountByTaskId ?? [];
$taskHasCreatedAt      = $taskHasCreatedAt ?? false;
$taskHasUpdatedAt      = $taskHasUpdatedAt ?? false;
$taskHasDeadline       = $taskHasDeadline ?? false;
$initialPanelTaskId    = isset($initialPanelTaskId) ? (int) $initialPanelTaskId : null;
if ($initialPanelTaskId <= 0) {
    $initialPanelTaskId = null;
}
$projectPageId = (int) ($project['id'] ?? 0);

$titleFieldLabel = 'Judul';
foreach ($fields as $wf) {
    if ((string) ($wf['field_key'] ?? '') === $titleFieldKey) {
        $titleFieldLabel = (string) ($wf['field_label'] ?? $titleFieldKey);
        break;
    }
}

$wiFmtDate = static function (?string $d): string {
    if ($d === null || $d === '') {
        return '—';
    }
    $ts = strtotime($d);

    return $ts ? date('M j, Y', $ts) : '—';
};

$wiColorPalette = [
    ['bg' => 'var(--badge-1-bg)', 'text' => 'var(--badge-1-text)'],
    ['bg' => 'var(--badge-2-bg)', 'text' => 'var(--badge-2-text)'],
    ['bg' => 'var(--badge-3-bg)', 'text' => 'var(--badge-3-text)'],
    ['bg' => 'var(--badge-4-bg)', 'text' => 'var(--badge-4-text)'],
    ['bg' => 'var(--badge-5-bg)', 'text' => 'var(--badge-5-text)'],
    ['bg' => 'var(--badge-6-bg)', 'text' => 'var(--badge-6-text)'],
    ['bg' => 'var(--badge-7-bg)', 'text' => 'var(--badge-7-text)'],
    ['bg' => 'var(--badge-8-bg)', 'text' => 'var(--badge-8-text)'],
    ['bg' => 'var(--badge-9-bg)', 'text' => 'var(--badge-9-text)'],
    ['bg' => 'var(--badge-10-bg)', 'text' => 'var(--badge-10-text)'],
    ['bg' => 'var(--badge-11-bg)', 'text' => 'var(--badge-11-text)'],
    ['bg' => 'var(--badge-12-bg)', 'text' => 'var(--badge-12-text)'],
    ['bg' => 'var(--badge-13-bg)', 'text' => 'var(--badge-13-text)'],
    ['bg' => 'var(--badge-14-bg)', 'text' => 'var(--badge-14-text)'],
    ['bg' => 'var(--badge-15-bg)', 'text' => 'var(--badge-15-text)'],
    ['bg' => 'var(--badge-16-bg)', 'text' => 'var(--badge-16-text)'],
    ['bg' => 'var(--badge-17-bg)', 'text' => 'var(--badge-17-text)'],
    ['bg' => 'var(--badge-18-bg)', 'text' => 'var(--badge-18-text)'],
    ['bg' => 'var(--badge-19-bg)', 'text' => 'var(--badge-19-text)'],
    ['bg' => 'var(--badge-20-bg)', 'text' => 'var(--badge-20-text)'],
    ['bg' => 'var(--badge-21-bg)', 'text' => 'var(--badge-21-text)'],
    ['bg' => 'var(--badge-22-bg)', 'text' => 'var(--badge-22-text)'],
    ['bg' => 'var(--badge-23-bg)', 'text' => 'var(--badge-23-text)'],
    ['bg' => 'var(--badge-24-bg)', 'text' => 'var(--badge-24-text)'],
];
$wiOptBadgeColor = static function (string $val, array $palette): array {
    $h   = 0;
    $len = strlen($val);
    for ($i = 0; $i < $len; $i++) {
        $h = ($h * 31 + ord($val[$i])) % count($palette);
    }

    return $palette[$h];
};
$priorityBselOptions = [];
$pdef                = null;
foreach ($fields as $wf) {
    if (($wf['field_key'] ?? '') === 'priority') {
        $pdef = $wf;
        break;
    }
}
if ($pdef && ($pdef['type'] ?? '') === 'select') {
    $raw = $pdef['options'] ?? [];
    if (is_string($raw)) {
        $raw = json_decode($raw, true) ?: [];
    }
    foreach ($raw as $o) {
        if (is_string($o)) {
            $priorityBselOptions[] = $o;
        } elseif (is_array($o)) {
            $priorityBselOptions[] = (string) ($o['value'] ?? $o['label'] ?? '');
        }
    }
    $priorityBselOptions = array_values(array_filter($priorityBselOptions, static fn ($x): bool => $x !== ''));
}
if ($priorityBselOptions === []) {
    $priorityBselOptions = ['Low', 'Medium', 'High', 'Urgent'];
}
if (! in_array('', $priorityBselOptions, true)) {
    array_unshift($priorityBselOptions, '');
}
$priorityBselPalettes = [];
foreach ($priorityBselOptions as $po) {
    $priorityBselPalettes[] = ($po === '')
        ? ['bg' => 'var(--surface-2)', 'text' => 'var(--text-3)']
        : $wiOptBadgeColor((string) $po, $wiColorPalette);
}
$tableColspan = $taskHasDeadline ? 9 : 8;
?>

<link rel="stylesheet" href="/assets/css/pages/clients-projects.css" />
<link rel="stylesheet" href="/assets/css/pages/tasks-index.css" />
<link rel="stylesheet" href="/assets/css/pages/task-show.css" />
<link rel="stylesheet" href="/assets/css/pages/task-detail-extras.css" />

<div class="wi-master-detail">
<div class="wi-master-pane">
<div class="page-header">
  <div class="page-header-left">
    <a href="/projects" class="page-back-link">← Semua project</a>
    <h2 class="page-title"><?= esc($project['name'] ?? '') ?></h2>
    <p class="page-sub">
      <?= esc($project['client_name'] ?? '') ?> · <?= esc($project['status'] ?? '') ?>
      · Work items: <?= (int) ($stats['total'] ?? 0) ?> (selesai <?= (int) ($stats['done_count'] ?? 0) ?>, lewat tenggat <?= (int) ($stats['overdue_count'] ?? 0) ?>)
    </p>
  </div>
  <?php if ($canManage): ?>
  <div class="page-header-right">
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('proj-edit-modal').classList.add('open')" title="Edit project">
      <i class="fa-solid fa-pen-to-square icon-xs" aria-hidden="true"></i> Edit project
    </button>
  </div>
  <?php endif; ?>
</div>

<div class="card cp-card wi-card">
  <div class="card-body">
    <h3 class="cp-section-title">Work items</h3>
    <p class="page-sub wi-intro">
      <strong>Work item</strong> di halaman ini terpisah dari <a href="/tasks">task internal</a>: daftar field dan nilainya memakai konteks <a href="/fields?project_id=<?= (int) ($project['id'] ?? 0) ?>">Field Manager untuk project ini</a> saja, tidak dicampur dengan kolom task internal. Kolom <strong>Judul</strong> memakai field sistem <code>judul</code> (otomatis). Panel detail memakai rich text <code>deskripsi</code> (alias: <code>description</code>, <code>body</code>, <code>keterangan</code>).
    </p>
<?php if (! empty($canCreateProjectTask) && ! empty($fields)): ?>
    <form method="post" action="/tasks/store" class="cp-quick-task-form wi-quick-add">
      <?= csrf_field() ?>
      <input type="hidden" name="form_context" value="project">
      <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
      <label style="flex:1;min-width:200px;">Judul work item
        <input type="text" name="fields[<?= esc($titleFieldKey) ?>]" class="form-control" required placeholder="Nama work item">
      </label>
      <label>State
        <select name="status" class="form-control">
          <option value="pending">pending</option>
          <option value="on_progress">on_progress</option>
          <option value="done">done</option>
          <option value="cancelled">cancelled</option>
        </select>
      </label>
      <button type="submit" class="btn btn-primary">Tambah work item</button>
    </form>
<?php elseif (empty($fields)): ?>
    <p class="page-sub" style="color:var(--text-muted);margin-bottom:1rem;">Belum ada field untuk proyek ini. Buka <a href="/fields?project_id=<?= (int) ($project['id'] ?? 0) ?>">Field Manager</a> atau jalankan <code>php spark project:sync-fields</code> untuk memetakan ulang task yang sudah ada.</p>
<?php endif; ?>
    <div class="table-wrap wi-table-wrap">
      <table class="table cp-table wi-table" id="wi-work-items-table">
        <thead>
          <tr>
            <th class="wi-col-work">Judul</th>
            <th class="wi-col-state">State</th>
            <th class="wi-col-priority">Priority</th>
            <th class="wi-col-assignees">Assignees</th>
            <?php if ($taskHasDeadline): ?><th class="wi-col-dl">Deadline</th><?php endif; ?>
            <th class="wi-col-date">Created on</th>
            <th class="wi-col-date">Updated on</th>
            <th class="wi-col-links">Link</th>
            <th class="wi-col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <?php
            $tid = (int) ($t['id'] ?? 0);
            $detail = '/projects/' . $projectPageId . '?task=' . $tid;

            // Satukan field_key + value + updated_at agar field-update & optimistic lock tidak salah target.
            $resolvedTitleKey = $titleFieldKey;
            $titleRaw         = (string) ($t['fields'][$resolvedTitleKey]['value'] ?? '');
            $titleUpdatedAt   = (string) ($t['fields'][$resolvedTitleKey]['updated_at'] ?? '');
            if ($titleRaw === '' && $resolvedTitleKey !== 'judul') {
                $jv = (string) ($t['fields']['judul']['value'] ?? '');
                if ($jv !== '') {
                    $resolvedTitleKey = 'judul';
                    $titleRaw         = $jv;
                    $titleUpdatedAt   = (string) ($t['fields']['judul']['updated_at'] ?? '');
                }
            }
            $titleFieldLabelRow = $titleFieldLabel;
            $titleFieldTypeRow  = 'text';
            foreach ($fields as $wf) {
                if ((string) ($wf['field_key'] ?? '') === $resolvedTitleKey) {
                    $titleFieldLabelRow = (string) ($wf['field_label'] ?? $resolvedTitleKey);
                    $titleFieldTypeRow  = (string) ($wf['type'] ?? 'text');
                    break;
                }
            }
            if ($titleFieldTypeRow === 'richtext') {
                $titleFieldTypeRow = 'textarea';
            }
            $code           = $workItemPrefix . '-' . $tid;
            $st             = (string) ($t['status'] ?? 'pending');
            if (! in_array($st, ['pending', 'on_progress', 'done', 'cancelled'], true)) {
                $st = 'pending';
            }
            $prioVal = (string) ($t['fields']['priority']['value'] ?? '');
            if (strtolower(trim($prioVal)) === 'none') {
                $prioVal = '';
            }
            $prioUpd = (string) ($t['fields']['priority']['updated_at'] ?? '');
            $assignees = $assigneesByTaskId[$tid] ?? [];
            $linkN     = (int) ($relationCountByTaskId[$tid] ?? 0);
            $createdRaw = $taskHasCreatedAt ? (string) ($t['created_at'] ?? '') : '';
            $updatedRaw = $taskHasUpdatedAt ? (string) ($t['updated_at'] ?? '') : '';
            $deadlineRaw = $taskHasDeadline ? (string) ($t['deadline'] ?? '') : '';
          ?>
          <tr class="wi-row" data-task-id="<?= $tid ?>">
            <td class="wi-cell-work">
              <div class="wi-work-cell-inner">
                <a href="<?= esc($detail) ?>" class="wi-work-code-link wi-task-drawer-link"><span class="wi-work-code"><?= esc($code) ?></span></a>
                <button type="button"
                        class="inline-cell-trigger wi-title-btn"
                        data-task-id="<?= $tid ?>"
                        data-field-key="<?= esc($resolvedTitleKey) ?>"
                        data-field-type="<?= esc($titleFieldTypeRow) ?>"
                        data-field-label="<?= esc($titleFieldLabelRow) ?>"
                        data-value="<?= esc($titleRaw, 'attr') ?>"
                        data-updated-at="<?= esc($titleUpdatedAt, 'attr') ?>"
                        title="Klik untuk mengubah judul">
                  <?php if ($titleRaw !== ''): ?><?= esc(mb_strimwidth($titleRaw, 0, 120, '…')) ?><?php else: ?><span class="wi-work-untitle">(tanpa judul)</span><?php endif; ?>
                </button>
              </div>
            </td>
            <td class="wi-cell-state">
              <div class="wi-state-pill">
                <select class="wi-state-select status-select wi-status-select badge badge-<?= esc($st) ?>"
                        data-task-id="<?= $tid ?>"
                        aria-label="State">
                  <?php foreach (['pending' => 'Pending', 'on_progress' => 'On progress', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $sv => $sl): ?>
                  <option value="<?= esc($sv) ?>" <?= $st === $sv ? 'selected' : '' ?>><?= esc($sl) ?></option>
                  <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-chevron-down wi-state-caret" aria-hidden="true"></i>
              </div>
            </td>
            <td class="wi-cell-priority">
              <?php
                $pIdx = array_search($prioVal, $priorityBselOptions, true);
                if ($pIdx === false && trim($prioVal) === '') {
                    $pIdx = array_search('', $priorityBselOptions, true);
                }
                $pCol = $pIdx !== false ? $priorityBselPalettes[$pIdx] : ['bg' => 'var(--surface-2)', 'text' => 'var(--text-3)'];
              ?>
              <div class="bsel wi-bsel wi-prio-bsel"
                   data-task-id="<?= $tid ?>"
                   data-field-key="priority"
                   data-value="<?= esc($prioVal) ?>"
                   data-updated-at="<?= esc($prioUpd) ?>"
                   data-options="<?= esc(json_encode($priorityBselOptions), 'attr') ?>"
                   data-palette="<?= esc(json_encode($priorityBselPalettes), 'attr') ?>">
                <span class="bsel-val wi-prio-bsel-val" style="background:<?= esc($pCol['bg']) ?>;color:<?= esc($pCol['text']) ?>">
                  <?= $prioVal !== '' ? '<span class="wi-prio-dot" aria-hidden="true"></span>' : '' ?><?= $prioVal !== '' ? esc($prioVal) : 'None' ?>
                  <i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>
                </span>
                <div class="bsel-drop"></div>
              </div>
            </td>
            <td class="wi-cell-assignees">
              <?php
                $assigneeLabels = array_values(array_filter(array_map(static function (array $a): string {
                    return trim((string) ($a['nickname'] ?? $a['username'] ?? ''));
                }, $assignees)));
                $assigneeTitleStr = implode(', ', $assigneeLabels);
                $assigneeForJs    = array_map(static function (array $a): array {
                    return [
                        'user_id'  => (int) ($a['user_id'] ?? 0),
                        'nickname' => (string) ($a['nickname'] ?? ''),
                        'username' => (string) ($a['username'] ?? ''),
                        'avatar'   => $a['avatar'] ?? null,
                    ];
                }, $assignees);
                $assigneeJsonAttr = esc(json_encode($assigneeForJs), 'attr');
              ?>
              <?php if ($workItemAssigneesEnabled): ?>
              <button type="button"
                      class="wi-assignee-trigger"
                      data-task-id="<?= $tid ?>"
                      data-assignees="<?= $assigneeJsonAttr ?>"
                      aria-haspopup="listbox"
                      aria-expanded="false"
                      title="Klik untuk mengubah PIC (dari daftar Team Users)">
              <?php endif; ?>
              <?php if (empty($assignees)): ?>
                <div class="wi-pic-empty" title="Belum ada PIC">
                  <span class="wi-pic-empty-avatar"><i class="fa-regular fa-user" aria-hidden="true"></i></span>
                  <span class="wi-pic-empty-txt">PIC</span>
                </div>
              <?php elseif (count($assignees) === 1): ?>
                <?php
                  $a    = $assignees[0];
                  $nick = (string) ($a['nickname'] ?? $a['username'] ?? '?');
                  $url  = \App\Models\UserModel::avatarUrl($a['avatar'] ?? null, $nick);
                ?>
                <?php if ($workItemAssigneesEnabled): ?>
                <span class="wi-pic-chip-link">
                  <span class="pic-chip wi-pic-chip">
                    <img src="<?= esc($url) ?>" alt="" class="wi-pic-chip-img" width="22" height="22" loading="lazy" />
                    <span class="pic-chip-text"><?= esc($nick) ?></span>
                  </span>
                </span>
                <?php else: ?>
                <a href="<?= esc($detail) ?>" class="wi-pic-chip-link" title="PIC: <?= esc($nick) ?>">
                  <span class="pic-chip wi-pic-chip">
                    <img src="<?= esc($url) ?>" alt="" class="wi-pic-chip-img" width="22" height="22" loading="lazy" />
                    <span class="pic-chip-text"><?= esc($nick) ?></span>
                  </span>
                </a>
                <?php endif; ?>
              <?php else: ?>
                <?php
                  $showAssignees = array_slice($assignees, 0, 5);
                  $moreN         = count($assignees) - count($showAssignees);
                ?>
                <div class="wi-pic-multi" title="<?= esc($assigneeTitleStr) ?>">
                  <div class="wi-pic-stack" role="group" aria-label="PIC">
                    <?php foreach ($showAssignees as $a): ?>
                      <?php
                        $nick = (string) ($a['nickname'] ?? $a['username'] ?? '?');
                        $url  = \App\Models\UserModel::avatarUrl($a['avatar'] ?? null, $nick);
                      ?>
                      <span class="wi-pic-bubble">
                        <img src="<?= esc($url) ?>" alt="<?= esc($nick) ?>" width="28" height="28" loading="lazy" />
                      </span>
                    <?php endforeach; ?>
                    <?php if ($moreN > 0): ?>
                      <span class="wi-pic-more" title="<?= (int) $moreN ?> PIC lainnya">+<?= (int) $moreN ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($workItemAssigneesEnabled): ?>
                  <span class="wi-pic-multi-hint"><?= (int) count($assignees) ?> PIC</span>
                  <?php else: ?>
                  <a href="<?= esc($detail) ?>" class="wi-pic-multi-hint"><?= (int) count($assignees) ?> PIC</a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($workItemAssigneesEnabled): ?>
              </button>
              <?php endif; ?>
            </td>
            <?php if ($taskHasDeadline): ?>
            <td class="wi-cell-dl">
              <button type="button"
                      class="task-deadline-btn wi-deadline-btn <?= $deadlineRaw !== '' ? 'dl-ok' : '' ?>"
                      data-task-id="<?= $tid ?>"
                      data-deadline="<?= esc($deadlineRaw !== '' ? substr($deadlineRaw, 0, 10) : '') ?>"
                      title="<?= $deadlineRaw !== '' ? esc($wiFmtDate($deadlineRaw)) : 'Klik untuk set deadline' ?>">
                <?php if ($deadlineRaw !== ''): ?>
                  <i class="fa-solid fa-calendar-days icon-xs" aria-hidden="true"></i> <?= esc($wiFmtDate($deadlineRaw)) ?>
                <?php else: ?>
                  <span class="dl-empty"><i class="fa-regular fa-calendar icon-xs" aria-hidden="true"></i> —</span>
                <?php endif; ?>
              </button>
            </td>
            <?php endif; ?>
            <td class="wi-cell-muted">
              <?php if ($taskHasCreatedAt): ?>
              <button type="button" class="btn btn-ghost btn-sm wi-ts-btn" style="padding:0.2rem 0.35rem;font-size:0.8rem;"
                      data-task-id="<?= $tid ?>"
                      data-wi-ts="created_at"
                      data-wi-value="<?= esc($createdRaw) ?>"
                      title="Ubah tanggal dibuat"><?= esc($createdRaw !== '' ? $wiFmtDate($createdRaw) : '—') ?></button>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="wi-cell-muted">
              <?php if ($taskHasUpdatedAt): ?>
              <button type="button" class="btn btn-ghost btn-sm wi-ts-btn" style="padding:0.2rem 0.35rem;font-size:0.8rem;"
                      data-task-id="<?= $tid ?>"
                      data-wi-ts="updated_at"
                      data-wi-value="<?= esc($updatedRaw) ?>"
                      title="Ubah tanggal diperbarui"><?= esc($updatedRaw !== '' ? $wiFmtDate($updatedRaw) : '—') ?></button>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="wi-cell-links">
              <?php if ($linkN <= 0): ?>
                <span class="wi-links-zero">0 links</span>
              <?php else: ?>
                <a class="wi-links-n wi-task-drawer-link" href="<?= esc($detail) ?>"><?= (int) $linkN ?> link<?= $linkN === 1 ? '' : 's' ?></a>
              <?php endif; ?>
            </td>
            <td class="cp-actions"><a class="btn btn-ghost btn-sm wi-task-drawer-link" href="<?= esc($detail) ?>">Detail</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tasks)): ?>
          <tr><td colspan="<?= (int) $tableColspan ?>" class="cp-empty">Tidak ada work item (atau di luar halaman ini).</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($pager)): ?>
      <?= view('components/table_pagination', [
          'pager'       => $pager,
          'queryParams' => $pagerQuery,
          'uriPath'     => $pagerUriPath,
      ]) ?>
    <?php endif; ?>
  </div>
</div>
</div>

<div id="wiTaskDrawerOverlay" class="wi-task-drawer-overlay" aria-hidden="true"></div>
<aside id="wiTaskDrawer" class="wi-task-drawer" aria-hidden="true" aria-label="Detail work item">
  <div class="wi-task-drawer-header">
    <button type="button" class="btn btn-ghost btn-sm wi-task-drawer-close" aria-label="Tutup panel">&times;</button>
  </div>
  <div id="wiTaskDrawerBody" class="wi-task-drawer-body">
    <div class="wi-task-drawer-loading" id="wiTaskDrawerLoading" hidden>Memuat…</div>
    <div id="wiTaskDrawerContent" class="wi-task-drawer-content"></div>
  </div>
</aside>
</div>

<div id="wiInlineFieldModal" class="overlay-fixed overlay-dark" style="display:none;align-items:center;justify-content:center;z-index:8500;">
  <div class="modal modal-inline-field">
    <div class="modal-header">
      <h3 id="wiIfmTitle" class="modal-title">Edit</h3>
      <button type="button" id="wiIfmClose" class="btn-icon btn-icon-md" aria-label="Tutup"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="modal-body">
      <div id="wiIfmInputWrap"></div>
    </div>
    <div class="modal-footer">
      <button type="button" id="wiIfmCancel" class="btn btn-ghost">Batal</button>
      <button type="button" id="wiIfmSave" class="btn btn-primary">Simpan</button>
    </div>
  </div>
</div>
<div id="wiCorePopover" style="display:none;position:fixed;z-index:8600;
     background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
     box-shadow:var(--shadow-md);padding:14px 16px;min-width:220px;max-width:280px">
  <div id="wiCorePopLabel" style="font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:10px"></div>
  <div id="wiCorePopInputWrap"></div>
  <div style="display:flex;gap:6px;margin-top:12px;justify-content:flex-end">
    <button type="button" class="btn btn-ghost btn-sm" id="wiCorePopCancel">Batal</button>
    <button type="button" class="btn btn-primary btn-sm" id="wiCorePopSave">Simpan</button>
  </div>
</div>
<script>
window.__wi = window.__wi || {};
window.__wi.assigneeDirectoryUrl = <?= json_encode('/team/users/directory') ?>;
window.__wi.csrfHeader = <?= json_encode(config(\Config\Security::class)->headerName) ?>;
window.__projectDrawer = {
  projectId: <?= $projectPageId ?>,
  initialTaskId: <?= $initialPanelTaskId !== null ? $initialPanelTaskId : 'null' ?>,
  listUrl: <?= json_encode('/projects/' . $projectPageId) ?>,
};
window.__taskExtras = {
  baseUrl: <?= json_encode(rtrim(base_url(), '/') . '/') ?>,
  csrfHeader: <?= json_encode(config(\Config\Security::class)->headerName) ?>,
};
</script>
<script src="/assets/js/task-show-richtext.js" defer></script>
<script src="/assets/js/project-task-fields-unified.js" defer></script>
<script src="/assets/js/task-detail-extras.js" defer></script>
<script src="/assets/js/project-task-drawer.js" defer></script>
<div id="wiAssigneePopover" class="wi-assignee-pop" style="display:none" role="listbox" aria-label="Pilih PIC">
  <div class="wi-assignee-pop-head">PIC / Assignees</div>
  <input type="search" class="form-control wi-assignee-filter" placeholder="Cari nama…" autocomplete="off" />
  <div class="wi-assignee-list"></div>
</div>
<script src="/assets/js/work-items-inline.js" defer></script>

<?php if ($canManage): ?>
<?php $projId = (int) ($project['id'] ?? 0); ?>
<div class="modal-overlay" id="proj-edit-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title">Edit project</h3>
      <button type="button" class="btn-icon" onclick="document.getElementById('proj-edit-modal').classList.remove('open')" aria-label="Tutup">&times;</button>
    </div>
    <form id="proj-edit-update-form" method="post" action="/projects/<?= $projId ?>/update" class="modal-body">
      <?= csrf_field() ?>
      <div class="form-stack">
        <label>Nama <input type="text" name="name" class="form-control" value="<?= esc($project['name'] ?? '') ?>" required></label>
        <label>Deskripsi <textarea name="description" class="form-control" rows="3"><?= esc($project['description'] ?? '') ?></textarea></label>
        <label>Status
          <select name="status" class="form-control">
            <?php foreach (['active', 'completed', 'on_hold'] as $s): ?>
            <option value="<?= esc($s) ?>" <?= ($project['status'] ?? '') === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </form>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('proj-edit-modal').classList.remove('open')">Batal</button>
      <button type="submit" class="btn btn-primary" form="proj-edit-update-form">Simpan</button>
    </div>
    <div class="cp-modal-danger-zone">
      <form method="post" action="/projects/<?= $projId ?>/delete" onsubmit="return confirm('Hapus project ini? Tindakan ini tidak dapat dibatalkan.');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost cp-btn-danger-text">Hapus project</button>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  document.addEventListener('taskflow:ajax-form-success', function (ev) {
    const d = ev.detail;
    if (!d || !d.stay_on_page || !d.project) return;
    const p = d.project;
    const titleEl = document.querySelector('.wi-master-pane .page-header .page-title');
    if (titleEl && p.name !== undefined) titleEl.textContent = p.name;
    const subEl = document.querySelector('.wi-master-pane .page-header .page-sub');
    if (subEl && p.page_sub) subEl.textContent = p.page_sub;
    try {
      document.title = (p.name || document.title) + ' — TaskFlow';
    } catch (e) {}
    const form = document.getElementById('proj-edit-update-form');
    if (form) {
      const n = form.querySelector('input[name="name"]');
      if (n && p.name !== undefined) n.value = p.name;
      const desc = form.querySelector('textarea[name="description"]');
      if (desc && p.description !== undefined) desc.value = p.description;
      const sel = form.querySelector('select[name="status"]');
      if (sel && p.status !== undefined) sel.value = p.status;
    }
    document.getElementById('proj-edit-modal')?.classList.remove('open');
  });
})();
</script>
<?php endif; ?>
