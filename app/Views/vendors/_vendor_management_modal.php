<?php
/** @var array $users */
/** @var array $teams */
?>

<div id="vendorEditModal" class="modal-overlay modal-z-900">
  <div class="modal vendor-modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Vendor</h3>
      <button type="button" class="btn-icon btn-icon-md" id="vendorEditClose"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="modal-body modal-scroll">
      <div class="vendor-modal-grid">
        <section class="vendor-modal-section">
          <div class="vendor-panel-title">Info Akun</div>
          <form method="POST" id="vendorInfoForm" class="vendor-panel-form">
            <?= csrf_field() ?>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-name">Nama Vendor</label>
              <input type="text" name="name" id="vm-name" class="form-control" placeholder="Contoh: Stylin" required>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-platform">Platform</label>
              <input type="text" name="platform" id="vm-platform" class="form-control" placeholder="Contoh: Canva / Envato">
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-owner">PIC / Owner</label>
              <input type="text" name="owner_name" id="vm-owner" class="form-control" placeholder="Nama owner akun vendor">
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-contract">Model Kontrak</label>
              <select name="contract_mode" id="vm-contract" class="form-control">
                <option value="monthly">Monthly (evaluasi berkala)</option>
                <option value="lifetime">Lifetime (tanpa tanggal berakhir)</option>
                <option value="on_demand">On demand (sesuai kebutuhan)</option>
              </select>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-review">Tanggal Review</label>
              <input type="date" name="next_review_date" id="vm-review" class="form-control">
              <div class="vendor-field-help">Dipakai untuk pengingat evaluasi akun vendor.</div>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-end">Tanggal Berakhir Kontrak</label>
              <input type="date" name="contract_end_date" id="vm-end" class="form-control">
              <div class="vendor-field-help">Akan nonaktif otomatis jika mode kontrak Lifetime.</div>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-status">Status Akun</label>
              <select name="status" id="vm-status" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Simpan Info</button>
          </form>
        </section>

        <section class="vendor-modal-section">
          <div class="vendor-panel-title">Target & Assignment</div>
          <form method="POST" id="vendorTargetForm" class="vendor-panel-form vendor-target-form">
            <?= csrf_field() ?>
            <input type="hidden" name="period_type" value="monthly">
            <input type="hidden" name="period_start" value="<?= esc(date('Y-m-01')) ?>">
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-target">Target Bulanan</label>
              <input type="number" min="0" name="target_value" id="vm-target" class="form-control" placeholder="Contoh: 150">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Set Target</button>
          </form>

          <form method="POST" id="vendorAllocationForm" class="vendor-panel-form vendor-allocation-form">
            <?= csrf_field() ?>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-assigned">Daftar Kreator Dialokasikan</label>
              <select multiple name="user_ids[]" id="vm-assigned" class="form-control vendor-multiselect">
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int) $u['id'] ?>"><?= esc($u['nickname'] ?: $u['username']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="vendor-field-help">Gunakan Ctrl/Cmd + klik untuk pilih lebih dari satu kreator.</div>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-primary">Kreator Primary</label>
              <select name="primary_user_id" id="vm-primary" class="form-control">
                <option value="">Pilih kreator primary</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int) $u['id'] ?>"><?= esc($u['nickname'] ?: $u['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Update Alokasi</button>
          </form>

          <form method="POST" id="vendorRuleForm" class="vendor-panel-form vendor-rule-form">
            <?= csrf_field() ?>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-rule-user">Default User</label>
              <select name="default_user_id" id="vm-rule-user" class="form-control">
                <option value="">Pilih default user</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int) $u['id'] ?>"><?= esc($u['nickname'] ?: $u['username']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="vendor-field-help">Task baru vendor ini otomatis assign ke user ini (jika rule aktif).</div>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-rule-team">Default Team</label>
              <select name="default_team_id" id="vm-rule-team" class="form-control">
                <option value="">Pilih default team</option>
                <?php foreach ($teams as $tm): ?>
                  <option value="<?= (int) $tm['id'] ?>"><?= esc($tm['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-rule-priority">Prioritas Rule</label>
              <input type="number" min="1" name="priority" id="vm-rule-priority" class="form-control" placeholder="Semakin kecil = semakin prioritas">
            </div>
            <div class="vendor-field">
              <label class="vendor-field-label" for="vm-rule-status">Status Rule</label>
              <select name="status" id="vm-rule-status" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Simpan Rule</button>
          </form>
        </section>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('vendorEditModal');
  const closeBtn = document.getElementById('vendorEditClose');
  const infoForm = document.getElementById('vendorInfoForm');
  const targetForm = document.getElementById('vendorTargetForm');
  const allocForm = document.getElementById('vendorAllocationForm');
  const ruleForm = document.getElementById('vendorRuleForm');
  if (!modal || !infoForm || !targetForm || !allocForm || !ruleForm) return;

  const vm = {
    name: document.getElementById('vm-name'),
    platform: document.getElementById('vm-platform'),
    owner: document.getElementById('vm-owner'),
    status: document.getElementById('vm-status'),
    contract: document.getElementById('vm-contract'),
    review: document.getElementById('vm-review'),
    end: document.getElementById('vm-end'),
    target: document.getElementById('vm-target'),
    assigned: document.getElementById('vm-assigned'),
    primary: document.getElementById('vm-primary'),
    ruleUser: document.getElementById('vm-rule-user'),
    ruleTeam: document.getElementById('vm-rule-team'),
    rulePriority: document.getElementById('vm-rule-priority'),
    ruleStatus: document.getElementById('vm-rule-status'),
  };

  function setAction(id) {
    infoForm.action = `/vendors/${id}/update`;
    targetForm.action = `/vendors/${id}/target`;
    allocForm.action = `/vendors/${id}/allocation`;
    ruleForm.action = `/vendors/${id}/rule`;
  }

  function setMultiSelectValues(el, values) {
    if (!el) return;
    const selected = new Set(values.map(String));
    [...el.options].forEach((opt) => { opt.selected = selected.has(String(opt.value)); });
  }

  function syncEndDateByMode(modeEl, endEl) {
    if (!modeEl || !endEl) return;
    const isLifetime = modeEl.value === 'lifetime';
    endEl.disabled = isLifetime;
    endEl.required = !isLifetime;
    if (isLifetime) endEl.value = '';
  }

  function openModalWithData(btn) {
    const id = btn.dataset.id;
    if (!id) return;
    setAction(id);
    vm.name.value = btn.dataset.name || '';
    vm.platform.value = btn.dataset.platform || '';
    vm.owner.value = btn.dataset.owner || '';
    vm.status.value = btn.dataset.status || 'active';
    vm.contract.value = btn.dataset.contract || 'monthly';
    vm.review.value = btn.dataset.review || '';
    vm.end.value = btn.dataset.end || '';
    vm.target.value = btn.dataset.target || '0';
    vm.primary.value = btn.dataset.primaryUser || '';
    vm.ruleUser.value = btn.dataset.defaultUser || '';
    vm.ruleTeam.value = btn.dataset.defaultTeam || '';
    vm.rulePriority.value = btn.dataset.priority || '100';
    vm.ruleStatus.value = btn.dataset.ruleStatus || 'active';
    try {
      const assigned = JSON.parse(btn.dataset.assigned || '[]');
      setMultiSelectValues(vm.assigned, assigned);
    } catch (_) {
      setMultiSelectValues(vm.assigned, []);
    }
    syncEndDateByMode(vm.contract, vm.end);
    modal.classList.add('open');
  }

  document.querySelectorAll('.vendor-edit-btn').forEach((btn) => {
    btn.addEventListener('click', () => openModalWithData(btn));
  });
  closeBtn?.addEventListener('click', () => { modal.classList.remove('open'); });
  modal.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('open');
  });
  vm.contract?.addEventListener('change', () => syncEndDateByMode(vm.contract, vm.end));
})();
</script>

