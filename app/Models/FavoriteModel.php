<?php

namespace App\Models;

use CodeIgniter\Model;

class FavoriteModel extends Model
{
    protected $table      = 'tb_user_favorites';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'entity_type', 'entity_id', 'created_at'];

    public function toggle(int $userId, string $entityType, int $entityId): bool
    {
        $existing = $this->where([
            'user_id'     => $userId,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ])->first();

        if ($existing) {
            $this->delete($existing['id']);

            return false;
        }

        $this->insert([
            'user_id'     => $userId,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function getForUser(int $userId): array
    {
        $rows = $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        if ($rows === []) {
            return [];
        }

        $taskIds = [];
        $projectIds = [];
        $clientIds = [];
        foreach ($rows as $r) {
            $eid = (int) $r['entity_id'];
            match ($r['entity_type']) {
                'task'    => $taskIds[] = $eid,
                'project' => $projectIds[] = $eid,
                'client'  => $clientIds[] = $eid,
                default   => null,
            };
        }

        $taskTitles = [];
        if ($taskIds !== []) {
            $tvRows = $this->db->table('tb_task_values tv')
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
                ->whereIn('tv.task_id', array_values(array_unique($taskIds)))
                ->get()
                ->getResultArray();
            foreach ($tvRows as $tr) {
                $tid = (int) $tr['task_id'];
                if (! isset($taskTitles[$tid])) {
                    $taskTitles[$tid] = $tr['value'];
                }
            }
        }

        $projectNames = [];
        if ($projectIds !== []) {
            $pRows = $this->db->table('tb_projects')
                ->select('id, name')
                ->whereIn('id', array_values(array_unique($projectIds)))
                ->get()
                ->getResultArray();
            $projectNames = array_column($pRows, 'name', 'id');
        }

        $clientNames = [];
        if ($clientIds !== []) {
            $cRows = $this->db->table('tb_clients')
                ->select('id, name')
                ->whereIn('id', array_values(array_unique($clientIds)))
                ->get()
                ->getResultArray();
            $clientNames = array_column($cRows, 'name', 'id');
        }

        foreach ($rows as &$row) {
            $id = (int) $row['entity_id'];
            $row['label'] = match ($row['entity_type']) {
                'task'    => $taskTitles[$id] ?? "Task #{$id}",
                'project' => $projectNames[$id] ?? "Project #{$id}",
                'client'  => $clientNames[$id] ?? "Client #{$id}",
                default   => "#{$id}",
            };
            $row['url'] = match ($row['entity_type']) {
                'task'    => base_url("tasks/{$row['entity_id']}"),
                'project' => base_url("projects/{$row['entity_id']}"),
                'client'  => base_url("clients/{$row['entity_id']}"),
                default   => '#',
            };
        }

        return $rows;
    }

    public function isFavorited(int $userId, string $entityType, int $entityId): bool
    {
        return $this->where([
            'user_id'     => $userId,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ])->countAllResults() > 0;
    }
}
