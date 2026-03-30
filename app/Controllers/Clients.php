<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

class Clients extends Controller
{
    private ClientModel $clientModel;
    private ProjectModel $projectModel;

    public function __construct()
    {
        $this->clientModel  = new ClientModel();
        $this->projectModel = new ProjectModel();
    }

    private function requirePerm(string $perm): void
    {
        $role = (string) (session()->get('user_role') ?? 'member');
        if ($role === 'super_admin') {
            return;
        }
        if ($role === 'member') {
            redirect()->to('/tasks')->with('error', 'Akses ditolak.')->send();
            exit;
        }
        $perms = session()->get('user_perms') ?? [];
        if (! in_array($perm, (array) $perms, true)) {
            redirect()->back()->with('error', 'Akses ditolak.')->send();
            exit;
        }
    }

    private function requireAuth(): bool
    {
        return (bool) session()->get('user_id');
    }

    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('view_clients');

        $clients = $this->clientModel->getWithStats();

        return view('layouts/main', [
            'title'   => 'Manajemen Klien',
            'content' => view('clients/index', ['clients' => $clients]),
        ]);
    }

    public function show(int $id): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('view_clients');

        $client = $this->clientModel->find($id);
        if (! $client) {
            return redirect()->to('/clients')->with('error', 'Klien tidak ditemukan.');
        }

        $projects = $this->projectModel->getWithClient($id);

        return view('layouts/main', [
            'title'   => $client['name'],
            'content' => view('clients/show', [
                'client'   => $client,
                'projects' => $projects,
            ]),
        ]);
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_clients');

        $data = [
            'name'    => $this->request->getPost('name'),
            'contact' => $this->request->getPost('contact'),
            'email'   => $this->request->getPost('email'),
            'phone'   => $this->request->getPost('phone'),
            'notes'   => $this->request->getPost('notes'),
            'status'  => 'active',
        ];

        if (! $this->clientModel->save($data)) {
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan klien.');
        }

        return redirect()->to('/clients')->with('success', 'Klien berhasil ditambahkan.');
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_clients');

        $data = [
            'name'    => $this->request->getPost('name'),
            'contact' => $this->request->getPost('contact'),
            'email'   => $this->request->getPost('email'),
            'phone'   => $this->request->getPost('phone'),
            'notes'   => $this->request->getPost('notes'),
            'status'  => $this->request->getPost('status') ?? 'active',
        ];

        $this->clientModel->update($id, $data);

        return redirect()->to('/clients/' . $id)->with('success', 'Klien diperbarui.');
    }

    public function delete(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $this->requireAuth()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePerm('manage_clients');

        $activeProjects = $this->projectModel->where('client_id', $id)
            ->where('status !=', 'completed')
            ->countAllResults();
        if ($activeProjects > 0) {
            return redirect()->back()->with(
                'error',
                "Klien masih punya {$activeProjects} project yang belum selesai. Selesaikan atau pindahkan project dulu."
            );
        }

        $this->clientModel->delete($id);

        return redirect()->to('/clients')->with('success', 'Klien dihapus.');
    }
}
