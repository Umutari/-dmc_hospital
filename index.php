<?php
require_once __DIR__ . '/config/functions.php';

/* handle login */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email && $pass) {
        $user = row("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['user']    = $user;
            execute("UPDATE users SET updated_at = NOW() WHERE id = ?", [$user['id']]);
            audit('login', 'users', $user['id'], 'User logged in');
            header('Location: ' . dashboardUrl()); exit;
        }
        $error = 'Invalid email or password.';
    } else {
        $error = 'Please fill in all fields.';
    }
}

if (isLoggedIn()) {
    $valid_roles = ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'accountant', 'lab_technician', 'patient'];
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $valid_roles)) {
        header('Location: ' . dashboardUrl()); exit;
    }
    session_destroy();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — DMC Hospital</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sora',sans-serif;background:linear-gradient(135deg,#0A2342 0%,#1B4F72 50%,#0E6655 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.login-wrap{width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1fr;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.3)}
.login-left{background:linear-gradient(160deg,#0A2342,#1A6BB5);padding:3rem;display:flex;flex-direction:column;justify-content:space-between;color:#fff}
.login-right{padding:3rem}
.dmc-logo{display:flex;align-items:center;gap:12px;margin-bottom:2.5rem}
.dmc-orb{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;font-size:24px}
.dmc-name{font-size:18px;font-weight:700;line-height:1.2}
.dmc-sub{font-size:11px;opacity:.7;margin-top:2px}
.hero-title{font-size:28px;font-weight:700;line-height:1.3;margin-bottom:1rem}
.hero-sub{font-size:13px;opacity:.75;line-height:1.7}
.feature-list{list-style:none;margin-top:2rem}
.feature-list li{display:flex;align-items:center;gap:10px;font-size:13px;opacity:.85;margin-bottom:.75rem}
.feature-list li i{font-size:16px;color:#52C3A0}
.address-block{font-size:11.5px;opacity:.6;border-top:1px solid rgba(255,255,255,.15);padding-top:1rem;margin-top:auto}
.right-title{font-size:22px;font-weight:700;color:#0A2342;margin-bottom:.3rem}
.right-sub{font-size:13px;color:#6c757d;margin-bottom:2rem}
.form-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;margin-bottom:5px}
.form-control{height:46px;border-radius:10px;border:1.5px solid #dee2e6;font-size:14px;transition:border-color .18s,box-shadow .18s}
.form-control:focus{border-color:#1A6BB5;box-shadow:0 0 0 3px rgba(26,107,181,.12)}
.btn-login{width:100%;height:48px;background:linear-gradient(135deg,#0A2342,#1A6BB5);border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;transition:opacity .15s,transform .1s}
.btn-login:hover{opacity:.92}
.btn-login:active{transform:scale(.98)}
.role-badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:1.5rem}
.rb{background:#f0f4ff;border:1px solid #c7d7f5;border-radius:20px;padding:3px 11px;font-size:10.5px;font-weight:600;color:#1A6BB5}
.input-icon{position:relative}
.input-icon i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#adb5bd;font-size:15px;pointer-events:none}
.input-icon .form-control{padding-left:38px}
@media(max-width:700px){.login-wrap{grid-template-columns:1fr}.login-left{display:none}}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-left">
    <div>
      <div class="dmc-logo">
        <div class="dmc-orb"><i class="bi bi-hospital"></i></div>
        <div><div class="dmc-name">DMC</div><div class="dmc-sub">Dream Medical Center</div></div>
      </div>
      <div class="hero-title">Transforming Healthcare in Rwanda</div>
      <div class="hero-sub">Integrated hospital management system providing seamless care from registration through treatment, billing and follow-up.</div>
      <ul class="feature-list">
        <li><i class="bi bi-check-circle-fill"></i> Patient records & electronic medical history</li>
        <li><i class="bi bi-check-circle-fill"></i> Smart appointment scheduling</li>
        <li><i class="bi bi-check-circle-fill"></i> Pharmacy & lab management</li>
        <li><i class="bi bi-check-circle-fill"></i> MoMo & card billing (Flutterwave)</li>
        <li><i class="bi bi-check-circle-fill"></i> SMS/WhatsApp notifications</li>
        <li><i class="bi bi-check-circle-fill"></i> Real-time reports & analytics</li>
      </ul>
    </div>
    <div class="address-block">
      <i class="bi bi-geo-alt me-1"></i> KK 541 Street, Kigali &nbsp;|&nbsp;
      <i class="bi bi-telephone me-1"></i> 0782 749 660
    </div>
  </div>

  <div class="login-right d-flex flex-column justify-content-center">
    <div class="right-title">Welcome back</div>
    <div class="right-sub">Sign in to your DMC account</div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:13px">
      <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
    <div class="alert alert-warning py-2 mb-3" style="font-size:13px">
      <i class="bi bi-shield-lock me-2"></i>Access denied for your role.
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Email address</label>
        <div class="input-icon">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" class="form-control" placeholder="you@dmc.rw"
                 value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-icon">
          <i class="bi bi-lock"></i>
          <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
          <i class="bi bi-eye" id="eyeBtn" onclick="togglePass()" style="left:auto;right:13px;cursor:pointer;z-index:2;pointer-events:all"></i>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <div style="text-align:center;margin-top:2rem">
      <p style="font-size:13px;color:#666">Don't have an account?
      <a href="/dmc/signup.php" style="color:#1A6BB5;text-decoration:none;font-weight:600">Create one here</a></p>
    </div>

    <div style="font-size:11px;color:#aaa;text-align:center;margin-top:1.5rem">
      &copy; <?= date('Y') ?> DMC - Dream Medical Center. All rights reserved.
    </div>
  </div>
</div>
<script>
function togglePass(){
  const i=document.getElementById('passInput');
  const e=document.getElementById('eyeBtn');
  if(i.type==='password'){i.type='text';e.className='bi bi-eye-slash';}
  else{i.type='password';e.className='bi bi-eye';}
}
</script>
</body>
</html>
