<link rel="stylesheet" href="/assets/css/pages/team-users.css" />

<div class="page-header">
  <div class="page-header-left">
    <a href="/team/users" class="btn btn-ghost btn-sm mb-2">
      <i class="fa-solid fa-arrow-left icon-xs"></i> Kembali
    </a>
    <div class="user-profile-mini">
      <img src="<?= \App\Models\UserModel::avatarUrl($user['avatar'], $user['nickname'] ?? $user['username']) ?>"
           class="user-avatar-sm" alt="Avatar" />
      <div>
        <h2 class="page-title"><?= esc($user['nickname'] ?? $user['username']) ?></h2>
        <p class="page-sub">@<?= esc($user['username']) ?> · <?= esc($roleLabels[$user['role']] ?? $user['role']) ?></p>
      </div>
    </div>
  </div>
</div>

<div class="activity-grid">

  <!-- Activity log -->
  <div class="card activity-card">
    <div class="card-head">
      <i class="fa-solid fa-clock-rotate-left icon-sm text-accent"></i>
      Activity Log
    </div>
    <?php if (empty($activity)): ?>
      <div class="empty-state-sm">Belum ada aktivitas tercatat.</div>
    <?php else: ?>
      <ul class="activity-list">
        <?php foreach ($activity as $a): ?>
        <li class="activity-item">
          <div class="activity-dot"></div>
          <div class="activity-content">
            <div class="activity-action"><?= esc(str_replace('_', ' ', $a['action'])) ?></div>
            <?php if ($a['description']): ?>
              <div class="activity-desc"><?= esc($a['description']) ?></div>
            <?php endif; ?>
            <div class="activity-meta">
              <?= date('d M Y H:i', strtotime($a['created_at'])) ?>
              <?php if ($a['ip_address']): ?> · <?= esc($a['ip_address']) ?><?php endif; ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Login history -->
  <div class="card activity-card">
    <div class="card-head">
      <i class="fa-solid fa-key icon-sm text-warn"></i>
      Login History
    </div>
    <?php if (empty($loginHistory)): ?>
      <div class="empty-state-sm">Tidak ada riwayat login.</div>
    <?php else: ?>
      <ul class="login-list">
        <?php foreach ($loginHistory as $h): ?>
        <li class="login-item <?= $h['success'] ? 'login-success' : 'login-fail' ?>">
          <i class="fa-solid <?= $h['success'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> login-icon"></i>
          <div class="login-info">
            <div><?= $h['success'] ? 'Login berhasil' : 'Login gagal' ?></div>
            <div class="activity-meta">
              <?= date('d M Y H:i:s', strtotime($h['created_at'])) ?>
              <?php if ($h['ip_address']): ?> · IP: <?= esc($h['ip_address']) ?><?php endif; ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

</div>
