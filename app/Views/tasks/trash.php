<?php
/**
 * @var array $tasks  soft-deleted tasks
 */
?>

<link rel="stylesheet" href="/assets/css/pages/tasks-trash.css" />

<div class="p-trash-head">
  <p class="p-trash-desc">
    Hanya <strong>task internal</strong> (<a href="/tasks">/tasks</a>) yang muncul di sini. Work item proyek tidak menggunakan trash ini.
  </p>
</div>

<div class="card">
  <?php if (empty($tasks)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i data-lucide="trash-2"></i></div>
      <div class="empty-title">Trash kosong</div>
      <div class="empty-desc">Tidak ada task yang dihapus.</div>
    </div>
  <?php else: ?>
    <div class="p-trash-toolbar">
      <div class="p-trash-selected">
        <span id="selected-count">0</span> dipilih
      </div>
      <div class="p-trash-actions">
        <select id="bulk-action-select" class="form-control p-trash-select" required>
          <option value="">Bulk action...</option>
          <option value="restore">Restore terpilih</option>
          <option value="force_delete">Hapus permanen terpilih</option>
        </select>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitBulkAction()">Terapkan</button>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="p-checkbox-col">
              <input type="checkbox" id="check-all-trash">
            </th>
            <th>#</th>
            <th>Status</th>
            <th>Dihapus pada</th>
            <th class="text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $task): ?>
            <tr>
              <td>
                <input type="checkbox" class="trash-row-check" name="task_ids[]" value="<?= (int) $task['id'] ?>">
              </td>
              <td class="p-cell-id">
                #<?= str_pad($task['id'], 4, '0', STR_PAD_LEFT) ?>
              </td>
              <td>
                <span class="badge badge-<?= $task['status'] ?>">
                  <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                </span>
              </td>
              <td class="p-cell-date">
                <?= esc(date('d M Y H:i', strtotime($task['deleted_at']))) ?>
              </td>
              <td class="action-cell">
                <form method="POST" action="/tasks/<?= $task['id'] ?>/restore" class="u-inline-form"
                      data-confirm="Restore task ini?"
                      data-confirm-title="Restore task?"
                      data-confirm-ok-text="Restore">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-ghost btn-sm">
                    <i data-lucide="rotate-ccw" class="u-icon-xs"></i>
                    Restore
                  </button>
                </form>
                <form method="POST" action="/tasks/<?= $task['id'] ?>/force-delete" class="u-inline-form"
                      data-confirm="Hapus permanen task ini? Data tidak bisa dikembalikan."
                      data-confirm-title="Hapus permanen?"
                      data-confirm-ok-text="Hapus Permanen"
                      data-confirm-ok-variant="danger">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-danger btn-sm">
                    <i data-lucide="trash-2" class="u-icon-xs"></i>
                    Permanen
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
          'pager'       => $pager,
          'queryParams' => $pagerQuery ?? [],
          'uriPath'     => $pagerUriPath ?? '/tasks/trash',
      ]) ?>
    <?php endif; ?>
    <form method="POST" action="/tasks/trash/bulk" id="trash-bulk-form" class="u-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" id="bulk-action-input">
      <div id="bulk-ids-wrap"></div>
    </form>
  <?php endif; ?>
</div>

<script>
const checkAll = document.getElementById('check-all-trash');
const rowChecks = [...document.querySelectorAll('.trash-row-check')];
const selectedCount = document.getElementById('selected-count');

function syncSelectedCount() {
  if (!selectedCount) return;
  selectedCount.textContent = String(rowChecks.filter(c => c.checked).length);
}

if (checkAll) {
  checkAll.addEventListener('change', function () {
    rowChecks.forEach(c => { c.checked = this.checked; });
    syncSelectedCount();
  });
}

rowChecks.forEach(c => c.addEventListener('change', () => {
  if (checkAll) {
    checkAll.checked = rowChecks.length > 0 && rowChecks.every(i => i.checked);
  }
  syncSelectedCount();
}));

async function submitBulkAction() {
  const action = document.getElementById('bulk-action-select')?.value || '';
  const selected = rowChecks.filter(c => c.checked).length;
  if (selected < 1) {
    alert('Pilih minimal 1 task.');
    return;
  }
  if (action === 'restore') {
    const ok = await appConfirm({
      head: 'Konfirmasi Bulk',
      title: 'Restore task terpilih?',
      message: `Restore ${selected} task terpilih?`,
      okText: 'Restore',
    });
    if (!ok) return;
  } else if (action === 'force_delete') {
    const ok = await appConfirm({
      head: 'Konfirmasi Bulk',
      title: 'Hapus permanen task terpilih?',
      message: `Hapus permanen ${selected} task terpilih? Data tidak bisa dikembalikan.`,
      okText: 'Hapus Permanen',
      okVariant: 'danger',
    });
    if (!ok) return;
  } else {
    alert('Pilih aksi bulk terlebih dahulu.');
    return;
  }

  const bulkForm = document.getElementById('trash-bulk-form');
  const actionInput = document.getElementById('bulk-action-input');
  const idsWrap = document.getElementById('bulk-ids-wrap');
  actionInput.value = action;
  idsWrap.innerHTML = '';
  rowChecks.filter(c => c.checked).forEach((c) => {
    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = 'task_ids[]';
    i.value = c.value;
    idsWrap.appendChild(i);
  });
  bulkForm.requestSubmit();
}
</script>
