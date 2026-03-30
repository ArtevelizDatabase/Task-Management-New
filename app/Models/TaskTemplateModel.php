<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskTemplateModel extends Model
{
    protected $table      = 'tb_task_templates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['name', 'description', 'created_by', 'field_values', 'is_public'];

    public function getForUser(int $userId): array
    {
        return $this->db->table('tb_task_templates t')
            ->select('t.*, u.username as creator_name')
            ->join('tb_users u', 'u.id = t.created_by', 'left')
            ->groupStart()
                ->where('t.created_by', $userId)
                ->orWhere('t.is_public', 1)
            ->groupEnd()
            ->orderBy('t.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function createFromValues(int $userId, string $name, string $desc, array $fieldValues, bool $isPublic = false): int|string
    {
        return $this->insert([
            'name'         => $name,
            'description'  => $desc,
            'created_by'   => $userId,
            'field_values' => json_encode($fieldValues),
            'is_public'    => $isPublic ? 1 : 0,
        ]);
    }

    public function getFieldValues(int $id): array
    {
        $tpl = $this->find($id);
        if (! $tpl || empty($tpl['field_values'])) {
            return [];
        }

        return json_decode($tpl['field_values'], true) ?? [];
    }
}
