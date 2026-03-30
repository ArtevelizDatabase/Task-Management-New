<?php

namespace App\Commands;

use App\Models\NotificationModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Spark Command: tasks:remind
 *
 * Kirim notifikasi deadline ke user yang punya task jatuh tempo H-1 dan H-0.
 *
 *   php spark tasks:remind
 *
 * Cron (harian 08:00):
 *   0 8 * * * /usr/bin/php /path/to/project/spark tasks:remind >> /var/log/taskflow-remind.log 2>&1
 */
class DeadlineReminder extends BaseCommand
{
    protected $group       = 'Tasks';
    protected $name        = 'tasks:remind';
    protected $description = 'Kirim notifikasi deadline H-1 dan H-0 ke assignee task.';

    public function run(array $params): void
    {
        $db    = \Config\Database::connect();
        $notif = new NotificationModel();

        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        CLI::write('[' . date('Y-m-d H:i:s') . '] Mulai cek deadline...', 'yellow');

        $rangeStart = $today . ' 00:00:00';
        $rangeEnd   = $tomorrow . ' 23:59:59';

        $tasks = $db->table('tb_task t')
            ->select('t.id, t.user_id, t.deadline')
            ->where('t.deadline >=', $rangeStart)
            ->where('t.deadline <=', $rangeEnd)
            ->whereNotIn('t.status', ['done', 'cancelled'])
            ->where('t.deleted_at IS NULL')
            ->get()->getResultArray();

        $judulMap = [];
        if ($tasks !== []) {
            $ids = array_values(array_unique(array_map(
                static fn(array $row): int => (int) $row['id'],
                $tasks
            )));
            $judulRows = $db->table('tb_task_values tv')
                ->select('tv.task_id, tv.value')
                ->join('tb_task t2', 't2.id = tv.task_id')
                ->join('tb_fields f', 'f.id = tv.field_id', 'inner')
                ->where('f.field_key', 'judul')
                ->where('f.status', 1)
                ->groupStart()
                ->groupStart()
                ->where('t2.project_id IS NULL', null, false)
                ->where('f.project_id IS NULL', null, false)
                ->groupEnd()
                ->orWhere('t2.project_id = f.project_id', null, false)
                ->groupEnd()
                ->whereIn('tv.task_id', $ids)
                ->get()
                ->getResultArray();
            foreach ($judulRows as $jr) {
                $tid = (int) $jr['task_id'];
                if (! isset($judulMap[$tid])) {
                    $judulMap[$tid] = $jr['value'];
                }
            }
        }

        foreach ($tasks as &$task) {
            $tid = (int) $task['id'];
            $task['judul'] = $judulMap[$tid] ?? "Task #{$tid}";
        }
        unset($task);

        $sent = 0;

        foreach ($tasks as $task) {
            $taskId   = (int) $task['id'];
            $userId   = (int) $task['user_id'];
            $deadline = (string) $task['deadline'];
            $judul    = $task['judul'] ?? "Task #{$taskId}";
            $isToday  = substr($deadline, 0, 10) === $today;

            $alreadySent = $db->table('tb_notifications')
                ->where('user_id', $userId)
                ->where('type', 'warning')
                ->like('title', 'Deadline task', 'after')
                ->like('message', "#{$taskId}", 'both')
                ->where('created_at >=', $today . ' 00:00:00')
                ->countAllResults();

            if ($alreadySent) {
                continue;
            }

            $title   = $isToday ? 'Deadline task hari ini!' : 'Deadline task besok!';
            $message = "Task #{$taskId} \"{$judul}\" " . ($isToday ? 'jatuh tempo HARI INI' : 'jatuh tempo besok') . " ({$deadline}).";

            $notif->send($userId, 'warning', $title, $message, ['task_id' => $taskId]);

            if ($db->tableExists('tb_task_assignees')) {
                $assignees = $db->table('tb_task_assignees')
                    ->where('task_id', $taskId)
                    ->where('user_id !=', $userId)
                    ->get()->getResultArray();

                foreach ($assignees as $a) {
                    $aid = (int) $a['user_id'];
                    $dup = $db->table('tb_notifications')
                        ->where('user_id', $aid)
                        ->where('type', 'warning')
                        ->like('title', 'Deadline task', 'after')
                        ->like('message', "#{$taskId}", 'both')
                        ->where('created_at >=', $today . ' 00:00:00')
                        ->countAllResults();
                    if (! $dup) {
                        $notif->send($aid, 'warning', $title, $message, ['task_id' => $taskId]);
                    }
                }
            }

            $sent++;
            CLI::write("  → Task #{$taskId} ({$judul}): notif dikirim ke user #{$userId}", 'green');
        }

        CLI::write('[' . date('Y-m-d H:i:s') . "] Selesai. {$sent} task diproses.", 'cyan');
    }
}
