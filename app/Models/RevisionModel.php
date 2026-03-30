<?php

namespace App\Models;

use CodeIgniter\Model;

class RevisionModel extends Model
{
    protected $table      = 'tb_revisions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'task_id', 'requested_by', 'description',
        'requested_at', 'due_date', 'handled_by',
        'status', 'handler_note', 'resolved_at',
    ];

    public function getForTask(int $taskId): array
    {
        return $this->db->table('tb_revisions r')
            ->select('r.*, u.username as handler_name, u.nickname as handler_nickname')
            ->join('tb_users u', 'u.id = r.handled_by', 'left')
            ->where('r.task_id', $taskId)
            ->orderBy('r.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function updateStatus(int $id, string $status, int $handlerId, string $note = ''): void
    {
        $data = [
            'status'       => $status,
            'handler_note' => $note,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($status === 'pending') {
            $data['handled_by']  = null;
            $data['resolved_at'] = null;
        } else {
            $data['handled_by'] = $handlerId;
            if ($status === 'done' || $status === 'rejected') {
                $data['resolved_at'] = date('Y-m-d H:i:s');
            }
        }

        $this->update($id, $data);
    }

    public function getPendingCount(int $taskId): int
    {
        return (int) $this->where('task_id', $taskId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->countAllResults();
    }
}
