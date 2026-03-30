<link rel="stylesheet" href="/assets/css/pages/team-users.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">User Management</h2>
    <p class="page-sub">Kelola pengguna, role, dan assignment tim</p>
  </div>
  <div class="page-header-right">
    <?php if (in_array($currentRole, ['super_admin', 'admin'])): ?>
    <a href="/team/users/create" class="btn btn-primary">
      <i class="fa-solid fa-plus icon-xs"></i> Tambah User
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="filter-bar card mb-4">
  <form method="GET" action="/team/users" class="filter-form">
    <div class="filter-group">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Cari username, email, nama..." value="<?= esc($search) ?>" />
    </div>
    <div class="filter-group">
      <select name="role" class="form-control form-control-sm">
        <option value="">Semua Role</option>
        <?php foreach ($roleLabels as $val => $label): ?>
          <option value="<?= $val ?>" <?= $filterRole === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <select name="status" class="form-control form-control-sm">
        <option value="">Semua Status</option>
        <option value="active"    <?= $filterStatus === 'active'    ? 'selected' : '' ?>>Aktif</option>
        <option value="inactive"  <?= $filterStatus === 'inactive'  ? 'selected' : '' ?>>Nonaktif</option>
        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>
    </div>
    <button type="submit" class="btn btn-ghost btn-sm">
      <i class="fa-solid fa-magnifying-glass icon-xs"></i> Filter
    </button>
    <a href="/team/users" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<!-- Stats row -->
<div class="user-stats-row">
  <div class="stat-chip">
    <span class="stat-chip-num"><?= (int) ($statTotal ?? count($users)) ?></span>
    <span class="stat-chip-label">Total User</span>
  </div>
  <div class="stat-chip stat-chip-success">
    <span class="stat-chip-num"><?= (int) ($statActive ?? 0) ?></span>
    <span class="stat-chip-label">Aktif</span>
  </div>
  <div class="stat-chip stat-chip-warn">
    <span class="stat-chip-num"><?= (int) ($statInactive ?? 0) ?></span>
    <span class="stat-chip-label">Nonaktif</span>
  </div>
  <div class="stat-chip stat-chip-accent">
    <span class="stat-chip-num"><?= (int) ($statAdmins ?? 0) ?></span>
    <span class="stat-chip-label">Admin/SA</span>
  </div>
</div>

<!-- Table -->
<div class="card table-card">
  <?php if (empty($users)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-users empty-icon"></i>
      <p>Tidak ada user yang ditemukan.</p>
      <?php if ($search || $filterRole || $filterStatus): ?>
        <a href="/team/users" class="btn btn-ghost btn-sm">Reset filter</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Tim</th>
          <th>Status</th>
          <th>Login Terakhir</th>
          <th>Login Gagal (24j)</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
        <?php
          $avatarUrl = \App\Models\UserModel::avatarUrl($user['avatar'], $user['nickname'] ?? $user['username']);
          $attempts  = $loginAttempts[$user['id']] ?? 0;
          $isSelf    = $user['id'] === $currentUserId;
        ?>
        <tr class="<?= $user['status'] !== 'active' ? 'row-inactive' : '' ?>">
          <td>
            <div class="user-cell">
              <img src="<?= $avatarUrl ?>" alt="Avatar" class="user-avatar-sm" />
              <div class="user-cell-info">
                <div class="user-cell-name">
                  <?= esc($user['nickname'] ?? $user['username']) ?>
                  <?php if ($isSelf): ?><span class="badge badge-self">Anda</span><?php endif; ?>
                </div>
                <div class="user-cell-meta">@<?= esc($user['username']) ?> · <?= esc($user['email']) ?></div>
                <?php if ($user['job_title']): ?>
                  <div class="user-cell-job"><?= esc($user['job_title']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <span class="role-badge role-<?= $user['role'] ?>">
              <?= esc($roleLabels[$user['role']] ?? $user['role']) ?>
            </span>
          </td>
          <td>
            <div class="teams-cell">
              <?php if (empty($user['teams'])): ?>
                <span class="text-muted text-sm">—</span>
              <?php else: ?>
                <?php foreach ($user['teams'] as $t): ?>
                  <span class="team-chip"><?= esc($t['name']) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <button class="status-toggle status-<?= $user['status'] ?>"
                    onclick="toggleStatus(<?= $user['id'] ?>, this)"
                    <?= (!in_array($currentRole, ['super_admin','admin']) || $isSelf) ? 'disabled' : '' ?>
                    title="Klik untuk ubah status">
              <?= $user['status'] === 'active' ? 'Aktif' : ($user['status'] === 'suspended' ? 'Suspended' : 'Nonaktif') ?>
            </button>
          </td>
          <td class="text-sm text-muted">
            <?= $user['last_login_at'] ? date('d M Y H:i', strtotime($user['last_login_at'])) : '—' ?>
          </td>
          <td>
            <?php if ($attempts > 0): ?>
              <span class="badge <?= $attempts >= 5 ? 'badge-danger' : 'badge-warn' ?>">
                <?= $attempts ?> kali
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="action-btns">
              <a href="/team/users/<?= $user['id'] ?>/edit" class="btn btn-ghost btn-xs" title="Edit">
                <i class="fa-solid fa-pen icon-xs"></i>
              </a>
              <a href="/team/users/<?= $user['id'] ?>/activity" class="btn btn-ghost btn-xs" title="Activity Log">
                <i class="fa-solid fa-clock-rotate-left icon-xs"></i>
              </a>
              <?php if ($currentRole === 'super_admin' && !$isSelf): ?>
              <button type="button" class="btn btn-ghost btn-xs text-accent"
                      onclick="impersonate(<?= $user['id'] ?>, '<?= esc($user['nickname'] ?? $user['username'], 'js') ?>')"
                      title="Impersonate user ini">
                <i class="fa-solid fa-user-secret icon-xs"></i>
              </button>
              <form method="POST" action="/team/users/<?= $user['id'] ?>/delete"
                    data-confirm="Hapus user <?= esc($user['username']) ?>? Tindakan ini tidak dapat dibatalkan."
                    data-confirm-ok-variant="danger">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-xs text-danger" title="Hapus">
                  <i class="fa-solid fa-trash icon-xs"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
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
        'uriPath'     => $pagerUriPath ?? '/team/users',
    ]) ?>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Impersonate confirm modal -->
<div id="impersonateModal" class="modal-overlay">
  <div class="modal modal-sm">
    <div class="modal-head">
      <i class="fa-solid fa-user-secret"></i> Impersonation
    </div>
    <div class="modal-body">
      <p>Anda akan login sebagai <strong id="impersonateTarget"></strong>.</p>
      <p class="text-muted text-sm mt-2">Semua aksi akan dilakukan atas nama user tersebut. Sesi asli Anda akan dipulihkan saat Anda berhenti impersonating.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('impersonateModal')">Batal</button>
      <form id="impersonateForm" method="POST" style="display:inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-user-secret icon-xs"></i> Mulai Impersonation
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const _csrfKey = '<?= csrf_token() ?>';
let _csrfVal   = '<?= csrf_hash() ?>';

async function toggleStatus(userId, btn) {
  btn.disabled = true;
  try {
    const res  = await fetch(`/team/users/${userId}/toggle-status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ [_csrfKey]: _csrfVal }),
    });
    const data = await res.json();
    if (data.success) {
      btn.className = `status-toggle status-${data.status}`;
      btn.textContent = data.status === 'active' ? 'Aktif' : 'Nonaktif';
      if (typeof showToast === 'function') showToast(data.message, 'success');
    } else {
      if (typeof showToast === 'function') showToast(data.message || 'Gagal mengubah status.', 'error');
    }
  } catch(e) {
    if (typeof showToast === 'function') showToast('Network error.', 'error');
  }
  btn.disabled = false;
}

function impersonate(userId, name) {
  document.getElementById('impersonateTarget').textContent = name;
  document.getElementById('impersonateForm').action = `/auth/impersonate/${userId}`;
  openModal('impersonateModal');
}
</script>
