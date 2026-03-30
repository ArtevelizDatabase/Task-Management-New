<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * DummyDataSeeder — Seeds realistic demo data for all modules.
 *
 * Run with: php spark db:seed DummyDataSeeder
 *
 * Inserts:
 *  - 7 users (1 super_admin already exists, 6 new)
 *  - 4 teams with member assignments
 *  - 25 tasks (EAV) with diverse field values
 *  - Clients, projects, task project_id/parent_id (jika belum ada klien)
 *  - Komentar, activity log task, favorit, relasi task, assignee, revisi, template, lampiran demo
 *  - Backfill field judul dari theme (jika judul kosong)
 *  - 35+ notifications spread across users
 *  - Login attempts log
 *  - User activity log
 */
class DummyDataSeeder extends Seeder
{
    protected \CodeIgniter\Database\BaseConnection $_ddb;

    public function run(): void
    {
        $this->_ddb = \Config\Database::connect();
        $now      = date('Y-m-d H:i:s');

        // ── 1. Users ──────────────────────────────────────────────────────
        $password = password_hash('Demo@123', PASSWORD_BCRYPT, ['cost' => 10]);
        $users    = [
            [
                'username'      => 'riani_admin',
                'email'         => 'riani@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Riani Putri',
                'job_title'     => 'Project Administrator',
                'role'          => 'admin',
                'status'        => 'active',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-30 days')),
                'updated_at'    => $now,
            ],
            [
                'username'      => 'bagas_manager',
                'email'         => 'bagas@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Bagas Prasetyo',
                'job_title'     => 'Design Manager',
                'role'          => 'manager',
                'status'        => 'active',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-28 days')),
                'updated_at'    => $now,
            ],
            [
                'username'      => 'nabila_designer',
                'email'         => 'nabila@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Nabila Sari',
                'job_title'     => 'Senior Designer',
                'role'          => 'member',
                'status'        => 'active',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-25 days')),
                'updated_at'    => $now,
            ],
            [
                'username'      => 'dimas_designer',
                'email'         => 'dimas@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Dimas Ardana',
                'job_title'     => 'Graphic Designer',
                'role'          => 'member',
                'status'        => 'active',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-22 days')),
                'updated_at'    => $now,
            ],
            [
                'username'      => 'sinta_member',
                'email'         => 'sinta@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Sinta Maharani',
                'job_title'     => 'Motion Designer',
                'role'          => 'member',
                'status'        => 'active',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-20 days')),
                'updated_at'    => $now,
            ],
            [
                'username'      => 'aldo_member',
                'email'         => 'aldo@taskflow.local',
                'password_hash' => $password,
                'nickname'      => 'Aldo Firmansyah',
                'job_title'     => 'Illustrator',
                'role'          => 'member',
                'status'        => 'inactive',
                'last_login_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                'created_at'    => date('Y-m-d H:i:s', strtotime('-15 days')),
                'updated_at'    => $now,
            ],
        ];

        // Skip if already seeded
        if ($this->db->table('tb_users')->countAllResults() > 1) {
            echo "  [skip] Users already seeded.\n";
        } else {
            $this->db->table('tb_users')->insertBatch($users);
            echo "  [ok] 6 users created. (Password: Demo@123)\n";
        }

        // Get all user IDs
        $allUsers = $this->db->table('tb_users')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $userMap = array_column($allUsers, null, 'username');

        $saId      = $userMap['superadmin']['id']       ?? 1;
        $adminId   = $userMap['riani_admin']['id']      ?? 2;
        $managerId = $userMap['bagas_manager']['id']    ?? 3;
        $nabila    = $userMap['nabila_designer']['id']  ?? 4;
        $dimas     = $userMap['dimas_designer']['id']   ?? 5;
        $sinta     = $userMap['sinta_member']['id']     ?? 6;
        $aldo      = $userMap['aldo_member']['id']      ?? 7;

        // ── 2. Teams ──────────────────────────────────────────────────────
        if ($this->db->table('tb_teams')->countAllResults() > 0) {
            echo "  [skip] Teams already seeded.\n";
        } else {
            $teams = [
                ['name' => 'Design A',    'slug' => 'design-a',    'description' => 'Tim desainer utama untuk produk Annora & Hayaaro',      'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Design B',    'slug' => 'design-b',    'description' => 'Tim desainer untuk produk Grachnorine & client khusus', 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Upload Team', 'slug' => 'upload-team', 'description' => 'Bertanggung jawab atas upload ke marketplace',           'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Management',  'slug' => 'management',  'description' => 'Manajemen dan admin organisasi',                        'created_at' => $now, 'updated_at' => $now],
            ];
            $this->db->table('tb_teams')->insertBatch($teams);
            echo "  [ok] 4 teams created.\n";

            // Get team IDs
            $teamRows  = $this->db->table('tb_teams')->orderBy('id', 'ASC')->get()->getResultArray();
            $teamIds   = array_column($teamRows, 'id');
            [$tA, $tB, $tUp, $tMgmt] = $teamIds + [0,0,0,0];

            // Members
            $members = [
                ['team_id' => $tA,    'user_id' => $nabila,    'joined_at' => $now],
                ['team_id' => $tA,    'user_id' => $dimas,     'joined_at' => $now],
                ['team_id' => $tB,    'user_id' => $sinta,     'joined_at' => $now],
                ['team_id' => $tB,    'user_id' => $aldo,      'joined_at' => $now],
                ['team_id' => $tUp,   'user_id' => $managerId, 'joined_at' => $now],
                ['team_id' => $tMgmt, 'user_id' => $saId,      'joined_at' => $now],
                ['team_id' => $tMgmt, 'user_id' => $adminId,   'joined_at' => $now],
                ['team_id' => $tMgmt, 'user_id' => $managerId, 'joined_at' => $now],
            ];
            $this->db->table('tb_team_members')->insertBatch($members);
            echo "  [ok] Team members assigned.\n";
        }

        // ── 3. Tasks ──────────────────────────────────────────────────────
        $activeTaskCount = (int) $this->db->table('tb_task')
            ->where('deleted_at IS NULL', null, false)
            ->countAllResults();
        if ($activeTaskCount > 5) {
            echo "  [skip] Tasks already seeded.\n";
        } else {
            $this->_seedTasks($saId, $nabila, $dimas, $sinta, $adminId, $now);
        }

        // ── 3b. Submissions (sync from existing tasks + setor flag) ───────
        if ($this->db->tableExists('tb_submissions')) {
            if ($this->db->table('tb_submissions')->countAllResults() > 0) {
                echo "  [skip] Submissions already seeded.\n";
            } else {
                $this->_seedSubmissionsFromTasks();
            }
        }

        // ── 4. Notifications ──────────────────────────────────────────────
        if ($this->db->table('tb_notifications')->countAllResults() > 0) {
            echo "  [skip] Notifications already seeded.\n";
        } else {
            $this->_seedNotifications($saId, $adminId, $managerId, $nabila, $dimas, $sinta);
        }

        // ── 5. Login attempts ─────────────────────────────────────────────
        if ($this->db->table('tb_auth_login_attempts')->countAllResults() > 0) {
            echo "  [skip] Login attempts already seeded.\n";
        } else {
            $this->_seedLoginAttempts($allUsers);
        }

        // ── 6. User activity ──────────────────────────────────────────────
        if ($this->db->table('tb_user_activity')->countAllResults() > 0) {
            echo "  [skip] User activity already seeded.\n";
        } else {
            $this->_seedActivity($saId, $adminId, $managerId, $nabila, $dimas);
        }

        // ── 7. Judul EAV (untuk UI / pencarian) ────────────────────────────
        $this->_backfillJudulFromTheme();

        // ── 8. Klien, project, kolaborasi (Notion-style) ───────────────────
        $this->_seedCollaborationDemo($adminId, $managerId, $nabila, $dimas, $now);

        echo "\n  Done! All dummy data seeded.\n";
        echo "  Login credentials:\n";
        echo "    superadmin / Admin\@123\n";
        echo "    riani_admin / Demo\@123\n";
        echo "    nabila_designer / Demo\@123\n";
        echo "  Akses fitur baru: admin/manager → klien, proyek, semua task & komentar.\n";
        echo "  Member → hanya task miliknya (user_id); komentar di task itu tetap terlihat.\n";
        echo "  Kolaborasi demo (klien/proyek/komentar/…) hanya di-insert jika tb_clients masih kosong.\n";
    }

    // ── Task seeder ────────────────────────────────────────────────────────

    private function _seedTasks(int $sa, int $nabila, int $dimas, int $sinta, int $admin, string $now): void
    {
        // Get field IDs
        $fields = $this->db->table('tb_fields')->get()->getResultArray();
        $fMap   = array_column($fields, 'id', 'field_key');

        $artboards = ['Presentation', 'Flyer', 'Social Media', 'Brochure', 'Logo', 'Infographic'];
        $accounts  = ['Annora', 'Hayaaro', 'Grachnorine', 'Client'];
        $priorities = ['Low', 'Medium', 'High', 'Urgent'];
        $progressOpts = ['Not Started', 'In Progress', 'Done'];
        $statuses  = ['pending', 'on_progress', 'done'];

        $taskDefs = [
            // id, user_id, status, progress, deadline_days, fields[date,pic_name,theme,artboard,account,priority,progress_val,setor]
            [$sa,     'done',        100, -5,  'Business Annual Report 2025',        'Presentation', 'Annora',      'High',   'Done',         1],
            [$nabila, 'done',        100, -3,  'Chinese New Year Banner',            'Flyer',        'Hayaaro',     'Medium', 'Done',         1],
            [$dimas,  'on_progress', 60,  2,   'Product Launch Social Kit',          'Social Media', 'Grachnorine', 'High',   'In Progress',  0],
            [$sinta,  'on_progress', 40,  3,   'Startup Pitch Deck Template',        'Presentation', 'Annora',      'Medium', 'In Progress',  0],
            [$nabila, 'pending',     0,   5,   'Minimalist Portfolio Cover',         'Brochure',     'Hayaaro',     'Low',    'Not Started',  0],
            [$dimas,  'done',        100, -8,  'E-Commerce Banner Pack',             'Flyer',        'Annora',      'Medium', 'Done',         1],
            [$sinta,  'done',        100, -2,  'Corporate Identity Package',         'Logo',         'Grachnorine', 'High',   'Done',         0],
            [$nabila, 'on_progress', 75,  1,   'Food & Beverage Menu Design',        'Brochure',     'Client',      'Medium', 'In Progress',  0],
            [$dimas,  'pending',     0,   7,   'Fashion Lookbook 2026',              'Brochure',     'Hayaaro',     'Low',    'Not Started',  0],
            [$sa,     'on_progress', 50,  4,   'Real Estate Listing Template',       'Presentation', 'Annora',      'High',   'In Progress',  0],
            [$nabila, 'done',        100, -10, 'Ramadan Promo Bundle',               'Flyer',        'Hayaaro',     'Urgent', 'Done',         1],
            [$sinta,  'pending',     0,   6,   'Wedding Invitation Suite',           'Brochure',     'Client',      'Medium', 'Not Started',  0],
            [$dimas,  'on_progress', 30,  8,   'Tech Startup Pitch Presentation',    'Presentation', 'Annora',      'High',   'In Progress',  0],
            [$nabila, 'done',        100, -6,  'Fitness App UI Kit',                 'Social Media', 'Grachnorine', 'Medium', 'Done',         0],
            [$sinta,  'done',        100, -4,  'Travel Agency Brochure Pack',        'Brochure',     'Client',      'Low',    'Done',         1],
            [$dimas,  'pending',     0,   10,  'Music Event Poster Series',          'Flyer',        'Hayaaro',     'Medium', 'Not Started',  0],
            [$admin,  'on_progress', 80,  2,   'Education Platform Explainer',       'Infographic',  'Annora',      'High',   'In Progress',  0],
            [$nabila, 'pending',     0,   12,  'Sustainable Brand Identity',         'Logo',         'Grachnorine', 'Low',    'Not Started',  0],
            [$sinta,  'done',        100, -1,  'Healthcare Data Dashboard',          'Infographic',  'Client',      'Urgent', 'Done',         1],
            [$dimas,  'on_progress', 20,  15,  'Kids Education Worksheet Pack',      'Brochure',     'Annora',      'Medium', 'In Progress',  0],
            [$nabila, 'pending',     0,   3,   'Crypto Explainer Infographic',       'Infographic',  'Hayaaro',     'High',   'Not Started',  0],
            [$sa,     'done',        100, -14, 'Year End Report Presentation',       'Presentation', 'Grachnorine', 'Urgent', 'Done',         1],
            [$sinta,  'pending',     0,   9,   'Coffee Shop Brand Identity',         'Logo',         'Client',      'Medium', 'Not Started',  0],
            [$dimas,  'done',        100, -9,  'Luxury Hotel Brochure',              'Brochure',     'Annora',      'High',   'Done',         1],
            [$nabila, 'on_progress', 90,  1,   'NFT Art Collection Promo Pack',      'Social Media', 'Grachnorine', 'Medium', 'In Progress',  0],
        ];

        $insertedTasks = 0;
        foreach ($taskDefs as $i => $t) {
            [$userId, $status, $progress, $dlDays, $theme, $artboard, $account, $priority, $progressVal, $setor] = $t;

            $createdAt = date('Y-m-d H:i:s', strtotime("-" . (20 - $i) . " days"));
            $taskDate  = date('Y-m-d', strtotime("-" . (20 - $i) . " days"));
            $deadlineDate = $dlDays ? date('Y-m-d', strtotime("{$dlDays} days")) : null;

            $this->db->table('tb_task')->insert([
                'user_id'    => $userId,
                'status'     => $status,
                'progress'   => $progress,
                'deadline'   => $deadlineDate,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $taskId = (int) $this->db->insertID();
            if ($taskId <= 0) {
                continue;
            }

            // EAV field values
            $picUser = array_values(array_filter(
                $this->db->table('tb_users')->where('id', $userId)->get()->getResultArray()
            ))[0] ?? null;
            $picName = $picUser ? ($picUser['nickname'] ?? $picUser['username']) : 'Unknown';

            $fieldValues = [
                ['task_id' => $taskId, 'field_id' => $fMap['date']     ?? 1,  'value' => $taskDate,     'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['task_id' => $taskId, 'field_id' => $fMap['pic_name'] ?? 2,  'value' => $picName,      'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['task_id' => $taskId, 'field_id' => $fMap['theme']    ?? 3,  'value' => $theme,        'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['task_id' => $taskId, 'field_id' => $fMap['artboard'] ?? 4,  'value' => $artboard,     'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['task_id' => $taskId, 'field_id' => $fMap['account']  ?? 5,  'value' => $account,      'created_at' => $createdAt, 'updated_at' => $createdAt],
            ];

            if (isset($fMap['priority'])) {
                $fieldValues[] = ['task_id' => $taskId, 'field_id' => $fMap['priority'], 'value' => $priority, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            if (isset($fMap['progress'])) {
                $fieldValues[] = ['task_id' => $taskId, 'field_id' => $fMap['progress'], 'value' => $progressVal, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            if (isset($fMap['setor'])) {
                $fieldValues[] = ['task_id' => $taskId, 'field_id' => $fMap['setor'], 'value' => $setor ? '1' : '0', 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            if (isset($fMap['judul'])) {
                $fieldValues[] = ['task_id' => $taskId, 'field_id' => $fMap['judul'], 'value' => $theme, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }

            $this->db->table('tb_task_values')->insertBatch($fieldValues);
            $insertedTasks++;
        }

        echo "  [ok] {$insertedTasks} tasks created.\n";
    }

    // ── Notification seeder ────────────────────────────────────────────────

    private function _seedNotifications(int $sa, int $admin, int $mgr, int $nabila, int $dimas, int $sinta): void
    {
        $now = date('Y-m-d H:i:s');

        $notifs = [
            // [user_id, type, title, message, is_read, minutes_ago]
            [$nabila, 'task',    'Task Baru Ditugaskan',             'Anda ditugaskan task "Business Annual Report 2025" oleh Super Admin.',      0, 10],
            [$dimas,  'task',    'Task Baru Ditugaskan',             'Anda ditugaskan task "Product Launch Social Kit" oleh Super Admin.',        0, 15],
            [$sinta,  'task',    'Task Baru Ditugaskan',             'Anda ditugaskan task "Startup Pitch Deck Template" oleh Super Admin.',      0, 20],
            [$nabila, 'success', 'Task Disetujui',                   'Task "Chinese New Year Banner" telah disetujui dan siap upload.',           1, 60],
            [$dimas,  'warning', 'Deadline Mendekat',                'Task "Product Launch Social Kit" jatuh tempo dalam 2 hari.',               0, 30],
            [$sinta,  'warning', 'Deadline Mendekat',                'Task "Startup Pitch Deck Template" jatuh tempo dalam 3 hari.',             0, 45],
            [$admin,  'user',    'User Baru Bergabung',              'Nabila Sari telah bergabung ke tim Design A.',                             1, 120],
            [$sa,     'system',  'Migrasi Database Selesai',         'Semua tabel berhasil dimigrasikan ke versi terbaru.',                       1, 180],
            [$mgr,    'task',    'Progress Update',                  'Dimas Ardana mengupdate progress task "E-Commerce Banner Pack" ke 60%.',   0, 25],
            [$nabila, 'info',    'Profil Diperbarui',                'Profil Anda berhasil diperbarui.',                                         1, 200],
            [$dimas,  'success', 'Upload Disetujui',                 'Produk "Ramadan Promo Bundle" disetujui oleh Envato Elements.',            0, 5],
            [$sinta,  'info',    'Tim Baru',                         'Anda telah ditambahkan ke tim Design B.',                                  1, 300],
            [$sa,     'warning', 'Login Gagal Terdeteksi',           'Terdeteksi 3 percobaan login gagal dari IP 192.168.1.100.',                0, 8],
            [$admin,  'task',    'Task Selesai',                     'Sinta Maharani menyelesaikan task "Corporate Identity Package".',           1, 90],
            [$nabila, 'task',    'Komentar Baru',                    'Admin menambahkan catatan pada task "Food & Beverage Menu Design".',        0, 12],
            [$dimas,  'info',    'Password Diubah',                  'Password akun Anda berhasil diubah.',                                      1, 1440],
            [$mgr,    'success', 'Export Berhasil',                  'Laporan bulanan berhasil diekspor ke format Excel.',                       1, 400],
            [$sinta,  'task',    'Task Baru: Wedding Invitation',    'Anda ditugaskan task "Wedding Invitation Suite" dengan deadline 6 hari.',  0, 3],
            [$sa,     'user',    'Role Diperbarui',                  'Role user Aldo Firmansyah diubah dari member menjadi inactive.',           1, 500],
            [$admin,  'system',  'Sistem Pemeliharaan',              'Pemeliharaan sistem dijadwalkan pada 30 Mar 2026, 02.00 WIB.',             0, 60],
            [$nabila, 'success', 'Task Selesai: Ramadan Promo',      'Task "Ramadan Promo Bundle" telah selesai dan masuk ke antrian upload.',   1, 800],
            [$dimas,  'warning', 'Kuota Upload Hampir Penuh',        'Kuota penyimpanan file upload sudah mencapai 80%.',                        0, 180],
            [$sinta,  'task',    'Status Task Diperbarui',           'Status task "Healthcare Data Dashboard" berubah menjadi Done.',            1, 150],
            [$mgr,    'info',    'Laporan Mingguan Tersedia',        'Laporan produktivitas minggu ini sudah tersedia di halaman Reports.',      0, 720],
            [$sa,     'task',    'Task Urgent Baru',                 'Task "NFT Art Collection Promo Pack" ditandai sebagai URGENT.',            0, 2],
        ];

        $rows = [];
        foreach ($notifs as $n) {
            [$userId, $type, $title, $message, $isRead, $minsAgo] = $n;
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$minsAgo} minutes"));
            $rows[] = [
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'is_read'    => $isRead,
                'read_at'    => $isRead ? date('Y-m-d H:i:s', strtotime("-" . ($minsAgo - 5) . " minutes")) : null,
                'created_at' => $createdAt,
            ];
        }

        $this->db->table('tb_notifications')->insertBatch($rows);
        echo "  [ok] " . count($rows) . " notifications created.\n";
    }

    // ── Login attempts seeder ──────────────────────────────────────────────

    private function _seedLoginAttempts(array $allUsers): void
    {
        $rows = [];
        $ips  = ['192.168.1.100', '10.0.0.45', '172.16.0.23', '::1', '203.0.113.42'];
        $uas  = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/125.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4) AppleWebKit/605.1.15 Safari/604.1',
        ];

        foreach ($allUsers as $u) {
            // 2-4 successful logins per user
            for ($i = 0; $i < rand(2, 4); $i++) {
                $rows[] = [
                    'identifier'     => $u['email'],
                    'ip_address'     => $ips[array_rand($ips)],
                    'user_agent'     => $uas[array_rand($uas)],
                    'success'        => 1,
                    'failure_reason' => null,
                    'created_at'     => date('Y-m-d H:i:s', strtotime("-" . rand(1, 720) . " minutes")),
                ];
            }
            // 0-2 failed logins
            for ($i = 0; $i < rand(0, 2); $i++) {
                $rows[] = [
                    'identifier'     => $u['email'],
                    'ip_address'     => $ips[array_rand($ips)],
                    'user_agent'     => $uas[array_rand($uas)],
                    'success'        => 0,
                    'failure_reason' => ['wrong_password', 'user_not_found'][rand(0, 1)],
                    'created_at'     => date('Y-m-d H:i:s', strtotime("-" . rand(1, 2880) . " minutes")),
                ];
            }
        }

        $this->db->table('tb_auth_login_attempts')->insertBatch($rows);
        echo "  [ok] " . count($rows) . " login attempts created.\n";
    }

    // ── User activity seeder ───────────────────────────────────────────────

    private function _seedActivity(int $sa, int $admin, int $mgr, int $nabila, int $dimas): void
    {
        $rows = [
            [$sa,     'login',           'Login dari IP ::1'],
            [$sa,     'create_user',     'Membuat user baru: nabila_designer'],
            [$sa,     'create_user',     'Membuat user baru: dimas_designer'],
            [$sa,     'update_role',     'Memperbarui role: Manager'],
            [$sa,     'login',           'Login dari IP ::1'],
            [$admin,  'login',           'Login dari IP 192.168.1.100'],
            [$admin,  'update_user',     'Memperbarui profil user: nabila_designer'],
            [$admin,  'create_task',     'Membuat task baru: Education Platform Explainer'],
            [$mgr,    'login',           'Login dari IP 10.0.0.45'],
            [$mgr,    'update_task',     'Update progress task: Product Launch Social Kit ke 60%'],
            [$nabila, 'login',           'Login dari IP 172.16.0.23'],
            [$nabila, 'update_profile',  'Memperbarui profil'],
            [$nabila, 'change_password', 'Mengubah password'],
            [$nabila, 'update_task',     'Update status task: Ramadan Promo Bundle ke Done'],
            [$dimas,  'login',           'Login dari IP 10.0.0.45'],
            [$dimas,  'update_task',     'Update progress task: E-Commerce Banner Pack ke 100%'],
            [$dimas,  'update_profile',  'Memperbarui foto profil'],
            [$sa,     'impersonate_start', 'Mulai impersonation user: nabila_designer'],
            [$sa,     'impersonate_stop',  'Berhenti impersonation user: nabila_designer'],
            [$admin,  'delete_task',     'Menghapus task ID #2'],
        ];

        $batchRows = [];
        foreach ($rows as $i => $r) {
            [$userId, $action, $desc] = $r;
            $batchRows[] = [
                'user_id'    => $userId,
                'action'     => $action,
                'description'=> $desc,
                'ip_address' => ['::1', '192.168.1.100', '10.0.0.45'][rand(0,2)],
                'created_at' => date('Y-m-d H:i:s', strtotime("-" . (count($rows) - $i) * 15 . " minutes")),
            ];
        }

        $this->db->table('tb_user_activity')->insertBatch($batchRows);
        echo "  [ok] " . count($batchRows) . " activity records created.\n";
    }

    private function _seedSubmissionsFromTasks(): void
    {
        $now = date('Y-m-d H:i:s');

        $fieldRows = $this->db->table('tb_fields')
            ->select('id, field_key, submission_col, data_source')
            ->where('status', 1)
            ->get()
            ->getResultArray();
        if ($fieldRows === []) {
            echo "  [skip] No fields found for submission sync.\n";
            return;
        }

        $fieldByKey = [];
        foreach ($fieldRows as $f) {
            $fieldByKey[(string) $f['field_key']] = $f;
        }
        $mappedFields = array_values(array_filter($fieldRows, static function (array $f): bool {
            return !empty($f['submission_col']);
        }));

        $submissionCols = array_map(
            static fn(object $c): string => (string) $c->name,
            $this->db->getFieldData('tb_submissions')
        );
        $allowedCols = array_flip($submissionCols);

        $tasks = $this->db->table('tb_task')
            ->select('id, created_at')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();
        if ($tasks === []) {
            echo "  [skip] No tasks to sync submissions.\n";
            return;
        }

        $inserted = 0;
        foreach ($tasks as $task) {
            $taskId = (int) ($task['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $valueRows = $this->db->table('tb_task_values tv')
                ->select('f.field_key, f.data_source, tv.value')
                ->join('tb_fields f', 'f.id = tv.field_id', 'inner')
                ->where('tv.task_id', $taskId)
                ->where('f.status', 1)
                ->get()
                ->getResultArray();
            if ($valueRows === []) {
                continue;
            }

            $values = [];
            $dataSourceByKey = [];
            foreach ($valueRows as $row) {
                $k = (string) ($row['field_key'] ?? '');
                if ($k === '') {
                    continue;
                }
                $values[$k] = (string) ($row['value'] ?? '');
                $dataSourceByKey[$k] = (string) ($row['data_source'] ?? 'manual');
            }

            $setorVal = $values['setor'] ?? '0';
            $isSetor  = in_array($setorVal, ['1', 'true', 'on'], true);
            if (!$isSetor) {
                continue;
            }

            $payload = [
                'task_id'     => $taskId,
                'updated_at'  => $now,
                'created_at'  => (string) ($task['created_at'] ?? $now),
            ];

            foreach ($mappedFields as $field) {
                $key = (string) ($field['field_key'] ?? '');
                $col = (string) ($field['submission_col'] ?? '');
                if ($key === '' || $col === '' || !isset($allowedCols[$col])) {
                    continue;
                }
                $raw = trim((string) ($values[$key] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $value = $this->_resolveSeedDisplayValue($raw, (string) ($field['data_source'] ?? 'manual'), $key);
                $payload[$col] = $value;
            }

            // Fallbacks for common submission columns if mapping is absent/incomplete
            if (isset($allowedCols['product_name']) && empty($payload['product_name']) && !empty($values['theme'])) {
                $payload['product_name'] = trim((string) $values['theme']);
            }
            if (isset($allowedCols['category']) && empty($payload['category']) && !empty($values['artboard'])) {
                $payload['category'] = trim((string) $values['artboard']);
            }
            if (isset($allowedCols['pic_name']) && empty($payload['pic_name']) && !empty($values['pic_name'])) {
                $payload['pic_name'] = $this->_resolveSeedDisplayValue(
                    (string) $values['pic_name'],
                    (string) ($dataSourceByKey['pic_name'] ?? 'manual'),
                    'pic_name'
                );
            }
            if (isset($allowedCols['account']) && empty($payload['account']) && !empty($values['account'])) {
                $payload['account'] = $this->_resolveSeedDisplayValue(
                    (string) $values['account'],
                    (string) ($dataSourceByKey['account'] ?? 'manual'),
                    'account'
                );
            }
            if (isset($allowedCols['date']) && empty($payload['date']) && !empty($values['date'])) {
                $payload['date'] = trim((string) $values['date']);
            }
            if (isset($allowedCols['link_setor']) && empty($payload['link_setor'])) {
                $payload['link_setor'] = 'https://example.com/submission/' . $taskId;
            }

            // Keep only existing table columns
            $payload = array_intersect_key($payload, $allowedCols);
            if (!isset($payload['task_id'])) {
                continue;
            }

            $this->db->table('tb_submissions')->insert($payload);
            $inserted++;
        }

        echo "  [ok] {$inserted} submissions synced from tasks.\n";
    }

    private function _resolveSeedDisplayValue(string $value, string $dataSource, string $fieldKey): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        if ($dataSource === 'team_users' || in_array($fieldKey, ['pic', 'pic_name'], true)) {
            $uid = (int) $raw;
            if ($uid > 0) {
                $u = $this->db->table('tb_users')
                    ->select('nickname, username')
                    ->where('id', $uid)
                    ->get()
                    ->getRowArray();
                if ($u) {
                    $label = trim((string) ($u['nickname'] ?? ''));
                    if ($label === '') {
                        $label = trim((string) ($u['username'] ?? ''));
                    }
                    if ($label !== '') {
                        return $label;
                    }
                }
            }
        }

        if ($dataSource === 'account_sources' || $fieldKey === 'account') {
            if (strpos($raw, ':') !== false) {
                [$source, $idRaw] = explode(':', $raw, 2);
                $id = (int) $idRaw;
                if ($id > 0) {
                    if ($source === 'account' && $this->db->tableExists('tb_accounts')) {
                        $acc = $this->db->table('tb_accounts')->select('name')->where('id', $id)->get()->getRowArray();
                        if (!empty($acc['name'])) {
                            return trim((string) $acc['name']);
                        }
                    }
                    if ($source === 'office' && $this->db->tableExists('tb_accounts')) {
                        $acc = $this->db->table('tb_accounts')->select('name')->where('legacy_office_id', $id)->get()->getRowArray();
                        if (!empty($acc['name'])) {
                            return trim((string) $acc['name']);
                        }
                    }
                    if ($source === 'vendor' && $this->db->tableExists('tb_accounts')) {
                        $acc = $this->db->table('tb_accounts')->select('name')->where('legacy_vendor_id', $id)->get()->getRowArray();
                        if (!empty($acc['name'])) {
                            return trim((string) $acc['name']);
                        }
                    }
                }
            }
        }

        return $raw;
    }

    /**
     * Isi field judul dari theme bila judul belum ada (task lama / field judul ditambahkan belakangan).
     */
    private function _backfillJudulFromTheme(): void
    {
        $fields = $this->db->table('tb_fields')->get()->getResultArray();
        $byKey  = array_column($fields, 'id', 'field_key');
        if (! isset($byKey['judul'], $byKey['theme'])) {
            return;
        }

        $judulId = (int) $byKey['judul'];
        $themeId = (int) $byKey['theme'];
        $now     = date('Y-m-d H:i:s');

        $tasks = $this->db->table('tb_task')
            ->select('id')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        $n = 0;
        foreach ($tasks as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $hasJudul = $this->db->table('tb_task_values')
                ->where('task_id', $tid)
                ->where('field_id', $judulId)
                ->countAllResults();
            if ($hasJudul > 0) {
                continue;
            }
            $themeRow = $this->db->table('tb_task_values')
                ->where('task_id', $tid)
                ->where('field_id', $themeId)
                ->get()
                ->getRowArray();
            if (! $themeRow || trim((string) ($themeRow['value'] ?? '')) === '') {
                continue;
            }
            $this->db->table('tb_task_values')->insert([
                'task_id'    => $tid,
                'field_id'   => $judulId,
                'value'      => (string) $themeRow['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $n++;
        }
        if ($n > 0) {
            echo "  [ok] Judul di-backfill dari theme untuk {$n} task.\n";
        }
    }

    /**
     * Dummy klien/proyek + isi fitur kolaborasi. Lewati jika tb_clients sudah berisi.
     */
    private function _seedCollaborationDemo(
        int $adminId,
        int $managerId,
        int $nabila,
        int $dimas,
        string $now
    ): void {
        if (! $this->db->tableExists('tb_clients')) {
            echo "  [skip] tb_clients tidak ada (jalankan migrate Notion features).\n";

            return;
        }

        if ($this->db->table('tb_clients')->countAllResults() > 0) {
            echo "  [skip] Collaboration demo — klien sudah ada.\n";

            return;
        }

        $clients = [
            [
                'name'       => 'PT Annora Digital',
                'contact'    => 'Budi Santoso',
                'email'      => 'budi@annora-demo.test',
                'phone'      => '081234500001',
                'status'     => 'active',
                'notes'      => 'Klien demo — brand & presentasi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Hayaaro Studio',
                'contact'    => 'Maya Lestari',
                'email'      => 'maya@hayaaro-demo.test',
                'phone'      => '081234500002',
                'status'     => 'active',
                'notes'      => 'Klien demo — social & print.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Grachnorine Brand Co.',
                'contact'    => 'Raka Wijaya',
                'email'      => 'raka@grach-demo.test',
                'phone'      => '081234500003',
                'status'     => 'active',
                'notes'      => 'Klien demo — identitas & kampanye.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Retail Nusantara (Umum)',
                'contact'    => 'Helpdesk',
                'email'      => 'support@retail-demo.test',
                'phone'      => '0215550100',
                'status'     => 'active',
                'notes'      => 'Klien umum untuk task bertipe Client.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        $this->db->table('tb_clients')->insertBatch($clients);
        $clientRows = $this->db->table('tb_clients')->orderBy('id', 'ASC')->get()->getResultArray();
        $cids       = array_column($clientRows, 'id');
        $c0         = (int) ($cids[0] ?? 0);
        $c1         = (int) ($cids[1] ?? $c0);
        $c2         = (int) ($cids[2] ?? $c0);
        $c3         = (int) ($cids[3] ?? $c0);

        $projects = [
            ['client_id' => $c0, 'name' => 'Kampanye Q1 2026', 'description' => 'Aset digital & presentasi untuk kuartal pertama.', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $c0, 'name' => 'Annual Report Suite', 'description' => 'Laporan tahunan & infografis.', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $c1, 'name' => 'Social Refresh', 'description' => 'Template feed & stories.', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $c2, 'name' => 'Rebranding 2026', 'description' => 'Logo, palet, dan guideline.', 'status' => 'on_hold', 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $c3, 'name' => 'Proyek Ad-hoc Retail', 'description' => 'Task satuan tanpa retainer.', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ];
        $this->db->table('tb_projects')->insertBatch($projects);
        $projRows = $this->db->table('tb_projects')->orderBy('id', 'ASC')->get()->getResultArray();
        $pids       = array_column($projRows, 'id');
        if ($pids === []) {
            echo "  [warn] Proyek gagal dibuat.\n";

            return;
        }

        echo '  [ok] ' . count($clientRows) . ' klien & ' . count($pids) . " proyek dibuat.\n";

        // Hubungkan task ke proyek + sub-task (parent_id)
        if ($this->db->fieldExists('project_id', 'tb_task')) {
            $tasks = $this->db->table('tb_task')
                ->select('id')
                ->where('deleted_at IS NULL', null, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $pc = count($pids);
            foreach ($tasks as $i => $row) {
                $tid = (int) ($row['id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $pid = (int) $pids[$i % $pc];
                $this->db->table('tb_task')->where('id', $tid)->update(['project_id' => $pid]);
            }
            if ($this->db->fieldExists('parent_id', 'tb_task') && count($tasks) >= 4) {
                $parentId = (int) $tasks[0]['id'];
                for ($j = 1; $j <= min(3, count($tasks) - 1); $j++) {
                    $cid = (int) $tasks[$j]['id'];
                    if ($cid === $parentId) {
                        continue;
                    }
                    $this->db->table('tb_task')->where('id', $cid)->update(['parent_id' => $parentId]);
                }
                echo "  [ok] project_id & parent_id diisi untuk task.\n";
            } else {
                echo "  [ok] project_id diisi untuk task.\n";
            }
        }

        $taskIds = $this->db->table('tb_task')
            ->select('id, user_id')
            ->where('deleted_at IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $ids = array_column($taskIds, 'id');

        // Komentar: pemilik task + admin/manager (semua punya akses sesuai role)
        if ($this->db->tableExists('tb_comments') && $this->db->table('tb_comments')->countAllResults() === 0) {
            $commentBodies = [
                'Silakan revisi warna utama agar kontras WCAG AA.',
                'Deadline bisa geser +2 hari jika perlu — konfirmasi di sini.',
                'Asset sudah di-upload ke folder tim; link internal menyusul.',
                'Noted, akan saya kerjakan bagian layout halaman 3–5.',
                'Komentar demo: siap untuk uji Ctrl+K / notifikasi.',
            ];
            $rows       = [];
            $bodyIdx    = 0;
            $pickTasks  = array_slice($taskIds, 0, 12);
            foreach ($pickTasks as $tr) {
                $tid = (int) $tr['id'];
                $owner = (int) ($tr['user_id'] ?? 0);
                $rows[] = [
                    'task_id'    => $tid,
                    'user_id'    => $owner > 0 ? $owner : $nabila,
                    'body'       => $commentBodies[$bodyIdx % count($commentBodies)],
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($bodyIdx + 1) . ' hours')),
                    'updated_at' => $now,
                ];
                $bodyIdx++;
                $rows[] = [
                    'task_id'    => $tid,
                    'user_id'    => $adminId,
                    'body'       => '[Admin] ' . $commentBodies[($bodyIdx + 2) % count($commentBodies)],
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($bodyIdx + 3) . ' hours')),
                    'updated_at' => $now,
                ];
                $bodyIdx++;
            }
            if ($rows !== []) {
                $this->db->table('tb_comments')->insertBatch($rows);
                echo '  [ok] ' . count($rows) . " komentar dibuat (akses: login sebagai pemilik task atau admin/manager).\n";
            }
        }

        // Activity log per task
        if ($this->db->tableExists('tb_activity_log') && $this->db->table('tb_activity_log')->countAllResults() === 0) {
            $logRows = [];
            foreach (array_slice($ids, 0, 8) as $i => $tid) {
                $logRows[] = [
                    'task_id'     => $tid,
                    'user_id'     => $i % 2 === 0 ? $adminId : $managerId,
                    'action'      => $i % 3 === 0 ? 'status_changed' : 'field_updated',
                    'description' => $i % 3 === 0 ? 'Status disesuaikan (data demo).' : 'Field diperbarui (data demo).',
                    'old_value'   => null,
                    'new_value'   => null,
                    'created_at'  => date('Y-m-d H:i:s', strtotime('-' . (20 - $i) . ' hours')),
                ];
            }
            if ($logRows !== []) {
                $this->db->table('tb_activity_log')->insertBatch($logRows);
                echo '  [ok] ' . count($logRows) . " entri activity log task.\n";
            }
        }

        // Favorit (admin)
        if ($this->db->tableExists('tb_user_favorites') && $this->db->table('tb_user_favorites')->countAllResults() === 0) {
            $fav = [];
            if ($ids !== []) {
                $fav[] = ['user_id' => $adminId, 'entity_type' => 'task', 'entity_id' => (int) $ids[0], 'created_at' => $now];
            }
            if ($pids !== []) {
                $fav[] = ['user_id' => $adminId, 'entity_type' => 'project', 'entity_id' => (int) $pids[0], 'created_at' => $now];
            }
            if ($cids !== []) {
                $fav[] = ['user_id' => $adminId, 'entity_type' => 'client', 'entity_id' => (int) $cids[0], 'created_at' => $now];
            }
            if ($fav !== []) {
                $this->db->table('tb_user_favorites')->insertBatch($fav);
                echo '  [ok] ' . count($fav) . " favorit (task/proyek/klien) untuk admin.\n";
            }
        }

        // Relasi antar-task
        if ($this->db->tableExists('tb_task_relations') && $this->db->table('tb_task_relations')->countAllResults() === 0 && count($ids) >= 3) {
            $this->db->table('tb_task_relations')->insertBatch([
                [
                    'task_id'         => (int) $ids[0],
                    'related_task_id' => (int) $ids[1],
                    'relation_type'   => 'relates_to',
                    'created_by'      => $adminId,
                    'created_at'      => $now,
                ],
                [
                    'task_id'         => (int) $ids[1],
                    'related_task_id' => (int) $ids[2],
                    'relation_type'   => 'blocked_by',
                    'created_by'      => $managerId,
                    'created_at'      => $now,
                ],
            ]);
            echo "  [ok] 2 relasi task (relates_to, blocked_by).\n";
        }

        // Assignee (tampilan di detail task; member tetap hanya melihat task miliknya di index)
        if ($this->db->tableExists('tb_task_assignees') && $this->db->table('tb_task_assignees')->countAllResults() === 0 && count($ids) >= 2) {
            $tid = (int) $ids[1];
            $this->db->table('tb_task_assignees')->insertBatch([
                ['task_id' => $tid, 'user_id' => $nabila, 'assigned_by' => $adminId, 'assigned_at' => $now],
                ['task_id' => $tid, 'user_id' => $dimas, 'assigned_by' => $adminId, 'assigned_at' => $now],
            ]);
            echo "  [ok] Assignee demo pada 1 task.\n";
        }

        // Revisi
        if ($this->db->tableExists('tb_revisions') && $this->db->table('tb_revisions')->countAllResults() === 0 && $ids !== []) {
            $this->db->table('tb_revisions')->insertBatch([
                [
                    'task_id'      => (int) $ids[0],
                    'requested_by' => 'Klien — PT Annora Digital',
                    'description'  => 'Mohon teks hero diganti ke versi final dari email 15 Mar.',
                    'requested_at' => date('Y-m-d', strtotime('-5 days')),
                    'due_date'     => date('Y-m-d', strtotime('+3 days')),
                    'handled_by'   => $nabila,
                    'status'       => 'in_progress',
                    'handler_note' => 'Sedang dikerjakan, draft sore ini.',
                    'resolved_at'  => null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                [
                    'task_id'      => (int) $ids[min(2, count($ids) - 1)],
                    'requested_by' => 'Internal QA',
                    'description'  => 'Periksa margin bleed 3 mm untuk cetak.',
                    'requested_at' => date('Y-m-d', strtotime('-2 days')),
                    'due_date'     => null,
                    'handled_by'   => null,
                    'status'       => 'pending',
                    'handler_note' => null,
                    'resolved_at'  => null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
            ]);
            echo "  [ok] 2 permintaan revisi.\n";
        }

        // Template task
        if ($this->db->tableExists('tb_task_templates') && $this->db->table('tb_task_templates')->countAllResults() === 0) {
            $tplValues = [
                'theme'    => 'Flyer Promo Musiman (dari template)',
                'artboard' => 'Flyer',
                'account'  => 'Annora',
            ];
            $this->db->table('tb_task_templates')->insertBatch([
                [
                    'name'         => 'Template — Flyer promo',
                    'description'  => 'Judul/theme dan artboard siap isi.',
                    'created_by'   => $adminId,
                    'field_values' => json_encode($tplValues),
                    'is_public'    => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                [
                    'name'         => 'Template — Deck presentasi',
                    'description'  => 'Untuk pitch & laporan.',
                    'created_by'   => $managerId,
                    'field_values' => json_encode([
                        'theme'    => 'Presentasi klien (template)',
                        'artboard' => 'Presentation',
                        'account'  => 'Client',
                    ]),
                    'is_public'    => 0,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
            ]);
            echo "  [ok] 2 task template.\n";
        }

        // Lampiran: file kecil di writable agar endpoint serve bisa diuji
        if ($this->db->tableExists('tb_attachments') && $this->db->table('tb_attachments')->countAllResults() === 0 && $ids !== []) {
            $tid0 = (int) $ids[0];
            $dir  = WRITEPATH . 'uploads/attachments/' . $tid0 . '/';
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fname = 'demo-brief.txt';
            file_put_contents($dir . $fname, "TaskFlow demo attachment\nTask ID: {$tid0}\n");
            $this->db->table('tb_attachments')->insert([
                'task_id'    => $tid0,
                'user_id'    => $adminId,
                'filename'   => $fname,
                'original'   => 'brief-klien.txt',
                'mime_type'  => 'text/plain',
                'size'       => (int) @filesize($dir . $fname),
                'created_at' => $now,
            ]);
            echo "  [ok] 1 lampiran demo (writable/uploads/attachments/{$tid0}/).\n";
        }

        // Alokasi vendor: bantu uji filter akun jika tb_accounts ada
        if ($this->db->tableExists('tb_vendor_allocations')
            && $this->db->tableExists('tb_accounts')
            && $this->db->fieldExists('account_id', 'tb_vendor_allocations')
            && $this->db->table('tb_vendor_allocations')->countAllResults() === 0) {
            $acc = $this->db->table('tb_accounts')->select('id')->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
            if ($acc && (int) ($acc['id'] ?? 0) > 0) {
                $aid = (int) $acc['id'];
                $this->db->table('tb_vendor_allocations')->insertBatch([
                    ['account_id' => $aid, 'user_id' => $nabila, 'is_primary' => 1, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now],
                    ['account_id' => $aid, 'user_id' => $dimas, 'is_primary' => 0, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now],
                ]);
                echo "  [ok] Alokasi vendor demo untuk nabila & dimas (1 akun).\n";
            }
        }
    }
}
