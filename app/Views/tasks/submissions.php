<?php
/**
 * @var array $submissions
 * @var array $colConfig
 * @var array $statusMap
 * @var bool  $pivotEnabled
 */
$colConfig    = $colConfig ?? ['groups' => [], 'platforms' => [], 'fileTypes' => []];
$statusMap    = $statusMap ?? [];
$pivotEnabled = $pivotEnabled ?? false;

$groups = $colConfig['groups'] ?? [];

$pivotStatusOptions = [];
if ($pivotEnabled) {
    $pivotCfg = config('UploadPivotStatuses');
    $pivotStatusOptions = $pivotCfg instanceof \Config\UploadPivotStatuses ? $pivotCfg->options : [];
}

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
if (!function_exists('optBadgeColor')) {
  function optBadgeColor(string $val, array $palette): array {
    $h = 0;
    for ($i = 0, $l = strlen($val); $i < $l; $i++) {
      $h = ($h * 31 + ord($val[$i])) % count($palette);
    }
    return $palette[$h];
  }
}
if (!function_exists('optAccountBadgeColor')) {
  function optAccountBadgeColor(string $account, array $palette): array {
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $account)));
    return optBadgeColor($normalized, $palette);
  }
}
?>

<link rel="stylesheet" href="/assets/css/pages/tasks-submissions.css" />
<?php if ($pivotEnabled): ?>
<link rel="stylesheet" href="/assets/css/pages/upload-status-pivot.css" />
<?php endif; ?>

<div class="p-submissions-head">
  <div>
    <p class="p-submissions-desc">
      Semua task yang sudah disetor<?php if ($pivotEnabled): ?> — baris pivot menampilkan <strong>tanggal setor</strong>, product/theme, kategori, PIC, account, dan <strong>link setor</strong> (mirror data submission), lalu kolom status mengikuti <strong>grup produk</strong>, platform, dan tipe file di <a href="/settings/upload-config">Konfigurasi upload</a>. Awalnya sel status <strong>kosong (—)</strong> sampai dipilih; pilih dari <strong>dropdown</strong> (disimpan tanpa reload). Pilih <strong>—</strong> lagi untuk mengosongkan.<?php endif; ?>
    </p>
  </div>
  <span class="p-submissions-count"><?= (int)($pager['total'] ?? count($submissions)) ?> record</span>
</div>

<?php if (empty($submissions)): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-icon"><i data-lucide="inbox"></i></div>
    <div class="empty-title">Belum ada submission</div>
    <div class="empty-desc">Centang "Setor" pada task untuk menyimpannya di sini.</div>
  </div>
</div>
<?php else: ?>

<?php if ($pivotEnabled): ?>
<div class="card pivot-card mb-4">
  <div class="table-wrap pivot-wrap">
    <table class="pivot-table">
      <thead>
        <tr class="pivot-head-group">
          <th rowspan="3" class="pivot-col-task">Task</th>
          <th rowspan="3" class="pivot-col-info pivot-col-date">Tanggal</th>
          <th rowspan="3" class="pivot-col-info pivot-col-product">Product / Theme</th>
          <th rowspan="3" class="pivot-col-info pivot-col-category">Category</th>
          <th rowspan="3" class="pivot-col-info pivot-col-pic">PIC</th>
          <th rowspan="3" class="pivot-col-info pivot-col-account">Account</th>
          <th rowspan="3" class="pivot-col-info pivot-col-link">Link Setor</th>
          <?php foreach ($groups as $g): ?>
            <th colspan="<?= (int) ($g['colspan'] ?? 1) ?>" class="pivot-group-header"><?= esc($g['name'] ?? '') ?></th>
          <?php endforeach ?>
          <th rowspan="3" class="pivot-col-actions">Aksi</th>
        </tr>
        <tr class="pivot-head-platform">
          <?php foreach ($groups as $g):
            $gHp = (int) ($g['has_platform'] ?? 1) === 1;
            $gHf = (int) ($g['has_file_types'] ?? 0) === 1;
            $gPlatforms  = $g['pivot_platforms'] ?? [];
            $gFileTypes  = $g['pivot_file_types'] ?? [];
            $nFt         = max(1, count($gFileTypes));
            if ($gHp && $gHf):
              foreach ($gPlatforms as $p): ?>
                <th colspan="<?= $nFt ?>" class="pivot-platform-header"><?= esc($p['abbr'] ?? '') ?></th>
              <?php endforeach;
              if ($gPlatforms === []): ?>
                <th colspan="<?= $nFt ?>" class="pivot-platform-header pivot-no-ft" title="Belum ada platform dipilih untuk grup ini">—</th>
              <?php endif;
            elseif ($gHp && ! $gHf):
              if ($gPlatforms === []): ?>
                <th colspan="1" class="pivot-platform-header pivot-no-ft" title="Belum ada platform dipilih untuk grup ini">—</th>
              <?php else:
                foreach ($gPlatforms as $p): ?>
                <th colspan="1" class="pivot-platform-header"><?= esc($p['abbr'] ?? '') ?></th>
                <?php endforeach;
              endif;
            elseif (! $gHp && $gHf):
              if ($gFileTypes === []): ?>
                <th colspan="1" class="pivot-platform-header pivot-no-ft" title="Belum ada tipe file dipilih untuk grup ini">—</th>
              <?php else:
                foreach ($gFileTypes as $ft): ?>
                <th colspan="1" class="pivot-platform-header"><?= esc($ft['abbr'] ?? '') ?></th>
                <?php endforeach;
              endif;
            else: ?>
              <th colspan="1" class="pivot-platform-header pivot-no-ft">—</th>
            <?php endif;
          endforeach ?>
        </tr>
        <tr class="pivot-head-filetype">
          <?php foreach ($groups as $g):
            $gHp = (int) ($g['has_platform'] ?? 1) === 1;
            $gHf = (int) ($g['has_file_types'] ?? 0) === 1;
            $gPlatforms = $g['pivot_platforms'] ?? [];
            $gFileTypes   = $g['pivot_file_types'] ?? [];
            if ($gHp && $gHf):
              if ($gPlatforms === []):
                foreach ($gFileTypes as $ft): ?>
                  <th class="pivot-filetype-header"><?= esc($ft['abbr'] ?? '') ?></th>
                <?php endforeach;
                if ($gFileTypes === []): ?>
                  <th class="pivot-filetype-header pivot-no-ft">—</th>
                <?php endif;
              else:
                foreach ($gPlatforms as $p):
                  foreach ($gFileTypes as $ft): ?>
                  <th class="pivot-filetype-header"><?= esc($ft['abbr'] ?? '') ?></th>
                  <?php endforeach;
                endforeach;
                if ($gFileTypes === []):
                  foreach ($gPlatforms as $p): ?>
                  <th class="pivot-filetype-header pivot-no-ft">—</th>
                  <?php endforeach;
                endif;
              endif;
            elseif ($gHp && ! $gHf):
              if ($gPlatforms === []): ?>
                <th class="pivot-filetype-header pivot-no-ft">—</th>
              <?php else:
                foreach ($gPlatforms as $p): ?>
                <th class="pivot-filetype-header pivot-no-ft">—</th>
                <?php endforeach;
              endif;
            elseif (! $gHp && $gHf):
              if ($gFileTypes === []): ?>
                <th class="pivot-filetype-header pivot-no-ft">—</th>
              <?php else:
                foreach ($gFileTypes as $ft): ?>
                <th class="pivot-filetype-header pivot-no-ft">—</th>
                <?php endforeach;
              endif;
            else: ?>
              <th class="pivot-filetype-header pivot-no-ft">—</th>
            <?php endif;
          endforeach ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $row): ?>
          <?php $sid = (int) ($row['submission_id'] ?? 0); ?>
          <tr data-task-id="<?= (int) ($row['id'] ?? 0) ?>" data-submission-id="<?= $sid ?>" data-setor-updated-at="<?= esc((string) ($row['setor_updated_at'] ?? ''), 'attr') ?>">
            <td class="pivot-col-task">
              <a href="/tasks?highlight_id=<?= (int) ($row['id'] ?? 0) ?>" class="p-task-link" title="Buka di daftar task">
                #<?= str_pad((string) ($row['id'] ?? '0'), 4, '0', STR_PAD_LEFT) ?>
              </a>
            </td>
            <td class="pivot-col-info pivot-col-date p-sub-date">
              <?= ! empty($row['submission_date']) ? esc(date('d M Y', strtotime((string) $row['submission_date']))) : '—' ?>
            </td>
            <td class="pivot-col-info pivot-col-product">
              <span title="<?= esc($row['product_name'] ?? '') ?>">
                <?= esc(function_exists('mb_strimwidth') ? mb_strimwidth((string) ($row['product_name'] ?? '—'), 0, 22, '…') : substr((string) ($row['product_name'] ?? '—'), 0, 22)) ?>
              </span>
            </td>
            <td class="pivot-col-info pivot-col-category">
              <?php if (! empty($row['category'])): ?>
                <?php $catColor = optBadgeColor((string) $row['category'], $colorPalette); ?>
                <span class="pivot-pill-ghost"
                      style="color:<?= esc($catColor['text'], 'attr') ?>;background:color-mix(in srgb, <?= esc($catColor['bg'], 'attr') ?> 52%, var(--surface));border-color:color-mix(in srgb, <?= esc($catColor['text'], 'attr') ?> 38%, transparent)">
                  <span class="pivot-pill-dot" style="background:<?= esc($catColor['text'], 'attr') ?>"></span>
                  <?= esc($row['category']) ?>
                </span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="pivot-col-info pivot-col-pic">
              <?php if (! empty($row['pic_name'])): ?>
                <?php $picColor = optBadgeColor((string) $row['pic_name'], $colorPalette); ?>
                <span class="pivot-pill-ghost"
                      style="color:<?= esc($picColor['text'], 'attr') ?>;background:color-mix(in srgb, <?= esc($picColor['bg'], 'attr') ?> 52%, var(--surface));border-color:color-mix(in srgb, <?= esc($picColor['text'], 'attr') ?> 38%, transparent)">
                  <span class="pivot-pill-dot" style="background:<?= esc($picColor['text'], 'attr') ?>"></span>
                  <?= esc($row['pic_name']) ?>
                </span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="pivot-col-info pivot-col-account">
              <?php if (! empty($row['account'])): ?>
                <?php $accColor = optAccountBadgeColor((string) $row['account'], $colorPalette); ?>
                <span class="p-account-chip pivot-pill-account" style="background:<?= esc($accColor['bg'], 'attr') ?>;color:<?= esc($accColor['text'], 'attr') ?>">
                  <?= esc($row['account']) ?>
                </span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="pivot-col-info pivot-col-link">
              <?php
                $rawLink = trim((string) ($row['link_setor'] ?? ''));
                $hrefLink = ($rawLink !== '' && ! preg_match('#^https?://#i', $rawLink)) ? '//' . $rawLink : $rawLink;
              ?>
              <?php if ($rawLink !== ''): ?>
                <a href="<?= esc($hrefLink) ?>" target="_blank" rel="noopener noreferrer" class="p-link">
                  <i class="fa-solid fa-up-right-from-square u-icon-xs" aria-hidden="true"></i> Link
                </a>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <?php foreach ($groups as $g):
              $gid = (int) ($g['id'] ?? 0);
              $gHp = (int) ($g['has_platform'] ?? 1) === 1;
              $gHf = (int) ($g['has_file_types'] ?? 0) === 1;
              $gPlatforms = $g['pivot_platforms'] ?? [];
              $gFileTypes   = $g['pivot_file_types'] ?? [];
              if ($gHp && $gHf):
                if ($gPlatforms === []):
                  if ($gFileTypes === []): ?>
                    <td class="pivot-muted-cell" title="Atur platform &amp; tipe file untuk grup ini di Upload Config">—</td>
                  <?php else:
                    foreach ($gFileTypes as $ft): ?>
                    <td class="pivot-muted-cell" title="Pilih platform untuk grup ini di Upload Config">—</td>
                    <?php endforeach;
                  endif;
                elseif ($gFileTypes === []):
                  foreach ($gPlatforms as $p):
                    $pid = (int) ($p['id'] ?? 0); ?>
                    <td class="pivot-muted-cell" title="Pilih tipe file untuk grup ini di Upload Config">—</td>
                  <?php endforeach;
                else:
                  foreach ($gPlatforms as $p):
                    $pid = (int) ($p['id'] ?? 0);
                    foreach ($gFileTypes as $ft):
                      $ftid = (int) ($ft['id'] ?? 0);
                      $curStat = $statusMap[$sid][$gid][$pid][$ftid] ?? '';
                      $titleBase = ($g['name'] ?? '') . ' / ' . ($p['abbr'] ?? '') . ' / ' . ($ft['abbr'] ?? '');
                      ?>
                    <td class="pivot-cell pivot-cell-select">
                      <?= view('tasks/partials/pivot_status_select', [
                          'pivotStatusOptions' => $pivotStatusOptions,
                          'curStat'            => $curStat,
                          'gid'                => $gid,
                          'pid'                => $pid,
                          'ftid'               => $ftid,
                          'titleBase'          => $titleBase,
                      ]) ?>
                    </td>
                    <?php
                    endforeach;
                  endforeach;
                endif;
              elseif ($gHp && ! $gHf):
                if ($gPlatforms === []): ?>
                  <td class="pivot-muted-cell" title="Pilih platform untuk grup ini di Upload Config">—</td>
                <?php else:
                  foreach ($gPlatforms as $p):
                    $pid = (int) ($p['id'] ?? 0);
                    $curStat = $statusMap[$sid][$gid][$pid]['_'] ?? '';
                    $titleBase = ($g['name'] ?? '') . ' / ' . ($p['abbr'] ?? '');
                    ?>
                    <td class="pivot-cell pivot-cell-select">
                      <?= view('tasks/partials/pivot_status_select', [
                          'pivotStatusOptions' => $pivotStatusOptions,
                          'curStat'            => $curStat,
                          'gid'                => $gid,
                          'pid'                => $pid,
                          'ftid'               => null,
                          'titleBase'          => $titleBase,
                      ]) ?>
                    </td>
                    <?php
                  endforeach;
                endif;
              elseif (! $gHp && $gHf):
                if ($gFileTypes === []): ?>
                  <td class="pivot-muted-cell" title="Pilih tipe file untuk grup ini di Upload Config">—</td>
                <?php else:
                  foreach ($gFileTypes as $ft):
                    $ftid = (int) ($ft['id'] ?? 0);
                    $curStat = $statusMap[$sid][$gid][0][$ftid] ?? '';
                    $titleBase = ($g['name'] ?? '') . ' / ' . ($ft['abbr'] ?? '');
                    ?>
                    <td class="pivot-cell pivot-cell-select">
                      <?= view('tasks/partials/pivot_status_select', [
                          'pivotStatusOptions' => $pivotStatusOptions,
                          'curStat'            => $curStat,
                          'gid'                => $gid,
                          'pid'                => 0,
                          'ftid'               => $ftid,
                          'titleBase'          => $titleBase,
                      ]) ?>
                    </td>
                    <?php
                  endforeach;
                endif;
              else:
                $curStat = $statusMap[$sid][$gid][0]['_'] ?? '';
                $titleBase = (string) ($g['name'] ?? '');
                ?>
                <td class="pivot-cell pivot-cell-select">
                  <?= view('tasks/partials/pivot_status_select', [
                      'pivotStatusOptions' => $pivotStatusOptions,
                      'curStat'            => $curStat,
                      'gid'                => $gid,
                      'pid'                => 0,
                      'ftid'               => null,
                      'titleBase'          => $titleBase,
                  ]) ?>
                </td>
              <?php endif;
            endforeach; ?>
            <td class="pivot-col-actions action-cell action-cell-compact">
              <button type="button" class="btn-icon edit-submission-btn" data-task-id="<?= (int) ($row['id'] ?? 0) ?>" title="Edit submission"><i data-lucide="pencil"></i></button>
              <button type="button" class="btn-icon btn-icon-danger unsetor-btn" data-task-id="<?= (int) ($row['id'] ?? 0) ?>" title="Batalkan setor"><i data-lucide="trash-2"></i></button>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
  <?php if (! empty($pager)): ?>
  <div class="card pivot-pagination-wrap mb-4">
    <?= view('components/table_pagination', [
        'pager'       => $pager,
        'queryParams' => $pagerQuery ?? [],
        'uriPath'     => $pagerUriPath ?? '/tasks/submissions',
    ]) ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (! $pivotEnabled): ?>
<div class="card">
  <div class="p-submissions-head" style="margin-bottom:12px;padding:12px 16px 0;">
    <strong style="font-size:14px;">Detail &amp; link</strong>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Task #</th>
          <th>Tanggal</th>
          <th>Product / Theme</th>
          <th>Category</th>
          <th>PIC</th>
          <th>Account</th>
          <th>Link Setor</th>
          <th class="text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $row): ?>
          <tr data-task-id="<?= (int) ($row['id'] ?? 0) ?>" data-setor-updated-at="<?= esc((string) ($row['setor_updated_at'] ?? ''), 'attr') ?>">
            <td>
              <a href="/tasks?highlight_id=<?= (int) ($row['id'] ?? 0) ?>" class="p-task-link">
                #<?= str_pad((string) ($row['id'] ?? '0'), 4, '0', STR_PAD_LEFT) ?>
              </a>
            </td>
            <td class="p-sub-date">
              <?= !empty($row['submission_date']) ? esc(date('d M Y', strtotime((string) $row['submission_date']))) : '—' ?>
            </td>
            <td>
              <?php if (!empty($row['product_name'])): ?>
                <span title="<?= esc($row['product_name']) ?>">
                  <?= esc(function_exists('mb_strimwidth') ? mb_strimwidth((string) $row['product_name'], 0, 40, '…') : substr((string) $row['product_name'], 0, 40)) ?>
                </span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['category'])): ?>
                <?php $catColor = optBadgeColor((string) $row['category'], $colorPalette); ?>
                <span class="badge p-category-badge" style="background:<?= esc($catColor['bg'], 'attr') ?>;color:<?= esc($catColor['text'], 'attr') ?>"><?= esc($row['category']) ?></span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['pic_name'])): ?>
                <?php $picColor = optBadgeColor((string) $row['pic_name'], $colorPalette); ?>
                <span class="badge p-category-badge" style="background:<?= esc($picColor['bg'], 'attr') ?>;color:<?= esc($picColor['text'], 'attr') ?>"><?= esc($row['pic_name']) ?></span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="p-account">
              <?php if (!empty($row['account'])): ?>
                <?php $accColor = optAccountBadgeColor((string) $row['account'], $colorPalette); ?>
                <span class="p-account-chip" style="background:<?= esc($accColor['bg'], 'attr') ?>;color:<?= esc($accColor['text'], 'attr') ?>"><?= esc($row['account']) ?></span>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $rawLink = trim((string) ($row['link_setor'] ?? ''));
                $hrefLink = ($rawLink !== '' && !preg_match('#^https?://#i', $rawLink)) ? '//' . $rawLink : $rawLink;
              ?>
              <?php if ($rawLink !== ''): ?>
                <a href="<?= esc($hrefLink) ?>" target="_blank" rel="noopener" class="p-link">
                  <i class="fa-solid fa-up-right-from-square u-icon-xs" aria-hidden="true"></i> Link
                </a>
              <?php else: ?>
                <span class="p-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="action-cell action-cell-compact">
              <button type="button" class="btn-icon edit-submission-btn" data-task-id="<?= (int) ($row['id'] ?? 0) ?>" title="Edit submission"><i data-lucide="pencil"></i></button>
              <button type="button" class="btn-icon btn-icon-danger unsetor-btn" data-task-id="<?= (int) ($row['id'] ?? 0) ?>" title="Batalkan setor"><i data-lucide="trash-2"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (! empty($pager)): ?>
    <?= view('components/table_pagination', [
        'pager'       => $pager,
        'queryParams' => $pagerQuery ?? [],
        'uriPath'     => $pagerUriPath ?? '/tasks/submissions',
    ]) ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (! empty($submissions)): ?>
<div id="subEditSubmissionModal" class="p-sub-overlay overlay-dark" aria-hidden="true">
  <div class="modal modal-setor p-sub-edit-modal" role="dialog" aria-labelledby="subEditSubmissionTitle">
    <div class="modal-header">
      <h3 id="subEditSubmissionTitle" class="modal-title">Edit submission</h3>
      <button type="button" id="subEditSubmissionClose" class="btn-icon btn-icon-md" aria-label="Tutup">
        <i data-lucide="x" class="icon-sm"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="p-sub-edit-grid">
        <div class="form-group mb-0">
          <label class="form-label" for="subEditProductName">Product / theme</label>
          <input id="subEditProductName" type="text" class="form-control" placeholder="Product name…" autocomplete="off">
        </div>
        <div class="form-group mb-0">
          <label class="form-label" for="subEditLink">Link setor</label>
          <input id="subEditLink" type="url" class="form-control" placeholder="https://…" autocomplete="off">
        </div>
      </div>
      <div class="form-group p-sub-edit-cat">
        <label class="form-label" for="subEditCategory">Category</label>
        <input id="subEditCategory" type="text" class="form-control" placeholder="Category…" autocomplete="off">
      </div>
      <div class="setor-meta-grid p-sub-edit-meta">
        <div>
          <div class="stat-label">Account</div>
          <div id="subEditAccount" class="setor-meta-value">—</div>
        </div>
        <div>
          <div class="stat-label">PIC</div>
          <div id="subEditPic" class="setor-meta-value">—</div>
        </div>
        <div>
          <div class="stat-label">Tanggal</div>
          <div id="subEditDate" class="setor-meta-value">—</div>
        </div>
      </div>
    </div>
    <div class="modal-footer p-sub-edit-footer">
      <button type="button" id="subEditCancel" class="btn btn-ghost">Batal</button>
      <a href="/tasks" id="subEditOpenTask" class="btn btn-secondary">Buka task penuh</a>
      <span class="p-sub-edit-footer-spacer" aria-hidden="true"></span>
      <button type="button" id="subEditUnsetor" class="btn btn-danger">Batalkan setor</button>
      <button type="button" id="subEditSave" class="btn btn-primary">Simpan</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  const CSRF_NAME = <?= json_encode(csrf_token()) ?>;
  const CSRF_HASH_PAGE = <?= json_encode(csrf_hash()) ?>;

  /** Token mutates after each POST (Security.regenerate); sync via window.appCsrf (layout) or hidden fields. */
  function csrfValue() {
    const el = document.querySelector('[name="' + CSRF_NAME + '"]');
    if (el && el.value) return el.value;
    if (typeof getAppCsrf === 'function') return getAppCsrf().val;
    return CSRF_HASH_PAGE;
  }

  function applyNewCsrf(data) {
    if (!data || !data.csrf) return;
    if (typeof updateAppCsrf === 'function') updateAppCsrf(data.csrf);
    document.querySelectorAll('[name="' + CSRF_NAME + '"]').forEach((inp) => { inp.value = data.csrf; });
  }

  function jsonHeaders() {
    const v = csrfValue();
    return {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': v,
    };
  }

  document.querySelectorAll('.unsetor-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const ok = await appConfirm({
        head: 'Konfirmasi',
        title: 'Batalkan setor?',
        message: 'Batalkan setor task ini? Data submission akan dihapus dari tb_submissions.',
        okText: 'Batalkan',
        okVariant: 'danger',
      });
      if (!ok) return;
      const id = this.dataset.taskId;
      this.style.opacity = '.4';

      const res = await fetch('/tasks/' + id + '/setor', {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ setor: false, [CSRF_NAME]: csrfValue() }),
      });
      let data = {};
      try { data = await res.json(); } catch (e) { data = {}; }
      if (data.success) {
        applyNewCsrf(data);
        showToast('Submission dihapus.', 'success');
        this.closest('tr').style.opacity = '.3';
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Gagal: ' + (data.message || ('HTTP ' + res.status)), 'error');
        this.style.opacity = '';
      }
    });
  });

  <?php if ($pivotEnabled): ?>
  function syncPivotStatusEmpty(sel) {
    const wrap = sel.closest('.pivot-status-wrap');
    if (!wrap) return;
    wrap.classList.toggle('is-empty', sel.value === '');
  }
  document.querySelectorAll('.pivot-status-wrap').forEach((wrap) => {
    wrap.addEventListener('mousedown', (e) => {
      const sel = wrap.querySelector('.pivot-status-select');
      if (!sel || !wrap.classList.contains('is-empty')) return;
      if (e.target === sel || sel.contains(e.target)) return;
      e.preventDefault();
      sel.focus();
    });
  });
  document.querySelectorAll('.pivot-status-select').forEach((sel) => {
    sel.dataset.prevValue = sel.value;
    syncPivotStatusEmpty(sel);
    sel.addEventListener('change', async function () {
      const tr = this.closest('tr');
      const taskId = tr.dataset.taskId;
      const subId = parseInt(tr.dataset.submissionId, 10);
      const groupId = parseInt(this.dataset.groupId, 10);
      let platformId = parseInt(this.dataset.platformId, 10);
      if (Number.isNaN(platformId)) platformId = 0;
      const ftRaw = this.dataset.filetypeId;
      const fileTypeId = (ftRaw && ftRaw !== '') ? parseInt(ftRaw, 10) : null;
      const newStatus = this.value;
      const prev = this.dataset.prevValue === undefined ? newStatus : this.dataset.prevValue;

      try {
        const res = await fetch('/tasks/' + taskId + '/upload-status', {
          method: 'POST',
          headers: jsonHeaders(),
          body: JSON.stringify({
            submission_id: subId,
            group_id: groupId,
            platform_id: platformId,
            file_type_id: fileTypeId,
            status: newStatus,
            [CSRF_NAME]: csrfValue(),
          }),
        });
        let data = {};
        try { data = await res.json(); } catch (e) { data = {}; }
        if (!res.ok || !data.success) {
          throw new Error(data.message || ('HTTP ' + res.status));
        }
        applyNewCsrf(data);
        this.dataset.prevValue = newStatus;
        syncPivotStatusEmpty(this);
      } catch (err) {
        this.value = prev;
        syncPivotStatusEmpty(this);
        if (typeof showToast === 'function') showToast('Gagal update status: ' + (err.message || err), 'error');
      }
    });
  });
  <?php endif; ?>

  <?php if (! empty($submissions)): ?>
  const subModal = document.getElementById('subEditSubmissionModal');
  if (subModal) {
    let subEditTaskId = null;
    const subEl = (id) => document.getElementById(id);
    function openSubEditModal() {
      subModal.classList.add('is-open');
      subModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
    function closeSubEditModal() {
      subModal.classList.remove('is-open');
      subModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      subEditTaskId = null;
    }
    subEl('subEditSubmissionClose').addEventListener('click', closeSubEditModal);
    subEl('subEditCancel').addEventListener('click', closeSubEditModal);
    subModal.addEventListener('click', (e) => {
      if (e.target === subModal) closeSubEditModal();
    });

    document.querySelectorAll('.edit-submission-btn').forEach((btn) => {
      btn.addEventListener('click', async function () {
        subEditTaskId = this.dataset.taskId;
        if (!subEditTaskId) return;
        const tr = document.querySelector('tr[data-task-id="' + subEditTaskId + '"]');
        const openTask = subEl('subEditOpenTask');
        if (openTask) openTask.href = '/tasks?edit_id=' + encodeURIComponent(subEditTaskId);
        openSubEditModal();
        try {
          const res = await fetch('/tasks/' + subEditTaskId + '/setor-data', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const data = await res.json();
          if (data.success) {
            const f = data.fields || {};
            const s = data.sub || {};
            subEl('subEditProductName').value = s.product_name || f.theme || '';
            subEl('subEditLink').value = s.link_setor || f.link_setor || '';
            subEl('subEditCategory').value = s.category || f.artboard || '';
            subEl('subEditAccount').textContent = f.account || s.account || '—';
            subEl('subEditPic').textContent = f.pic_name || s.pic_name || '—';
            const dRaw = f.date || s.date;
            if (dRaw) {
              const d = String(dRaw);
              subEl('subEditDate').textContent = d.length >= 10 ? d.slice(0, 10) : d;
            } else {
              subEl('subEditDate').textContent = '—';
            }
          }
        } catch (e) { /* keep fields */ }
        if (typeof refreshLucide === 'function') refreshLucide(subModal);
      });
    });

    subEl('subEditSave').addEventListener('click', async function () {
      if (!subEditTaskId) return;
      const tr = document.querySelector('tr[data-task-id="' + subEditTaskId + '"]');
      const expected = tr && tr.dataset.setorUpdatedAt ? tr.dataset.setorUpdatedAt : null;
      const saveBtn = this;
      saveBtn.disabled = true;
      const prevLabel = saveBtn.textContent;
      saveBtn.textContent = 'Menyimpan…';
      try {
        const res = await fetch('/tasks/' + subEditTaskId + '/setor', {
          method: 'POST',
          headers: jsonHeaders(),
          body: JSON.stringify({
            setor: true,
            product_name: subEl('subEditProductName').value,
            link_setor: subEl('subEditLink').value,
            category: subEl('subEditCategory').value,
            expected_setor_updated_at: expected,
            [CSRF_NAME]: csrfValue(),
          }),
        });
        let data = {};
        try { data = await res.json(); } catch (e) { data = {}; }
        applyNewCsrf(data);
        if (res.status === 409 && data.conflict) {
          if (typeof showToast === 'function') showToast(data.message || 'Data setor sudah berubah.', 'error');
          closeSubEditModal();
          setTimeout(() => location.reload(), 400);
          return;
        }
        if (!res.ok || !data.success) {
          throw new Error(data.message || ('HTTP ' + res.status));
        }
        if (typeof showToast === 'function') showToast('Submission disimpan.', 'success');
        closeSubEditModal();
        location.reload();
      } catch (err) {
        if (typeof showToast === 'function') showToast(err.message || 'Gagal menyimpan', 'error');
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = prevLabel;
      }
    });

    subEl('subEditUnsetor').addEventListener('click', async function () {
      if (!subEditTaskId) return;
      const ok = await appConfirm({
        head: 'Konfirmasi',
        title: 'Batalkan setor?',
        message: 'Batalkan setor task ini? Data submission akan dihapus dari daftar setor.',
        okText: 'Batalkan',
        okVariant: 'danger',
      });
      if (!ok) return;
      const tr = document.querySelector('tr[data-task-id="' + subEditTaskId + '"]');
      const expected = tr && tr.dataset.setorUpdatedAt ? tr.dataset.setorUpdatedAt : null;
      const uBtn = this;
      uBtn.disabled = true;
      try {
        const res = await fetch('/tasks/' + subEditTaskId + '/setor', {
          method: 'POST',
          headers: jsonHeaders(),
          body: JSON.stringify({
            setor: false,
            expected_setor_updated_at: expected,
            [CSRF_NAME]: csrfValue(),
          }),
        });
        let data = {};
        try { data = await res.json(); } catch (e) { data = {}; }
        applyNewCsrf(data);
        if (data.success) {
          if (typeof showToast === 'function') showToast('Submission dihapus.', 'success');
          closeSubEditModal();
          location.reload();
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'Gagal', 'error');
        }
      } finally {
        uBtn.disabled = false;
      }
    });
  }
  <?php endif; ?>
})();
</script>
