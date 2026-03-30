<?php
/** @var array $projects */
/** @var array $clients */
$projects = $projects ?? [];
$clients  = $clients ?? [];
$sessionUserPerms = session()->get('user_perms') ?? [];
$canManage = (session()->get('user_role') === 'super_admin')
    || in_array('manage_projects', (array) $sessionUserPerms, true);
?>

<link rel="stylesheet" href="/assets/css/pages/clients-projects.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Projects</h2>
    <p class="page-sub">Project per klien; <strong>Work items</strong> dikelola di halaman project (terpisah dari Task internal).</p>
  </div>
  <?php if ($canManage): ?>
  <div class="page-header-right">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('proj-add-modal').classList.add('open')">Tambah project</button>
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
            <th>Klien</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): ?>
          <tr>
            <td><strong><?= esc($p['name'] ?? '') ?></strong></td>
            <td><?= esc($p['client_name'] ?? '—') ?></td>
            <td><span class="cp-badge"><?= esc($p['status'] ?? '') ?></span></td>
            <td class="cp-actions"><a class="btn btn-ghost btn-sm" href="/projects/<?= (int) $p['id'] ?>">Task</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($projects)): ?>
          <tr><td colspan="4" class="cp-empty">Belum ada project.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="proj-add-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title">Tambah project</h3>
      <button type="button" class="btn-icon" onclick="document.getElementById('proj-add-modal').classList.remove('open')">&times;</button>
    </div>
    <form method="post" action="/projects/store" class="modal-body">
      <?= csrf_field() ?>
      <div class="form-stack">
        <label>Klien (opsional)
          <select name="client_id" class="form-control">
            <option value="">—</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nama <input type="text" name="name" class="form-control" required></label>
        <label>Deskripsi <textarea name="description" class="form-control" rows="2"></textarea></label>
      </div>
      <div class="modal-footer" style="margin-top:1rem;">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('proj-add-modal').classList.remove('open')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
