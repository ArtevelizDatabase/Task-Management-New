<?php
// Run: php app/Database/Seeds/RunSeed.php
$db = new PDO('mysql:host=127.0.0.1;port=8889;dbname=TaskManagementMAC;charset=utf8mb4', 'root', 'root');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$now = date('Y-m-d H:i:s');

$userRows = $db->query('SELECT id, username FROM tb_users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$uMap = array_column($userRows, 'id', 'username');
echo count($userRows) . " users found.\n";

$saId      = $uMap['superadmin']      ?? 1;
$adminId   = $uMap['riani_admin']     ?? 2;
$managerId = $uMap['bagas_manager']   ?? 3;
$nabila    = $uMap['nabila_designer'] ?? 4;
$dimas     = $uMap['dimas_designer']  ?? 5;
$sinta     = $uMap['sinta_member']    ?? 6;
$aldo      = $uMap['aldo_member']     ?? 7;

/* ── Teams ── */
$tc = (int)$db->query('SELECT COUNT(*) FROM tb_teams')->fetchColumn();
if ($tc < 4) {
    $s = $db->prepare('INSERT IGNORE INTO tb_teams (name,slug,description,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?)');
    foreach ([
        ['Design A',    'design-a',    'Tim desainer utama untuk produk Annora & Hayaaro'],
        ['Design B',    'design-b',    'Tim desainer Grachnorine & client'],
        ['Upload Team', 'upload-team', 'Bertanggung jawab upload ke marketplace'],
        ['Management',  'management',  'Manajemen dan admin organisasi'],
    ] as $t) {
        $s->execute([$t[0], $t[1], $t[2], $saId, $now, $now]);
    }
    echo "Teams inserted.\n";
} else { echo "Teams exist ($tc).\n"; }

$tMap = array_column($db->query('SELECT id,slug FROM tb_teams ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id', 'slug');
$tA = $tMap['design-a']    ?? 2;
$tB = $tMap['design-b']    ?? 3;
$tUp = $tMap['upload-team'] ?? 4;
$tMgmt = $tMap['management'] ?? 5;

/* ── Team Members ── */
$mc = (int)$db->query('SELECT COUNT(*) FROM tb_team_members')->fetchColumn();
if ($mc < 5) {
    $s = $db->prepare('INSERT IGNORE INTO tb_team_members (team_id,user_id,joined_at) VALUES (?,?,?)');
    foreach ([
        [$tA, $nabila], [$tA, $dimas],
        [$tB, $sinta],  [$tB, $aldo],
        [$tUp, $managerId], [$tUp, $adminId],
        [$tMgmt, $saId], [$tMgmt, $adminId], [$tMgmt, $managerId],
    ] as $m) {
        $s->execute([$m[0], $m[1], $now]);
    }
    echo "Members assigned.\n";
} else { echo "Members exist ($mc).\n"; }

/* ── Tasks ── */
$tkc = (int)$db->query('SELECT COUNT(*) FROM tb_task WHERE deleted_at IS NULL')->fetchColumn();
echo "Existing tasks: $tkc\n";

if ($tkc < 15) {
    $fMap = array_column($db->query('SELECT id,field_key FROM tb_fields')->fetchAll(PDO::FETCH_ASSOC), 'id', 'field_key');

    $pics = [
        $nabila    => 'Nabila Sari',
        $dimas     => 'Dimas Ardana',
        $sinta     => 'Sinta Maharani',
        $saId      => 'Super Admin',
        $adminId   => 'Riani Putri',
        $managerId => 'Bagas Prasetyo',
    ];

    $tasks = [
        //  uid      status           %    dl_offset   artboard       theme                             account      priority  progressVal    setor
        [$nabila,    'done',        100, '-5 days',  'Flyer',        'Chinese New Year Banner',          'Hayaaro',   'Medium', 'Done',        '1'],
        [$dimas,     'on_progress',  60, '+2 days',  'Social Media', 'Product Launch Social Kit',        'Grachnorine','High',  'In Progress', '0'],
        [$sinta,     'on_progress',  40, '+3 days',  'Presentation', 'Startup Pitch Deck Template',      'Annora',    'Medium', 'In Progress', '0'],
        [$nabila,    'pending',       0, '+5 days',  'Brochure',     'Minimalist Portfolio Cover',       'Hayaaro',   'Low',    'Not Started', '0'],
        [$dimas,     'done',        100, '-8 days',  'Flyer',        'E-Commerce Banner Pack',           'Annora',    'Medium', 'Done',        '1'],
        [$sinta,     'done',        100, '-2 days',  'Logo',         'Corporate Identity Package',       'Grachnorine','High',  'Done',        '0'],
        [$nabila,    'on_progress',  75, '+1 day',   'Brochure',     'Food & Beverage Menu Design',      'Client',    'Medium', 'In Progress', '0'],
        [$dimas,     'pending',       0, '+7 days',  'Brochure',     'Fashion Lookbook 2026',            'Hayaaro',   'Low',    'Not Started', '0'],
        [$saId,      'on_progress',  50, '+4 days',  'Presentation', 'Real Estate Listing Template',     'Annora',    'High',   'In Progress', '0'],
        [$nabila,    'done',        100, '-10 days', 'Flyer',        'Ramadan Promo Bundle',             'Hayaaro',   'Urgent', 'Done',        '1'],
        [$sinta,     'pending',       0, '+6 days',  'Brochure',     'Wedding Invitation Suite',         'Client',    'Medium', 'Not Started', '0'],
        [$dimas,     'on_progress',  30, '+8 days',  'Presentation', 'Tech Startup Pitch',               'Annora',    'High',   'In Progress', '0'],
        [$nabila,    'done',        100, '-6 days',  'Social Media', 'Fitness App UI Kit',               'Grachnorine','Medium','Done',        '0'],
        [$sinta,     'done',        100, '-4 days',  'Brochure',     'Travel Agency Brochure Pack',      'Client',    'Low',    'Done',        '1'],
        [$adminId,   'on_progress',  80, '+2 days',  'Infographic',  'Education Platform Explainer',     'Annora',    'High',   'In Progress', '0'],
        [$nabila,    'pending',       0, '+12 days', 'Logo',         'Sustainable Brand Identity',       'Grachnorine','Low',   'Not Started', '0'],
        [$sinta,     'done',        100, '-1 day',   'Infographic',  'Healthcare Data Dashboard',        'Client',    'Urgent', 'Done',        '1'],
        [$dimas,     'on_progress',  20, '+15 days', 'Brochure',     'Kids Education Worksheet Pack',    'Annora',    'Medium', 'In Progress', '0'],
        [$saId,      'done',        100, '-14 days', 'Presentation', 'Year End Report Presentation',     'Grachnorine','Urgent','Done',        '1'],
        [$nabila,    'on_progress',  90, '+1 day',   'Social Media', 'NFT Art Collection Promo Pack',    'Grachnorine','Medium','In Progress', '0'],
    ];

    $ts = $db->prepare('INSERT INTO tb_task (user_id,status,progress,deadline,created_at,updated_at) VALUES (?,?,?,?,?,?)');
    $vs = $db->prepare('INSERT INTO tb_task_values (task_id,field_id,value,created_at,updated_at) VALUES (?,?,?,?,?)');
    $cnt = 0;

    foreach ($tasks as $i => $t) {
        [$uid, $status, $prog, $dlRel, $artboard, $theme, $account, $priority, $pv, $setor] = $t;
        $ca    = date('Y-m-d H:i:s', strtotime('-' . (20 - $i) . ' days'));
        $dl    = date('Y-m-d', strtotime($dlRel));
        $tDate = date('Y-m-d', strtotime('-' . (20 - $i) . ' days'));
        $pName = $pics[$uid] ?? 'Unknown';

        $ts->execute([$uid, $status, $prog, $dl, $ca, $ca]);
        $tid = (int)$db->lastInsertId();

        foreach ([
            $fMap['date']     ?? 1 => $tDate,
            $fMap['pic_name'] ?? 2 => $pName,
            $fMap['theme']    ?? 3 => $theme,
            $fMap['artboard'] ?? 4 => $artboard,
            $fMap['account']  ?? 5 => $account,
        ] as $fid => $val) {
            $vs->execute([$tid, $fid, $val, $ca, $ca]);
        }
        if (isset($fMap['priority'])) $vs->execute([$tid, $fMap['priority'], $priority, $ca, $ca]);
        if (isset($fMap['progress'])) $vs->execute([$tid, $fMap['progress'], $pv,       $ca, $ca]);
        if (isset($fMap['setor']))    $vs->execute([$tid, $fMap['setor'],    $setor,     $ca, $ca]);
        $cnt++;
    }
    echo "$cnt tasks (EAV) inserted.\n";
} else { echo "Tasks already sufficient.\n"; }

/* ── Login Attempts ── */
$lac = (int)$db->query('SELECT COUNT(*) FROM tb_auth_login_attempts')->fetchColumn();
if ($lac < 10) {
    $cols  = array_column($db->query('SHOW COLUMNS FROM tb_auth_login_attempts')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $hasFR = in_array('failure_reason', $cols);
    $emails = array_column($db->query('SELECT email FROM tb_users')->fetchAll(PDO::FETCH_ASSOC), 'email');
    $ips = ['192.168.1.100', '10.0.0.45', '172.16.0.23', '::1'];
    $ua  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/124.0';

    foreach ($emails as $email) {
        for ($k = 0; $k < 3; $k++) {
            $dt = date('Y-m-d H:i:s', strtotime('-' . rand(10, 1440) . ' minutes'));
            if ($hasFR) {
                $db->prepare('INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,failure_reason,created_at) VALUES (?,?,?,?,?,?)')->execute([$email, $ips[rand(0, 3)], $ua, 1, null, $dt]);
            } else {
                $db->prepare('INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,created_at) VALUES (?,?,?,?,?)')->execute([$email, $ips[rand(0, 3)], $ua, 1, $dt]);
            }
        }
        // 1 failed
        $dt = date('Y-m-d H:i:s', strtotime('-' . rand(60, 2880) . ' minutes'));
        if ($hasFR) {
            $db->prepare('INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,failure_reason,created_at) VALUES (?,?,?,?,?,?)')->execute([$email, $ips[rand(0, 3)], $ua, 0, 'wrong_password', $dt]);
        } else {
            $db->prepare('INSERT INTO tb_auth_login_attempts (identifier,ip_address,user_agent,success,created_at) VALUES (?,?,?,?,?)')->execute([$email, $ips[rand(0, 3)], $ua, 0, $dt]);
        }
    }
    echo "Login attempts inserted.\n";
} else { echo "Login attempts exist ($lac).\n"; }

/* ── Activity Log ── */
$actc = (int)$db->query('SELECT COUNT(*) FROM tb_user_activity')->fetchColumn();
if ($actc < 10) {
    $actCols = array_column($db->query('SHOW COLUMNS FROM tb_user_activity')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $hasD  = in_array('description', $actCols);
    $hasE  = in_array('entity_type', $actCols);

    $acts = [
        [$saId,      'create_user',      'Membuat user: nabila_designer'],
        [$saId,      'create_user',      'Membuat user: dimas_designer'],
        [$saId,      'update_role',      'Update role: Manager'],
        [$adminId,   'login',            'Login berhasil'],
        [$adminId,   'create_task',      'Task baru: Education Platform Explainer'],
        [$managerId, 'login',            'Login berhasil'],
        [$managerId, 'update_task',      'Update progress task ke 60%'],
        [$nabila,    'login',            'Login berhasil'],
        [$nabila,    'update_profile',   'Memperbarui profil'],
        [$nabila,    'change_password',  'Mengubah password'],
        [$nabila,    'update_task',      'Task selesai: Ramadan Promo Bundle'],
        [$dimas,     'login',            'Login berhasil'],
        [$dimas,     'update_task',      'Update progress ke 100%'],
        [$dimas,     'update_profile',   'Memperbarui foto profil'],
        [$sinta,     'login',            'Login berhasil'],
        [$sinta,     'update_task',      'Task selesai: Healthcare Data Dashboard'],
        [$saId,      'impersonate_start', 'Impersonasi: nabila_designer'],
        [$saId,      'impersonate_stop',  'Stop impersonasi: nabila_designer'],
    ];

    $total = count($acts);
    foreach ($acts as $k => [$uid, $action, $desc]) {
        $dt = date('Y-m-d H:i:s', strtotime('-' . ($total - $k) * 10 . ' minutes'));
        $ip = ['::1', '192.168.1.100', '10.0.0.45'][rand(0, 2)];
        if ($hasD && $hasE) {
            $db->prepare('INSERT INTO tb_user_activity (user_id,action,description,entity_type,entity_id,ip_address,created_at) VALUES (?,?,?,?,?,?,?)')->execute([$uid, $action, $desc, '', null, $ip, $dt]);
        } elseif ($hasD) {
            $db->prepare('INSERT INTO tb_user_activity (user_id,action,description,ip_address,created_at) VALUES (?,?,?,?,?)')->execute([$uid, $action, $desc, $ip, $dt]);
        } else {
            $db->prepare('INSERT INTO tb_user_activity (user_id,action,ip_address,created_at) VALUES (?,?,?,?)')->execute([$uid, $action, $ip, $dt]);
        }
    }
    echo count($acts) . " activity records inserted.\n";
} else { echo "Activity exist ($actc).\n"; }

echo "\nALL DONE! Summary:\n";
echo "  Users: " . $db->query('SELECT COUNT(*) FROM tb_users')->fetchColumn() . "\n";
echo "  Teams: " . $db->query('SELECT COUNT(*) FROM tb_teams')->fetchColumn() . "\n";
echo "  Members: " . $db->query('SELECT COUNT(*) FROM tb_team_members')->fetchColumn() . "\n";
echo "  Tasks: " . $db->query('SELECT COUNT(*) FROM tb_task WHERE deleted_at IS NULL')->fetchColumn() . "\n";
echo "  Notifications: " . $db->query('SELECT COUNT(*) FROM tb_notifications')->fetchColumn() . "\n";
echo "  Login attempts: " . $db->query('SELECT COUNT(*) FROM tb_auth_login_attempts')->fetchColumn() . "\n";
echo "  Activity: " . $db->query('SELECT COUNT(*) FROM tb_user_activity')->fetchColumn() . "\n";
