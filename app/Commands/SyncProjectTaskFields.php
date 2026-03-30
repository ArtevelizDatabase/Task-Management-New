<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\FieldModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * For tasks that already had project_id before per-project field definitions existed:
 * clone internal tb_fields into the project and remap tb_task_values.field_id.
 */
class SyncProjectTaskFields extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'project:sync-fields';
    protected $description = 'Salin definisi field internal ke project kosong, remap EAV, dan pastikan judul/priority/deskripsi sistem.';

    protected $usage   = 'project:sync-fields [options]';
    protected $options = [
        '--force' => 'Terapkan perubahan (tanpa ini: dry-run)',
    ];

    public function run(array $params): void
    {
        $db    = Database::connect();
        $force = CLI::getOption('force') !== null;

        if (! $db->fieldExists('project_id', 'tb_fields') || ! $db->fieldExists('project_id', 'tb_task')) {
            CLI::error('Kolom project_id pada tb_fields/tb_task belum ada — jalankan migrasi dulu.');

            return;
        }

        $pids = $db->table('tb_task')
            ->select('project_id')
            ->where('deleted_at IS NULL')
            ->where('project_id IS NOT NULL', null, false)
            ->groupBy('project_id')
            ->get()
            ->getResultArray();

        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $r): int => (int) ($r['project_id'] ?? 0),
            $pids
        ), static fn(int $v): bool => $v > 0)));

        if ($ids === []) {
            CLI::write('Tidak ada task dengan project_id.');

            return;
        }

        $fm = new FieldModel();
        CLI::write('Project IDs terdeteksi: ' . implode(', ', $ids));

        foreach ($ids as $pid) {
            $hasFields = (int) $db->table('tb_fields')->where('project_id', $pid)->countAllResults();
            if ($hasFields === 0) {
                $map = $fm->cloneInternalDefinitionsToProject($pid);
            } else {
                $map = [];
            }

            // Judul / priority / deskripsi sistem — selalu lengkapi meski project sudah punya field lain.
            $fm->ensureDefaultProjectTaskFields($pid);

            if ($hasFields > 0) {
                CLI::write("  [defaults] project {$pid}: judul/priority/deskripsi dicek (sudah ada {$hasFields} field).");

                continue;
            }

            if ($map === []) {
                CLI::write("  [skip] project {$pid}: tidak ada field internal untuk dikloning.");

                continue;
            }

            $taskIds = $db->table('tb_task')
                ->select('id')
                ->where('project_id', $pid)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();

            $nVal = 0;
            foreach ($taskIds as $tr) {
                $tid = (int) ($tr['id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $vals = $db->table('tb_task_values')->where('task_id', $tid)->get()->getResultArray();
                foreach ($vals as $v) {
                    $oldId = (int) ($v['field_id'] ?? 0);
                    $rowId = (int) ($v['id'] ?? 0);
                    if ($oldId <= 0 || $rowId <= 0 || ! isset($map[$oldId])) {
                        continue;
                    }
                    $nVal++;
                    if ($force) {
                        $db->table('tb_task_values')->where('id', $rowId)->update(['field_id' => (int) $map[$oldId]]);
                    }
                }
            }

            $mode = $force ? 'applied' : 'dry-run';
            CLI::write("  [{$mode}] project {$pid}: cloned " . count($map) . " fields, {$nVal} nilai EAV akan/di remap.");
        }

        if (! $force) {
            CLI::write('Gunakan --force untuk menulis perubahan ke database.');
        }
    }
}
