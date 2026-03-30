<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table        = 'tb_notifications';
    protected $primaryKey   = 'id';
    protected $returnType   = 'array';
    protected $useTimestamps = false;
    protected $updatedField  = false;

    protected $allowedFields = ['user_id', 'type', 'title', 'message', 'data', 'is_read', 'read_at', 'created_at'];

    public static array $types = [
        'info'    => ['icon' => 'fa-circle-info',       'color' => '#4f46e5'],
        'success' => ['icon' => 'fa-circle-check',      'color' => '#16a34a'],
        'warning' => ['icon' => 'fa-triangle-exclamation', 'color' => '#d97706'],
        'error'   => ['icon' => 'fa-circle-exclamation', 'color' => '#dc2626'],
        'task'    => ['icon' => 'fa-clipboard-list',    'color' => '#0891b2'],
        'user'    => ['icon' => 'fa-user',              'color' => '#7c3aed'],
        'team'    => ['icon' => 'fa-users',             'color' => '#059669'],
        'system'  => ['icon' => 'fa-gear',              'color' => '#6b7280'],
    ];

    // ── Send ──────────────────────────────────────────────────────────────

    public function send(int $userId, string $type, string $title, string $message = '', array $data = []): int|string
    {
        return $this->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'data'       => $data ? json_encode($data) : null,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function sendToAll(string $type, string $title, string $message = '', array $data = []): void
    {
        $users = $this->db->table('tb_users')->where('status', 'active')->get()->getResultArray();
        foreach ($users as $user) {
            $this->send((int)$user['id'], $type, $title, $message, $data);
        }
    }

    // ── Queries ───────────────────────────────────────────────────────────

    public function getForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->findAll();
    }

    public function getUnreadCount(int $userId): int
    {
        return (int) $this->where('user_id', $userId)->where('is_read', 0)->countAllResults();
    }

    public function getUnread(int $userId, int $limit = 10): array
    {
        return $this->where('user_id', $userId)
                    ->where('is_read', 0)
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    // ── Actions ───────────────────────────────────────────────────────────

    public function markRead(int $id, int $userId): void
    {
        $this->where('id', $id)->where('user_id', $userId)->set([
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function markUnread(int $id, int $userId): void
    {
        $this->where('id', $id)->where('user_id', $userId)->set([
            'is_read' => 0,
            'read_at' => null,
        ])->update();
    }

    public function markAllRead(int $userId): void
    {
        $this->where('user_id', $userId)->where('is_read', 0)->set([
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function deleteForUser(int $id, int $userId): void
    {
        $this->where('id', $id)->where('user_id', $userId)->delete();
    }

    public function deleteAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }

    public function deleteReadForUser(int $userId): void
    {
        $this->where('user_id', $userId)->where('is_read', 1)->delete();
    }

    // ── Preferences ───────────────────────────────────────────────────────

    public function getPreferences(int $userId): array
    {
        $rows = $this->db->table('tb_notification_preferences')
            ->where('user_id', $userId)
            ->get()->getResultArray();

        $prefs = [];
        foreach ($rows as $r) {
            $prefs[$r['notification_type']] = (bool)$r['is_enabled'];
        }
        return $prefs;
    }

    public function setPreference(int $userId, string $type, bool $enabled): void
    {
        $existing = $this->db->table('tb_notification_preferences')
            ->where(['user_id' => $userId, 'notification_type' => $type])
            ->countAllResults();

        if ($existing) {
            $this->db->table('tb_notification_preferences')
                ->where(['user_id' => $userId, 'notification_type' => $type])
                ->update(['is_enabled' => $enabled ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->table('tb_notification_preferences')->insert([
                'user_id'           => $userId,
                'notification_type' => $type,
                'is_enabled'        => $enabled ? 1 : 0,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ── Auto-check inactive users ──────────────────────────────────────────

    public function notifyInactiveUsers(int $inactiveDays = 7): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$inactiveDays} days"));
        $users = $this->db->table('tb_users')
            ->where('status', 'active')
            ->where("(last_activity < '{$since}' OR last_activity IS NULL)")
            ->get()->getResultArray();

        $count = 0;
        foreach ($users as $user) {
            $alreadyNotified = $this->where('user_id', $user['id'])
                ->where('type', 'system')
                ->like('title', 'inactive', 'after')
                ->where('created_at >', date('Y-m-d H:i:s', strtotime('-1 day')))
                ->countAllResults();

            if (!$alreadyNotified) {
                $this->send((int)$user['id'], 'warning', 'Account tidak aktif', "Anda tidak aktif selama {$inactiveDays} hari terakhir. Segera login untuk tetap aktif.");
                $count++;
            }
        }
        return $count;
    }

    public function timeAgo(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)  return 'baru saja';
        if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
        if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
        if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
        return date('d M Y', strtotime($datetime));
    }
}
