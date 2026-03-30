<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Menambah grup produk contoh untuk halaman Konfigurasi upload & pivot Daftar Setor.
 *
 * Jalankan: php spark db:seed UploadConfigDummySeeder
 *
 * Idempoten: jika sudah ada grup dengan abbr DUMMY_SM, seeder tidak mengubah apa pun.
 */
class UploadConfigDummySeeder extends Seeder
{
    private const MARKER_ABBR = 'DUMMY_SM';

    public function run(): void
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('tb_product_groups')) {
            echo "UploadConfigDummySeeder: tb_product_groups tidak ada — lewati.\n";

            return;
        }

        if ($db->table('tb_product_groups')->where('abbr', self::MARKER_ABBR)->countAllResults() > 0) {
            echo "UploadConfigDummySeeder: grup contoh (abbr " . self::MARKER_ABBR . ") sudah ada — lewati.\n";

            return;
        }

        if (! $db->tableExists('tb_platforms') || ! $db->tableExists('tb_file_types')) {
            echo "UploadConfigDummySeeder: tb_platforms / tb_file_types tidak ada — lewati.\n";

            return;
        }

        $scoped = $db->fieldExists('product_group_id', 'tb_platforms');

        $now = date('Y-m-d H:i:s');
        $row = $db->table('tb_product_groups')->selectMax('order_no')->get()->getRowArray();
        $ord = (int) ($row['order_no'] ?? 0);

        $db->transStart();

        ++$ord;
        $g1 = [
            'name'           => 'SOCIAL MEDIA',
            'abbr'           => self::MARKER_ABBR,
            'has_file_types' => 1,
            'order_no'       => $ord,
            'status'         => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        if ($db->fieldExists('has_platform', 'tb_product_groups')) {
            $g1['has_platform'] = 1;
        }
        $db->table('tb_product_groups')->insert($g1);
        $gid1 = (int) $db->insertID();

        ++$ord;
        $g2 = [
            'name'           => 'PRINT & OFFSET',
            'abbr'           => 'DUMMY_PR',
            'has_file_types' => 1,
            'order_no'       => $ord,
            'status'         => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        if ($db->fieldExists('has_platform', 'tb_product_groups')) {
            $g2['has_platform'] = 1;
        }
        $db->table('tb_product_groups')->insert($g2);
        $gid2 = (int) $db->insertID();

        if ($gid1 < 1 || $gid2 < 1) {
            $db->transRollback();
            echo "UploadConfigDummySeeder: gagal insert grup.\n";

            return;
        }

        if ($scoped) {
            $this->seedScopedGroup1($db, $gid1, $now);
            $this->seedScopedGroup2($db, $gid2, $now);
        } else {
            $this->seedJunctionGroup1($db, $gid1, $now);
            $this->seedJunctionGroup2($db, $gid2, $now);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            echo "UploadConfigDummySeeder: transaksi gagal.\n";

            return;
        }

        echo "UploadConfigDummySeeder: OK — grup SOCIAL MEDIA (abbr " . self::MARKER_ABBR . ") & PRINT & OFFSET (DUMMY_PR) ditambahkan.\n";
    }

    private function seedScopedGroup1($db, int $gid, string $now): void
    {
        $platforms = [
            ['name' => 'Instagram', 'abbr' => 'IG', 'order' => 1],
            ['name' => 'Facebook', 'abbr' => 'FB', 'order' => 2],
            ['name' => 'TikTok', 'abbr' => 'TT', 'order' => 3],
        ];
        foreach ($platforms as $p) {
            $ins = [
                'product_group_id' => $gid,
                'name'             => $p['name'],
                'abbr'             => $p['abbr'],
                'order_no'         => $p['order'],
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            if ($db->fieldExists('icon', 'tb_platforms')) {
                $ins['icon'] = null;
            }
            $db->table('tb_platforms')->insert($ins);
        }

        $types = [
            ['name' => 'Feed Post', 'abbr' => 'IGPOST', 'order' => 1],
            ['name' => 'Carousel', 'abbr' => 'CARO', 'order' => 2],
            ['name' => 'Story', 'abbr' => 'STOR', 'order' => 3],
            ['name' => 'Reels / Video', 'abbr' => 'REEL', 'order' => 4],
        ];
        foreach ($types as $t) {
            $db->table('tb_file_types')->insert([
                'product_group_id' => $gid,
                'name'             => $t['name'],
                'abbr'             => $t['abbr'],
                'order_no'         => $t['order'],
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    private function seedScopedGroup2($db, int $gid, string $now): void
    {
        foreach (
            [
                ['name' => 'Digital PDF', 'abbr' => 'PDF', 'order' => 1],
                ['name' => 'Cetak', 'abbr' => 'PRT', 'order' => 2],
            ] as $p
        ) {
            $ins = [
                'product_group_id' => $gid,
                'name'             => $p['name'],
                'abbr'             => $p['abbr'],
                'order_no'         => $p['order'],
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            if ($db->fieldExists('icon', 'tb_platforms')) {
                $ins['icon'] = null;
            }
            $db->table('tb_platforms')->insert($ins);
        }

        foreach (
            [
                ['name' => 'Flyer / Brosur', 'abbr' => 'FLY', 'order' => 1],
                ['name' => 'Banner / X-Banner', 'abbr' => 'BNR', 'order' => 2],
                ['name' => 'Katalog', 'abbr' => 'KAT', 'order' => 3],
            ] as $t
        ) {
            $db->table('tb_file_types')->insert([
                'product_group_id' => $gid,
                'name'             => $t['name'],
                'abbr'             => $t['abbr'],
                'order_no'         => $t['order'],
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    /**
     * Mode master global + junction (sebelum / tanpa kolom product_group_id di master).
     */
    private function seedJunctionGroup1($db, int $gid, string $now): void
    {
        $pIds = [];
        foreach (
            [
                ['name' => 'Instagram (contoh)', 'abbr' => 'D_SM_IG'],
                ['name' => 'Facebook (contoh)', 'abbr' => 'D_SM_FB'],
                ['name' => 'TikTok (contoh)', 'abbr' => 'D_SM_TT'],
            ] as $i => $p
        ) {
            $existing = $db->table('tb_platforms')->where('abbr', $p['abbr'])->get()->getRowArray();
            if ($existing) {
                $pIds[] = (int) ($existing['id'] ?? 0);
            } else {
                $ins = [
                    'name'       => $p['name'],
                    'abbr'       => $p['abbr'],
                    'order_no'   => 900 + $i,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($db->fieldExists('icon', 'tb_platforms')) {
                    $ins['icon'] = null;
                }
                $db->table('tb_platforms')->insert($ins);
                $pIds[] = (int) $db->insertID();
            }
        }

        $fIds = [];
        foreach (
            [
                ['name' => 'Feed Post (contoh)', 'abbr' => 'D_SM_POST'],
                ['name' => 'Carousel (contoh)', 'abbr' => 'D_SM_CARO'],
                ['name' => 'Story (contoh)', 'abbr' => 'D_SM_STOR'],
            ] as $i => $t
        ) {
            $existing = $db->table('tb_file_types')->where('abbr', $t['abbr'])->get()->getRowArray();
            if ($existing) {
                $fIds[] = (int) ($existing['id'] ?? 0);
            } else {
                $db->table('tb_file_types')->insert([
                    'name'       => $t['name'],
                    'abbr'       => $t['abbr'],
                    'order_no'   => 900 + $i,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $fIds[] = (int) $db->insertID();
            }
        }

        $this->fillJunction($db, $gid, $pIds, $fIds, $now);
    }

    private function seedJunctionGroup2($db, int $gid, string $now): void
    {
        $pIds = [];
        foreach (
            [
                ['name' => 'Digital PDF (contoh)', 'abbr' => 'D_PR_PDF'],
                ['name' => 'Cetak (contoh)', 'abbr' => 'D_PR_PRT'],
            ] as $i => $p
        ) {
            $existing = $db->table('tb_platforms')->where('abbr', $p['abbr'])->get()->getRowArray();
            if ($existing) {
                $pIds[] = (int) ($existing['id'] ?? 0);
            } else {
                $ins = [
                    'name'       => $p['name'],
                    'abbr'       => $p['abbr'],
                    'order_no'   => 910 + $i,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($db->fieldExists('icon', 'tb_platforms')) {
                    $ins['icon'] = null;
                }
                $db->table('tb_platforms')->insert($ins);
                $pIds[] = (int) $db->insertID();
            }
        }

        $fIds = [];
        foreach (
            [
                ['name' => 'Flyer (contoh)', 'abbr' => 'D_PR_FLY'],
                ['name' => 'Banner (contoh)', 'abbr' => 'D_PR_BNR'],
            ] as $i => $t
        ) {
            $existing = $db->table('tb_file_types')->where('abbr', $t['abbr'])->get()->getRowArray();
            if ($existing) {
                $fIds[] = (int) ($existing['id'] ?? 0);
            } else {
                $db->table('tb_file_types')->insert([
                    'name'       => $t['name'],
                    'abbr'       => $t['abbr'],
                    'order_no'   => 910 + $i,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $fIds[] = (int) $db->insertID();
            }
        }

        $this->fillJunction($db, $gid, $pIds, $fIds, $now);
    }

    /**
     * @param list<int> $platformIds
     * @param list<int> $fileTypeIds
     */
    private function fillJunction($db, int $gid, array $platformIds, array $fileTypeIds, string $now): void
    {
        $platformIds = array_values(array_filter($platformIds, static fn (int $id): bool => $id > 0));
        $fileTypeIds = array_values(array_filter($fileTypeIds, static fn (int $id): bool => $id > 0));

        if ($db->tableExists('tb_product_group_platforms') && $platformIds !== []) {
            $db->table('tb_product_group_platforms')->where('product_group_id', $gid)->delete();
            $o = 0;
            foreach ($platformIds as $pid) {
                ++$o;
                $db->table('tb_product_group_platforms')->insert([
                    'product_group_id' => $gid,
                    'platform_id'      => $pid,
                    'order_no'         => $o,
                    'created_at'       => $now,
                ]);
            }
        }

        if ($db->tableExists('tb_product_group_file_types') && $fileTypeIds !== []) {
            $db->table('tb_product_group_file_types')->where('product_group_id', $gid)->delete();
            $o = 0;
            foreach ($fileTypeIds as $fid) {
                ++$o;
                $db->table('tb_product_group_file_types')->insert([
                    'product_group_id' => $gid,
                    'file_type_id'     => $fid,
                    'order_no'         => $o,
                    'created_at'       => $now,
                ]);
            }
        }
    }
}
