<?php
/**
 * HTML fragment for project work-item drawer (no main layout).
 *
 * Editor utama = satu dokumen deskripsi (satu field utama), bukan satu heading per field.
 *
 * @var array      $task
 * @var array|null $projectMeta
 * @var bool       $panelMode
 */
$task        = $task ?? [];
$projectMeta = $projectMeta ?? null;
$judul  = $task['fields']['judul']['value'] ?? ('Task #' . ($task['id'] ?? ''));

$editableTypes  = ['text', 'textarea', 'richtext'];
$fieldsEditable = [];
$fieldsOther    = [];
foreach ($task['fields'] ?? [] as $key => $f) {
    $t = (string) ($f['type'] ?? 'text');
    if (in_array($t, $editableTypes, true)) {
        $fieldsEditable[$key] = $f;
    } else {
        $fieldsOther[$key] = $f;
    }
}

// Priority di blok Informasi
$fieldsOtherDisplay = array_filter(
    $fieldsOther,
    static fn (string $k): bool => $k !== 'priority',
    ARRAY_FILTER_USE_KEY
);

/** Urutan field seperti Field Manager */
$fieldKeysOrder = array_keys($task['fields'] ?? []);

/** Satu field utama untuk Editor.js (deskripsi work item) — sama dengan FieldModel::PRIMARY_BODY_FIELD_KEYS */
$primaryKey = null;
$preferred    = \App\Models\FieldModel::PRIMARY_BODY_FIELD_KEYS;
foreach ($preferred as $cand) {
    if (isset($fieldsEditable[$cand])) {
        $primaryKey = $cand;
        break;
    }
}
if ($primaryKey === null) {
    foreach ($fieldKeysOrder as $k) {
        if (! isset($fieldsEditable[$k])) {
            continue;
        }
        if (($fieldsEditable[$k]['type'] ?? '') === 'richtext') {
            $primaryKey = $k;
            break;
        }
    }
}
if ($primaryKey === null) {
    foreach ($fieldKeysOrder as $k) {
        if (isset($fieldsEditable[$k])) {
            $primaryKey = $k;
            break;
        }
    }
}

$primaryField = null;
if ($primaryKey !== null && isset($fieldsEditable[$primaryKey])) {
    $pf = $fieldsEditable[$primaryKey];
    $primaryField = [
        'key'        => $primaryKey,
        'label'      => $pf['label'] ?? $primaryKey,
        'type'       => $pf['type'] ?? 'text',
        'value'      => (string) ($pf['value'] ?? ''),
        'updated_at' => $pf['updated_at'] ?? null,
    ];
}

/** Field teks lain: input ringkas (bukan blok heading di Editor) */
$secondaryEditable = [];
foreach ($fieldsEditable as $k => $f) {
    if ($k === $primaryKey) {
        continue;
    }
    $secondaryEditable[$k] = $f;
}

$tid = (int) ($task['id'] ?? 0);

$hasAnyTextField = $fieldsEditable !== [];
$hasPrimary      = $primaryField !== null;

$panelEditorConfig = [
    'taskId'  => $tid,
    'primary' => $primaryField,
];
?>

<div class="project-task-panel-fragment" data-panel-task-id="<?= $tid ?>">
  <div class="project-task-panel-head">
    <h2 class="project-task-panel-title"><?= esc($judul) ?></h2>
    <p class="project-task-panel-meta">
      Task #<?= $tid ?>
      <?php if (! empty($task['deadline'])): ?>
        · Deadline: <?= esc(substr((string) $task['deadline'], 0, 10)) ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="project-task-panel-fields">
    <?php if (! empty($canMutate)): ?>
      <?php if ($hasAnyTextField): ?>
        <div class="project-task-fields-unified-root"
             data-can-mutate="1"
             data-task-id="<?= $tid ?>">
          <script type="application/json" class="project-task-fields-unified-config"><?= json_encode(
              $panelEditorConfig,
              JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
          ) ?></script>
          <?php if ($hasPrimary): ?>
            <p class="project-task-desc-label"><?= esc($primaryField['label']) ?></p>
          <?php else: ?>
            <p class="project-task-desc-label">Deskripsi</p>
          <?php endif; ?>
          <div id="ptfu-holder-<?= $tid ?>" class="project-task-fields-unified-holder rt-js-editor-shell"></div>

          <?php if ($secondaryEditable !== []): ?>
            <div class="project-task-secondary-fields">
              <p class="project-task-secondary-title">Field pendukung</p>
              <?php foreach ($secondaryEditable as $key => $f): ?>
                <?php
                $ft = (string) ($f['type'] ?? 'text');
                $ev = (string) ($f['updated_at'] ?? '');
                ?>
                <div class="project-task-secondary-row" data-field-key="<?= esc($key, 'attr') ?>">
                  <label class="project-task-secondary-label" for="pts-<?= $tid ?>-<?= esc($key, 'attr') ?>"><?= esc($f['label'] ?? $key) ?></label>
                  <?php if ($ft === 'textarea'): ?>
                    <textarea id="pts-<?= $tid ?>-<?= esc($key, 'attr') ?>"
                              class="project-task-secondary-input form-control"
                              rows="3"
                              data-project-task-secondary-input="1"
                              data-field-key="<?= esc($key, 'attr') ?>"
                              data-expected-updated-at="<?= esc($ev, 'attr') ?>"><?= esc((string) ($f['value'] ?? '')) ?></textarea>
                  <?php elseif ($ft === 'richtext'): ?>
                    <div class="project-task-secondary-richtext-note">
                      Isi utama work item memakai field <code>deskripsi</code> (atau alias <code>description</code> / <code>body</code> / <code>keterangan</code>). Kolom rich text lain sunting dari tabel work items.
                    </div>
                    <div class="editorjs-readonly project-task-secondary-richtext-preview"><?= editorjs_to_html((string) ($f['value'] ?? '')) ?></div>
                  <?php else: ?>
                    <input type="text"
                           id="pts-<?= $tid ?>-<?= esc($key, 'attr') ?>"
                           class="project-task-secondary-input form-control"
                           value="<?= esc((string) ($f['value'] ?? '')) ?>"
                           data-project-task-secondary-input="1"
                           data-field-key="<?= esc($key, 'attr') ?>"
                           data-expected-updated-at="<?= esc($ev, 'attr') ?>" />
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="project-task-fields-unified-actions">
            <button type="button" class="btn btn-primary btn-sm project-task-fields-unified-save">Simpan</button>
          </div>
        </div>
      <?php else: ?>
        <div class="project-task-fields-unified-root"
             data-can-mutate="1"
             data-task-id="<?= $tid ?>"
             data-fields-empty="1">
          <script type="application/json" class="project-task-fields-unified-config"><?= json_encode([
              'taskId'  => $tid,
              'primary' => null,
          ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
          <p class="project-task-desc-label">Deskripsi</p>
          <div id="ptfu-holder-<?= $tid ?>" class="project-task-fields-unified-holder rt-js-editor-shell"></div>
          <p class="project-task-fields-unified-empty-hint" role="note">
            Belum ada field teks di proyek ini. Buka halaman project work items atau
            <a href="<?= base_url('fields?project_id=' . (int) ($task['project_id'] ?? 0)) ?>">Field Manager</a>
            — field <code>deskripsi</code> (rich text) dibuat otomatis untuk project baru.
          </p>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <?php if ($hasAnyTextField): ?>
        <div class="project-task-fields-readonly project-task-fields-readonly--single-desc">
          <?php if ($hasPrimary): ?>
            <p class="project-task-desc-label"><?= esc($primaryField['label']) ?></p>
            <div class="project-task-readonly-body task-show-type-<?= esc($primaryField['type'] ?? 'text', 'attr') ?>">
              <?php if (($primaryField['type'] ?? '') === 'richtext'): ?>
                <div class="editorjs-readonly"><?= editorjs_to_html((string) ($primaryField['value'] ?? '')) ?></div>
              <?php elseif (($primaryField['type'] ?? '') === 'textarea'): ?>
                <?= nl2br(esc((string) ($primaryField['value'] ?? ''))) ?>
              <?php else: ?>
                <?= esc((string) ($primaryField['value'] ?? '')) ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php foreach ($secondaryEditable as $key => $f): ?>
            <?php $ft = (string) ($f['type'] ?? 'text'); ?>
            <div class="project-task-fields-readonly-row">
              <div class="project-task-fields-readonly-label"><?= esc($f['label'] ?? $key) ?></div>
              <div class="project-task-fields-readonly-value task-show-type-<?= esc($ft, 'attr') ?>">
                <?php if ($ft === 'richtext'): ?>
                  <div class="editorjs-readonly"><?= editorjs_to_html((string) ($f['value'] ?? '')) ?></div>
                <?php elseif ($ft === 'textarea'): ?>
                  <?= nl2br(esc((string) ($f['value'] ?? ''))) ?>
                <?php else: ?>
                  <?= esc((string) ($f['value'] ?? '')) ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="project-task-fields-unified-holder project-task-fields-unified-holder--readonly-empty rt-js-editor-shell" aria-hidden="true">
          <p class="project-task-fields-readonly-empty-msg">Tidak ada field teks untuk ditampilkan.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($fieldsOtherDisplay !== []): ?>
      <h4 class="project-task-fields-other-heading">Field lain</h4>
      <dl class="task-show-fields project-task-fields-other-dl">
        <?php foreach ($fieldsOtherDisplay as $key => $f): ?>
          <div class="task-show-field-row">
            <dt><?= esc($f['label'] ?? $key) ?></dt>
            <dd class="task-show-field-value task-show-type-<?= esc($f['type'] ?? 'text', 'attr') ?>">
              <?= esc((string) ($f['value'] ?? '')) ?>
            </dd>
          </div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>

  <?= view('tasks/partials/project_task_properties', [
      'task'        => $task,
      'projectMeta' => $projectMeta,
      'assignees'   => $assignees ?? [],
      'relations'   => $relations ?? [],
      'attachments' => $attachments ?? [],
  ]) ?>

  <?= view('tasks/partials/detail_extras', [
      'task'        => $task,
      'comments'    => $comments ?? [],
      'activityLog' => $activityLog ?? [],
      'revisions'   => $revisions ?? [],
      'attachments' => $attachments ?? [],
      'relations'   => $relations ?? [],
      'assignees'   => $assignees ?? [],
      'allUsers'    => $allUsers ?? [],
      'isFavorited' => $isFavorited ?? false,
      'canMutate'   => $canMutate ?? false,
  ]) ?>
</div>
