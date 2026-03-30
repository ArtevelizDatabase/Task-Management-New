<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti ENUM status pivot menjadi VARCHAR agar mendukung workflow review (under_review, soft_reject, reject).
 * Memetakan nilai lama: live → uploaded, skip → soft_reject.
 */
class UploadStatusVarchar extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_submission_upload_status')) {
            return;
        }

        $p   = $this->db->DBPrefix;
        $tbl = $p . 'tb_submission_upload_status';

        $this->db->query("UPDATE {$tbl} SET status = 'uploaded' WHERE status = 'live'");
        $this->db->query("UPDATE {$tbl} SET status = 'soft_reject' WHERE status = 'skip'");

        $this->db->query("ALTER TABLE {$tbl} MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! $this->db->tableExists('tb_submission_upload_status')) {
            return;
        }

        $p   = $this->db->DBPrefix;
        $tbl = $p . 'tb_submission_upload_status';

        $this->db->query("UPDATE {$tbl} SET status = 'skip' WHERE status = 'soft_reject'");
        $this->db->query("UPDATE {$tbl} SET status = 'draft' WHERE status IN ('under_review','reject')");

        $this->db->query("ALTER TABLE {$tbl} MODIFY COLUMN status ENUM('draft','uploaded','live','skip') NOT NULL DEFAULT 'draft'");
    }
}
