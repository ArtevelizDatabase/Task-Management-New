<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus unique lama hanya pada field_key (mis. uk_field_key) agar boleh ada
 * baris judul/deskripsi per project (project_id berbeda). Pasang unique komposit
 * (field_scope_uid, field_key) jika belum ada.
 *
 * Tanpa ini, clone internal → project gagal (Duplicate entry 'judul' for key 'uk_field_key').
 */
class FixTbFieldsScopeUnique extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_fields')) {
            return;
        }
        if ($this->db->DBDriver !== 'MySQLi') {
            log_message('warning', 'fix_tb_fields_scope_unique: hanya MySQL/MariaDB, dilewati.');

            return;
        }

        $this->dropLegacyFieldKeyOnlyUniques();

        if ($this->db->fieldExists('field_scope_uid', 'tb_fields')) {
            $this->ensureScopeCompositeUnique();

            return;
        }

        try {
            $this->db->query(
                'ALTER TABLE `tb_fields` ADD COLUMN `field_scope_uid` INT UNSIGNED
                 GENERATED ALWAYS AS (IFNULL(`project_id`, 0)) STORED'
            );
        } catch (\Throwable $e) {
            log_message('warning', 'fix_tb_fields_scope_uid column: ' . $e->getMessage());
        }

        $this->ensureScopeCompositeUnique();
    }

    public function down(): void
    {
        // Tidak mengembalikan uk_field_key — bisa memecah data multi-project.
    }

    private function dropLegacyFieldKeyOnlyUniques(): void
    {
        $q = $this->db->query('SHOW INDEX FROM `tb_fields`');
        if (! $q) {
            return;
        }
        $byName = [];
        foreach ($q->getResultArray() as $row) {
            $kn = (string) ($row['Key_name'] ?? '');
            if ($kn === '' || $kn === 'PRIMARY' || $kn === 'uq_fields_scope_key') {
                continue;
            }
            $seq = (int) ($row['Seq_in_index'] ?? 0);
            $col = (string) ($row['Column_name'] ?? '');
            $non = (int) ($row['Non_unique'] ?? 1);
            if ($non !== 0) {
                continue;
            }
            if (! isset($byName[$kn])) {
                $byName[$kn] = [];
            }
            $byName[$kn][$seq] = $col;
        }

        foreach ($byName as $indexName => $cols) {
            ksort($cols);
            $ordered = array_values($cols);
            if ($ordered !== ['field_key']) {
                continue;
            }
            try {
                $escaped = str_replace('`', '``', $indexName);
                $this->db->query('ALTER TABLE `tb_fields` DROP INDEX `' . $escaped . '`');
            } catch (\Throwable $e) {
                log_message('warning', 'fix_tb_fields_scope_unique DROP INDEX ' . $indexName . ': ' . $e->getMessage());
            }
        }
    }

    private function ensureScopeCompositeUnique(): void
    {
        try {
            $this->db->query(
                'ALTER TABLE `tb_fields` ADD UNIQUE KEY `uq_fields_scope_key` (`field_scope_uid`, `field_key`)'
            );
        } catch (\Throwable) {
            // sudah ada
        }
    }
}
