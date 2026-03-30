<link rel="stylesheet" href="/assets/css/pages/notifications.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Notifikasi</h2>
    <p class="page-sub"><?= $unreadCount ?> belum dibaca &middot; <?= $total ?> total</p>
  </div>
  <div class="page-header-right notif-bulk-actions">
    <?php if ($unreadCount > 0): ?>
    <button class="btn btn-ghost btn-sm" onclick="markAllRead()">
      <i class="fa-solid fa-check-double icon-xs"></i> Tandai Semua Dibaca
    </button>
    <?php endif; ?>
    <?php if ($total > 0): ?>
    <div class="dropdown" id="bulkDropdown">
      <button class="btn btn-ghost btn-sm" onclick="toggleDropdown('bulkDropdown')">
        <i class="fa-solid fa-ellipsis icon-xs"></i>
      </button>
      <div class="dropdown-menu">
        <button class="dropdown-item" onclick="deleteRead()">
          <i class="fa-solid fa-broom icon-xs text-warn"></i> Hapus yang sudah dibaca
        </button>
        <button class="dropdown-item text-danger" onclick="deleteAll()">
          <i class="fa-solid fa-trash icon-xs"></i> Hapus semua
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="notif-layout">
  <!-- Main list -->
  <div class="notif-main">
    <?php if (empty($notifications)): ?>
      <div class="card notif-empty-card">
        <div class="notif-empty">
          <div class="notif-empty-icon">
            <i class="fa-solid fa-bell-slash"></i>
          </div>
          <p class="notif-empty-title">Belum ada notifikasi</p>
          <p class="notif-empty-sub">Notifikasi akan muncul di sini saat ada pembaruan pada task, tim, atau akun Anda.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="notif-list card">
        <?php foreach ($notifications as $n):
          $typeInfo = $types[$n['type']] ?? $types['info'];
        ?>
        <div class="notif-item <?= $n['is_read'] ? 'notif-read' : 'notif-unread' ?>" id="notif-<?= $n['id'] ?>">
          <div class="notif-icon" style="background:<?= $typeInfo['color'] ?>15;color:<?= $typeInfo['color'] ?>">
            <i class="fa-solid <?= $typeInfo['icon'] ?>"></i>
          </div>
          <div class="notif-body">
            <div class="notif-title"><?= esc($n['title']) ?></div>
            <?php if ($n['message']): ?>
              <div class="notif-msg"><?= esc($n['message']) ?></div>
            <?php endif; ?>
            <div class="notif-meta">
              <span class="notif-time">
                <i class="fa-regular fa-clock"></i>
                <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
              </span>
              <?php if ($n['is_read'] && $n['read_at']): ?>
                <span class="notif-read-badge">
                  <i class="fa-solid fa-check"></i> Dibaca
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="notif-actions">
            <?php if ($n['is_read']): ?>
              <button class="btn btn-ghost btn-xs" onclick="markUnread(<?= $n['id'] ?>)" title="Tandai belum dibaca">
                <i class="fa-solid fa-envelope icon-xs"></i>
              </button>
            <?php else: ?>
              <button class="btn btn-ghost btn-xs" onclick="markRead(<?= $n['id'] ?>)" title="Tandai sudah dibaca">
                <i class="fa-solid fa-envelope-open icon-xs"></i>
              </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-xs text-danger" onclick="deleteNotif(<?= $n['id'] ?>)" title="Hapus">
              <i class="fa-solid fa-trash icon-xs"></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Sidebar: preferences -->
  <div class="notif-sidebar">
    <div class="card notif-prefs-card">
      <div class="card-head">
        <i class="fa-solid fa-sliders icon-sm text-accent"></i>
        Preferensi Notifikasi
      </div>
      <p class="notif-prefs-desc">Pilih jenis notifikasi yang ingin Anda terima.</p>
      <form id="prefsForm" class="notif-prefs-list">
        <?= csrf_field() ?>
        <?php foreach ($types as $type => $info): ?>
        <label class="notif-pref-row">
          <div class="notif-pref-info">
            <span class="notif-pref-icon" style="color:<?= $info['color'] ?>">
              <i class="fa-solid <?= $info['icon'] ?>"></i>
            </span>
            <span class="notif-pref-name"><?= ucfirst($type) ?></span>
          </div>
          <label class="switch">
            <input type="checkbox"
                   name="pref_<?= $type ?>"
                   value="1"
                   <?= ($prefs[$type] ?? true) ? 'checked' : '' ?>
                   onchange="savePref(this, '<?= $type ?>')" />
            <span class="switch-track"></span>
          </label>
        </label>
        <?php endforeach; ?>
      </form>
    </div>
  </div>
</div>

<script>
const _csrf = { key: '<?= csrf_token() ?>', val: '<?= csrf_hash() ?>' };

async function notifFetch(url, body = {}) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ [_csrf.key]: _csrf.val, ...body }),
  });
  return res.json();
}

async function markRead(id) {
  await notifFetch(`/notifications/${id}/read`);
  const el = document.getElementById(`notif-${id}`);
  if (el) { el.classList.replace('notif-unread', 'notif-read'); }
  updateNotifBadge();
  if (typeof showToast === 'function') showToast('Ditandai sudah dibaca', 'success');
}

async function markUnread(id) {
  await notifFetch(`/notifications/${id}/unread`);
  const el = document.getElementById(`notif-${id}`);
  if (el) { el.classList.replace('notif-read', 'notif-unread'); }
  if (typeof showToast === 'function') showToast('Ditandai belum dibaca', 'success');
}

async function markAllRead() {
  await notifFetch('/notifications/mark-all-read');
  document.querySelectorAll('.notif-unread').forEach(el => el.classList.replace('notif-unread', 'notif-read'));
  updateNotifBadge(0);
  if (typeof showToast === 'function') showToast('Semua ditandai sudah dibaca', 'success');
}

async function deleteNotif(id) {
  const ok = await appConfirm({ title: 'Hapus notifikasi ini?', okVariant: 'danger' });
  if (!ok) return;
  await notifFetch(`/notifications/${id}/delete`);
  const el = document.getElementById(`notif-${id}`);
  if (el) { el.style.transition = 'opacity .2s, max-height .3s'; el.style.opacity = '0'; el.style.maxHeight = '0'; el.style.overflow = 'hidden'; setTimeout(() => el.remove(), 300); }
  if (typeof showToast === 'function') showToast('Notifikasi dihapus', 'success');
}

async function deleteRead() {
  const ok = await appConfirm({ title: 'Hapus notifikasi yang sudah dibaca?', okVariant: 'danger' });
  if (!ok) return;
  await notifFetch('/notifications/delete-read');
  document.querySelectorAll('.notif-read').forEach(el => el.remove());
  if (typeof showToast === 'function') showToast('Notifikasi yang sudah dibaca dihapus', 'success');
  setTimeout(() => location.reload(), 600);
}

async function deleteAll() {
  const ok = await appConfirm({ title: 'Hapus SEMUA notifikasi?', message: 'Tindakan ini tidak dapat dibatalkan.', okVariant: 'danger' });
  if (!ok) return;
  await notifFetch('/notifications/delete-all');
  if (typeof showToast === 'function') showToast('Semua notifikasi dihapus', 'success');
  setTimeout(() => location.reload(), 600);
}

async function savePref(cb, type) {
  await notifFetch('/notifications/preferences', { [`pref_${type}`]: cb.checked ? '1' : '' });
  if (typeof showToast === 'function') showToast('Preferensi disimpan', 'success');
}

function toggleDropdown(id) {
  document.getElementById(id)?.classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

function updateNotifBadge(count) {
  const badge = document.getElementById('notifBadge');
  if (!badge) return;
  if (count === undefined) {
    fetch('/notifications/unread-count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json()).then(d => {
        badge.textContent = d.count > 0 ? d.count : '';
        badge.style.display = d.count > 0 ? '' : 'none';
      });
  } else {
    badge.textContent = count > 0 ? count : '';
    badge.style.display = count > 0 ? '' : 'none';
  }
}
</script>
