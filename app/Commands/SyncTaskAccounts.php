<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\TaskModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Backfill / refresh tb_task.account_id from EAV account field (account:id / office: / vendor:).
 *
 * Default: dry-run, only tasks with account_id empty. Use --force to apply, --all to overwrite existing account_id.
 */
class SyncTaskAccounts extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'data:sync-task-accounts';
    protected $description = 'Sinkronkan tb_task.account_id dari nilai mentah field EAV account.';

    protected $usage       = 'data:sync-task-accounts [options]';
    protected $options     = [
        '--force' => 'Terapkan update (tanpa ini hanya laporan dry-run)',
        '--all'   => 'Juga timpa task yang sudah punya account_id',
    ];

    public function run(array $params): void
    {
        $db      = Database::connect();
        $force   = CLI::getOption('force') !== null;
        $all     = CLI::getOption('all') !== null;
        $overwrite = $all;

        if (! $db->fieldExists('account_id', 'tb_task')) {
            CLI::error('Kolom tb_task.account_id tidak ada; lewati.');

            return;
        }

        $builder = $db->table('tb_task t')
            ->select('t.id')
            ->where('t.deleted_at IS NULL', null, false);
        if (! $overwrite) {
            $builder->groupStart()
                ->where('t.account_id IS NULL', null, false)
                ->orWhere('t.account_id', 0)
                ->groupEnd();
        }

        $rows = $builder->get()->getResultArray();
        $ids  = array_values(array_filter(array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $rows)));

        $taskModel = new TaskModel();
        $would     = [];
        foreach ($ids as $taskId) {
            $raw = $taskModel->getRawAccountFieldValueForTask($taskId);
            if ($raw === '') {
                continue;
            }
            $resolved = $taskModel->resolveAccountIdFromRawValue($raw, $db);
            if ($resolved === null) {
                continue;
            }
            $would[] = ['id' => $taskId, 'raw' => $raw, 'account_id' => $resolved];
        }

        CLI::write('=== data:sync-task-accounts ===', 'cyan');
        CLI::write('Mode: ' . ($overwrite ? 'semua task aktif (timpa account_id jika EAV ter-resolve)' : 'hanya account_id kosong'));
        CLI::write('Kandidat update: ' . count($would));

        $preview = array_slice($would, 0, 15);
        foreach ($preview as $w) {
            CLI::write("  task {$w['id']}: {$w['raw']} -> account_id={$w['account_id']}");
        }
        if (count($would) > 15) {
            CLI::write('  ... +' . (count($would) - 15) . ' lainnya');
        }

        if ($would === []) {
            CLI::write('Tidak ada task yang perlu disinkronkan.', 'green');

            return;
        }

        if (! $force) {
            CLI::newLine();
            CLI::write('Dry-run. Untuk menerapkan:', 'yellow');
            CLI::write('  php spark data:sync-task-accounts --force' . ($all ? ' --all' : ''), 'white');

            return;
        }

        $updated = 0;
        foreach ($would as $w) {
            if ($taskModel->syncCoreAccountIdFromAccountField((int) $w['id'], $overwrite)) {
                ++$updated;
            }
        }

        CLI::write("Diperbarui: {$updated}", 'green');
    }
}
