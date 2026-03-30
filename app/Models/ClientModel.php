<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table      = 'tb_clients';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['name', 'contact', 'email', 'phone', 'status', 'notes'];

    protected $validationRules = [
        'name' => 'required|max_length[150]',
    ];

    public function getActiveList(): array
    {
        return $this->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getWithStats(): array
    {
        return $this->db->table('tb_clients c')
            ->select([
                'c.*',
                'COUNT(DISTINCT p.id) as project_count',
                'COUNT(DISTINCT t.id) as task_count',
            ])
            ->join('tb_projects p', 'p.client_id = c.id AND p.status != "completed"', 'left')
            ->join('tb_task t', 't.project_id = p.id AND t.deleted_at IS NULL', 'left')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
