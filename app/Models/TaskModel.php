<?php

namespace App\Models;

use App\Libraries\RichtextSanitizer;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * TaskModel — EAV Architecture
 *
 * Setiap task menyimpan nilai field-nya di tb_task_values (EAV).
 * Field yang punya `submission_col` diisi otomatis ke tb_submissions
 * saat task dibuat/diupdate dan field `setor` = 1 (true).
 */
class TaskModel extends Model
{
    protected $table      = 'tb_task';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['user_id', 'account_id', 'project_id', 'parent_id', 'status', 'progress', 'deadline', 'deleted_at', 'created_at', 'updated_at'];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    // ------------------------------------------------------------------
    // GET SINGLE TASK WITH ALL FIELD VALUES
    // ------------------------------------------------------------------
    public function getTaskWithFields(int $taskId): ?array
    {
        $task = $this->find($taskId);
        if (!$task) return null;

        $db = \Config\Database::connect();

        $taskPid = (int) ($task['project_id'] ?? 0);
        $valuesQ = $db->table('tb_task_values tv')
            ->select('f.field_key, f.field_label, f.type, f.options, f.submission_col, tv.value, tv.updated_at')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->where('tv.task_id', $taskId)
            ->where('f.status', 1)
            ->groupStart()
                ->where('f.scope', 'task')
                ->orWhere('f.scope', 'both')
            ->groupEnd();
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if ($taskPid > 0) {
                $valuesQ->where('f.project_id', $taskPid);
            } else {
                $valuesQ->where('f.project_id IS NULL', null, false);
            }
        }
        $values = $valuesQ->orderBy('f.order_no', 'ASC')
            ->get()
            ->getResultArray();

        $task['fields'] = [];
        foreach ($values as $v) {
            $task['fields'][$v['field_key']] = [
                'label'          => $v['field_label'],
                'type'           => $v['type'],
                'options'        => $v['options'] ? json_decode($v['options'], true) : [],
                'value'          => $v['value'],
                'submission_col' => $v['submission_col'],
                'updated_at'     => $v['updated_at'] ?? null,
            ];
        }

        // Attach submission only for internal tasks
        $task['submission'] = null;
        if (! $db->fieldExists('project_id', 'tb_task') || empty($task['project_id']) || (int) $task['project_id'] <= 0) {
            $task['submission'] = $db->table('tb_submissions')
                ->where('task_id', $taskId)
                ->get()->getRowArray();
        }

        return $task;
    }

    // ------------------------------------------------------------------
    // GET ALL TASKS WITH FIELD VALUES (optimised: 2 queries)
    // ------------------------------------------------------------------
    public function getTasksWithFields(
        array $filters = [],
        string $sortField = '',
        string $sortDir = 'DESC',
        ?int $userId = null,
        array $allowedVendorIds = []
    ): array
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('tb_task t')->select('t.*')->where('t.deleted_at IS NULL');
        $hasVendorCol = $db->fieldExists('account_id', 'tb_task');

        if ($userId !== null) {
            $builder->where('t.user_id', $userId);
        }

        if ($hasVendorCol && $allowedVendorIds !== []) {
            $builder->whereIn('t.account_id', $allowedVendorIds);
        }

        if ($hasVendorCol && isset($filters['vendor_account_id']) && $filters['vendor_account_id'] !== '') {
            $builder->where('t.account_id', (int) $filters['vendor_account_id']);
            unset($filters['vendor_account_id']);
        }

        if ($db->fieldExists('project_id', 'tb_task') && isset($filters['project_id']) && $filters['project_id'] !== '' && $filters['project_id'] !== null) {
            $builder->where('t.project_id', (int) $filters['project_id']);
            unset($filters['project_id']);
        }

        if ($db->fieldExists('parent_id', 'tb_task') && isset($filters['parent_id']) && $filters['parent_id'] !== '' && $filters['parent_id'] !== null) {
            $builder->where('t.parent_id', (int) $filters['parent_id']);
            unset($filters['parent_id']);
        }

        $fieldKeysToLoad = [];
        foreach ($filters as $fieldKey => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $fieldKeysToLoad[] = (string) $fieldKey;
        }
        if ($sortField !== '') {
            $fieldKeysToLoad[] = $sortField;
        }
        $fieldKeysToLoad = array_values(array_unique($fieldKeysToLoad));

        $fieldByKey = [];
        if ($fieldKeysToLoad !== []) {
            $fieldRows = $db->table('tb_fields')
                ->where('status', 1)
                ->whereIn('field_key', $fieldKeysToLoad)
                ->get()
                ->getResultArray();
            foreach ($fieldRows as $f) {
                $fieldByKey[(string) ($f['field_key'] ?? '')] = $f;
            }
        }

        // Filter by dynamic field value
        foreach ($filters as $fieldKey => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $field = $fieldByKey[(string) $fieldKey] ?? null;
            if ($field) {
                $alias = 'tv_' . preg_replace('/\W/', '_', (string) $fieldKey);
                $builder->join(
                    "tb_task_values {$alias}",
                    "{$alias}.task_id = t.id AND {$alias}.field_id = {$field['id']}",
                    'inner'
                );
                $builder->like("{$alias}.value", $value);
            }
        }

        // Sort by dynamic field
        if ($sortField !== '') {
            $field = $fieldByKey[$sortField] ?? null;
            if ($field) {
                $sortAlias = 'sort_' . $sortField;
                $builder->join(
                    "tb_task_values {$sortAlias}",
                    "{$sortAlias}.task_id = t.id AND {$sortAlias}.field_id = {$field['id']}",
                    'left'
                );
                $builder->orderBy("{$sortAlias}.value", $sortDir);
            }
        } else {
            $builder->orderBy('t.id', 'DESC');
        }

        $tasks = $builder->get()->getResultArray();
        if (empty($tasks)) return [];

        $taskIds = array_column($tasks, 'id');

        // Batch-fetch all values
        $allValues = $db->table('tb_task_values tv')
            ->select('tv.task_id, f.field_key, f.field_label, f.type, f.options, f.submission_col, tv.value, tv.updated_at')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->whereIn('tv.task_id', $taskIds)
            ->where('f.status', 1)
            ->groupStart()
                ->where('f.scope', 'task')
                ->orWhere('f.scope', 'both')
            ->groupEnd()
            ->orderBy('f.order_no', 'ASC')
            ->get()
            ->getResultArray();

        $byTask = [];
        foreach ($allValues as $v) {
            $byTask[$v['task_id']][$v['field_key']] = [
                'label'          => $v['field_label'],
                'type'           => $v['type'],
                'options'        => $v['options'] ? json_decode($v['options'], true) : [],
                'value'          => $v['value'],
                'submission_col' => $v['submission_col'],
                'updated_at'     => $v['updated_at'] ?? null,
            ];
        }

        // Batch-fetch submission setor status
        $submissions = $db->table('tb_submissions')
            ->select('task_id, id as submission_id')
            ->whereIn('task_id', $taskIds)
            ->get()->getResultArray();
        $submissionByTask = array_column($submissions, null, 'task_id');

        foreach ($tasks as &$task) {
            $task['fields']      = $byTask[$task['id']] ?? [];
            $task['submission']  = $submissionByTask[$task['id']] ?? null;
        }

        return $tasks;
    }

    // ------------------------------------------------------------------
    // INDEX PAGE: Filter + pagination in DB (no in-memory filtering)
    // Returns:
    // - items: tasks for current page (with fields + submission status)
    // - total: total tasks for pager
    // - statsAvgProgress / statsOverdue: aggregates over the filtered result set
    // ------------------------------------------------------------------
    public function getTasksIndexPage(
        array $eavFilters,
        array $coreFilters,
        int $page,
        int $perPage,
        ?int $userId = null,
        array $allowedVendorIds = []
    ): array {
        $db = \Config\Database::connect();

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $totalBuilder = $db->table('tb_task t')->where('t.deleted_at IS NULL');
        $this->applyTasksIndexFilters($totalBuilder, $db, $eavFilters, $coreFilters, $userId, $allowedVendorIds);
        $total = (int) $totalBuilder->countAllResults();

        $today = date('Y-m-d');
        $statsBuilder = $db->table('tb_task t')->where('t.deleted_at IS NULL');
        $this->applyTasksIndexFilters($statsBuilder, $db, $eavFilters, $coreFilters, $userId, $allowedVendorIds);
        $statsRow = $statsBuilder
            ->select(
                "COALESCE(AVG(t.progress), 0) AS avg_progress, " .
                "SUM(CASE WHEN t.deadline IS NOT NULL AND t.deadline < " . $db->escape($today) . " THEN 1 ELSE 0 END) AS overdue",
                false
            )
            ->get()
            ->getRowArray();

        $statsAvgProgress = (int) round((float) ($statsRow['avg_progress'] ?? 0));
        $statsOverdue     = (int) ($statsRow['overdue'] ?? 0);

        $listBuilder = $db->table('tb_task t')->select('t.*')->where('t.deleted_at IS NULL');
        $this->applyTasksIndexFilters($listBuilder, $db, $eavFilters, $coreFilters, $userId, $allowedVendorIds);
        $tasks = $listBuilder
            ->orderBy('t.id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        if ($tasks === []) {
            return [
                'items'            => [],
                'total'            => 0,
                'page'             => $page,
                'perPage'          => $perPage,
                'totalPages'       => 0,
                'statsAvgProgress' => 0,
                'statsOverdue'     => 0,
            ];
        }

        $taskIds = array_column($tasks, 'id');

        $fieldJoinProjectId = null;
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if (! empty($coreFilters['internal_only'])) {
                $fieldJoinProjectId = 0;
            } elseif (($coreFilters['project_id'] ?? '') !== '') {
                $fieldJoinProjectId = (int) $coreFilters['project_id'];
            }
        }

        $allV = $db->table('tb_task_values tv')
            ->select('tv.task_id, f.field_key, f.field_label, f.type, f.options, f.submission_col, tv.value, tv.updated_at')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->whereIn('tv.task_id', $taskIds)
            ->where('f.status', 1)
            ->groupStart()
                ->where('f.scope', 'task')
                ->orWhere('f.scope', 'both')
            ->groupEnd();
        if ($fieldJoinProjectId !== null) {
            if ($fieldJoinProjectId === 0) {
                $allV->where('f.project_id IS NULL', null, false);
            } else {
                $allV->where('f.project_id', $fieldJoinProjectId);
            }
        }
        $allValues = $allV->orderBy('f.order_no', 'ASC')
            ->get()
            ->getResultArray();

        $byTask = [];
        foreach ($allValues as $v) {
            $byTask[$v['task_id']][$v['field_key']] = [
                'label'          => $v['field_label'],
                'type'           => $v['type'],
                'options'        => $v['options'] ? json_decode($v['options'], true) : [],
                'value'          => $v['value'],
                'submission_col' => $v['submission_col'],
                'updated_at'     => $v['updated_at'] ?? null,
            ];
        }

        $subs = $db->table('tb_submissions')
            ->select('task_id, id as submission_id')
            ->whereIn('task_id', $taskIds)
            ->get()
            ->getResultArray();
        $submissionByTask = array_column($subs, null, 'task_id');

        foreach ($tasks as &$task) {
            $task['fields']     = $byTask[$task['id']] ?? [];
            $task['submission'] = $submissionByTask[$task['id']] ?? null;
        }
        unset($task);

        $totalPages = (int) ceil($total / $perPage);

        return [
            'items'            => $tasks,
            'total'            => $total,
            'page'             => $page,
            'perPage'          => $perPage,
            'totalPages'       => $totalPages,
            'statsAvgProgress' => $statsAvgProgress,
            'statsOverdue'     => $statsOverdue,
        ];
    }

    // ------------------------------------------------------------------
    // Set satu nilai EAV (untuk bulk update) — sinkron submission jika perlu
    // ------------------------------------------------------------------
    public function setTaskFieldValue(int $taskId, string $fieldKey, string $value): bool
    {
        $db = \Config\Database::connect();

        $task = $this->find($taskId);
        if (! $task) {
            return false;
        }
        $tp = (int) ($task['project_id'] ?? 0);
        $fieldModel = new FieldModel();
        $field      = $fieldModel->resolveFieldRowForTask($tp, $fieldKey);

        if (! $field) {
            return false;
        }

        if (($field['type'] ?? '') === 'richtext') {
            $value = RichtextSanitizer::sanitizeEditorJsJson($value);
        }

        $this->upsertTaskValueRow($db, $taskId, (int) $field['id'], $value);

        if (!empty($field['submission_col']) || ($field['field_key'] ?? '') === 'setor') {
            $this->syncSubmissionFromTaskValues($taskId);
        }

        if (($field['field_key'] ?? '') === 'account'
            || (($field['data_source'] ?? '') === 'account_sources' && ($field['submission_col'] ?? '') === 'account')) {
            $this->syncCoreAccountIdFromAccountField($taskId);
        }

        return true;
    }

    // ------------------------------------------------------------------
    // CREATE TASK + FIELD VALUES + SUBMISSION SYNC (transaction)
    // ------------------------------------------------------------------
    public function createTaskWithFields(
        int $userId,
        string $status,
        array $fieldValues,
        ?int $vendorAccountId = null,
        ?int $projectId = null,
        ?int $parentId = null
    ): int|false
    {
        $db = \Config\Database::connect();
        $db->transStart();
        $hasVendorCol = $db->fieldExists('account_id', 'tb_task');

        $payload = [
            'user_id'           => $userId,
            'status'            => $status,
        ];
        if ($hasVendorCol) {
            $payload['account_id'] = $vendorAccountId;
        }
        if ($db->fieldExists('project_id', 'tb_task') && $projectId !== null && $projectId > 0) {
            $payload['project_id'] = $projectId;
        }
        if ($db->fieldExists('parent_id', 'tb_task') && $parentId !== null && $parentId > 0) {
            $payload['parent_id'] = $parentId;
        }

        $taskId = $this->insert($payload, true);
        if (!$taskId) {
            $db->transRollback();
            return false;
        }

        $scopePid = ($projectId !== null && $projectId > 0) ? $projectId : null;
        $this->_saveFieldValues($taskId, $fieldValues, $db, $scopePid);
        $this->_syncSubmission($taskId, $fieldValues, $db);
        $this->syncCoreAccountIdFromAccountField($taskId);

        $db->transComplete();
        return $db->transStatus() ? $taskId : false;
    }

    // ------------------------------------------------------------------
    // UPDATE TASK + FIELD VALUES + SUBMISSION SYNC (transaction)
    // ------------------------------------------------------------------
    /**
     * @param array<string, mixed>|null $taskCore Optional tb_task columns: project_id, parent_id (omit keys to leave unchanged)
     */
    public function updateTaskWithFields(int $taskId, string $status, array $fieldValues, ?array $taskCore = null): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $core = ['status' => $status];
        if ($taskCore !== null) {
            if ($db->fieldExists('project_id', 'tb_task') && array_key_exists('project_id', $taskCore)) {
                $p = $taskCore['project_id'];
                $core['project_id'] = ($p !== null && $p !== '' && (int) $p > 0) ? (int) $p : null;
            }
            if ($db->fieldExists('parent_id', 'tb_task') && array_key_exists('parent_id', $taskCore)) {
                $p = $taskCore['parent_id'];
                $core['parent_id'] = ($p !== null && $p !== '' && (int) $p > 0) ? (int) $p : null;
            }
        }

        $this->update($taskId, $core);

        $rowAfter     = $this->find($taskId);
        $scopePid     = (int) ($rowAfter['project_id'] ?? 0);
        $fieldsScope  = $scopePid > 0 ? $scopePid : null;

        $this->_saveFieldValues($taskId, $fieldValues, $db, $fieldsScope);
        $this->_syncSubmission($taskId, $fieldValues, $db);
        $this->syncCoreAccountIdFromAccountField($taskId);

        $db->transComplete();
        return $db->transStatus();
    }

    // ------------------------------------------------------------------
    // PUBLIC: Sync submission from persisted EAV values
    // Optional $overrides can inject transient values (e.g. link_setor).
    // ------------------------------------------------------------------
    public function syncSubmissionFromTaskValues(int $taskId, array $overrides = [], bool $useTransaction = true): bool
    {
        $db = \Config\Database::connect();
        $rows = $db->table('tb_task_values tv')
            ->select('f.field_key, tv.value')
            ->join('tb_fields f', 'f.id = tv.field_id', 'inner')
            ->where('tv.task_id', $taskId)
            ->get()
            ->getResultArray();

        $fieldValues = [];
        foreach ($rows as $row) {
            $fieldValues[$row['field_key']] = $row['value'];
        }

        foreach ($overrides as $key => $value) {
            $fieldValues[$key] = $value;
        }

        if ($useTransaction) {
            $db->transStart();
        }
        $this->_syncSubmission($taskId, $fieldValues, $db);
        if ($useTransaction) {
            $db->transComplete();

            return $db->transStatus();
        }

        return true;
    }

    /**
     * Soft-deleted tasks with DB pagination (avoid loading all rows into memory).
     *
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function getTrashTasksPage(int $page, int $perPage, ?int $scopeUserId = null): array
    {
        $db      = \Config\Database::connect();
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $countB = $db->table('tb_task')->where('deleted_at IS NOT NULL', null, false);
        if ($db->fieldExists('project_id', 'tb_task')) {
            $countB->where('project_id IS NULL', null, false);
        }
        if ($scopeUserId !== null) {
            $countB->where('user_id', $scopeUserId);
        }
        $total = (int) $countB->countAllResults();

        $listB = $db->table('tb_task')->where('deleted_at IS NOT NULL', null, false);
        if ($db->fieldExists('project_id', 'tb_task')) {
            $listB->where('project_id IS NULL', null, false);
        }
        if ($scopeUserId !== null) {
            $listB->where('user_id', $scopeUserId);
        }
        $items = $listB
            ->orderBy('deleted_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Tasks with submission row — paginated in DB (avoid OOM on large datasets).
     *
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function getTasksWithSubmissionPage(int $page, int $perPage): array
    {
        $db      = \Config\Database::connect();
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = (int) $db->table('tb_task t')
            ->join('tb_submissions s', 's.task_id = t.id', 'inner')
            ->where('t.deleted_at IS NULL')
            ->countAllResults();

        $ids = $db->table('tb_task t')
            ->select('t.id')
            ->join('tb_submissions s', 's.task_id = t.id', 'inner')
            ->where('t.deleted_at IS NULL')
            ->orderBy('s.id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();
        $idList = array_values(array_filter(array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $ids)));

        if ($idList === []) {
            return ['items' => [], 'total' => $total];
        }

        $tv = $db->prefixTable('tb_task_values');
        $tf = $db->prefixTable('tb_fields');
        $setorTs = "(SELECT tv2.updated_at FROM {$tv} tv2 INNER JOIN {$tf} f2 ON f2.id = tv2.field_id WHERE tv2.task_id = t.id AND f2.field_key = 'setor' LIMIT 1) AS setor_updated_at";

        $rows = $db->table('tb_task t')
            ->select('t.*, t.account_id as task_account_id, s.id as submission_id, s.product_name, s.category, s.pic_name, s.account, s.link_setor, s.date as submission_date, ' . $setorTs, false)
            ->join('tb_submissions s', 's.task_id = t.id', 'inner')
            ->whereIn('t.id', $idList)
            ->get()
            ->getResultArray();

        $pos = array_flip($idList);
        usort($rows, static function (array $a, array $b) use ($pos): int {
            $ia = $pos[(int) ($a['id'] ?? 0)] ?? 0;
            $ib = $pos[(int) ($b['id'] ?? 0)] ?? 0;

            return $ia <=> $ib;
        });

        return ['items' => $rows, 'total' => $total];
    }

    // ------------------------------------------------------------------
    // PRIVATE: Upsert field values in EAV
    // ------------------------------------------------------------------
    /**
     * @param int|null $fieldsProjectScope tb_fields.project_id untuk task ini; null = definisi internal (project_id IS NULL).
     */
    private function _saveFieldValues(int $taskId, array $fieldValues, BaseConnection $db, ?int $fieldsProjectScope = null): void
    {
        $b = $db->table('tb_fields')->where('status', 1);
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if ($fieldsProjectScope !== null && $fieldsProjectScope > 0) {
                $b->where('project_id', $fieldsProjectScope);
            } else {
                $b->where('project_id IS NULL', null, false);
            }
        }
        $fields = $b->get()->getResultArray();

        $fieldMap = array_column($fields, 'id', 'field_key');
        $typeByKey = [];
        foreach ($fields as $f) {
            $typeByKey[$f['field_key']] = (string) ($f['type'] ?? '');
        }

        foreach ($fieldValues as $key => $value) {
            if (!isset($fieldMap[$key])) {
                continue;
            }

            if (($typeByKey[$key] ?? '') === 'richtext' && is_string($value)) {
                $value = RichtextSanitizer::sanitizeEditorJsJson($value);
            }

            $fieldId = (int) $fieldMap[$key];
            $this->upsertTaskValueRow($db, $taskId, $fieldId, $value);
        }
    }

    /**
     * Atomic upsert for EAV row (requires UNIQUE(task_id, field_id); see migration TaskValuesUniqueTaskField).
     */
    private function upsertTaskValueRow(BaseConnection $db, int $taskId, int $fieldId, mixed $value): void
    {
        $now = date('Y-m-d H:i:s');
        $tbl = $db->escapeIdentifiers($db->prefixTable('tb_task_values'));
        $db->query(
            "INSERT INTO {$tbl} (task_id, field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = ?, updated_at = ?",
            [$taskId, $fieldId, $value, $now, $now, $value, $now]
        );
    }

    // ------------------------------------------------------------------
    // PRIVATE: Sync EAV values → tb_submissions
    //
    // Logic:
    //  - Jika field `setor` = 1 (true/checked), buat atau update tb_submissions
    //  - Jika `setor` = 0, hapus submission yang terkait (opsional: bisa dinonaktifkan)
    //  - Mapping: field.submission_col → kolom di tb_submissions
    // ------------------------------------------------------------------
    private function _syncSubmission(int $taskId, array $fieldValues, $db): void
    {
        // Check apakah setor di-centang
        $setorVal = $fieldValues['setor'] ?? '0';
        $isSetor  = in_array($setorVal, ['1', 'true', 'on', true], true);

        if (!$isSetor) {
            // Jika setor di-uncheck, hapus submission yang ada
            $db->table('tb_submissions')->where('task_id', $taskId)->delete();
            return;
        }

        // Load existing submission (to preserve columns like link_setor that aren't in form)
        $existing = $db->table('tb_submissions')
            ->where('task_id', $taskId)
            ->get()->getRowArray();

        // Start with existing data so non-EAV columns (e.g. link_setor) are preserved
        $submissionData = $existing
            ? array_filter($existing, fn($v) => $v !== null)
            : [];

        // Always override these
        $submissionData['task_id']    = $taskId;
        $submissionData['updated_at'] = date('Y-m-d H:i:s');
        unset($submissionData['id'], $submissionData['created_at']);

        // Ambil semua field yang punya submission_col
        $fields = $db->table('tb_fields')
            ->where('status', 1)
            ->where('submission_col IS NOT NULL')
            ->where('submission_col !=', '')
            ->get()->getResultArray();

        // Overwrite only EAV-mapped columns
        foreach ($fields as $field) {
            $key   = $field['field_key'];
            $col   = $field['submission_col'];
            $value = $fieldValues[$key] ?? null;

            if ($value !== null && $value !== '') {
                if (($field['data_source'] ?? 'manual') === 'team_users' || in_array((string) ($field['field_key'] ?? ''), ['pic_name', 'pic'], true)) {
                    $uid = (int) $value;
                    if ($uid > 0) {
                        $userRow = $db->table('tb_users')
                            ->select('nickname, username')
                            ->where('id', $uid)
                            ->get()->getRowArray();
                        if ($userRow) {
                            $display = trim((string) ($userRow['nickname'] ?? ''));
                            if ($display === '') {
                                $display = trim((string) ($userRow['username'] ?? ''));
                            }
                            if ($display !== '') {
                                $value = $display;
                            }
                        }
                    }
                } elseif (($field['data_source'] ?? 'manual') === 'account_sources') {
                    $value = $this->resolveAccountSourceLabel((string) $value, $db);
                }
                $submissionData[$col] = $value;
            }
        }

        // Direct modal overrides for core submission columns.
        // This ensures setor modal updates (especially link_setor) persist
        // even when there is no explicit field mapping for those columns.
        $directCols = ['product_name', 'category', 'link_setor', 'account', 'pic_name', 'date'];
        foreach ($directCols as $col) {
            if (array_key_exists($col, $fieldValues) && $fieldValues[$col] !== null) {
                $directValue = is_string($fieldValues[$col])
                    ? trim($fieldValues[$col])
                    : $fieldValues[$col];
                if ($col === 'account' && is_string($directValue)) {
                    $directValue = $this->resolveAccountSourceLabel($directValue, $db);
                }
                $submissionData[$col] = $directValue;
            }
        }

        if ($existing) {
            $db->table('tb_submissions')
                ->where('task_id', $taskId)
                ->update($submissionData);
        } else {
            $submissionData['created_at'] = date('Y-m-d H:i:s');
            $db->table('tb_submissions')->insert($submissionData);
        }
    }

    // ------------------------------------------------------------------
    // COUNT by status
    // ------------------------------------------------------------------
    public function countByStatus(?int $userId = null): array
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('tb_task')
            ->select('status, COUNT(*) as total')
            ->where('deleted_at IS NULL');
        if ($userId !== null) {
            $builder->where('user_id', $userId);
        }
        return $builder->groupBy('status')->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // COUNT setor (tasks yang sudah disubmit)
    // ------------------------------------------------------------------
    public function countSetor(): int
    {
        $db = \Config\Database::connect();
        return (int) $db->table('tb_submissions')->countAllResults();
    }

    /**
     * Resolve raw EAV account value (e.g. account:12, office:1, vendor:2) to tb_accounts.id.
     */
    public function resolveAccountIdFromRawValue(string $value, BaseConnection $db): ?int
    {
        $raw = trim($value);
        if ($raw === '' || strpos($raw, ':') === false || ! $db->tableExists('tb_accounts')) {
            return null;
        }
        [$source, $idRaw] = explode(':', $raw, 2);
        $source = strtolower(trim($source));
        $id = (int) $idRaw;
        if ($id <= 0) {
            return null;
        }

        if ($source === 'account') {
            $row = $db->table('tb_accounts')->select('id')->where('id', $id)->get()->getRowArray();

            return $row ? (int) $row['id'] : null;
        }
        if ($source === 'office') {
            $row = $db->table('tb_accounts')->select('id')->where('legacy_office_id', $id)->get()->getRowArray();

            return $row ? (int) $row['id'] : null;
        }
        if ($source === 'vendor') {
            $row = $db->table('tb_accounts')->select('id')->where('legacy_vendor_id', $id)->get()->getRowArray();

            return $row ? (int) $row['id'] : null;
        }

        return null;
    }

    /**
     * Set tb_task.account_id from persisted EAV "account" field when resolvable.
     * When $overwriteExisting is false, skips tasks that already have account_id (backfill-only).
     */
    public function syncCoreAccountIdFromAccountField(int $taskId, bool $overwriteExisting = true): bool
    {
        $db = \Config\Database::connect();
        if (! $db->fieldExists('account_id', 'tb_task')) {
            return false;
        }

        if (! $overwriteExisting) {
            $task = $this->find($taskId);
            if ($task && isset($task['account_id']) && $task['account_id'] !== null && (int) $task['account_id'] > 0) {
                return false;
            }
        }

        $raw = $this->getRawAccountFieldValueForSync($db, $taskId);
        $resolved = $this->resolveAccountIdFromRawValue($raw, $db);
        if ($resolved === null) {
            return false;
        }

        return $this->update($taskId, ['account_id' => $resolved]);
    }

    /** Raw stored EAV value for account (CLI / diagnostics). */
    public function getRawAccountFieldValueForTask(int $taskId): string
    {
        return $this->getRawAccountFieldValueForSync(\Config\Database::connect(), $taskId);
    }

    private function getRawAccountFieldValueForSync(BaseConnection $db, int $taskId): string
    {
        $fq = $db->table('tb_fields')
            ->where('field_key', 'account')
            ->where('status', 1);
        if ($db->fieldExists('project_id', 'tb_fields')) {
            $fq->where('project_id IS NULL', null, false);
        }
        $field = $fq->get()->getRowArray();
        if ($field) {
            $row = $db->table('tb_task_values')
                ->where('task_id', $taskId)
                ->where('field_id', (int) $field['id'])
                ->get()->getRowArray();
            if ($row) {
                $v = trim((string) ($row['value'] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        $fq2 = $db->table('tb_fields')
            ->where('data_source', 'account_sources')
            ->where('submission_col', 'account')
            ->where('status', 1);
        if ($db->fieldExists('project_id', 'tb_fields')) {
            $fq2->where('project_id IS NULL', null, false);
        }
        $field = $fq2->get()->getRowArray();
        if (! $field) {
            return '';
        }
        $row = $db->table('tb_task_values')
            ->where('task_id', $taskId)
            ->where('field_id', (int) $field['id'])
            ->get()->getRowArray();

        return $row ? trim((string) ($row['value'] ?? '')) : '';
    }

    private function resolveAccountSourceLabel(string $value, $db): string
    {
        $raw = trim($value);
        if ($raw === '' || strpos($raw, ':') === false) {
            return $raw;
        }
        [$source, $idRaw] = explode(':', $raw, 2);
        $source = strtolower(trim($source));
        $id = (int) $idRaw;
        if ($id <= 0) {
            return $raw;
        }

        if ($source === 'account' && $db->tableExists('tb_accounts')) {
            $row = $db->table('tb_accounts')->select('name')->where('id', $id)->get()->getRowArray();
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        if ($source === 'office' && $db->tableExists('tb_accounts')) {
            $row = $db->table('tb_accounts')->select('name')->where('legacy_office_id', $id)->get()->getRowArray();
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        if ($source === 'vendor' && $db->tableExists('tb_accounts')) {
            $row = $db->table('tb_accounts')->select('name')->where('legacy_vendor_id', $id)->get()->getRowArray();
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        return $raw;
    }

    /**
     * KPI ringkas untuk dashboard; scope sama dengan Tasks::index (tanpa filter EAV/core).
     *
     * @return array{
     *   total:int,
     *   by_status: array<string,int>,
     *   overdue:int,
     *   avg_progress:int,
     *   with_submission:int
     * }
     */
    public function getDashboardSummary(?int $userId, array $allowedVendorIds): array
    {
        $db    = \Config\Database::connect();
        $today = date('Y-m-d');

        $byStatus = ['pending' => 0, 'on_progress' => 0, 'done' => 0, 'cancelled' => 0];
        $rows     = $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->select('t.status, COUNT(*) AS c')
            ->groupBy('t.status')
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            $st = (string) ($row['status'] ?? '');
            if (isset($byStatus[$st])) {
                $byStatus[$st] = (int) ($row['c'] ?? 0);
            }
        }
        $total = (int) $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)->countAllResults();

        $overdue = (int) $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->where("t.deadline IS NOT NULL AND t.deadline < " . $db->escape($today), null, false)
            ->countAllResults();

        $avgRow = $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->select('COALESCE(AVG(t.progress), 0) AS avg_p', false)
            ->get()
            ->getRowArray();
        $avgProgress = (int) round((float) ($avgRow['avg_p'] ?? 0));

        $withSubmission = (int) $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->where("EXISTS (SELECT 1 FROM tb_submissions s WHERE s.task_id = t.id)", null, false)
            ->countAllResults();

        return [
            'total'           => $total,
            'by_status'       => $byStatus,
            'overdue'         => $overdue,
            'avg_progress'    => $avgProgress,
            'with_submission' => $withSubmission,
        ];
    }

    /**
     * Task teratas yang overdue (deadline &lt; hari ini), untuk blok ringkasan.
     *
     * @return list<array{id:int,deadline:?string,status:string,progress:int}>
     */
    public function getDashboardOverduePreview(?int $userId, array $allowedVendorIds, int $limit = 5): array
    {
        $db    = \Config\Database::connect();
        $today = date('Y-m-d');
        $limit = max(1, min(20, $limit));

        return $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->select('t.id, t.deadline, t.status, t.progress')
            ->where("t.deadline IS NOT NULL AND t.deadline < " . $db->escape($today), null, false)
            ->where('t.status !=', 'done')
            ->orderBy('t.deadline', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Task dibuat vs diselesaikan (status done + updated) dalam bulan kalender berjalan.
     *
     * @return array{created_this_month:int, completed_this_month:int}
     */
    public function getDashboardMonthActivity(?int $userId, array $allowedVendorIds): array
    {
        $db    = \Config\Database::connect();
        $start = date('Y-m-01 00:00:00');
        $next  = date('Y-m-d H:i:s', strtotime('first day of next month'));

        $created = (int) $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->where('t.created_at >=', $start)
            ->where('t.created_at <', $next)
            ->countAllResults();

        $completed = (int) $this->dashboardBaseBuilder($db, $userId, $allowedVendorIds)
            ->where('t.status', 'done')
            ->where('t.updated_at >=', $start)
            ->where('t.updated_at <', $next)
            ->countAllResults();

        return [
            'created_this_month'   => $created,
            'completed_this_month' => $completed,
        ];
    }

    /**
     * Progress per anggota tim dalam scope dashboard: total task, rata-rata progress, selesai bulan ini.
     *
     * @return list<array{user_id:int,nickname:?string,username:?string,total_tasks:int,avg_progress:float,done_this_month:int}>
     */
    public function getDashboardTeamMonthProgress(?int $userId, array $allowedVendorIds, int $limit = 10): array
    {
        $db    = \Config\Database::connect();
        $start = date('Y-m-01 00:00:00');
        $next  = date('Y-m-d H:i:s', strtotime('first day of next month'));
        $limit = max(1, min(20, $limit));

        $builder = $db->table('tb_task t')
            ->select(
                't.user_id, MAX(u.nickname) AS nickname, MAX(u.username) AS username, '
                . 'COUNT(t.id) AS total_tasks, AVG(COALESCE(t.progress, 0)) AS avg_progress',
                false
            )
            ->select(
                'SUM(CASE WHEN t.status = \'done\' AND t.updated_at >= '
                . $db->escape($start) . ' AND t.updated_at < ' . $db->escape($next)
                . ' THEN 1 ELSE 0 END) AS done_this_month',
                false
            )
            ->join('tb_users u', 'u.id = t.user_id', 'left')
            ->where('t.deleted_at IS NULL')
            ->groupBy('t.user_id')
            ->orderBy('done_this_month', 'DESC')
            ->orderBy('total_tasks', 'DESC')
            ->limit($limit);

        $this->applyTasksIndexFilters($builder, $db, [], ['internal_only' => true], $userId, $allowedVendorIds);

        return $builder->get()->getResultArray();
    }

    private function dashboardBaseBuilder($db, ?int $userId, array $allowedVendorIds)
    {
        $builder = $db->table('tb_task t')->where('t.deleted_at IS NULL');
        $this->applyTasksIndexFilters($builder, $db, [], ['internal_only' => true], $userId, $allowedVendorIds);

        return $builder;
    }

    private function applyTasksIndexFilters(
        $builder,
        $db,
        array $eavFilters,
        array $coreFilters,
        ?int $userId,
        array $allowedVendorIds
    ): void {
        $hasVendorCol = $db->fieldExists('account_id', 'tb_task');

        if ($userId !== null) {
            $builder->where('t.user_id', $userId);
        }

        if ($hasVendorCol && $allowedVendorIds !== []) {
            $builder->whereIn('t.account_id', $allowedVendorIds);
        }

        if ($hasVendorCol && ($coreFilters['vendor_account_id'] ?? '') !== '') {
            $builder->where('t.account_id', (int) $coreFilters['vendor_account_id']);
        }

        if ($db->fieldExists('project_id', 'tb_task')) {
            if (! empty($coreFilters['internal_only'])) {
                $builder->where('t.project_id IS NULL', null, false);
            } elseif (($coreFilters['project_id'] ?? '') !== '') {
                $builder->where('t.project_id', (int) $coreFilters['project_id']);
            }
        }

        if (($coreFilters['status'] ?? '') !== '') {
            $builder->where('t.status', (string) $coreFilters['status']);
        }

        $setor = (string) ($coreFilters['setor'] ?? '');
        if ($setor === '1') {
            $builder->where("EXISTS (SELECT 1 FROM tb_submissions s WHERE s.task_id = t.id)", null, false);
        } elseif ($setor === '0') {
            $builder->where("NOT EXISTS (SELECT 1 FROM tb_submissions s WHERE s.task_id = t.id)", null, false);
        }

        $progressFilter = (string) ($coreFilters['progress_filter'] ?? '');
        if ($progressFilter === 'not_started') {
            $builder->where('t.progress', 0);
        } elseif ($progressFilter === 'in_progress') {
            $builder->where('t.progress >', 0)->where('t.progress <', 100);
        } elseif ($progressFilter === 'done') {
            $builder->where('t.progress', 100);
        }

        $deadlineFilter = (string) ($coreFilters['deadline_filter'] ?? '');
        $today = date('Y-m-d');
        if ($deadlineFilter === 'overdue') {
            $builder->where("t.deadline IS NOT NULL AND t.deadline < " . $db->escape($today), null, false);
        } elseif ($deadlineFilter === 'this_week') {
            $weekEnd = date('Y-m-d', strtotime('+7 days'));
            $builder->where("t.deadline IS NOT NULL AND t.deadline >= " . $db->escape($today) . " AND t.deadline <= " . $db->escape($weekEnd), null, false);
        } elseif ($deadlineFilter === 'no_deadline') {
            $builder->where("t.deadline IS NULL", null, false);
        }

        // Resolve field_ids in one query, then filter using EXISTS per filter.
        $normalized = [];
        foreach ($eavFilters as $key => $val) {
            $k = trim((string) $key);
            if ($k === '') continue;
            if ($val === '' || $val === null) continue;
            $normalized[$k] = (string) $val;
        }
        if ($normalized !== []) {
            $fb = $db->table('tb_fields')
                ->select('id, field_key')
                ->where('status', 1)
                ->whereIn('field_key', array_keys($normalized));
            if ($db->fieldExists('project_id', 'tb_fields')) {
                if (! empty($coreFilters['internal_only'])) {
                    $fb->where('project_id IS NULL', null, false);
                } elseif (($coreFilters['project_id'] ?? '') !== '') {
                    $fb->where('project_id', (int) $coreFilters['project_id']);
                }
            }
            $rows = $fb->get()->getResultArray();
            $idByKey = [];
            foreach ($rows as $r) {
                $idByKey[(string) $r['field_key']] = (int) $r['id'];
            }
            foreach ($normalized as $fieldKey => $value) {
                $fieldId = $idByKey[$fieldKey] ?? 0;
                if ($fieldId <= 0) {
                    continue;
                }
                $like = '%' . $value . '%';
                $builder->where(
                    "EXISTS (SELECT 1 FROM tb_task_values tv WHERE tv.task_id = t.id AND tv.field_id = " . (int) $fieldId . " AND tv.value LIKE " . $db->escape($like) . ")",
                    null,
                    false
                );
            }
        }
    }

    /**
     * Task relasi: cari kandidat dalam scope yang sama dengan picker (project / internal-only + member scope).
     *
     * @param array<string, mixed> $coreFilters internal_only atau project_id, sama seperti index task
     *
     * @return list<array{id:int, judul:string}>
     */
    public function searchRelationPicker(
        string $q,
        array $coreFilters,
        ?int $scopeUserId,
        array $allowedVendorIds,
        int $excludeTaskId,
        int $limit = 25
    ): array {
        $db    = \Config\Database::connect();
        $limit = max(1, min(50, $limit));
        $q     = trim($q);

        $builder = $db->table('tb_task t')->select('t.id')->where('t.deleted_at IS NULL');
        if ($excludeTaskId > 0) {
            $builder->where('t.id !=', $excludeTaskId);
        }
        $this->applyTasksIndexFilters($builder, $db, [], $coreFilters, $scopeUserId, $allowedVendorIds);

        if ($q !== '') {
            $fieldIds = $this->resolveJudulFieldIdsForPicker($db, $coreFilters);
            $builder->groupStart();
            $isDigitId = ctype_digit($q) && strlen($q) <= 9 && (int) $q > 0;
            if ($isDigitId) {
                $builder->where('t.id', (int) $q);
            }
            if ($fieldIds !== []) {
                $likePattern = '%' . $db->escapeLikeString($q) . '%';
                $parts       = [];
                foreach ($fieldIds as $fid) {
                    $parts[] = 'EXISTS (SELECT 1 FROM tb_task_values tv WHERE tv.task_id = t.id AND tv.field_id = ' . (int) $fid
                        . ' AND tv.value LIKE ' . $db->escape($likePattern) . ')';
                }
                $judulSql = '(' . implode(' OR ', $parts) . ')';
                if ($isDigitId) {
                    $builder->orWhere($judulSql, null, false);
                } else {
                    $builder->where($judulSql, null, false);
                }
            } elseif (! $isDigitId) {
                $builder->where('1 = 0', null, false);
            }
            $builder->groupEnd();
        }

        $rows = $builder->orderBy('t.id', 'DESC')->limit($limit)->get()->getResultArray();
        $ids  = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['id'], $rows)));
        if ($ids === []) {
            return [];
        }

        $judulById = $this->batchFetchJudulForTaskIds($db, $ids);
        $out       = [];
        foreach ($ids as $tid) {
            $out[] = [
                'id'    => $tid,
                'judul' => $judulById[$tid] ?? ('Task #' . $tid),
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function resolveJudulFieldIdsForPicker($db, array $coreFilters): array
    {
        $fb = $db->table('tb_fields')
            ->select('id')
            ->where('status', 1)
            ->where('field_key', 'judul');
        if ($db->fieldExists('project_id', 'tb_fields')) {
            if (! empty($coreFilters['internal_only'])) {
                $fb->where('project_id IS NULL', null, false);
            } elseif (($coreFilters['project_id'] ?? '') !== '') {
                $fb->where('project_id', (int) $coreFilters['project_id']);
            }
        }
        $rows = $fb->get()->getResultArray();

        return array_values(array_map(static fn(array $r): int => (int) $r['id'], $rows));
    }

    /**
     * @param list<int> $taskIds
     *
     * @return array<int, string> task_id => judul
     */
    private function batchFetchJudulForTaskIds($db, array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $rows = $db->table('tb_task_values tv')
            ->select('tv.task_id, tv.value')
            ->join('tb_task t', 't.id = tv.task_id')
            ->join('tb_fields f', 'f.id = tv.field_id', 'inner')
            ->where('f.field_key', 'judul')
            ->where('f.status', 1)
            ->groupStart()
            ->groupStart()
            ->where('t.project_id IS NULL', null, false)
            ->where('f.project_id IS NULL', null, false)
            ->groupEnd()
            ->orWhere('t.project_id = f.project_id', null, false)
            ->groupEnd()
            ->whereIn('tv.task_id', $taskIds)
            ->get()
            ->getResultArray();
        $map = [];
        foreach ($rows as $r) {
            $tid = (int) $r['task_id'];
            if (! isset($map[$tid])) {
                $map[$tid] = (string) $r['value'];
            }
        }

        return $map;
    }
}
