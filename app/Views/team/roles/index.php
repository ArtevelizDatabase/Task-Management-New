<link rel="stylesheet" href="/assets/css/pages/team-users.css" />
<link rel="stylesheet" href="/assets/css/pages/roles.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Role Configuration</h2>
    <p class="page-sub">Kelola role dan permission untuk kontrol akses berbasis peran (RBAC)</p>
  </div>
  <div class="page-header-right">
    <?php if ($currentRole === 'super_admin'): ?>
    <button class="btn btn-primary" onclick="openModal('addRoleModal')">
      <i class="fa-solid fa-plus icon-xs"></i> Tambah Role
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Permission legend -->
<div class="perm-legend card mb-4">
  <div class="perm-legend-title">
    <i class="fa-solid fa-shield-halved text-accent"></i>
    <?= count($allKeys) ?> Permissions Tersedia
  </div>
  <div class="perm-legend-grid">
    <?php foreach ($permissions as $groupKey => $group): ?>
    <div class="perm-legend-group">
      <div class="perm-legend-group-label"><?= esc($group['label']) ?></div>
      <?php foreach ($group['items'] as $key => $label): ?>
        <span class="perm-tag"><code><?= $key ?></code> — <?= esc($label) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Roles list -->
<div class="roles-grid">
  <?php foreach ($roles as $role): ?>
  <?php
    $perms      = is_array($role['permissions']) ? $role['permissions'] : [];
    $isSuperAdmin = $role['slug'] === 'super_admin';
    $canEdit    = $currentRole === 'super_admin';
    $permCount  = count($perms);
    $totalPerms = count($allKeys);
  ?>
  <div class="role-card card">
    <div class="role-card-head">
      <div class="role-badge-dot" style="background:<?= esc($role['color']) ?>"></div>
      <div class="role-card-info">
        <div class="role-card-name">
          <?= esc($role['name']) ?>
          <?php if ($role['is_system']): ?>
            <span class="badge-system">System</span>
          <?php endif; ?>
        </div>
        <?php if ($role['description']): ?>
          <div class="role-card-desc"><?= esc($role['description']) ?></div>
        <?php endif; ?>
      </div>
      <div class="role-card-actions">
        <?php if ($canEdit): ?>
        <button class="btn btn-ghost btn-xs"
                onclick="openEditModal(<?= $role['id'] ?>, '<?= esc($role['name'], 'js') ?>', '<?= esc($role['description'] ?? '', 'js') ?>', '<?= esc($role['color'], 'js') ?>', <?= json_encode($perms) ?>, <?= $role['is_system'] ?>)"
                title="Edit permissions">
          <i class="fa-solid fa-pen icon-xs"></i>
        </button>
        <?php if (!$role['is_system']): ?>
        <form method="POST" action="/team/roles/<?= $role['id'] ?>/delete"
              data-confirm="Hapus role &quot;<?= esc($role['name']) ?>&quot;?"
              data-confirm-ok-variant="danger" style="display:inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-xs text-danger" title="Hapus">
            <i class="fa-solid fa-trash icon-xs"></i>
          </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats row -->
    <div class="role-stats-row">
      <div class="role-stat">
        <span class="role-stat-num"><?= $role['user_count'] ?></span>
        <span class="role-stat-label">User</span>
      </div>
      <div class="role-stat">
        <span class="role-stat-num"><?= $isSuperAdmin ? $totalPerms : $permCount ?></span>
        <span class="role-stat-label">dari <?= $totalPerms ?> permission</span>
      </div>
      <div class="role-perm-bar">
        <div class="role-perm-fill"
             style="width:<?= $isSuperAdmin ? 100 : round(($permCount/$totalPerms)*100) ?>%;background:<?= esc($role['color']) ?>">
        </div>
      </div>
    </div>

    <!-- Permission matrix -->
    <div class="perm-matrix">
      <?php foreach ($permissions as $groupKey => $group): ?>
      <div class="perm-group">
        <div class="perm-group-label"><?= esc($group['label']) ?></div>
        <div class="perm-items">
          <?php foreach ($group['items'] as $permKey => $permLabel): ?>
          <?php $has = $isSuperAdmin || in_array($permKey, $perms, true); ?>
          <span class="perm-item <?= $has ? 'perm-has' : 'perm-missing' ?>" title="<?= esc($permLabel) ?>">
            <i class="fa-solid <?= $has ? 'fa-check' : 'fa-xmark' ?>"></i>
            <?= esc(str_replace('_', ' ', $permKey)) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Add Role Modal -->
<div id="addRoleModal" class="modal-overlay">
  <div class="modal modal-lg">
    <div class="modal-head">
      <i class="fa-solid fa-shield-halved"></i> Tambah Role Baru
      <button type="button" class="modal-close" onclick="closeModal('addRoleModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="/team/roles/store">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-col">
            <label class="form-label">Nama Role <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="misal: Content Writer" required />
          </div>
          <div class="form-col">
            <label class="form-label">Warna Role</label>
            <div class="color-pick-row">
              <input type="color" name="color" value="#6b7280" class="form-control-color" />
              <div class="color-presets">
                <?php foreach (['#7e22ce','#4f46e5','#0891b2','#059669','#d97706','#dc2626','#6b7280'] as $c): ?>
                  <button type="button" class="color-preset" style="background:<?= $c ?>"
                          onclick="document.querySelector('#addRoleModal [name=color]').value='<?= $c ?>'">
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="form-col-full">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="description" class="form-control" placeholder="Deskripsi role ini" />
          </div>
        </div>

        <div class="perm-editor">
          <div class="perm-editor-head">
            <span class="form-label mb-0">Permissions</span>
            <div class="perm-quick-btns">
              <button type="button" class="btn btn-ghost btn-xs" onclick="selectAllPerms('addRoleModal', true)">Select All</button>
              <button type="button" class="btn btn-ghost btn-xs" onclick="selectAllPerms('addRoleModal', false)">Clear All</button>
            </div>
          </div>
          <?php foreach ($permissions as $groupKey => $group): ?>
          <div class="perm-edit-group">
            <div class="perm-edit-group-label">
              <label class="checkbox-label">
                <input type="checkbox" class="group-check" data-group="<?= $groupKey ?>" data-modal="addRoleModal"
                       onchange="toggleGroup(this)" />
                <span class="checkbox-custom"></span>
                <?= esc($group['label']) ?>
              </label>
            </div>
            <div class="perm-edit-items">
              <?php foreach ($group['items'] as $permKey => $permLabel): ?>
              <label class="perm-checkbox checkbox-label" data-group="<?= $groupKey ?>">
                <input type="checkbox" name="permissions[]" value="<?= $permKey ?>"
                       class="perm-item-check" data-group="<?= $groupKey ?>" />
                <span class="checkbox-custom"></span>
                <div>
                  <code class="perm-key"><?= $permKey ?></code>
                  <span class="perm-label-text"><?= esc($permLabel) ?></span>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('addRoleModal')">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus icon-xs"></i> Buat Role
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal-overlay">
  <div class="modal modal-lg">
    <div class="modal-head">
      <i class="fa-solid fa-pen"></i> Edit Role & Permissions
      <button type="button" class="modal-close" onclick="closeModal('editRoleModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form id="editRoleForm" method="POST">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div id="editRoleSystemNotice" class="role-system-notice" style="display:none">
          <i class="fa-solid fa-circle-info"></i>
          Role sistem — nama tidak dapat diubah, hanya permissions yang bisa diedit.
        </div>
        <div id="editRoleNameRow" class="form-grid">
          <div class="form-col">
            <label class="form-label">Nama Role</label>
            <input type="text" id="editRoleName" name="name" class="form-control" />
          </div>
          <div class="form-col">
            <label class="form-label">Warna Role</label>
            <div class="color-pick-row">
              <input type="color" id="editRoleColor" name="color" value="#6b7280" class="form-control-color" />
              <div class="color-presets">
                <?php foreach (['#7e22ce','#4f46e5','#0891b2','#059669','#d97706','#dc2626','#6b7280'] as $c): ?>
                  <button type="button" class="color-preset" style="background:<?= $c ?>"
                          onclick="document.getElementById('editRoleColor').value='<?= $c ?>'">
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="form-col-full">
            <label class="form-label">Deskripsi</label>
            <input type="text" id="editRoleDesc" name="description" class="form-control" />
          </div>
        </div>

        <div id="editSuperAdminNotice" class="role-system-notice role-system-notice-success" style="display:none">
          <i class="fa-solid fa-crown"></i>
          Super Admin secara otomatis memiliki semua permissions.
        </div>

        <div class="perm-editor" id="editPermEditor">
          <div class="perm-editor-head">
            <span class="form-label mb-0">Permissions</span>
            <div class="perm-quick-btns">
              <button type="button" class="btn btn-ghost btn-xs" onclick="selectAllPerms('editRoleModal', true)">Select All</button>
              <button type="button" class="btn btn-ghost btn-xs" onclick="selectAllPerms('editRoleModal', false)">Clear All</button>
            </div>
          </div>
          <?php foreach ($permissions as $groupKey => $group): ?>
          <div class="perm-edit-group">
            <div class="perm-edit-group-label">
              <label class="checkbox-label">
                <input type="checkbox" class="group-check" data-group="<?= $groupKey ?>" data-modal="editRoleModal"
                       onchange="toggleGroup(this)" />
                <span class="checkbox-custom"></span>
                <?= esc($group['label']) ?>
              </label>
            </div>
            <div class="perm-edit-items">
              <?php foreach ($group['items'] as $permKey => $permLabel): ?>
              <label class="perm-checkbox checkbox-label" data-group="<?= $groupKey ?>">
                <input type="checkbox" name="permissions[]" value="<?= $permKey ?>"
                       class="perm-item-check edit-perm-check" data-group="<?= $groupKey ?>" />
                <span class="checkbox-custom"></span>
                <div>
                  <code class="perm-key"><?= $permKey ?></code>
                  <span class="perm-label-text"><?= esc($permLabel) ?></span>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('editRoleModal')">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-floppy-disk icon-xs"></i> Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Open edit modal ───────────────────────────────────────────
function openEditModal(id, name, desc, color, currentPerms, isSystem) {
  const form = document.getElementById('editRoleForm');
  form.action = `/team/roles/${id}/update`;
  document.getElementById('editRoleName').value = name;
  document.getElementById('editRoleDesc').value  = desc;
  document.getElementById('editRoleColor').value = color;
  document.getElementById('editRoleName').disabled = !!isSystem;

  const systemNotice = document.getElementById('editRoleSystemNotice');
  systemNotice.style.display = isSystem ? 'flex' : 'none';

  const isSA = (id === 1); // super_admin is always id=1
  document.getElementById('editSuperAdminNotice').style.display = isSA ? 'flex' : 'none';
  document.getElementById('editPermEditor').style.opacity = isSA ? '.5' : '1';
  document.getElementById('editPermEditor').style.pointerEvents = isSA ? 'none' : '';

  // Set checkboxes
  document.querySelectorAll('#editRoleModal .edit-perm-check').forEach(cb => {
    cb.checked = currentPerms.includes(cb.value);
  });
  syncGroupChecks('editRoleModal');
  openModal('editRoleModal');
}

// ── Toggle group ──────────────────────────────────────────────
function toggleGroup(groupCb) {
  const modal = document.getElementById(groupCb.dataset.modal);
  const group = groupCb.dataset.group;
  modal.querySelectorAll(`.perm-item-check[data-group="${group}"]`).forEach(cb => {
    cb.checked = groupCb.checked;
  });
}

function syncGroupChecks(modalId) {
  const modal = document.getElementById(modalId);
  modal.querySelectorAll('.group-check').forEach(groupCb => {
    const group    = groupCb.dataset.group;
    const allItems = [...modal.querySelectorAll(`.perm-item-check[data-group="${group}"]`)];
    const allCkd   = allItems.every(cb => cb.checked);
    const someCkd  = allItems.some(cb => cb.checked);
    groupCb.checked = allCkd;
    groupCb.indeterminate = !allCkd && someCkd;
  });
}

function selectAllPerms(modalId, checked) {
  document.getElementById(modalId).querySelectorAll('.perm-item-check').forEach(cb => cb.checked = checked);
  syncGroupChecks(modalId);
}

// Sync group checkboxes on individual item change
document.querySelectorAll('.perm-item-check').forEach(cb => {
  cb.addEventListener('change', () => {
    const modal = cb.closest('.modal-overlay');
    if (modal) syncGroupChecks(modal.id);
  });
});

// Init group checks on page load
syncGroupChecks('addRoleModal');
syncGroupChecks('editRoleModal');
</script>
