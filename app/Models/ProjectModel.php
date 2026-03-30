<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table      = 'tb_projects';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['client_id', 'name', 'description', 'status'];

    protected $validationRules = [
        'name' => 'required|max_length[150]',
    ];

    public function getWithClient(?int $clientId = null): array
    {
        $builder = $this->db->table('tb_projects p')
            ->select('p.*, c.name as client_name')
            ->join('tb_clients c', 'c.id = p.client_id', 'left')
            ->orderBy('p.name', 'ASC');

        if ($clientId !== null) {
            $builder->where('p.client_id', $clientId);
        }

        return $builder->get()->getResultArray();
    }

    public function getActiveList(): array
    {
        return $this->db->table('tb_projects p')
            ->select('p.id, p.name, c.name as client_name')
            ->join('tb_clients c', 'c.id = p.client_id', 'left')
            ->where('p.status !=', 'completed')
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getWithTaskStats(int $projectId): ?array
    {
        $project = $this->db->table('tb_projects p')
            ->select('p.*, c.name as client_name')
            ->join('tb_clients c', 'c.id = p.client_id', 'left')
            ->where('p.id', $projectId)
            ->get()->getRowArray();

        if (! $project) {
            return null;
        }

        $stats = $this->db->table('tb_task')
            ->select([
                'COUNT(*) as total',
                "SUM(status = 'done') as done_count",
                "SUM(status = 'on_progress') as on_progress_count",
                "SUM(status = 'pending') as pending_count",
                "SUM(deadline < NOW() AND status NOT IN ('done','cancelled')) as overdue_count",
            ])
            ->where('project_id', $projectId)
            ->where('deleted_at IS NULL')
            ->get()->getRowArray();

        $project['stats'] = $stats;

        return $project;
    }
}
