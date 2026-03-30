<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorAllocationModel extends Model
{
    protected $table         = 'tb_vendor_allocations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'account_id',
        'user_id',
        'is_primary',
        'created_by',
    ];
}
