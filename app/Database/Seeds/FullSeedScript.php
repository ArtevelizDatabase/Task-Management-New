<?php
/**
 * Full seed script — run via: php app/Database/Seeds/FullSeedScript.php
 * Handles: teams, tasks (EAV), login_attempts, user_activity
 */

define('FCPATH', __DIR__ . '/../../../../public/');
chdir(__DIR__ . '/../../../..');
require 'vendor/autoload.php';

// Bootstrap CI4 manually
$app = require 'app/Config/Boot/development.php';

// Use pure PDO for simplicity
$db = new PDO('mysql:host=127.0.0.1;port=8889;dbname=TaskManagementMAC;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$now = date('Y-m-d H:i:s');

// Get user IDs
$userRows = $db->query("SELECT id, username FROM tb_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$uMap = array_column($userRows, 'id', 'username');
$allIds = array_values($uMap);
echo count($userRows) . " users found.\n";

$saId      = $uMap['superadmin']       ?? 1;
$adminId   = $uMap['riani_admin']      ?? 2;
$managerId = $uMap['bagas_manager']    ?? 3;
$nabila    = $uMap['nabila_designer']  ?? 4;
$dimas     = $uMap['dimas_designer']   ?? 5;
$sinta     = $uMap['sinta_member']     ?? 6;
$aldo      = $uMap['aldo_member']      ?? 7;

// ── TEAMS ──────────────────────────────────────────────────────────────────
$teamCount = (int)$db->query("SELECT COUNT(*) FROM tb_teams")->fetchColumn();
if ($teamCount < 4) {
    $stmt = $db->prepare("INSERT IGNORE INTO tb_teams (name, slug, description, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)");
    $teamsData = [
        ['Design A',    'design-a',    'Tim desainer utama untuk produk Annora & Hayaaro',      $saId],
        ['Design B',    'design-b',    'Tim desainer untuk produk Grachnorine & client khusus', $saId],
        ['Upload Team', 'upload-team', 'Bertanggung jawab atas upload ke marketplace',           $saId],
        ['Management',  'management',  'Manajemen dan admin organisasi',                        $saId],
    ];
    foreach ($teamsData as $t) {
        $stmt->execute([$t[0], $t[1], $t[2], $t[3], $now, $now]);
    }
    echo "Teams inserted.\n";
}

// Get team IDs
$teamRows = $db->query("SELECT id, slug FROM tb_teams ORDER BY id LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
$tMap = array_column($teamRows, 'id', 'slug');
$tA    = $tMap['design-a']    ?? 1;
$tB    = $tMap['design-b']    ?? 2;
$tUp   = $tMap['upload-team'] ?? 3;
$tMgmt = $tMap['management']  ?? 4;

// ── TEAM MEMBERS ──────────────────────────────────────────────────────────
$memberCount = (int)$db->query("SELECT COUNT(*) FROM tb_team_members")->fetchColumn();
if ($memberCount < 5) {
    $stmt = $db->prepare("INSERT IGNORE INTO tb_team_members (team_id, user_id, joined_at) VALUES (?,?,?)");
    $membersData = [
        [$tA, $nabila, $now], [$tA, $dimas, $now],
        [$tB, $sinta,  $now], [$tB, $aldo,  $now],
        [$tUp, $managerId, $now], [$tUp, $adminId, $now],
        [$tMgmt, $saId, $now], [$tMgmt, $adminId, $now], [$tMgmt, $managerId, $now],
    ];
    foreach ($membersData as $m) {
        $stmt->execute($m);
    }
    echo "Team members assigned.\n";
}

// ── TASKS (EAV) ────────────────────────────────────────────────────────────
$taskCount = (int)$db->query("SELECT COUNT(*) FROM tb_task WHERE deleted_at IS NULL")->fetchColumn();
echo "Existing tasks: $taskCount\n";

if ($taskCount < 15) {
    // Get field IDs
    $fieldRows = $db->query("SELECT id, field_key FROM tb_fields")->fetchAll(PDO::FETCH_ASSOC);
    $fMap = array_column($fieldRows, 'id', 'field_key');

    $taskDefs = [
        [$nabila, 'done',        100, '-5 days',  '2025-03-20', 'Nabila Sari',      'Chinese New Year Banner 2026',        'Flyer',        'Hayaaro',     'Medium', 'Done',         '1'],
        [$dimas,  'on_progress',  60, '+2 days',  date('Y-m-d', strtotime('-3 days')), 'Dimas Ardana', 'Product Launch Social Kit', 'Social Media', 'Grachnorine', 'High',   'In Progress',  '0'],
        [$sinta,  'on_progress',  40, '+3 days',  date('Y-m-d', strtotime('-4 days')), 'Sinta Maharani', 'Startup Pitch Deck Template', 'Presentation', 'Annora', 'Medium', 'In Progress',  '0'],
        [$nabila, 'pending',       0, '+5 days',  date('Y-m-d', strtotime('-6 days')), 'Nabila Sari',   'Minimalist Portfolio Cover', 'Brochure', 'Hayaaro',     'Low',    'Not Started',  '0'],
        [$dimas,  'done',         100, '-8 days', date('Y-m-d', strtotime('-9 days')), 'Dimas Ardana',  'E-Commerce Banner Pack',     'Flyer',    'Annora',      'Medium', 'Done',         '1'],
        [$sinta,  'done',         100, '-2 days', date('Y-m-d', strtotime('-3 days')), 'Sinta Maharani', 'Corporate Identity Package', 'Logo',    'Grachnorine', 'High',   'Done',         '0'],
        [$nabila, 'on_progress',   75, '+1 day',  date('Y-m-d', strtotime('-1 day')),  'Nabila Sari',   'Food & Beverage Menu Design', 'Brochure', 'Client',     'Medium', 'In Progress',  '0'],
        [$dimas,  'pending',        0, '+7 days', date('Y-m-d', strtotime('-2 days')), 'Dimas Ardana',  'Fashion Lookbook 2026',      'Brochure',  'Hayaaro',    'Low',    'Not Started',  '0'],
        [$saId,   'on_progress',   50, '+4 days', date('Y-m-d', strtotime('-5 days')), 'Super Admin',   'Real Estate Listing Template', 'Presentation', 'Annora', 'High',  'In Progress',  '0'],
        [$nabila, 'done',         100, '-10 days',date('Y-m-d', strtotime('-11 days')),'Nabila Sari',   'Ramadan Promo Bundle',       'Flyer',     'Hayaaro',    'Urgent', 'Done',         '1'],
        [$sinta,  'pending',        0, '+6 days', date('Y-m-d', strtotime('-7 days')), 'Sinta Maharani', 'Wedding Invitation Suite',  'Brochure',  'Client',     'Medium', 'Not Started',  '0'],
        [$dimas,  'on_progress',   30, '+8 days', date('Y-m-d', strtotime('-2 days')), 'Dimas Ardana',  'Tech Startup Pitch Presentation', 'Presentation', 'Annora', 'High', 'In Progress', '0'],
        [$nabila, 'done',         100, '-6 days', date('Y-m-d', strtotime('-7 days')), 'Nabila Sari',   'Fitness App UI Kit',         'Social Media', 'Grachnorine', 'Medium', 'Done',      '0'],
        [$sinta,  'done',         100, '-4 days', date('Y-m-d', strtotime('-5 days')), 'Sinta Maharani', 'Travel Agency Brochure Pack', 'Brochure', 'Client',    'Low',    'Done',         '1'],
        [$adminId,'on_progress',   80, '+2 days', date('Y-m-d', strtotime('-1 day')),  'Riani Putri',   'Education Platform Explainer', 'Infographic', 'Annora',  'High',  'In Progress',  '0'],
        [$nabila, 'pending',        0, '+12 days',date('Y-m-d', strtotime('-8 days')), 'Nabila Sari',   'Sustainable Brand Identity', 'Logo',      'Grachnorine', 'Low',  'Not Started',  '0'],
        [$sinta,  'done',         100, '-1 day',  date('Y-m-d', strtotime('-2 days')), 'Sinta Maharani', 'Healthcare Data Dashboard', 'Infographic', 'Client',   'Urgent', 'Done',        '1'],
        [$dimas,  'on_progress',   20, '+15 days',date('Y-m-d', strtotime('-3 days')), 'Dimas Ardana',  'Kids Education Worksheet Pack', 'Brochure', 'Annora',   'Medium', 'In Progress', '0'],
        [$saId,   'done',         100, '-14 days',date('Y-m-d', strtotime('-15 days')),'Super Admin',   'Year End Report Presentation', 'Presentation', 'Grachnorine', 'Urgent', 'Done',  '1'],
        [$nabila, 'on_progress',   90, '+1 day',  date('Y-m-d'),                       'Nabila Sari',   'NFT Art Collection Promo Pack', 'Social Media', 'Grachnorine', 'Medium', 'In Progress', '0'],
    ];

    $taskStmt = $db->prepare("INSERT INTO tb_task (user_id, status, progress, deadline, created_at, updated_at) VALUES (?,?,?,?,?,?)");
    $valStmt  = $db->prepare("INSERT INTO tb_task_values (task_id, field_id, value, created_at, updated_at) VALUES (?,?,?,?,?)");

    $count = 0;
    foreach ($taskDefs as $i => $t) {
        [$uid, $status, $progress, $dlRel, $tDate, $picName, $theme, $artboard, $account, $priority, $progressVal, $setor] = $t;
        $createdAt    = date('Y-m-d H:i:s', strtotime("-" . (20 - $i) . " days"));
        $deadlineDate = date('Y-m-d', strtotime($dlRel));

        $taskStmt->execute([$uid, $status, $progress, $deadlineDate, $createdAt, $createdAt]);
        $taskId = $db->lastInsertId();

        $fvData = [
            [$fMap['date']     ?? 1,  $tDate],
            [$fMap['pic_name'] ?? 2,  $picName],
            [$fMap['theme']    ?? 3,  $theme],
            [$fMap['artboard'] ?? 4,  $artboard],
            [$fMap['account']  ?? 5,  $account],
        ];
        if (isset($fMap['priority'])) $fvData[] = [$fMap['priority'], $priority];
        if (isset($fMap['progress'])) $fvData[] = [$fMap['progress'], $progressVal];
        if (isset($fMap['setor']))    $fvData[] = [$fMap['setor'],    $setor];

        foreach ($fvData as $fv) {
            $valStmt->execute([$taskId, $fv[0], $fv[1], $createdAt, $createdAt]);
        }
        $count++;
    }
    echo "$count tasks (EAV) inserted.\n";
}

// ── LOGIN ATTEMPTS ─────────────────────────────────────────────────────────
$laCount = (int)$db->query("SELECT COUNT(*) FROM tb_auth_login_attempts")->fetchColumn();
if ($laCount < 10) {
    $cols = array_column(
        $db->query("SHOW COLUMNS FROM tb_auth_login_attempts")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    $hasFailureReason = in_array('failure_reason', $cols);

    $emails = array_column(
        $db->query("SELECT email FROM tb_users")->fetchAll(PDO::FETCH_ASSOC), 'email'
    );
    $ips = ['192.168.1.100','10.0.0.45','172.16.0.23','::1','203.0.113.42'];
    $ua  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0';

    foreach ($emails as $email) {
        for ($i = 0; $i < 3; $i++) {
            $minsAgo = rand(10, 1440);
            $dt = date('Y-m-d H:i:s', strtotime("-{$minsAgo} minutes"));
            if ($hasFailureReason) {
                $db->prepare("INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,failure_reason,created_at) VALUES (?,?,?,?,?,?)")
                   ->execute([$email, $ips[array_rand($ips)], $ua, 1, null, $dt]);
            } else {
                $db->prepare("INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,created_at) VALUES (?,?,?,?,?)")
                   ->execute([$email, $ips[array_rand($ips)], $ua, 1, $dt]);
            }
        }
        // 1 failed attempt
        $minsAgo = rand(60, 2880);
        $dt = date('Y-m-d H:i:s', strtotime("-{$minsAgo} minutes"));
        if ($hasFailureReason) {
            $db->prepare("INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,failure_reason,created_at) VALUES (?,?,?,?,?,?)")
               ->execute([$email, $ips[array_rand($ips)], $ua, 0, 'wrong_password', $dt]);
        } else {
            $db->prepare("INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,created_at) VALUES (?,?,?,?,?)")
               ->execute([$email, $ips[array_rand($ips)], $ua, 0, $dt]);
        }
    }
    echo "Login attempts inserted.\n";
}

// ── USER ACTIVITY ──────────────────────────────────────────────────────────
$actCount = (int)$db->query("SELECT COUNT(*) FROM tb_user_activity")->fetchColumn();
if ($actCount < 10) {
    $actCols = array_column(
        $db->query("SHOW COLUMNS FROM tb_user_activity")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    $hasDesc = in_array('description', $actCols);
    $hasEntity = in_array('entity_type', $actCols);

    $activities = [
        [$saId,      'create_user',      'Membuat user baru: nabila_designer'],
        [$saId,      'create_user',      'Membuat user baru: dimas_designer'],
        [$saId,      'update_role',      'Memperbarui role: Manager'],
        [$adminId,   'login',            'Login berhasil'],
        [$adminId,   'update_user',      'Memperbarui profil user: nabila_designer'],
        [$adminId,   'create_task',      'Membuat task: Education Platform Explainer'],
        [$managerId, 'login',            'Login berhasil'],
        [$managerId, 'update_task',      'Update progress task ke 60%'],
        [$nabila,    'login',            'Login berhasil'],
        [$nabila,    'update_profile',   'Memperbarui profil'],
        [$nabila,    'change_password',  'Mengubah password'],
        [$nabila,    'update_task',      'Update status task ke Done'],
        [$dimas,     'login',            'Login berhasil'],
        [$dimas,     'update_task',      'Update progress task ke 100%'],
        [$dimas,     'update_profile',   'Memperbarui foto profil'],
        [$saId,      'impersonate_start','Mulai impersonation: nabila_designer'],
        [$saId,      'impersonate_stop', 'Berhenti impersonation: nabila_designer'],
    ];

    foreach ($activities as $i => $a) {
        [$uid, $action, $desc] = $a;
        $dt = date('Y-m-d H:i:s', strtotime("-" . (count($activities) - $i) * 15 . " minutes"));
        $ip = ['::1','192.168.1.100','10.0.0.45'][rand(0,2)];

        if ($hasDesc && $hasEntity) {
            $db->prepare("INSERT INTO tb_user_activity (user_id,action,description,entity_type,entity_id,ip_address,created_at) VALUES (?,?,?,?,?,?,?)")
               ->execute([$uid, $action, $desc, '', null, $ip, $dt]);
        } elseif ($hasDesc) {
            $db->prepare("INSERT INTO tb_user_activity (user_id,action,description,ip_address,created_at) VALUES (?,?,?,?,?)")
               ->execute([$uid, $action, $desc, $ip, $dt]);
        } else {
            $db->prepare("INSERT INTO tb_user_activity (user_id,action,ip_address,created_at) VALUES (?,?,?,?)")
               ->execute([$uid, $action, $ip, $dt]);
        }
    }
    echo "User activity inserted.\n";
}

echo "\nAll done!\n";
