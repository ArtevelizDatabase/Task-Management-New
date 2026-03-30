<?php

namespace App\Models;

use CodeIgniter\Model;

class AssignmentRuleModel extends Model
{
    protected $table         = 'tb_assignment_rules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'account_id',
        'default_user_id',
        'default_team_id',
        'status',
        'priority',
        'created_by',
    ];

    public function resolveDefaultUserId(?int $accountId): ?int
    {
        if (empty($accountId)) {
            return null;
        }
        if (!$this->db->tableExists($this->table)) {
            return null;
        }

        $rule = $this->where('account_id', $accountId)
            ->where('status', 'active')
            ->orderBy('priority', 'ASC')
            ->first();

        if (!$rule) {
            return null;
        }

        return !empty($rule['default_user_id']) ? (int) $rule['default_user_id'] : null;
    }
}
