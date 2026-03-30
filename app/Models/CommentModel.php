<?php

namespace App\Models;

use CodeIgniter\Model;

class CommentModel extends Model
{
    protected $table      = 'tb_comments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['task_id', 'user_id', 'body'];

    public function getForTask(int $taskId): array
    {
        return $this->db->table('tb_comments c')
            ->select('c.*, u.username, u.nickname, u.avatar')
            ->join('tb_users u', 'u.id = c.user_id', 'left')
            ->where('c.task_id', $taskId)
            ->orderBy('c.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function addComment(int $taskId, int $userId, string $body): int|string
    {
        $id = $this->insert([
            'task_id' => $taskId,
            'user_id' => $userId,
            'body'    => $body,
        ]);

        $this->dispatchMentions($taskId, $userId, $body);

        return $id;
    }

    private function dispatchMentions(int $taskId, int $commenterUserId, string $body): void
    {
        preg_match_all('/@([\w]+)/', $body, $matches);
        if (empty($matches[1])) {
            return;
        }

        $usernames = array_unique($matches[1]);
        $notifModel = new NotificationModel();

        foreach ($usernames as $username) {
            $user = $this->db->table('tb_users')
                ->where('username', $username)
                ->get()->getRowArray();
            if (! $user) {
                $user = $this->db->table('tb_users')
                    ->where('nickname', $username)
                    ->get()->getRowArray();
            }

            if (! $user || (int) $user['id'] === $commenterUserId) {
                continue;
            }

            $commenter = $this->db->table('tb_users')
                ->select('nickname, username')
                ->where('id', $commenterUserId)
                ->get()->getRowArray();

            $commenterName = $commenter['nickname'] ?? $commenter['username'] ?? 'Someone';

            $notifModel->send(
                (int) $user['id'],
                'task',
                "{$commenterName} menyebut kamu di komentar",
                strip_tags($body),
                ['task_id' => $taskId]
            );
        }
    }

    public function deleteComment(int $commentId, int $userId, string $role): bool
    {
        $comment = $this->find($commentId);
        if (! $comment) {
            return false;
        }

        if ($role !== 'super_admin' && $role !== 'admin' && (int) $comment['user_id'] !== $userId) {
            return false;
        }

        return $this->delete($commentId);
    }
}
