<?php

namespace App\Controllers;

use App\Libraries\RichtextSanitizer;
use App\Models\ActivityLogModel;
use App\Models\CommentModel;
use App\Models\FavoriteModel;
use App\Models\ProjectModel;
use App\Models\RevisionModel;
use App\Models\AttachmentModel;
use App\Models\TaskAssigneeModel;
use App\Models\TaskRelationModel;
use App\Models\TaskModel;
use App\Models\FieldModel;
use App\Models\AccountModel;
use App\Models\VendorAllocationModel;
use App\Models\AssignmentRuleModel;
use App\Models\UserModel;
use App\Models\UploadStatusModel;
use CodeIgniter\Controller;

class Tasks extends Controller
{
    private const BULK_CREATE_MAX_LINES = 250;

    protected TaskModel  $taskModel;
    protected FieldModel $fieldModel;
    protected AccountModel $accountModel;
    protected VendorAllocationModel $vendorAllocationModel;
    protected AssignmentRuleModel $assignmentRuleModel;
    protected UserModel $userModel;
    protected UploadStatusModel $uploadStatusModel;

    protected $helpers = ['table_pagination'];

    public function __construct()
    {
        $this->taskModel  = new TaskModel();
        $this->fieldModel = new FieldModel();
        $this->accountModel = new AccountModel();
        $this->vendorAllocationModel = new VendorAllocationModel();
        $this->assignmentRuleModel = new AssignmentRuleModel();
        $this->userModel = new UserModel();
        $this->uploadStatusModel = new UploadStatusModel();
    }

    /**
     * Batasi task member ke vendor yang di-assign (jika ada alokasi).
     *
     * @param array<string, mixed> $task
     */
    private function memberVendorAllowsTask(array $task, int $userId): bool
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_vendor_allocations') || ! $db->fieldExists('account_id', 'tb_task')) {
            return true;
        }
        $rows = $this->vendorAllocationModel->where('user_id', $userId)->findAll();
        $allowedVendorIds = array_values(array_filter(array_map(
            static fn(array $r): int => (int) ($r['account_id'] ?? 0),
            $rows
        ), static fn(int $v): bool => $v > 0));
        if ($allowedVendorIds === []) {
            return true;
        }
        $taskVendorId = (int) ($task['account_id'] ?? 0);

        return $taskVendorId <= 0 || in_array($taskVendorId, $allowedVendorIds, true);
    }

    /**
     * @param array<string, mixed> $task
     */
    private function memberMayActOnTask(int $taskId, array $task, int $userId): bool
    {
        $perms = (array) (session()->get('user_perms') ?? []);
        if (in_array('manage_tasks', $perms, true)) {
            return $this->memberVendorAllowsTask($task, $userId);
        }
        if ((int) ($task['user_id'] ?? 0) === $userId) {
            return $this->memberVendorAllowsTask($task, $userId);
        }
        $db = \Config\Database::connect();
        if ($db->tableExists('tb_task_assignees')) {
            $n = (int) $db->table('tb_task_assignees')
                ->where('task_id', $taskId)
                ->where('user_id', $userId)
                ->countAllResults();
            if ($n > 0) {
                return $this->memberVendorAllowsTask($task, $userId);
            }
        }

        return false;
    }

    private function canMutateTask(int $taskId): bool
    {
        $role   = session()->get('user_role') ?? 'member';
        $userId = (int) (session()->get('user_id') ?? 0);

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

        return $this->memberMayActOnTask($taskId, $task, $userId);
    }

    /**
     * Member: task sendiri, atau punya manage_tasks, atau terdaftar sebagai assignee (selaras field-update).
     * Non-member: semua task dalam scope app.
     */
    private function canViewTaskInScope(int $taskId): bool
    {
        $uid = (int) (session()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role !== 'member') {
            return true;
        }

        $task = $this->taskModel->find($taskId);
        if (! $task || ! empty($task['deleted_at'])) {
            return false;
        }

        return $this->memberMayActOnTask($taskId, $task, $uid);
    }

    /**
     * Audit trail: admin/manager mengubah task milik user lain (bukan super_admin, bukan member).
     */
    private function auditTaskMutationIfNotOwner(int $taskId, string $action): void
    {
        $uid = (int) (session()->get('user_id') ?? 0);
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($uid <= 0 || $role === 'member' || $role === 'super_admin') {
            return;
        }
        $task = $this->taskModel->find($taskId);
        if (! $task || ! empty($task['deleted_at'])) {
            return;
        }
        $owner = (int) ($task['user_id'] ?? 0);
        if ($owner === $uid) {
            return;
        }
        $this->userModel->logActivity(
            $uid,
            'task_mutate_other',
            'action=' . $action . ' task_id=' . $taskId . ' owner_user_id=' . $owner,
            'task',
            $taskId
        );
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
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        $perms = session()->get('user_perms') ?? [];
        if (!in_array($perm, (array) $perms, true)) {
            if ($asJson) {
                $this->jsonForbidden('Akses ditolak.')->send();
            } else {
                redirect()->back()->with('error', 'Akses ditolak.')->send();
            }
            exit;
        }
    }

    /**
     * Admin/manager: salah satu dari daftar permission (member selalu lolos di requirePerm).
     */
    private function requirePermOneOf(array $perms, bool $asJson = false): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        $userPerms = session()->get('user_perms') ?? [];
        foreach ($perms as $p) {
            if (in_array($p, (array) $userPerms, true)) {
                return;
            }
        }
        if ($asJson) {
            $this->jsonForbidden('Akses ditolak.')->send();
        } else {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
        }
        exit;
    }

    // ------------------------------------------------------------------
    // INDEX - Task List
    // ------------------------------------------------------------------
    public function index(): string
    {
        $this->requirePerm('view_tasks');
        $db = \Config\Database::connect();
        $hasVendorAccounts = $db->tableExists('tb_accounts');
        $hasVendorAllocs   = $db->tableExists('tb_vendor_allocations');
        $filters        = $this->request->getGet() ?? [];
        $statusFilter   = $filters['status']          ?? '';
        $setorFilter    = $filters['setor']            ?? '';
        $progressFilter = $filters['progress_filter'] ?? '';
        $deadlineFilter = $filters['deadline_filter'] ?? '';
        $vendorFilter   = (string) ($filters['vendor_account_id'] ?? '');
        unset($filters['status'], $filters['setor'], $filters['page'],
              $filters['progress_filter'], $filters['deadline_filter'],
              $filters['vendor_account_id']);

        // Member role can only see their own tasks
        $currentRole   = session()->get('user_role') ?? 'member';
        $currentUserId = (int)(session()->get('user_id') ?? 0);
        $scopeUserId   = ($currentRole === 'member') ? $currentUserId : null;
        $allowedVendorIds = [];
        if ($currentRole === 'member' && $hasVendorAllocs) {
            $rows = $this->vendorAllocationModel->where('user_id', $currentUserId)->findAll();
            $allowedVendorIds = array_values(array_map(static fn(array $r): int => (int) ($r['account_id'] ?? 0), $rows));
            $allowedVendorIds = array_values(array_filter($allowedVendorIds, static fn(int $v): bool => $v > 0));
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        unset($filters['project_id']);

        $coreFilters = [
            'status'              => (string) $statusFilter,
            'setor'               => (string) $setorFilter,
            'progress_filter'   => (string) $progressFilter,
            'deadline_filter'   => (string) $deadlineFilter,
            'vendor_account_id' => (string) $vendorFilter,
            'project_id'        => '',
            'internal_only'     => true,
        ];

        $result = $this->taskModel->getTasksIndexPage($filters, $coreFilters, $page, $perPage, $scopeUserId, $allowedVendorIds);

        if ($this->fieldModel->hasProjectScopeColumn()) {
            $this->fieldModel->ensureJudulFieldInternalGlobal();
        }
        $fields  = $this->fieldModel->getActiveFields();
        $fields  = array_map([$this->fieldModel, 'decodeOptions'], $fields);
        foreach ($fields as &$field) {
            $fieldKey = (string) ($field['field_key'] ?? '');
            if ($fieldKey !== 'account') {
                continue;
            }
            if (($field['data_source'] ?? 'manual') === 'manual') {
                $field['data_source'] = 'account_sources';
                $field['source_config_array'] = [
                    'include_office' => true,
                    'include_vendor' => true,
                ];
            }
        }
        unset($field);
        $userSourceOptionsByField = [];
        $accountSourceLabelMap = [];
        foreach ($fields as $field) {
            $key = (string) ($field['field_key'] ?? '');
            $isPicField = in_array($key, ['pic_name', 'pic'], true);
            $dataSource = (string) ($field['data_source'] ?? 'manual');
            if ($dataSource === 'team_users' || $isPicField) {
                $userSourceOptionsByField[$key] = $this->buildUserSourceOptions($field);
            } elseif ($dataSource === 'account_sources') {
                $userSourceOptionsByField[$key] = $this->buildAccountSourceOptions($field);
                foreach ($userSourceOptionsByField[$key] as $opt) {
                    $optValue = (string) ($opt['value'] ?? '');
                    if ($optValue === '') {
                        continue;
                    }
                    $accountSourceLabelMap[$optValue] = (string) ($opt['label'] ?? $optValue);
                }
            }
        }

        $counts  = $this->taskModel->countByStatus($scopeUserId, true);
        $setor   = $this->taskModel->countSetor();

        $countMap = ['pending' => 0, 'on_progress' => 0, 'done' => 0, 'cancelled' => 0];
        foreach ($counts as $c) {
            $countMap[$c['status']] = (int) $c['total'];
        }

        // Read feature toggle settings
        $settRows = $db->table('tb_app_settings')->get()->getResultArray();
        $settings = [];
        foreach ($settRows as $r) {
            $settings[$r['setting_key']] = (bool) $r['setting_value'];
        }

        $bulkFields = array_values(array_filter($fields, static function (array $f): bool {
            $key = $f['field_key'] ?? '';

            return $key !== 'setor'
                && in_array($f['type'] ?? '', ['text', 'email', 'number', 'select'], true);
        }));

        return view('layouts/main', [
            'title'   => 'Task internal',
            'content' => view('tasks/index', [
                'tasks'               => $result['items'],
                'fields'              => $fields,
                'bulkFields'          => $bulkFields,
                'countMap'            => $countMap,
                'countSetor'          => $setor,
                'filters'             => $filters,
                'statusFilter'        => $statusFilter,
                'setorFilter'         => $setorFilter,
                'vendorFilter'        => $vendorFilter,
                'projectFilter'       => '',
                'projectsForFilter'   => [],
                'vendorAccounts'      => $hasVendorAccounts ? $this->accountModel->getActiveByType('vendor') : [],
                'showProgress'        => $settings['feature_progress'] ?? true,
                'showDeadline'        => $settings['feature_deadline'] ?? true,
                'filteredTaskCount'   => $result['total'],
                'statsAvgProgress'    => $result['statsAvgProgress'],
                'statsOverdue'        => $result['statsOverdue'],
                'pager'               => [
                    'total'      => $result['total'],
                    'page'       => $result['page'],
                    'perPage'    => $result['perPage'],
                    'totalPages' => $result['totalPages'],
                ],
                'pagerQuery'          => table_pagination_query_params($this->request),
                'pagerUriPath'        => table_pagination_uri_path(),
                'userSourceOptionsByField' => $userSourceOptionsByField,
                'accountSourceLabelMap' => $accountSourceLabelMap,
            ]),
        ]);
    }

    /**
     * Bulk: hapus (soft), ubah status, atau ubah satu field EAV (account, artboard, theme, dll.)
     */
    public function bulkTasks(): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('manage_tasks');
        $ids = $this->request->getPost('task_ids') ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

        $action = (string) ($this->request->getPost('bulk_action') ?? '');
        if ($ids === []) {
            return redirect()->back()->with('error', 'Pilih minimal 1 task.');
        }
        if (!in_array($action, ['delete', 'status', 'field'], true)) {
            return redirect()->back()->with('error', 'Aksi bulk tidak valid.');
        }

        $db        = \Config\Database::connect();
        $allowedIds = [];
        foreach ($ids as $id) {
            if (! $this->canMutateTask($id)) {
                continue;
            }
            if ($db->fieldExists('project_id', 'tb_task')) {
                $row = $this->taskModel->find($id);
                if ($row && ! empty($row['project_id']) && (int) $row['project_id'] > 0) {
                    continue;
                }
            }
            $allowedIds[] = $id;
        }

        if ($allowedIds === []) {
            return redirect()->back()->with('error', 'Tidak ada task internal yang dapat diproses (bulk hanya untuk /tasks, bukan work item proyek).');
        }

        $n = count($allowedIds);

        if ($action === 'delete') {
            foreach ($allowedIds as $id) {
                $this->auditTaskMutationIfNotOwner($id, 'bulk_soft_delete');
                $this->taskModel->delete($id);
            }

            return redirect()->back()->with('success', "{$n} task dipindahkan ke Trash.");
        }

        if ($action === 'status') {
            $status = (string) ($this->request->getPost('bulk_status') ?? '');
            $okList = ['pending', 'on_progress', 'done', 'cancelled'];
            if (!in_array($status, $okList, true)) {
                return redirect()->back()->with('error', 'Status tidak valid.');
            }
            foreach ($allowedIds as $id) {
                $this->auditTaskMutationIfNotOwner($id, 'bulk_status');
                $this->taskModel->update($id, ['status' => $status]);
            }

            return redirect()->back()->with('success', "Status diperbarui untuk {$n} task.");
        }

        $fieldKey = trim((string) ($this->request->getPost('bulk_field_key') ?? ''));
        $value    = (string) ($this->request->getPost('bulk_field_value') ?? '');

        if ($fieldKey === '' || $fieldKey === 'setor') {
            return redirect()->back()->with('error', 'Pilih field yang akan diubah.');
        }

        $fieldRow = $this->fieldModel->resolveFieldRowForTask(0, $fieldKey);
        if (! $fieldRow || ! in_array($fieldRow['type'], ['text', 'email', 'number', 'select'], true)) {
            return redirect()->back()->with('error', 'Field tidak diizinkan untuk bulk.');
        }

        if ($fieldRow['type'] === 'select') {
            $opts = $fieldRow['options'] ? json_decode($fieldRow['options'], true) : [];
            $opts = is_array($opts) ? $opts : [];
            if ($value !== '' && $opts !== [] && !in_array($value, $opts, true)) {
                return redirect()->back()->with('error', 'Nilai field tidak valid untuk opsi select.');
            }
        }

        foreach ($allowedIds as $id) {
            $this->auditTaskMutationIfNotOwner($id, 'bulk_field');
            $this->taskModel->setTaskFieldValue($id, $fieldKey, $value);
        }

        return redirect()->back()->with('success', "Field «{$fieldRow['field_label']}» diperbarui untuk {$n} task.");
    }

    // ------------------------------------------------------------------
    // CREATE (deprecated — now handled by modal in index.php)
    // ------------------------------------------------------------------
    public function create(): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to('/tasks');
    }

    // ------------------------------------------------------------------
    // STORE
    // ------------------------------------------------------------------
    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('view_tasks');
        $db = \Config\Database::connect();
        $hasVendorAccounts = $db->tableExists('tb_accounts');
        $hasAssignmentRule = $db->tableExists('tb_assignment_rules');
        $post        = $this->request->getPost();
        $status      = $post['status'] ?? 'pending';
        $fieldValues = $post['fields'] ?? [];
        $quickDraft  = (string) ($post['quick_draft'] ?? '') === '1';
        $vendorAccountId = ($hasVendorAccounts && !empty($post['vendor_account_id'])) ? (int) $post['vendor_account_id'] : null;

        $formContext = (string) ($post['form_context'] ?? 'internal');
        $projectId   = null;
        if ($formContext === 'project' && $db->fieldExists('project_id', 'tb_task') && $db->tableExists('tb_projects')) {
            $pid = (int) ($post['project_id'] ?? 0);
            if ($pid > 0 && (int) $db->table('tb_projects')->where('id', $pid)->countAllResults() > 0) {
                $projectId = $pid;
            }
        }

        $parentId = isset($post['parent_id']) && $post['parent_id'] !== '' ? (int) $post['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        if ($projectId !== null) {
            $activeFields = $this->fieldModel->getActiveFieldsForProject((int) $projectId);
        } else {
            $activeFields = $this->fieldModel->getActiveFields();
        }
        $activeFields = array_map([$this->fieldModel, 'decodeOptions'], $activeFields);
        $errors = [];

        if (! $quickDraft) {
            foreach ($activeFields as $f) {
                if ($f['type'] === 'boolean') {
                    continue;
                }

                if ($f['is_required'] && empty($fieldValues[$f['field_key']])) {
                    $errors[$f['field_key']] = $f['field_label'] . ' wajib diisi.';
                }
            }
        }

        if (! empty($errors)) {
            return redirect()->back()
                ->with('errors', $errors)
                ->with('old', $post)
                ->withInput();
        }
        $userSourceErrors = $this->validateUserSourceFieldValues($activeFields, $fieldValues);
        if (! empty($userSourceErrors)) {
            return redirect()->back()
                ->with('errors', $userSourceErrors)
                ->with('old', $post)
                ->withInput();
        }

        $sessionUserId   = (int) (session()->get('user_id') ?? 1);
        $assignedUserId  = $sessionUserId;
        $resolvedUserId  = $hasAssignmentRule ? $this->assignmentRuleModel->resolveDefaultUserId($vendorAccountId) : null;
        if ($resolvedUserId !== null) {
            $assignedUserId = $resolvedUserId;
        }

        $taskId = $this->taskModel->createTaskWithFields(
            $assignedUserId,
            $status,
            $fieldValues,
            $vendorAccountId,
            $projectId,
            $parentId
        );

        if (! $taskId) {
            return redirect()->back()->with('error', 'Gagal menyimpan task. Silakan coba lagi.');
        }

        if ($db->tableExists('tb_activity_log')) {
            $actor = (int) (session()->get('user_id') ?? 0);
            if ($actor > 0) {
                (new ActivityLogModel())->logCreated((int) $taskId, $actor);
            }
        }

        if ($projectId !== null) {
            return redirect()->to('/projects/' . (int) $projectId)->with('success', 'Task proyek berhasil ditambahkan.');
        }

        return redirect()->to('/tasks')->with('success', 'Task berhasil ditambahkan!');
    }

    // ------------------------------------------------------------------
    // SHOW  (detail modal via AJAX atau direct page)
    // ------------------------------------------------------------------

    /**
     * Task proyek: getTaskWithFields hanya mengembalikan field yang sudah punya baris di tb_task_values.
     * Untuk panel/detail, gabungkan definisi field aktif dari Field Manager agar UI (termasuk Editor.js) tetap muncul
     * dengan nilai kosong sampai user menyimpan pertama kali.
     *
     * @param array<string, mixed> $task From getTaskWithFields
     *
     * @return array<string, mixed>
     */
    protected function mergeTaskFieldsWithProjectDefinitions(array $task): array
    {
        $pid = (int) ($task['project_id'] ?? 0);
        if ($pid <= 0 || ! $this->fieldModel->hasProjectScopeColumn()) {
            return $task;
        }

        $defs = $this->fieldModel->getActiveFieldsForProject($pid);
        if ($defs === []) {
            return $task;
        }

        $fields     = $task['fields'] ?? [];
        $orderKeys  = [];

        foreach ($defs as $idx => $row) {
            $key = (string) ($row['field_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $scope = (string) ($row['scope'] ?? 'task');
            if (! in_array($scope, ['task', 'both'], true)) {
                continue;
            }

            $orderKeys[$key] = (int) ($row['order_no'] ?? $idx);
            if (isset($fields[$key])) {
                continue;
            }

            $opts = [];
            if (! empty($row['options'])) {
                $dec = json_decode((string) $row['options'], true);
                $opts = is_array($dec) ? $dec : [];
            }

            $fields[$key] = [
                'label'          => $row['field_label'] ?? $key,
                'type'           => $row['type'] ?? 'text',
                'options'        => $opts,
                'value'          => '',
                'submission_col' => $row['submission_col'] ?? null,
                'updated_at'     => null,
            ];
        }

        uksort($fields, static function ($a, $b) use ($orderKeys) {
            $oa = $orderKeys[$a] ?? 50000;
            $ob = $orderKeys[$b] ?? 50000;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcmp($a, $b);
        });

        $task['fields'] = $fields;

        return $task;
    }

    /**
     * @param array<string, mixed> $task From getTaskWithFields
     * @param int|null             $relationProjectFilter Reserved (picker memakai GET tasks/{id}/relation-tasks dari task.project_id)
     * @param int|null             $taskDetailProjectId   If set, task/show back link goes to /projects/{id}
     *
     * @return array<string, mixed>
     */
    protected function buildTaskShowViewData(array $task, int $id, ?int $relationProjectFilter, ?int $taskDetailProjectId): array
    {
        $projectIdForFields = (int) ($task['project_id'] ?? 0);
        if ($projectIdForFields > 0) {
            $this->fieldModel->ensureDefaultProjectTaskFields($projectIdForFields);
        }
        $task = $this->mergeTaskFieldsWithProjectDefinitions($task);
        $db  = \Config\Database::connect();
        $uid = (int) (session()->get('user_id') ?? 0);

        $comments    = [];
        $activityLog = [];
        $revisions   = [];
        $attachments = [];
        $relations   = [];
        $assignees   = [];
        $allUsers    = [];
        $isFavorited = false;
        $canMutate   = $this->canMutateTask($id);

        if ($db->tableExists('tb_comments')) {
            $comments = (new CommentModel())->getForTask($id);
        }
        if ($db->tableExists('tb_activity_log')) {
            $activityLog = (new ActivityLogModel())->getForTask($id);
        }
        if ($db->tableExists('tb_revisions')) {
            $revisions = (new RevisionModel())->getForTask($id);
        }
        if ($db->tableExists('tb_attachments')) {
            $attachments = (new AttachmentModel())->getForTask($id);
        }
        if ($db->tableExists('tb_task_relations')) {
            $relations = (new TaskRelationModel())->getForTask($id);
        }
        if ($db->tableExists('tb_task_assignees')) {
            $assignees = (new TaskAssigneeModel())->getAssignees($id);
        }
        if ($db->tableExists('tb_user_favorites') && $uid > 0) {
            $isFavorited = (new FavoriteModel())->isFavorited($uid, 'task', $id);
        }

        $allUsers = $this->userModel->getActiveUsers();

        $projectMeta = null;
        if ($db->tableExists('tb_projects') && ! empty($task['project_id'])) {
            $projectMeta = $db->table('tb_projects p')
                ->select('p.id, p.name, p.client_id, c.name AS client_name')
                ->join('tb_clients c', 'c.id = p.client_id', 'left')
                ->where('p.id', (int) $task['project_id'])
                ->get()
                ->getRowArray();
        }

        return [
            'task'                => $task,
            'projectMeta'         => $projectMeta,
            'comments'            => $comments,
            'activityLog'         => $activityLog,
            'revisions'           => $revisions,
            'attachments'         => $attachments,
            'relations'           => $relations,
            'assignees'           => $assignees,
            'allUsers'            => $allUsers,
            'isFavorited'         => $isFavorited,
            'canMutate'           => $canMutate,
            'taskDetailProjectId' => $taskDetailProjectId,
        ];
    }

    public function show(int $id): string|\CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', $this->request->isAJAX());
        if (! $this->canViewTaskInScope($id)) {
            if ($this->request->isAJAX()) {
                return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
            }

            return redirect()->to('/tasks')->with('error', 'Anda tidak memiliki akses ke task ini.');
        }
        $task = $this->taskModel->getTaskWithFields($id);

        if (!$task) {
            return $this->response->setStatusCode(404)->setBody('Task not found');
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'task' => $task]);
        }

        $db  = \Config\Database::connect();
        $pid = (int) ($task['project_id'] ?? 0);
        if ($db->fieldExists('project_id', 'tb_task') && $pid > 0) {
            return redirect()->to('/projects/' . $pid . '/tasks/' . $id);
        }

        helper('editorjs');

        return view('layouts/main', [
            'title'   => 'Detail Task',
            'content' => view('tasks/show', $this->buildTaskShowViewData($task, $id, null, null)),
        ]);
    }

    /**
     * Detail task proyek: satu layar master–detail di /projects/{id}?task={taskId}.
     */
    public function showForProject(int $projectId, int $taskId): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('view_tasks', false);
        if (! $this->canViewTaskInScope($taskId)) {
            return redirect()->to('/projects/' . $projectId)->with('error', 'Anda tidak memiliki akses ke task ini.');
        }

        $task = $this->taskModel->getTaskWithFields($taskId);
        if (! $task) {
            return redirect()->to('/projects/' . $projectId)->with('error', 'Task tidak ditemukan.');
        }

        $tpid = (int) ($task['project_id'] ?? 0);
        if ($tpid !== $projectId) {
            if ($tpid > 0) {
                return redirect()->to('/projects/' . $tpid . '?task=' . $taskId);
            }

            return redirect()->to('/tasks/' . $taskId);
        }

        return redirect()->to('/projects/' . $projectId . '?task=' . $taskId);
    }

    /**
     * Fragment HTML untuk panel drawer di halaman work items proyek.
     */
    public function showForProjectPanel(int $projectId, int $taskId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $this->requirePerm('view_tasks', false);
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $task = $this->taskModel->getTaskWithFields($taskId);
        if (! $task) {
            return $this->response->setStatusCode(404)->setBody('Task not found');
        }

        $tpid = (int) ($task['project_id'] ?? 0);
        if ($tpid !== $projectId) {
            return $this->response->setStatusCode(404)->setBody('Task not found');
        }

        helper('editorjs');

        $data = $this->buildTaskShowViewData($task, $taskId, $projectId, $projectId);
        $data['panelMode'] = true;

        return $this->response->setContentType('text/html')->setBody(view('tasks/project_task_panel', $data));
    }

    // ------------------------------------------------------------------
    // EDIT FORM
    // ------------------------------------------------------------------
    public function edit(int $id): string
    {
        return redirect()->to('/tasks')
            ->with('error', 'Mode edit sekarang dibuka lewat modal dari halaman Tasks.');
    }

    // ------------------------------------------------------------------
    // UPDATE
    // ------------------------------------------------------------------
    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('view_tasks');
        if (! $this->canMutateTask($id)) {
            return redirect()->to('/tasks')->with('error', 'Anda tidak memiliki akses untuk mengubah task ini.');
        }

        $post        = $this->request->getPost();
        $status      = $post['status'] ?? 'pending';
        $fieldValues = $post['fields'] ?? [];

        $existing = $this->taskModel->find($id);
        if (! $existing) {
            return redirect()->to('/tasks')->with('error', 'Task tidak ditemukan.');
        }
        $taskPid = (int) ($existing['project_id'] ?? 0);

        if ($taskPid > 0) {
            $activeFields = $this->fieldModel->getActiveFieldsForProject($taskPid);
        } else {
            $activeFields = $this->fieldModel->getActiveFields();
        }
        $activeFields = array_map([$this->fieldModel, 'decodeOptions'], $activeFields);
        $errors = [];

        foreach ($activeFields as $f) {
            if ($f['type'] === 'boolean') {
                continue;
            }

            if ($f['is_required'] && empty($fieldValues[$f['field_key']])) {
                $errors[$f['field_key']] = $f['field_label'] . ' wajib diisi.';
            }
        }

        if (! empty($errors)) {
            return redirect()->back()->with('errors', $errors)->withInput();
        }
        $userSourceErrors = $this->validateUserSourceFieldValues($activeFields, $fieldValues);
        if (! empty($userSourceErrors)) {
            return redirect()->back()->with('errors', $userSourceErrors)->withInput();
        }

        $db   = \Config\Database::connect();
        $core = [];
        if ($db->fieldExists('project_id', 'tb_task')) {
            if ($taskPid > 0) {
                $core['project_id'] = $taskPid;
            } else {
                $core['project_id'] = null;
            }
        }
        if ($db->fieldExists('parent_id', 'tb_task')) {
            $core['parent_id'] = $post['parent_id'] ?? null;
        }

        $ok = $this->taskModel->updateTaskWithFields($id, $status, $fieldValues, $core === [] ? null : $core);

        if (! $ok) {
            return redirect()->back()->with('error', 'Gagal mengupdate task.');
        }

        $this->auditTaskMutationIfNotOwner($id, 'form_update');

        if ($db->tableExists('tb_activity_log')) {
            $actor = (int) (session()->get('user_id') ?? 0);
            if ($actor > 0) {
                (new ActivityLogModel())->log($id, $actor, 'updated', 'Task diperbarui (form)');
            }
        }

        if ($taskPid > 0) {
            return redirect()->to('/projects/' . $taskPid)->with('success', 'Task berhasil diupdate.');
        }

        return redirect()->to('/tasks')->with('success', 'Task berhasil diupdate!');
    }

    // ------------------------------------------------------------------
    // DELETE  (soft delete)
    // ------------------------------------------------------------------
    public function delete(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('manage_tasks');
        if (!$this->canMutateTask($id)) {
            return redirect()->to('/tasks')->with('error', 'Anda tidak memiliki akses untuk menghapus task ini.');
        }
        $db  = \Config\Database::connect();
        $row = $this->taskModel->find($id);
        $pid = ($db->fieldExists('project_id', 'tb_task') && $row) ? (int) ($row['project_id'] ?? 0) : 0;

        $this->auditTaskMutationIfNotOwner($id, 'soft_delete');
        $this->taskModel->delete($id);
        if ($pid > 0) {
            return redirect()->to('/projects/' . $pid)->with('success', 'Work item dihapus (arsip). Trash task internal hanya untuk task tanpa proyek.');
        }

        return redirect()->to('/tasks')->with('success', 'Task dihapus.');
    }

    // ------------------------------------------------------------------
    // RESTORE  (dari soft delete)
    // ------------------------------------------------------------------
    public function restore(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('manage_tasks');
        if (!$this->canMutateTask($id)) {
            return redirect()->to('/tasks/trash')->with('error', 'Anda tidak memiliki akses untuk memulihkan task ini.');
        }
        $db  = \Config\Database::connect();
        $row = $this->taskModel->withDeleted()->find($id);
        if ($db->fieldExists('project_id', 'tb_task') && $row && ! empty($row['project_id']) && (int) $row['project_id'] > 0) {
            return redirect()->to('/tasks/trash')->with('error', 'Work item proyek tidak dikelola dari Trash task internal.');
        }
        $this->auditTaskMutationIfNotOwner($id, 'restore');
        $this->taskModel->withDeleted()->where('id', $id)->set(['deleted_at' => null])->update();
        return redirect()->to('/tasks/trash')->with('success', 'Task berhasil dipulihkan!');
    }

    // ------------------------------------------------------------------
    // FORCE DELETE (permanent)
    // ------------------------------------------------------------------
    public function forceDelete(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('manage_tasks');
        if (!$this->canMutateTask($id)) {
            return redirect()->to('/tasks/trash')->with('error', 'Anda tidak memiliki akses untuk menghapus permanen task ini.');
        }
        $db  = \Config\Database::connect();
        $row = $this->taskModel->withDeleted()->find($id);
        if ($db->fieldExists('project_id', 'tb_task') && $row && ! empty($row['project_id']) && (int) $row['project_id'] > 0) {
            return redirect()->to('/tasks/trash')->with('error', 'Work item proyek tidak dikelola dari Trash task internal.');
        }
        $this->auditTaskMutationIfNotOwner($id, 'force_delete');
        $db = \Config\Database::connect();
        $hasVendorCol = $db->fieldExists('account_id', 'tb_task');
        $db->transStart();
        $db->table('tb_task_values')->where('task_id', $id)->delete();
        $db->table('tb_submissions')->where('task_id', $id)->delete();
        $this->taskModel->delete($id, true);
        $db->transComplete();

        if (!$db->transStatus()) {
            return redirect()->to('/tasks/trash')->with('error', 'Gagal hapus permanen task.');
        }

        return redirect()->to('/tasks/trash')->with('success', 'Task dihapus permanen.');
    }

    // ------------------------------------------------------------------
    // BULK ACTIONS FOR TRASH
    // ------------------------------------------------------------------
    public function bulkTrashAction(): \CodeIgniter\HTTP\RedirectResponse
    {
        $this->requirePerm('manage_tasks');
        $ids = $this->request->getPost('task_ids') ?? [];
        $action = $this->request->getPost('bulk_action') ?? '';

        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (empty($ids)) {
            return redirect()->to('/tasks/trash')->with('error', 'Pilih minimal 1 task.');
        }

        $db = \Config\Database::connect();
        $ids = array_values(array_filter($ids, function (int $id) use ($db): bool {
            if (! $this->canMutateTask($id)) {
                return false;
            }
            if ($db->fieldExists('project_id', 'tb_task')) {
                $row = $this->taskModel->withDeleted()->find($id);
                if ($row && ! empty($row['project_id']) && (int) $row['project_id'] > 0) {
                    return false;
                }
            }

            return true;
        }));
        if ($ids === []) {
            return redirect()->to('/tasks/trash')->with('error', 'Tidak ada task internal yang dapat diproses.');
        }

        $db->transStart();

        if ($action === 'restore') {
            $db->table('tb_task')
                ->whereIn('id', $ids)
                ->set(['deleted_at' => null])
                ->update();
            $db->transComplete();
            if (!$db->transStatus()) {
                return redirect()->to('/tasks/trash')->with('error', 'Bulk restore gagal.');
            }
            $count = count($ids);
            return redirect()->to('/tasks/trash')->with('success', "{$count} task berhasil dipulihkan.");
        }

        if ($action === 'force_delete') {
            $db->table('tb_task_values')->whereIn('task_id', $ids)->delete();
            $db->table('tb_submissions')->whereIn('task_id', $ids)->delete();
            foreach ($ids as $id) {
                $this->taskModel->delete($id, true);
            }
            $db->transComplete();
            if (!$db->transStatus()) {
                return redirect()->to('/tasks/trash')->with('error', 'Bulk hapus permanen gagal.');
            }
            $count = count($ids);
            return redirect()->to('/tasks/trash')->with('success', "{$count} task dihapus permanen.");
        }

        $db->transComplete();
        return redirect()->to('/tasks/trash')->with('error', 'Aksi bulk tidak valid.');
    }

    // ------------------------------------------------------------------
    // TRASH  (list soft-deleted tasks)
    // ------------------------------------------------------------------
    public function trash(): string
    {
        $this->requirePerm('view_tasks');
        $currentRole   = session()->get('user_role') ?? 'member';
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        $scopeUserId   = ($currentRole === 'member') ? $currentUserId : null;

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;
        $result  = $this->taskModel->getTrashTasksPage($page, $perPage, $scopeUserId);
        $total   = $result['total'];
        $tasks   = $result['items'];
        $totalPages = (int) ceil($total / $perPage);

        return view('layouts/main', [
            'title'   => 'Trash task internal',
            'content' => view('tasks/trash', [
                'tasks'        => $tasks,
                'pager'        => [
                    'total'      => $total,
                    'page'       => $page,
                    'perPage'    => $perPage,
                    'totalPages' => $totalPages,
                ],
                'pagerQuery'   => table_pagination_query_params($this->request),
                'pagerUriPath' => table_pagination_uri_path(),
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Update single field value inline (for select dropdowns in table)
    // ------------------------------------------------------------------
    public function fieldUpdate(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        if (!$this->canMutateTask($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        $db   = \Config\Database::connect();
        $json = $this->request->getJSON(true);
        if (! is_array($json)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Body JSON tidak valid.',
            ]);
        }
        $fieldKey = $json['field_key'] ?? '';
        $value    = $json['value'] ?? '';
        $expectedUpdatedAt = $json['expected_updated_at'] ?? null;
        if (is_string($expectedUpdatedAt)) {
            $expectedUpdatedAt = trim($expectedUpdatedAt);
            if ($expectedUpdatedAt === '') {
                $expectedUpdatedAt = null;
            }
        } elseif ($expectedUpdatedAt !== null && $expectedUpdatedAt !== '') {
            $expectedUpdatedAt = trim((string) $expectedUpdatedAt);
            if ($expectedUpdatedAt === '') {
                $expectedUpdatedAt = null;
            }
        }

        if (!$fieldKey) {
            return $this->response->setJSON(['success' => false, 'message' => 'field_key required']);
        }

        $trow = $db->table('tb_task')->select('project_id')->where('id', $id)->get()->getRowArray();
        $tp   = (int) ($trow['project_id'] ?? 0);
        $field = $this->fieldModel->resolveFieldRowForTask($tp, $fieldKey);

        if (! $field) {
            return $this->response->setJSON(['success' => false, 'message' => 'Field not found']);
        }
        if (($field['type'] ?? '') === 'richtext' && is_string($value)) {
            $value = RichtextSanitizer::sanitizeEditorJsJson($value);
        }
        $isAccountField = (string) ($field['field_key'] ?? '') === 'account';
        if (($field['data_source'] ?? 'manual') === 'team_users' || in_array((string) ($field['field_key'] ?? ''), ['pic_name', 'pic'], true)) {
            $allowedValues = array_column($this->buildUserSourceOptions($field), 'value');
            if ($value !== '' && !in_array((string) $value, $allowedValues, true)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'PIC tidak valid untuk role filter saat ini.',
                ]);
            }
        } elseif (($field['data_source'] ?? 'manual') === 'account_sources' || $isAccountField) {
            $allowedValues = array_column($this->buildAccountSourceOptions($field), 'value');
            if ($value !== '' && !in_array((string) $value, $allowedValues, true)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Account tidak valid.',
                ]);
            }
        }

        $now      = date('Y-m-d H:i:s');
        $existing = $db->table('tb_task_values')
            ->where('task_id', $id)
            ->where('field_id', $field['id'])
            ->get()->getRowArray();

        $currentUpdatedAt = $existing['updated_at'] ?? null;
        $hasExpectedVersion = !($expectedUpdatedAt === '' || $expectedUpdatedAt === null);
        $normalizeTaskValueTs = static function ($ts): ?string {
            if ($ts === null) {
                return null;
            }
            $s = trim((string) $ts);
            if ($s === '') {
                return null;
            }
            $s = str_replace('T', ' ', $s);
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $s, $m)) {
                return $m[1];
            }

            return $s;
        };
        $normExpected = $normalizeTaskValueTs($hasExpectedVersion ? (string) $expectedUpdatedAt : null);
        $normCurrent  = $normalizeTaskValueTs($currentUpdatedAt);
        // Hanya kunci optimistik jika sudah ada baris nilai; hindari 409 palsu (mis. mikrodetik DB vs atribut HTML).
        if ($existing && $hasExpectedVersion && $normExpected !== null && $normCurrent !== null && $normExpected !== $normCurrent) {
            return $this->response->setStatusCode(409)->setJSON([
                'success'           => false,
                'conflict'          => true,
                'message'           => 'Data sudah diubah user lain.',
                'server_value'      => $existing['value'] ?? '',
                'server_updated_at' => $currentUpdatedAt,
                'csrf'              => csrf_hash(),
            ]);
        }

        if ($existing) {
            $db->table('tb_task_values')
                ->where('task_id', $id)->where('field_id', $field['id'])
                ->update(['value' => $value, 'updated_at' => $now]);
        } else {
            $db->table('tb_task_values')->insert([
                'task_id'    => $id,
                'field_id'   => $field['id'],
                'value'      => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Task proyek: jangan sentuh submission / account_id core sync.
        if ($tp <= 0) {
            if (! empty($field['submission_col']) || $field['field_key'] === 'setor') {
                $this->taskModel->syncSubmissionFromTaskValues($id);
            }

            if ($fieldKey === 'account'
                || (($field['data_source'] ?? '') === 'account_sources' && ($field['submission_col'] ?? '') === 'account')) {
                $this->taskModel->syncCoreAccountIdFromAccountField($id, true);
            }
        }

        $this->auditTaskMutationIfNotOwner($id, 'field_update:' . $fieldKey);

        return $this->response->setJSON([
            'success'           => true,
            'csrf'              => csrf_hash(),
            'server_updated_at' => $now,
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Update task status inline
    // ------------------------------------------------------------------
    public function updateStatus(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        if (!$this->canMutateTask($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        $json    = $this->request->getJSON(true);
        $status  = $json['status'] ?? '';
        $allowed = ['pending', 'on_progress', 'done', 'cancelled'];

        if (!in_array($status, $allowed)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid status']);
        }

        $prev = $this->taskModel->find($id);
        $oldStatus = (string) ($prev['status'] ?? '');

        $this->taskModel->update($id, ['status' => $status]);
        $this->auditTaskMutationIfNotOwner($id, 'status');

        $db = \Config\Database::connect();
        if ($db->tableExists('tb_activity_log') && $oldStatus !== $status) {
            $actor = (int) (session()->get('user_id') ?? 0);
            if ($actor > 0) {
                (new ActivityLogModel())->logStatusChanged($id, $actor, $oldStatus, $status);
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'csrf'    => csrf_hash(),
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Toggle setor (langsung update setor field di EAV + submission)
    // ------------------------------------------------------------------
    public function toggleSetor(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePermOneOf(['view_tasks', 'view_submissions'], true);
        if (!$this->canMutateTask($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        $db    = \Config\Database::connect();
        $taskRow = $this->taskModel->find($id);
        if ($taskRow && ! empty($taskRow['project_id']) && (int) $taskRow['project_id'] > 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Setor hanya untuk task internal.',
                'csrf'    => csrf_hash(),
            ]);
        }
        $json  = $this->request->getJSON(true);
        $value = ($json['setor'] ?? false) ? '1' : '0';
        $expectedSetorUpdatedAt = $json['expected_setor_updated_at'] ?? null;

        $db->transStart();

        // Extra fields sent from the modal
        $modalData = [
            'theme'        => $json['product_name'] ?? null, // EAV key (if mapped)
            'artboard'     => $json['category']     ?? null, // EAV key (if mapped)
            'product_name' => $json['product_name'] ?? null, // direct submission col
            'category'     => $json['category']     ?? null, // direct submission col
            'link_setor'   => $json['link_setor']   ?? null, // direct submission col
        ];

        // Ambil field_id untuk 'setor' (hanya definisi internal)
        $sf = $db->table('tb_fields')->where('field_key', 'setor');
        if ($db->fieldExists('project_id', 'tb_fields')) {
            $sf->where('project_id IS NULL', null, false);
        }
        $field = $sf->get()->getRowArray();
        if (! $field) {
            $db->transRollback();

            return $this->response->setJSON(['success' => false, 'message' => 'Field setor tidak ditemukan']);
        }

        $now      = date('Y-m-d H:i:s');
        $existing = $db->table('tb_task_values')
            ->where('task_id', $id)
            ->where('field_id', $field['id'])
            ->get()->getRowArray();

        $currentSetorUpdatedAt = $existing['updated_at'] ?? null;
        $hasExpectedVersion = !($expectedSetorUpdatedAt === '' || $expectedSetorUpdatedAt === null);
        $normalizedExpected = $hasExpectedVersion ? (string) $expectedSetorUpdatedAt : null;
        $normalizedCurrent  = $currentSetorUpdatedAt ? (string) $currentSetorUpdatedAt : null;
        if ($hasExpectedVersion && $normalizedExpected !== $normalizedCurrent) {
            $db->transRollback();

            return $this->response->setStatusCode(409)->setJSON([
                'success'           => false,
                'conflict'          => true,
                'message'           => 'Status setor sudah diubah user lain.',
                'server_setor'      => $normalizedCurrent !== null && ($existing['value'] ?? '0') === '1',
                'server_updated_at' => $currentSetorUpdatedAt,
                'csrf'              => csrf_hash(),
            ]);
        }

        if ($existing) {
            $db->table('tb_task_values')
                ->where('task_id', $id)->where('field_id', $field['id'])
                ->update(['value' => $value, 'updated_at' => $now]);
        } else {
            $db->table('tb_task_values')->insert([
                'task_id' => $id, 'field_id' => $field['id'],
                'value' => $value, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Use single source-of-truth sync logic from model.
        $overrides = ['setor' => $value];
        foreach ($modalData as $k => $v) {
            if ($v !== null && $v !== '') {
                $overrides[$k] = $v;
            }
        }
        $this->taskModel->syncSubmissionFromTaskValues($id, $overrides, false);
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal sinkronisasi submission']);
        }

        $this->auditTaskMutationIfNotOwner($id, 'toggle_setor');

        return $this->response->setJSON([
            'success' => true,
            'setor'   => (bool) $value,
            'csrf'    => csrf_hash(),
            'setor_updated_at' => $now,
        ]);
    }



    // ------------------------------------------------------------------
    // AJAX: Get task field data for setor modal
    // ------------------------------------------------------------------
    public function getSetorData(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePermOneOf(['view_tasks', 'view_submissions'], true);
        if (! $this->canViewTaskInScope($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }
        $tr = $this->taskModel->find($id);
        if ($tr && ! empty($tr['project_id']) && (int) $tr['project_id'] > 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Setor tidak berlaku untuk task proyek.',
                'csrf'    => csrf_hash(),
            ]);
        }

        $db   = \Config\Database::connect();
        $rows = $db->table('tb_task_values tv')
            ->select('f.field_key, f.data_source, f.source_config, tv.value')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->where('tv.task_id', $id)
            ->get()->getResultArray();

        $userIds = [];
        foreach ($rows as $r) {
            if (($r['data_source'] ?? 'manual') === 'team_users') {
                $uid = (int) ($r['value'] ?? 0);
                if ($uid > 0) {
                    $userIds[] = $uid;
                }
            }
        }
        $userIds = array_values(array_unique($userIds));
        $userLabels = [];
        if ($userIds !== []) {
            $urows = $db->table('tb_users')
                ->select('id, nickname, username')
                ->whereIn('id', $userIds)
                ->get()
                ->getResultArray();
            foreach ($urows as $u) {
                $lid = (int) ($u['id'] ?? 0);
                $label = trim((string) ($u['nickname'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($u['username'] ?? ''));
                }
                $userLabels[$lid] = $label;
            }
        }

        $fields = [];
        foreach ($rows as $r) {
            $value = (string) ($r['value'] ?? '');
            if (($r['data_source'] ?? 'manual') === 'team_users') {
                $uid = (int) $value;
                if ($uid > 0 && ($userLabels[$uid] ?? '') !== '') {
                    $value = $userLabels[$uid];
                }
            } elseif (($r['data_source'] ?? 'manual') === 'account_sources') {
                $opts = $this->buildAccountSourceOptions([
                    'source_config' => (string) ($r['source_config'] ?? ''),
                ]);
                $map = [];
                foreach ($opts as $opt) {
                    $map[(string) ($opt['value'] ?? '')] = (string) ($opt['label'] ?? '');
                }
                $value = (string) ($map[$value] ?? $value);
            }
            $fields[$r['field_key']] = $value;
        }

        // Also pull existing submission data if any
        $sub = $db->table('tb_submissions')->where('task_id', $id)->get()->getRowArray();

        return $this->response->setJSON([
            'success' => true,
            'fields'  => $fields,
            'sub'     => $sub ?? [],
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Duplicate a task (clone row + EAV values, skip setor field)
    // ------------------------------------------------------------------
    public function duplicate(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        if (!$this->canMutateTask($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk menduplikasi task ini.');
        }
        $task = $this->taskModel->find($id);
        if (!$task) {
            return $this->response->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Task tidak ditemukan']);
        }

        $db = \Config\Database::connect();
        $hasVendorCol = $db->fieldExists('account_id', 'tb_task');

        $tp = (int) ($task['project_id'] ?? 0);
        $sf = $db->table('tb_fields')->where('field_key', 'setor');
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if ($tp > 0) {
                $sf->where('project_id', $tp);
            } else {
                $sf->where('project_id IS NULL', null, false);
            }
        }
        $setorField   = $sf->get()->getRowArray();
        $setorFieldId = $setorField['id'] ?? null;

        $db->transStart();

        $insert = [
            'user_id'   => $task['user_id'] ?? 1,
            'status'    => 'pending',
            'progress'  => 0,
            'deadline'  => $task['deadline'] ?? null,
        ];
        if ($hasVendorCol) {
            $insert['account_id'] = $task['account_id'] ?? null;
        }
        if ($db->fieldExists('project_id', 'tb_task')) {
            $insert['project_id'] = ! empty($task['project_id']) ? (int) $task['project_id'] : null;
        }
        if ($db->fieldExists('parent_id', 'tb_task')) {
            $insert['parent_id'] = ! empty($task['parent_id']) ? (int) $task['parent_id'] : null;
        }

        $newId = $this->taskModel->insert($insert, true);

        if ($newId) {
            $values = $db->table('tb_task_values')
                ->where('task_id', $id)
                ->get()->getResultArray();

            $now = date('Y-m-d H:i:s');
            foreach ($values as $v) {
                // Skip the 'setor' field — duplicate starts as not-submitted
                if ($setorFieldId && (int) $v['field_id'] === (int) $setorFieldId) continue;

                $db->table('tb_task_values')->insert([
                    'task_id'    => $newId,
                    'field_id'   => $v['field_id'],
                    'value'      => $v['value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $db->transComplete();

        if (!$db->transStatus() || !$newId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal duplikasi task']);
        }

        $this->auditTaskMutationIfNotOwner((int) $id, 'duplicate_source');

        return $this->response->setJSON([
            'success' => true,
            'task_id' => $newId,
            'csrf'    => csrf_hash(),
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Bulk create tasks from a list of names
    // ------------------------------------------------------------------
    public function bulkCreate(): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        $userId = (int) (session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->jsonForbidden('Login diperlukan.');
        }
        $json   = $this->request->getJSON(true);
        $lines  = $json['lines']  ?? [];
        $status = $json['status'] ?? 'pending';
        $titleFieldKey = $json['title_field_key'] ?? null;

        $lines = array_values(array_filter(array_map('trim', (array) $lines), fn($v) => $v !== ''));

        if (empty($lines)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada task yang valid']);
        }

        if (count($lines) > self::BULK_CREATE_MAX_LINES) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Maksimal ' . self::BULK_CREATE_MAX_LINES . ' baris per permintaan.',
            ]);
        }

        $count  = 0;
        $ids    = [];

        foreach ($lines as $line) {
            $fieldValues = [];
            if ($titleFieldKey) {
                $fieldValues[$titleFieldKey] = $line;
            }

            $taskId = $this->taskModel->createTaskWithFields($userId, $status, $fieldValues);
            if ($taskId) {
                $count++;
                $ids[] = $taskId;
            }
        }

        return $this->response->setJSON([
            'success'  => $count > 0,
            'count'    => $count,
            'task_ids' => $ids,
            'csrf'     => csrf_hash(),
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Update core task fields (progress, deadline) directly on tb_task
    // ------------------------------------------------------------------
    public function updateCore(int $id): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_tasks', true);
        if (!$this->canMutateTask($id)) {
            return $this->jsonForbidden('Anda tidak memiliki akses untuk mengubah task ini.');
        }
        $json    = $this->request->getJSON(true);
        $allowed = ['progress', 'deadline', 'parent_id'];
        $data    = [];
        $db      = \Config\Database::connect();

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }

            if ($key === 'progress') {
                $data[$key] = max(0, min(100, (int) $json[$key]));
            } elseif ($key === 'deadline') {
                $val        = trim((string) ($json[$key] ?? ''));
                $data[$key] = ($val !== '') ? date('Y-m-d', strtotime($val)) : null;
            } elseif ($key === 'parent_id' && $db->fieldExists('parent_id', 'tb_task')) {
                $v = $json[$key];
                $data[$key] = ($v === null || $v === '') ? null : (int) $v;
                if (isset($data[$key]) && $data[$key] <= 0) {
                    $data[$key] = null;
                }
                if (isset($data[$key]) && (int) $data[$key] === $id) {
                    unset($data[$key]);
                }
            }
        }

        $taskRow = $this->taskModel->find($id);
        $isProjectTask = $taskRow
            && $db->fieldExists('project_id', 'tb_task')
            && ! empty($taskRow['project_id'])
            && (int) $taskRow['project_id'] > 0;

        if ($isProjectTask) {
            foreach (['created_at', 'updated_at'] as $tsKey) {
                if (! array_key_exists($tsKey, $json)) {
                    continue;
                }
                $val = trim((string) ($json[$tsKey] ?? ''));
                if ($val === '') {
                    continue;
                }
                $norm = str_contains($val, 'T') ? str_replace('T', ' ', $val) : $val;
                if (strlen($norm) <= 10) {
                    $norm .= ' 00:00:00';
                }
                $parsed = strtotime($norm);
                if ($parsed !== false) {
                    $data[$tsKey] = date('Y-m-d H:i:s', $parsed);
                }
            }
        }

        if (empty($data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada data']);
        }

        $this->taskModel->update($id, $data);
        $this->auditTaskMutationIfNotOwner($id, 'core_update');

        return $this->response->setJSON([
            'success' => true,
            'data'    => $data,
            'csrf'    => csrf_hash(),
        ]);
    }

    // ------------------------------------------------------------------
    // Submissions list  (tasks yang sudah setor)
    // ------------------------------------------------------------------
    public function submissions(): string
    {
        $this->requirePerm('view_submissions');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;
        $result  = $this->taskModel->getTasksWithSubmissionPage($page, $perPage);
        $total   = $result['total'];
        $items   = $result['items'];
        $totalPages = (int) ceil($total / $perPage);

        $db            = \Config\Database::connect();
        $pivotEnabled  = $db->tableExists('tb_submission_upload_status') && $db->tableExists('tb_product_groups');
        $colConfig     = ['groups' => [], 'platforms' => [], 'fileTypes' => []];
        $statusMap     = [];
        if ($pivotEnabled) {
            $colConfig = $this->uploadStatusModel->getColumnConfig();
            $submissionIds = array_values(array_filter(array_map(
                static fn(array $r): int => (int) ($r['submission_id'] ?? 0),
                $items
            )));
            $statusMap = $this->uploadStatusModel->getStatusMap($submissionIds);
        }

        return view('layouts/main', [
            'title'   => 'Daftar Setor',
            'content' => view('tasks/submissions', [
                'submissions'   => $items,
                'colConfig'     => $colConfig,
                'statusMap'     => $statusMap,
                'pivotEnabled'  => $pivotEnabled && ($colConfig['groups'] ?? []) !== [],
                'pager'         => [
                    'total'      => $total,
                    'page'       => $page,
                    'perPage'    => $perPage,
                    'totalPages' => $totalPages,
                ],
                'pagerQuery'   => table_pagination_query_params($this->request),
                'pagerUriPath' => table_pagination_uri_path(),
            ]),
        ]);
    }

    public function updateUploadStatus(int $taskId): \CodeIgniter\HTTP\Response
    {
        $this->requirePerm('view_submissions', true);
        if (! $this->canViewTaskInScope($taskId)) {
            return $this->jsonForbidden('Anda tidak memiliki akses ke task ini.');
        }

        $json = $this->request->getJSON(true);
        if (! is_array($json)) {
            $json = [];
        }

        $submissionId = (int) ($json['submission_id'] ?? 0);
        $groupId      = (int) ($json['group_id'] ?? 0);
        $platformId   = (int) ($json['platform_id'] ?? 0);
        $fileTypeRaw  = $json['file_type_id'] ?? null;
        $fileTypeId   = ($fileTypeRaw === null || $fileTypeRaw === '' || $fileTypeRaw === false)
            ? null
            : (int) $fileTypeRaw;
        if (! array_key_exists('status', $json)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Status wajib (kosong = hapus / tampil —).',
                'csrf'    => csrf_hash(),
            ]);
        }
        $status = trim((string) $json['status']);
        $userId = (int) (session()->get('user_id') ?? 0);

        if ($submissionId < 1 || $groupId < 1 || $userId < 1) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Parameter tidak lengkap.',
                'csrf'    => csrf_hash(),
            ]);
        }

        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_submission_upload_status')) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Fitur status upload belum aktif (migrasi DB).',
                'csrf'    => csrf_hash(),
            ]);
        }

        $sub = $db->table('tb_submissions')->where('id', $submissionId)->where('task_id', $taskId)->get()->getRowArray();
        if (! $sub) {
            return $this->jsonForbidden('Submission tidak ditemukan.');
        }

        $group = $this->uploadStatusModel->getProductGroup($groupId);
        if (! $group || (int) ($group['status'] ?? 0) !== 1) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Grup produk tidak valid.',
                'csrf'    => csrf_hash(),
            ]);
        }
        $hasPt = (int) ($group['has_platform'] ?? 1) === 1;
        $hasFt = (int) ($group['has_file_types'] ?? 0) === 1;

        if ($hasPt) {
            if ($platformId < 1) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Platform wajib untuk grup ini.',
                    'csrf'    => csrf_hash(),
                ]);
            }
            $ptB = $db->table('tb_platforms')->where('id', $platformId)->where('status', 1);
            if ($db->fieldExists('product_group_id', 'tb_platforms')) {
                $ptB->where('product_group_id', $groupId);
            }
            $ptOk = $ptB->countAllResults();
            if ($ptOk < 1) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Platform tidak valid.',
                    'csrf'    => csrf_hash(),
                ]);
            }
            if (! $this->uploadStatusModel->platformAssignedToGroup($groupId, $platformId)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Platform tidak termasuk assignment grup ini.',
                    'csrf'    => csrf_hash(),
                ]);
            }
        } else {
            $platformId = 0;
        }

        if ($hasFt) {
            if ($fileTypeId === null || $fileTypeId < 1) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Tipe file wajib untuk grup ini.',
                    'csrf'    => csrf_hash(),
                ]);
            }
            $ftB = $db->table('tb_file_types')->where('id', $fileTypeId)->where('status', 1);
            if ($db->fieldExists('product_group_id', 'tb_file_types')) {
                $ftB->where('product_group_id', $groupId);
            }
            $ftOk = $ftB->countAllResults();
            if ($ftOk < 1) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Tipe file tidak valid.',
                    'csrf'    => csrf_hash(),
                ]);
            }
            if (! $this->uploadStatusModel->fileTypeAssignedToGroup($groupId, $fileTypeId)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Tipe file tidak termasuk assignment grup ini.',
                    'csrf'    => csrf_hash(),
                ]);
            }
        } else {
            $fileTypeId = null;
        }

        if ($status === '') {
            $this->uploadStatusModel->deleteStatusCell(
                $submissionId,
                $groupId,
                $platformId,
                $fileTypeId
            );

            return $this->response->setJSON([
                'success' => true,
                'status'  => '',
                'csrf'    => csrf_hash(),
            ]);
        }

        $statusCfg = config('UploadPivotStatuses');
        $allowed   = $statusCfg instanceof \Config\UploadPivotStatuses
            ? $statusCfg->allowedValues()
            : ['draft', 'under_review', 'uploaded', 'soft_reject', 'reject'];
        if (! in_array($status, $allowed, true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Status tidak valid.',
                'csrf'    => csrf_hash(),
            ]);
        }

        $ok = $this->uploadStatusModel->upsertStatus(
            $submissionId,
            $groupId,
            $platformId,
            $fileTypeId,
            $status,
            $userId
        );
        if (! $ok) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Gagal menyimpan status.',
                'csrf'    => csrf_hash(),
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'status'  => $status,
            'csrf'    => csrf_hash(),
        ]);
    }

    private function buildUserSourceOptions(array $field): array
    {
        $cfg = $field['source_config_array'] ?? [];
        if (!is_array($cfg) && !empty($field['source_config'])) {
            $cfg = json_decode((string) $field['source_config'], true) ?: [];
        }
        $allowedRoles = array_values(array_filter((array) ($cfg['allowed_roles'] ?? []), static fn($v): bool => trim((string) $v) !== ''));

        $builder = $this->userModel->where('status', 'active')->orderBy('username', 'ASC');
        if ($allowedRoles !== []) {
            $builder->whereIn('role', $allowedRoles);
        } else {
            $builder->whereNotIn('role', ['super_admin', 'admin']);
        }
        $users = $builder->findAll();

        $options = [];
        foreach ($users as $u) {
            $id = (string) ($u['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($u['nickname'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($u['username'] ?? ''));
            }
            if ($label === '') {
                $label = '#' . $id;
            }
            $options[] = ['value' => $id, 'label' => $label];
        }
        return $options;
    }

    private function buildAccountSourceOptions(array $field): array
    {
        $cfg = $field['source_config_array'] ?? [];
        if (!is_array($cfg) && !empty($field['source_config'])) {
            $cfg = json_decode((string) $field['source_config'], true) ?: [];
        }
        $includeOffice = ($cfg['include_office'] ?? true) !== false;
        $includeVendor = ($cfg['include_vendor'] ?? true) !== false;

        $options = [];
        if ($includeOffice) {
            foreach ($this->accountModel->getActiveByType('office') as $office) {
                $id = (int) ($office['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($office['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $options[] = [
                    'value' => 'account:' . $id,
                    'label' => $name,
                    'group' => 'Akun Kantor',
                ];
            }
        }

        if ($includeVendor) {
            foreach ($this->accountModel->getActiveByType('vendor') as $vendor) {
                $id = (int) ($vendor['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($vendor['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $options[] = [
                    'value' => 'account:' . $id,
                    'label' => $name,
                    'group' => 'Akun Vendor',
                ];
            }
        }

        return $options;
    }

    private function validateUserSourceFieldValues(array $activeFields, array $fieldValues): array
    {
        $errors = [];
        foreach ($activeFields as $field) {
            $key = (string) ($field['field_key'] ?? '');
            $isPicField = in_array($key, ['pic_name', 'pic'], true);
            $dataSource = (string) ($field['data_source'] ?? 'manual');
            $isAccountField = $key === 'account';
            if ($dataSource !== 'team_users' && $dataSource !== 'account_sources' && !$isPicField && !$isAccountField) {
                continue;
            }
            if ($key === '' || !array_key_exists($key, $fieldValues)) {
                continue;
            }
            $value = trim((string) ($fieldValues[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $allowed = ($dataSource === 'account_sources' || $isAccountField)
                ? array_column($this->buildAccountSourceOptions($field), 'value')
                : array_column($this->buildUserSourceOptions($field), 'value');
            if (!in_array($value, $allowed, true)) {
                $errors[$key] = ($field['field_label'] ?? $key) . ' tidak valid.';
            }
        }
        return $errors;
    }
}
