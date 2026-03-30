<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;
use App\Models\AttachmentModel;
use App\Models\CommentModel;
use App\Models\FavoriteModel;
use App\Models\RevisionModel;
use App\Models\TaskAssigneeModel;
use App\Models\TaskModel;
use App\Models\TaskRelationModel;
use App\Models\TaskTemplateModel;
use App\Models\VendorAllocationModel;
use CodeIgniter\Controller;

class TaskExtras extends Controller
{
    private CommentModel $commentModel;
    private ActivityLogModel $actLogModel;
    private RevisionModel $revisionModel;
    private AttachmentModel $attachmentModel;
    private FavoriteModel $favoriteModel;
    private TaskTemplateModel $templateModel;
    private TaskRelationModel $relationModel;
    private TaskAssigneeModel $assigneeModel;
    private TaskModel $taskModel;
    private VendorAllocationModel $vendorAllocationModel;

    public function __construct()
    {
        $this->commentModel          = new CommentModel();
        $this->actLogModel           = new ActivityLogModel();
        $this->revisionModel         = new RevisionModel();
        $this->attachmentModel       = new AttachmentModel();
        $this->favoriteModel         = new FavoriteModel();
        $this->templateModel         = new TaskTemplateModel();
        $this->relationModel         = new TaskRelationModel();
        $this->assigneeModel         = new TaskAssigneeModel();
        $this->taskModel             = new TaskModel();
        $this->vendorAllocationModel = new VendorAllocationModel();
    }

    private function uid(): int
    {
        return (int) session()->get('user_id');
    }

    private function role(): string
    {
        return (string) (session()->get('user_role') ?? 'member');
    }

    private function json(array $data, int $code = 200): \CodeIgniter\HTTP\Response
    {
        // Selalu sertakan hash baru setelah filter CSRF (regenerate); jika tidak, AJAX berikutnya gagal.
        if (! array_key_exists('csrf', $data)) {
            $data['csrf'] = csrf_hash();
        }

        return $this->response->setStatusCode($code)->setJSON($data);
    }

    private function jsonForbidden(string $message = 'Forbidden'): \CodeIgniter\HTTP\Response
    {
        return $this->response->setStatusCode(403)->setJSON([
            'success' => false,
            'message' => $message,
            'csrf'    => csrf_hash(),
        ]);
    }

    private function requirePerm(string $perm, bool $asJson = false): void
    {
        $role = $this->role();
        if ($role === 'super_admin') {
            return;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            if ($asJson) {
                $this->jsonForbidden('Akses ditolak.')->send();
            } else {
                redirect()->back()->with('error', 'Akses ditolak.')->send();
            }
            exit;
        }
    }

    private function hasPerm(string $perm): bool
    {
        $role = $this->role();
        if ($role === 'super_admin') {
            return true;
        }

        return in_array($perm, (array) (session()->get('user_perms') ?? []), true);
    }

    private function canMutateTask(int $taskId): bool
    {
        $role   = $this->role();
        $userId = $this->uid();

        if ($role !== 'member') {
            return $userId > 0;
        }
        if ($userId <= 0) {
            return false;
        }

        $task = $this->taskModel->find($taskId);
        if (! $task || ! empty($task['deleted_at'])) {
            return false;
        }
        if ((int) ($task['user_id'] ?? 0) !== $userId) {
            return false;
        }

        $db = \Config\Database::connect();
        if ($db->tableExists('tb_vendor_allocations') && $db->fieldExists('account_id', 'tb_task')) {
            $rows = $this->vendorAllocationModel->where('user_id', $userId)->findAll();
            $allowedVendorIds = array_values(array_filter(array_map(
                static fn(array $r): int => (int) ($r['account_id'] ?? 0),
                $rows
            ), static fn(int $v): bool => $v > 0));
            if ($allowedVendorIds !== []) {
                $taskVendorId = (int) ($task['account_id'] ?? 0);
                if ($taskVendorId > 0 && ! in_array($taskVendorId, $allowedVendorIds, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function canViewTaskInScope(int $taskId): bool
    {
        $uid = $this->uid();
        if ($uid <= 0) {
            return false;
        }
        if ($this->role() !== 'member') {
            return true;
        }

        return $this->canMutateTask($taskId);
    }

    /** @return array{0: list<int>, 1: bool} allowed vendor ids and hasVendorCol */
    private function memberVendorScope(): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_vendor_allocations') || ! $db->fieldExists('account_id', 'tb_task')) {
            return [[], false];
        }
        $uid = $this->uid();
        if ($uid <= 0) {
            return [[], true];
        }
        $rows = $this->vendorAllocationModel->where('user_id', $uid)->findAll();
        $ids  = array_values(array_filter(array_map(
            static fn(array $r): int => (int) ($r['account_id'] ?? 0),
            $rows
        ), static fn(int $v): bool => $v > 0));

        return [$ids, true];
    }

    /**
     * Scope task yang sama seperti daftar task (member = task sendiri + filter vendor jika ada).
     *
     * @param object $builder Query builder on tb_task t
     */
    private function applyGlobalSearchTaskScope($builder, ?int $scopeUserId, bool $hasVendorCol, array $allowedVendorIds): void
    {
        if ($scopeUserId !== null) {
            $builder->where('t.user_id', $scopeUserId);
        }
        if ($hasVendorCol && $allowedVendorIds !== []) {
            $builder->whereIn('t.account_id', $allowedVendorIds);
        }
    }

    private function fetchJudulForTask(\CodeIgniter\Database\BaseConnection $db, int $taskId): string
    {
        $trow = $db->table('tb_task t')
            ->select('t.project_id')
            ->where('t.id', $taskId)
            ->where('t.deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();
        $tp = (int) ($trow['project_id'] ?? 0);

        $rowQ = $db->table('tb_task_values tv')
            ->select('tv.value')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->where('tv.task_id', $taskId)
            ->where('f.field_key', 'judul')
            ->where('f.status', 1);
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if ($tp > 0) {
                $rowQ->where('f.project_id', $tp);
            } else {
                $rowQ->where('f.project_id IS NULL', null, false);
            }
        }
        $row = $rowQ->limit(1)->get()->getRowArray();

        $v = trim((string) ($row['value'] ?? ''));

        return $v !== '' ? $v : ('Task #' . $taskId);
    }

    public function addComment(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }

        $body = trim((string) ($this->request->getPost('body') ?? ''));
        if ($body === '') {
            return $this->json(['error' => 'Komentar tidak boleh kosong.'], 422);
        }

        $id = $this->commentModel->addComment($taskId, $uid, $body);
        $this->actLogModel->logCommented($taskId, $uid);

        return $this->json(['success' => true, 'id' => $id]);
    }

    public function deleteComment(int $taskId, int $commentId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }

        $ok = $this->commentModel->deleteComment($commentId, $uid, $this->role());

        return $this->json($ok ? ['success' => true] : ['error' => 'Tidak bisa menghapus komentar ini.'], $ok ? 200 : 403);
    }

    public function addRevision(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }

        $requestedAt = (string) ($this->request->getPost('requested_at') ?? date('Y-m-d'));
        $dueDate     = $this->request->getPost('due_date') ?: null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedAt) || ! strtotime($requestedAt)) {
            $requestedAt = date('Y-m-d');
        }
        if ($dueDate !== null && (! is_string($dueDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || ! strtotime($dueDate))) {
            $dueDate = null;
        }

        $data = [
            'task_id'      => $taskId,
            'requested_by' => trim((string) ($this->request->getPost('requested_by') ?? '')),
            'description'  => trim((string) ($this->request->getPost('description') ?? '')),
            'requested_at' => $requestedAt,
            'due_date'     => $dueDate,
            'status'       => 'pending',
        ];

        if ($data['requested_by'] === '' || $data['description'] === '') {
            return $this->json(['error' => 'Requester dan deskripsi wajib diisi.'], 422);
        }

        $id = $this->revisionModel->insert($data);
        $this->actLogModel->log($taskId, $uid, 'revision_added', 'Menambahkan catatan revisi');

        return $this->json(['success' => true, 'id' => $id]);
    }

    public function updateRevisionStatus(int $taskId, int $revisionId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $status = $this->request->getPost('status');
        $note   = (string) ($this->request->getPost('note') ?? '');
        if (! in_array($status, ['pending', 'in_progress', 'done', 'rejected'], true)) {
            return $this->json(['error' => 'Status tidak valid.'], 422);
        }

        $rev = $this->revisionModel->find($revisionId);
        if (! $rev || (int) $rev['task_id'] !== $taskId) {
            return $this->json(['error' => 'Revisi tidak ditemukan.'], 404);
        }

        $this->revisionModel->updateStatus($revisionId, $status, $uid, $note);

        return $this->json(['success' => true]);
    }

    public function deleteRevision(int $taskId, int $revisionId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! in_array($this->role(), ['super_admin', 'admin'], true)) {
            return $this->json(['error' => 'Tidak ada akses.'], 403);
        }

        $rev = $this->revisionModel->find($revisionId);
        if (! $rev || (int) $rev['task_id'] !== $taskId) {
            return $this->json(['error' => 'Revisi tidak ditemukan.'], 404);
        }

        $this->revisionModel->delete($revisionId);

        return $this->json(['success' => true]);
    }

    public function uploadAttachment(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $file = $this->request->getFile('file');
        if (! $file) {
            return $this->json(['error' => 'Tidak ada file.'], 422);
        }

        try {
            $att = $this->attachmentModel->uploadForTask($taskId, $uid, $file);
            $this->actLogModel->logAttachmentAdded($taskId, $uid, (string) $att['original']);

            return $this->json([
                'success'  => true,
                'id'       => $att['id'],
                'original' => $att['original'],
                'size'     => AttachmentModel::formatSize((int) $att['size']),
                'url'      => AttachmentModel::publicUrl($taskId, (string) $att['filename']),
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function serveAttachment(int $taskId, string $filename)
    {
        $this->requirePerm('view_tasks');
        $uid = $this->uid();
        if (! $uid) {
            return redirect()->to('/auth/login');
        }
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->response->setStatusCode(403)->setBody('Akses ditolak.');
        }

        $filename = basename($filename);
        if ($filename === '' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return $this->response->setStatusCode(400)->setBody('Nama file tidak valid.');
        }

        $path = WRITEPATH . "uploads/attachments/{$taskId}/{$filename}";
        if (! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('File tidak ditemukan.');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $size = filesize($path) ?: 0;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string) $size)
            ->setHeader('Content-Disposition', 'inline; filename="' . addslashes($filename) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff');
        $this->response->sendHeaders();
        readfile($path);
        exit;
    }

    public function deleteAttachment(int $taskId, int $attachmentId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $ok = $this->attachmentModel->deleteAttachment($attachmentId, $taskId);

        return $this->json($ok ? ['success' => true] : ['error' => 'Attachment tidak ditemukan.'], $ok ? 200 : 404);
    }

    public function addAssignee(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid    = $this->uid();
        $userId = (int) $this->request->getPost('user_id');

        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        if (! $userId) {
            return $this->json(['error' => 'user_id wajib diisi.'], 422);
        }

        $ok = $this->assigneeModel->assign($taskId, $userId, $uid);
        if ($ok) {
            $this->actLogModel->logAssigned($taskId, $uid, $userId);
        }

        return $this->json(['success' => $ok, 'message' => $ok ? 'Berhasil di-assign.' : 'User sudah di-assign.']);
    }

    public function removeAssignee(int $taskId, int $userId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $ok = $this->assigneeModel->unassign($taskId, $userId);

        return $this->json(['success' => $ok]);
    }

    public function addRelation(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid           = $this->uid();
        $relatedTaskId = (int) $this->request->getPost('related_task_id');
        $type          = (string) ($this->request->getPost('relation_type') ?? 'relates_to');

        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        if (! $relatedTaskId) {
            return $this->json(['error' => 'related_task_id wajib diisi.'], 422);
        }
        if (! $this->canViewTaskInScope($relatedTaskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task terkait.');
        }

        $ok = $this->relationModel->addRelation($taskId, $relatedTaskId, $type, $uid);

        return $this->json([
            'success' => $ok,
            'message' => $ok ? 'Relasi ditambahkan.' : 'Relasi sudah ada atau task sama.',
        ]);
    }

    public function deleteRelation(int $taskId, int $relationId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canMutateTask($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $this->relationModel->where('id', $relationId)->where('task_id', $taskId)->delete();

        return $this->json(['success' => true]);
    }

    /**
     * GET tasks/{id}/relation-tasks?q= — autocomplete kandidat relasi (scope sama dengan picker lama).
     */
    public function relationTaskSearch(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if (strlen($q) > 120) {
            $q = substr($q, 0, 120);
        }

        $task = $this->taskModel->find($taskId);
        if (! $task || ! empty($task['deleted_at'])) {
            return $this->json(['data' => []], 404);
        }

        $coreFilters = [];
        $pid = (int) ($task['project_id'] ?? 0);
        if ($pid > 0) {
            $coreFilters['project_id'] = (string) $pid;
        } else {
            $coreFilters['internal_only'] = true;
        }

        $role          = $this->role();
        $scopeUserId   = ($role === 'member') ? $uid : null;
        [$allowedVendorIds] = $this->memberVendorScope();

        $items = $this->taskModel->searchRelationPicker(
            $q,
            $coreFilters,
            $scopeUserId,
            $allowedVendorIds,
            $taskId,
            25
        );

        return $this->json(['data' => $items]);
    }

    public function toggleFavorite(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid        = $this->uid();
        $entityType = $this->request->getPost('entity_type');
        $entityId   = (int) $this->request->getPost('entity_id');

        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }
        if (! in_array($entityType, ['task', 'project', 'client'], true)) {
            return $this->json(['error' => 'entity_type tidak valid.'], 422);
        }

        $isFav = $this->favoriteModel->toggle($uid, $entityType, $entityId);

        return $this->json(['success' => true, 'is_favorited' => $isFav]);
    }

    public function listFavorites(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $favs = $this->favoriteModel->getForUser($uid);

        return $this->json(['data' => $favs]);
    }

    public function listTemplates(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $templates = $this->templateModel->getForUser($uid);

        return $this->json(['data' => $templates]);
    }

    public function storeTemplate(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $name        = trim((string) ($this->request->getPost('name') ?? ''));
        $desc        = trim((string) ($this->request->getPost('description') ?? ''));
        $fieldValues = $this->request->getPost('field_values') ?? [];
        $isPublic    = (bool) $this->request->getPost('is_public');

        if ($name === '') {
            return $this->json(['error' => 'Nama template wajib diisi.'], 422);
        }
        if (! is_array($fieldValues)) {
            $fieldValues = json_decode((string) $fieldValues, true) ?? [];
        }

        $id = $this->templateModel->createFromValues($uid, $name, $desc, $fieldValues, $isPublic);

        return $this->json(['success' => true, 'id' => $id]);
    }

    public function templateFields(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $tpl = $this->templateModel->find($id);
        if (! $tpl) {
            return $this->json(['error' => 'Tidak ditemukan.'], 404);
        }
        if ((int) $tpl['created_by'] !== $uid && empty($tpl['is_public'])
            && ! in_array($this->role(), ['super_admin', 'admin'], true)) {
            return $this->json(['error' => 'Akses ditolak.'], 403);
        }

        $fields = $this->templateModel->getFieldValues($id);

        return $this->json(['data' => $fields]);
    }

    public function deleteTemplate(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $tpl = $this->templateModel->find($id);
        if (! $tpl) {
            return $this->json(['error' => 'Template tidak ditemukan.'], 404);
        }

        if ((int) $tpl['created_by'] !== $uid && ! in_array($this->role(), ['super_admin', 'admin'], true)) {
            return $this->json(['error' => 'Tidak ada akses.'], 403);
        }

        $this->templateModel->delete($id);

        return $this->json(['success' => true]);
    }

    public function search(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $uid = $this->uid();
        if (! $uid) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if (strlen($q) < 2) {
            return $this->json(['data' => []]);
        }

        $like = '%' . $q . '%';
        $db   = \Config\Database::connect();
        $role = $this->role();

        $results = [];

        $scopeUserId = ($role === 'member') ? $uid : null;
        [$allowedVendorIds, $hasVendorCol] = $this->memberVendorScope();

        $seenTaskIds = [];
        $maxTasks    = 10;

        // Angka murni: cari task by ID (setelah cek akses).
        if (ctype_digit($q) && strlen($q) <= 9) {
            $directId = (int) $q;
            if ($directId > 0 && $this->canViewTaskInScope($directId)) {
                $trow = $db->table('tb_task t')
                    ->select('t.id, t.status')
                    ->where('t.id', $directId)
                    ->where('t.deleted_at IS NULL')
                    ->get()->getRowArray();
                if ($trow) {
                    $tid                 = (int) $trow['id'];
                    $seenTaskIds[$tid] = true;
                    $results[]           = [
                        'type'  => 'task',
                        'id'    => $tid,
                        'label' => $this->fetchJudulForTask($db, $tid),
                        'meta'  => $trow['status'],
                        'url'   => base_url('tasks/' . $tid),
                    ];
                }
            }
        }

        // Semua nilai field task (EAV) kecuali boolean — label tetap dari judul jika ada.
        $taskIdBuilder = $db->table('tb_task t')
            ->select('t.id, t.status')
            ->distinct()
            ->join('tb_task_values tv', 'tv.task_id = t.id')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->where('f.status', 1)
            ->groupStart()
                ->where('f.scope', 'task')
                ->orWhere('f.scope', 'both')
            ->groupEnd()
            ->whereNotIn('f.type', ['boolean'])
            ->like('tv.value', $q)
            ->where('t.deleted_at IS NULL');
        if ($db->fieldExists('project_id', 'tb_task') && $db->fieldExists('project_id', 'tb_fields')) {
            $taskIdBuilder->groupStart()
                ->groupStart()
                    ->where('t.project_id IS NULL', null, false)
                    ->where('f.project_id IS NULL', null, false)
                ->groupEnd()
                ->orGroupStart()
                    ->where('t.project_id IS NOT NULL', null, false)
                    ->where('t.project_id = f.project_id', null, false)
                ->groupEnd()
            ->groupEnd();
        }
        $this->applyGlobalSearchTaskScope($taskIdBuilder, $scopeUserId, $hasVendorCol, $allowedVendorIds);
        $taskRows = $taskIdBuilder->orderBy('t.id', 'DESC')->limit(12)->get()->getResultArray();

        foreach ($taskRows as $trow) {
            if (count($seenTaskIds) >= $maxTasks) {
                break;
            }
            $tid = (int) $trow['id'];
            if (isset($seenTaskIds[$tid])) {
                continue;
            }
            $seenTaskIds[$tid] = true;
            $results[]         = [
                'type'  => 'task',
                'id'    => $tid,
                'label' => $this->fetchJudulForTask($db, $tid),
                'meta'  => $trow['status'],
                'url'   => base_url('tasks/' . $tid),
            ];
        }

        if ($this->hasPerm('view_clients') && $db->tableExists('tb_clients')) {
            $clients = $db->table('tb_clients')
                ->select('id, name, contact')
                ->groupStart()
                    ->like('name', $q)
                    ->orLike('contact', $q)
                ->groupEnd()
                ->where('status', 'active')
                ->limit(3)
                ->get()->getResultArray();

            foreach ($clients as $c) {
                $results[] = [
                    'type'  => 'client',
                    'id'    => $c['id'],
                    'label' => $c['name'],
                    'meta'  => $c['contact'] ?? '',
                    'url'   => base_url('clients/' . $c['id']),
                ];
            }
        }

        if ($this->hasPerm('view_projects') && $db->tableExists('tb_projects')) {
            $projects = $db->table('tb_projects p')
                ->select('p.id, p.name, c.name as client_name')
                ->join('tb_clients c', 'c.id = p.client_id', 'left')
                ->like('p.name', $q)
                ->limit(3)
                ->get()->getResultArray();

            foreach ($projects as $p) {
                $results[] = [
                    'type'  => 'project',
                    'id'    => $p['id'],
                    'label' => $p['name'],
                    'meta'  => $p['client_name'] ?? '',
                    'url'   => base_url('projects/' . $p['id']),
                ];
            }
        }

        if ($db->tableExists('tb_comments')) {
            $commentBuilder = $db->table('tb_comments c')
                ->select('c.task_id, SUBSTRING(c.body, 1, 80) as excerpt')
                ->join('tb_task t', 't.id = c.task_id')
                ->like('c.body', $q)
                ->where('t.deleted_at IS NULL');

            if ($scopeUserId !== null) {
                $commentBuilder->where('t.user_id', $scopeUserId);
            }
            if ($hasVendorCol && $allowedVendorIds !== []) {
                $commentBuilder->whereIn('t.account_id', $allowedVendorIds);
            }

            $comments = $commentBuilder->orderBy('c.id', 'DESC')->limit(3)->get()->getResultArray();

            foreach ($comments as $c) {
                $excerpt = strip_tags(substr((string) ($c['excerpt'] ?? ''), 0, 80));
                $results[] = [
                    'type'  => 'comment',
                    'id'    => $c['task_id'],
                    'label' => 'Komentar di Task #' . $c['task_id'],
                    'meta'  => $excerpt,
                    'url'   => base_url('tasks/' . $c['task_id'] . '#comments'),
                ];
            }
        }

        return $this->json(['data' => $results]);
    }
}
