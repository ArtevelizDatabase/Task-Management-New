<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddContractEndDateToVendorAccounts extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tb_vendor_accounts')) {
            return;
        }

        if (!$this->db->fieldExists('contract_end_date', 'tb_vendor_accounts')) {
            $this->forge->addColumn('tb_vendor_accounts', [
                'contract_end_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'next_review_date',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('tb_vendor_accounts') && $this->db->fieldExists('contract_end_date', 'tb_vendor_accounts')) {
            $this->forge->dropColumn('tb_vendor_accounts', 'contract_end_date');
        }
    }
}
