<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class UploadConfig extends Controller
{
    private function requirePerm(string $perm): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        if ($role === 'member') {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }

    private function uploadUsesScopedRows(): bool
    {
        $db = \Config\Database::connect();

        return $db->tableExists('tb_platforms') && $db->fieldExists('product_group_id', 'tb_platforms');
    }

    public function index(): string
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();

        $groups = $db->tableExists('tb_product_groups')
            ? $db->table('tb_product_groups')->orderBy('order_no', 'ASC')->get()->getResultArray()
            : [];

        $scoped              = $this->uploadUsesScopedRows();
        $groupPlatforms      = [];
        $groupFileTypes      = [];
        $groupPlatformAssign = [];
        $groupFileTypeAssign = [];

        if ($scoped) {
            foreach ($groups as $g) {
                $gid                   = (int) ($g['id'] ?? 0);
                $groupPlatforms[$gid]  = $gid > 0
                    ? $db->table('tb_platforms')->where('product_group_id', $gid)->orderBy('order_no', 'ASC')->get()->getResultArray()
                    : [];
                $groupFileTypes[$gid] = $gid > 0
                    ? $db->table('tb_file_types')->where('product_group_id', $gid)->orderBy('order_no', 'ASC')->get()->getResultArray()
                    : [];
                foreach ($groupPlatforms[$gid] as $p) {
                    if ((int) ($p['status'] ?? 0) === 1) {
                        $groupPlatformAssign[$gid][] = (int) ($p['id'] ?? 0);
                    }
                }
                foreach ($groupFileTypes[$gid] as $f) {
                    if ((int) ($f['status'] ?? 0) === 1) {
                        $groupFileTypeAssign[$gid][] = (int) ($f['id'] ?? 0);
                    }
                }
            }
        } elseif ($db->tableExists('tb_product_group_platforms')) {
            foreach ($db->table('tb_product_group_platforms')->orderBy('order_no', 'ASC')->get()->getResultArray() as $r) {
                $gid = (int) ($r['product_group_id'] ?? 0);
                if ($gid > 0) {
                    $groupPlatformAssign[$gid][] = (int) ($r['platform_id'] ?? 0);
                }
            }
        }
        if (! $scoped && $db->tableExists('tb_product_group_file_types')) {
            foreach ($db->table('tb_product_group_file_types')->orderBy('order_no', 'ASC')->get()->getResultArray() as $r) {
                $gid = (int) ($r['product_group_id'] ?? 0);
                if ($gid > 0) {
                    $groupFileTypeAssign[$gid][] = (int) ($r['file_type_id'] ?? 0);
                }
            }
        }

        $junctionReady = ! $scoped
            && $db->tableExists('tb_product_group_platforms')
            && $db->tableExists('tb_product_group_file_types');

        $platforms = $scoped ? [] : ($db->tableExists('tb_platforms')
            ? $db->table('tb_platforms')->orderBy('order_no', 'ASC')->get()->getResultArray()
            : []);
        $fileTypes = $scoped ? [] : ($db->tableExists('tb_file_types')
            ? $db->table('tb_file_types')->orderBy('order_no', 'ASC')->get()->getResultArray()
            : []);

        $scopedPlatformsFlat = [];
        $scopedFileTypesFlat = [];
        if ($scoped) {
            foreach ($groups as $g) {
                $gn  = (string) ($g['name'] ?? '');
                $gid = (int) ($g['id'] ?? 0);
                foreach ($groupPlatforms[$gid] ?? [] as $p) {
                    $scopedPlatformsFlat[] = array_merge($p, ['_group_label' => $gn]);
                }
                foreach ($groupFileTypes[$gid] ?? [] as $f) {
                    $scopedFileTypesFlat[] = array_merge($f, ['_group_label' => $gn]);
                }
            }
        }

        return view('layouts/main', [
            'title'   => 'Upload Config',
            'content' => view('settings/upload_config', [
                'groups'              => $groups,
                'platforms'           => $platforms,
                'fileTypes'           => $fileTypes,
                'groupPlatforms'      => $groupPlatforms,
                'groupFileTypes'      => $groupFileTypes,
                'groupPlatformAssign' => $groupPlatformAssign,
                'groupFileTypeAssign' => $groupFileTypeAssign,
                'junctionReady'         => $junctionReady || $scoped,
                'scopedUpload'          => $scoped,
                'scopedPlatformsFlat'   => $scopedPlatformsFlat,
                'scopedFileTypesFlat'   => $scopedFileTypesFlat,
            ]),
        ]);
    }

    public function storeGroup(): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama grup wajib diisi.');
        }
        $row    = $db->table('tb_product_groups')->selectMax('order_no')->get()->getRowArray();
        $maxOrd = (int) ($row['order_no'] ?? 0);

        $insert = [
            'name'           => $name,
            'abbr'           => trim((string) $this->request->getPost('abbr')) ?: null,
            'has_file_types' => $this->request->getPost('has_file_types') ? 1 : 0,
            'order_no'       => $maxOrd + 1,
            'status'         => 1,
            'created_by'     => session()->get('user_id'),
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        $hasPt = 1;
        if ($db->fieldExists('has_platform', 'tb_product_groups')) {
            $hasPt                   = $this->request->getPost('has_platform') ? 1 : 0;
            $insert['has_platform'] = $hasPt;
        }
        $hasFt         = (int) ($insert['has_file_types'] ?? 0);
        $scoped        = $this->uploadUsesScopedRows();
        $junctionReady = ! $scoped
            && $db->tableExists('tb_product_group_platforms')
            && $db->tableExists('tb_product_group_file_types');

        $db->transStart();
        $db->table('tb_product_groups')->insert($insert);
        $newId = (int) $db->insertID();

        if ($newId > 0) {
            if ($scoped) {
                if ($hasPt === 1) {
                    $pnames = $this->request->getPost('new_platform_name');
                    $pabbrs = $this->request->getPost('new_platform_abbr');
                    $nP     = $this->insertScopedPlatformsFromPairs(
                        $db,
                        $newId,
                        is_array($pnames) ? $pnames : [],
                        is_array($pabbrs) ? $pabbrs : []
                    );
                    if ($nP < 1) {
                        $db->transRollback();

                        return redirect()->back()->with('error', 'Isi minimal satu platform (nama + singkatan) untuk grup dengan kolom platform.');
                    }
                }
                if ($hasFt === 1) {
                    $fnames = $this->request->getPost('new_file_type_name');
                    $fabbrs = $this->request->getPost('new_file_type_abbr');
                    $nF     = $this->insertScopedFileTypesFromPairs(
                        $db,
                        $newId,
                        is_array($fnames) ? $fnames : [],
                        is_array($fabbrs) ? $fabbrs : []
                    );
                    if ($nF < 1) {
                        $db->transRollback();

                        return redirect()->back()->with('error', 'Isi minimal satu tipe file (nama + singkatan) untuk grup dengan kolom tipe file.');
                    }
                }
            } elseif ($junctionReady) {
                if ($hasPt === 1) {
                    $rawP = $this->request->getPost('platform_ids');
                    $pids = $this->normalizePostedIntIds(is_array($rawP) ? $rawP : []);
                    $nP   = $this->replaceGroupPlatformJunction($db, $newId, $pids);
                    if ($nP < 1) {
                        $this->backfillPlatformsForGroup($db, $newId);
                    }
                }
                if ($hasFt === 1) {
                    $rawF = $this->request->getPost('file_type_ids');
                    $fids = $this->normalizePostedIntIds(is_array($rawF) ? $rawF : []);
                    $nF   = $this->replaceGroupFileTypeJunction($db, $newId, $fids);
                    if ($nF < 1) {
                        $this->backfillFileTypesForGroup($db, $newId);
                    }
                }
            } else {
                if ($hasPt === 1) {
                    $this->backfillPlatformsForGroup($db, $newId);
                }
                if ($hasFt === 1) {
                    $this->backfillFileTypesForGroup($db, $newId);
                }
            }
        }
        $db->transComplete();

        if (! $db->transStatus() || $newId < 1) {
            return redirect()->back()->with('error', 'Gagal menambahkan grup.');
        }

        return redirect()->to('/settings/upload-config')->with('success', 'Grup beserta platform/tipe file untuk pivot disimpan.');
    }

    public function toggleGroup(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_product_groups')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Grup tidak ditemukan.');
        }
        $db->table('tb_product_groups')->where('id', $id)->update([
            'status'     => (int) ($row['status'] ?? 0) === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Status grup diperbarui.');
    }

    public function storePlatform(): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        if (! $db->tableExists('tb_platforms')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $name = trim((string) $this->request->getPost('name'));
        $abbr = strtoupper(trim((string) $this->request->getPost('abbr')));
        if ($name === '' || $abbr === '') {
            return redirect()->back()->with('error', 'Nama dan singkatan platform wajib diisi.');
        }
        if ($this->uploadUsesScopedRows()) {
            $gid = (int) $this->request->getPost('product_group_id');
            if ($gid < 1 || ! $db->table('tb_product_groups')->where('id', $gid)->get()->getRowArray()) {
                return redirect()->back()->with('error', 'Grup tidak valid untuk menambah platform.');
            }
            if ($db->table('tb_platforms')->where('product_group_id', $gid)->where('abbr', $abbr)->countAllResults() > 0) {
                return redirect()->back()->with('error', 'Singkatan platform sudah dipakai di grup ini.');
            }
            $row    = $db->table('tb_platforms')->where('product_group_id', $gid)->selectMax('order_no')->get()->getRowArray();
            $maxOrd = (int) ($row['order_no'] ?? 0);
            $db->table('tb_platforms')->insert([
                'product_group_id' => $gid,
                'name'             => $name,
                'abbr'             => $abbr,
                'order_no'         => $maxOrd + 1,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            return redirect()->to('/settings/upload-config')->with('success', 'Platform ditambahkan untuk grup.');
        }
        $dup = $db->table('tb_platforms')->where('abbr', $abbr)->countAllResults();
        if ($dup > 0) {
            return redirect()->back()->with('error', 'Singkatan platform sudah dipakai.');
        }
        $row    = $db->table('tb_platforms')->selectMax('order_no')->get()->getRowArray();
        $maxOrd = (int) ($row['order_no'] ?? 0);
        $db->table('tb_platforms')->insert([
            'name'       => $name,
            'abbr'       => $abbr,
            'order_no'   => $maxOrd + 1,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Platform ditambahkan.');
    }

    public function storeFileType(): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        if (! $db->tableExists('tb_file_types')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $name = trim((string) $this->request->getPost('name'));
        $abbr = strtoupper(trim((string) $this->request->getPost('abbr')));
        if ($name === '' || $abbr === '') {
            return redirect()->back()->with('error', 'Nama dan singkatan tipe file wajib diisi.');
        }
        if ($this->uploadUsesScopedRows()) {
            $gid = (int) $this->request->getPost('product_group_id');
            if ($gid < 1 || ! $db->table('tb_product_groups')->where('id', $gid)->get()->getRowArray()) {
                return redirect()->back()->with('error', 'Grup tidak valid untuk menambah tipe file.');
            }
            if ($db->table('tb_file_types')->where('product_group_id', $gid)->where('abbr', $abbr)->countAllResults() > 0) {
                return redirect()->back()->with('error', 'Singkatan tipe file sudah dipakai di grup ini.');
            }
            $row    = $db->table('tb_file_types')->where('product_group_id', $gid)->selectMax('order_no')->get()->getRowArray();
            $maxOrd = (int) ($row['order_no'] ?? 0);
            $db->table('tb_file_types')->insert([
                'product_group_id' => $gid,
                'name'             => $name,
                'abbr'             => $abbr,
                'order_no'         => $maxOrd + 1,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            return redirect()->to('/settings/upload-config')->with('success', 'Tipe file ditambahkan untuk grup.');
        }
        $dup = $db->table('tb_file_types')->where('abbr', $abbr)->countAllResults();
        if ($dup > 0) {
            return redirect()->back()->with('error', 'Singkatan tipe file sudah dipakai.');
        }
        $row    = $db->table('tb_file_types')->selectMax('order_no')->get()->getRowArray();
        $maxOrd = (int) ($row['order_no'] ?? 0);
        $db->table('tb_file_types')->insert([
            'name'       => $name,
            'abbr'       => $abbr,
            'order_no'   => $maxOrd + 1,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Tipe file ditambahkan.');
    }

    public function updateGroup(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_product_groups')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Grup tidak ditemukan.');
        }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Nama grup wajib diisi.');
        }
        $hasFt = $this->request->getPost('has_file_types') ? 1 : 0;
        $hasPt = $db->fieldExists('has_platform', 'tb_product_groups')
            ? ($this->request->getPost('has_platform') ? 1 : 0)
            : 1;
        $order = (int) $this->request->getPost('order_no');
        if ($order < 0) {
            $order = 0;
        }

        $oldFt = (int) ($row['has_file_types'] ?? 0);
        $oldPt = (int) ($row['has_platform'] ?? 1);

        $db->transStart();
        if ($db->tableExists('tb_submission_upload_status')
            && ($oldFt !== $hasFt || $oldPt !== $hasPt)) {
            $db->table('tb_submission_upload_status')->where('product_group_id', $id)->delete();
        }
        $update = [
            'name'           => $name,
            'abbr'           => trim((string) $this->request->getPost('abbr')) ?: null,
            'has_file_types' => $hasFt,
            'order_no'       => $order,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if ($db->fieldExists('has_platform', 'tb_product_groups')) {
            $update['has_platform'] = $hasPt;
        }
        $db->table('tb_product_groups')->where('id', $id)->update($update);

        if ($this->uploadUsesScopedRows()) {
            if ($hasPt === 0) {
                $db->table('tb_platforms')->where('product_group_id', $id)->delete();
            }
            if ($hasFt === 0) {
                $db->table('tb_file_types')->where('product_group_id', $id)->delete();
            }
        } else {
            if ($db->tableExists('tb_product_group_platforms')) {
                if ($hasPt === 0) {
                    $db->table('tb_product_group_platforms')->where('product_group_id', $id)->delete();
                } elseif ($oldPt === 0 && $hasPt === 1) {
                    $this->backfillPlatformsForGroup($db, $id);
                }
            }
            if ($db->tableExists('tb_product_group_file_types')) {
                if ($hasFt === 0) {
                    $db->table('tb_product_group_file_types')->where('product_group_id', $id)->delete();
                } elseif ($oldFt === 0 && $hasFt === 1) {
                    $this->backfillFileTypesForGroup($db, $id);
                }
            }
        }

        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Grup diperbarui.')
            : redirect()->back()->with('error', 'Gagal memperbarui grup.');
    }

    public function deleteGroup(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_product_groups')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Grup tidak ditemukan.');
        }
        $db->transStart();
        if ($db->tableExists('tb_submission_upload_status')) {
            $db->table('tb_submission_upload_status')->where('product_group_id', $id)->delete();
        }
        if ($this->uploadUsesScopedRows()) {
            if ($db->tableExists('tb_platforms')) {
                $db->table('tb_platforms')->where('product_group_id', $id)->delete();
            }
            if ($db->tableExists('tb_file_types')) {
                $db->table('tb_file_types')->where('product_group_id', $id)->delete();
            }
        }
        if ($db->tableExists('tb_product_group_platforms')) {
            $db->table('tb_product_group_platforms')->where('product_group_id', $id)->delete();
        }
        if ($db->tableExists('tb_product_group_file_types')) {
            $db->table('tb_product_group_file_types')->where('product_group_id', $id)->delete();
        }
        $db->table('tb_product_groups')->where('id', $id)->delete();
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Grup dihapus.')
            : redirect()->back()->with('error', 'Gagal menghapus grup.');
    }

    public function saveGroupPlatforms(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')) {
            return redirect()->back()->with('error', 'Tabel grup belum tersedia.');
        }
        $row = $db->table('tb_product_groups')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Grup tidak ditemukan.');
        }
        if ((int) ($row['has_platform'] ?? 1) !== 1) {
            return redirect()->back()->with('error', 'Grup ini tidak memakai kolom platform.');
        }
        $raw = $this->request->getPost('platform_ids');
        $ids = $this->normalizePostedIntIds(is_array($raw) ? $raw : []);
        $db->transStart();
        if ($this->uploadUsesScopedRows()) {
            $this->syncScopedPlatformsActive($db, $id, $ids);
        } elseif ($db->tableExists('tb_product_group_platforms')) {
            $this->replaceGroupPlatformJunction($db, $id, $ids);
        }
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Platform untuk grup disimpan (urutan kolom = urutan daftar yang dicentang).')
            : redirect()->back()->with('error', 'Gagal menyimpan assignment platform.');
    }

    public function saveGroupFileTypes(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')) {
            return redirect()->back()->with('error', 'Tabel grup belum tersedia.');
        }
        $row = $db->table('tb_product_groups')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Grup tidak ditemukan.');
        }
        if ((int) ($row['has_file_types'] ?? 0) !== 1) {
            return redirect()->back()->with('error', 'Grup ini tidak memakai kolom tipe file.');
        }
        $raw = $this->request->getPost('file_type_ids');
        $ids = $this->normalizePostedIntIds(is_array($raw) ? $raw : []);
        $db->transStart();
        if ($this->uploadUsesScopedRows()) {
            $this->syncScopedFileTypesActive($db, $id, $ids);
        } elseif ($db->tableExists('tb_product_group_file_types')) {
            $this->replaceGroupFileTypeJunction($db, $id, $ids);
        }
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Tipe file untuk grup disimpan (urutan kolom = urutan daftar yang dicentang).')
            : redirect()->back()->with('error', 'Gagal menyimpan assignment tipe file.');
    }

    public function updatePlatform(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_platforms')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_platforms')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Platform tidak ditemukan.');
        }
        $name = trim((string) $this->request->getPost('name'));
        $abbr = strtoupper(trim((string) $this->request->getPost('abbr')));
        if ($name === '' || $abbr === '') {
            return redirect()->back()->with('error', 'Nama dan singkatan wajib diisi.');
        }
        if ($this->uploadUsesScopedRows()) {
            $gid = (int) ($row['product_group_id'] ?? 0);
            $dup = $db->table('tb_platforms')->where('abbr', $abbr)->where('product_group_id', $gid)->where('id !=', $id)->countAllResults();
        } else {
            $dup = $db->table('tb_platforms')->where('abbr', $abbr)->where('id !=', $id)->countAllResults();
        }
        if ($dup > 0) {
            return redirect()->back()->with('error', 'Singkatan sudah dipakai platform lain.');
        }
        $order = (int) $this->request->getPost('order_no');
        if ($order < 0) {
            $order = 0;
        }
        $db->table('tb_platforms')->where('id', $id)->update([
            'name'       => $name,
            'abbr'       => $abbr,
            'order_no'   => $order,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Platform diperbarui.');
    }

    public function deletePlatform(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_platforms')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        if (! $db->table('tb_platforms')->where('id', $id)->get()->getRowArray()) {
            return redirect()->back()->with('error', 'Platform tidak ditemukan.');
        }
        $db->transStart();
        if ($db->tableExists('tb_submission_upload_status')) {
            $db->table('tb_submission_upload_status')->where('platform_id', $id)->delete();
        }
        if ($db->tableExists('tb_product_group_platforms')) {
            $db->table('tb_product_group_platforms')->where('platform_id', $id)->delete();
        }
        $db->table('tb_platforms')->where('id', $id)->delete();
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Platform dihapus.')
            : redirect()->back()->with('error', 'Gagal menghapus platform.');
    }

    public function togglePlatform(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_platforms')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_platforms')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Platform tidak ditemukan.');
        }
        $db->table('tb_platforms')->where('id', $id)->update([
            'status'     => (int) ($row['status'] ?? 0) === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Status platform diperbarui.');
    }

    public function updateFileType(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_file_types')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_file_types')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Tipe file tidak ditemukan.');
        }
        $name = trim((string) $this->request->getPost('name'));
        $abbr = strtoupper(trim((string) $this->request->getPost('abbr')));
        if ($name === '' || $abbr === '') {
            return redirect()->back()->with('error', 'Nama dan singkatan wajib diisi.');
        }
        if ($this->uploadUsesScopedRows()) {
            $gid = (int) ($row['product_group_id'] ?? 0);
            $dup = $db->table('tb_file_types')->where('abbr', $abbr)->where('product_group_id', $gid)->where('id !=', $id)->countAllResults();
        } else {
            $dup = $db->table('tb_file_types')->where('abbr', $abbr)->where('id !=', $id)->countAllResults();
        }
        if ($dup > 0) {
            return redirect()->back()->with('error', 'Singkatan sudah dipakai tipe lain.');
        }
        $order = (int) $this->request->getPost('order_no');
        if ($order < 0) {
            $order = 0;
        }
        $db->table('tb_file_types')->where('id', $id)->update([
            'name'       => $name,
            'abbr'       => $abbr,
            'order_no'   => $order,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Tipe file diperbarui.');
    }

    public function deleteFileType(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $id || $id < 1) {
            return redirect()->back()->with('error', 'ID tidak valid.');
        }
        if (! $db->tableExists('tb_file_types')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        if (! $db->table('tb_file_types')->where('id', $id)->get()->getRowArray()) {
            return redirect()->back()->with('error', 'Tipe file tidak ditemukan.');
        }
        $db->transStart();
        if ($db->tableExists('tb_submission_upload_status')) {
            $db->table('tb_submission_upload_status')->where('file_type_id', $id)->delete();
        }
        if ($db->tableExists('tb_product_group_file_types')) {
            $db->table('tb_product_group_file_types')->where('file_type_id', $id)->delete();
        }
        $db->table('tb_file_types')->where('id', $id)->delete();
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->to('/settings/upload-config')->with('success', 'Tipe file dihapus.')
            : redirect()->back()->with('error', 'Gagal menghapus tipe file.');
    }

    public function toggleFileType(int $id): RedirectResponse
    {
        $this->requirePerm('manage_fields');
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_file_types')) {
            return redirect()->back()->with('error', 'Tabel belum tersedia.');
        }
        $row = $db->table('tb_file_types')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Tipe file tidak ditemukan.');
        }
        $db->table('tb_file_types')->where('id', $id)->update([
            'status'     => (int) ($row['status'] ?? 0) === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/settings/upload-config')->with('success', 'Status tipe file diperbarui.');
    }

    /**
     * @param list<mixed> $names
     * @param list<mixed> $abbrs
     */
    private function insertScopedPlatformsFromPairs($db, int $groupId, array $names, array $abbrs): int
    {
        if ($groupId < 1) {
            return 0;
        }
        $now   = date('Y-m-d H:i:s');
        $row   = $db->table('tb_platforms')->where('product_group_id', $groupId)->selectMax('order_no')->get()->getRowArray();
        $ord   = (int) ($row['order_no'] ?? 0);
        $count = max(count($names), count($abbrs));
        $n     = 0;
        for ($i = 0; $i < $count; $i++) {
            $name = trim((string) ($names[$i] ?? ''));
            $abbr = strtoupper(trim((string) ($abbrs[$i] ?? '')));
            if ($name === '' || $abbr === '') {
                continue;
            }
            if ($db->table('tb_platforms')->where('product_group_id', $groupId)->where('abbr', $abbr)->countAllResults() > 0) {
                continue;
            }
            ++$ord;
            $insert = [
                'product_group_id' => $groupId,
                'name'             => $name,
                'abbr'             => $abbr,
                'order_no'         => $ord,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            if ($db->fieldExists('icon', 'tb_platforms')) {
                $insert['icon'] = null;
            }
            $db->table('tb_platforms')->insert($insert);
            ++$n;
        }

        return $n;
    }

    /**
     * @param list<mixed> $names
     * @param list<mixed> $abbrs
     */
    private function insertScopedFileTypesFromPairs($db, int $groupId, array $names, array $abbrs): int
    {
        if ($groupId < 1) {
            return 0;
        }
        $now   = date('Y-m-d H:i:s');
        $row   = $db->table('tb_file_types')->where('product_group_id', $groupId)->selectMax('order_no')->get()->getRowArray();
        $ord   = (int) ($row['order_no'] ?? 0);
        $count = max(count($names), count($abbrs));
        $n     = 0;
        for ($i = 0; $i < $count; $i++) {
            $name = trim((string) ($names[$i] ?? ''));
            $abbr = strtoupper(trim((string) ($abbrs[$i] ?? '')));
            if ($name === '' || $abbr === '') {
                continue;
            }
            if ($db->table('tb_file_types')->where('product_group_id', $groupId)->where('abbr', $abbr)->countAllResults() > 0) {
                continue;
            }
            ++$ord;
            $db->table('tb_file_types')->insert([
                'product_group_id' => $groupId,
                'name'             => $name,
                'abbr'             => $abbr,
                'order_no'         => $ord,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            ++$n;
        }

        return $n;
    }

    /**
     * @param list<int> $orderedActiveIds
     */
    private function syncScopedPlatformsActive($db, int $groupId, array $orderedActiveIds): void
    {
        if ($groupId < 1) {
            return;
        }
        $db->table('tb_platforms')->where('product_group_id', $groupId)->update(['status' => 0]);
        $ord = 0;
        foreach ($orderedActiveIds as $pid) {
            if ($pid < 1) {
                continue;
            }
            $r = $db->table('tb_platforms')->where('id', $pid)->where('product_group_id', $groupId)->get()->getRowArray();
            if (! $r) {
                continue;
            }
            ++$ord;
            $db->table('tb_platforms')->where('id', $pid)->update([
                'status'     => 1,
                'order_no'   => $ord,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @param list<int> $orderedActiveIds
     */
    private function syncScopedFileTypesActive($db, int $groupId, array $orderedActiveIds): void
    {
        if ($groupId < 1) {
            return;
        }
        $db->table('tb_file_types')->where('product_group_id', $groupId)->update(['status' => 0]);
        $ord = 0;
        foreach ($orderedActiveIds as $fid) {
            if ($fid < 1) {
                continue;
            }
            $r = $db->table('tb_file_types')->where('id', $fid)->where('product_group_id', $groupId)->get()->getRowArray();
            if (! $r) {
                continue;
            }
            ++$ord;
            $db->table('tb_file_types')->where('id', $fid)->update([
                'status'     => 1,
                'order_no'   => $ord,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Urutan pertama kali muncul di POST dipertahankan; duplikat diabaikan.
     *
     * @param list<mixed> $raw
     *
     * @return list<int>
     */
    private function normalizePostedIntIds(array $raw): array
    {
        $out  = [];
        $seen = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[]     = $id;
        }

        return $out;
    }

    /**
     * Hapus lalu isi junction platform untuk grup. Hanya master aktif.
     *
     * @param list<int> $orderedIds
     *
     * @return int jumlah baris yang di-insert
     */
    private function replaceGroupPlatformJunction($db, int $groupId, array $orderedIds): int
    {
        if (! $db->tableExists('tb_product_group_platforms') || $groupId < 1) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $db->table('tb_product_group_platforms')->where('product_group_id', $groupId)->delete();
        $ord = 0;
        foreach ($orderedIds as $pid) {
            if ($db->table('tb_platforms')->where('id', $pid)->where('status', 1)->countAllResults() < 1) {
                continue;
            }
            ++$ord;
            $db->table('tb_product_group_platforms')->insert([
                'product_group_id' => $groupId,
                'platform_id'      => $pid,
                'order_no'         => $ord,
                'created_at'       => $now,
            ]);
        }

        return $ord;
    }

    /**
     * @param list<int> $orderedIds
     *
     * @return int jumlah baris yang di-insert
     */
    private function replaceGroupFileTypeJunction($db, int $groupId, array $orderedIds): int
    {
        if (! $db->tableExists('tb_product_group_file_types') || $groupId < 1) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $db->table('tb_product_group_file_types')->where('product_group_id', $groupId)->delete();
        $ord = 0;
        foreach ($orderedIds as $fid) {
            if ($db->table('tb_file_types')->where('id', $fid)->where('status', 1)->countAllResults() < 1) {
                continue;
            }
            ++$ord;
            $db->table('tb_product_group_file_types')->insert([
                'product_group_id' => $groupId,
                'file_type_id'     => $fid,
                'order_no'         => $ord,
                'created_at'       => $now,
            ]);
        }

        return $ord;
    }

    /** @param \CodeIgniter\Database\BaseConnection $db */
    private function backfillPlatformsForGroup($db, int $groupId): void
    {
        if ($groupId < 1 || ! $db->tableExists('tb_product_group_platforms')) {
            return;
        }
        if ($db->table('tb_product_group_platforms')->where('product_group_id', $groupId)->countAllResults() > 0) {
            return;
        }
        $now       = date('Y-m-d H:i:s');
        $platforms = $db->table('tb_platforms')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray();
        $ord       = 0;
        foreach ($platforms as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            ++$ord;
            $db->table('tb_product_group_platforms')->insert([
                'product_group_id' => $groupId,
                'platform_id'      => $pid,
                'order_no'         => $ord,
                'created_at'       => $now,
            ]);
        }
    }

    /** @param \CodeIgniter\Database\BaseConnection $db */
    private function backfillFileTypesForGroup($db, int $groupId): void
    {
        if ($groupId < 1 || ! $db->tableExists('tb_product_group_file_types')) {
            return;
        }
        if ($db->table('tb_product_group_file_types')->where('product_group_id', $groupId)->countAllResults() > 0) {
            return;
        }
        $now       = date('Y-m-d H:i:s');
        $fileTypes = $db->table('tb_file_types')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray();
        $ord       = 0;
        foreach ($fileTypes as $f) {
            $fid = (int) ($f['id'] ?? 0);
            if ($fid < 1) {
                continue;
            }
            ++$ord;
            $db->table('tb_product_group_file_types')->insert([
                'product_group_id' => $groupId,
                'file_type_id'     => $fid,
                'order_no'         => $ord,
                'created_at'       => $now,
            ]);
        }
    }
}
