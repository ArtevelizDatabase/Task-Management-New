<?php

namespace App\Controllers;

use App\Models\NotificationModel;
use CodeIgniter\Controller;

class Notifications extends Controller
{
    protected NotificationModel $notifModel;

    public function __construct()
    {
        $this->notifModel = new NotificationModel();
        helper(['url', 'security']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function jsonAjaxMutation(array $extra = []): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON(array_merge(['success' => true, 'csrf' => csrf_hash()], $extra));
    }

    private function _userId(): int
    {
        return (int) session()->get('user_id');
    }

    // ── Main page ─────────────────────────────────────────────────────────

    public function index(): string
    {
        $page  = (int)($this->request->getGet('page') ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $all   = $this->notifModel->getForUser($this->_userId(), $limit, $offset);
        $total = $this->notifModel->where('user_id', $this->_userId())->countAllResults();
        $unread = $this->notifModel->getUnreadCount($this->_userId());
        $prefs = $this->notifModel->getPreferences($this->_userId());

        $d = [
            'title'         => 'Notifikasi',
            'notifications' => $all,
            'unreadCount'   => $unread,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $limit,
            'totalPages'    => max(1, (int)ceil($total / $limit)),
            'prefs'         => $prefs,
            'types'         => NotificationModel::$types,
        ];
        return view('layouts/main', array_merge($d, ['content' => view('notifications/index', $d)]));
    }

    // ── Unread count (AJAX) ───────────────────────────────────────────────

    public function unreadCount(): mixed
    {
        $count   = $this->notifModel->getUnreadCount($this->_userId());
        $unread  = $this->notifModel->getUnread($this->_userId(), 5);

        foreach ($unread as &$n) {
            $n['time_ago'] = $this->notifModel->timeAgo($n['created_at']);
        }
        unset($n);

        return $this->response->setJSON([
            'count'   => $count,
            'unread'  => $unread,
            'types'   => NotificationModel::$types,
        ]);
    }

    // ── Mark single as read ───────────────────────────────────────────────

    public function markRead(int $id): mixed
    {
        $this->notifModel->markRead($id, $this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back();
    }

    // ── Mark single as unread ─────────────────────────────────────────────

    public function markUnread(int $id): mixed
    {
        $this->notifModel->markUnread($id, $this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back();
    }

    // ── Mark all read ─────────────────────────────────────────────────────

    public function markAllRead(): mixed
    {
        $this->notifModel->markAllRead($this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    // ── Delete single ─────────────────────────────────────────────────────

    public function delete(int $id): mixed
    {
        $this->notifModel->deleteForUser($id, $this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back()->with('success', 'Notifikasi dihapus.');
    }

    // ── Delete all ────────────────────────────────────────────────────────

    public function deleteAll(): mixed
    {
        $this->notifModel->deleteAllForUser($this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back()->with('success', 'Semua notifikasi dihapus.');
    }

    // ── Delete read ───────────────────────────────────────────────────────

    public function deleteRead(): mixed
    {
        $this->notifModel->deleteReadForUser($this->_userId());
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back()->with('success', 'Notifikasi yang sudah dibaca dihapus.');
    }

    // ── Preferences update ────────────────────────────────────────────────

    public function updatePreferences(): mixed
    {
        $types = array_keys(NotificationModel::$types);
        foreach ($types as $type) {
            $enabled = (bool) $this->request->getPost("pref_{$type}");
            $this->notifModel->setPreference($this->_userId(), $type, $enabled);
        }
        if ($this->request->isAJAX()) {
            return $this->jsonAjaxMutation();
        }
        return redirect()->back()->with('success', 'Preferensi notifikasi disimpan.');
    }
}
