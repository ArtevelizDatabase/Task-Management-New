<?php
/**
 * @var array|null $task     null = create mode
 * @var array      $fields   active fields with options_array
 * @var array      $errors   validation errors keyed by field_key
 */

$isEdit   = !empty($task);
$action   = $isEdit ? "/tasks/{$task['id']}/update" : '/tasks/store';
$oldInput = session()->getFlashdata('old') ?? [];

function oldVal(string $key, ?array $task, array $old): string {
    if (isset($old['fields'][$key])) return esc($old['fields'][$key]);
    if ($task && isset($task['fields'][$key]['value'])) return esc($task['fields'][$key]['value']);
    return '';
}

// Check if this task already has a submission
$hasSubmission = !empty($task['submission']);
?>

<div style="max-width:720px;margin:0 auto">
  <!-- Breadcrumb -->
  <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-3);margin-bottom:20px">
    <a href="/tasks" style="color:var(--text-2)">Tasks</a>
    <i class="fa-solid fa-chevron-right u-icon-xs" aria-hidden="true"></i>
    <span><?= $isEdit ? 'Edit Task #' . str_pad($task['id'], 4, '0', STR_PAD_LEFT) : 'Task Baru' ?></span>
  </div>

  <?php if ($hasSubmission): ?>
    <!-- Submission Status Banner -->
    <div style="display:flex;align-items:center;gap:10px;background:var(--success-soft);border:1px solid #86efac;border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--success)">
      <i class="fa-solid fa-circle-check icon-sm" aria-hidden="true"></i>
      <span>
        Task ini <strong>sudah disetor</strong> ke tb_submissions
        <span style="color:var(--text-3);font-size:11px;margin-left:6px">ID Submission: #<?= $task['submission']['id'] ?></span>
      </span>
      <a href="/tasks/submissions" style="margin-left:auto;font-size:11px;color:var(--success);text-decoration:underline">Lihat Setor →</a>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:24px">
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" style="margin-bottom:20px">
        <div>
          <strong>Perbaiki error berikut:</strong>
          <ul style="margin-top:6px;padding-left:16px">
            <?php foreach ($errors as $err): ?>
              <li><?= esc($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" id="task-form">
      <?= csrf_field() ?>

      <input type="hidden" name="status" value="<?= esc($task['status'] ?? 'pending') ?>">

      <!-- Dynamic Fields -->
      <div class="form-grid">
        <?php foreach ($fields as $f): ?>
          <?php
          $key     = $f['field_key'];
          $val     = oldVal($key, $task, $oldInput);
          $err     = $errors[$key] ?? '';
          $reqStr  = $f['is_required'] ? ' <span class="req">*</span>' : '';
          $ph      = esc($f['placeholder'] ?? '');
          $colSpan = in_array($f['type'], ['textarea','richtext']) ? 'grid-column:1/-1' : '';

          // Show sync hint if field maps to tb_submissions
          $syncHint = '';
          if (!empty($f['submission_col'])) {
            $syncHint = '<span style="font-size:10px;color:var(--success);margin-left:4px;display:inline-flex;align-items:center;gap:3px" title="Sync ke tb_submissions.'. $f['submission_col'] .'"><i data-lucide="refresh-cw" style="width:10px;height:10px"></i> setor</span>';
          }
          ?>

          <div class="form-group" style="<?= $colSpan ?>">
            <label class="form-label">
              <?= esc($f['field_label']) ?><?= $reqStr ?><?= $syncHint ?>
            </label>

            <?php if ($f['type'] === 'text' || $f['type'] === 'email' || $f['type'] === 'number'): ?>
              <input type="<?= $f['type'] ?>"
                     name="fields[<?= $key ?>]"
                     value="<?= $val ?>"
                     placeholder="<?= $ph ?>"
                     class="form-control <?= $err ? 'is-invalid' : '' ?>"
                     <?= $f['is_required'] ? 'required' : '' ?>>

            <?php elseif ($f['type'] === 'date'): ?>
              <input type="date"
                     name="fields[<?= $key ?>]"
                     value="<?= $val ?>"
                     class="form-control <?= $err ? 'is-invalid' : '' ?>"
                     <?= $f['is_required'] ? 'required' : '' ?>>

            <?php elseif ($f['type'] === 'select'): ?>
              <select name="fields[<?= $key ?>]"
                      class="form-control <?= $err ? 'is-invalid' : '' ?>"
                      <?= $f['is_required'] ? 'required' : '' ?>>
                <option value=""><?= $ph ?: 'Pilih ' . esc($f['field_label']) ?></option>
                <?php foreach ($f['options_array'] ?? [] as $opt): ?>
                  <option value="<?= esc($opt) ?>" <?= $val === esc($opt) ? 'selected' : '' ?>>
                    <?= esc($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>

            <?php elseif ($f['type'] === 'textarea'): ?>
              <textarea name="fields[<?= $key ?>]"
                        placeholder="<?= $ph ?>"
                        class="form-control <?= $err ? 'is-invalid' : '' ?>"
                        <?= $f['is_required'] ? 'required' : '' ?>><?= $val ?></textarea>

            <?php elseif ($f['type'] === 'boolean'): ?>
              <?php
                // Boolean 'setor' gets special treatment
                $isSetorField = ($key === 'setor');
              ?>
              <div style="padding-top:4px">
                <?php if ($isSetorField): ?>
                  <label class="form-check" id="setor-label">
                    <input type="hidden" name="fields[<?= $key ?>]" value="0">
                    <input type="checkbox"
                           name="fields[<?= $key ?>]"
                           value="1"
                           id="setor-checkbox"
                           <?= $val === '1' ? 'checked' : '' ?>>
                    <span style="font-weight:500">Setor ke Submission</span>
                    <span id="setor-status-text" style="font-size:11px;color:var(--text-3);margin-left:4px">
                      <?= $val === '1' ? '(data akan disinkronkan ke tb_submissions)' : '(centang untuk menyimpan ke tb_submissions)' ?>
                    </span>
                  </label>
                <?php else: ?>
                  <label class="form-check">
                    <input type="hidden" name="fields[<?= $key ?>]" value="0">
                    <input type="checkbox"
                           name="fields[<?= $key ?>]"
                           value="1"
                           <?= $val === '1' ? 'checked' : '' ?>>
                    <span><?= esc($f['field_label']) ?></span>
                  </label>
                <?php endif; ?>
              </div>

            <?php elseif ($f['type'] === 'richtext'): ?>
              <?php
                $rtId      = 'rt_' . $key;
                $rtPreview = '';
                if ($val) {
                  $parsed = json_decode($val, true);
                  if (isset($parsed['blocks'])) {
                    foreach (array_slice($parsed['blocks'], 0, 2) as $blk) {
                      $rtPreview .= strip_tags($blk['data']['text'] ?? '') . ' ';
                    }
                  }
                }
              ?>
              <input type="hidden" name="fields[<?= $key ?>]" id="<?= $rtId ?>" value="<?= esc($val) ?>">
              <div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;min-height:48px;background:var(--surface);cursor:pointer;font-size:13px;color:var(--text-2)"
                   onclick="openRtEditor('<?= $key ?>', '<?= $rtId ?>')"
                   id="<?= $rtId ?>_preview">
                <?= $rtPreview ? esc(mb_strimwidth($rtPreview, 0, 120, '…')) : '<span style="color:var(--text-3)">Klik untuk buka editor…</span>' ?>
              </div>
            <?php endif; ?>

            <?php if ($err): ?>
              <div class="invalid-feedback"><?= esc($err) ?></div>
            <?php endif; ?>

            <?php if (!empty($f['help_text'])): ?>
              <div style="font-size:11px;color:var(--text-3);margin-top:3px"><?= esc($f['help_text']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Submission Preview (when setor is checked) -->
      <div id="setor-preview" style="display:none;margin-top:4px;padding:12px 14px;background:var(--success-soft);border:1px solid #86efac;border-radius:var(--radius);font-size:12px;color:var(--success)">
        <div style="font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:6px">
          <i class="fa-solid fa-check icon-xs" aria-hidden="true"></i>
          Data berikut akan disimpan ke tb_submissions:
        </div>
        <div id="setor-preview-content" style="color:var(--text-2);line-height:1.8"></div>
      </div>

      <!-- Form actions -->
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="/tasks" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary" id="submit-btn">
          <i class="fa-solid fa-check icon-xs" aria-hidden="true"></i>
          <?= $isEdit ? 'Update Task' : 'Simpan Task' ?>
        </button>
      </div>
    </form>
  </div>
</div>


<script>
// ── Setor checkbox helper ───────────────────────────────────
const setorCb   = document.getElementById('setor-checkbox');
const setorTxt  = document.getElementById('setor-status-text');
const setorPrev = document.getElementById('setor-preview');

// Sync fields that map to submission columns
const syncMappings = <?= json_encode(
    array_reduce($fields, function($carry, $f) {
        if (!empty($f['submission_col'])) {
            $carry[$f['field_key']] = $f['submission_col'];
        }
        return $carry;
    }, [])
) ?>;

function updateSetorPreview(isChecked) {
  if (setorTxt) {
    setorTxt.textContent = isChecked
      ? '(data akan disinkronkan ke tb_submissions)'
      : '(centang untuk menyimpan ke tb_submissions)';
  }
  if (!setorPrev) return;
  setorPrev.style.display = isChecked ? 'block' : 'none';

  if (isChecked) {
    let lines = [];
    for (const [fieldKey, subCol] of Object.entries(syncMappings)) {
      const input = document.querySelector(`[name="fields[${fieldKey}]"]:not([type=hidden])`);
      const val   = input ? input.value : '—';
      if (val && val !== '') {
        lines.push(`<span style="font-family:var(--mono);color:var(--text-3)">${subCol}</span> = <strong>${val}</strong>`);
      }
    }
    document.getElementById('setor-preview-content').innerHTML = lines.length
      ? lines.join('<br>')
      : '<em style="color:var(--text-3)">Isi field yang ditandai <i data-lucide="refresh-cw" style="width:11px;height:11px;vertical-align:-2px"></i> setor untuk melihat preview.</em>';
    if (typeof refreshLucide === 'function') refreshLucide();
  }
}

if (setorCb) {
  setorCb.addEventListener('change', function() {
    updateSetorPreview(this.checked);
  });
  // Update preview whenever a mapped field changes
  for (const fieldKey of Object.keys(syncMappings)) {
    const input = document.querySelector(`[name="fields[${fieldKey}]"]:not([type=hidden])`);
    if (input) input.addEventListener('input', () => updateSetorPreview(setorCb.checked));
    if (input) input.addEventListener('change', () => updateSetorPreview(setorCb.checked));
  }
  // Init
  updateSetorPreview(setorCb.checked);
}

// ── Submit feedback ─────────────────────────────────────────
document.getElementById('task-form').addEventListener('submit', function() {
  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner icon-xs" style="animation:spin 1s linear infinite" aria-hidden="true"></i> Menyimpan…';
});
</script>

