<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskRelationModel extends Model
{
    protected $table      = 'tb_task_relations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = false;
    protected $allowedFields = ['task_id', 'related_task_id', 'relation_type', 'created_by', 'created_at'];

    public function getForTask(int $taskId): array
    {
        $db = $this->db;

        $rows = $db->table('tb_task_relations r')
            ->select('r.*, rt.status as related_task_status, rt.project_id as related_project_id')
            ->join('tb_task rt', 'rt.id = r.related_task_id', 'left')
            ->where('r.task_id', $taskId)
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return [];
        }

        $relatedIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) $row['related_task_id'],
            $rows
        )));

        $titleBuilder = $db->table('tb_task_values tv')
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
            ->whereIn('tv.task_id', $relatedIds);

        $titles = $titleBuilder->get()->getResultArray();

        $titleMap = [];
        foreach ($titles as $t) {
            $tid = (int) $t['task_id'];
            if (! isset($titleMap[$tid])) {
                $titleMap[$tid] = $t['value'];
            }
        }

        foreach ($rows as &$row) {
            $rid = (int) $row['related_task_id'];
            $row['related_task_title'] = $titleMap[$rid] ?? "Task #{$rid}";
        }

        return $rows;
    }

    public function addRelation(int $taskId, int $relatedTaskId, string $type, int $userId): bool
    {
        if ($taskId === $relatedTaskId) {
            return false;
        }

        $exists = $this->where([
            'task_id'         => $taskId,
            'related_task_id' => $relatedTaskId,
            'relation_type'   => $type,
        ])->countAllResults();

        if ($exists) {
            return false;
        }

        $this->insert([
            'task_id'         => $taskId,
            'related_task_id' => $relatedTaskId,
            'relation_type'   => $type,
            'created_by'      => $userId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
