<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title ?? 'Task Manager') ?> — TaskFlow</title>
  <?php
    // Auth helpers available in layout
    $sessionUserId   = session()->get('user_id');
    $sessionUserName = session()->get('user_name') ?? 'User';
    $sessionUserRole = session()->get('user_role') ?? 'member';
    $sessionUserPerms = session()->get('user_perms') ?? [];
    $can = static function (string $perm) use ($sessionUserRole, $sessionUserPerms): bool {
        return $sessionUserRole === 'super_admin' || in_array($perm, (array) $sessionUserPerms, true);
    };
    $isImpersonating = (bool) session()->get('is_impersonating');
    $impersonateeName = $isImpersonating ? $sessionUserName : null;
    $impersonateeId   = $isImpersonating ? (int)$sessionUserId : null;
    // Load unread count for bell
    $notifUnreadCount = 0;
    if ($sessionUserId) {
        $notifModel = new \App\Models\NotificationModel();
        $notifUnreadCount = $notifModel->getUnreadCount((int)$sessionUserId);
    }
    // Load current user avatar
    $currentUserAvatar = '';
    if ($sessionUserId) {
        $db = \Config\Database::connect();
        $u  = $db->table('tb_users')->select('avatar,nickname,username')->where('id', $sessionUserId)->get()->getRowArray();
        $currentUserAvatar = \App\Models\UserModel::avatarUrl($u['avatar'] ?? null, $u['nickname'] ?? $u['username'] ?? 'U');
    }
  ?>
  <script>
    (function () {
      // ── Theme ──
      try {
        const saved = localStorage.getItem('taskflow-theme');
        const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = saved || (systemDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
        // #region agent log
        // logger may not exist yet in <head>; skip if undefined
        try { window.__dbgSend?.({sessionId:'472502',runId:'pre',hypothesisId:'H3',location:'app/Views/layouts/main.php:headThemeIIFE',message:'head theme applied',data:{saved:!!saved,systemDark:!!systemDark,themeApplied:theme},timestamp:Date.now()}); } catch (e) {}
        // #endregion
        <?php endif; ?>
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
      // ── Sidebar collapse pre-apply (prevents open→close flash on navigation) ──
      // Must run in <head> before body is painted so the sidebar is already narrow
      // on first paint. Class is transferred to <body> later by the collapse IIFE.
      try {
        const isMobile = window.matchMedia('(max-width: 1023px)').matches;
        if (!isMobile && localStorage.getItem('taskflow-sidebar-collapsed') === '1') {
          document.documentElement.classList.add('sidebar-pre-collapsed');
        }
      } catch (e) {}
    })();
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/themes/theme-light.css" />
  <link rel="stylesheet" href="/assets/css/themes/theme-dark.css" />
  <link rel="stylesheet" href="/assets/css/themes/theme-brand.css" />
  <link rel="stylesheet" href="/assets/css/base/app-base.css" />
  <link rel="stylesheet" href="/assets/css/components/richtext-modal.css" />
  <?php if (!empty($sessionUserId)): ?>
  <link rel="stylesheet" href="/assets/css/components/global-search.css" />
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
</head>
<body>

<!-- SIDEBAR -->
<?php
  $curUrl        = current_url();
  $uriPath       = trim((string) (service('uri')->getPath() ?? ''), '/');
  $isDashboard   = ($uriPath === '' || $uriPath === 'dashboard');
  $isTrash       = strpos($curUrl, '/tasks/trash')        !== false;
  $isSub         = strpos($curUrl, '/tasks/submissions')  !== false;
  $isFields      = strpos($curUrl, '/fields')             !== false;
  $isUploadCfg   = strpos($curUrl, '/settings/upload-config') !== false;
  $isTask        = ($uriPath === 'tasks' || preg_match('#^tasks/\d#', $uriPath) === 1) && !$isTrash && !$isSub;
  $isTeamUsers   = strpos($curUrl, '/team/users')         !== false;
  $isTeamTeams   = strpos($curUrl, '/team/teams')         !== false;
  $isTeamRoles   = strpos($curUrl, '/team/roles')         !== false;
  $isNotifs      = strpos($curUrl, '/notifications')      !== false;
  $isProfile     = strpos($curUrl, '/profile')            !== false;
  $isVendors     = strpos($curUrl, '/vendors')            !== false;
  $isMonitoring  = strpos($curUrl, '/projects/monitoring') !== false;
  $isClientsPage = ($uriPath === 'clients' || preg_match('#^clients/\d+$#', $uriPath));
  $isProjectsCrud = ($uriPath === 'projects' || preg_match('#^projects/\d+(/tasks/\d+)?$#', $uriPath));
?>
<div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>
<aside class="sidebar" id="appSidebar">

  <!-- Logo + collapse toggle -->
  <div class="sidebar-logo">
    <a href="/" class="sidebar-brand" title="TaskFlow">
      <div class="sidebar-brand-icon">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
      </div>
      <span class="sidebar-brand-name">TaskFlow</span>
    </a>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Collapse sidebar" aria-label="Toggle sidebar">
      <i class="fa-solid fa-chevron-left" id="sidebarCollapseIcon" aria-hidden="true"></i>
    </button>
  </div>

  <!-- Nav -->
  <nav class="sidebar-nav">
    <div class="nav-label sidebar-label-text">Menu</div>
    <ul>
      <?php if ($can('view_tasks')): ?>
      <li class="nav-item <?= $isDashboard ? 'active' : '' ?>">
        <a href="/" title="Dashboard">
          <i class="fa-solid fa-gauge-high nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Dashboard</span>
        </a>
      </li>
      <li class="nav-item <?= $isTask ? 'active' : '' ?>">
        <a href="/tasks" title="Tasks">
          <i class="fa-solid fa-table-cells nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Task internal</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_projects') || $can('view_tasks')): ?>
      <li class="nav-item <?= $isProjectsCrud ? 'active' : '' ?>">
        <a href="/projects" title="Projects">
          <i class="fa-solid fa-folder-tree nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Projects</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_clients')): ?>
      <li class="nav-item <?= $isClientsPage ? 'active' : '' ?>">
        <a href="/clients" title="Klien">
          <i class="fa-solid fa-building-user nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Klien</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_submissions')): ?>
      <li class="nav-item <?= $isSub ? 'active' : '' ?>">
        <a href="/tasks/submissions" title="Daftar Setor">
          <i class="fa-solid fa-circle-check nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Daftar Setor</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_vendor_accounts')): ?>
      <li class="nav-item <?= ($uriPath === 'accounts') ? 'active' : '' ?>">
        <a href="/accounts" title="Accounts Master">
          <i class="fa-solid fa-address-book nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Accounts</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_project_monitoring')): ?>
      <li class="nav-item <?= $isMonitoring ? 'active' : '' ?>">
        <a href="/projects/monitoring" title="Project Monitoring">
          <i class="fa-solid fa-chart-line nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Monitoring</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($can('view_tasks') && $sessionUserId): ?>
      <li class="nav-item">
        <button type="button" class="sidebar-search-btn" onclick="openGlobalSearch()" title="Cari (Ctrl+K)">
          <i class="fa-solid fa-magnifying-glass nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Cari…</span>
        </button>
      </li>
      <?php endif; ?>
      <?php if ($can('manage_tasks')): ?>
      <li class="nav-item nav-item-danger <?= $isTrash ? 'active' : '' ?>">
        <a href="/tasks/trash" title="Trash">
          <i class="fa-solid fa-trash nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Trash</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <?php if (in_array($sessionUserRole, ['super_admin','admin','manager'])): ?>
    <div class="nav-label sidebar-label-text">Team</div>
    <ul>
      <li class="nav-item <?= $isTeamUsers ? 'active' : '' ?>">
        <a href="/team/users" title="User Management">
          <i class="fa-solid fa-users nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Users</span>
        </a>
      </li>
      <li class="nav-item <?= $isTeamTeams ? 'active' : '' ?>">
        <a href="/team/teams" title="Team Management">
          <i class="fa-solid fa-users-rectangle nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Teams</span>
        </a>
      </li>
      <?php if (in_array($sessionUserRole, ['super_admin', 'admin'])): ?>
      <li class="nav-item <?= $isTeamRoles ? 'active' : '' ?>">
        <a href="/team/roles" title="Role Configuration">
          <i class="fa-solid fa-shield-halved nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Roles</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <div class="nav-label settings-nav-label sidebar-label-text">Settings</div>
    <ul>
      <li class="nav-item <?= $isFields ? 'active' : '' ?>">
        <a href="/fields" title="Field Manager">
          <i class="fa-solid fa-sliders nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Field Manager</span>
        </a>
      </li>
      <?php if ($can('manage_fields')): ?>
      <li class="nav-item <?= $isUploadCfg ? 'active' : '' ?>">
        <a href="/settings/upload-config" title="Upload status — grup &amp; platform">
          <i class="fa-solid fa-table-cells-large nav-icon" aria-hidden="true"></i>
          <span class="nav-text">Upload matrix</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>

  <!-- User section -->
  <div class="sidebar-user" id="sidebarUserWrap">
    <button class="sidebar-user-btn" id="sidebarUserBtn" onclick="toggleSidebarUserMenu()" title="<?= esc($sessionUserName) ?>">
      <img src="<?= $currentUserAvatar ?>" class="sidebar-user-avatar" alt="" />
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?= esc($sessionUserName) ?></span>
        <span class="sidebar-user-role"><?= esc(\App\Models\UserModel::$roleLabels[$sessionUserRole] ?? $sessionUserRole) ?></span>
      </div>
      <i class="fa-solid fa-ellipsis sidebar-user-more" aria-hidden="true"></i>
    </button>
    <div class="sidebar-user-dropdown" id="sidebarUserDropdown">
      <a href="/profile" class="dropdown-item">
        <i class="fa-solid fa-user icon-xs"></i> Profil Saya
      </a>
      <a href="/notifications" class="dropdown-item">
        <i class="fa-solid fa-bell icon-xs"></i> Notifikasi
        <?php if ($notifUnreadCount > 0): ?>
          <span class="badge badge-accent"><?= $notifUnreadCount ?></span>
        <?php endif; ?>
      </a>
      <div class="dropdown-divider"></div>
      <a href="/auth/logout" class="dropdown-item dropdown-item-danger">
        <i class="fa-solid fa-right-from-bracket icon-xs"></i> Logout
      </a>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">

  <?php if ($isImpersonating): ?>
  <!-- Impersonation Banner -->
  <div class="impersonation-banner" id="impersonationBanner">
    <div class="impersonation-banner-inner">
      <i class="fa-solid fa-user-secret impersonation-icon"></i>
      <span>Anda sedang melihat sebagai <strong><?= esc($impersonateeName) ?></strong></span>
    </div>
    <a href="/auth/stop-impersonation" class="btn btn-sm impersonation-stop-btn">
      <i class="fa-solid fa-arrow-right-to-bracket icon-xs"></i>
      Stop Impersonation
    </a>
  </div>
  <?php endif; ?>

  <header class="topbar">
    <div class="topbar-left">
      <button type="button" class="btn btn-ghost btn-sm mobile-menu-btn" id="mobileMenuBtn" aria-label="Buka menu">
        <i data-lucide="menu"></i>
      </button>
    </div>
    <div class="topbar-actions">
      <?php if (strpos(current_url(), '/fields') !== false): ?>
        <button class="btn btn-primary" onclick="openModal('add-field-modal')">
          <i class="fa-solid fa-plus icon-xs" aria-hidden="true"></i>
          Add Field
        </button>
      <?php endif; ?>

      <!-- Notification Bell -->
      <div class="topbar-notif-wrap" id="notifDropdownWrap">
        <button class="btn btn-ghost btn-sm topbar-notif-btn" id="notifBellBtn"
                onclick="toggleNotifDropdown()" title="Notifikasi" aria-label="Notifikasi">
          <i class="fa-solid fa-bell" aria-hidden="true"></i>
          <?php if ($notifUnreadCount > 0): ?>
          <span class="notif-badge" id="notifBadge"><?= $notifUnreadCount > 99 ? '99+' : $notifUnreadCount ?></span>
          <?php else: ?>
          <span class="notif-badge" id="notifBadge" style="display:none">0</span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-dd-head">
            <span>Notifikasi</span>
            <a href="/notifications" class="btn btn-ghost btn-xs">Lihat Semua</a>
          </div>
          <div class="notif-dd-list" id="notifDdList">
            <div class="notif-dd-loading">
              <i class="fa-solid fa-spinner fa-spin"></i> Memuat...
            </div>
          </div>
          <div class="notif-dd-foot">
            <button class="btn btn-ghost btn-xs w-full" onclick="markAllReadDd()">
              <i class="fa-solid fa-check-double icon-xs"></i> Tandai Semua Dibaca
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="content">
    <?php if (session()->getFlashdata('success')): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check icon-sm" aria-hidden="true"></i>
        <?= session()->getFlashdata('success') ?>
      </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation icon-sm" aria-hidden="true"></i>
        <?= session()->getFlashdata('error') ?>
      </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </main>
</div>

<div id="toast-container"></div>
<div id="confirmOverlay" class="confirm-overlay" aria-hidden="true">
  <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
    <div class="confirm-head" id="confirmHead">Konfirmasi</div>
    <div class="confirm-body">
      <div class="confirm-title" id="confirmTitle">Lanjutkan aksi?</div>
      <div class="confirm-message" id="confirmMessage">Perubahan ini akan diproses sekarang.</div>
    </div>
    <div class="confirm-actions">
      <button type="button" class="btn btn-ghost btn-sm" id="confirmCancelBtn">Cancel</button>
      <button type="button" class="btn btn-primary btn-sm" id="confirmOkBtn">OK</button>
    </div>
  </div>
</div>
<div class="theme-fab-wrap">
  <button type="button" class="theme-fab" id="themeToggleBtn" title="Toggle theme">
    <i data-lucide="moon" id="themeToggleIcon"></i>
    <span class="theme-fab-label">Theme: <span id="themeLabel">Light</span></span>
  </button>
</div>

<script>
window.appCsrf = {
  key: '<?= csrf_token() ?>',
  val: '<?= csrf_hash() ?>',
};
function getAppCsrf() {
  return window.appCsrf || { key: '<?= csrf_token() ?>', val: '<?= csrf_hash() ?>' };
}
function updateAppCsrf(nextVal) {
  if (!nextVal) return;
  if (!window.appCsrf) window.appCsrf = { key: '<?= csrf_token() ?>', val: nextVal };
  window.appCsrf.val = nextVal;
}
function refreshLucide(root = document) {
  const iconMap = {
    'sparkles': 'fa-sparkles',
    'menu': 'fa-bars',
    'moon': 'fa-moon',
    'sun': 'fa-sun',
    'square-pen': 'fa-pen-to-square',
    'check': 'fa-check',
    'file-text': 'fa-file-lines',
    'inbox': 'fa-inbox',
    'pencil': 'fa-pen',
    'trash-2': 'fa-trash',
    'type': 'fa-font',
    'calendar-days': 'fa-calendar-days',
    'list': 'fa-list',
    'check-square': 'fa-square-check',
    'align-left': 'fa-align-left',
    'hash': 'fa-hashtag',
    'at-sign': 'fa-at',
    'refresh-cw': 'fa-rotate',
    'folders': 'fa-folder-tree',
    'clipboard-list': 'fa-clipboard-list',
    'minus': 'fa-minus',
    'x': 'fa-xmark',
    'circle': 'fa-circle',
    'rotate-ccw': 'fa-rotate-left'
  };

  root.querySelectorAll('[data-lucide]').forEach((el) => {
    const name = (el.getAttribute('data-lucide') || '').trim();
    const faName = iconMap[name] || 'fa-circle-question';
    const keepClasses = (el.className || '')
      .split(/\s+/)
      .filter(Boolean)
      .filter((c) => !c.startsWith('fa-'))
      .join(' ');
    el.className = `fa-solid ${faName}${keepClasses ? ` ${keepClasses}` : ''}`;
    el.removeAttribute('data-lucide');
    el.setAttribute('aria-hidden', 'true');
  });
}

// #region agent log
const __DBG_URL = 'http://127.0.0.1:7243/ingest/42633582-f9f3-40ca-b9df-00ad13692ab9';
function __dbgSend(payload) {
  try {
    fetch(__DBG_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '472502' },
      body: JSON.stringify(payload),
    }).catch(() => {});
  } catch (e) {}
}
window.__dbgSend = __dbgSend;

// #region agent log
try {
  window.__themeDbgInitCount = (window.__themeDbgInitCount || 0) + 1;
  __dbgSend({
    sessionId: '472502',
    runId: 'pre',
    hypothesisId: 'H3',
    location: 'app/Views/layouts/main.php:script:loaded',
    message: 'theme script loaded',
    data: {
      initCount: window.__themeDbgInitCount,
      readyState: document.readyState,
      themeAttr: document.documentElement.getAttribute('data-theme') || '',
    },
    timestamp: Date.now(),
  });
} catch (e) {}
// #endregion
// #endregion

function getCurrentTheme() {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function setTheme(theme) {
  // #region agent log
  const __dbg_t0 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
  __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H2',location:'app/Views/layouts/main.php:setTheme:enter',message:'setTheme enter',data:{requested:String(theme||''),current:document.documentElement.getAttribute('data-theme')||''},timestamp:Date.now()});
  // #endregion
  const next = theme === 'dark' ? 'dark' : 'light';
  const __dbg_p0 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
  // #region agent log
  try {
    document.documentElement.classList.add('theme-switching');
    __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H2',location:'app/Views/layouts/main.php:setTheme:switchingOn',message:'theme-switching ON',data:{next},timestamp:Date.now()});
  } catch (e) {}
  // #endregion
  document.documentElement.setAttribute('data-theme', next);
  try { localStorage.setItem('taskflow-theme', next); } catch (e) {}
  const icon = document.getElementById('themeToggleIcon');
  const label = document.getElementById('themeLabel');
  if (icon) icon.setAttribute('data-lucide', next === 'dark' ? 'sun' : 'moon');
  if (label) label.textContent = next === 'dark' ? 'Dark' : 'Light';
  // #region agent log
  const __dbg_lucideBefore = document.querySelectorAll('[data-lucide]').length;
  // #endregion
  const __dbg_rl0 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
  refreshLucide();
  const __dbg_rl1 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
  // #region agent log
  const __dbg_lucideAfter = document.querySelectorAll('[data-lucide]').length;
  __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H1',location:'app/Views/layouts/main.php:setTheme:afterRefreshLucide',message:'refreshLucide timing',data:{themeNext:next,lucideBefore:__dbg_lucideBefore,lucideAfter:__dbg_lucideAfter,refreshLucideMs:Math.round((__dbg_rl1-__dbg_rl0)*1000)/1000},timestamp:Date.now()});
  // #endregion

  // #region agent log
  const __dbg_t1 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
  __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H2',location:'app/Views/layouts/main.php:setTheme:exit',message:'setTheme exit',data:{next,elapsedMs:Math.round((__dbg_t1-__dbg_t0)*1000)/1000},timestamp:Date.now()});
  // #endregion

  // #region agent log
  try {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const __dbg_p1 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        // #region agent log
        try {
          document.documentElement.classList.remove('theme-switching');
          __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H2',location:'app/Views/layouts/main.php:setTheme:switchingOff',message:'theme-switching OFF',data:{next},timestamp:Date.now()});
        } catch (e) {}
        // #endregion
        __dbgSend({
          sessionId: '472502',
          runId: 'pre',
          hypothesisId: 'H2',
          location: 'app/Views/layouts/main.php:setTheme:paint',
          message: 'theme paint timing',
          data: { next, raf2Ms: Math.round((__dbg_p1 - __dbg_p0) * 1000) / 1000 },
          timestamp: Date.now(),
        });
      });
    });
  } catch (e) {}
  // #endregion
}

function toggleTheme() {
  // #region agent log
  const __dbg_curr = getCurrentTheme();
  const __dbg_target = __dbg_curr === 'dark' ? 'light' : 'dark';
  __dbgSend({sessionId:'472502',runId:'pre',hypothesisId:'H4',location:'app/Views/layouts/main.php:toggleTheme',message:'toggleTheme invoked',data:{curr:__dbg_curr,target:__dbg_target},timestamp:Date.now()});
  // #endregion
  setTheme(__dbg_target);
}

function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.textContent = msg;
  document.getElementById('toast-container').appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

const _confirmState = { resolver: null };
function appConfirm(options) {
  const conf = typeof options === 'string' ? { message: options } : (options || {});
  const overlay = document.getElementById('confirmOverlay');
  const headEl = document.getElementById('confirmHead');
  const titleEl = document.getElementById('confirmTitle');
  const msgEl = document.getElementById('confirmMessage');
  const cancelBtn = document.getElementById('confirmCancelBtn');
  const okBtn = document.getElementById('confirmOkBtn');
  if (!overlay || !msgEl || !cancelBtn || !okBtn) return Promise.resolve(window.confirm(conf.message || 'Lanjutkan?'));

  headEl.textContent = conf.head || 'Konfirmasi';
  titleEl.textContent = conf.title || 'Lanjutkan aksi?';
  msgEl.textContent = conf.message || 'Aksi ini tidak bisa dibatalkan.';
  okBtn.textContent = conf.okText || 'OK';
  cancelBtn.textContent = conf.cancelText || 'Cancel';
  okBtn.className = `btn btn-sm ${conf.okVariant === 'danger' ? 'btn-danger' : 'btn-primary'}`;

  overlay.classList.add('open');
  overlay.setAttribute('aria-hidden', 'false');

  return new Promise((resolve) => {
    _confirmState.resolver = resolve;
    const cleanup = () => {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      cancelBtn.removeEventListener('click', onCancel);
      okBtn.removeEventListener('click', onOk);
      overlay.removeEventListener('click', onOverlay);
      document.removeEventListener('keydown', onKey);
      _confirmState.resolver = null;
    };
    const done = (result) => { cleanup(); resolve(result); };
    const onCancel = () => done(false);
    const onOk = () => done(true);
    const onOverlay = (e) => { if (e.target === overlay) done(false); };
    const onKey = (e) => {
      if (e.key === 'Escape') done(false);
      if (e.key === 'Enter') done(true);
    };
    cancelBtn.addEventListener('click', onCancel);
    okBtn.addEventListener('click', onOk);
    overlay.addEventListener('click', onOverlay);
    document.addEventListener('keydown', onKey);
    setTimeout(() => okBtn.focus(), 10);
  });
}
window.appConfirm = appConfirm;

document.addEventListener('submit', async (e) => {
  const form = e.target.closest('form[data-confirm]');
  if (!form) return;
  if (form.dataset.confirmed === '1') {
    delete form.dataset.confirmed;
    return;
  }
  e.preventDefault();
  const ok = await appConfirm({
    head: form.dataset.confirmHead || 'Konfirmasi',
    title: form.dataset.confirmTitle || 'Lanjutkan aksi?',
    message: form.dataset.confirm || 'Aksi ini akan diproses sekarang.',
    okText: form.dataset.confirmOkText || 'OK',
    cancelText: form.dataset.confirmCancelText || 'Cancel',
    okVariant: form.dataset.confirmOkVariant || 'primary',
  });
  if (!ok) return;
  form.dataset.confirmed = '1';
  form.requestSubmit();
});

// ── POST forms: kirim via fetch (AJAX), redirect dari JSON (AjaxRedirectFilter) ──
document.addEventListener('submit', async function (e) {
  const form = e.target && e.target.closest ? e.target.closest('form') : null;
  if (!form || form.tagName !== 'FORM') return;
  const method = (form.getAttribute('method') || 'get').toLowerCase();
  if (method !== 'post') return;
  if (form.dataset.noAjax === 'true' || form.dataset.noAjax === '') return;
  if (e.defaultPrevented) return;

  e.preventDefault();

  const action = form.getAttribute('action') || window.location.href;
  const fd = new FormData(form);
  const busyBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
  busyBtns.forEach((b) => {
    b.disabled = true;
  });
  let navigated = false;

  try {
    const res = await fetch(action, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    });
    const ct = (res.headers.get('Content-Type') || '').toLowerCase();
    if (ct.includes('application/json')) {
      const data = await res.json().catch(() => ({}));
      if (data.csrf !== undefined && data.csrf !== null) {
        if (typeof updateAppCsrf === 'function') updateAppCsrf(data.csrf);
        else if (window.appCsrf) window.appCsrf.val = data.csrf;
        const k = (typeof getAppCsrf === 'function' ? getAppCsrf() : window.appCsrf || {}).key;
        if (k) {
          document.querySelectorAll('input[name="' + k + '"]').forEach((inp) => {
            inp.value = data.csrf;
          });
        }
      }
      if (data.redirect && !data.stay_on_page) {
        navigated = true;
        window.location.href = data.redirect;
        return;
      }
      if (data.success === false) {
        showToast(String(data.message || data.error || 'Permintaan ditolak.'), 'error');
        return;
      }
      if (!res.ok) {
        showToast(data.message ? String(data.message) : ('Permintaan gagal (' + res.status + ')'), 'error');
        return;
      }
      if (data.message) {
        showToast(String(data.message), 'success');
      }
      if (data.stay_on_page) {
        window.dispatchEvent(new CustomEvent('taskflow:ajax-form-success', { detail: data }));
        return;
      }
      if (res.ok && !data.redirect) {
        navigated = true;
        window.location.reload();
        return;
      }
      return;
    }
    const html = await res.text();
    if (res.ok) {
      document.open();
      document.write(html);
      document.close();
      navigated = true;
    } else {
      showToast('Permintaan gagal (' + res.status + '). Muat ulang halaman jika perlu.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Koneksi gagal. Coba lagi.', 'error');
  } finally {
    if (!navigated) {
      busyBtns.forEach((b) => {
        b.disabled = false;
      });
    }
  }
});

function openModal(id)  {
  if (id === 'addTaskModal' && typeof window.prepareAddTaskModal === 'function') {
    window.prepareAddTaskModal();
  }
  document.getElementById(id)?.classList.add('open');
}
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
document.querySelectorAll('.alert').forEach(el => {
  el.addEventListener('click', () => el.remove());
});
refreshLucide();
setTheme(getCurrentTheme());

document.getElementById('themeToggleBtn')?.addEventListener('click', toggleTheme);

// ── Notification dropdown ──────────────────────────────────────
let _notifDdOpen = false;
let _notifDdLoaded = false;

function toggleNotifDropdown() {
  const dd = document.getElementById('notifDropdown');
  _notifDdOpen = !_notifDdOpen;
  dd.classList.toggle('open', _notifDdOpen);
  if (_notifDdOpen && !_notifDdLoaded) {
    loadNotifDropdown();
  }
}

async function loadNotifDropdown() {
  try {
    const res  = await fetch('/notifications/unread-count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    _notifDdLoaded = true;

    const list = document.getElementById('notifDdList');
    const badge = document.getElementById('notifBadge');

    if (badge) {
      badge.textContent = data.count > 99 ? '99+' : data.count;
      badge.style.display = data.count > 0 ? '' : 'none';
    }

    if (!data.unread || data.unread.length === 0) {
      list.innerHTML = '<div class="notif-dd-empty"><i class="fa-solid fa-bell-slash"></i> Tidak ada notifikasi baru</div>';
      return;
    }

    list.innerHTML = data.unread.map(n => {
      const typeInfo = (data.types || {})[n.type] || { icon: 'fa-circle-info', color: '#4f46e5' };
      return `<div class="notif-dd-item">
        <div class="notif-dd-icon" style="color:${typeInfo.color}">
          <i class="fa-solid ${typeInfo.icon}"></i>
        </div>
        <div class="notif-dd-content">
          <div class="notif-dd-title">${n.title}</div>
          ${n.message ? `<div class="notif-dd-msg">${n.message}</div>` : ''}
          <div class="notif-dd-time">${n.time_ago}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    document.getElementById('notifDdList').innerHTML = '<div class="notif-dd-empty">Gagal memuat notifikasi.</div>';
  }
}

async function markAllReadDd() {
  const csrf = getAppCsrf();
  const hdr = <?= json_encode(config(\Config\Security::class)->headerName) ?>;
  const h = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  if (csrf.val) h[hdr] = csrf.val;
  const res = await fetch('/notifications/mark-all-read', {
    method: 'POST',
    headers: h,
    body: JSON.stringify({ [csrf.key]: csrf.val }),
  });
  try {
    const data = await res.json();
    if (data?.csrf && typeof updateAppCsrf === 'function') updateAppCsrf(data.csrf);
    else if (data?.csrf && window.appCsrf) window.appCsrf.val = data.csrf;
  } catch (e) {}
  const badge = document.getElementById('notifBadge');
  if (badge) badge.style.display = 'none';
  _notifDdLoaded = false;
  loadNotifDropdown();
}

// ── Sidebar user dropdown ─────────────────────────────────────
function toggleSidebarUserMenu() {
  document.getElementById('sidebarUserDropdown')?.classList.toggle('open');
}

// Close dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('#notifDropdownWrap')) {
    document.getElementById('notifDropdown')?.classList.remove('open');
    _notifDdOpen = false;
  }
  if (!e.target.closest('#sidebarUserWrap')) {
    document.getElementById('sidebarUserDropdown')?.classList.remove('open');
  }
});

// Poll notification count every 60 seconds
setInterval(async () => {
  try {
    const res  = await fetch('/notifications/unread-count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const badge = document.getElementById('notifBadge');
    if (badge) {
      badge.textContent = data.count > 99 ? '99+' : data.count;
      badge.style.display = data.count > 0 ? '' : 'none';
    }
  } catch(e) {}
}, 60000);

const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileNavBackdrop = document.getElementById('mobileNavBackdrop');
function closeMobileNav() {
  document.body.classList.remove('mobile-nav-open');
  mobileNavBackdrop?.classList.remove('open');
}
function openMobileNav() {
  document.body.classList.add('mobile-nav-open');
  mobileNavBackdrop?.classList.add('open');
}
mobileMenuBtn?.addEventListener('click', () => {
  const opened = document.body.classList.contains('mobile-nav-open');
  if (opened) closeMobileNav(); else openMobileNav();
});
mobileNavBackdrop?.addEventListener('click', closeMobileNav);
window.addEventListener('resize', () => {
  if (window.innerWidth >= 1024) closeMobileNav();
});
document.querySelectorAll('.sidebar a').forEach((el) => el.addEventListener('click', closeMobileNav));

// ── Sidebar collapse (desktop only) ──────────────────────────
(function () {
  const collapseBtn  = document.getElementById('sidebarCollapseBtn');
  const collapseIcon = document.getElementById('sidebarCollapseIcon');
  const STORAGE_KEY  = 'taskflow-sidebar-collapsed';

  function _syncIcons(collapsed) {
    if (collapseIcon) {
      collapseIcon.className = collapsed
        ? 'fa-solid fa-chevron-right'
        : 'fa-solid fa-chevron-left';
    }
    if (collapseBtn) {
      collapseBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
      collapseBtn.setAttribute('aria-label', collapseBtn.title);
    }
  }

  function applyCollapsed(collapsed) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    // Remove the pre-load helper class (safe to call even if absent)
    document.documentElement.classList.remove('sidebar-pre-collapsed');
    _syncIcons(collapsed);
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch(e) {}
  }

  // ── Transfer pre-collapsed class from <html> → <body> synchronously ──
  // The <head> script already set sidebar-pre-collapsed on <html> which prevents
  // any flash. Here we simply move it to <body> so interactive CSS takes over.
  // Both happen in the same JS task → no repaint between them → zero animation.
  if (document.documentElement.classList.contains('sidebar-pre-collapsed')) {
    document.documentElement.classList.remove('sidebar-pre-collapsed');
    document.body.classList.add('sidebar-collapsed');
    _syncIcons(true);
  }

  collapseBtn?.addEventListener('click', () => {
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    applyCollapsed(!isCollapsed);
  });

  // Remove collapsed class when switching to mobile viewport
  window.addEventListener('resize', () => {
    if (window.innerWidth < 1024) {
      document.body.classList.remove('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-pre-collapsed');
    }
  });
})();

</script>

<!-- ═══════════════════════════════════ RICHTEXT (global) ══ -->
<!-- Editor.js CDN — loaded once for all pages               -->
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.29.1/dist/editorjs.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/paragraph@2.11.6/dist/paragraph.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.7/dist/header.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@2.0.2/dist/list.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@2.7.0/dist/quote.umd.min.js"></script>

<!-- Rich Text EDITOR Modal -->
<div id="rtEditorModal" style="display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:min(720px,96vw);max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span style="font-weight:600;font-size:15px;display:inline-flex;align-items:center;gap:6px"><i data-lucide="square-pen" style="width:14px;height:14px"></i> Rich Text Editor</span>
      <button onclick="closeRtEditor()" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-2)">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
    <div id="rtEditorHolder" class="rt-js-editor-shell" style="overflow-y:auto;padding:20px 28px;min-height:150px;max-height:52vh"></div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
      <button onclick="closeRtEditor()" class="btn btn-ghost">Batal</button>
      <button onclick="saveRtEditor()" class="btn btn-primary"><i data-lucide="check" style="width:13px;height:13px"></i> Simpan</button>
    </div>
  </div>
</div>

<!-- Rich Text VIEWER Modal (read-only) -->
<div id="rtViewerModal" style="display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:min(680px,96vw);max-height:82vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border)">
      <span style="font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:6px"><i data-lucide="file-text" style="width:14px;height:14px"></i> Rich Text</span>
      <button onclick="document.getElementById('rtViewerModal').style.display='none'" style="background:none;border:none;cursor:pointer;padding:4px">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
    <div id="rtViewerContent" style="flex:1;overflow-y:auto;padding:20px 28px;font-size:14px;line-height:1.7;color:var(--text)"></div>
  </div>
</div>

<script>
// ── Global Rich Text helpers ──────────────────────────────────
let _rtEditor = null, _rtTargetId = null, _rtPreviewId = null;
let _rtInlineTaskId = null, _rtInlineFieldKey = null, _rtInlinePreviewId = null;

function _rtBuildConfig(data) {
  const tools = {};

  // Support multiple UMD global names used by Editor.js tools.
  const ParagraphTool = window.Paragraph || window.EditorjsParagraph;
  const HeaderTool    = window.Header || window.EditorjsHeader;
  const ListTool      = window.List || window.EditorjsList;
  const QuoteTool     = window.Quote || window.EditorjsQuote;

  if (ParagraphTool) tools.paragraph = { class: ParagraphTool, inlineToolbar: true };
  if (HeaderTool) tools.header = { class: HeaderTool, inlineToolbar: true, config: { levels:[1,2,3], defaultLevel:2 } };
  if (ListTool) tools.list = { class: ListTool, inlineToolbar: true };
  if (QuoteTool) tools.quote = { class: QuoteTool, inlineToolbar: true };

  return {
    holder: 'rtEditorHolder',
    autofocus: true,
    data,
    defaultBlock: tools.paragraph ? 'paragraph' : undefined,
    tools,
    placeholder: 'Tulis konten di sini...',
  };
}

function _rtInitEditor(data) {
  const holder = document.getElementById('rtEditorHolder');
  if (!window.EditorJS) {
    holder.innerHTML = '<div style="color:var(--danger);font-size:13px">Editor.js gagal dimuat. Coba refresh halaman.</div>';
    return null;
  }
  try {
    return new EditorJS(_rtBuildConfig(data));
  } catch (e) {
    console.error('Editor init error:', e);
    holder.innerHTML = '<div style="color:var(--danger);font-size:13px">Editor gagal dibuka. Buka console untuk detail error.</div>';
    return null;
  }
}

// Allow opening links directly from editor area.
document.addEventListener('click', (e) => {
  const a = e.target.closest('#rtEditorHolder a');
  if (!a) return;
  e.preventDefault();
  const href = a.getAttribute('href');
  if (!href) return;
  window.open(href, '_blank', 'noopener,noreferrer');
});

function openRtEditor(fieldKey, hiddenId) {
  _rtTargetId  = hiddenId;
  _rtPreviewId = hiddenId + '_preview';
  _rtInlineTaskId = null;
  _rtInlineFieldKey = null;
  _rtInlinePreviewId = null;
  document.getElementById('rtEditorModal').style.display = 'flex';
  refreshLucide(document.getElementById('rtEditorModal'));
  if (_rtEditor) { _rtEditor.destroy(); _rtEditor = null; }
  document.getElementById('rtEditorHolder').innerHTML = '';
  const existing = document.getElementById(hiddenId)?.value;
  let data;
  if (existing) { try { data = JSON.parse(existing); } catch(e) {} }
  _rtEditor = _rtInitEditor(data);
}

function openRtEditorInline(taskId, fieldKey, currentJson, previewId = null) {
  _rtTargetId = null;
  _rtPreviewId = null;
  _rtInlineTaskId = taskId;
  _rtInlineFieldKey = fieldKey;
  _rtInlinePreviewId = previewId;

  document.getElementById('rtEditorModal').style.display = 'flex';
  refreshLucide(document.getElementById('rtEditorModal'));
  if (_rtEditor) { _rtEditor.destroy(); _rtEditor = null; }
  document.getElementById('rtEditorHolder').innerHTML = '';

  let data;
  if (currentJson) { try { data = JSON.parse(currentJson); } catch(e) {} }
  _rtEditor = _rtInitEditor(data);
}

async function saveRtEditor() {
  if (!_rtEditor) return;
  const output = await _rtEditor.save();

  if (_rtInlineTaskId && _rtInlineFieldKey) {
    try {
      const value = JSON.stringify(output);
      const csrf = getAppCsrf();
      const _rtCsrfHdr = <?= json_encode(config(\Config\Security::class)->headerName) ?>;
      const _rtH = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      if (csrf.val) _rtH[_rtCsrfHdr] = csrf.val;
      const res = await fetch(`/tasks/${_rtInlineTaskId}/field-update`, {
        method: 'POST',
        headers: _rtH,
        body: JSON.stringify({ field_key: _rtInlineFieldKey, value, [csrf.key]: csrf.val })
      });
      const data = await res.json();
      if (data?.csrf) updateAppCsrf(data.csrf);
      if (!data?.success) {
        showToast('Gagal simpan rich text', 'error');
        return;
      }

      if (_rtInlinePreviewId) {
        const prevBtn = document.getElementById(_rtInlinePreviewId);
        if (prevBtn) {
          prevBtn.dataset.value = value;
          prevBtn.innerHTML = '<i data-lucide="square-pen" style="width:12px;height:12px"></i> Edit';
          refreshLucide(prevBtn);
        }
      }
      showToast('Rich text disimpan', 'success');
      closeRtEditor();
      return;
    } catch(e) {
      showToast('Network error', 'error');
      return;
    }
  }

  document.getElementById(_rtTargetId).value = JSON.stringify(output);
  const prev = document.getElementById(_rtPreviewId);
  if (prev) {
    const raw = (output.blocks||[]).slice(0,2).map(b=>b.data?.text||'').join(' ').trim();
    const esc = _rtPreviewEscape(raw);
    prev.innerHTML = raw
      ? `<span style="color:var(--text)">${esc.length>100 ? esc.substring(0,100)+'…' : esc}</span>`
      : '<span style="color:var(--text-3)">Klik untuk buka editor…</span>';
  }
  closeRtEditor();
}

function _rtPreviewEscape(s) {
  const div = document.createElement('div');
  div.textContent = String(s ?? '');
  return div.innerHTML;
}

function closeRtEditor() {
  document.getElementById('rtEditorModal').style.display = 'none';
}

function viewRtContent(json) {
  const cont = document.getElementById('rtViewerContent');
  document.getElementById('rtViewerModal').style.display = 'flex';
  let html = '';

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }

  function linkify(text) {
    if (!text) return '';
    const src = String(text);
    const safe = escapeHtml(src);
    const urlRegex = /((https?:\/\/|www\.)[^\s<]+)/gi;
    return safe.replace(urlRegex, (url) => {
      const rawHref = url.startsWith('http') ? url : `https://${url}`;
      let href = '';
      try {
        const u = new URL(rawHref);
        if (u.protocol !== 'http:' && u.protocol !== 'https:') {
          return escapeHtml(url);
        }
        href = u.toString();
      } catch (e) {
        return escapeHtml(url);
      }
      const hrefSafe = href
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
      return `<a href="${hrefSafe}" target="_blank" rel="noopener noreferrer">${escapeHtml(url)}</a>`;
    });
  }

  try {
    const data = typeof json === 'string' && json.trim() ? JSON.parse(json) : (json || {});
    for (const blk of (data.blocks || [])) {
      switch(blk.type) {
        case 'header': {
          const rawLevel = Number.parseInt(String(blk?.data?.level ?? '2'), 10);
          const level = Number.isFinite(rawLevel) ? Math.min(6, Math.max(1, rawLevel)) : 2;
          html += `<h${level} style="margin:.6em 0 .3em">${linkify(blk.data.text)}</h${level}>`;
          break;
        }
        case 'paragraph': html += `<p style="margin:.4em 0">${linkify(blk.data.text)}</p>`; break;
        case 'list': {
          const listType = blk.data.style || blk.data.type || 'unordered';
          const itemText = (i) => {
            if (typeof i === 'string') return i;
            if (i && typeof i === 'object' && typeof i.content === 'string') return i.content;
            return '';
          };
          html += listType === 'ordered'
          ? `<ol style="margin:.4em 0 .4em 1.4em">${(blk.data.items||[]).map(i=>`<li>${linkify(itemText(i))}</li>`).join('')}</ol>`
          : `<ul style="margin:.4em 0 .4em 1.4em">${(blk.data.items||[]).map(i=>`<li>${linkify(itemText(i))}</li>`).join('')}</ul>`;
          break;
        }
        case 'quote':     html += `<blockquote style="border-left:3px solid var(--accent);margin:.5em 0;padding:.3em .8em;color:var(--text-2)">${linkify(blk.data.text)}</blockquote>`; break;
        default:          html += `<p>${linkify(blk.data?.text || '')}</p>`;
      }
    }
  } catch(e) { html = json ? `<p style="color:var(--text-3)">${linkify(json)}</p>` : ''; }
  
  if (!html.trim()) {
    html = '<div style="display:flex;align-items:center;justify-content:center;height:100px;color:var(--text-3)">Konten kosong.</div>';
  }
  cont.innerHTML = html;
}
</script>
<?php if (!empty($sessionUserId)): ?>
<?= view('components/global_search') ?>
<?php endif; ?>
</body>
</html>
