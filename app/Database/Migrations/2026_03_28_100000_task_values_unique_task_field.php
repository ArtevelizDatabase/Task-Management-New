<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dedupe tb_task_values (task_id, field_id) and add UNIQUE for atomic upsert / race safety.
 */
class TaskValuesUniqueTaskField extends Migration
{
    public function up(): void
    {
        $db = $this->db;
        if (! $db->tableExists('tb_task_values')) {
            return;
        }

        // Remove duplicates: keep lowest id per (task_id, field_id)
        $db->query(
            'DELETE tv1 FROM tb_task_values tv1
             INNER JOIN tb_task_values tv2
               ON tv1.task_id = tv2.task_id AND tv1.field_id = tv2.field_id AND tv1.id > tv2.id'
        );

        if ($this->hasUniqueTaskField()) {
            return;
        }

        $db->query(
            'ALTER TABLE tb_task_values ADD UNIQUE KEY uq_task_values_task_field (task_id, field_id)'
        );
    }

    public function down(): void
    {
        $db = $this->db;
        if (! $db->tableExists('tb_task_values') || ! $this->hasUniqueTaskField()) {
            return;
        }

        $db->query('ALTER TABLE tb_task_values DROP INDEX uq_task_values_task_field');
    }

    private function hasUniqueTaskField(): bool
    {
        $rows = $this->db->query('SHOW INDEX FROM tb_task_values')->getResultArray();
        foreach ($rows as $r) {
            if (($r['Key_name'] ?? '') === 'uq_task_values_task_field') {
                return true;
            }
        }

        return false;
    }
}
