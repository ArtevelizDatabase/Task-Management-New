<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorTargetModel extends Model
{
    protected $table         = 'tb_vendor_targets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'account_id',
        'period_type',
        'period_start',
        'period_end',
        'target_value',
        'created_by',
    ];
}
