<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UploadStatusModel extends Model
{
    protected $table            = 'tb_submission_upload_status';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'submission_id', 'product_group_id', 'platform_id',
        'file_type_id', 'status', 'uploaded_at', 'notes', 'updated_by', 'updated_at',
    ];

    public function getColumnConfig(): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('tb_product_groups')
            || ! $db->tableExists('tb_platforms')
            || ! $db->tableExists('tb_file_types')) {
            return ['groups' => [], 'platforms' => [], 'fileTypes' => []];
        }

        $groups = $db->table('tb_product_groups')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray();

        $scoped       = $db->fieldExists('product_group_id', 'tb_platforms');
        $useJunction  = ! $scoped
            && $db->tableExists('tb_product_group_platforms')
            && $db->tableExists('tb_product_group_file_types');
        $platformsAll = $db->table('tb_platforms')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray();
        $fileTypesAll = $db->table('tb_file_types')->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray();

        foreach ($groups as &$g) {
            $gid = (int) ($g['id'] ?? 0);
            $hp  = (int) ($g['has_platform'] ?? 1) === 1;
            $hf  = (int) ($g['has_file_types'] ?? 0) === 1;

            if ($scoped) {
                $g['pivot_platforms']  = $hp
                    ? $db->table('tb_platforms')->where('product_group_id', $gid)->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray()
                    : [];
                $g['pivot_file_types'] = $hf
                    ? $db->table('tb_file_types')->where('product_group_id', $gid)->where('status', 1)->orderBy('order_no', 'ASC')->get()->getResultArray()
                    : [];
            } elseif ($useJunction) {
                $g['pivot_platforms']  = $hp ? $this->fetchPivotPlatformsForGroup($db, $gid) : [];
                $g['pivot_file_types'] = $hf ? $this->fetchPivotFileTypesForGroup($db, $gid) : [];
            } else {
                $g['pivot_platforms']  = $hp ? $platformsAll : [];
                $g['pivot_file_types'] = $hf ? $fileTypesAll : [];
            }

            $ptCount = count($g['pivot_platforms']);
            $ftCount = count($g['pivot_file_types']);
            $ptEff   = $hp ? max(1, $ptCount) : 1;
            $ftEff   = $hf ? max(1, $ftCount) : 1;

            if ($hp && $hf) {
                $g['colspan'] = $ptEff * $ftEff;
            } elseif ($hp && ! $hf) {
                $g['colspan'] = $ptEff;
            } elseif (! $hp && $hf) {
                $g['colspan'] = $ftEff;
            } else {
                $g['colspan'] = 1;
            }
        }
        unset($g);

        return [
            'groups'    => $groups,
            'platforms' => $platformsAll,
            'fileTypes' => $fileTypesAll,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPivotPlatformsForGroup($db, int $groupId): array
    {
        if ($groupId < 1 || ! $db->tableExists('tb_product_group_platforms')) {
            return [];
        }

        return $db->table('tb_product_group_platforms')
            ->select('tb_platforms.*')
            ->join('tb_platforms', 'tb_platforms.id = tb_product_group_platforms.platform_id', 'inner')
            ->where('tb_product_group_platforms.product_group_id', $groupId)
            ->where('tb_platforms.status', 1)
            ->orderBy('tb_product_group_platforms.order_no', 'ASC')
            ->orderBy('tb_platforms.order_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPivotFileTypesForGroup($db, int $groupId): array
    {
        if ($groupId < 1 || ! $db->tableExists('tb_product_group_file_types')) {
            return [];
        }

        return $db->table('tb_product_group_file_types')
            ->select('tb_file_types.*')
            ->join('tb_file_types', 'tb_file_types.id = tb_product_group_file_types.file_type_id', 'inner')
            ->where('tb_product_group_file_types.product_group_id', $groupId)
            ->where('tb_file_types.status', 1)
            ->orderBy('tb_product_group_file_types.order_no', 'ASC')
            ->orderBy('tb_file_types.order_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function platformAssignedToGroup(int $groupId, int $platformId): bool
    {
        if ($groupId < 1 || $platformId < 1) {
            return false;
        }
        $db = \Config\Database::connect();
        if ($db->fieldExists('product_group_id', 'tb_platforms')) {
            return $db->table('tb_platforms')
                    ->where('id', $platformId)
                    ->where('product_group_id', $groupId)
                    ->where('status', 1)
                    ->countAllResults() > 0;
        }
        if ($db->table('tb_platforms')->where('id', $platformId)->where('status', 1)->countAllResults() < 1) {
            return false;
        }
        if (! $db->tableExists('tb_product_group_platforms')) {
            return true;
        }

        return $db->table('tb_product_group_platforms')
                ->where('product_group_id', $groupId)
                ->where('platform_id', $platformId)
                ->countAllResults() > 0;
    }

    public function fileTypeAssignedToGroup(int $groupId, int $fileTypeId): bool
    {
        if ($groupId < 1 || $fileTypeId < 1) {
            return false;
        }
        $db = \Config\Database::connect();
        if ($db->fieldExists('product_group_id', 'tb_file_types')) {
            return $db->table('tb_file_types')
                    ->where('id', $fileTypeId)
                    ->where('product_group_id', $groupId)
                    ->where('status', 1)
                    ->countAllResults() > 0;
        }
        if ($db->table('tb_file_types')->where('id', $fileTypeId)->where('status', 1)->countAllResults() < 1) {
            return false;
        }
        if (! $db->tableExists('tb_product_group_file_types')) {
            return true;
        }

        return $db->table('tb_product_group_file_types')
                ->where('product_group_id', $groupId)
                ->where('file_type_id', $fileTypeId)
                ->countAllResults() > 0;
    }

    /**
     * @return array<int, array<int, array<int, array<int|string, string>>>>
     */
    public function getStatusMap(array $submissionIds): array
    {
        if ($submissionIds === [] || ! \Config\Database::connect()->tableExists('tb_submission_upload_status')) {
            return [];
        }

        $rows = $this->whereIn('submission_id', $submissionIds)->findAll();

        $map = [];
        $cfg = config('UploadPivotStatuses');
        foreach ($rows as $r) {
            $sid  = (int) $r['submission_id'];
            $gid  = (int) $r['product_group_id'];
            $pid  = (int) $r['platform_id'];
            $ftid = (int) ($r['file_type_id'] ?? 0);
            $key  = $ftid === 0 ? '_' : $ftid;
            $raw  = (string) ($r['status'] ?? 'draft');
            $map[$sid][$gid][$pid][$key] = $cfg instanceof \Config\UploadPivotStatuses
                ? $cfg->normalizeStoredStatus($raw)
                : $raw;
        }

        return $map;
    }

    public function upsertStatus(
        int $submissionId,
        int $groupId,
        int $platformId,
        ?int $fileTypeId,
        string $status,
        int $updatedBy
    ): bool {
        $cfg           = config('UploadPivotStatuses');
        $allowedStatus = $cfg instanceof \Config\UploadPivotStatuses
            ? $cfg->allowedValues()
            : ['draft', 'under_review', 'uploaded', 'soft_reject', 'reject'];
        if (! in_array($status, $allowedStatus, true)) {
            return false;
        }

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $ftResolved = ($fileTypeId !== null && $fileTypeId > 0) ? $fileTypeId : 0;

        $uploadedAt = $status === 'uploaded' ? $now : null;

        $tbl = $db->escapeIdentifiers($db->prefixTable('tb_submission_upload_status'));
        $db->query(
            "INSERT INTO {$tbl} (submission_id, product_group_id, platform_id, file_type_id, status, uploaded_at, notes, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE status = ?, uploaded_at = ?, updated_by = ?, updated_at = ?",
            [
                $submissionId, $groupId, $platformId, $ftResolved, $status, $uploadedAt, $updatedBy, $now,
                $status, $uploadedAt, $updatedBy, $now,
            ]
        );

        return true;
    }

    /**
     * Hapus satu sel status pivot (kembali ke “belum diisi” / tampil —).
     */
    public function deleteStatusCell(
        int $submissionId,
        int $groupId,
        int $platformId,
        ?int $fileTypeId
    ): bool {
        if ($submissionId < 1 || $groupId < 1) {
            return false;
        }

        $ftResolved = ($fileTypeId !== null && $fileTypeId > 0) ? $fileTypeId : 0;

        return $this->where('submission_id', $submissionId)
            ->where('product_group_id', $groupId)
            ->where('platform_id', $platformId)
            ->where('file_type_id', $ftResolved)
            ->delete();
    }

    /** @internal for controller validation */
    public function getProductGroup(int $id): ?array
    {
        if (! \Config\Database::connect()->tableExists('tb_product_groups')) {
            return null;
        }

        $row = \Config\Database::connect()->table('tb_product_groups')->where('id', $id)->get()->getRowArray();

        return $row ?: null;
    }
}
