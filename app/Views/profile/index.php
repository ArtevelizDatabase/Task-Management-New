<link rel="stylesheet" href="/assets/css/pages/profile.css" />

<?php
  $avatarUrl  = \App\Models\UserModel::avatarUrl($user['avatar'], $user['nickname'] ?? $user['username']);
  $roleLabels = \App\Models\UserModel::$roleLabels;
  $roleColors = [
    'super_admin' => '#7e22ce', 'admin' => '#4f46e5', 'manager' => '#d97706', 'member' => '#6b7280',
  ];
  $roleColor   = $roleColors[$user['role']] ?? '#6b7280';
  $notifModel  = new \App\Models\NotificationModel();
  $prefs       = $notifModel->getPreferences((int)$user['id']);
  $notifTypes  = \App\Models\NotificationModel::$types;
  $unreadCount = $notifModel->getUnreadCount((int)$user['id']);
?>

<!-- ── Page header ── -->
<div class="profile-page-header">
  <div>
    <h2 class="page-title">Profil Saya</h2>
    <p class="page-sub">Kelola informasi akun, keamanan, dan preferensi notifikasi</p>
  </div>
</div>

<div class="profile-layout">

  <!-- ══ LEFT SIDEBAR ══════════════════════════════════ -->
  <div class="profile-sidebar">

    <!-- Profile card -->
    <div class="card profile-card">
      <div class="profile-avatar-wrap">
        <img src="<?= $avatarUrl ?>" id="profileAvatarImg" class="profile-avatar" alt="Avatar" />
        <label class="avatar-edit-btn" for="avatarFileInput" title="Ganti foto">
          <i class="fa-solid fa-camera"></i>
        </label>
        <form id="avatarForm" method="POST" action="/profile/update" enctype="multipart/form-data" style="display:none">
          <?= csrf_field() ?>
          <input type="file" id="avatarFileInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
                 onchange="submitAvatarForm(this)" />
        </form>
      </div>

      <div class="profile-name"><?= esc($user['nickname'] ?? $user['username']) ?></div>
      <div class="profile-username">@<?= esc($user['username']) ?></div>
      <?php if ($user['job_title']): ?>
        <div class="profile-job"><?= esc($user['job_title']) ?></div>
      <?php endif; ?>

      <span class="profile-role-badge" style="background:<?= $roleColor ?>22;color:<?= $roleColor ?>;border-color:<?= $roleColor ?>44">
        <?= esc($roleLabels[$user['role']] ?? $user['role']) ?>
      </span>

      <!-- Quick stats -->
      <div class="profile-quick-stats">
        <div class="quick-stat">
          <span class="quick-stat-num"><?= $unreadCount ?></span>
          <span class="quick-stat-label">Notif Baru</span>
        </div>
        <div class="quick-stat">
          <span class="quick-stat-num"><?= count($activity) ?></span>
          <span class="quick-stat-label">Aktivitas</span>
        </div>
        <div class="quick-stat">
          <span class="quick-stat-num"><?= count($teams) ?></span>
          <span class="quick-stat-label">Tim</span>
        </div>
      </div>

      <div class="profile-meta">
        <div class="profile-meta-item">
          <i class="fa-solid fa-envelope icon-xs text-muted"></i>
          <span><?= esc($user['email']) ?></span>
        </div>
        <?php if ($user['last_login_at']): ?>
        <div class="profile-meta-item">
          <i class="fa-solid fa-clock icon-xs text-muted"></i>
          <span>Login terakhir: <?= date('d M Y H:i', strtotime($user['last_login_at'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($user['last_login_ip']): ?>
        <div class="profile-meta-item">
          <i class="fa-solid fa-location-dot icon-xs text-muted"></i>
          <span>IP: <?= esc($user['last_login_ip']) ?></span>
        </div>
        <?php endif; ?>
        <div class="profile-meta-item">
          <i class="fa-solid fa-calendar icon-xs text-muted"></i>
          <span>Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?></span>
        </div>
      </div>

      <!-- Teams -->
      <?php if (!empty($teams)): ?>
      <div class="profile-teams">
        <div class="profile-section-label">Tim Saya</div>
        <div class="teams-list">
          <?php foreach ($teams as $t): ?>
            <span class="team-chip"><i class="fa-solid fa-users-rectangle" style="font-size:9px;opacity:.5"></i> <?= esc($t['name']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Quick links -->
      <div class="profile-quick-links">
        <a href="/notifications" class="profile-quick-link">
          <i class="fa-solid fa-bell icon-xs"></i>
          Semua Notifikasi
          <?php if ($unreadCount > 0): ?><span class="badge-dot"><?= $unreadCount ?></span><?php endif; ?>
        </a>
        <a href="/tasks" class="profile-quick-link">
          <i class="fa-solid fa-table-cells icon-xs"></i>
          My Tasks
        </a>
      </div>
    </div>

  </div><!-- /.profile-sidebar -->

  <!-- ══ MAIN PANEL ════════════════════════════════════ -->
  <div class="profile-main">

    <!-- Tab navigation -->
    <div class="profile-tabs">
      <button class="profile-tab active" data-tab="info">
        <i class="fa-solid fa-user icon-xs"></i> Informasi
      </button>
      <button class="profile-tab" data-tab="security">
        <i class="fa-solid fa-lock icon-xs"></i> Keamanan
      </button>
      <button class="profile-tab" data-tab="notifications">
        <i class="fa-solid fa-bell icon-xs"></i> Notifikasi
      </button>
      <button class="profile-tab" data-tab="activity">
        <i class="fa-solid fa-clock-rotate-left icon-xs"></i> Aktivitas
      </button>
    </div>

    <!-- ── TAB: Info ── -->
    <div class="profile-tab-content active" id="tab-info">
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-user-pen icon-sm text-accent"></i>
          Edit Profil
        </div>

        <?php if (session()->getFlashdata('success')): ?>
          <div class="alert alert-success mb-3">
            <i class="fa-solid fa-circle-check icon-xs"></i>
            <?= esc(session()->getFlashdata('success')) ?>
          </div>
        <?php endif; ?>
        <?php if ($errors = session()->getFlashdata('errors')): ?>
          <div class="alert alert-danger mb-3">
            <?php foreach ((array)$errors as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/profile/update" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="form-col">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?= esc($user['username']) ?>" disabled />
              <div class="form-hint">Username tidak dapat diubah.</div>
            </div>
            <div class="form-col">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= esc($user['email']) ?>" disabled />
              <div class="form-hint">Hubungi admin untuk mengubah email.</div>
            </div>
            <div class="form-col">
              <label class="form-label">Nama Tampilan</label>
              <input type="text" name="nickname" class="form-control"
                     value="<?= esc($user['nickname'] ?? '') ?>"
                     placeholder="Nama yang ditampilkan di sistem" />
            </div>
            <div class="form-col">
              <label class="form-label">Jabatan / Job Title</label>
              <input type="text" name="job_title" class="form-control"
                     value="<?= esc($user['job_title'] ?? '') ?>"
                     placeholder="misal: Frontend Developer" />
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-floppy-disk icon-xs"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>

      <!-- Role info -->
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-shield-halved icon-sm" style="color:<?= $roleColor ?>"></i>
          Role & Akses
        </div>
        <div class="role-info-row">
          <div class="role-info-badge" style="background:<?= $roleColor ?>22;border-color:<?= $roleColor ?>44">
            <span class="role-info-dot" style="background:<?= $roleColor ?>"></span>
            <span style="color:<?= $roleColor ?>;font-weight:700"><?= esc($roleLabels[$user['role']] ?? $user['role']) ?></span>
          </div>
          <div class="role-info-desc">
            <?php
              $roleDescs = [
                'super_admin' => 'Memiliki akses penuh ke seluruh fitur dan pengaturan sistem.',
                'admin'       => 'Dapat mengelola task dan user, kecuali pengaturan role dan sistem.',
                'manager'     => 'Dapat mengelola task, melihat team, laporan, dan tools.',
                'member'      => 'Dapat membuat dan mengelola task sendiri serta menggunakan tools.',
              ];
            ?>
            <?= esc($roleDescs[$user['role']] ?? 'Role kustom.') ?>
          </div>
        </div>

        <?php
          $roleModel = new \App\Models\RoleModel();
          $roleData  = $roleModel->findBySlug($user['role']);
          $perms     = $roleData['permissions'] ?? [];
          $isSA      = $user['role'] === 'super_admin';
        ?>
        <div class="role-perms-list">
          <?php foreach (\App\Models\RoleModel::PERMISSIONS as $groupKey => $group): ?>
          <div class="role-perm-group">
            <div class="role-perm-group-label"><?= esc($group['label']) ?></div>
            <div class="perm-items">
              <?php foreach ($group['items'] as $permKey => $permLabel): ?>
              <?php $has = $isSA || in_array($permKey, $perms, true); ?>
              <span class="perm-item <?= $has ? 'perm-has' : 'perm-missing' ?>">
                <i class="fa-solid <?= $has ? 'fa-check' : 'fa-xmark' ?>"></i>
                <?= esc(str_replace('_', ' ', $permKey)) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── TAB: Security ── -->
    <div class="profile-tab-content" id="tab-security">
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-key icon-sm text-warn"></i>
          Ganti Password
        </div>
        <form method="POST" action="/profile/change-password">
          <?= csrf_field() ?>
          <div class="security-notice">
            <i class="fa-solid fa-circle-info"></i>
            Password minimal 8 karakter. Gunakan kombinasi huruf, angka, dan simbol untuk keamanan optimal.
          </div>
          <div class="form-grid">
            <div class="form-col-full">
              <label class="form-label">Password Saat Ini <span class="required">*</span></label>
              <div class="input-password-wrap">
                <input type="password" name="current_password" class="form-control"
                       placeholder="Masukkan password lama" required />
                <button type="button" class="btn-toggle-password" onclick="togglePwd(this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="form-col">
              <label class="form-label">Password Baru <span class="required">*</span></label>
              <div class="input-password-wrap">
                <input type="password" name="new_password" id="newPwd" class="form-control"
                       placeholder="Minimal 8 karakter" required
                       oninput="checkPwdStrength(this.value)" />
                <button type="button" class="btn-toggle-password" onclick="togglePwd(this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <div class="pwd-strength-bar">
                <div id="pwdStrengthFill" class="pwd-strength-fill"></div>
              </div>
              <div id="pwdStrengthLabel" class="form-hint"></div>
            </div>
            <div class="form-col">
              <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
              <div class="input-password-wrap">
                <input type="password" name="confirm_password" class="form-control"
                       placeholder="Ulangi password baru" required />
                <button type="button" class="btn-toggle-password" onclick="togglePwd(this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-key icon-xs"></i> Ubah Password
            </button>
          </div>
        </form>
      </div>

      <!-- Login history -->
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-shield icon-sm text-accent"></i>
          Riwayat Login (20 Terakhir)
        </div>
        <?php
          $loginHistory = \Config\Database::connect()
            ->table('tb_auth_login_attempts')
            ->where('identifier', $user['email'])
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get()->getResultArray();
        ?>
        <?php if (empty($loginHistory)): ?>
          <div class="empty-state-sm">Belum ada riwayat login.</div>
        <?php else: ?>
          <ul class="login-list">
            <?php foreach ($loginHistory as $h): ?>
            <li class="login-item <?= $h['success'] ? 'login-success' : 'login-fail' ?>">
              <i class="fa-solid <?= $h['success'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> login-icon"></i>
              <div class="login-info">
                <div><?= $h['success'] ? '<strong>Login berhasil</strong>' : '<strong>Login gagal</strong>' ?></div>
                <div class="activity-meta">
                  <?= date('d M Y H:i:s', strtotime($h['created_at'])) ?>
                  <?php if ($h['ip_address']): ?> · IP: <code><?= esc($h['ip_address']) ?></code><?php endif; ?>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── TAB: Notifications ── -->
    <div class="profile-tab-content" id="tab-notifications">
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-sliders icon-sm text-accent"></i>
          Preferensi Notifikasi
          <?php if ($unreadCount > 0): ?>
            <span class="badge badge-accent ml-auto"><?= $unreadCount ?> belum dibaca</span>
          <?php endif; ?>
          <a href="/notifications" class="btn btn-ghost btn-xs ml-auto">
            <i class="fa-solid fa-arrow-up-right-from-square icon-xs"></i> Lihat Semua
          </a>
        </div>

        <p class="notif-prefs-desc">Pilih jenis notifikasi yang ingin Anda terima di dalam aplikasi.</p>

        <form id="notifPrefsForm">
          <?= csrf_field() ?>
          <div class="notif-prefs-full-grid">
            <?php foreach ($notifTypes as $type => $info): ?>
            <div class="notif-pref-full-row">
              <div class="notif-pref-icon" style="color:<?= $info['color'] ?>">
                <i class="fa-solid <?= $info['icon'] ?>"></i>
              </div>
              <div class="notif-pref-info">
                <div class="notif-pref-name"><?= ucfirst($type) ?></div>
                <div class="notif-pref-sub">
                  <?php
                    $typeDescs = [
                      'info'    => 'Informasi umum dari sistem',
                      'success' => 'Notifikasi keberhasilan aksi',
                      'warning' => 'Peringatan dan alert penting',
                      'error'   => 'Error dan kegagalan sistem',
                      'task'    => 'Update task dan assignment',
                      'user'    => 'Perubahan akun dan user',
                      'team'    => 'Aktivitas tim',
                      'system'  => 'Pengumuman sistem & maintenance',
                    ];
                  ?>
                  <?= esc($typeDescs[$type] ?? '') ?>
                </div>
              </div>
              <label class="switch ml-auto">
                <input type="checkbox"
                       name="pref_<?= $type ?>"
                       value="1"
                       <?= ($prefs[$type] ?? true) ? 'checked' : '' ?>
                       onchange="saveNotifPref('<?= $type ?>', this.checked)" />
                <span class="switch-track"></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </form>
      </div>

      <!-- Recent notifications preview -->
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-bell icon-sm text-accent"></i>
          Notifikasi Terbaru
          <a href="/notifications" class="btn btn-ghost btn-xs ml-auto">Lihat Semua</a>
        </div>
        <?php
          $recentNotifs = $notifModel->getForUser((int)$user['id'], 5);
        ?>
        <?php if (empty($recentNotifs)): ?>
          <div class="empty-state-sm">Belum ada notifikasi.</div>
        <?php else: ?>
          <?php foreach ($recentNotifs as $n):
            $ti = $notifTypes[$n['type']] ?? $notifTypes['info'];
          ?>
          <div class="notif-preview-item <?= $n['is_read'] ? '' : 'notif-preview-unread' ?>">
            <div class="notif-preview-icon" style="color:<?= $ti['color'] ?>">
              <i class="fa-solid <?= $ti['icon'] ?>"></i>
            </div>
            <div class="notif-preview-body">
              <div class="notif-preview-title"><?= esc($n['title']) ?></div>
              <?php if ($n['message']): ?>
                <div class="notif-preview-msg"><?= esc(mb_substr($n['message'], 0, 80)) . (mb_strlen($n['message']) > 80 ? '…' : '') ?></div>
              <?php endif; ?>
              <div class="notif-preview-time"><?= $notifModel->timeAgo($n['created_at']) ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
              <span class="notif-preview-dot"></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── TAB: Activity ── -->
    <div class="profile-tab-content" id="tab-activity">
      <div class="card profile-section-card">
        <div class="card-head">
          <i class="fa-solid fa-clock-rotate-left icon-sm text-accent"></i>
          Aktivitas Terbaru (20 Terakhir)
        </div>
        <?php if (empty($activity)): ?>
          <div class="empty-state-sm">Belum ada aktivitas tercatat.</div>
        <?php else: ?>
          <ul class="activity-timeline">
            <?php foreach ($activity as $a): ?>
            <li class="activity-tl-item">
              <div class="activity-tl-dot"></div>
              <div class="activity-tl-content">
                <div class="activity-tl-action">
                  <i class="fa-solid <?= match(strtolower(explode('_', $a['action'])[0])) {
                    'login'  => 'fa-arrow-right-to-bracket',
                    'logout' => 'fa-arrow-right-from-bracket',
                    'create' => 'fa-plus',
                    'update', 'change' => 'fa-pen',
                    'delete' => 'fa-trash',
                    default  => 'fa-circle-dot',
                  } ?> icon-xs text-muted"></i>
                  <?= esc(ucwords(str_replace('_', ' ', $a['action']))) ?>
                </div>
                <?php if ($a['description']): ?>
                  <div class="activity-tl-desc"><?= esc($a['description']) ?></div>
                <?php endif; ?>
                <div class="activity-tl-meta">
                  <i class="fa-solid fa-clock icon-xs"></i>
                  <?= date('d M Y H:i', strtotime($a['created_at'])) ?>
                  <?php if ($a['ip_address']): ?>
                    · <i class="fa-solid fa-location-dot icon-xs"></i> <?= esc($a['ip_address']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.profile-main -->
</div>

<script>
// ── Tabs ──────────────────────────────────────────────────────
document.querySelectorAll('.profile-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.profile-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
  });
});

// ── Avatar auto-submit ────────────────────────────────────────
function submitAvatarForm(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('profileAvatarImg').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
    document.getElementById('avatarForm').requestSubmit();
  }
}

// ── Password toggle ───────────────────────────────────────────
function togglePwd(btn) {
  const inp  = btn.previousElementSibling;
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

// ── Password strength ─────────────────────────────────────────
function checkPwdStrength(val) {
  const fill  = document.getElementById('pwdStrengthFill');
  const label = document.getElementById('pwdStrengthLabel');
  if (!fill) return;

  let score = 0;
  if (val.length >= 8)  score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { w: '25%', color: 'var(--danger)',  text: 'Sangat lemah' },
    { w: '50%', color: 'var(--warn)',    text: 'Lemah' },
    { w: '75%', color: '#f59e0b',        text: 'Cukup kuat' },
    { w: '100%', color: 'var(--success)', text: 'Kuat ✓' },
  ];
  const lvl = levels[Math.max(0, score - 1)] || levels[0];
  fill.style.width = val.length ? lvl.w : '0';
  fill.style.background = lvl.color;
  label.textContent = val.length ? lvl.text : '';
  label.style.color = lvl.color;
}

// ── Notification preferences ──────────────────────────────────
async function saveNotifPref(type, enabled) {
  const csrf = getAppCsrf();
  const body = { [csrf.key]: csrf.val };
  body[`pref_${type}`] = enabled ? '1' : '';
  try {
    await fetch('/notifications/preferences', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    if (typeof showToast === 'function') showToast('Preferensi disimpan', 'success');
  } catch(e) {
    if (typeof showToast === 'function') showToast('Gagal menyimpan preferensi', 'error');
  }
}

// ── Switch to security tab if error ──────────────────────────
<?php if (session()->getFlashdata('error')): ?>
document.querySelector('[data-tab="security"]')?.click();
<?php endif; ?>
</script>
