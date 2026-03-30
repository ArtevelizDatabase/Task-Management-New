<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddContractFieldsToVendorAccounts extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_vendor_accounts')) {
            return;
        }

        if (!$this->db->fieldExists('contract_mode', 'tb_vendor_accounts')) {
            $this->forge->addColumn('tb_vendor_accounts', [
                'contract_mode' => [
                    'type'       => 'ENUM',
                    'constraint' => ['monthly', 'lifetime', 'on_demand'],
                    'default'    => 'monthly',
                    'after'      => 'status',
                ],
            ]);
        }

        if (!$this->db->fieldExists('next_review_date', 'tb_vendor_accounts')) {
            $this->forge->addColumn('tb_vendor_accounts', [
                'next_review_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'contract_mode',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('tb_vendor_accounts') && $this->db->fieldExists('next_review_date', 'tb_vendor_accounts')) {
            $this->forge->dropColumn('tb_vendor_accounts', 'next_review_date');
        }
        if ($this->db->tableExists('tb_vendor_accounts') && $this->db->fieldExists('contract_mode', 'tb_vendor_accounts')) {
            $this->forge->dropColumn('tb_vendor_accounts', 'contract_mode');
        }
    }
}
