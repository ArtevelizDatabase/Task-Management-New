<?php
/**
 * @var array  $fields    all fields ordered
 * @var array  $subCols   column names from tb_submissions
 * @var array  $settings  app setting key→bool map
 * @var array  $roles     available role slugs
 */
$settings = $settings ?? [];
$roles = $roles ?? [];

$typeLabels = [
  'text'     => 'Text',
  'date'     => 'Date',
  'select'   => 'Select',
  'boolean'  => 'Boolean',
  'textarea' => 'Textarea',
  'richtext' => 'Rich Text',
  'number'   => 'Number',
  'email'    => 'Email',
];
$typeIcons = [
  'text'     => 'type',
  'date'     => 'calendar-days',
  'select'   => 'list',
  'boolean'  => 'check-square',
  'textarea' => 'align-left',
  'richtext' => 'square-pen',
  'number'   => 'hash',
  'email'    => 'at-sign',
];
$scopeLabels = [
  'task'  => 'Tasks',
  'setor' => 'Daftar Setor',
  'both'  => 'Tasks + Setor',
];
$scopeColors = [
  'task'  => 'scope-task',
  'setor' => 'scope-setor',
  'both'  => 'scope-both',
];
$fieldProjectContext = $fieldProjectContext ?? null;
$projectsList = $projectsList ?? [];
$isProjectFieldCtx = $fieldProjectContext !== null && (int) $fieldProjectContext > 0;
?>

<link rel="stylesheet" href="/assets/css/pages/settings-fields.css" />

<div class="fields-page-wrap">

  <div class="page-header">
    <div class="page-header-left">
      <h2 class="page-title"><?= $isProjectFieldCtx ? 'Field Manager — work item proyek' : 'Field Manager — task internal' ?></h2>
      <p class="page-sub">
        <?php if ($isProjectFieldCtx): ?>
          Definisi ini <strong>hanya</strong> dipakai di <code>/projects/…</code> (work item). Terpisah dari kolom <a href="/fields">task internal</a> di <code>/tasks</code>.
        <?php else: ?>
          Hanya untuk halaman <strong><a href="/tasks">Task internal</a></strong>. Work item proyek punya set field sendiri — pilih project di bawah.
        <?php endif; ?>
        Drag untuk reorder &middot; toggle aktif/nonaktif &middot; <strong>Scope</strong> menentukan di halaman mana field ini muncul.
        <span class="fields-count"><?= count($fields) ?> field</span>
      </p>
    </div>
  </div>

  <form method="get" action="/fields" class="fields-context-bar" style="margin:0 0 1.25rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
    <label style="margin:0;display:flex;align-items:center;gap:0.5rem;font-weight:500;">
      Konteks field
      <select name="project_id" class="form-control" style="min-width:220px;" onchange="this.form.requestSubmit()">
        <option value="">Task internal (utama)</option>
        <?php foreach ($projectsList as $pr): ?>
          <option value="<?= (int) ($pr['id'] ?? 0) ?>" <?= $isProjectFieldCtx && (int) $fieldProjectContext === (int) ($pr['id'] ?? 0) ? 'selected' : '' ?>>
            <?= esc((string) ($pr['name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($isProjectFieldCtx): ?>
      <span class="page-sub" style="margin:0;">Field di bawah hanya untuk task di <strong>Project</strong> ini — tanpa sync setor.</span>
    <?php endif; ?>
  </form>

  <!-- ── Core Features ────────────────────────────────────── -->
  <div class="core-features-section">
    <div class="core-features-label">
      <i class="fa-solid fa-bolt" aria-hidden="true"></i>
      Core Features
      <span class="core-features-hint">Fitur bawaan yang dapat diaktifkan / dinonaktifkan</span>
    </div>

    <div class="core-features-grid">

      <!-- Progress -->
      <div class="core-feature-card <?= ($settings['feature_progress'] ?? true) ? 'active' : '' ?>"
           id="cf-progress">
        <div class="cf-icon cf-icon-progress">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        </div>
        <div class="cf-info">
          <div class="cf-name">Progress Tracking</div>
          <div class="cf-desc">Kolom progress 0–100% dengan bar visual di tabel Tasks. Inline editable.</div>
        </div>
        <label class="toggle cf-toggle" title="Toggle Progress Tracking">
          <input type="checkbox"
                 id="toggle-feature_progress"
                 <?= ($settings['feature_progress'] ?? true) ? 'checked' : '' ?>
                 onchange="toggleCoreSetting('feature_progress', this)">
          <span class="toggle-slider"></span>
        </label>
      </div>

      <!-- Deadline -->
      <div class="core-feature-card <?= ($settings['feature_deadline'] ?? true) ? 'active' : '' ?>"
           id="cf-deadline">
        <div class="cf-icon cf-icon-deadline">
          <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
        </div>
        <div class="cf-info">
          <div class="cf-name">Deadline Management</div>
          <div class="cf-desc">Kolom deadline dengan indikator warna overdue/urgent/soon di tabel Tasks.</div>
        </div>
        <label class="toggle cf-toggle" title="Toggle Deadline Management">
          <input type="checkbox"
                 id="toggle-feature_deadline"
                 <?= ($settings['feature_deadline'] ?? true) ? 'checked' : '' ?>
                 onchange="toggleCoreSetting('feature_deadline', this)">
          <span class="toggle-slider"></span>
        </label>
      </div>

    </div>
  </div>

  <!-- Scope filter tabs -->
  <div class="scope-tabs" id="scope-tabs">
    <button class="scope-tab active" data-scope="all">
      <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
      Semua <span class="scope-tab-count"><?= count($fields) ?></span>
    </button>
    <button class="scope-tab" data-scope="task">
      <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
      Tasks
      <span class="scope-tab-count"><?= count(array_filter($fields, fn($f) => in_array($f['scope'] ?? 'task', ['task','both']))) ?></span>
    </button>
    <button class="scope-tab" data-scope="setor">
      <i class="fa-solid fa-house" aria-hidden="true"></i>
      Daftar Setor
      <span class="scope-tab-count"><?= count(array_filter($fields, fn($f) => in_array($f['scope'] ?? 'task', ['setor','both']))) ?></span>
    </button>
    <button class="scope-tab" data-scope="both">
      <i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>
      Both
      <span class="scope-tab-count"><?= count(array_filter($fields, fn($f) => ($f['scope'] ?? 'task') === 'both')) ?></span>
    </button>
  </div>

  <div class="spacer-12"></div>

  <!-- FIELD LIST -->
  <div id="field-list">
    <?php foreach ($fields as $f): ?>
      <?php
        $scope = $f['scope'] ?? 'task';
        $opts  = $f['options'] ? json_decode($f['options'], true) : [];
      ?>
      <div class="field-item" draggable="true" data-field-id="<?= $f['id'] ?>" data-scope="<?= esc($scope) ?>">
        <!-- Drag handle -->
        <div class="drag-handle">
          <i class="fa-solid fa-grip-lines u-icon-sm" aria-hidden="true"></i>
        </div>

        <!-- Type icon -->
        <div class="field-type-icon-wrap">
          <i data-lucide="<?= esc($typeIcons[$f['type']] ?? 'type') ?>" class="u-icon-sm"></i>
        </div>

        <!-- Info -->
        <div class="field-meta">
          <div class="field-name">
            <?= esc($f['field_label']) ?>
            <?php if ($f['is_required']): ?>
              <span class="p-fields-required-tag">required</span>
            <?php endif; ?>
            <span class="scope-badge <?= $scopeColors[$scope] ?? 'scope-task' ?>">
              <i class="fa-solid fa-<?= $scope === 'task' ? 'table-cells' : ($scope === 'setor' ? 'house' : 'arrows-left-right') ?>" aria-hidden="true"></i>
              <?= $scopeLabels[$scope] ?? 'Tasks' ?>
            </span>
          </div>
          <div class="field-sub">
            <span class="p-fields-key"><?= esc($f['field_key']) ?></span>
            &middot; <?= $typeLabels[$f['type']] ?? $f['type'] ?>
            <?php if (($f['data_source'] ?? 'manual') === 'team_users'): ?>
              &middot; <span class="p-fields-sync"><i data-lucide="users" class="u-icon-xxs"></i> users</span>
            <?php endif; ?>
            <?php if (($f['data_source'] ?? 'manual') === 'account_sources'): ?>
              &middot; <span class="p-fields-sync"><i data-lucide="building-2" class="u-icon-xxs"></i> accounts</span>
            <?php endif; ?>
            <?php if ($opts): ?>
              &middot; <?= count($opts) ?> opsi
            <?php endif; ?>
            <?php if (!empty($f['submission_col'])): ?>
              &middot; <span class="p-fields-sync"><i data-lucide="refresh-cw" class="u-icon-xxs"></i> <?= esc($f['submission_col']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="field-actions">
          <!-- Toggle active -->
          <label class="toggle" title="<?= $f['status'] ? 'Aktif — klik nonaktifkan' : 'Nonaktif — klik aktifkan' ?>">
            <input type="checkbox" <?= $f['status'] ? 'checked' : '' ?>
                   onchange="toggleField(<?= $f['id'] ?>, this)">
            <span class="toggle-slider"></span>
          </label>

          <!-- Edit -->
          <button class="btn-icon" onclick="editField(<?= $f['id'] ?>)" title="Edit">
            <i data-lucide="pencil"></i>
          </button>

          <!-- Delete -->
          <button class="btn-icon btn-icon-danger" onclick="deleteField(<?= $f['id'] ?>)" title="Hapus">
            <i data-lucide="trash-2"></i>
          </button>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($fields)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i data-lucide="folders"></i></div>
        <div class="empty-title">Belum ada field</div>
        <div class="empty-desc">Tambah field pertama kamu.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<form id="delete-field-form" method="POST" class="u-hidden">
  <?= csrf_field() ?>
</form>

<!-- ════════════════════════════════════════════════════════ -->
<!--  ADD FIELD MODAL                                         -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="add-field-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Tambah Field Baru</span>
      <button class="btn-icon" onclick="closeModal('add-field-modal')">
        <i class="fa-solid fa-xmark u-icon-sm" aria-hidden="true"></i>
      </button>
    </div>
    <form method="POST" action="/fields/store" id="add-field-form">
      <?= csrf_field() ?>
      <input type="hidden" name="field_project_id" value="<?= $isProjectFieldCtx ? (int) $fieldProjectContext : 0 ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Field Label <span class="req">*</span></label>
            <input type="text" name="field_label" class="form-control" placeholder="mis. Nama Project" required
                   oninput="autoSlug(this.value)">
          </div>
          <div class="form-group">
            <label class="form-label">Field Key <span class="req">*</span></label>
            <input type="text" name="field_key" id="field-key-input" class="form-control"
                   placeholder="mis. nama_project" required pattern="[a-zA-Z0-9_\-]+">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Type <span class="req">*</span></label>
          <select name="type" class="form-control" onchange="handleTypeChange(this.value, 'options-group')" required>
            <option value="text">Text</option>
            <option value="date">Date</option>
            <option value="select">Select (dropdown)</option>
            <option value="boolean">Boolean (checkbox)</option>
            <option value="textarea">Textarea</option>
            <option value="richtext">Rich Text (Editor.js)</option>
            <option value="number">Number</option>
            <option value="email">Email</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Data Source</label>
          <select name="data_source" class="form-control" id="add-data-source" onchange="toggleSourceRoles('add')">
            <option value="manual">Manual input</option>
            <option value="team_users">Team Users (PIC)</option>
            <?php if (! $isProjectFieldCtx): ?>
            <option value="account_sources">Office + Vendor Accounts</option>
            <?php endif; ?>
          </select>
          <div class="p-sync-help">Pilih Team Users untuk PIC, atau Office + Vendor Accounts untuk dropdown akun.</div>
        </div>

        <div class="form-group u-hidden" id="add-source-roles-wrap">
          <label class="form-label">Role yang boleh dipilih (opsional)</label>
          <select name="source_roles[]" class="form-control" id="add-source-roles" multiple size="5">
            <?php foreach ($roles as $role): ?>
              <option value="<?= esc($role) ?>"><?= esc($role) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="p-sync-help">Kosongkan untuk default non-admin (exclude super_admin/admin).</div>
        </div>

        <div class="form-group u-hidden" id="options-group">
          <label class="form-label">Opsi <span class="p-options-hint">(satu per baris)</span></label>
          <textarea name="options_raw" class="form-control" rows="5"
                    placeholder="Opsi A&#10;Opsi B&#10;Opsi C"></textarea>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Placeholder</label>
            <input type="text" name="placeholder" class="form-control" placeholder="Teks petunjuk...">
          </div>
          <div class="form-group">
            <label class="form-label">Default Value</label>
            <input type="text" name="default_value" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Help Text</label>
          <input type="text" name="help_text" class="form-control" placeholder="Deskripsi singkat di bawah field">
        </div>

        <!-- SCOPE -->
        <div class="form-group scope-selector-group<?= $isProjectFieldCtx ? ' u-hidden' : '' ?>">
          <label class="form-label">
            <i class="fa-solid fa-layer-group u-icon-xs" aria-hidden="true"></i>
            Tampil di <span class="req">*</span>
          </label>
          <div class="scope-radio-group">
            <label class="scope-radio">
              <input type="radio" name="scope" value="task" checked>
              <span class="scope-radio-box scope-task">
                <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                Tasks
              </span>
            </label>
            <label class="scope-radio">
              <input type="radio" name="scope" value="setor">
              <span class="scope-radio-box scope-setor">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                Daftar Setor
              </span>
            </label>
            <label class="scope-radio">
              <input type="radio" name="scope" value="both">
              <span class="scope-radio-box scope-both">
                <i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>
                Keduanya
              </span>
            </label>
          </div>
          <div class="scope-hint" id="scope-add-hint">Field akan muncul sebagai kolom di tabel Tasks.</div>
        </div>

        <!-- SUBMISSION COLUMN MAPPING -->
        <div class="form-group p-sync-box<?= $isProjectFieldCtx ? ' u-hidden' : '' ?>" id="add-sync-box">
          <label class="form-label p-sync-label">
            <i class="fa-solid fa-link u-icon-xs" aria-hidden="true"></i>
            Sync ke kolom tb_submissions
            <span class="p-sync-optional">(opsional)</span>
          </label>
          <select name="submission_col" class="form-control">
            <option value="">— Tidak disync —</option>
            <?php foreach ($subCols as $col): ?>
              <option value="<?= esc($col) ?>"><?= esc($col) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="p-sync-help">
            Nilai field ini akan otomatis masuk ke kolom tb_submissions saat task di-setor.
          </div>
        </div>

        <label class="form-check u-mt-4">
          <input type="checkbox" name="is_required" value="1">
          <span>Wajib diisi</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-field-modal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Field</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!--  EDIT FIELD MODAL                                        -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-field-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Field</span>
      <button class="btn-icon" onclick="closeModal('edit-field-modal')">
        <i class="fa-solid fa-xmark u-icon-sm" aria-hidden="true"></i>
      </button>
    </div>
    <form method="POST" id="edit-field-form">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Field Label <span class="req">*</span></label>
            <input type="text" name="field_label" id="edit-field-label" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Field Key</label>
            <input type="text" id="edit-field-key-display" class="form-control u-soft-readonly u-mono" readonly>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Type</label>
          <input type="text" id="edit-field-type-display" class="form-control u-soft-readonly" readonly>
        </div>

        <div class="form-group">
          <label class="form-label">Data Source</label>
          <select name="data_source" id="edit-data-source" class="form-control" onchange="toggleSourceRoles('edit')">
            <option value="manual">Manual input</option>
            <option value="team_users">Team Users (PIC)</option>
            <option value="account_sources">Office + Vendor Accounts</option>
          </select>
        </div>

        <div class="form-group u-hidden" id="edit-source-roles-wrap">
          <label class="form-label">Role yang boleh dipilih (opsional)</label>
          <select name="source_roles[]" id="edit-source-roles" class="form-control" multiple size="5">
            <?php foreach ($roles as $role): ?>
              <option value="<?= esc($role) ?>"><?= esc($role) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="p-sync-help">Kosongkan untuk default non-admin.</div>
        </div>

        <div class="form-group" id="edit-options-group">
          <label class="form-label">Opsi <span class="p-options-hint">(satu per baris)</span></label>
          <textarea name="options_raw" id="edit-options-raw" class="form-control" rows="5"></textarea>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Placeholder</label>
            <input type="text" name="placeholder" id="edit-placeholder" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Default Value</label>
            <input type="text" name="default_value" id="edit-default-value" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Help Text</label>
          <input type="text" name="help_text" id="edit-help-text" class="form-control">
        </div>

        <!-- SCOPE (edit) -->
        <div class="form-group scope-selector-group" id="edit-scope-section">
          <label class="form-label">
            <i class="fa-solid fa-layer-group u-icon-xs" aria-hidden="true"></i>
            Tampil di
          </label>
          <div class="scope-radio-group">
            <label class="scope-radio">
              <input type="radio" name="scope" id="edit-scope-task" value="task">
              <span class="scope-radio-box scope-task">
                <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                Tasks
              </span>
            </label>
            <label class="scope-radio">
              <input type="radio" name="scope" id="edit-scope-setor" value="setor">
              <span class="scope-radio-box scope-setor">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                Daftar Setor
              </span>
            </label>
            <label class="scope-radio">
              <input type="radio" name="scope" id="edit-scope-both" value="both">
              <span class="scope-radio-box scope-both">
                <i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>
                Keduanya
              </span>
            </label>
          </div>
          <div class="scope-hint" id="scope-edit-hint"></div>
        </div>

        <!-- SUBMISSION COLUMN MAPPING (EDIT) -->
        <div class="form-group p-sync-box" id="edit-sync-section">
          <label class="form-label p-sync-label">
            <i class="fa-solid fa-link u-icon-xs" aria-hidden="true"></i>
            Sync ke kolom tb_submissions
          </label>
          <select name="submission_col" id="edit-submission-col" class="form-control">
            <option value="">— Tidak disync —</option>
            <?php foreach ($subCols as $col): ?>
              <option value="<?= esc($col) ?>"><?= esc($col) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <label class="form-check u-mt-4">
          <input type="checkbox" name="is_required" id="edit-is-required" value="1">
          <span>Wajib diisi</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-field-modal')">Batal</button>
        <button type="submit" class="btn btn-primary">Update Field</button>
      </div>
    </form>
  </div>
</div>

<script>
window.fieldsProjectContext = <?= $isProjectFieldCtx ? (int) $fieldProjectContext : 0 ?>;
// ── Core Feature Toggles ──────────────────────────────────────
async function toggleCoreSetting(key, checkbox) {
  const card = document.getElementById('cf-' + key.replace('feature_', ''));
  const prev = checkbox.checked;
  try {
    const csrf = getAppCsrf();
    const res  = await fetch(`/fields/setting/${key}/toggle`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf.val,
      },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.csrf) updateAppCsrf(data.csrf);
    if (!data.success) {
      checkbox.checked = !prev;
      showToast('Toggle gagal', 'error');
      return;
    }
    checkbox.checked = data.enabled;
    card?.classList.toggle('active', data.enabled);
    showToast(data.enabled ? 'Fitur diaktifkan' : 'Fitur dinonaktifkan');
  } catch (e) {
    checkbox.checked = !prev;
    showToast('Koneksi gagal, coba lagi', 'error');
  }
}

const _scopeHints = {
  task:  'Field muncul sebagai kolom di tabel Tasks saja.',
  setor: 'Field muncul di halaman Daftar Setor saja.',
  both:  'Field muncul di Tasks dan Daftar Setor.',
};

// ── Scope tab filter ─────────────────────────────────────────
document.getElementById('scope-tabs').addEventListener('click', (e) => {
  const btn = e.target.closest('.scope-tab');
  if (!btn) return;
  document.querySelectorAll('.scope-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const scope = btn.dataset.scope;
  document.querySelectorAll('#field-list .field-item').forEach(item => {
    const s = item.dataset.scope || 'task';
    const show = scope === 'all'
      || scope === s
      || (scope === 'task'  && (s === 'task'  || s === 'both'))
      || (scope === 'setor' && (s === 'setor' || s === 'both'));
    item.style.display = show ? '' : 'none';
  });
});

// ── Scope radio hints ─────────────────────────────────────────
function bindScopeHints(radioName, hintId) {
  document.querySelectorAll(`input[name="${radioName}"]`).forEach(r => {
    r.addEventListener('change', () => {
      const hint = document.getElementById(hintId);
      if (hint) hint.textContent = _scopeHints[r.value] || '';
    });
  });
}
bindScopeHints('scope', 'scope-add-hint');

function toggleSourceRoles(mode) {
  const src = document.getElementById(`${mode}-data-source`)?.value || 'manual';
  const wrap = document.getElementById(`${mode}-source-roles-wrap`);
  if (wrap) wrap.style.display = src === 'team_users' ? 'block' : 'none';
}
toggleSourceRoles('add');

// ── Auto-slug ────────────────────────────────────────────────
function autoSlug(val) {
  document.getElementById('field-key-input').value = val
    .toLowerCase().trim()
    .replace(/\s+/g, '_')
    .replace(/[^\w_]/g, '');
}

// ── Show/hide options textarea ───────────────────────────────
function handleTypeChange(type, groupId) {
  document.getElementById(groupId).style.display = type === 'select' ? 'block' : 'none';
}

// ── Toggle field active/inactive ────────────────────────────
async function toggleField(id, checkbox) {
  const prev = checkbox.checked;
  try {
    const csrf = getAppCsrf();
    const res  = await fetch(`/fields/toggle/${id}`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf.val,
      },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.csrf) updateAppCsrf(data.csrf);
    if (!data.success) {
      checkbox.checked = !prev;
      showToast('Toggle gagal', 'error');
    } else {
      showToast(checkbox.checked ? 'Field diaktifkan' : 'Field dinonaktifkan');
    }
  } catch (e) {
    checkbox.checked = !prev;
    showToast('Koneksi gagal, coba lagi', 'error');
  }
}

// ── Edit field (load data into modal) ───────────────────────
async function editField(id) {
  const res  = await fetch(`/fields/${id}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
  const data = await res.json();
  if (!data.success) return showToast('Gagal load field', 'error');

  const f = data.field;
  document.getElementById('edit-field-form').action    = `/fields/update/${id}`;
  document.getElementById('edit-field-label').value    = f.field_label;
  document.getElementById('edit-field-key-display').value  = f.field_key;
  document.getElementById('edit-field-type-display').value = f.type;
  document.getElementById('edit-options-raw').value    = f.options_raw || '';
  document.getElementById('edit-placeholder').value    = f.placeholder || '';
  document.getElementById('edit-default-value').value  = f.default_value || '';
  document.getElementById('edit-help-text').value      = f.help_text || '';
  document.getElementById('edit-is-required').checked  = f.is_required == 1;
  document.getElementById('edit-submission-col').value = f.submission_col || '';
  document.getElementById('edit-data-source').value    = f.data_source || 'manual';
  const roleSel = document.getElementById('edit-source-roles');
  if (roleSel) {
    [...roleSel.options].forEach(opt => {
      opt.selected = Array.isArray(f.source_roles) && f.source_roles.includes(opt.value);
    });
  }

  const scope = f.scope || 'task';
  const scopeRadio = document.getElementById(`edit-scope-${scope}`);
  if (scopeRadio) scopeRadio.checked = true;
  const hint = document.getElementById('scope-edit-hint');
  if (hint) hint.textContent = _scopeHints[scope] || '';

  document.getElementById('edit-options-group').style.display =
    f.type === 'select' ? 'block' : 'none';
  toggleSourceRoles('edit');

  const projField = f.project_id && parseInt(String(f.project_id), 10) > 0;
  ['edit-scope-section', 'edit-sync-section'].forEach((sid) => {
    const el = document.getElementById(sid);
    if (el) el.classList.toggle('u-hidden', !!projField);
  });
  const accOpt = document.querySelector('#edit-data-source option[value="account_sources"]');
  if (accOpt) accOpt.disabled = !!projField;

  openModal('edit-field-modal');
}

// ── Delete field ─────────────────────────────────────────────
async function deleteField(id) {
  const ok = await appConfirm({
    head: 'Konfirmasi',
    title: 'Hapus field?',
    message: 'Hapus field ini? Tidak bisa dibatalkan.',
    okText: 'Hapus',
    okVariant: 'danger',
  });
  if (!ok) return;
  const form = document.getElementById('delete-field-form');
  const csrf = typeof getAppCsrf === 'function' ? getAppCsrf() : null;
  if (csrf && csrf.key && csrf.val) {
    let csrfInp = form.querySelector('input[name="' + csrf.key + '"]');
    if (!csrfInp) {
      csrfInp = document.createElement('input');
      csrfInp.type = 'hidden';
      csrfInp.name = csrf.key;
      form.appendChild(csrfInp);
    }
    csrfInp.value = csrf.val;
  }
  form.action = '/fields/delete/' + id;
  form.requestSubmit();
}

// ── Drag & Drop reorder ──────────────────────────────────────
const list = document.getElementById('field-list');
let dragging = null;

list.addEventListener('dragstart', e => {
  dragging = e.target.closest('.field-item');
  if (!dragging) return;
  dragging.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
});

list.addEventListener('dragend', () => {
  if (dragging) dragging.classList.remove('dragging');
  dragging = null;
  saveOrder();
});

list.addEventListener('dragover', e => {
  e.preventDefault();
  const target = e.target.closest('.field-item');
  if (!target || target === dragging) return;
  const mid = e.clientY - target.getBoundingClientRect().top - target.getBoundingClientRect().height / 2;
  list.insertBefore(dragging, mid < 0 ? target : target.nextSibling);
});

async function saveOrder() {
  const items = [...list.querySelectorAll('[data-field-id]')];
  const order = items.map((el, i) => ({ id: el.dataset.fieldId, order_no: i + 1 }));
  try {
    const csrf = getAppCsrf();
    const res  = await fetch('/fields/reorder', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf.val,
      },
      body: JSON.stringify({ fields: order, project_id: window.fieldsProjectContext || null }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.csrf) updateAppCsrf(data.csrf);
    if (data.success) showToast('Urutan disimpan');
    else showToast('Gagal simpan urutan', 'error');
  } catch(e) {
    showToast('Koneksi gagal, coba lagi', 'error');
  }
}
</script>
