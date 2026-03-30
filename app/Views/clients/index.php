<?php
/** @var array $clients */
$clients = $clients ?? [];
$sessionUserPerms = session()->get('user_perms') ?? [];
$canManage = (session()->get('user_role') === 'super_admin')
    || in_array('manage_clients', (array) $sessionUserPerms, true);
?>

<link rel="stylesheet" href="/assets/css/pages/clients-projects.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Manajemen Klien</h2>
    <p class="page-sub">Daftar klien dan jumlah project / task terkait.</p>
  </div>
  <?php if ($canManage): ?>
  <div class="page-header-right">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('client-add-modal').classList.add('open')">
      Tambah klien
    </button>
  </div>
  <?php endif; ?>
</div>

<div class="card cp-card">
  <div class="card-body">
    <div class="table-wrap">
      <table class="table cp-table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Kontak</th>
            <th>Status</th>
            <th>Projects</th>
            <th>Tasks</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
          <tr>
            <td><strong><?= esc($c['name'] ?? '') ?></strong></td>
            <td><?= esc($c['contact'] ?? '') ?></td>
            <td><span class="cp-badge"><?= esc($c['status'] ?? '') ?></span></td>
            <td><?= (int) ($c['project_count'] ?? 0) ?></td>
            <td><?= (int) ($c['task_count'] ?? 0) ?></td>
            <td class="cp-actions">
              <a class="btn btn-ghost btn-sm" href="/clients/<?= (int) $c['id'] ?>">Detail</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($clients)): ?>
          <tr><td colspan="6" class="cp-empty">Belum ada klien.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="client-add-modal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 class="modal-title">Tambah klien</h3>
      <button type="button" class="btn-icon" onclick="document.getElementById('client-add-modal').classList.remove('open')">&times;</button>
    </div>
    <form method="post" action="/clients/store" class="modal-body">
      <?= csrf_field() ?>
      <div class="form-stack">
        <label>Nama <input type="text" name="name" class="form-control" required></label>
        <label>Kontak <input type="text" name="contact" class="form-control"></label>
        <label>Email <input type="email" name="email" class="form-control"></label>
        <label>Telepon <input type="text" name="phone" class="form-control"></label>
        <label>Catatan <textarea name="notes" class="form-control" rows="2"></textarea></label>
      </div>
      <div class="modal-footer" style="margin-top:1rem;">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('client-add-modal').classList.remove('open')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
