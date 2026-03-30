<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-group subset of platforms and file types for upload-status pivot.
 */
class ProductGroupJunctions extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tb_product_group_platforms')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'product_group_id' => ['type' => 'INT', 'unsigned' => true],
                'platform_id'      => ['type' => 'INT', 'unsigned' => true],
                'order_no'         => ['type' => 'INT', 'default' => 0],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['product_group_id', 'platform_id'], 'uq_pg_platform');
            $this->forge->addKey('product_group_id');
            $this->forge->createTable('tb_product_group_platforms');
        }

        if (! $this->db->tableExists('tb_product_group_file_types')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'product_group_id' => ['type' => 'INT', 'unsigned' => true],
                'file_type_id'     => ['type' => 'INT', 'unsigned' => true],
                'order_no'         => ['type' => 'INT', 'default' => 0],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['product_group_id', 'file_type_id'], 'uq_pg_filetype');
            $this->forge->addKey('product_group_id');
            $this->forge->createTable('tb_product_group_file_types');
        }

        if (! $this->db->tableExists('tb_product_groups')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $platforms = $this->db->tableExists('tb_platforms')
            ? $this->db->table('tb_platforms')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray()
            : [];
        $fileTypes = $this->db->tableExists('tb_file_types')
            ? $this->db->table('tb_file_types')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray()
            : [];

        $groups = $this->db->table('tb_product_groups')->get()->getResultArray();

        foreach ($groups as $g) {
            $gid = (int) ($g['id'] ?? 0);
            if ($gid < 1) {
                continue;
            }
            $hp = (int) ($g['has_platform'] ?? 1) === 1;
            $hf = (int) ($g['has_file_types'] ?? 0) === 1;

            if ($hp && $platforms !== [] && $this->db->table('tb_product_group_platforms')->where('product_group_id', $gid)->countAllResults() === 0) {
                $ord = 0;
                foreach ($platforms as $p) {
                    $pid = (int) ($p['id'] ?? 0);
                    if ($pid < 1) {
                        continue;
                    }
                    ++$ord;
                    $this->db->table('tb_product_group_platforms')->insert([
                        'product_group_id' => $gid,
                        'platform_id'      => $pid,
                        'order_no'         => $ord,
                        'created_at'       => $now,
                    ]);
                }
            }

            if ($hf && $fileTypes !== [] && $this->db->table('tb_product_group_file_types')->where('product_group_id', $gid)->countAllResults() === 0) {
                $ord = 0;
                foreach ($fileTypes as $f) {
                    $fid = (int) ($f['id'] ?? 0);
                    if ($fid < 1) {
                        continue;
                    }
                    ++$ord;
                    $this->db->table('tb_product_group_file_types')->insert([
                        'product_group_id' => $gid,
                        'file_type_id'     => $fid,
                        'order_no'         => $ord,
                        'created_at'       => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('tb_product_group_file_types', true);
        $this->forge->dropTable('tb_product_group_platforms', true);
    }
}
