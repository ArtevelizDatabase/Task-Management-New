<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskAssigneeModel extends Model
{
    protected $table      = 'tb_task_assignees';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = false;
    protected $allowedFields = ['task_id', 'user_id', 'assigned_by', 'assigned_at'];

    public function getAssignees(int $taskId): array
    {
        $rows = $this->db->table('tb_task_assignees ta')
            ->select('ta.*')
            ->where('ta.task_id', $taskId)
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
        $userModel = new UserModel();
        $users     = $uids === [] ? [] : $userModel->whereIn('id', $uids)->findAll();
        $byId      = [];
        foreach ($users as $u) {
            $byId[(int) ($u['id'] ?? 0)] = $u;
        }
        foreach ($rows as &$r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $u   = $byId[$uid] ?? null;
            $r['username'] = (string) ($u['username'] ?? '');
            $r['nickname'] = (string) ($u['nickname'] ?? '');
            $r['avatar']   = $u['avatar'] ?? null;
            if ($u === null) {
                $r['nickname'] = 'User #' . $uid;
            }
        }
        unset($r);

        return $rows;
    }

    public function assign(int $taskId, int $userId, int $assignedBy): bool
    {
        $exists = $this->where(['task_id' => $taskId, 'user_id' => $userId])->countAllResults();
        if ($exists) {
            return false;
        }

        $this->insert([
            'task_id'     => $taskId,
            'user_id'     => $userId,
            'assigned_by' => $assignedBy,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function unassign(int $taskId, int $userId): bool
    {
        return $this->where(['task_id' => $taskId, 'user_id' => $userId])->delete();
    }
}
