<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — TaskFlow</title>
  <script>
    (function(){
      try {
        const t = localStorage.getItem('taskflow-theme');
        const d = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', t || (d ? 'dark' : 'light'));
      } catch(e) { document.documentElement.setAttribute('data-theme','light'); }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/themes/theme-light.css" />
  <link rel="stylesheet" href="/assets/css/themes/theme-dark.css" />
  <link rel="stylesheet" href="/assets/css/themes/theme-brand.css" />
  <link rel="stylesheet" href="/assets/css/base/app-base.css" />
  <link rel="stylesheet" href="/assets/css/pages/auth-login.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
</head>
<body class="auth-body">

<div class="auth-bg">
  <div class="auth-bg-shape auth-bg-shape-1"></div>
  <div class="auth-bg-shape auth-bg-shape-2"></div>
</div>

<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <div class="auth-brand-icon">
        <i class="fa-solid fa-bolt"></i>
      </div>
      <div>
        <div class="auth-brand-name">TaskFlow</div>
        <div class="auth-brand-sub">Task Management System</div>
      </div>
    </div>

    <h1 class="auth-title">Selamat datang kembali</h1>
    <p class="auth-subtitle">Masukkan kredensial Anda untuk melanjutkan</p>

    <!-- Alerts -->
    <?php if (session()->getFlashdata('error')): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= esc(session()->getFlashdata('error')) ?>
      </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
      <div class="auth-alert auth-alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <?= esc(session()->getFlashdata('success')) ?>
      </div>
    <?php endif; ?>
    <?php if ($errors = session()->getFlashdata('errors')): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <ul style="margin:4px 0 0 16px;padding:0">
          <?php foreach ((array)$errors as $e): ?>
            <li><?= esc($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form action="/auth/login" method="POST" class="auth-form" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="identifier">
          <i class="fa-solid fa-user icon-xs"></i> Email atau Username
        </label>
        <input
          type="text"
          id="identifier"
          name="identifier"
          class="form-control"
          placeholder="email@domain.com atau username"
          value="<?= esc(old('identifier')) ?>"
          autocomplete="username"
          required
        />
      </div>

      <div class="form-group">
        <label class="form-label" for="password">
          <i class="fa-solid fa-lock icon-xs"></i> Password
        </label>
        <div class="input-password-wrap">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Masukkan password"
            autocomplete="current-password"
            required
          />
          <button type="button" class="btn-toggle-password" onclick="togglePassword()" title="Tampilkan/sembunyikan password">
            <i class="fa-solid fa-eye" id="passwordEyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="auth-remember-row">
        <label class="checkbox-label">
          <input type="checkbox" name="remember_me" value="1" id="rememberMe" />
          <span class="checkbox-custom"></span>
          <span>Ingat saya <span class="text-muted">(30 hari)</span></span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary auth-submit">
        <i class="fa-solid fa-arrow-right-to-bracket"></i>
        Masuk
      </button>
    </form>

    <div class="auth-footer">
      <span class="text-muted">Lupa password? Hubungi administrator Anda.</span>
    </div>

    <?php if (ENVIRONMENT === 'development'): ?>
    <div class="dev-hint">
      <div class="dev-hint-label">
        <i class="fa-solid fa-flask"></i> Dev Credentials
      </div>
      <div class="dev-hint-row">
        <span class="dev-hint-key">Username</span>
        <code class="dev-hint-val" onclick="fillCredentials('superadmin', 'Admin@123')">superadmin</code>
      </div>
      <div class="dev-hint-row">
        <span class="dev-hint-key">Email</span>
        <code class="dev-hint-val" onclick="fillCredentials('superadmin@taskflow.local', 'Admin@123')">superadmin@taskflow.local</code>
      </div>
      <div class="dev-hint-row">
        <span class="dev-hint-key">Password</span>
        <code class="dev-hint-val" onclick="fillCredentials('superadmin', 'Admin@123')">Admin@123</code>
      </div>
      <div class="dev-hint-tip">
        <i class="fa-solid fa-hand-pointer" style="font-size:10px"></i> Klik credential untuk auto-fill
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="auth-theme-toggle">
    <button type="button" id="themeToggleBtn" class="btn btn-ghost btn-sm" title="Toggle tema">
      <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>
  </div>
</div>

<script>
function fillCredentials(identifier, password) {
  document.getElementById('identifier').value = identifier;
  document.getElementById('password').value   = password;
  document.getElementById('identifier').focus();
}

function togglePassword() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('passwordEyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

(function() {
  const btn  = document.getElementById('themeToggleBtn');
  const icon = document.getElementById('themeIcon');
  function syncIcon() {
    const t = document.documentElement.getAttribute('data-theme');
    icon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  }
  syncIcon();
  btn?.addEventListener('click', () => {
    const curr = document.documentElement.getAttribute('data-theme');
    const next = curr === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('taskflow-theme', next); } catch(e) {}
    syncIcon();
  });
})();
</script>
</body>
</html>
