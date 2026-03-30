<link rel="stylesheet" href="/assets/css/pages/team-users.css" />

<?php $isEdit = !empty($user); ?>

<div class="page-header">
  <div class="page-header-left">
    <a href="/team/users" class="btn btn-ghost btn-sm mb-2">
      <i class="fa-solid fa-arrow-left icon-xs"></i> Kembali
    </a>
    <h2 class="page-title"><?= $isEdit ? 'Edit User' : 'Tambah User Baru' ?></h2>
  </div>
</div>

<div class="form-card card">
  <form method="POST"
        action="<?= $isEdit ? "/team/users/{$user['id']}/update" : '/team/users/store' ?>"
        enctype="multipart/form-data"
        novalidate>
    <?= csrf_field() ?>

    <?php if ($errors = session()->getFlashdata('errors')): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation icon-sm"></i>
        <ul style="margin:4px 0 0 16px;padding:0">
          <?php foreach ((array)$errors as $e): ?>
            <li><?= esc($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="form-grid">

      <!-- Avatar -->
      <div class="form-col-full">
        <div class="avatar-upload-section">
          <?php
            $avatarUrl = $isEdit
              ? \App\Models\UserModel::avatarUrl($user['avatar'], $user['nickname'] ?? $user['username'])
              : 'https://ui-avatars.com/api/?name=U&background=4f46e5&color=fff&size=80&bold=true';
          ?>
          <img src="<?= $avatarUrl ?>" id="avatarPreview" class="avatar-preview" alt="Avatar" />
          <div class="avatar-upload-info">
            <label class="btn btn-ghost btn-sm" for="avatarInput">
              <i class="fa-solid fa-upload icon-xs"></i> Upload Foto
            </label>
            <input type="file" id="avatarInput" name="avatar" accept="image/*" class="d-none"
                   onchange="previewAvatar(this)" />
            <span class="text-muted text-sm">JPG, PNG, GIF · Maks 2MB</span>
          </div>
        </div>
      </div>

      <!-- Username -->
      <div class="form-col">
        <label class="form-label" for="username">Username <span class="required">*</span></label>
        <input type="text" id="username" name="username" class="form-control"
               value="<?= esc(old('username', $user['username'] ?? '')) ?>"
               placeholder="username" required />
      </div>

      <!-- Email -->
      <div class="form-col">
        <label class="form-label" for="email">Email <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= esc(old('email', $user['email'] ?? '')) ?>"
               placeholder="email@domain.com" required />
      </div>

      <!-- Nickname -->
      <div class="form-col">
        <label class="form-label" for="nickname">Nama Tampilan</label>
        <input type="text" id="nickname" name="nickname" class="form-control"
               value="<?= esc(old('nickname', $user['nickname'] ?? '')) ?>"
               placeholder="Nama yang ditampilkan" />
      </div>

      <!-- Job title -->
      <div class="form-col">
        <label class="form-label" for="job_title">Jabatan / Job Title</label>
        <input type="text" id="job_title" name="job_title" class="form-control"
               value="<?= esc(old('job_title', $user['job_title'] ?? '')) ?>"
               placeholder="misal: Frontend Developer" />
      </div>

      <!-- Role -->
      <div class="form-col">
        <label class="form-label" for="role">Role <span class="required">*</span></label>
        <select id="role" name="role" class="form-control" required>
          <?php foreach ($roleLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= old('role', $user['role'] ?? 'member') === $val ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status -->
      <div class="form-col">
        <label class="form-label" for="status">Status <span class="required">*</span></label>
        <select id="status" name="status" class="form-control" required>
          <option value="active"    <?= old('status', $user['status'] ?? 'active') === 'active'    ? 'selected' : '' ?>>Aktif</option>
          <option value="inactive"  <?= old('status', $user['status'] ?? 'active') === 'inactive'  ? 'selected' : '' ?>>Nonaktif</option>
          <option value="suspended" <?= old('status', $user['status'] ?? 'active') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>

      <!-- Password -->
      <div class="form-col">
        <label class="form-label" for="password">
          Password <?= $isEdit ? '<span class="text-muted">(kosongkan jika tidak diubah)</span>' : '<span class="required">*</span>' ?>
        </label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="<?= $isEdit ? 'Password baru (opsional)' : 'Password' ?>"
               <?= $isEdit ? '' : 'required' ?> autocomplete="new-password" />
      </div>

      <!-- Password confirm -->
      <div class="form-col">
        <label class="form-label" for="password_confirm">Konfirmasi Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
               placeholder="Ulangi password" autocomplete="new-password" />
      </div>

      <!-- Teams -->
      <div class="form-col-full">
        <label class="form-label">Assign ke Tim</label>
        <div class="teams-checkbox-grid">
          <?php
            $userTeamIds = $isEdit ? ($user['teams'] ?? []) : [];
          ?>
          <?php if (empty($teams)): ?>
            <span class="text-muted text-sm">Belum ada tim. <a href="/team/teams">Buat tim dulu.</a></span>
          <?php else: ?>
            <?php foreach ($teams as $t): ?>
              <label class="checkbox-label">
                <input type="checkbox" name="team_ids[]"
                       value="<?= $t['id'] ?>"
                       <?= in_array($t['id'], array_map('intval', (array)$userTeamIds)) ? 'checked' : '' ?> />
                <span class="checkbox-custom"></span>
                <?= esc($t['name']) ?>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.form-grid -->

    <div class="form-actions">
      <a href="/team/users" class="btn btn-ghost">Batal</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-<?= $isEdit ? 'floppy-disk' : 'plus' ?> icon-xs"></i>
        <?= $isEdit ? 'Simpan Perubahan' : 'Tambah User' ?>
      </button>
    </div>
  </form>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatarPreview').src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
