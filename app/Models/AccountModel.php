<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountModel extends Model
{
    protected $table         = 'tb_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'name',
        'type',
        'status',
        'platform',
        'owner_name',
        'contract_mode',
        'next_review_date',
        'contract_end_date',
        'notes',
        'created_by',
        'legacy_vendor_id',
        'legacy_office_id',
    ];

    public function getActiveByType(string $type): array
    {
        if (!$this->db->tableExists($this->table)) {
            return [];
        }
        $type = in_array($type, ['office', 'vendor'], true) ? $type : 'vendor';
        return $this->where('status', 'active')
            ->where('type', $type)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getActiveAll(): array
    {
        if (!$this->db->tableExists($this->table)) {
            return [];
        }
        return $this->where('status', 'active')
            ->orderBy('type', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function resolveLabelFromNamespacedValue(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        if (strpos($raw, ':') === false) {
            return $raw;
        }
        [$prefix, $idRaw] = explode(':', $raw, 2);
        $id = (int) $idRaw;
        if ($id <= 0) {
            return $raw;
        }

        if ($prefix === 'account') {
            $row = $this->select('name')->find($id);
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        if ($prefix === 'vendor') {
            $row = $this->where('legacy_vendor_id', $id)->select('name')->first();
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        if ($prefix === 'office') {
            $row = $this->where('legacy_office_id', $id)->select('name')->first();
            return trim((string) ($row['name'] ?? '')) ?: $raw;
        }
        return $raw;
    }
}

