<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Consolidates vendor/office data into tb_accounts only:
 * - backfills account_id from legacy vendor_account_id
 * - drops vendor_account_id columns + legacy tables tb_vendor_accounts, tb_office_accounts
 */
class DropLegacyOfficeAccountTables extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        if (! $db->tableExists('tb_accounts')) {
            return;
        }

        // ── Backfill account_id from legacy vendor id (tb_accounts.legacy_vendor_id) ──
        if ($db->tableExists('tb_task') && $db->fieldExists('vendor_account_id', 'tb_task') && $db->fieldExists('account_id', 'tb_task')) {
            $db->query(
                'UPDATE tb_task t
                 INNER JOIN tb_accounts a ON a.legacy_vendor_id = t.vendor_account_id AND a.type = \'vendor\'
                 SET t.account_id = a.id
                 WHERE t.vendor_account_id IS NOT NULL
                   AND (t.account_id IS NULL OR t.account_id = 0)'
            );
        }

        foreach (['tb_vendor_targets', 'tb_vendor_allocations', 'tb_assignment_rules'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('vendor_account_id', $table) || ! $db->fieldExists('account_id', $table)) {
                continue;
            }
            $db->query(
                "UPDATE `{$table}` x
                 INNER JOIN tb_accounts a ON a.legacy_vendor_id = x.vendor_account_id AND a.type = 'vendor'
                 SET x.account_id = a.id
                 WHERE x.vendor_account_id IS NOT NULL
                   AND (x.account_id IS NULL OR x.account_id = 0)"
            );
        }

        // Dedupe targets after backfill (keep lowest id)
        if ($db->tableExists('tb_vendor_targets') && $db->fieldExists('account_id', 'tb_vendor_targets')) {
            $db->query(
                'DELETE t1 FROM tb_vendor_targets t1
                 INNER JOIN tb_vendor_targets t2
                   ON t1.account_id = t2.account_id
                  AND t1.period_type = t2.period_type
                  AND t1.period_start = t2.period_start
                  AND t1.id > t2.id
                 WHERE t1.account_id IS NOT NULL'
            );
        }

        // Dedupe allocations
        if ($db->tableExists('tb_vendor_allocations') && $db->fieldExists('account_id', 'tb_vendor_allocations')) {
            $db->query(
                'DELETE a1 FROM tb_vendor_allocations a1
                 INNER JOIN tb_vendor_allocations a2
                   ON a1.account_id = a2.account_id
                  AND a1.user_id = a2.user_id
                  AND a1.id > a2.id
                 WHERE a1.account_id IS NOT NULL'
            );
        }

        // ── tb_vendor_targets: drop legacy column + indexes ──
        if ($db->tableExists('tb_vendor_targets') && $db->fieldExists('vendor_account_id', 'tb_vendor_targets')) {
            $this->dropIndexIfExists('tb_vendor_targets', 'vendor_account_id_period_type_period_start');
            $this->dropIndexIfExists('tb_vendor_targets', 'vendor_account_id');
            $this->forge->dropColumn('tb_vendor_targets', 'vendor_account_id');
            if (! $this->indexExists('tb_vendor_targets', 'account_id_period_type_period_start')) {
                $db->query(
                    'ALTER TABLE tb_vendor_targets
                     ADD UNIQUE KEY account_id_period_type_period_start (account_id, period_type, period_start)'
                );
            }
            if (! $this->indexExists('tb_vendor_targets', 'account_id')) {
                $db->query('ALTER TABLE tb_vendor_targets ADD KEY account_id (account_id)');
            }
        }

        // ── tb_vendor_allocations ──
        if ($db->tableExists('tb_vendor_allocations') && $db->fieldExists('vendor_account_id', 'tb_vendor_allocations')) {
            $this->dropIndexIfExists('tb_vendor_allocations', 'vendor_account_id_user_id');
            $this->dropIndexIfExists('tb_vendor_allocations', 'vendor_account_id');
            $this->forge->dropColumn('tb_vendor_allocations', 'vendor_account_id');
            if (! $this->indexExists('tb_vendor_allocations', 'account_id_user_id')) {
                $db->query(
                    'ALTER TABLE tb_vendor_allocations
                     ADD UNIQUE KEY account_id_user_id (account_id, user_id)'
                );
            }
            if (! $this->indexExists('tb_vendor_allocations', 'account_id')) {
                $db->query('ALTER TABLE tb_vendor_allocations ADD KEY account_id (account_id)');
            }
        }

        // ── tb_assignment_rules ──
        if ($db->tableExists('tb_assignment_rules') && $db->fieldExists('vendor_account_id', 'tb_assignment_rules')) {
            $this->dropIndexIfExists('tb_assignment_rules', 'vendor_account_id_status_priority');
            $this->dropIndexIfExists('tb_assignment_rules', 'vendor_account_id');
            $this->forge->dropColumn('tb_assignment_rules', 'vendor_account_id');
            if (! $this->indexExists('tb_assignment_rules', 'account_id_status_priority')) {
                $db->query(
                    'ALTER TABLE tb_assignment_rules
                     ADD KEY account_id_status_priority (account_id, status, priority)'
                );
            }
        }

        // ── tb_task ──
        if ($db->tableExists('tb_task') && $db->fieldExists('vendor_account_id', 'tb_task')) {
            $this->forge->dropColumn('tb_task', 'vendor_account_id');
        }

        // ── Legacy tables (data already in tb_accounts via migration 000006) ──
        if ($db->tableExists('tb_vendor_accounts')) {
            $this->forge->dropTable('tb_vendor_accounts', true);
        }
        if ($db->tableExists('tb_office_accounts')) {
            $this->forge->dropTable('tb_office_accounts', true);
        }
    }

    public function down(): void
    {
        // Irreversible: legacy tables dropped; restore from backup if needed.
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }
        $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = $this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$table, $indexName]
        )->getRow();

        return $row !== null;
    }
}
