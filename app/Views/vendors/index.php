<?php
/** @var array $accounts */
/** @var array $users */
/** @var array $teams */
/** @var array $targetByAccount */
/** @var array $allocByAccount */
/** @var array $primaryByAccount */
/** @var array $ruleByAccount */
?>

<link rel="stylesheet" href="/assets/css/pages/vendors.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Vendor Accounts</h2>
    <p class="page-sub">Kelola akun vendor, target, alokasi kreator, dan assignment default.</p>
  </div>
</div>

<div class="card mb-14 vendor-filter-card">
  <div class="card-body vendor-create-card">
    <form method="POST" action="/vendors/store" class="vendor-create-form">
      <?= csrf_field() ?>
      <input type="text" name="name" class="form-control" placeholder="Nama vendor" required>
      <input type="text" name="platform" class="form-control" placeholder="Platform">
      <input type="text" name="owner_name" class="form-control" placeholder="Owner">
      <select name="contract_mode" class="form-control">
        <option value="monthly">Monthly</option>
        <option value="lifetime">Lifetime</option>
        <option value="on_demand">On demand</option>
      </select>
      <input type="date" name="next_review_date" class="form-control" title="Review berikutnya">
      <input type="date" name="contract_end_date" class="form-control" title="Tanggal berakhir kontrak">
      <select name="status" class="form-control">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button class="btn btn-primary btn-sm vendor-create-submit" type="submit">Tambah Vendor</button>
    </form>
  </div>
</div>

<?php if (empty($accounts)): ?>
  <div class="card vendor-table-card">
    <div class="empty-state">
      <div class="empty-title">Belum ada akun vendor</div>
      <div class="empty-desc">Tambahkan vendor pertama menggunakan form di atas.</div>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="vendor-table-compact">
        <thead>
          <tr>
            <th>#</th>
            <th>Vendor</th>
            <th>Platform</th>
            <th>Model</th>
            <th>Review</th>
            <th>Berakhir</th>
            <th>Status</th>
            <th>Target</th>
            <th>Alokasi</th>
            <th class="text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $acc): ?>
            <?php
              $accId = (int) $acc['id'];
              $rule  = $ruleByAccount[$accId] ?? null;
              $assigned = $allocByAccount[$accId] ?? [];
              $primaryUserId = (int) ($primaryByAccount[$accId] ?? 0);
              $monthStart = date('Y-m-01');
              $targetVal = 0;
              foreach (($targetByAccount[$accId] ?? []) as $t) {
                  if (($t['period_type'] ?? '') === 'monthly' && ($t['period_start'] ?? '') === $monthStart) {
                      $targetVal = (int) ($t['target_value'] ?? 0);
                      break;
                  }
              }
            ?>
            <tr>
              <td>#<?= $accId ?></td>
              <td>
                <div class="vendor-main"><?= esc($acc['name']) ?></div>
                <div class="vendor-sub"><?= esc($acc['owner_name'] ?: '—') ?></div>
              </td>
              <td><?= esc($acc['platform'] ?: '—') ?></td>
              <td><?= esc($acc['contract_mode'] ?? 'monthly') ?></td>
              <td><?= !empty($acc['next_review_date']) ? esc($acc['next_review_date']) : '—' ?></td>
              <td><?= !empty($acc['contract_end_date']) ? esc($acc['contract_end_date']) : '—' ?></td>
              <td><span class="badge"><?= esc($acc['status']) ?></span></td>
              <td><?= $targetVal ?></td>
              <td><?= count($assigned) ?> kreator</td>
              <td class="text-right">
                <div class="vendor-row-actions">
                  <button type="button"
                          class="btn btn-ghost btn-sm vendor-edit-btn"
                          data-id="<?= $accId ?>"
                          data-name="<?= esc($acc['name'], 'attr') ?>"
                          data-platform="<?= esc($acc['platform'] ?? '', 'attr') ?>"
                          data-owner="<?= esc($acc['owner_name'] ?? '', 'attr') ?>"
                          data-status="<?= esc($acc['status'] ?? 'active', 'attr') ?>"
                          data-contract="<?= esc($acc['contract_mode'] ?? 'monthly', 'attr') ?>"
                          data-review="<?= esc($acc['next_review_date'] ?? '', 'attr') ?>"
                          data-end="<?= esc($acc['contract_end_date'] ?? '', 'attr') ?>"
                          data-target="<?= (int) $targetVal ?>"
                          data-primary-user="<?= $primaryUserId ?>"
                          data-default-user="<?= (int) ($rule['default_user_id'] ?? 0) ?>"
                          data-default-team="<?= (int) ($rule['default_team_id'] ?? 0) ?>"
                          data-priority="<?= (int) ($rule['priority'] ?? 100) ?>"
                          data-rule-status="<?= esc($rule['status'] ?? 'active', 'attr') ?>"
                          data-assigned='<?= esc(json_encode(array_values($assigned)), "attr") ?>'>
                    Edit
                  </button>
                  <form method="POST" action="/vendors/<?= $accId ?>/delete" class="inline-form"
                        data-confirm="Hapus akun vendor ini?"
                        data-confirm-title="Hapus vendor?"
                        data-confirm-ok-text="Hapus"
                        data-confirm-ok-variant="danger">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_vendor_management_modal.php'; ?>
