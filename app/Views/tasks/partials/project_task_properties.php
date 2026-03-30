<?php
/**
 * Metadata work item untuk panel drawer proyek (bergaya “Properties”).
 *
 * @var array      $task
 * @var array|null $projectMeta
 * @var array      $assignees
 * @var array      $relations
 * @var array      $attachments
 */
$task        = $task ?? [];
$projectMeta = $projectMeta ?? null;
$assignees   = $assignees ?? [];
$relations   = $relations ?? [];
$attachments = $attachments ?? [];

$tid = (int) ($task['id'] ?? 0);
$pid = (int) ($task['project_id'] ?? 0);

$status     = (string) ($task['status'] ?? 'pending');
$statusLbls = [
    'pending'     => 'Menunggu',
    'on_progress' => 'Berjalan',
    'done'        => 'Selesai',
    'cancelled'   => 'Dibatalkan',
];
$statusLabel = $statusLbls[$status] ?? $status;

$prioVal = (string) ($task['fields']['priority']['value'] ?? '');

$deadline = $task['deadline'] ?? null;
$created  = $task['created_at'] ?? null;
$updated  = $task['updated_at'] ?? null;

$fmtTs = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }
    $t = strtotime((string) $v);

    return $t ? date('d M Y H:i', $t) : '';
};

$judul = $task['fields']['judul']['value'] ?? ('Work item #' . $tid);

$assigneeNames = array_values(array_filter(array_map(
    static fn (array $a): string => (string) ($a['nickname'] ?? $a['username'] ?? ''),
    $assignees
), static fn (string $s): bool => $s !== ''));

$parentId = 0;
if (! empty($task['parent_id'])) {
    $parentId = (int) $task['parent_id'];
}

$permalinkProject = $pid > 0 && $tid > 0
    ? base_url('projects/' . $pid . '?task=' . $tid)
    : base_url('tasks/' . $tid);
$permalinkTask = base_url('tasks/' . $tid);
?>
<section class="project-task-panel-properties" aria-label="Informasi work item">
  <h3 class="project-task-panel-properties-title">Informasi</h3>
  <dl class="project-task-panel-properties-dl">
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Work item</dt>
      <dd class="project-task-prop-value">
        <span class="project-task-prop-strong"><?= esc((string) $judul) ?></span>
      </dd>
    </div>
    <?php if (is_array($projectMeta) && ($projectMeta['name'] ?? '') !== ''): ?>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Proyek</dt>
      <dd class="project-task-prop-value">
        <a href="<?= esc(base_url('projects/' . (int) ($projectMeta['id'] ?? $pid))) ?>" class="project-task-prop-link"><?= esc((string) $projectMeta['name']) ?></a>
        <?php if (! empty($projectMeta['client_name'])): ?>
          <span class="project-task-prop-muted"> · <?= esc((string) $projectMeta['client_name']) ?></span>
        <?php endif; ?>
      </dd>
    </div>
    <?php endif; ?>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">State</dt>
      <dd class="project-task-prop-value"><span class="project-task-prop-status project-task-prop-status--<?= esc(preg_replace('/[^a-z0-9_-]/i', '', $status), 'attr') ?>"><?= esc($statusLabel) ?></span></dd>
    </div>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Priority</dt>
      <dd class="project-task-prop-value">
        <?php if ($prioVal !== ''): ?>
          <?= esc($prioVal) ?>
        <?php else: ?>
          <span class="project-task-prop-placeholder">—</span>
        <?php endif; ?>
      </dd>
    </div>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Assignees</dt>
      <dd class="project-task-prop-value">
        <?php if ($assigneeNames !== []): ?>
          <?= esc(implode(', ', $assigneeNames)) ?>
        <?php else: ?>
          <span class="project-task-prop-placeholder">Belum ada assignee</span>
        <?php endif; ?>
      </dd>
    </div>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Deadline</dt>
      <dd class="project-task-prop-value">
        <?php if (! empty($deadline)): ?>
          <?= esc(substr((string) $deadline, 0, 10)) ?>
        <?php else: ?>
          <span class="project-task-prop-placeholder">Belum diatur</span>
        <?php endif; ?>
      </dd>
    </div>
    <?php if ($parentId > 0 && $pid > 0): ?>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Induk</dt>
      <dd class="project-task-prop-value">
        <a href="<?= esc(base_url('projects/' . $pid . '?task=' . $parentId)) ?>" class="project-task-prop-link">Work item #<?= $parentId ?></a>
      </dd>
    </div>
    <?php endif; ?>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Dibuat</dt>
      <dd class="project-task-prop-value"><?= $created ? esc($fmtTs($created)) : '—' ?></dd>
    </div>
    <div class="project-task-prop-row">
      <dt class="project-task-prop-label">Diperbarui</dt>
      <dd class="project-task-prop-value"><?= $updated ? esc($fmtTs($updated)) : '—' ?></dd>
    </div>
    <div class="project-task-prop-row project-task-prop-row--links">
      <dt class="project-task-prop-label">Tautan</dt>
      <dd class="project-task-prop-value">
        <ul class="project-task-prop-linklist">
          <li><a href="<?= esc($permalinkProject) ?>" class="project-task-prop-link">Panel work item (URL ini)</a></li>
          <li><a href="<?= esc($permalinkTask) ?>" class="project-task-prop-link" target="_blank" rel="noopener noreferrer">Halaman task penuh</a></li>
          <?php foreach ($relations as $r): ?>
            <?php
            $rid = (int) ($r['related_task_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $rt = (string) ($r['related_task_title'] ?? ('Task #' . $rid));
            $rtype = (string) ($r['relation_type'] ?? '');
            ?>
            <li>
              <span class="project-task-prop-rel-type"><?= esc($rtype) ?></span>
              <a href="<?= esc(base_url('tasks/' . $rid)) ?>" class="project-task-prop-link" target="_blank" rel="noopener noreferrer"><?= esc($rt) ?></a>
            </li>
          <?php endforeach; ?>
          <?php foreach ($attachments as $a): ?>
            <li>
              <a href="<?= base_url('tasks/' . $tid . '/attachments/' . rawurlencode((string) $a['filename']) . '/serve') ?>" class="project-task-prop-link" target="_blank" rel="noopener noreferrer"><?= esc($a['original'] ?? 'Lampiran') ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </dd>
    </div>
  </dl>
</section>
