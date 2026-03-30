<?php

namespace App\Controllers;

use App\Models\FieldModel;
use CodeIgniter\Controller;

class Fields extends Controller
{
    protected FieldModel $fieldModel;

    public function __construct()
    {
        $this->fieldModel = new FieldModel();
    }

    private function fieldContextProjectIdFromGet(): ?int
    {
        $raw = $this->request->getGet('project_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $pid = (int) $raw;

        return $pid > 0 ? $pid : null;
    }

    // ------------------------------------------------------------------
    // INDEX
    // ------------------------------------------------------------------
    public function index(): string
    {
        $ctx = $this->fieldContextProjectIdFromGet();
        if ($this->fieldModel->hasProjectScopeColumn()) {
            if ($ctx !== null && $ctx > 0) {
                $this->fieldModel->ensureDefaultProjectTaskFields($ctx);
            } else {
                $this->fieldModel->ensureJudulFieldInternalGlobal();
            }
        }
        $fields   = $this->fieldModel->getAllOrdered($ctx);
        $fields   = array_map([$this->fieldModel, 'decodeOptions'], $fields);
        $subCols  = $this->fieldModel->getSubmissionColumns();
        $settings = $this->_getSettings();
        $roles    = $this->_getRoleSlugs();

        $db           = \Config\Database::connect();
        $projectsList = [];
        if ($db->tableExists('tb_projects')) {
            $projectsList = $db->table('tb_projects')
                ->select('id, name')
                ->where('status', 'active')
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }

        $pageTitle = ($ctx !== null && $ctx > 0) ? 'Field work item (proyek)' : 'Field task internal';

        return view('layouts/main', [
            'title'   => $pageTitle,
            'content' => view('settings/fields', [
                'fields'              => $fields,
                'subCols'             => $subCols,
                'settings'            => $settings,
                'roles'               => $roles,
                'fieldProjectContext' => $ctx,
                'projectsList'        => $projectsList,
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // HELPER: read all app settings into key → bool map
    // ------------------------------------------------------------------
    private function _getSettings(): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('tb_app_settings')->get()->getResultArray();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (bool) $r['setting_value'];
        }
        return $map;
    }

    private function _getRoleSlugs(): array
    {
        $db = \Config\Database::connect();
        $roles = [];
        if ($db->tableExists('tb_roles')) {
            $rows = $db->table('tb_roles')->select('slug')->orderBy('slug', 'ASC')->get()->getResultArray();
            $roles = array_values(array_filter(array_map(static fn(array $r): string => (string) ($r['slug'] ?? ''), $rows)));
        }
        if ($roles === []) {
            $roles = ['super_admin', 'admin', 'manager', 'member'];
        }
        return array_values(array_unique($roles));
    }

    // ------------------------------------------------------------------
    // STORE
    // ------------------------------------------------------------------
    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $post = $this->request->getPost();
        $db   = \Config\Database::connect();

        $fpid = (int) ($post['field_project_id'] ?? 0);
        unset($post['field_project_id']);
        $projectFieldCtx = null;
        if ($fpid > 0 && $db->tableExists('tb_projects')
            && (int) $db->table('tb_projects')->where('id', $fpid)->countAllResults() > 0) {
            $projectFieldCtx = $fpid;
        }
        $post['project_id'] = $projectFieldCtx;

        if (! empty($post['options_raw'])) {
            $opts            = array_filter(array_map('trim', explode("\n", $post['options_raw'])));
            $post['options'] = json_encode(array_values($opts));
        } else {
            $post['options'] = null;
        }
        unset($post['options_raw']);

        $ob = $db->table('tb_fields');
        if ($this->fieldModel->hasProjectScopeColumn()) {
            if ($projectFieldCtx !== null) {
                $ob->where('project_id', $projectFieldCtx);
            } else {
                $ob->where('project_id IS NULL', null, false);
            }
        }
        $maxRow             = $ob->selectMax('order_no')->get()->getRowArray();
        $post['order_no']   = (int) ($maxRow['order_no'] ?? 0) + 1;
        $post['is_required'] = isset($post['is_required']) ? 1 : 0;
        $post['status']      = 1;
        $post['submission_col'] = $post['submission_col'] ?: null;
        $post['scope']       = in_array($post['scope'] ?? '', ['task', 'setor', 'both'], true) ? $post['scope'] : 'task';
        if ($projectFieldCtx !== null) {
            $post['submission_col'] = null;
            $post['scope']          = 'task';
        }
        if (! empty($post['submission_col']) && $post['scope'] === 'task') {
            $post['scope'] = 'both';
        }
        $allowedSources = ['manual', 'team_users', 'account_sources'];
        $dataSource     = (string) ($post['data_source'] ?? 'manual');
        if (! in_array($dataSource, $allowedSources, true)) {
            $dataSource = 'manual';
        }
        if ($projectFieldCtx !== null && $dataSource === 'account_sources') {
            $dataSource = 'manual';
        }
        $post['data_source'] = $dataSource;
        $roleFilter          = array_values(array_filter((array) ($post['source_roles'] ?? []), static fn($v): bool => trim((string) $v) !== ''));
        if ($post['data_source'] === 'team_users') {
            $post['source_config'] = json_encode([
                'allowed_roles' => array_values(array_unique($roleFilter)),
                'display'       => 'nickname_first',
            ]);
        } elseif ($post['data_source'] === 'account_sources') {
            $post['source_config'] = json_encode([
                'include_office' => true,
                'include_vendor' => true,
            ]);
        } else {
            $post['source_config'] = null;
        }
        unset($post['source_roles']);

        $fkey = trim((string) ($post['field_key'] ?? ''));
        if ($fkey === '' || ! $this->fieldModel->isFieldKeyAvailable($fkey, $projectFieldCtx)) {
            return redirect()->back()
                ->with('error', 'Field key tidak valid atau sudah dipakai di konteks ini.')
                ->withInput();
        }

        if (! $this->fieldModel->skipValidation(true)->insert($post)) {
            return redirect()->back()
                ->with('errors', $this->fieldModel->errors())
                ->withInput();
        }

        $redir = '/fields';
        if ($projectFieldCtx !== null) {
            $redir .= '?project_id=' . (int) $projectFieldCtx;
        }

        return redirect()->to($redir)->with('success', 'Field berhasil ditambahkan!');
    }

    // ------------------------------------------------------------------
    // UPDATE
    // ------------------------------------------------------------------
    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $existing = $this->fieldModel->find($id);
        if (! $existing) {
            return redirect()->to('/fields')->with('error', 'Field tidak ditemukan.');
        }
        $post = $this->request->getPost();

        if (! empty($post['options_raw'])) {
            $opts            = array_filter(array_map('trim', explode("\n", $post['options_raw'])));
            $post['options'] = json_encode(array_values($opts));
        } else {
            $post['options'] = null;
        }
        unset($post['options_raw']);

        $post['is_required']    = isset($post['is_required']) ? 1 : 0;
        $post['submission_col'] = $post['submission_col'] ?: null;
        $post['scope']          = in_array($post['scope'] ?? '', ['task', 'setor', 'both'], true) ? $post['scope'] : 'task';
        $projId                 = null;
        if ($this->fieldModel->hasProjectScopeColumn() && ! empty($existing['project_id'])) {
            $projId = (int) $existing['project_id'];
            $post['project_id']     = $projId;
            $post['submission_col'] = null;
            $post['scope']          = 'task';
        }
        if (! empty($post['submission_col']) && $post['scope'] === 'task') {
            $post['scope'] = 'both';
        }
        $allowedSources = ['manual', 'team_users', 'account_sources'];
        $dataSource     = (string) ($post['data_source'] ?? 'manual');
        if (! in_array($dataSource, $allowedSources, true)) {
            $dataSource = 'manual';
        }
        if ($projId !== null && $dataSource === 'account_sources') {
            $dataSource = 'manual';
        }
        $post['data_source'] = $dataSource;
        $roleFilter          = array_values(array_filter((array) ($post['source_roles'] ?? []), static fn($v): bool => trim((string) $v) !== ''));
        if ($post['data_source'] === 'team_users') {
            $post['source_config'] = json_encode([
                'allowed_roles' => array_values(array_unique($roleFilter)),
                'display'       => 'nickname_first',
            ]);
        } elseif ($post['data_source'] === 'account_sources') {
            $post['source_config'] = json_encode([
                'include_office' => true,
                'include_vendor' => true,
            ]);
        } else {
            $post['source_config'] = null;
        }
        unset($post['source_roles']);

        if (($existing['field_key'] ?? '') === FieldModel::RESERVED_TITLE_FIELD_KEY) {
            $post['field_key']    = FieldModel::RESERVED_TITLE_FIELD_KEY;
            $post['is_required']  = 1;
            $post['status']       = 1;
        }

        $this->fieldModel->skipValidation(true)->update($id, $post);

        $redir = '/fields';
        if ($projId !== null) {
            $redir .= '?project_id=' . $projId;
        }

        return redirect()->to($redir)->with('success', 'Field diupdate.');
    }

    // ------------------------------------------------------------------
    // DELETE
    // ------------------------------------------------------------------
    public function delete(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $db = \Config\Database::connect();

        $row     = $this->fieldModel->find($id);
        $ctxProj = ($row && $this->fieldModel->hasProjectScopeColumn() && ! empty($row['project_id']))
            ? (int) $row['project_id'] : null;
        $redir   = '/fields';
        if ($ctxProj !== null) {
            $redir .= '?project_id=' . $ctxProj;
        }

        if (! $row) {
            return redirect()->to($redir)->with('error', 'Field tidak ditemukan.');
        }

        if (($row['field_key'] ?? '') === FieldModel::RESERVED_TITLE_FIELD_KEY) {
            return redirect()->to($redir)->with('error', 'Field judul adalah field sistem dan tidak boleh dihapus.');
        }

        // Hapus semua nilai EAV untuk field ini, lalu definisi field (termasuk yang masih dipakai task).
        $nVals = (int) $db->table('tb_task_values')->where('field_id', $id)->countAllResults();

        $db->transStart();
        $db->table('tb_task_values')->where('field_id', $id)->delete();
        $this->fieldModel->delete($id);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($redir)->with('error', 'Gagal menghapus field. Coba lagi.');
        }

        $msg = 'Field dihapus.';
        if ($nVals > 0) {
            $msg .= ' ' . $nVals . ' nilai tersimpan di work item/task ikut dihapus.';
        }

        return redirect()->to($redir)->with('success', $msg);
    }

    // ------------------------------------------------------------------
    // AJAX: Toggle status
    // ------------------------------------------------------------------
    public function toggle(int $id): \CodeIgniter\HTTP\Response
    {
        $row = $this->fieldModel->find($id);
        if ($row && ($row['field_key'] ?? '') === FieldModel::RESERVED_TITLE_FIELD_KEY) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Field judul tidak boleh dinonaktifkan.',
                'csrf'    => csrf_hash(),
            ]);
        }
        $ok = $this->fieldModel->toggleStatus($id);
        return $this->response->setJSON(['success' => $ok, 'csrf' => csrf_hash()]);
    }

    // ------------------------------------------------------------------
    // AJAX: Reorder
    // ------------------------------------------------------------------
    public function reorder(): \CodeIgniter\HTTP\Response
    {
        $json  = $this->request->getJSON(true);
        $items = $json['fields'] ?? [];
        $pid   = isset($json['project_id']) ? (int) $json['project_id'] : 0;
        $scope = ($pid > 0) ? $pid : null;
        $ok    = $this->fieldModel->reorder($items, $scope);
        return $this->response->setJSON(['success' => $ok, 'csrf' => csrf_hash()]);
    }

    // ------------------------------------------------------------------
    // AJAX: Toggle an app setting on/off
    // ------------------------------------------------------------------
    public function settingToggle(string $key): \CodeIgniter\HTTP\Response
    {
        $allowed = ['feature_progress', 'feature_deadline'];
        if (!in_array($key, $allowed)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['success' => false, 'message' => 'Setting tidak dikenal']);
        }

        $db      = \Config\Database::connect();
        $current = $db->table('tb_app_settings')
            ->where('setting_key', $key)
            ->get()->getRowArray();

        $newVal = $current ? ($current['setting_value'] === '1' ? '0' : '1') : '1';
        $now    = date('Y-m-d H:i:s');

        if ($current) {
            $db->table('tb_app_settings')
                ->where('setting_key', $key)
                ->update(['setting_value' => $newVal, 'updated_at' => $now]);
        } else {
            $db->table('tb_app_settings')
                ->insert(['setting_key' => $key, 'setting_value' => $newVal, 'updated_at' => $now]);
        }

        return $this->response->setJSON([
            'success' => true,
            'key'     => $key,
            'enabled' => $newVal === '1',
            'csrf'    => csrf_hash(),
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX: Get field data for edit modal
    // ------------------------------------------------------------------
    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        $field = $this->fieldModel->find($id);
        if (!$field) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false]);
        }

        if ($field['options']) {
            $opts = json_decode($field['options'], true);
            $field['options_raw'] = implode("\n", $opts ?? []);
        } else {
            $field['options_raw'] = '';
        }
        $cfg = !empty($field['source_config']) ? json_decode((string) $field['source_config'], true) : [];
        $field['source_roles'] = is_array($cfg['allowed_roles'] ?? null) ? $cfg['allowed_roles'] : [];

        return $this->response->setJSON(['success' => true, 'field' => $field]);
    }
}
