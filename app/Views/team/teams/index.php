<link rel="stylesheet" href="/assets/css/pages/team-users.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Team Management</h2>
    <p class="page-sub">Buat dan kelola tim dalam organisasi</p>
  </div>
  <div class="page-header-right">
    <?php $cr = session()->get('user_role'); ?>
    <?php if (in_array($cr, ['super_admin','admin'])): ?>
    <button class="btn btn-primary" onclick="openModal('addTeamModal')">
      <i class="fa-solid fa-plus icon-xs"></i> Tambah Tim
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($teams)): ?>
<div class="card">
  <div class="empty-state">
    <i class="fa-solid fa-users-rectangle empty-icon"></i>
    <p>Belum ada tim. Buat tim pertama Anda!</p>
  </div>
</div>
<?php else: ?>
<div class="teams-grid">
  <?php foreach ($teams as $team): ?>
  <div class="team-card card">
    <div class="team-card-head">
      <div class="team-card-icon">
        <?= strtoupper(mb_substr($team['name'], 0, 2)) ?>
      </div>
      <div class="team-card-info">
        <div class="team-card-name"><?= esc($team['name']) ?></div>
        <div class="text-muted text-sm"><?= $team['member_count'] ?> anggota</div>
      </div>
      <?php if (in_array($cr, ['super_admin','admin'])): ?>
      <div class="team-card-actions">
        <button class="btn btn-ghost btn-xs"
                onclick="openEditTeamModal(<?= $team['id'] ?>, '<?= esc($team['name'], 'js') ?>', '<?= esc($team['description'] ?? '', 'js') ?>', <?= json_encode(array_column($team['members'], 'user_id')) ?>)"
                title="Edit Tim">
          <i class="fa-solid fa-pen icon-xs"></i>
        </button>
        <form method="POST" action="/team/teams/<?= $team['id'] ?>/delete"
              data-confirm="Hapus tim &quot;<?= esc($team['name']) ?>&quot;? Semua member akan dikeluarkan."
              data-confirm-ok-variant="danger" style="display:inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-xs text-danger" title="Hapus">
            <i class="fa-solid fa-trash icon-xs"></i>
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($team['description']): ?>
      <p class="team-card-desc"><?= esc($team['description']) ?></p>
    <?php endif; ?>

    <div class="team-members">
      <?php if (empty($team['members'])): ?>
        <span class="text-muted text-sm">Belum ada anggota</span>
      <?php else: ?>
        <div class="member-avatars">
          <?php foreach (array_slice($team['members'], 0, 6) as $m): ?>
            <img src="<?= \App\Models\UserModel::avatarUrl($m['avatar'], $m['nickname'] ?? $m['username']) ?>"
                 class="member-avatar-bubble"
                 title="<?= esc($m['nickname'] ?? $m['username']) ?>" alt="" />
          <?php endforeach; ?>
          <?php if (count($team['members']) > 6): ?>
            <span class="member-avatar-more">+<?= count($team['members']) - 6 ?></span>
          <?php endif; ?>
        </div>
        <div class="member-names">
          <?php foreach ($team['members'] as $m): ?>
            <span class="team-chip"><?= esc($m['nickname'] ?? $m['username']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Team Modal -->
<div id="addTeamModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head">
      <i class="fa-solid fa-users-rectangle"></i> Tambah Tim Baru
      <button type="button" class="modal-close" onclick="closeModal('addTeamModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="/team/teams/store">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Tim <span class="required">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="misal: Frontend Team" required />
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi tim (opsional)"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Anggota Awal</label>
          <div class="member-select-grid">
            <?php foreach ($users as $u): ?>
              <label class="checkbox-label">
                <input type="checkbox" name="member_ids[]" value="<?= $u['id'] ?>" />
                <span class="checkbox-custom"></span>
                <img src="<?= \App\Models\UserModel::avatarUrl($u['avatar'], $u['nickname'] ?? $u['username']) ?>"
                     class="member-avatar-tiny" alt="" />
                <?= esc($u['nickname'] ?? $u['username']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('addTeamModal')">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus icon-xs"></i> Buat Tim
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Team Modal -->
<div id="editTeamModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head">
      <i class="fa-solid fa-pen"></i> Edit Tim
      <button type="button" class="modal-close" onclick="closeModal('editTeamModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form id="editTeamForm" method="POST">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Tim <span class="required">*</span></label>
          <input type="text" id="editTeamName" name="name" class="form-control" required />
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea id="editTeamDesc" name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Anggota</label>
          <div id="editMemberGrid" class="member-select-grid">
            <?php foreach ($users as $u): ?>
              <label class="checkbox-label" data-uid="<?= $u['id'] ?>">
                <input type="checkbox" name="member_ids[]" value="<?= $u['id'] ?>" class="edit-member-check" />
                <span class="checkbox-custom"></span>
                <img src="<?= \App\Models\UserModel::avatarUrl($u['avatar'], $u['nickname'] ?? $u['username']) ?>"
                     class="member-avatar-tiny" alt="" />
                <?= esc($u['nickname'] ?? $u['username']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('editTeamModal')">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-floppy-disk icon-xs"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditTeamModal(id, name, desc, memberIds) {
  document.getElementById('editTeamForm').action = `/team/teams/${id}/update`;
  document.getElementById('editTeamName').value  = name;
  document.getElementById('editTeamDesc').value  = desc;
  document.querySelectorAll('.edit-member-check').forEach(cb => {
    cb.checked = memberIds.includes(parseInt(cb.value));
  });
  openModal('editTeamModal');
}
</script>
