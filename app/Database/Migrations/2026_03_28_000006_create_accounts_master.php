<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountsMaster extends Migration
{
    public function up(): void
    {
        // 1) Create master table
        if (!$this->db->tableExists('tb_accounts')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'             => ['type' => 'VARCHAR', 'constraint' => 140],
                'type'             => ['type' => 'ENUM', 'constraint' => ['office', 'vendor'], 'default' => 'vendor'],
                'status'           => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],

                // vendor-ish metadata (optional for office)
                'platform'         => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'owner_name'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'contract_mode'    => ['type' => 'ENUM', 'constraint' => ['monthly', 'lifetime', 'on_demand'], 'null' => true],
                'next_review_date' => ['type' => 'DATE', 'null' => true],
                'contract_end_date'=> ['type' => 'DATE', 'null' => true],
                'notes'            => ['type' => 'TEXT', 'null' => true],
                'created_by'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                // legacy mapping so we can backfill foreign keys safely
                'legacy_vendor_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'legacy_office_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['type', 'name']);
            $this->forge->addUniqueKey('legacy_vendor_id');
            $this->forge->addUniqueKey('legacy_office_id');
            $this->forge->addKey(['type', 'status']);
            $this->forge->createTable('tb_accounts');
        }

        // 2) Add account_id columns to vendor-management tables and tb_task
        if ($this->db->tableExists('tb_vendor_targets') && !$this->db->fieldExists('account_id', 'tb_vendor_targets')) {
            $this->forge->addColumn('tb_vendor_targets', [
                'account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'vendor_account_id'],
            ]);
        }
        if ($this->db->tableExists('tb_vendor_allocations') && !$this->db->fieldExists('account_id', 'tb_vendor_allocations')) {
            $this->forge->addColumn('tb_vendor_allocations', [
                'account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'vendor_account_id'],
            ]);
        }
        if ($this->db->tableExists('tb_assignment_rules') && !$this->db->fieldExists('account_id', 'tb_assignment_rules')) {
            $this->forge->addColumn('tb_assignment_rules', [
                'account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'vendor_account_id'],
            ]);
        }
        if ($this->db->tableExists('tb_task') && !$this->db->fieldExists('account_id', 'tb_task')) {
            // This is "vendor account" for assignment/scope (not the dynamic field `account`)
            $this->forge->addColumn('tb_task', [
                'account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'vendor_account_id'],
            ]);
        }

        // 3) Migrate legacy rows into tb_accounts and backfill account_id columns
        $now = date('Y-m-d H:i:s');

        if ($this->db->tableExists('tb_vendor_accounts')) {
            $vendors = $this->db->table('tb_vendor_accounts')->get()->getResultArray();
            foreach ($vendors as $v) {
                $legacyId = (int) ($v['id'] ?? 0);
                if ($legacyId <= 0) continue;

                $exists = $this->db->table('tb_accounts')->where('legacy_vendor_id', $legacyId)->countAllResults();
                if ((int) $exists > 0) continue;

                $this->db->table('tb_accounts')->insert([
                    'name'             => (string) ($v['name'] ?? ''),
                    'type'             => 'vendor',
                    'status'           => (string) ($v['status'] ?? 'active'),
                    'platform'         => $v['platform'] ?? null,
                    'owner_name'       => $v['owner_name'] ?? null,
                    'contract_mode'    => $v['contract_mode'] ?? null,
                    'next_review_date' => $v['next_review_date'] ?? null,
                    'contract_end_date'=> $v['contract_end_date'] ?? null,
                    'notes'            => $v['notes'] ?? null,
                    'created_by'       => $v['created_by'] ?? null,
                    'legacy_vendor_id' => $legacyId,
                    'created_at'       => $v['created_at'] ?? $now,
                    'updated_at'       => $v['updated_at'] ?? $now,
                ]);
            }

            // Backfill account_id for vendor-management tables
            if ($this->db->tableExists('tb_vendor_targets') && $this->db->fieldExists('account_id', 'tb_vendor_targets')) {
                $rows = $this->db->table('tb_vendor_targets')->select('id, vendor_account_id')->get()->getResultArray();
                foreach ($rows as $r) {
                    $vid = (int) ($r['vendor_account_id'] ?? 0);
                    if ($vid <= 0) continue;
                    $acc = $this->db->table('tb_accounts')->select('id')->where('legacy_vendor_id', $vid)->get()->getRowArray();
                    if (!$acc) continue;
                    $this->db->table('tb_vendor_targets')->where('id', (int) $r['id'])->update(['account_id' => (int) $acc['id']]);
                }
            }
            if ($this->db->tableExists('tb_vendor_allocations') && $this->db->fieldExists('account_id', 'tb_vendor_allocations')) {
                $rows = $this->db->table('tb_vendor_allocations')->select('id, vendor_account_id')->get()->getResultArray();
                foreach ($rows as $r) {
                    $vid = (int) ($r['vendor_account_id'] ?? 0);
                    if ($vid <= 0) continue;
                    $acc = $this->db->table('tb_accounts')->select('id')->where('legacy_vendor_id', $vid)->get()->getRowArray();
                    if (!$acc) continue;
                    $this->db->table('tb_vendor_allocations')->where('id', (int) $r['id'])->update(['account_id' => (int) $acc['id']]);
                }
            }
            if ($this->db->tableExists('tb_assignment_rules') && $this->db->fieldExists('account_id', 'tb_assignment_rules')) {
                $rows = $this->db->table('tb_assignment_rules')->select('id, vendor_account_id')->get()->getResultArray();
                foreach ($rows as $r) {
                    $vid = (int) ($r['vendor_account_id'] ?? 0);
                    if ($vid <= 0) continue;
                    $acc = $this->db->table('tb_accounts')->select('id')->where('legacy_vendor_id', $vid)->get()->getRowArray();
                    if (!$acc) continue;
                    $this->db->table('tb_assignment_rules')->where('id', (int) $r['id'])->update(['account_id' => (int) $acc['id']]);
                }
            }
            if ($this->db->tableExists('tb_task') && $this->db->fieldExists('account_id', 'tb_task') && $this->db->fieldExists('vendor_account_id', 'tb_task')) {
                $rows = $this->db->table('tb_task')->select('id, vendor_account_id')->get()->getResultArray();
                foreach ($rows as $r) {
                    $vid = (int) ($r['vendor_account_id'] ?? 0);
                    if ($vid <= 0) continue;
                    $acc = $this->db->table('tb_accounts')->select('id')->where('legacy_vendor_id', $vid)->get()->getRowArray();
                    if (!$acc) continue;
                    $this->db->table('tb_task')->where('id', (int) $r['id'])->update(['account_id' => (int) $acc['id']]);
                }
            }
        }

        if ($this->db->tableExists('tb_office_accounts')) {
            $offices = $this->db->table('tb_office_accounts')->get()->getResultArray();
            foreach ($offices as $o) {
                $legacyId = (int) ($o['id'] ?? 0);
                if ($legacyId <= 0) continue;

                $exists = $this->db->table('tb_accounts')->where('legacy_office_id', $legacyId)->countAllResults();
                if ((int) $exists > 0) continue;

                $this->db->table('tb_accounts')->insert([
                    'name'             => (string) ($o['name'] ?? ''),
                    'type'             => 'office',
                    'status'           => (string) ($o['status'] ?? 'active'),
                    'notes'            => $o['notes'] ?? null,
                    'legacy_office_id' => $legacyId,
                    'created_at'       => $o['created_at'] ?? $now,
                    'updated_at'       => $o['updated_at'] ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('tb_vendor_targets') && $this->db->fieldExists('account_id', 'tb_vendor_targets')) {
            $this->forge->dropColumn('tb_vendor_targets', 'account_id');
        }
        if ($this->db->tableExists('tb_vendor_allocations') && $this->db->fieldExists('account_id', 'tb_vendor_allocations')) {
            $this->forge->dropColumn('tb_vendor_allocations', 'account_id');
        }
        if ($this->db->tableExists('tb_assignment_rules') && $this->db->fieldExists('account_id', 'tb_assignment_rules')) {
            $this->forge->dropColumn('tb_assignment_rules', 'account_id');
        }
        if ($this->db->tableExists('tb_task') && $this->db->fieldExists('account_id', 'tb_task')) {
            $this->forge->dropColumn('tb_task', 'account_id');
        }
        $this->forge->dropTable('tb_accounts', true);
    }
}

