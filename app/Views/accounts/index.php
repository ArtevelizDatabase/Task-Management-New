<?php
/** @var array $accounts */
/** @var array $filters */
/** @var array $users */
/** @var array $teams */
/** @var array $targetByAccount */
/** @var array $allocByAccount */
/** @var array $primaryByAccount */
/** @var array $ruleByAccount */
?>

<link rel="stylesheet" href="/assets/css/pages/accounts.css" />

<div class="page-header">
  <div class="page-header-left">
    <h2 class="page-title">Accounts</h2>
    <p class="page-sub">Master data akun (Kantor + Vendor) untuk dropdown Account di Tasks.</p>
  </div>
</div>

<div class="accounts-page">
  <div class="card">
    <div class="card-body">
      <div class="accounts-toolbar">
        <form method="GET" action="/accounts" class="accounts-filter">
          <select name="type" class="form-control">
            <option value="">Semua Type</option>
            <option value="office" <?= ($filters['type'] ?? '') === 'office' ? 'selected' : '' ?>>Office</option>
            <option value="vendor" <?= ($filters['type'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendor</option>
          </select>
          <input type="text" name="q" class="form-control" placeholder="Cari nama..." value="<?= esc($filters['q'] ?? '') ?>">
          <div class="accounts-filter-actions">
            <button class="btn btn-ghost" type="submit">Filter</button>
            <a class="btn btn-ghost" href="/accounts">Reset</a>
          </div>
        </form>
        <div class="accounts-actions">
          <button type="button" class="btn btn-primary" onclick="openModal('add-account-modal')">
            Tambah Account
          </button>
        </div>
      </div>
    </div>
  </div>

<div class="modal-overlay" id="add-account-modal">
  <div class="modal modal-task" style="max-width: 860px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Account</h3>
      <button type="button" class="btn-icon" onclick="closeModal('add-account-modal')">
        <i class="fa-solid fa-xmark u-icon-sm" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body modal-scroll">
      <form method="POST" action="/accounts/store" class="accounts-create" id="accountsCreateForm">
        <?= csrf_field() ?>
        <div class="ac-row ac-row-1">
          <input type="text" name="name" class="form-control" placeholder="Nama account (mis. Annora / Client X)" required>
          <select name="type" class="form-control" title="Type" id="ac-type">
            <option value="office">Office</option>
            <option value="vendor">Vendor</option>
          </select>
          <select name="status" class="form-control" title="Status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="ac-row ac-row-2" id="ac-vendor-fields">
          <input type="text" name="platform" class="form-control" placeholder="Platform (vendor)">
          <input type="text" name="owner_name" class="form-control" placeholder="Owner (vendor)">
          <select name="contract_mode" class="form-control" title="Contract (vendor)">
            <option value="monthly">Monthly</option>
            <option value="lifetime">Lifetime</option>
            <option value="on_demand">On demand</option>
          </select>
          <input type="date" name="next_review_date" class="form-control" title="Review berikutnya (vendor)">
          <input type="date" name="contract_end_date" class="form-control" title="Berakhir kontrak (vendor)">
          <input type="text" name="notes" class="form-control" placeholder="Notes (opsional)">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('add-account-modal')">Batal</button>
      <button type="submit" form="accountsCreateForm" class="btn btn-primary">Tambah</button>
    </div>
  </div>
</div>

  <div class="card">
    <div class="table-wrap">
      <table class="accounts-table">
      <thead>
      <tr>
        <th>ID</th>
        <th>Nama</th>
        <th>Type</th>
        <th>Status</th>
        <th>Platform</th>
        <th>Owner</th>
        <?php if (($filters['type'] ?? '') === 'vendor'): ?>
          <th>Target</th>
          <th>Alokasi</th>
        <?php endif; ?>
        <th class="text-right">Aksi</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach (($accounts ?? []) as $acc): ?>
        <?php $id = (int) ($acc['id'] ?? 0); ?>
        <?php
          $isVendorRow = (($acc['type'] ?? '') === 'vendor');
          $rule  = $ruleByAccount[$id] ?? null;
          $assigned = $allocByAccount[$id] ?? [];
          $primaryUserId = (int) ($primaryByAccount[$id] ?? 0);
          $monthStart = date('Y-m-01');
          $targetVal = 0;
          foreach (($targetByAccount[$id] ?? []) as $t) {
              if (($t['period_type'] ?? '') === 'monthly' && ($t['period_start'] ?? '') === $monthStart) {
                  $targetVal = (int) ($t['target_value'] ?? 0);
                  break;
              }
          }
        ?>
        <tr>
          <td>#<?= $id ?></td>
          <td><?= esc($acc['name'] ?? '') ?></td>
          <td><span class="badge"><?= esc($acc['type'] ?? '') ?></span></td>
          <td><span class="badge"><?= esc($acc['status'] ?? '') ?></span></td>
          <td><?= esc($acc['platform'] ?: '—') ?></td>
          <td><?= esc($acc['owner_name'] ?: '—') ?></td>
          <?php if (($filters['type'] ?? '') === 'vendor'): ?>
            <td><?= $isVendorRow ? $targetVal : '—' ?></td>
            <td><?= $isVendorRow ? (count($assigned) . ' kreator') : '—' ?></td>
          <?php endif; ?>
          <td class="text-right">
            <button type="button"
                    class="btn btn-ghost btn-sm account-edit-btn"
                    data-id="<?= $id ?>"
                    data-type="<?= esc($acc['type'] ?? 'office', 'attr') ?>"
                    data-name="<?= esc($acc['name'] ?? '', 'attr') ?>"
                    data-status="<?= esc($acc['status'] ?? 'active', 'attr') ?>"
                    data-platform="<?= esc($acc['platform'] ?? '', 'attr') ?>"
                    data-owner="<?= esc($acc['owner_name'] ?? '', 'attr') ?>"
                    data-contract="<?= esc($acc['contract_mode'] ?? 'monthly', 'attr') ?>"
                    data-review="<?= esc($acc['next_review_date'] ?? '', 'attr') ?>"
                    data-end="<?= esc($acc['contract_end_date'] ?? '', 'attr') ?>"
                    data-notes="<?= esc($acc['notes'] ?? '', 'attr') ?>">
              Edit
            </button>
            <?php if (($filters['type'] ?? '') === 'vendor' && $isVendorRow): ?>
              <button type="button"
                      class="btn btn-ghost btn-sm vendor-edit-btn"
                      data-id="<?= $id ?>"
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
                Manage
              </button>
            <?php endif; ?>
            <form method="POST" action="/accounts/<?= $id ?>/delete" class="inline-form" data-confirm="Hapus account ini?" data-confirm-title="Hapus account?">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost btn-sm text-danger">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
  </div><!-- /accounts-page -->

<div class="modal-overlay" id="edit-account-modal">
  <div class="modal modal-task" style="max-width: 860px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit Account</h3>
      <button type="button" class="btn-icon" onclick="closeModal('edit-account-modal')">
        <i class="fa-solid fa-xmark u-icon-sm" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body modal-scroll">
      <form method="POST" action="" class="accounts-create" id="accountsEditForm">
        <?= csrf_field() ?>
        <div class="ac-row ac-row-1">
          <input type="text" name="name" id="ae-name" class="form-control" placeholder="Nama account" required>
          <select name="status" id="ae-status" class="form-control" title="Status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="ac-row ac-row-2" id="ae-vendor-fields">
          <input type="text" name="platform" id="ae-platform" class="form-control" placeholder="Platform (vendor)">
          <input type="text" name="owner_name" id="ae-owner" class="form-control" placeholder="Owner (vendor)">
          <select name="contract_mode" id="ae-contract" class="form-control" title="Contract (vendor)">
            <option value="monthly">Monthly</option>
            <option value="lifetime">Lifetime</option>
            <option value="on_demand">On demand</option>
          </select>
          <input type="date" name="next_review_date" id="ae-review" class="form-control" title="Review berikutnya (vendor)">
          <input type="date" name="contract_end_date" id="ae-end" class="form-control" title="Berakhir kontrak (vendor)">
          <input type="text" name="notes" id="ae-notes" class="form-control" placeholder="Notes (opsional)">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('edit-account-modal')">Batal</button>
      <button type="submit" form="accountsEditForm" class="btn btn-primary">Simpan</button>
    </div>
  </div>
</div>

<script>
(function () {
  function toggleVendorFields() {
    const typeSel = document.getElementById('ac-type');
    const vendorWrap = document.getElementById('ac-vendor-fields');
    if (!typeSel || !vendorWrap) return;
    const isVendor = typeSel.value === 'vendor';
    vendorWrap.style.display = isVendor ? '' : 'none';
    vendorWrap.querySelectorAll('input,select,textarea').forEach((el) => {
      el.disabled = !isVendor;
      if (el.name === 'contract_end_date') el.required = false;
    });
  }
  document.getElementById('ac-type')?.addEventListener('change', toggleVendorFields);
  toggleVendorFields();

  function openEditModal(btn) {
    const id = btn?.dataset?.id;
    if (!id) return;
    const form = document.getElementById('accountsEditForm');
    if (!form) return;
    form.action = `/accounts/${id}/update`;

    document.getElementById('ae-name').value = btn.dataset.name || '';
    document.getElementById('ae-status').value = btn.dataset.status || 'active';
    document.getElementById('ae-platform').value = btn.dataset.platform || '';
    document.getElementById('ae-owner').value = btn.dataset.owner || '';
    document.getElementById('ae-contract').value = btn.dataset.contract || 'monthly';
    document.getElementById('ae-review').value = btn.dataset.review || '';
    document.getElementById('ae-end').value = btn.dataset.end || '';
    document.getElementById('ae-notes').value = btn.dataset.notes || '';

    const vendorWrap = document.getElementById('ae-vendor-fields');
    const isVendorRow = (btn.dataset.type || '') === 'vendor';
    if (vendorWrap) {
      vendorWrap.style.display = isVendorRow ? '' : 'none';
      vendorWrap.querySelectorAll('input,select,textarea').forEach((el) => {
        el.disabled = !isVendorRow;
        if (el.name === 'contract_end_date') el.required = false;
      });
    }

    openModal('edit-account-modal');
    setTimeout(() => document.getElementById('ae-name')?.focus(), 30);
  }

  document.querySelectorAll('.account-edit-btn').forEach((btn) => {
    btn.addEventListener('click', () => openEditModal(btn));
  });
})();
</script>

<?php if (($filters['type'] ?? '') === 'vendor'): ?>
  <link rel="stylesheet" href="/assets/css/pages/vendors.css" />
  <?php require __DIR__ . '/../vendors/_vendor_management_modal.php'; ?>
<?php endif; ?>

