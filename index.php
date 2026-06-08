<?php
require_once __DIR__ . '/config/functions.php';

$error       = '';
$otp_error   = '';
$otp_message = '';

/* ── Cancel 2FA and return to login ── */
if (isset($_GET['back'])) {
    unset($_SESSION['pending_2fa']);
    header('Location: /dmc/index.php'); exit;
}

/* ── STEP 2: OTP verification ── */
if (isset($_SESSION['pending_2fa']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'resend_otp') {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['pending_2fa']['otp']   = $otp;
        $_SESSION['pending_2fa']['exp']   = time() + 600;
        $_SESSION['pending_2fa']['tries'] = 0;
        sendSMS($_SESSION['pending_2fa']['phone'],
            "DMC Hospital\nYour login code: $otp\nValid 10 min. Do NOT share. KK 541 St, Kigali.");
        $otp_message = 'A new code has been sent to your phone.';
    } else {
        $entered = trim($_POST['otp'] ?? '');
        $tfa     = $_SESSION['pending_2fa'];

        if ($tfa['tries'] >= 5) {
            unset($_SESSION['pending_2fa']);
            $error = 'Too many failed attempts. Please sign in again.';
        } elseif (time() > $tfa['exp']) {
            unset($_SESSION['pending_2fa']);
            $error = 'Verification code expired. Please sign in again.';
        } elseif (strlen($entered) === 6 && hash_equals($tfa['otp'], $entered)) {
            $user = row("SELECT * FROM users WHERE id = ? AND is_active = 1", [$tfa['user']['id']]);
            if (!$user) {
                unset($_SESSION['pending_2fa']);
                $error = 'Account not found or deactivated. Please contact support.';
            } else {
                unset($_SESSION['pending_2fa']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['user']    = $user;
                execute("UPDATE users SET updated_at = NOW() WHERE id = ?", [$user['id']]);
                audit('login', 'users', $user['id'], 'User logged in (2FA)');
                header('Location: ' . dashboardUrl()); exit;
            }
        } else {
            $_SESSION['pending_2fa']['tries']++;
            $left = 5 - $_SESSION['pending_2fa']['tries'];
            if ($left <= 0) {
                unset($_SESSION['pending_2fa']);
                $error = 'Too many failed attempts. Please sign in again.';
            } else {
                $otp_error = "Incorrect code. $left attempt(s) left.";
            }
        }
    }
}

/* ── STEP 1: Login form ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['pending_2fa']) && empty($error)) {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email && $pass) {
        $user = row("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if ($user && password_verify($pass, $user['password'])) {
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['pending_2fa'] = [
                'user'  => $user,
                'otp'   => $otp,
                'exp'   => time() + 600,
                'tries' => 0,
                'phone' => $user['phone'],
            ];
            sendSMS($user['phone'],
                "DMC Hospital\nYour login code: $otp\nValid 10 min. Do NOT share. KK 541 St, Kigali.");
            header('Location: /dmc/index.php'); exit;
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

/* ── OTP screen helpers ── */
$maskedPhone  = '';
$otpRemaining = 0;
if (isset($_SESSION['pending_2fa'])) {
    $rawPhone    = $_SESSION['pending_2fa']['phone'];
    $d           = preg_replace('/[^0-9]/', '', preg_replace('/^\+?250/', '', $rawPhone));
    $maskedPhone = substr($d, 0, 3) . '****' . substr($d, -3);
    $otpRemaining = max(0, $_SESSION['pending_2fa']['exp'] - time());
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
.input-icon{position:relative}
.input-icon i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#adb5bd;font-size:15px;pointer-events:none}
.input-icon .form-control{padding-left:38px}
.otp-shield{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#e8f4fd,#d0e8ff);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:28px;color:#1A6BB5}
.otp-inputs{display:flex;gap:8px;justify-content:center;margin:1.5rem 0}
.otp-digit{width:46px;height:54px;border:2px solid #dee2e6;border-radius:10px;text-align:center;font-size:22px;font-weight:700;color:#0A2342;transition:border-color .18s,box-shadow .18s;outline:none;background:#fff}
.otp-digit:focus{border-color:#1A6BB5;box-shadow:0 0 0 3px rgba(26,107,181,.12)}
.otp-digit.filled{border-color:#0E6655;background:#f0fff8}
.otp-countdown{font-size:12px;color:#999;text-align:center;margin-top:.25rem}
.btn-resend{background:none;border:none;color:#1A6BB5;font-size:13px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;padding:0;text-decoration:underline}
.btn-resend:hover{opacity:.7}
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
        <li><i class="bi bi-check-circle-fill"></i> Patient records &amp; electronic medical history</li>
        <li><i class="bi bi-check-circle-fill"></i> Smart appointment scheduling</li>
        <li><i class="bi bi-check-circle-fill"></i> Pharmacy &amp; lab management</li>
        <li><i class="bi bi-check-circle-fill"></i> MoMo &amp; card billing (Flutterwave)</li>
        <li><i class="bi bi-check-circle-fill"></i> SMS/WhatsApp notifications</li>
        <li><i class="bi bi-check-circle-fill"></i> Real-time reports &amp; analytics</li>
      </ul>
    </div>
    <div class="address-block">
      <i class="bi bi-geo-alt me-1"></i> KK 541 Street, Kigali &nbsp;|&nbsp;
      <i class="bi bi-telephone me-1"></i> 0782 749 660
    </div>
  </div>

  <div class="login-right d-flex flex-column justify-content-center">

    <?php if (isset($_SESSION['pending_2fa'])): ?>
    <!-- ── OTP step ── -->
    <div class="otp-shield"><i class="bi bi-shield-lock"></i></div>
    <div class="right-title" style="text-align:center">Two-Step Verification</div>
    <div class="right-sub" style="text-align:center">
      A 6-digit code was sent via SMS to<br>
      <strong style="color:#0A2342">+250 <?= e($maskedPhone) ?></strong>
    </div>

    <?php if ($otp_error): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:13px">
      <i class="bi bi-exclamation-circle me-2"></i><?= e($otp_error) ?>
    </div>
    <?php endif; ?>

    <?php if ($otp_message): ?>
    <div class="alert alert-success py-2 mb-3" style="font-size:13px">
      <i class="bi bi-check-circle me-2"></i><?= e($otp_message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
      <input type="hidden" name="otp" id="otpHidden">
      <div class="otp-inputs">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric">
        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric">
      </div>
      <div class="otp-countdown" id="countdown"></div>
      <button type="submit" class="btn-login mt-3">
        <i class="bi bi-shield-check me-2"></i>Verify Code
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="resend_otp">
        <span style="font-size:13px;color:#666">Didn't receive a code?</span>
        <button type="submit" class="btn-resend ms-1">Resend</button>
      </form>
    </div>
    <div style="text-align:center;margin-top:.75rem">
      <a href="/dmc/index.php?back=1" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#0A2342;border:1.5px solid #dee2e6;border-radius:8px;padding:7px 16px;text-decoration:none;transition:background .15s,border-color .15s" onmouseover="this.style.background='#f0f4ff';this.style.borderColor='#1A6BB5'" onmouseout="this.style.background='';this.style.borderColor='#dee2e6'">
        <i class="bi bi-arrow-left"></i>Back to sign in
      </a>
    </div>

    <?php else: ?>
    <!-- ── Login step ── -->
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
    <?php endif; ?>

    <div style="font-size:11px;color:#aaa;text-align:center;margin-top:1.5rem">
      &copy; <?= date('Y') ?> DMC - Dream Medical Center. All rights reserved.
    </div>
  </div>
</div>

<script>
<?php if (isset($_SESSION['pending_2fa'])): ?>
const otpInputs = document.querySelectorAll('.otp-digit');
const otpHidden = document.getElementById('otpHidden');

otpInputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '').slice(-1);
        inp.classList.toggle('filled', inp.value !== '');
        if (inp.value && i < otpInputs.length - 1) otpInputs[i + 1].focus();
        syncHidden();
    });
    inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) {
            otpInputs[i - 1].value = '';
            otpInputs[i - 1].classList.remove('filled');
            otpInputs[i - 1].focus();
            syncHidden();
        }
    });
    inp.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        [...text].forEach((ch, j) => {
            if (otpInputs[j]) { otpInputs[j].value = ch; otpInputs[j].classList.add('filled'); }
        });
        otpInputs[Math.min(text.length, otpInputs.length - 1)].focus();
        syncHidden();
    });
});

function syncHidden() {
    otpHidden.value = [...otpInputs].map(i => i.value).join('');
}

document.getElementById('otpForm').addEventListener('submit', e => {
    syncHidden();
    if (otpHidden.value.length < 6) { e.preventDefault(); otpInputs[0].focus(); }
});

let secs = <?= (int)$otpRemaining ?>;
const cdEl = document.getElementById('countdown');
(function tick() {
    if (secs <= 0) {
        cdEl.textContent = 'Code expired — please request a new one.';
        cdEl.style.color = '#dc3545';
        return;
    }
    const m = Math.floor(secs / 60), s = secs % 60;
    cdEl.textContent = 'Expires in ' + m + ':' + String(s).padStart(2, '0');
    secs--;
    setTimeout(tick, 1000);
})();

const firstEmpty = [...otpInputs].find(i => !i.value);
if (firstEmpty) firstEmpty.focus();

<?php else: ?>
function togglePass() {
    const i = document.getElementById('passInput');
    const e = document.getElementById('eyeBtn');
    if (i.type === 'password') { i.type = 'text'; e.className = 'bi bi-eye-slash'; }
    else { i.type = 'password'; e.className = 'bi bi-eye'; }
}
<?php endif; ?>
</script>
</body>
</html>
