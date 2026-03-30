<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\TeamModel;
use App\Models\NotificationModel;
use CodeIgniter\Controller;

class Profile extends Controller
{
    protected UserModel         $userModel;
    protected TeamModel         $teamModel;
    protected NotificationModel $notifModel;

    public function __construct()
    {
        $this->userModel  = new UserModel();
        $this->teamModel  = new TeamModel();
        $this->notifModel = new NotificationModel();
        helper('url');
    }

    // ── View profile ──────────────────────────────────────────────────────

    public function index(): string
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);
        $teams  = $this->teamModel->getUserTeams($userId);
        $activity = $this->userModel->getActivity($userId, 20);

        $viewData = [
            'title'    => 'Profil Saya',
            'user'     => $user,
            'teams'    => $teams,
            'activity' => $activity,
        ];

        return view('layouts/main', array_merge($viewData, [
            'content' => view('profile/index', $viewData),
        ]));
    }

    // ── Update profile ────────────────────────────────────────────────────

    public function update(): mixed
    {
        $userId = (int) session()->get('user_id');

        $rules = [
            'nickname'  => 'permit_empty|max_length[80]',
            'job_title' => 'permit_empty|max_length[120]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'nickname'  => $this->request->getPost('nickname') ?: null,
            'job_title' => $this->request->getPost('job_title') ?: null,
        ];

        // Avatar upload
        $avatar = $this->request->getFile('avatar');
        if ($avatar && $avatar->isValid() && !$avatar->hasMoved()) {
            $user = $this->userModel->find($userId);
            if ($user['avatar'] && file_exists(FCPATH . 'uploads/avatars/' . $user['avatar'])) {
                unlink(FCPATH . 'uploads/avatars/' . $user['avatar']);
            }
            $ext      = $avatar->getClientExtension();
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $avatar->move(FCPATH . 'uploads/avatars', $filename);
            $data['avatar'] = $filename;
        }

        $this->userModel->update($userId, $data);

        // Update session name
        session()->set('user_name', $data['nickname'] ?? session()->get('user_name'));

        $this->userModel->logActivity($userId, 'update_profile', 'Memperbarui profil');

        return redirect()->to('/profile')->with('success', 'Profil berhasil diperbarui.');
    }

    // ── Change password ───────────────────────────────────────────────────

    public function changePassword(): mixed
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        $rules = [
            'current_password'  => 'required',
            'new_password'      => 'required|regex_match[/^(?=.*[A-Za-z])(?=.*\d).{10,}$/]',
            'confirm_password'  => 'required|matches[new_password]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if (!password_verify($this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->back()->with('error', 'Password saat ini salah.');
        }

        $this->userModel->update($userId, [
            'password_hash' => password_hash($this->request->getPost('new_password'), PASSWORD_BCRYPT),
        ]);

        $this->userModel->logActivity($userId, 'change_password', 'Mengubah password');

        return redirect()->to('/profile')->with('success', 'Password berhasil diubah.');
    }
}
