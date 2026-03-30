<?php
/**
 * @var array $task
 * @var array $comments
 * @var array $activityLog
 * @var array $revisions
 * @var array $attachments
 * @var array $relations
 * @var array $assignees
 * @var array $allUsers
 * @var bool  $isFavorited
 * @var bool  $canMutate
 * @var int|null $taskDetailProjectId When set, back link targets project work list
 */
$task        = $task ?? [];
$projectMeta = $projectMeta ?? null;
$comments    = $comments ?? [];
$activityLog = $activityLog ?? [];
$revisions   = $revisions ?? [];
$attachments = $attachments ?? [];
$relations   = $relations ?? [];
$assignees   = $assignees ?? [];
$allUsers    = $allUsers ?? [];
$isFavorited = $isFavorited ?? false;
$canMutate   = $canMutate ?? false;
$taskDetailProjectId = $taskDetailProjectId ?? null;

$status = $task['status'] ?? 'pending';
$judul  = $task['fields']['judul']['value'] ?? ('Task #' . ($task['id'] ?? ''));
?>

<link rel="stylesheet" href="/assets/css/pages/task-show.css" />

<div class="page-header">
  <div class="page-header-left">
    <a href="<?= $taskDetailProjectId ? '/projects/' . (int) $taskDetailProjectId : '/tasks' ?>" class="page-back-link"><?= $taskDetailProjectId ? '← Kembali ke task proyek' : '← Kembali ke daftar task' ?></a>
    <h2 class="page-title"><?= esc($judul) ?></h2>
    <p class="page-sub">Task #<?= (int) ($task['id'] ?? 0) ?> · Status: <strong><?= esc($status) ?></strong>
      <?php if (!empty($task['deadline'])): ?> · Deadline: <?= esc(substr((string) $task['deadline'], 0, 10)) ?><?php endif; ?>
    </p>
  </div>
</div>

<div class="task-show-card card-surface">
  <p class="task-show-hint" role="note">
    <strong>Bedanya apa?</strong>
    <strong class="task-show-hint-term">Proyek &amp; klien</strong> (di bawah ini, kalau ada) datang dari menu
    <em>Projects / Clients</em> — satu task bisa ditautkan ke satu proyek, dan proyek itu punya klien.
    Kolom di tabel seperti <strong class="task-show-hint-term">Account</strong>, <strong>Theme</strong>, dll. adalah
    <em>field custom</em> dari Field Manager; nilai <code>account:7</code> artinya referensi ke akun internal (vendor/marketplace),
    <strong>bukan</strong> otomatis sama dengan klien bisnis di Proyek.
  </p>

  <div class="task-show-meta">
    <?php if (!empty($task['progress'])): ?>
      <span>Progress: <?= (int) $task['progress'] ?>%</span>
    <?php endif; ?>
    <?php if (!empty($task['project_id'])): ?>
      <?php if (is_array($projectMeta) && ($projectMeta['name'] ?? '') !== ''): ?>
        <span class="task-show-project">
          Proyek:
          <a href="<?= base_url('projects/' . (int) $projectMeta['id']) ?>"><?= esc((string) $projectMeta['name']) ?></a>
          <?php if (!empty($projectMeta['client_name'])): ?>
            <span class="task-show-client"> · Klien: <?= esc((string) $projectMeta['client_name']) ?></span>
            <?php if (!empty($projectMeta['client_id'])): ?>
              <a href="<?= base_url('clients/' . (int) $projectMeta['client_id']) ?>" class="task-show-client-link">(detail)</a>
            <?php endif; ?>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span>Proyek #<?= (int) $task['project_id'] ?> <span style="color:var(--text-muted); font-size:.85em;">(nama tidak ditemukan)</span></span>
      <?php endif; ?>
    <?php else: ?>
      <span class="task-show-no-project">Belum ada proyek — tautkan lewat edit task (modal) jika task ini bagian dari proyek klien.</span>
    <?php endif; ?>
  </div>

  <h3 class="task-show-fields-heading">Field task</h3>
  <p class="task-show-fields-sub">Sesuai urutan &amp; pengaturan di <a href="<?= base_url('fields') ?>">Field Manager</a>.</p>

  <dl class="task-show-fields">
    <?php foreach ($task['fields'] ?? [] as $key => $f): ?>
      <div class="task-show-field-row">
        <dt><?= esc($f['label'] ?? $key) ?></dt>
        <dd class="task-show-field-value task-show-type-<?= esc($f['type'] ?? 'text', 'attr') ?>">
          <?php
          $ft = (string) ($f['type'] ?? 'text');
          if (in_array($ft, ['richtext', 'textarea', 'text'], true)):
              echo view('tasks/partials/richtext_field_value', [
                  'taskId'    => (int) ($task['id'] ?? 0),
                  'fieldKey'  => $key,
                  'f'         => $f,
                  'canMutate' => $canMutate,
                  'fieldType' => $ft,
              ]);
          else:
              echo esc((string) ($f['value'] ?? ''));
          endif;
          ?>
        </dd>
      </div>
    <?php endforeach; ?>
  </dl>
</div>

<?= view('tasks/partials/detail_extras', [
    'task'        => $task,
    'comments'    => $comments,
    'activityLog' => $activityLog,
    'revisions'   => $revisions,
    'attachments' => $attachments,
    'relations'   => $relations,
    'assignees'   => $assignees,
    'allUsers'    => $allUsers,
    'isFavorited' => $isFavorited,
    'canMutate'   => $canMutate,
]) ?>

<script>
window.__taskExtras = {
  baseUrl: <?= json_encode(rtrim(base_url(), '/') . '/') ?>,
  csrfHeader: <?= json_encode(config(\Config\Security::class)->headerName) ?>,
};
</script>
<script src="/assets/js/task-show-richtext.js" defer></script>
<script src="/assets/js/task-detail-extras.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof initTaskShowRichtextEditors === 'function') {
    initTaskShowRichtextEditors(document.body);
  }
});
</script>
