<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Status sel pivot upload pada Daftar Setor (tb_submission_upload_status.status).
 *
 * @var list<array{value:string,label:string,abbr:string}>
 */
class UploadPivotStatuses extends BaseConfig
{
    /** @var list<array{value:string,label:string,abbr:string}> */
    public array $options = [
        ['value' => 'draft', 'label' => 'Draft', 'abbr' => 'D'],
        ['value' => 'under_review', 'label' => 'Under review', 'abbr' => 'UR'],
        ['value' => 'uploaded', 'label' => 'Uploaded', 'abbr' => 'U'],
        ['value' => 'soft_reject', 'label' => 'Soft reject', 'abbr' => 'SR'],
        ['value' => 'reject', 'label' => 'Reject', 'abbr' => 'R'],
    ];

    /**
     * @return list<string>
     */
    public function allowedValues(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $o): string => (string) ($o['value'] ?? ''),
            $this->options
        )));
    }

    public function normalizeStoredStatus(string $status): string
    {
        $allowed = $this->allowedValues();
        if (in_array($status, $allowed, true)) {
            return $status;
        }
        // Legacy ENUM values (sebelum migrasi VARCHAR)
        return match ($status) {
            'live'  => 'uploaded',
            'skip'  => 'soft_reject',
            default => 'draft',
        };
    }
}
