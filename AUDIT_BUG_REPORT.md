# Bug Audit Report — Notion Features (Production Deploy)
> Tanggal: 30 Maret 2026 | Berdasarkan: `PRODUCTION-DEPLOY.zip`

---

## Ringkasan Eksekutif

| Tingkat | Jumlah | Status |
|---|---|---|
| 🔴 Critical (crash / security) | 4 | Harus fix sebelum deploy |
| 🟠 High (logic error / data loss) | 5 | Fix segera setelah deploy |
| 🟡 Medium (fungsional terganggu) | 6 | Fix dalam sprint berikutnya |
| 🟢 Low / saran (polish) | 4 | Backlog |

---

## 🔴 CRITICAL

### BUG-C1: `NotificationModel::$updatedField = ''` — DB error saat update notifikasi
**File:** `app/Models/NotificationModel.php` baris 14
**Masalah:** `$useTimestamps = true` tapi `$updatedField = ''` (string kosong, bukan `false`). CI4 mencoba `UPDATE tb_notifications SET `` = '...'` yang menyebabkan DB error pada operasi `markRead`, `markAllRead`, `markUnread`.
Ini langsung mempengaruhi fitur mention @user karena `CommentModel::dispatchMentions` → `NotificationModel::send()` → lanjut ke `markRead` saat user klik notif.

**Fix:**
```php
// NotificationModel.php
protected $useTimestamps = false;           // ← ubah dari true
protected $createdField  = 'created_at';   // ← tetap set manual di send()
protected $updatedField  = '';             // ← hapus baris ini
```
Lalu di method `send()`, set `created_at` secara manual (sudah tidak ada, tapi `insert()` CI4 akan skip timestamps jika `useTimestamps = false` — tinggal pastikan `created_at` masuk di data insert).

---

### BUG-C2: `TaskExtras::requirePerm()` — member role **bypass semua permission check**
**File:** `app/Controllers/TaskExtras.php` baris 76–79
**Masalah:** Logic `if ($role === 'member') { return; }` artinya semua user dengan role `member` **langsung lolos** tanpa cek permission apapun. Ini berarti member bisa akses endpoint yang seharusnya di-restrict (misalnya `deleteRevision` yang seharusnya admin only).

```php
// SEKARANG (SALAH):
private function requirePerm(string $perm, bool $asJson = false): void
{
    if ($role === 'super_admin') return;
    if ($role === 'member') return;   // ← BUG: member bypass semua check
    ...
}
```

**Bandingkan dengan `Clients::requirePerm`** yang benar: member justru di-BLOCK.

**Fix:**
```php
private function requirePerm(string $perm, bool $asJson = false): void
{
    $role = $this->role();
    if ($role === 'super_admin') return;
    
    // Member: hanya boleh view_tasks, bukan manage_clients/manage_projects/dll
    if ($role === 'member' && $perm === 'view_tasks') return;
    
    $perms = session()->get('user_perms') ?? [];
    if (!in_array($perm, (array) $perms, true)) {
        if ($asJson) {
            $this->jsonForbidden('Akses ditolak.')->send(); exit;
        }
        redirect()->back()->with('error', 'Akses ditolak.')->send(); exit;
    }
}
```

---

### BUG-C3: `serveAttachment` — `file_get_contents()` untuk file besar → memory exhausted
**File:** `app/Controllers/TaskExtras.php` baris ~394
**Masalah:** `->setBody(file_get_contents($path))` memuat seluruh file ke RAM. File video MP4 20MB = OOM error di server dengan `memory_limit = 128M` yang umum.

**Fix: Stream dengan `readfile()` / `fpassthru()`:**
```php
public function serveAttachment(int $taskId, string $filename)
{
    // ... validasi tetap sama ...

    $mime = mime_content_type($path) ?: 'application/octet-stream';
    $size = filesize($path);

    // Stream langsung, jangan load ke RAM
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . addslashes(basename($filename)) . '"');
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    ob_end_clean();
    readfile($path);
    exit;
}
```

---

### BUG-C4: `TaskRelationModel::getForTask` — correlated subquery di JOIN tanpa project scope
**File:** `app/Models/TaskRelationModel.php` baris ~12
**Masalah:** JOIN menggunakan `(SELECT id FROM tb_fields WHERE field_key = 'judul' LIMIT 1)` tanpa filter `project_id`. Setelah migrasi `add_project_id_to_fields`, ada **multiple rows** dengan `field_key = 'judul'` (satu per project). Subquery ini bisa return ID field yang salah, mengambil judul task yang keliru atau NULL.

**Fix:**
```php
public function getForTask(int $taskId): array
{
    $db = \Config\Database::connect();
    
    // Resolve field_id 'judul' dengan benar berdasarkan project task terkait
    $rows = $db->table('tb_task_relations r')
        ->select('r.*, rt.status as related_task_status, rt.project_id as related_project_id')
        ->join('tb_task rt', 'rt.id = r.related_task_id', 'left')
        ->where('r.task_id', $taskId)
        ->get()->getResultArray();
    
    if (empty($rows)) return [];
    
    // Batch fetch judul via separate query (2-query pattern, bukan N+1)
    $relatedIds = array_map(fn($r) => (int)$r['related_task_id'], $rows);
    $titles = $db->table('tb_task_values tv')
        ->select('tv.task_id, tv.value')
        ->join('tb_fields f', 'f.id = tv.field_id')
        ->where('f.field_key', 'judul')
        ->where('f.status', 1)
        ->whereIn('tv.task_id', $relatedIds)
        ->get()->getResultArray();
    
    $titleMap = array_column($titles, 'value', 'task_id');
    
    foreach ($rows as &$row) {
        $row['related_task_title'] = $titleMap[$row['related_task_id']] ?? "Task #{$row['related_task_id']}";
    }
    return $rows;
}
```

---

## 🟠 HIGH

### BUG-H1: `FavoriteModel::getForUser` — N+1 query problem
**File:** `app/Models/FavoriteModel.php`
**Masalah:** Setiap favorit memanggil `getTaskTitle()` / `getProjectName()` / `getClientName()` secara individual. 20 favorit = 20+ query. Terlihat di endpoint `GET /favorites` yang dipanggil di sidebar setiap page load.

**Fix: Batch fetch per entity type:**
```php
public function getForUser(int $userId): array
{
    $rows = $this->where('user_id', $userId)->orderBy('created_at', 'DESC')->findAll();
    if (empty($rows)) return [];
    
    // Kelompokkan id per type
    $taskIds = $projectIds = $clientIds = [];
    foreach ($rows as $r) {
        match ($r['entity_type']) {
            'task'    => $taskIds[]    = (int)$r['entity_id'],
            'project' => $projectIds[] = (int)$r['entity_id'],
            'client'  => $clientIds[]  = (int)$r['entity_id'],
            default   => null,
        };
    }
    
    // Batch fetch 3 query total (bukan N)
    $taskTitles = $projectNames = $clientNames = [];
    if ($taskIds) {
        $tvRows = $this->db->table('tb_task_values tv')
            ->select('tv.task_id, tv.value')
            ->join('tb_fields f', 'f.id = tv.field_id')
            ->where('f.field_key', 'judul')->where('f.status', 1)
            ->whereIn('tv.task_id', array_unique($taskIds))
            ->get()->getResultArray();
        $taskTitles = array_column($tvRows, 'value', 'task_id');
    }
    if ($projectIds) {
        $pRows = $this->db->table('tb_projects')->select('id, name')->whereIn('id', array_unique($projectIds))->get()->getResultArray();
        $projectNames = array_column($pRows, 'name', 'id');
    }
    if ($clientIds) {
        $cRows = $this->db->table('tb_clients')->select('id, name')->whereIn('id', array_unique($clientIds))->get()->getResultArray();
        $clientNames = array_column($cRows, 'name', 'id');
    }
    
    foreach ($rows as &$row) {
        $id = (int)$row['entity_id'];
        $row['label'] = match ($row['entity_type']) {
            'task'    => $taskTitles[$id]    ?? "Task #{$id}",
            'project' => $projectNames[$id]  ?? "Project #{$id}",
            'client'  => $clientNames[$id]   ?? "Client #{$id}",
            default   => "#{$id}",
        };
        $row['url'] = match ($row['entity_type']) {
            'task'    => base_url("tasks/{$id}"),
            'project' => base_url("projects/{$id}"),
            'client'  => base_url("clients/{$id}"),
            default   => '#',
        };
    }
    return $rows;
}
```

---

### BUG-H2: `addRevision` — tidak validasi format tanggal `requested_at` / `due_date`
**File:** `app/Controllers/TaskExtras.php` baris 273–274
**Masalah:** User bisa kirim `requested_at = "'; DROP TABLE tb_revisions; --"` atau string invalid. Meski PDO parameterized mencegah injection, nilai akan tersimpan sebagai `0000-00-00` di kolom DATE, merusak tampilan.

**Fix:**
```php
$requestedAt = $this->request->getPost('requested_at') ?? date('Y-m-d');
$dueDate     = $this->request->getPost('due_date') ?: null;

// Validasi format date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedAt) || !strtotime($requestedAt)) {
    $requestedAt = date('Y-m-d');
}
if ($dueDate !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !strtotime($dueDate))) {
    $dueDate = null;
}
```

---

### BUG-H3: `Projects::show` mengambil `$allTasks` tanpa pagination untuk dropdown relasi
**File:** `app/Controllers/Tasks.php` baris ~675
**Masalah:** Loop membangun `$allTasks` untuk dropdown relasi task. Jika ada 5000+ task, ini memuat semua ke memory hanya untuk mengisi satu `<select>`.

**Fix:** Ganti dropdown statis dengan AJAX search. Di frontend:
```javascript
// Ganti <select id="relatedTaskId"> dengan input + autocomplete
// Panggil GET /search?q={q}&type=task saat user ketik
```
Atau tambahkan limit di controller: `->limit(200)`.

---

### BUG-H4: `AttachmentModel::uploadForTask` — race condition pada `mkdir`
**File:** `app/Models/AttachmentModel.php` baris ~36
**Masalah:** `if (!is_dir($uploadPath)) { mkdir(...) }` tidak atomic. Dua upload bersamaan bisa keduanya masuk `if`, lalu keduanya `mkdir` — yang kedua throw PHP Warning.

**Fix:**
```php
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0755, true); // true = ignore jika sudah ada (atomic di PHP 8+)
}
// Atau gunakan @ untuk suppress warning jika sudah exist:
@mkdir($uploadPath, 0755, true);
```

---

### BUG-H5: Route konflik `tasks/(:any)/attachments/(:segment)/serve` vs route lain
**File:** `app/Config/Routes.php`
**Masalah:** Route `tasks/(:segment)/attachments/(:segment)/serve` menggunakan `:segment` yang cocok dengan string apapun termasuk `trash`, `submissions`, `bulk-create`. Urutan route saat ini menempatkan ini setelah route spesifik, tapi jika urutan berubah bisa menyebabkan konflik. Gunakan `:num` untuk `taskId`.

**Fix:**
```php
// SEKARANG:
$routes->get('tasks/(:num)/attachments/(:segment)/serve', 'TaskExtras::serveAttachment/$1/$2');
// Sudah benar - (:num) di posisi pertama sudah membatasi, tidak perlu ubah
// Tapi perlu dipastikan route ini ada SETELAH route tasks/:num yang spesifik
```
Verifikasi urutan: `tasks/submissions`, `tasks/trash` harus **di atas** `tasks/(:num)`.
**Cek routes.php** — sudah benar urutannya. Tidak perlu fix, tapi perlu monitoring.

---

## 🟡 MEDIUM

### BUG-M1: `CommentModel::dispatchMentions` — duplikasi notifikasi jika username = nickname user lain
**File:** `app/Models/CommentModel.php`
**Masalah:** Query `WHERE username = ? OR nickname = ?` bisa return user berbeda jika ada username = "budi" dan ada juga user lain dengan nickname = "budi". Bisa kirim notifikasi ke orang yang salah.

**Fix:** Prioritaskan `username` dulu, fallback ke `nickname`:
```php
$user = $this->db->table('tb_users')->where('username', $username)->get()->getRowArray();
if (!$user) {
    $user = $this->db->table('tb_users')->where('nickname', $username)->get()->getRowArray();
}
```

---

### BUG-M2: `TaskTemplateModel::getFieldValues` — tidak cek ownership sebelum return
**File:** `app/Models/TaskTemplateModel.php`
**Masalah:** `templateFields(int $id)` di controller return field values template **tanpa cek apakah user boleh akses template tersebut**. User bisa akses `GET /templates/999/fields` untuk template private milik user lain dengan ID 999.

**Fix di `TaskExtras::templateFields`:**
```php
public function templateFields(int $id): \CodeIgniter\HTTP\Response
{
    $uid = $this->uid();
    $tpl = $this->templateModel->find($id);
    if (!$tpl) return $this->json(['error' => 'Tidak ditemukan.'], 404);
    
    // Cek akses: milik sendiri atau public
    if ((int)$tpl['created_by'] !== $uid && !$tpl['is_public'] && 
        !in_array($this->role(), ['super_admin', 'admin'], true)) {
        return $this->json(['error' => 'Akses ditolak.'], 403);
    }
    
    $fields = $this->templateModel->getFieldValues($id);
    return $this->json(['data' => $fields]);
}
```

---

### BUG-M3: `RevisionModel::updateStatus` — `handled_by` di-set meski status kembali ke `pending`
**File:** `app/Models/RevisionModel.php`
**Masalah:** Jika admin mengubah status kembali ke `pending` (misalnya batal), `handled_by` tetap di-set ke admin yang baru. Ini inconsistent — seharusnya `handled_by = null` saat kembali ke pending.

**Fix:**
```php
$data = ['status' => $status, 'handler_note' => $note, 'updated_at' => date('Y-m-d H:i:s')];
if ($status === 'pending') {
    $data['handled_by'] = null;
    $data['resolved_at'] = null;
} else {
    $data['handled_by'] = $handlerId;
    if (in_array($status, ['done', 'rejected'])) {
        $data['resolved_at'] = date('Y-m-d H:i:s');
    }
}
```

---

### BUG-M4: `DeadlineReminder` — query `DATE(t.deadline) IN (...)` tidak pakai index
**File:** `app/Commands/DeadlineReminder.php`
**Masalah:** `DATE(t.deadline)` adalah function call — MySQL tidak bisa pakai index pada kolom `deadline`. Dengan ribuan task ini jadi full table scan.

**Fix:**
```php
// Ganti:
->where("DATE(t.deadline) IN ({$inList})", null, false)

// Dengan range scan (pakai index):
->where('t.deadline >=', $today . ' 00:00:00')
->where('t.deadline <=', $tomorrow . ' 23:59:59')
```

---

### BUG-M5: `global_search.php` — tidak ada debounce guard saat request overlap
**File:** `app/Views/components/global_search.php`
**Masalah:** Jika user mengetik cepat dan request pertama lambat, response bisa datang out-of-order. Huruf "a" bisa return hasil setelah "ab", menampilkan hasil yang tidak relevan.

**Fix: Abort request sebelumnya:**
```javascript
let currentController = null;

async function doSearch(q) {
    if (currentController) currentController.abort();
    currentController = new AbortController();
    try {
        const r = await fetch(BASE_URL + 'search?q=' + encodeURIComponent(q), 
            { signal: currentController.signal });
        const d = await r.json();
        renderResults(d.data || []);
    } catch (e) {
        if (e.name !== 'AbortError') console.error(e);
    }
}
```

---

### BUG-M6: `Clients::delete` — tidak cek apakah klien masih punya project aktif
**File:** `app/Controllers/Clients.php`
**Masalah:** Menghapus klien yang masih punya project aktif hanya akan set `client_id = NULL` di `tb_projects` (karena FK `ON DELETE SET NULL`). Project menjadi "orphan" tanpa klien — tidak ada warning ke user.

**Fix:**
```php
public function delete(int $id): \CodeIgniter\HTTP\RedirectResponse
{
    $this->requirePerm('manage_clients');
    
    // Cek project aktif
    $activeProjects = $this->projectModel->where('client_id', $id)
        ->where('status !=', 'completed')->countAllResults();
    if ($activeProjects > 0) {
        return redirect()->back()->with('error', 
            "Klien masih punya {$activeProjects} project aktif. Selesaikan atau pindahkan project dulu.");
    }
    
    $this->clientModel->delete($id);
    return redirect()->to('/clients')->with('success', 'Klien dihapus.');
}
```

---

## 🟢 LOW / SARAN

### BUG-L1: `tb_user_favorites` — tidak ada FK ke `tb_users`
**File:** Migration `notion_features`
Tabel `tb_user_favorites` tidak punya FK ke `tb_users`. Jika user dihapus, favorit-nya jadi orphan. Tambahkan saat alter table atau migration baru:
```sql
ALTER TABLE tb_user_favorites ADD CONSTRAINT fk_favorites_user
  FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE;
```

---

### BUG-L2: `tb_task_templates` — tidak ada FK ke `tb_users` untuk `created_by`
Sama dengan L1. `created_by` tidak punya FK — template tetap ada meski creator dihapus. Aman tapi inconsistent:
```sql
ALTER TABLE tb_task_templates ADD CONSTRAINT fk_templates_user
  FOREIGN KEY (created_by) REFERENCES tb_users(id) ON DELETE CASCADE;
```

---

### BUG-L3: Search global — `excerpt` komentar bisa mengandung HTML jika body mengandung tag
**File:** `app/Controllers/TaskExtras.php` baris ~720
`SUBSTRING(c.body, 1, 80)` tidak strip HTML. Jika user pernah input `<script>` di komentar (sebelum sanitasi), excerpt yang tampil di search bisa mengandung tag. `escHtml()` di JS sudah menangani ini tapi sebaiknya `strip_tags` di PHP juga:
```php
'meta' => strip_tags(substr($c['excerpt'] ?? '', 0, 80)),
```

---

### BUG-L4: `AttachmentModel::ALLOWED_MIMES` — `video/mp4` tanpa limit storage per task
Saat ini tidak ada batas jumlah attachment per task atau total storage per task. User bisa upload banyak video 20MB. Pertimbangkan tambah `MAX_ATTACHMENTS_PER_TASK = 20` atau cek total size.

---

## Fitur yang Tidak Perlu / Bisa Disederhanakan

### REDUDANT-1: `ExtraModels.php` di output lama — tidak digunakan
File `/home/claude/output/models/ExtraModels.php` berisi 4 class dalam 1 file. Di production, semua sudah dipecah ke file terpisah yang benar. File ini **tidak perlu di-deploy**.

### REDUDANT-2: `SyncProjectTaskFields` command — tidak relevan untuk install baru
Command `project:sync-fields` hanya dibutuhkan untuk migrasi data lama (sebelum fitur `project_id` di fields). Untuk fresh install tidak perlu dijalankan.

### REDUDANT-3: Migration `add_project_id_to_fields` — `field_scope_uid` generated column
Kolom `field_scope_uid` (generated column) cukup kompleks dan hanya support MySQLi. Jika tim mungkin pakai SQLite untuk testing, ini akan error. Pertimbangkan pakai unique index biasa:
```sql
-- Alternatif lebih sederhana (tanpa generated column):
ALTER TABLE tb_fields ADD UNIQUE KEY uq_scope_key (project_id, field_key);
-- project_id NULL dianggap unique per MySQL 5.7+ (NULL != NULL in unique index)
```

---

## Checklist Fix Prioritas

```
SEBELUM DEPLOY ke production:
[ ] BUG-C1: Fix NotificationModel::$updatedField = '' → false
[ ] BUG-C2: Fix TaskExtras::requirePerm() member bypass
[ ] BUG-C3: Fix serveAttachment file_get_contents → readfile/fpassthru
[ ] BUG-C4: Fix TaskRelationModel::getForTask correlated subquery

SEGERA SETELAH DEPLOY:
[ ] BUG-H1: Fix FavoriteModel N+1 → batch query
[ ] BUG-H2: Fix addRevision date validation
[ ] BUG-H4: Fix mkdir race condition (@mkdir)
[ ] BUG-M2: Fix templateFields ownership check
[ ] BUG-M3: Fix RevisionModel::updateStatus handled_by saat pending
[ ] BUG-M4: Fix DeadlineReminder query pakai index (range instead of DATE())
[ ] BUG-M6: Fix Clients::delete cek project aktif

SPRINT BERIKUTNYA:
[ ] BUG-M1: Fix mention username vs nickname ambiguity
[ ] BUG-M5: Tambah AbortController di global search
[ ] BUG-L1+L2: Tambah FK user ke favorites dan templates
[ ] BUG-L3: strip_tags pada comment excerpt di search
```

---

## Hal yang SUDAH BENAR (tidak perlu diubah)

- ✅ **CSRF**: Semua endpoint POST mengirim `csrf_hash()` di response JSON dan JS membacanya via `applyCsrfFromResponse()` — sudah benar
- ✅ **Path traversal attachment**: `basename($filename)` + regex `/^[a-zA-Z0-9._-]+$/` — sudah aman
- ✅ **XSS di global search**: `escHtml()` function sudah cover semua output ke innerHTML
- ✅ **Auth check di semua endpoint**: `$this->requirePerm()` dipanggil di setiap method publik
- ✅ **canMutateTask vs canViewTaskInScope**: Pembedaan sudah tepat (mutate butuh ownership, view lebih permissive untuk role tinggi)
- ✅ **TaskModel::allowedFields**: Sudah include `project_id` dan `parent_id`
- ✅ **Migration idempotent**: Semua `createXxx()` methods cek `tableExists()` / `fieldExists()` dulu — aman dijalankan berulang
- ✅ **DeadlineReminder duplikasi notif**: Cek `alreadySent` per user per hari — sudah ada
- ✅ **TaskAssigneeModel batch load user**: Tidak N+1, sudah pakai `whereIn` bulk fetch
- ✅ **Routes urutan**: `tasks/submissions`, `tasks/trash` sudah di atas `tasks/(:num)`
- ✅ **Clients::requirePerm**: Member diblok dengan benar (berbeda dengan TaskExtras yang bug)
- ✅ **RevisionModel ownership check**: Controller cek `rev['task_id'] === taskId` sebelum update — sudah ada di `updateRevisionStatus` dan `deleteRevision`

---

*Laporan ini di-generate berdasarkan analisis kode sumber `PRODUCTION-DEPLOY.zip`. 30 Maret 2026.*
