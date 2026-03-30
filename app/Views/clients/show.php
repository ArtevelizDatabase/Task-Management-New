<?php
/** @var array $client */
/** @var array $projects */
$client   = $client ?? [];
$projects = $projects ?? [];
$sessionUserPerms = session()->get('user_perms') ?? [];
$canManage = (session()->get('user_role') === 'super_admin')
    || in_array('manage_clients', (array) $sessionUserPerms, true);
?>

<link rel="stylesheet" href="/assets/css/pages/clients-projects.css" />

<div class="page-header">
  <div class="page-header-left">
    <a href="/clients" class="page-back-link">← Kembali</a>
    <h2 class="page-title"><?= esc($client['name'] ?? '') ?></h2>
    <p class="page-sub">Status: <?= esc($client['status'] ?? '') ?>
      <?php if (!empty($client['contact'])): ?> · <?= esc($client['contact']) ?><?php endif; ?>
    </p>
  </div>
</div>

<div class="card cp-card">
  <div class="card-body">
    <h3 class="cp-section-title">Data klien</h3>
    <?php if ($canManage): ?>
    <form method="post" action="/clients/<?= (int) $client['id'] ?>/update" class="form-stack cp-form">
      <?= csrf_field() ?>
      <label>Nama <input type="text" name="name" class="form-control" value="<?= esc($client['name'] ?? '') ?>" required></label>
      <label>Kontak <input type="text" name="contact" class="form-control" value="<?= esc($client['contact'] ?? '') ?>"></label>
      <label>Email <input type="email" name="email" class="form-control" value="<?= esc($client['email'] ?? '') ?>"></label>
      <label>Telepon <input type="text" name="phone" class="form-control" value="<?= esc($client['phone'] ?? '') ?>"></label>
      <label>Status
        <select name="status" class="form-control">
          <option value="active" <?= ($client['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
          <option value="inactive" <?= ($client['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>inactive</option>
        </select>
      </label>
      <label>Catatan <textarea name="notes" class="form-control" rows="3"><?= esc($client['notes'] ?? '') ?></textarea></label>
      <button type="submit" class="btn btn-primary">Simpan perubahan</button>
    </form>
    <form method="post" action="/clients/<?= (int) $client['id'] ?>/delete" class="cp-danger-form" onsubmit="return confirm('Hapus klien ini?');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-ghost" style="color:var(--danger,#b91c1c);">Hapus klien</button>
    </form>
    <?php else: ?>
    <dl class="cp-dl">
      <dt>Email</dt><dd><?= esc($client['email'] ?? '—') ?></dd>
      <dt>Telepon</dt><dd><?= esc($client['phone'] ?? '—') ?></dd>
      <dt>Catatan</dt><dd><?= nl2br(esc($client['notes'] ?? '—')) ?></dd>
    </dl>
    <?php endif; ?>
  </div>
</div>

<div class="card cp-card">
  <div class="card-body">
    <div class="cp-toolbar">
      <h3 class="cp-section-title">Projects</h3>
      <?php
      $canProj = (session()->get('user_role') === 'super_admin')
          || in_array('manage_projects', (array) $sessionUserPerms, true);
      ?>
      <?php if ($canProj): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('project-add-modal').classList.add('open')">+ Project</button>
      <?php endif; ?>
    </div>
    <ul class="cp-list">
      <?php foreach ($projects as $p): ?>
      <li>
        <a href="/projects/<?= (int) $p['id'] ?>"><?= esc($p['name'] ?? '') ?></a>
        <span class="cp-muted"><?= esc($p['status'] ?? '') ?></span>
      </li>
      <?php endforeach; ?>
      <?php if (empty($projects)): ?>
      <li class="cp-empty">Belum ada project.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>

<?php if ($canProj): ?>
<div class="modal-overlay" id="project-add-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title">Tambah project</h3>
      <button type="button" class="btn-icon" onclick="document.getElementById('project-add-modal').classList.remove('open')">&times;</button>
    </div>
    <form method="post" action="/projects/store" class="modal-body">
      <?= csrf_field() ?>
      <input type="hidden" name="client_id" value="<?= (int) ($client['id'] ?? 0) ?>">
      <div class="form-stack">
        <label>Nama project <input type="text" name="name" class="form-control" required></label>
        <label>Deskripsi <textarea name="description" class="form-control" rows="2"></textarea></label>
      </div>
      <div class="modal-footer" style="margin-top:1rem;">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('project-add-modal').classList.remove('open')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
