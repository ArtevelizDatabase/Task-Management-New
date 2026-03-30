<?php
/**
 * Inline Editor.js for task fields (richtext JSON, textarea plain, text single-line plain).
 * When canMutate is false, read-only display per type.
 *
 * @var int    $taskId
 * @var string $fieldKey
 * @var array  $f field row (value, updated_at, type, …)
 * @var bool   $canMutate
 * @var string $fieldType richtext|textarea|text
 */
$taskId    = (int) ($taskId ?? 0);
$fieldKey  = (string) ($fieldKey ?? '');
$f         = is_array($f ?? null) ? $f : [];
$canMutate = ! empty($canMutate);
$ft        = (string) ($fieldType ?? $f['type'] ?? 'text');
if (! in_array($ft, ['richtext', 'textarea', 'text'], true)) {
    $ft = 'text';
}
$rtVal = (string) ($f['value'] ?? '');
$upd   = (string) ($f['updated_at'] ?? '');
$safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fieldKey) ?: 'f';
$holderId = 'ts-rt-' . $taskId . '-' . $safeKey;

if ($ft === 'richtext') {
    $storageFormat = 'editorjs';
} elseif ($ft === 'textarea') {
    $storageFormat = 'multiline';
} else {
    $storageFormat = 'singleline';
}
?>
<?php if ($canMutate): ?>
  <div class="task-show-richtext-editor-wrap"
       data-can-mutate="1"
       data-task-id="<?= $taskId ?>"
       data-field-key="<?= esc($fieldKey, 'attr') ?>"
       data-field-type="<?= esc($ft, 'attr') ?>"
       data-storage-format="<?= esc($storageFormat, 'attr') ?>"
       data-expected-updated-at="<?= esc($upd, 'attr') ?>"
       data-editor-holder-id="<?= esc($holderId, 'attr') ?>">
    <script type="application/json" class="task-show-richtext-initial"><?= json_encode($rtVal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    <div class="task-show-richtext-holder rt-js-editor-shell" id="<?= esc($holderId, 'attr') ?>"></div>
    <div class="task-show-richtext-actions">
      <button type="button" class="btn btn-primary btn-sm task-show-richtext-save">Simpan</button>
    </div>
  </div>
<?php else: ?>
  <?php if ($ft === 'richtext'): ?>
    <div class="editorjs-readonly"><?= editorjs_to_html($rtVal) ?></div>
  <?php elseif ($ft === 'textarea'): ?>
    <?= nl2br(esc($rtVal)) ?>
  <?php else: ?>
    <?= esc($rtVal) ?>
  <?php endif; ?>
<?php endif; ?>
