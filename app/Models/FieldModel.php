<?php

namespace App\Models;

use CodeIgniter\Model;

class FieldModel extends Model
{
    /**
     * Field key untuk isi utama work item (panel Editor.js). Urutan: pertama yang ada di task dipakai.
     */
    public const PRIMARY_BODY_FIELD_KEYS = ['deskripsi', 'description', 'body', 'keterangan'];

    /** Field judul work item / task — selalu ada, wajib diisi (disinkronkan otomatis). */
    public const RESERVED_TITLE_FIELD_KEY = 'judul';

    protected $table      = 'tb_fields';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'field_key', 'field_label', 'type', 'options', 'order_no',
        'status', 'is_required', 'validation_rule', 'default_value',
        'placeholder', 'help_text', 'submission_col', 'scope',
        'data_source', 'source_config', 'project_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'field_key'   => 'required|alpha_dash|max_length[100]',
        'field_label' => 'required|max_length[150]',
        'type'        => 'required|in_list[text,date,select,boolean,textarea,richtext,number,email]',
    ];

    protected $validationMessages = [
        'field_key' => [
            'alpha_dash' => 'Field key hanya boleh huruf, angka, underscore, atau dash.',
        ],
    ];

    public function hasProjectScopeColumn(): bool
    {
        return \Config\Database::connect()->fieldExists('project_id', 'tb_fields');
    }

    public function isFieldKeyAvailable(string $fieldKey, ?int $projectId, ?int $excludeId = null): bool
    {
        $db = \Config\Database::connect();
        $b  = $db->table('tb_fields')->where('field_key', $fieldKey);
        if ($excludeId !== null && $excludeId > 0) {
            $b->where('id !=', $excludeId);
        }
        if (! $this->hasProjectScopeColumn()) {
            return $b->countAllResults() === 0;
        }
        if ($projectId === null || $projectId <= 0) {
            $b->where('project_id IS NULL', null, false);
        } else {
            $b->where('project_id', $projectId);
        }

        return $b->countAllResults() === 0;
    }

    /**
     * @return array<int,int> map old_field_id => new_field_id
     */
    public function cloneInternalDefinitionsToProject(int $projectId): array
    {
        $db = \Config\Database::connect();
        if (! $this->hasProjectScopeColumn() || $projectId <= 0) {
            return [];
        }
        $hasAny = (int) $db->table('tb_fields')->where('project_id', $projectId)->countAllResults();
        if ($hasAny > 0) {
            return [];
        }
        $internal = $db->table('tb_fields')
            ->where('project_id IS NULL', null, false)
            ->orderBy('order_no', 'ASC')
            ->get()
            ->getResultArray();
        $map = [];
        $now = date('Y-m-d H:i:s');
        foreach ($internal as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            unset($row['id']);
            $row['project_id']     = $projectId;
            $row['submission_col'] = null;
            $row['scope']          = 'task';
            $row['created_at']     = $row['created_at'] ?? $now;
            $row['updated_at']     = $now;
            $db->table('tb_fields')->insert($row);
            $newId = (int) $db->insertID();
            if ($oldId > 0 && $newId > 0) {
                $map[$oldId] = $newId;
            }
        }

        return $map;
    }

    /**
     * Pastikan ada field `priority` (select) untuk project agar Work items bisa simpan prioritas via field-update.
     */
    public function ensurePrioritySelectForProject(int $projectId): bool
    {
        $db = \Config\Database::connect();
        if (! $this->hasProjectScopeColumn() || $projectId <= 0 || ! $db->tableExists('tb_fields')) {
            return false;
        }
        $exists = (int) $db->table('tb_fields')
            ->where('project_id', $projectId)
            ->where('field_key', 'priority')
            ->countAllResults();
        if ($exists > 0) {
            return true;
        }

        $maxRow = $db->table('tb_fields')
            ->selectMax('order_no')
            ->where('project_id', $projectId)
            ->get()
            ->getRowArray();
        $nextOrder = (int) ($maxRow['order_no'] ?? 0) + 1;
        $now       = date('Y-m-d H:i:s');
        $opts      = json_encode(['Low', 'Medium', 'High', 'Urgent'], JSON_UNESCAPED_UNICODE);

        $insert = [
            'field_key'    => 'priority',
            'field_label'  => 'Priority',
            'type'         => 'select',
            'options'      => $opts,
            'order_no'     => $nextOrder,
            'status'       => 1,
            'is_required'  => 0,
            'scope'        => 'task',
            'project_id'   => $projectId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        if ($db->fieldExists('data_source', 'tb_fields')) {
            $insert['data_source'] = 'manual';
        }
        if ($db->fieldExists('source_config', 'tb_fields')) {
            $insert['source_config'] = null;
        }
        if ($db->fieldExists('validation_rule', 'tb_fields')) {
            $insert['validation_rule'] = null;
        }
        if ($db->fieldExists('default_value', 'tb_fields')) {
            $insert['default_value'] = null;
        }
        if ($db->fieldExists('placeholder', 'tb_fields')) {
            $insert['placeholder'] = null;
        }
        if ($db->fieldExists('help_text', 'tb_fields')) {
            $insert['help_text'] = null;
        }
        if ($db->fieldExists('submission_col', 'tb_fields')) {
            $insert['submission_col'] = null;
        }

        try {
            $db->table('tb_fields')->insert($insert);

            return (int) $db->insertID() > 0;
        } catch (\Throwable $e) {
            log_message('warning', 'ensurePrioritySelectForProject: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Pastikan ada satu field isi utama (rich text `deskripsi`) jika belum ada alias
     * (description / body / keterangan) — supaya panel work item memetakan Editor ke kolom yang benar.
     */
    public function ensureDeskripsiRichtextForProject(int $projectId): bool
    {
        $db = \Config\Database::connect();
        if (! $this->hasProjectScopeColumn() || $projectId <= 0 || ! $db->tableExists('tb_fields')) {
            return false;
        }

        $keys = self::PRIMARY_BODY_FIELD_KEYS;
        $exists = (int) $db->table('tb_fields')
            ->where('project_id', $projectId)
            ->where('status', 1)
            ->whereIn('field_key', $keys)
            ->countAllResults();
        if ($exists > 0) {
            return true;
        }

        $maxRow = $db->table('tb_fields')
            ->selectMax('order_no')
            ->where('project_id', $projectId)
            ->get()
            ->getRowArray();
        $nextOrder = (int) ($maxRow['order_no'] ?? 0) + 1;
        $now       = date('Y-m-d H:i:s');

        $insert = [
            'field_key'    => 'deskripsi',
            'field_label'  => 'Deskripsi',
            'type'         => 'richtext',
            'options'      => null,
            'order_no'     => $nextOrder,
            'status'       => 1,
            'is_required'  => 0,
            'scope'        => 'task',
            'project_id'   => $projectId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        if ($db->fieldExists('data_source', 'tb_fields')) {
            $insert['data_source'] = 'manual';
        }
        if ($db->fieldExists('source_config', 'tb_fields')) {
            $insert['source_config'] = null;
        }
        if ($db->fieldExists('validation_rule', 'tb_fields')) {
            $insert['validation_rule'] = null;
        }
        if ($db->fieldExists('default_value', 'tb_fields')) {
            $insert['default_value'] = null;
        }
        if ($db->fieldExists('placeholder', 'tb_fields')) {
            $insert['placeholder'] = 'Tulis deskripsi work item…';
        }
        if ($db->fieldExists('help_text', 'tb_fields')) {
            $insert['help_text'] = null;
        }
        if ($db->fieldExists('submission_col', 'tb_fields')) {
            $insert['submission_col'] = null;
        }

        try {
            $db->table('tb_fields')->insert($insert);

            return (int) $db->insertID() > 0;
        } catch (\Throwable $e) {
            log_message('warning', 'ensureDeskripsiRichtextForProject: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Field bawaan untuk halaman work items proyek: judul wajib + prioritas + deskripsi utama.
     */
    public function ensureDefaultProjectTaskFields(int $projectId): void
    {
        if ($this->hasProjectScopeColumn()) {
            $this->ensureJudulFieldInternalGlobal();
        }
        if ($projectId > 0) {
            $this->ensureJudulFieldForProject($projectId);
        }
        $this->ensurePrioritySelectForProject($projectId);
        $this->ensureDeskripsiRichtextForProject($projectId);
    }

    /**
     * Pastikan field `judul` ada di definisi internal (project_id NULL) untuk task tanpa proyek.
     */
    public function ensureJudulFieldInternalGlobal(): bool
    {
        $db = \Config\Database::connect();
        if (! $this->hasProjectScopeColumn() || ! $db->tableExists('tb_fields')) {
            return false;
        }
        $existing = $db->table('tb_fields')
            ->where('field_key', self::RESERVED_TITLE_FIELD_KEY)
            ->where('project_id IS NULL', null, false)
            ->get()
            ->getRowArray();
        if ($existing) {
            return $this->finalizeJudulFieldRow($db, (int) $existing['id'], $existing);
        }

        return $this->insertJudulFieldRow($db, null);
    }

    /**
     * Pastikan field `judul` ada per proyek: teks/textarea, aktif, wajib.
     */
    public function ensureJudulFieldForProject(int $projectId): bool
    {
        $db = \Config\Database::connect();
        if (! $this->hasProjectScopeColumn() || $projectId <= 0 || ! $db->tableExists('tb_fields')) {
            return false;
        }
        $existing = $db->table('tb_fields')
            ->where('project_id', $projectId)
            ->where('field_key', self::RESERVED_TITLE_FIELD_KEY)
            ->get()
            ->getRowArray();
        if ($existing) {
            return $this->finalizeJudulFieldRow($db, (int) $existing['id'], $existing);
        }

        return $this->insertJudulFieldRow($db, $projectId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function finalizeJudulFieldRow($db, int $id, array $row): bool
    {
        $okTypes = ['text', 'textarea', 'email', 'number'];
        $type    = (string) ($row['type'] ?? 'text');
        $patch   = [];
        if (! in_array($type, $okTypes, true)) {
            $patch['type'] = 'text';
        }
        if ((int) ($row['status'] ?? 0) !== 1) {
            $patch['status'] = 1;
        }
        if ((int) ($row['is_required'] ?? 0) !== 1) {
            $patch['is_required'] = 1;
        }
        if ($patch !== []) {
            $patch['updated_at'] = date('Y-m-d H:i:s');
            $db->table('tb_fields')->where('id', $id)->update($patch);
        }

        return true;
    }

    private function insertJudulFieldRow($db, ?int $projectId): bool
    {
        $b = $db->table('tb_fields')->selectMin('order_no', 'mn');
        if ($projectId !== null && $projectId > 0) {
            $b->where('project_id', $projectId);
        } else {
            $b->where('project_id IS NULL', null, false);
        }
        $mnRow = $b->get()->getRowArray();
        $mn    = $mnRow['mn'] ?? null;
        $orderNo = 1;
        if ($mn !== null && $mn !== '' && (int) $mn > 0) {
            $orderNo = max(0, (int) $mn - 1);
        }

        $now = date('Y-m-d H:i:s');
        $insert = [
            'field_key'    => self::RESERVED_TITLE_FIELD_KEY,
            'field_label'  => 'Judul',
            'type'         => 'text',
            'options'      => null,
            'order_no'     => $orderNo,
            'status'       => 1,
            'is_required'  => 1,
            'scope'        => 'task',
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        if ($projectId !== null && $projectId > 0) {
            $insert['project_id'] = $projectId;
        } else {
            $insert['project_id'] = null;
        }
        if ($db->fieldExists('data_source', 'tb_fields')) {
            $insert['data_source'] = 'manual';
        }
        if ($db->fieldExists('source_config', 'tb_fields')) {
            $insert['source_config'] = null;
        }
        if ($db->fieldExists('validation_rule', 'tb_fields')) {
            $insert['validation_rule'] = null;
        }
        if ($db->fieldExists('default_value', 'tb_fields')) {
            $insert['default_value'] = null;
        }
        if ($db->fieldExists('placeholder', 'tb_fields')) {
            $insert['placeholder'] = null;
        }
        if ($db->fieldExists('help_text', 'tb_fields')) {
            $insert['help_text'] = null;
        }
        if ($db->fieldExists('submission_col', 'tb_fields')) {
            $insert['submission_col'] = null;
        }

        try {
            $db->table('tb_fields')->insert($insert);

            return (int) $db->insertID() > 0;
        } catch (\Throwable $e) {
            log_message('warning', 'insertJudulFieldRow: ' . $e->getMessage());

            return false;
        }
    }

    public function getActiveFields(): array
    {
        $b = $this->where('status', 1)
            ->groupStart()
            ->where('scope', 'task')
            ->orWhere('scope', 'both')
            ->groupEnd();
        if ($this->hasProjectScopeColumn()) {
            $b->where('project_id IS NULL', null, false);
        }

        return $b->orderBy('order_no', 'ASC')->findAll();
    }

    public function getActiveFieldsForProject(int $projectId): array
    {
        if (! $this->hasProjectScopeColumn() || $projectId <= 0) {
            return [];
        }

        return $this->where('status', 1)
            ->where('project_id', $projectId)
            ->orderBy('order_no', 'ASC')
            ->findAll();
    }

    /**
     * Satu baris tb_fields untuk menyimpan nilai task (field-update / EAV).
     * Task proyek: utamakan definisi project_id = task.project_id; untuk judul,
     * pastikan lewat ensure + fallback definisi internal (project_id NULL) bila DB lama.
     *
     * @return array<string, mixed>|null
     */
    public function resolveFieldRowForTask(int $taskProjectId, string $fieldKey): ?array
    {
        $fieldKey = trim($fieldKey);
        if ($fieldKey === '') {
            return null;
        }

        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_fields')) {
            return null;
        }

        $builder = $db->table('tb_fields')
            ->where('field_key', $fieldKey)
            ->where('status', 1);
        if ($this->hasProjectScopeColumn()) {
            if ($taskProjectId > 0) {
                $builder->where('project_id', $taskProjectId);
            } else {
                $builder->where('project_id IS NULL', null, false);
            }
        }
        $field = $builder->get()->getRowArray();
        if ($field) {
            return $field;
        }

        if ($fieldKey === self::RESERVED_TITLE_FIELD_KEY && $taskProjectId > 0 && $this->hasProjectScopeColumn()) {
            $this->ensureJudulFieldForProject($taskProjectId);
            $field = $db->table('tb_fields')
                ->where('field_key', $fieldKey)
                ->where('status', 1)
                ->where('project_id', $taskProjectId)
                ->get()->getRowArray();
            if ($field) {
                return $field;
            }

            return $db->table('tb_fields')
                ->where('field_key', $fieldKey)
                ->where('status', 1)
                ->where('project_id IS NULL', null, false)
                ->get()->getRowArray() ?: null;
        }

        return null;
    }

    public function getActiveFieldsByScope(string $scope): array
    {
        $b = $this->where('status', 1);
        if ($this->hasProjectScopeColumn()) {
            $b->where('project_id IS NULL', null, false);
        }
        if ($scope === 'both') {
            return $b->where('scope', 'both')
                ->orderBy('order_no', 'ASC')
                ->findAll();
        }

        return $b->groupStart()
            ->where('scope', $scope)
            ->orWhere('scope', 'both')
            ->groupEnd()
            ->orderBy('order_no', 'ASC')
            ->findAll();
    }

    public function getAllOrdered(?int $projectId = null): array
    {
        $b = $this->builder()->orderBy('order_no', 'ASC');
        if ($this->hasProjectScopeColumn()) {
            if ($projectId === null || $projectId <= 0) {
                $b->where('tb_fields.project_id IS NULL', null, false);
            } else {
                $b->where('tb_fields.project_id', $projectId);
            }
        }

        return $b->get()->getResultArray();
    }

    public function reorder(array $items, ?int $projectScope = null): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($this->hasProjectScopeColumn()) {
                $f = $db->table('tb_fields')->select('project_id')->where('id', $id)->get()->getRowArray();
                if (! $f) {
                    continue;
                }
                $fp = $f['project_id'] ?? null;
                if ($projectScope === null || $projectScope <= 0) {
                    if ($fp !== null) {
                        continue;
                    }
                } elseif ((int) $fp !== (int) $projectScope) {
                    continue;
                }
            }
            $db->table('tb_fields')
                ->where('id', $id)
                ->update(['order_no' => (int) $item['order_no']]);
        }
        $db->transComplete();

        return $db->transStatus();
    }

    public function toggleStatus(int $id): bool
    {
        $field = $this->find($id);
        if (! $field) {
            return false;
        }

        return $this->update($id, ['status' => $field['status'] ? 0 : 1]);
    }

    public function decodeOptions(array $field): array
    {
        $field['options_array'] = [];
        if (! empty($field['options'])) {
            $decoded                = json_decode($field['options'], true);
            $field['options_array'] = is_array($decoded) ? $decoded : [];
        }
        $field['source_config_array'] = [];
        if (! empty($field['source_config'])) {
            $cfg                          = json_decode((string) $field['source_config'], true);
            $field['source_config_array'] = is_array($cfg) ? $cfg : [];
        }

        return $field;
    }

    public function getSubmissionColumns(): array
    {
        $db    = \Config\Database::connect();
        $query = $db->query('SHOW COLUMNS FROM `tb_submissions`');
        if (! $query) {
            return [];
        }

        $columns = [];
        foreach ($query->getResultArray() as $col) {
            if (in_array($col['Field'], ['id', 'created_at', 'updated_at', 'task_id'], true)) {
                continue;
            }
            $columns[] = $col['Field'];
        }

        return $columns;
    }
}
