<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Upload status pivot: product groups × platforms × optional file types per submission.
 * file_type_id = 0 means "no file-type dimension" (groups with has_file_types = 0); avoids UNIQUE+NULL issues on MySQL.
 */
class CreateUploadStatusTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_product_groups')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
                'abbr'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'has_file_types' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'order_no'       => ['type' => 'INT', 'default' => 0],
                'status'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('status');
            $this->forge->createTable('tb_product_groups');

            $now = date('Y-m-d H:i:s');
            $this->db->table('tb_product_groups')->insertBatch([
                ['name' => 'Special NO PPT', 'abbr' => 'NOPPT', 'has_file_types' => 0, 'order_no' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'PPT Envato Element', 'abbr' => 'EE', 'has_file_types' => 1, 'order_no' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'PPT Creative Market', 'abbr' => 'CM', 'has_file_types' => 1, 'order_no' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! $this->db->tableExists('tb_platforms')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'abbr'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'icon'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'order_no'   => ['type' => 'INT', 'default' => 0],
                'status'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('abbr');
            $this->forge->createTable('tb_platforms');

            $now = date('Y-m-d H:i:s');
            $this->db->table('tb_platforms')->insertBatch([
                ['name' => 'Envato Elements', 'abbr' => 'EE', 'order_no' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'GraphicRiver', 'abbr' => 'GR', 'order_no' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Creative Market', 'abbr' => 'CM', 'order_no' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! $this->db->tableExists('tb_file_types')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'abbr'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'order_no'   => ['type' => 'INT', 'default' => 0],
                'status'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('abbr');
            $this->forge->createTable('tb_file_types');

            $now = date('Y-m-d H:i:s');
            $this->db->table('tb_file_types')->insertBatch([
                ['name' => 'PowerPoint', 'abbr' => 'PPT', 'order_no' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Keynote', 'abbr' => 'KEY', 'order_no' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Google Slides', 'abbr' => 'GSL', 'order_no' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! $this->db->tableExists('tb_submission_upload_status')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'submission_id'    => ['type' => 'INT', 'unsigned' => true],
                'product_group_id' => ['type' => 'INT', 'unsigned' => true],
                'platform_id'      => ['type' => 'INT', 'unsigned' => true],
                'file_type_id'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'status'           => [
                    'type'       => 'ENUM',
                    'constraint' => ['draft', 'uploaded', 'live', 'skip'],
                    'default'    => 'draft',
                ],
                'uploaded_at' => ['type' => 'DATETIME', 'null' => true],
                'notes'       => ['type' => 'TEXT', 'null' => true],
                'updated_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['submission_id', 'product_group_id', 'platform_id', 'file_type_id'], 'uq_submission_upload_cell');
            $this->forge->addKey('submission_id');
            $this->forge->addKey('platform_id');
            $this->forge->createTable('tb_submission_upload_status');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_submission_upload_status', true);
        $this->forge->dropTable('tb_file_types', true);
        $this->forge->dropTable('tb_platforms', true);
        $this->forge->dropTable('tb_product_groups', true);
    }
}
