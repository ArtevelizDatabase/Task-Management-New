<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Platform & file types are owned by a single product group (no global master).
 * Drops junction tables; remaps tb_submission_upload_status to new per-group row ids.
 */
class ScopedUploadPlatformsFiletypes extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_platforms')) {
            return;
        }
        if ($this->db->fieldExists('product_group_id', 'tb_platforms')) {
            return;
        }

        $p  = $this->db->DBPrefix;
        $db = $this->db;

        $db->query("ALTER TABLE {$p}tb_platforms ADD COLUMN product_group_id INT UNSIGNED NULL AFTER id");
        $db->query("ALTER TABLE {$p}tb_file_types ADD COLUMN product_group_id INT UNSIGNED NULL AFTER id");

        foreach (['tb_platforms', 'tb_file_types'] as $tbl) {
            try {
                $db->query("ALTER TABLE {$p}{$tbl} DROP INDEX abbr");
            } catch (\Throwable) {
                // index name may differ
            }
        }

        $globalsPlat = $db->table('tb_platforms')->where('product_group_id', null)->orderBy('order_no', 'ASC')->get()->getResultArray();
        $globalsFt   = $db->table('tb_file_types')->where('product_group_id', null)->orderBy('order_no', 'ASC')->get()->getResultArray();
        $platById    = [];
        foreach ($globalsPlat as $row) {
            $platById[(int) ($row['id'] ?? 0)] = $row;
        }
        $ftById = [];
        foreach ($globalsFt as $row) {
            $ftById[(int) ($row['id'] ?? 0)] = $row;
        }

        $platformMap = []; // "gid:oldPid" => newPid
        $fileTypeMap = []; // "gid:oldFid" => newFid

        $now = date('Y-m-d H:i:s');

        if ($db->tableExists('tb_product_groups')) {
            $groups = $db->table('tb_product_groups')->get()->getResultArray();
            foreach ($groups as $g) {
                $gid = (int) ($g['id'] ?? 0);
                if ($gid < 1) {
                    continue;
                }
                $hp = (int) ($g['has_platform'] ?? 1) === 1;
                $hf = (int) ($g['has_file_types'] ?? 0) === 1;

                if ($hp) {
                    $pairs = [];
                    if ($db->tableExists('tb_product_group_platforms')) {
                        $jRows = $db->table('tb_product_group_platforms')->where('product_group_id', $gid)->orderBy('order_no', 'ASC')->get()->getResultArray();
                        foreach ($jRows as $jr) {
                            $pairs[] = (int) ($jr['platform_id'] ?? 0);
                        }
                    }
                    if ($pairs === [] && $globalsPlat !== []) {
                        foreach ($globalsPlat as $gp) {
                            $pairs[] = (int) ($gp['id'] ?? 0);
                        }
                    }
                    $ord = 0;
                    foreach ($pairs as $oldPid) {
                        if ($oldPid < 1 || ! isset($platById[$oldPid])) {
                            continue;
                        }
                        $key = $gid . ':' . $oldPid;
                        if (isset($platformMap[$key])) {
                            continue;
                        }
                        $src = $platById[$oldPid];
                        ++$ord;
                        $abbr = strtoupper(trim((string) ($src['abbr'] ?? '')));
                        $abbr = $this->uniqueAbbrForGroup($db, 'tb_platforms', $gid, $abbr);
                        $db->table('tb_platforms')->insert([
                            'product_group_id' => $gid,
                            'name'             => $src['name'] ?? '',
                            'abbr'             => $abbr,
                            'icon'             => $src['icon'] ?? null,
                            'order_no'         => $ord,
                            'status'           => (int) ($src['status'] ?? 1),
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                        $platformMap[$key] = (int) $db->insertID();
                    }
                }

                if ($hf) {
                    $fids = [];
                    if ($db->tableExists('tb_product_group_file_types')) {
                        $jRows = $db->table('tb_product_group_file_types')->where('product_group_id', $gid)->orderBy('order_no', 'ASC')->get()->getResultArray();
                        foreach ($jRows as $jr) {
                            $fids[] = (int) ($jr['file_type_id'] ?? 0);
                        }
                    }
                    if ($fids === [] && $globalsFt !== []) {
                        foreach ($globalsFt as $gf) {
                            $fids[] = (int) ($gf['id'] ?? 0);
                        }
                    }
                    $ord = 0;
                    foreach ($fids as $oldFid) {
                        if ($oldFid < 1 || ! isset($ftById[$oldFid])) {
                            continue;
                        }
                        $key = $gid . ':' . $oldFid;
                        if (isset($fileTypeMap[$key])) {
                            continue;
                        }
                        $src = $ftById[$oldFid];
                        ++$ord;
                        $abbr = strtoupper(trim((string) ($src['abbr'] ?? '')));
                        $abbr = $this->uniqueAbbrForGroup($db, 'tb_file_types', $gid, $abbr);
                        $db->table('tb_file_types')->insert([
                            'product_group_id' => $gid,
                            'name'             => $src['name'] ?? '',
                            'abbr'             => $abbr,
                            'order_no'         => $ord,
                            'status'           => (int) ($src['status'] ?? 1),
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                        $fileTypeMap[$key] = (int) $db->insertID();
                    }
                }
            }
        }

        if ($db->tableExists('tb_submission_upload_status')) {
            foreach ($platformMap as $key => $newPid) {
                [$gid, $oldPid] = array_map('intval', explode(':', $key, 2));
                $db->table('tb_submission_upload_status')
                    ->where('product_group_id', $gid)
                    ->where('platform_id', $oldPid)
                    ->update(['platform_id' => $newPid]);
            }
            foreach ($fileTypeMap as $key => $newFid) {
                [$gid, $oldFid] = array_map('intval', explode(':', $key, 2));
                $db->table('tb_submission_upload_status')
                    ->where('product_group_id', $gid)
                    ->where('file_type_id', $oldFid)
                    ->update(['file_type_id' => $newFid]);
            }
        }

        $db->query("DELETE FROM {$p}tb_platforms WHERE product_group_id IS NULL");
        $db->query("DELETE FROM {$p}tb_file_types WHERE product_group_id IS NULL");

        if ($db->tableExists('tb_product_group_platforms')) {
            $this->forge->dropTable('tb_product_group_platforms', true);
        }
        if ($db->tableExists('tb_product_group_file_types')) {
            $this->forge->dropTable('tb_product_group_file_types', true);
        }

        try {
            $db->query("ALTER TABLE {$p}tb_platforms MODIFY product_group_id INT UNSIGNED NOT NULL");
        } catch (\Throwable) {
            // empty table edge case
        }
        try {
            $db->query("ALTER TABLE {$p}tb_file_types MODIFY product_group_id INT UNSIGNED NOT NULL");
        } catch (\Throwable) {
        }

        try {
            $db->query("ALTER TABLE {$p}tb_platforms ADD UNIQUE KEY uq_platform_group_abbr (product_group_id, abbr)");
        } catch (\Throwable) {
        }
        try {
            $db->query("ALTER TABLE {$p}tb_file_types ADD UNIQUE KEY uq_filetype_group_abbr (product_group_id, abbr)");
        } catch (\Throwable) {
        }
        try {
            $db->query("ALTER TABLE {$p}tb_platforms ADD KEY idx_platform_product_group (product_group_id)");
        } catch (\Throwable) {
        }
        try {
            $db->query("ALTER TABLE {$p}tb_file_types ADD KEY idx_filetype_product_group (product_group_id)");
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // Irreversible without backup; junction + global masters cannot be reconstructed safely.
    }

    private function uniqueAbbrForGroup($db, string $table, int $groupId, string $abbr): string
    {
        if ($abbr === '') {
            $abbr = 'X';
        }
        $base = $abbr;
        $n    = 0;
        while ($db->table($table)->where('product_group_id', $groupId)->where('abbr', $abbr)->countAllResults() > 0) {
            ++$n;
            $abbr = substr($base, 0, 16) . $n;
        }

        return $abbr;
    }
}
