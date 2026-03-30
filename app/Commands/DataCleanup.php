<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\TaskModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Maintenance: kurangi data sampah (task soft-deleted + orphan EAV/submissions).
 *
 * Default dry-run. Gunakan --force untuk eksekusi.
 */
class DataCleanup extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'data:cleanup';
    protected $description = 'Laporan + hapus permanen task di trash, orphan tb_task_values / tb_submissions.';

    protected $usage       = 'data:cleanup [options]';
    protected $options     = [
        '--force' => 'Jalankan penghapusan (tanpa ini hanya laporan dry-run)',
    ];

    public function run(array $params): void
    {
        $db    = Database::connect();
        $force = CLI::getOption('force') !== null;

        $trashCount = (int) $db->table('tb_task')
            ->where('deleted_at IS NOT NULL', null, false)
            ->countAllResults();

        $orphanValues = 0;
        $orphanSubs   = 0;
        if ($db->tableExists('tb_task_values')) {
            $row = $db->query(
                'SELECT COUNT(*) AS c FROM tb_task_values tv
                 LEFT JOIN tb_task t ON t.id = tv.task_id
                 WHERE t.id IS NULL'
            )->getRow();
            $orphanValues = $row !== null ? (int) $row->c : 0;
        }
        if ($db->tableExists('tb_submissions')) {
            $row = $db->query(
                'SELECT COUNT(*) AS c FROM tb_submissions s
                 LEFT JOIN tb_task t ON t.id = s.task_id
                 WHERE t.id IS NULL'
            )->getRow();
            $orphanSubs = $row !== null ? (int) $row->c : 0;
        }

        CLI::write('=== data:cleanup ===', 'cyan');
        CLI::write("Task soft-deleted (trash): {$trashCount}");
        CLI::write("Orphan tb_task_values (task hilang): {$orphanValues}");
        CLI::write("Orphan tb_submissions (task hilang): {$orphanSubs}");

        if ($trashCount === 0 && $orphanValues === 0 && $orphanSubs === 0) {
            CLI::write('Tidak ada yang perlu dibersihkan.', 'green');

            return;
        }

        if (! $force) {
            CLI::newLine();
            CLI::write('Dry-run. Untuk menjalankan hapus:', 'yellow');
            CLI::write('  php spark data:cleanup --force', 'white');

            return;
        }

        $db->transStart();

        if ($orphanValues > 0 && $db->tableExists('tb_task_values')) {
            $db->query(
                'DELETE tv FROM tb_task_values tv
                 LEFT JOIN tb_task t ON t.id = tv.task_id
                 WHERE t.id IS NULL'
            );
            CLI::write("Dihapus orphan tb_task_values: {$orphanValues}", 'green');
        }

        if ($orphanSubs > 0 && $db->tableExists('tb_submissions')) {
            $db->query(
                'DELETE s FROM tb_submissions s
                 LEFT JOIN tb_task t ON t.id = s.task_id
                 WHERE t.id IS NULL'
            );
            CLI::write("Dihapus orphan tb_submissions: {$orphanSubs}", 'green');
        }

        if ($trashCount > 0) {
            $rows = $db->table('tb_task')
                ->select('id')
                ->where('deleted_at IS NOT NULL', null, false)
                ->get()
                ->getResultArray();
            $ids = array_values(array_filter(array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $rows)));

            if ($ids !== []) {
                $db->table('tb_task_values')->whereIn('task_id', $ids)->delete();
                $db->table('tb_submissions')->whereIn('task_id', $ids)->delete();

                $taskModel = new TaskModel();
                foreach ($ids as $id) {
                    $taskModel->delete($id, true);
                }
                CLI::write('Dihapus permanen task di trash: ' . count($ids), 'green');
            }
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            CLI::error('Transaksi gagal; tidak ada perubahan yang disimpan.');

            return;
        }

        CLI::write('Selesai.', 'green');
    }
}
