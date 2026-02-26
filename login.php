<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (isset($_SESSION['admin_id'])) {
    header("Location: /Sama_Roadlines/index.php");
    exit();
}

require_once "businessLogics/Admin.php";

$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ok = Admin::login($_POST['signin-username'] ?? '', $_POST['signin-password'] ?? '');
    if ($ok) { header("Location: index.php"); exit(); }
    $error = "Invalid username or password. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
<title>Sama Roadlines — Sign In</title>

<!-- Favicon -->
<link rel="icon" href="./assets/images/brand-logos/favicon.ico" type="image/x-icon">

<!-- ✅ CDN Icons — No local dependency -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root {
  --navy:       #0f172a;
  --navy-mid:   #1e3a5f;
  --blue:       #1d4ed8;
  --blue-light: #3b82f6;
  --accent:     #f59e0b;
  --red:        #ef4444;
  --surface:    #ffffff;
  --muted:      #94a3b8;
  --border:     #e2e8f0;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  min-height: 100vh;
  display: flex;
  background: #f0f4f8;
  overflow: hidden;
}

/* ════════════════════════════════
   LEFT — Brand Panel
════════════════════════════════ */
.brand-panel {
  flex: 1;
  background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 50%, #1d4ed8 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 48px;
  position: relative;
  overflow: hidden;
}

/* Glowing orbs */
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  pointer-events: none;
}
.orb-1 { width: 320px; height: 320px; background: rgba(59,130,246,0.25); top: -80px; right: -80px; }
.orb-2 { width: 240px; height: 240px; background: rgba(245,158,11,0.12); bottom: 60px; left: -60px; }
.orb-3 { width: 180px; height: 180px; background: rgba(29,78,216,0.3);  top: 45%; left: 30%; transform: translate(-50%,-50%); }

/* Grid overlay */
.brand-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

.brand-content { position: relative; z-index: 2; text-align: center; width: 100%; max-width: 420px; }

/* Truck animation */
.truck-wrap {
  position: relative;
  width: 110px;
  height: 110px;
  margin: 0 auto 28px;
  background: rgba(255,255,255,0.06);
  border-radius: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(10px);
  animation: float 3.5s ease-in-out infinite;
}
.truck-wrap::after {
  content: '';
  position: absolute;
  inset: -1px;
  border-radius: 29px;
  background: linear-gradient(135deg, rgba(59,130,246,0.4), transparent 60%);
  z-index: -1;
}
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50%       { transform: translateY(-10px); }
}

.truck-emoji { font-size: 58px; line-height: 1; }

.brand-title {
  font-size: 36px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  line-height: 1.1;
}
.brand-sub {
  font-size: 12px;
  color: rgba(255,255,255,0.5);
  letter-spacing: 4px;
  text-transform: uppercase;
  margin-top: 8px;
}

/* Divider line */
.brand-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  margin: 32px 0;
}

/* Feature cards */
.feature-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.feature-card {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  padding: 16px 14px;
  text-align: left;
  backdrop-filter: blur(6px);
  transition: background 0.2s;
}
.feature-card:hover { background: rgba(255,255,255,0.09); }
.feature-icon { font-size: 22px; margin-bottom: 8px; display: block; }
.feature-label { font-size: 12px; color: rgba(255,255,255,0.75); font-weight: 600; line-height: 1.4; }

/* Road strip */
.road-strip {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 52px;
  background: rgba(0,0,0,0.35);
  display: flex;
  align-items: center;
  gap: 0;
  overflow: hidden;
  z-index: 3;
}
.road-dashes {
  height: 3px;
  width: 100%;
  background: repeating-linear-gradient(
    90deg,
    #f59e0b 0, #f59e0b 36px,
    transparent 36px, transparent 64px
  );
  animation: road-scroll 1.2s linear infinite;
}
@keyframes road-scroll {
  from { background-position: 0; }
  to   { background-position: -100px; }
}

/* ════════════════════════════════
   RIGHT — Login Panel
════════════════════════════════ */
.login-panel {
  width: 480px;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface);
  padding: 48px 44px;
  position: relative;
  box-shadow: -8px 0 48px rgba(0,0,0,0.12);
}

/* Corner decoration */
.login-panel::before {
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 180px; height: 180px;
  background: radial-gradient(circle at top right, rgba(59,130,246,0.06), transparent 70%);
  pointer-events: none;
}

.login-wrap { width: 100%; max-width: 360px; }

/* Lock icon */
.lock-icon-wrap {
  width: 68px; height: 68px;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 28px;
  box-shadow: 0 8px 24px rgba(29,78,216,0.3);
  position: relative;
}
.lock-icon-wrap::after {
  content: '';
  position: absolute;
  inset: -2px;
  border-radius: 22px;
  background: linear-gradient(135deg, rgba(59,130,246,0.4), transparent);
  z-index: -1;
}
.lock-icon-wrap i {
  font-size: 32px;
  color: #fff;
}

.login-title { font-size: 28px; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
.login-sub   { font-size: 13.5px; color: var(--muted); margin-bottom: 32px; }

/* Error Alert */
.error-alert {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-left: 4px solid var(--red);
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 13px;
  color: #b91c1c;
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 20px;
  animation: shake 0.38s ease;
}
.error-alert i { font-size: 18px; flex-shrink: 0; }
@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%,60%  { transform: translateX(-6px); }
  40%,80%  { transform: translateX(6px); }
}

/* Input groups */
.field-group { margin-bottom: 20px; }
.field-label {
  font-size: 12.5px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 7px;
  display: block;
  letter-spacing: 0.3px;
}
.input-wrap {
  position: relative;
}
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
  transition: color 0.2s;
}
.field-input {
  width: 100%;
  height: 50px;
  padding: 0 44px;
  border: 1.8px solid var(--border);
  border-radius: 12px;
  font-size: 14px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--navy);
  background: #f8fafc;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  outline: none;
}
.field-input::placeholder { color: #c0c9d4; }
.field-input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 4px rgba(29,78,216,0.1);
  background: #fff;
}
.field-input:focus + .input-icon-end,
.input-wrap:focus-within .input-icon { color: var(--blue); }

/* Password toggle */
.pw-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted);
  font-size: 18px;
  padding: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  transition: color 0.2s;
}
.pw-toggle:hover { color: var(--blue); }

/* Signin Button */
.btn-signin {
  width: 100%;
  height: 52px;
  background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
  border: none;
  border-radius: 12px;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif;
  letter-spacing: 0.4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity 0.2s, transform 0.1s, box-shadow 0.2s;
  box-shadow: 0 4px 20px rgba(29,78,216,0.35);
  margin-top: 8px;
}
.btn-signin:hover  { opacity: 0.92; box-shadow: 0 6px 28px rgba(29,78,216,0.45); }
.btn-signin:active { transform: scale(0.98); }
.btn-signin:disabled { opacity: 0.7; cursor: not-allowed; }

/* Spinner */
.spinner {
  width: 18px; height: 18px;
  border: 2.5px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* System info bottom */
.system-info {
  margin-top: 32px;
  padding: 14px 16px;
  background: #f8fafc;
  border: 1px solid var(--border);
  border-radius: 12px;
  text-align: center;
}
.system-info .badge-system {
  font-size: 11px;
  color: #64748b;
  line-height: 1.8;
}
.system-info strong { color: var(--navy); }

/* Bottom tag */
.copy-line {
  margin-top: 24px;
  text-align: center;
  font-size: 11.5px;
  color: #cbd5e1;
}

/* Responsive */
@media (max-width: 768px) {
  body { overflow-y: auto; }
  .brand-panel { display: none; }
  .login-panel { width: 100%; min-height: 100vh; padding: 36px 24px; }
}
</style>
</head>
<body>

<!-- ════ LEFT — BRAND PANEL ════ -->
<div class="brand-panel">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <div class="brand-content">

    <div class="truck-wrap">
      <span class="truck-emoji">🚛</span>
    </div>

    <div class="brand-title">Sama Roadlines</div>
    <div class="brand-sub">Transport Management System</div>

    <div class="brand-divider"></div>

    <div class="feature-grid">
      <div class="feature-card">
        <span class="feature-icon">🗺️</span>
        <div class="feature-label">Multi-Route<br>Trip Management</div>
      </div>
      <div class="feature-card">
        <span class="feature-icon">📄</span>
        <div class="feature-label">Bills &amp;<br>GC Notes</div>
      </div>
      <div class="feature-card">
        <span class="feature-icon">💰</span>
        <div class="feature-label">Payment<br>Tracking</div>
      </div>
      <div class="feature-card">
        <span class="feature-icon">📊</span>
        <div class="feature-label">Commission<br>Management</div>
      </div>
      <div class="feature-card">
        <span class="feature-icon">🚚</span>
        <div class="feature-label">Vehicle Owner<br>Payments</div>
      </div>
      <div class="feature-card">
        <span class="feature-icon">⚡</span>
        <div class="feature-label">Direct Payment<br>Trips</div>
      </div>
    </div>

  </div>

  <div class="road-strip">
    <div class="road-dashes"></div>
  </div>
</div>

<!-- ════ RIGHT — LOGIN PANEL ════ -->
<div class="login-panel">
  <div class="login-wrap">

    <div class="lock-icon-wrap">
      <i class="ri-shield-keyhole-line"></i>
    </div>

    <div class="login-title">Welcome Back</div>
    <div class="login-sub">Sign in to your admin dashboard</div>

    <?php if ($error): ?>
    <div class="error-alert">
      <i class="ri-error-warning-fill"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" id="loginForm" novalidate>

      <!-- Username -->
      <div class="field-group">
        <label class="field-label" for="signin-username">
          USERNAME
        </label>
        <div class="input-wrap">
          <i class="ri-user-line input-icon"></i>
          <input
            type="text"
            class="field-input"
            id="signin-username"
            name="signin-username"
            placeholder="Enter your username"
            required
            autofocus
            value="<?= htmlspecialchars($_POST['signin-username'] ?? '') ?>"
          >
        </div>
        <div class="field-error" id="err-username" style="font-size:12px;color:var(--red);margin-top:5px;display:none;">
          <i class="ri-information-line"></i> Username is required
        </div>
      </div>

      <!-- Password -->
      <div class="field-group">
        <label class="field-label" for="signin-password">
          PASSWORD
        </label>
        <div class="input-wrap">
          <i class="ri-lock-line input-icon"></i>
          <input
            type="password"
            class="field-input"
            id="signin-password"
            name="signin-password"
            placeholder="Enter your password"
            required
            style="padding-right: 44px;"
          >
          <button type="button" class="pw-toggle" id="pwToggle" title="Show/Hide Password">
            <i class="ri-eye-off-line" id="pwIcon"></i>
          </button>
        </div>
        <div class="field-error" id="err-password" style="font-size:12px;color:var(--red);margin-top:5px;display:none;">
          <i class="ri-information-line"></i> Password is required
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn-signin" id="submitBtn">
        <span id="btnText" style="display:flex;align-items:center;gap:8px;">
          <i class="ri-login-box-line" style="font-size:18px;"></i>
          Sign In
        </span>
        <span id="btnLoading" style="display:none;align-items:center;gap:8px;">
          <span class="spinner"></span>
          Signing in...
        </span>
      </button>

    </form>

    <!-- System Info -->
    <div class="system-info">
      <div class="badge-system">
        <strong>🚛 Sama Roadlines TMS</strong><br>
        Trips · Bills · Payments · GC Notes · Commission
      </div>
    </div>

    <div class="copy-line">
      © <?= date('Y') ?> Sama Roadlines &nbsp;&middot;&nbsp; All rights reserved
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Password Toggle
document.getElementById('pwToggle').addEventListener('click', function () {
  var inp = document.getElementById('signin-password');
  var ico = document.getElementById('pwIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'ri-eye-line';
    this.title = 'Hide Password';
  } else {
    inp.type = 'password';
    ico.className = 'ri-eye-off-line';
    this.title = 'Show Password';
  }
});

// ── Form Validation + Submit State
document.getElementById('loginForm').addEventListener('submit', function (e) {
  var user = document.getElementById('signin-username').value.trim();
  var pass = document.getElementById('signin-password').value.trim();
  var errU = document.getElementById('err-username');
  var errP = document.getElementById('err-password');
  var valid = true;

  errU.style.display = 'none';
  errP.style.display = 'none';

  if (!user) { errU.style.display = 'block'; valid = false; }
  if (!pass) { errP.style.display = 'block'; valid = false; }

  if (!valid) { e.preventDefault(); return; }

  // Show loading state
  document.getElementById('btnText').style.display    = 'none';
  document.getElementById('btnLoading').style.display = 'flex';
  document.getElementById('submitBtn').disabled = true;
});

// ── Clear errors on input
document.getElementById('signin-username').addEventListener('input', function () {
  document.getElementById('err-username').style.display = 'none';
});
document.getElementById('signin-password').addEventListener('input', function () {
  document.getElementById('err-password').style.display = 'none';
});

// ── Input focus: highlight icon
document.querySelectorAll('.field-input').forEach(function(inp) {
  inp.addEventListener('focus', function() {
    this.parentElement.querySelector('.input-icon').style.color = '#1d4ed8';
  });
  inp.addEventListener('blur', function() {
    this.parentElement.querySelector('.input-icon').style.color = '#94a3b8';
  });
});
</script>
</body>
</html>
