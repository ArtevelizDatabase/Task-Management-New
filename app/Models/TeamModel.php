<?php

namespace App\Models;

use CodeIgniter\Model;

class TeamModel extends Model
{
    protected $table         = 'tb_teams';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = ['name', 'slug', 'description', 'created_by'];

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[120]',
    ];

    public function getAllWithMembers(): array
    {
        $teams = $this->orderBy('created_at', 'DESC')->findAll();

        $db = \Config\Database::connect();
        $members = $db->table('tb_team_members tm')
            ->select('tm.team_id, u.id AS user_id, u.nickname, u.username, u.avatar, u.role, u.status')
            ->join('tb_users u', 'u.id = tm.user_id')
            ->get()->getResultArray();

        $memberMap = [];
        foreach ($members as $m) {
            $memberMap[$m['team_id']][] = $m;
        }

        foreach ($teams as &$team) {
            $team['members']      = $memberMap[$team['id']] ?? [];
            $team['member_count'] = count($team['members']);
        }
        unset($team);

        return $teams;
    }

    public function getTeamWithMembers(int $teamId): ?array
    {
        $team = $this->find($teamId);
        if (!$team) {
            return null;
        }

        $db = \Config\Database::connect();
        $team['members'] = $db->table('tb_team_members tm')
            ->select('tm.team_id, u.id AS user_id, u.nickname, u.username, u.avatar, u.role, u.status, u.job_title')
            ->join('tb_users u', 'u.id = tm.user_id')
            ->where('tm.team_id', $teamId)
            ->get()->getResultArray();

        return $team;
    }

    public function addMember(int $teamId, int $userId): bool
    {
        $existing = $this->db->table('tb_team_members')
            ->where(['team_id' => $teamId, 'user_id' => $userId])
            ->countAllResults();

        if ($existing > 0) {
            return false;
        }

        $this->db->table('tb_team_members')->insert([
            'team_id'   => $teamId,
            'user_id'   => $userId,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $this->db->table('tb_team_members')
            ->where(['team_id' => $teamId, 'user_id' => $userId])
            ->delete();
    }

    public function getUserTeams(int $userId): array
    {
        return $this->db->table('tb_teams t')
            ->select('t.*')
            ->join('tb_team_members tm', 'tm.team_id = t.id')
            ->where('tm.user_id', $userId)
            ->get()->getResultArray();
    }

    public function generateSlug(string $name): string
    {
        $slug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
        $slug  = trim($slug, '-');
        $base  = $slug;
        $i     = 1;
        while ($this->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
