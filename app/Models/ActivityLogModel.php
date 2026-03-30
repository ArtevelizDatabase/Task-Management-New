<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table      = 'tb_activity_log';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = false;
    protected $allowedFields = ['task_id', 'user_id', 'action', 'description', 'old_value', 'new_value', 'created_at'];

    public function log(
        int $taskId,
        int $userId,
        string $action,
        string $description = '',
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $this->insert([
            'task_id'     => $taskId,
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'old_value'   => $oldValue !== null ? json_encode($oldValue) : null,
            'new_value'   => $newValue !== null ? json_encode($newValue) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function logCreated(int $taskId, int $userId): void
    {
        $this->log($taskId, $userId, 'created', 'Task dibuat');
    }

    public function logStatusChanged(int $taskId, int $userId, string $old, string $new): void
    {
        $this->log($taskId, $userId, 'status_changed',
            "Status diubah dari '{$old}' ke '{$new}'",
            ['status' => $old],
            ['status' => $new]
        );
    }

    public function logFieldUpdated(int $taskId, int $userId, string $fieldLabel, mixed $old, mixed $new): void
    {
        $this->log($taskId, $userId, 'field_updated',
            "Field '{$fieldLabel}' diperbarui",
            [$fieldLabel => $old],
            [$fieldLabel => $new]
        );
    }

    public function logCommented(int $taskId, int $userId): void
    {
        $this->log($taskId, $userId, 'commented', 'Menambahkan komentar');
    }

    public function logAttachmentAdded(int $taskId, int $userId, string $filename): void
    {
        $this->log($taskId, $userId, 'attachment_added',
            "File '{$filename}' dilampirkan"
        );
    }

    public function logAssigned(int $taskId, int $assignedBy, int $assignedTo): void
    {
        $this->log($taskId, $assignedBy, 'assigned',
            "Task di-assign ke user #{$assignedTo}",
            null,
            ['user_id' => $assignedTo]
        );
    }

    public function getForTask(int $taskId, int $limit = 50): array
    {
        return $this->db->table('tb_activity_log al')
            ->select('al.*, u.username, u.nickname, u.avatar')
            ->join('tb_users u', 'u.id = al.user_id', 'left')
            ->where('al.task_id', $taskId)
            ->orderBy('al.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }
}
