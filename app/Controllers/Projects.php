<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\FieldModel;
use App\Models\ProjectModel;
use App\Models\TaskModel;
use App\Models\VendorAllocationModel;
use CodeIgniter\Controller;

class Projects extends Controller
{
    protected $helpers = ['table_pagination', 'url'];

    private ProjectModel $projectModel;
    private ClientModel $clientModel;
    private TaskModel $taskModel;
    private VendorAllocationModel $vendorAllocationModel;

    public function __construct()
    {
        $this->projectModel          = new ProjectModel();
        $this->clientModel           = new ClientModel();
        $this->taskModel             = new TaskModel();
        $this->vendorAllocationModel = new VendorAllocationModel();
    }

    private function requirePerm(string $perm): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        if ($role === 'member') {
            redirect()->to('/tasks')->with('error', 'Akses ditolak.')->send();
            exit;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }

    /** Daftar/detail project: izinkan view_projects atau view_tasks (member tidak diblok total). */
    private function requireProjectReadAccess(): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        $perms = (array) (session()->get('user_perms') ?? []);
        if (in_array('view_projects', $perms, true) || in_array('view_tasks', $perms, true)) {
            return;
        }
        redirect()->to('/tasks')->with('error', 'Akses ditolak.')->send();
        exit;
    }

    private function requireAuth(): bool
    {
        return (bool) session()->get('user_id');
    }

    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requireProjectReadAccess();

        $projects = $this->projectModel->getWithClient();
        $clients  = $this->clientModel->getActiveList();

        return view('layouts/main', [
            'title'   => 'Projects',
            'content' => view('projects/index', [
                'projects' => $projects,
                'clients'  => $clients,
            ]),
        ]);
    }

    public function show(int $id): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requireProjectReadAccess();

        $project = $this->projectModel->getWithTaskStats($id);
        if (! $project) {
            return redirect()->to('/projects')->with('error', 'Project tidak ditemukan.');
        }

        $db               = \Config\Database::connect();
        $hasVendorAllocs  = $db->tableExists('tb_vendor_allocations');
        $currentRole      = session()->get('user_role') ?? 'member';
        $currentUserId    = (int) (session()->get('user_id') ?? 0);
        $scopeUserId      = ($currentRole === 'member') ? $currentUserId : null;
        $allowedVendorIds = [];
        if ($currentRole === 'member' && $hasVendorAllocs) {
            $rows = $this->vendorAllocationModel->where('user_id', $currentUserId)->findAll();
            $allowedVendorIds = array_values(array_filter(array_map(
                static fn(array $r): int => (int) ($r['account_id'] ?? 0),
                $rows
            ), static fn(int $v): bool => $v > 0));
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        $coreFilters = [
            'status'            => '',
            'setor'             => '',
            'progress_filter'   => '',
            'deadline_filter'   => '',
            'vendor_account_id' => '',
            'project_id'        => (string) $id,
        ];

        $result = $this->taskModel->getTasksIndexPage([], $coreFilters, $page, $perPage, $scopeUserId, $allowedVendorIds);

        $fieldModel = new \App\Models\FieldModel();
        $fieldModel->ensureDefaultProjectTaskFields($id);
        $fields = $fieldModel->getActiveFieldsForProject($id);
        $fields = array_map([$fieldModel, 'decodeOptions'], $fields);

        $perms               = session()->get('user_perms') ?? [];
        $canCreateProjectTask = ($currentRole === 'super_admin')
            || in_array('view_tasks', (array) $perms, true);

        $taskIds = array_values(array_filter(array_map(
            static fn(array $t): int => (int) ($t['id'] ?? 0),
            $result['items']
        ), static fn(int $tid): bool => $tid > 0));

        $workItemPrefix       = $this->workItemPrefixFromProjectName((string) ($project['name'] ?? ''));
        $titleFieldKey        = $this->titleFieldKeyFromProjectFields($fields);
        $assigneesByTaskId    = $this->loadWorkItemAssigneesGrouped($taskIds);
        $relationCountByTask = $this->loadWorkItemRelationCounts($taskIds);

        $initialPanelTaskId = $this->resolveInitialPanelTaskId($id);

        return view('layouts/main', [
            'title'   => $project['name'],
            'content' => view('projects/show', [
                'project'               => $project,
                'tasks'                 => $result['items'],
                'fields'                => $fields,
                'canCreateProjectTask'  => $canCreateProjectTask,
                'workItemPrefix'        => $workItemPrefix,
                'titleFieldKey'         => $titleFieldKey,
                'assigneesByTaskId'     => $assigneesByTaskId,
                'relationCountByTaskId' => $relationCountByTask,
                'workItemAssigneesEnabled' => $db->tableExists('tb_task_assignees'),
                'taskHasCreatedAt'      => $db->fieldExists('created_at', 'tb_task'),
                'taskHasUpdatedAt'      => $db->fieldExists('updated_at', 'tb_task'),
                'taskHasDeadline'       => $db->fieldExists('deadline', 'tb_task'),
                'pager'                 => [
                    'total'      => $result['total'],
                    'page'       => $result['page'],
                    'perPage'    => $result['perPage'],
                    'totalPages' => $result['totalPages'],
                ],
                'pagerQuery'   => table_pagination_query_params($this->request),
                'pagerUriPath' => '/projects/' . $id,
                'initialPanelTaskId'    => $initialPanelTaskId,
            ]),
        ]);
    }

    /**
     * Task ID dari ?task= harus milik project ini dan boleh dilihat user (selaras Tasks::canViewTaskInScope).
     */
    private function resolveInitialPanelTaskId(int $projectId): ?int
    {
        $raw = $this->request->getGet('task');
        if ($raw === null || $raw === '') {
            return null;
        }
        $tid = (int) $raw;
        if ($tid <= 0) {
            return null;
        }

        return $this->canViewTaskInProject($tid, $projectId) ? $tid : null;
    }

    private function canViewTaskInProject(int $taskId, int $projectId): bool
    {
        $task = $this->taskModel->find($taskId);
        if (! $task || ! empty($task['deleted_at'])) {
            return false;
        }
        if ((int) ($task['project_id'] ?? 0) !== $projectId) {
            return false;
        }
        $uid = (int) (session()->get('user_id') ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role !== 'member') {
            return true;
        }
        if ((int) ($task['user_id'] ?? 0) !== $uid) {
            return false;
        }
        $db = \Config\Database::connect();
        if ($db->tableExists('tb_vendor_allocations') && $db->fieldExists('account_id', 'tb_task')) {
            $rows = $this->vendorAllocationModel->where('user_id', $uid)->findAll();
            $allowedVendorIds = array_values(array_filter(array_map(
                static fn (array $r): int => (int) ($r['account_id'] ?? 0),
                $rows
            ), static fn (int $v): bool => $v > 0));
            if ($allowedVendorIds !== []) {
                $taskVendorId = (int) ($task['account_id'] ?? 0);
                if ($taskVendorId > 0 && ! in_array($taskVendorId, $allowedVendorIds, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function workItemPrefixFromProjectName(string $name): string
    {
        $s = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));

        return $s !== '' ? substr($s, 0, 4) : 'PRJ';
    }

    /**
     * @param list<array<string, mixed>> $fields Active project fields
     */
    private function titleFieldKeyFromProjectFields(array $fields): string
    {
        $editable = ['text', 'textarea', 'email', 'number'];

        // Field sistem `judul` dibuat otomatis (ensureDefaultProjectTaskFields) — selalu dipakai jika ada.
        foreach ($fields as $f) {
            if (($f['field_key'] ?? '') === FieldModel::RESERVED_TITLE_FIELD_KEY) {
                return FieldModel::RESERVED_TITLE_FIELD_KEY;
            }
        }
        foreach ($fields as $f) {
            if (in_array($f['type'] ?? '', $editable, true) && ($f['field_key'] ?? '') !== 'setor') {
                return (string) ($f['field_key'] ?? FieldModel::RESERVED_TITLE_FIELD_KEY);
            }
        }

        return FieldModel::RESERVED_TITLE_FIELD_KEY;
    }

    /**
     * @param list<int> $taskIds
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadWorkItemAssigneesGrouped(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_task_assignees')) {
            return [];
        }
        $rows = $db->table('tb_task_assignees ta')
            ->select('ta.task_id, ta.user_id')
            ->whereIn('ta.task_id', $taskIds)
            ->orderBy('ta.id', 'ASC')
            ->get()
            ->getResultArray();
        if ($rows === []) {
            return [];
        }
        $uids = array_values(array_unique(array_filter(
            array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $rows),
            static fn (int $id): bool => $id > 0
        )));
        $userModel = new \App\Models\UserModel();
        $users     = $uids === [] ? [] : $userModel->whereIn('id', $uids)->findAll();
        $byId      = [];
        foreach ($users as $u) {
            $byId[(int) ($u['id'] ?? 0)] = $u;
        }
        $out = [];
        foreach ($rows as $r) {
            $tid = (int) ($r['task_id'] ?? 0);
            $uid = (int) ($r['user_id'] ?? 0);
            if ($tid <= 0 || $uid <= 0) {
                continue;
            }
            $u = $byId[$uid] ?? null;
            $nick = (string) ($u['nickname'] ?? '');
            $out[$tid][] = [
                'user_id'  => $uid,
                'username' => (string) ($u['username'] ?? ''),
                'nickname' => $u === null ? ('User #' . $uid) : $nick,
                'avatar'   => $u['avatar'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $taskIds
     *
     * @return array<int, int>
     */
    private function loadWorkItemRelationCounts(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_task_relations')) {
            return [];
        }
        $rows = $db->table('tb_task_relations')
            ->select('task_id, COUNT(*) AS c', false)
            ->whereIn('task_id', $taskIds)
            ->groupBy('task_id')
            ->get()
            ->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['task_id']] = (int) $r['c'];
        }

        return $out;
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_projects');

        $data = [
            'client_id'   => $this->request->getPost('client_id') ?: null,
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'status'      => 'active',
        ];

        if (! $this->projectModel->save($data)) {
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan project.');
        }

        $newPid = (int) $this->projectModel->getInsertID();
        if ($newPid > 0) {
            $fieldModel = new \App\Models\FieldModel();
            $fieldModel->cloneInternalDefinitionsToProject($newPid);
            $fieldModel->ensureDefaultProjectTaskFields($newPid);
        }

        $clientId = $data['client_id'];
        $redirect = $clientId ? '/clients/' . $clientId : '/projects';

        return redirect()->to($redirect)->with('success', 'Project berhasil ditambahkan.');
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_projects');

        $post = $this->request->getPost();
        $payload = [
            'name'        => $post['name'] ?? null,
            'description' => $post['description'] ?? null,
            'status'      => $post['status'] ?? 'active',
        ];
        if (array_key_exists('client_id', $post)) {
            $payload['client_id'] = $post['client_id'] ?: null;
        }

        if (! $this->projectModel->update($id, $payload)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Gagal memperbarui project.',
                    'csrf'    => csrf_hash(),
                ]);
            }

            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui project.');
        }

        if ($this->request->isAJAX()) {
            $project = $this->projectModel->getWithTaskStats($id);
            if (! $project) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Project tidak ditemukan.',
                    'csrf'    => csrf_hash(),
                ]);
            }
            $stats   = $project['stats'] ?? [];
            $pageSub = ($project['client_name'] ?? '') . ' · ' . ($project['status'] ?? '')
                . ' · Work items: ' . (int) ($stats['total'] ?? 0)
                . ' (selesai ' . (int) ($stats['done_count'] ?? 0) . ', lewat tenggat ' . (int) ($stats['overdue_count'] ?? 0) . ')';

            return $this->response->setJSON([
                'success'      => true,
                'message'      => 'Project diperbarui.',
                'csrf'         => csrf_hash(),
                'stay_on_page' => true,
                'project'      => [
                    'name'        => (string) ($project['name'] ?? ''),
                    'description' => (string) ($project['description'] ?? ''),
                    'status'      => (string) ($project['status'] ?? ''),
                    'page_sub'    => $pageSub,
                ],
            ]);
        }

        return redirect()->to('/projects/' . $id)->with('success', 'Project diperbarui.');
    }

    public function delete(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_projects');

        $this->projectModel->delete($id);

        return redirect()->to('/projects')->with('success', 'Project dihapus.');
    }
}
