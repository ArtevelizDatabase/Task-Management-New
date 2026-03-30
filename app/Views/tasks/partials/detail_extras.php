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
 */
$taskId      = (int) ($task['id'] ?? 0);
$currentUid  = session()->get('user_id');
$currentRole = session()->get('user_role') ?? 'member';
$canMutate   = ! empty($canMutate);
?>

<link rel="stylesheet" href="/assets/css/pages/task-detail-extras.css" />

<div class="task-detail-extras-root" data-task-id="<?= $taskId ?>">
<div style="margin-top:1rem; display:flex; align-items:center; gap:.75rem;">
    <button type="button" class="btn-fav <?= $isFavorited ? 'active' : '' ?>" id="btnFav" onclick="toggleFav(<?= $taskId ?>)">
        <?= $isFavorited ? '★ Favorit' : '☆ Tambah ke Favorit' ?>
    </button>
    <span style="font-size:.78rem; color:var(--text-muted);">Task #<?= $taskId ?></span>
</div>

<div class="extras-tabs" id="extras-tabs-bar">
    <div class="extras-tab active" data-tab="comments">Komentar <span id="badge-comments" style="font-size:.7rem; background:var(--accent); color:#fff; border-radius:99px; padding:.05rem .45rem; margin-left:.25rem;"><?= count($comments) ?></span></div>
    <div class="extras-tab" data-tab="revisions">Revisi
        <?php
        $pendingRev = array_filter($revisions, static fn($r) => in_array($r['status'], ['pending', 'in_progress'], true));
        ?>
        <?php if ($pendingRev): ?>
            <span style="font-size:.7rem; background:#f59e0b; color:#fff; border-radius:99px; padding:.05rem .45rem; margin-left:.25rem;"><?= count($pendingRev) ?></span>
        <?php endif; ?>
    </div>
    <div class="extras-tab" data-tab="attachments">Lampiran (<?= count($attachments) ?>)</div>
    <div class="extras-tab" data-tab="assignees">Assignee (<?= count($assignees) ?>)</div>
    <div class="extras-tab" data-tab="relations">Relasi</div>
    <div class="extras-tab" data-tab="activity">Aktivitas</div>
</div>

<div class="extras-panel active" id="panel-comments">
    <div id="comments-list">
        <?php foreach ($comments as $c): ?>
        <div class="comment-item" id="comment-<?= (int) $c['id'] ?>">
            <div class="comment-avatar"><?= strtoupper(substr((string) ($c['nickname'] ?? $c['username'] ?? 'U'), 0, 1)) ?></div>
            <div class="comment-bubble">
                <div class="comment-meta">
                    <strong><?= esc($c['nickname'] ?? $c['username']) ?></strong>
                    · <?= date('d M Y H:i', strtotime((string) $c['created_at'])) ?>
                    <?php if ($currentRole !== 'member' || (int) $c['user_id'] === (int) $currentUid): ?>
                    · <a href="#" onclick="deleteComment(<?= (int) $c['id'] ?>, <?= $taskId ?>); return false;" style="color:#dc2626; font-size:.72rem;">Hapus</a>
                    <?php endif; ?>
                </div>
                <div class="comment-body"><?= nl2br(esc($c['body'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?>
        <p style="color:var(--text-muted); font-size:.85rem;">Belum ada komentar.</p>
        <?php endif; ?>
    </div>

    <div style="margin-top:1rem;">
        <textarea id="commentBody" placeholder="Tulis komentar... gunakan @username untuk mention" rows="3"
            style="width:100%; padding:.6rem; border:1px solid var(--border); border-radius:6px; font-size:.875rem; resize:vertical;"></textarea>
        <button type="button" onclick="submitComment(<?= $taskId ?>)" style="margin-top:.5rem; padding:.45rem 1rem; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem;">
            Kirim Komentar
        </button>
    </div>
</div>

<div class="extras-panel" id="panel-revisions">
    <div id="revisions-list">
        <?php foreach ($revisions as $r): ?>
        <div class="revision-card">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem;">
                <div>
                    <strong><?= esc($r['requested_by']) ?></strong>
                    <span class="revision-badge badge-<?= esc($r['status']) ?>"><?= esc($r['status']) ?></span>
                </div>
                <div style="font-size:.75rem; color:var(--text-muted);">
                    <?= esc((string) $r['requested_at']) ?>
                    <?= ! empty($r['due_date']) ? ' · Due: ' . esc((string) $r['due_date']) : '' ?>
                </div>
            </div>
            <p style="font-size:.875rem; margin:0 0 .5rem;"><?= nl2br(esc($r['description'])) ?></p>
            <?php if (! empty($r['handler_note'])): ?>
            <p style="font-size:.8rem; color:var(--text-muted); font-style:italic;">Catatan: <?= esc($r['handler_note']) ?></p>
            <?php endif; ?>
            <?php if ($canMutate && $r['status'] !== 'done' && $r['status'] !== 'rejected'): ?>
            <div style="margin-top:.5rem; display:flex; gap:.4rem; flex-wrap:wrap;">
                <?php foreach (['in_progress', 'done', 'rejected'] as $s): ?>
                <button type="button" onclick="updateRevStatus(<?= $taskId ?>, <?= (int) $r['id'] ?>, '<?= esc($s, 'attr') ?>')"
                    style="padding:.25rem .7rem; font-size:.75rem; border:1px solid var(--border); border-radius:4px; cursor:pointer; background:var(--surface);">
                    → <?= esc(ucfirst(str_replace('_', ' ', $s))) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($revisions)): ?>
        <p style="color:var(--text-muted); font-size:.85rem;">Belum ada revisi.</p>
        <?php endif; ?>
    </div>

    <details style="margin-top:1rem;">
        <summary style="cursor:pointer; font-size:.85rem; font-weight:500;">+ Tambah Revisi</summary>
        <div style="margin-top:.75rem; display:grid; gap:.5rem;">
            <input type="text" id="rev_by" placeholder="Nama klien / yang meminta" style="padding:.5rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
            <textarea id="rev_desc" placeholder="Deskripsi revisi" rows="3" style="padding:.5rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem; resize:vertical;"></textarea>
            <div style="display:flex; gap:.5rem;">
                <input type="date" id="rev_date" style="flex:1; padding:.5rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;" value="<?= date('Y-m-d') ?>">
                <input type="date" id="rev_due" style="flex:1; padding:.5rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
            </div>
            <button type="button" onclick="submitRevision(<?= $taskId ?>)" style="padding:.45rem 1rem; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem;">
                Simpan Revisi
            </button>
        </div>
    </details>
</div>

<div class="extras-panel" id="panel-attachments">
    <div id="attachments-list">
        <?php foreach ($attachments as $a): ?>
        <div class="attachment-item" id="att-<?= (int) $a['id'] ?>">
            <span class="attachment-icon" aria-hidden="true">📎</span>
            <a href="<?= base_url('tasks/' . $taskId . '/attachments/' . rawurlencode((string) $a['filename']) . '/serve') ?>" target="_blank" rel="noopener noreferrer" class="attachment-name" title="<?= esc($a['original']) ?>"><?= esc($a['original']) ?></a>
            <span class="attachment-size"><?= esc(\App\Models\AttachmentModel::formatSize((int) $a['size'])) ?></span>
            <span style="font-size:.75rem; color:var(--text-muted);"><?= esc($a['username'] ?? '') ?></span>
            <?php if ($canMutate): ?>
            <button type="button" onclick="deleteAttachment(<?= $taskId ?>, <?= (int) $a['id'] ?>)" style="background:none; border:none; cursor:pointer; color:#dc2626; font-size:.8rem;">✕</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($attachments)): ?>
        <p style="color:var(--text-muted); font-size:.85rem;">Belum ada lampiran.</p>
        <?php endif; ?>
    </div>

    <?php if ($canMutate): ?>
    <div style="margin-top:1rem;">
        <input type="file" id="attachFile" multiple style="font-size:.85rem;">
        <button type="button" onclick="uploadAttachment(<?= $taskId ?>)" style="margin-top:.5rem; padding:.45rem 1rem; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem;">
            Upload File
        </button>
        <p style="font-size:.75rem; color:var(--text-muted); margin-top:.3rem;">Maks <?= (int) \App\Models\AttachmentModel::MAX_SIZE_MB ?>MB per file.</p>
    </div>
    <?php endif; ?>
</div>

<div class="extras-panel" id="panel-assignees">
    <div id="assignees-list" style="margin-bottom:1rem;">
        <?php foreach ($assignees as $a): ?>
        <span class="assignee-chip" id="chip-<?= (int) $a['user_id'] ?>">
            <?= esc($a['nickname'] ?? $a['username']) ?>
            <?php if ($canMutate): ?>
            <button type="button" onclick="removeAssignee(<?= $taskId ?>, <?= (int) $a['user_id'] ?>)" style="background:none; border:none; cursor:pointer; font-size:.8rem; color:#dc2626; padding:0; line-height:1;">✕</button>
            <?php endif; ?>
        </span>
        <?php endforeach; ?>
        <?php if (empty($assignees)): ?>
        <p style="color:var(--text-muted); font-size:.85rem;">Belum ada assignee tambahan.</p>
        <?php endif; ?>
    </div>

    <?php if ($canMutate): ?>
    <div style="display:flex; gap:.5rem;">
        <select id="assigneeSelect" style="flex:1; padding:.45rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
            <option value="">— Pilih user —</option>
            <?php foreach ($allUsers as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= esc($u['nickname'] ?? $u['username']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" onclick="addAssignee(<?= $taskId ?>)" style="padding:.45rem 1rem; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem;">Tambah</button>
    </div>
    <?php endif; ?>
</div>

<div class="extras-panel" id="panel-relations">
    <div id="relations-list" style="margin-bottom:1rem;">
        <?php foreach ($relations as $r): ?>
        <div style="display:flex; align-items:center; gap:.6rem; padding:.4rem 0; border-bottom:1px solid var(--border-light, var(--border)); font-size:.85rem;" id="rel-<?= (int) $r['id'] ?>">
            <span style="font-size:.72rem; background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:.1rem .5rem;"><?= esc($r['relation_type']) ?></span>
            <a href="<?= base_url('tasks/' . (int) $r['related_task_id']) ?>"><?= esc($r['related_task_title'] ?? ('Task #' . $r['related_task_id'])) ?></a>
            <span style="color:var(--text-muted); font-size:.78rem;">[<?= esc($r['related_task_status'] ?? '') ?>]</span>
            <?php if ($canMutate): ?>
            <button type="button" onclick="deleteRelation(<?= $taskId ?>, <?= (int) $r['id'] ?>)" style="margin-left:auto; background:none; border:none; cursor:pointer; color:#dc2626; font-size:.8rem;">✕</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($relations)): ?>
        <p style="color:var(--text-muted); font-size:.85rem;">Belum ada relasi task.</p>
        <?php endif; ?>
    </div>

    <?php if ($canMutate): ?>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start;">
        <select id="relationType" style="padding:.45rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
            <option value="relates_to">relates_to</option>
            <option value="blocks">blocks</option>
            <option value="blocked_by">blocked_by</option>
            <option value="duplicate_of">duplicate_of</option>
        </select>
        <div class="relation-task-ac-wrap" style="flex:1; min-width:12rem; position:relative;">
            <input type="hidden" id="relatedTaskId" value="">
            <input type="search" id="relatedTaskSearch" placeholder="Cari task (ID atau judul)…" autocomplete="off"
            style="width:100%; padding:.45rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem; box-sizing:border-box;"
            aria-autocomplete="list" aria-controls="relatedTaskAcList" aria-expanded="false">
            <div id="relatedTaskAcList" class="relation-task-ac-list" role="listbox" hidden></div>
        </div>
        <button type="button" onclick="addRelation(<?= $taskId ?>)" style="padding:.45rem 1rem; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem;">Tambah</button>
    </div>
    <?php endif; ?>
</div>

<div class="extras-panel" id="panel-activity">
    <?php foreach ($activityLog as $al): ?>
    <?php
    $act = (string) ($al['action'] ?? '');
    $icon = match ($act) {
        'created'           => '✨',
        'status_changed'    => '🔄',
        'field_updated'     => '✏️',
        'commented'         => '💬',
        'attachment_added'  => '📎',
        'assigned'          => '👤',
        'revision_added'    => '📝',
        'updated'           => '✓',
        default             => '•',
    };
    ?>
    <div class="activity-item">
        <div class="activity-icon"><?= $icon ?></div>
        <div class="activity-desc">
            <strong><?= esc($al['nickname'] ?? $al['username'] ?? ('User#' . $al['user_id'])) ?></strong>
            — <?= esc($al['description'] ?? '') ?>
        </div>
        <div class="activity-time"><?= date('d M, H:i', strtotime((string) ($al['created_at'] ?? 'now'))) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($activityLog)): ?>
    <p style="color:var(--text-muted); font-size:.85rem;">Belum ada aktivitas.</p>
    <?php endif; ?>
</div>
</div>
