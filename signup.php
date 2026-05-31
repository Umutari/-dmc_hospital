<?php
require_once __DIR__ . '/config/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $insurance_provider = trim($_POST['insurance_provider'] ?? '');
    $insurance_number = trim($_POST['insurance_number'] ?? '');

    if (!$email || !$password || !$confirm_password || !$first_name || !$last_name || !$phone || !$dob || !$gender) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $existing = row("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'Email already registered.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $patient_no = generateNo('PAT', 'patients', 'patient_no');

            try {
                execute("INSERT INTO patients (patient_no, first_name, last_name, phone, date_of_birth, gender, address, email, insurance_provider, insurance_number, status)
                         VALUES (?,?,?,?,?,?,?,?,?,?,'active')",
                        [$patient_no, $first_name, $last_name, $phone, $dob, strtolower($gender), $address, $email, $insurance_provider, $insurance_number]);

                execute("INSERT INTO users (first_name, last_name, email, phone, password, role, is_active)
                         VALUES (?,?,?,?,?,'patient',1)",
                        [$first_name, $last_name, $email, $phone, $hashed_password]);

                $success = 'Account created successfully! You can now log in.';
            } catch (Exception $e) {
                file_put_contents(__DIR__.'/tmp_signup_error.log', date('c') . ' ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                $error = 'Error creating account. Please try again.';
            }
        }
    }
}

if (isLoggedIn()) { header('Location: ' . dashboardUrl()); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — DMC Hospital</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sora',sans-serif;background:linear-gradient(135deg,#0A2342 0%,#1B4F72 50%,#0E6655 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.signup-wrap{width:100%;max-width:900px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.3);padding:2.5rem}
.dmc-logo{display:flex;align-items:center;gap:12px;margin-bottom:1.5rem}
.dmc-orb{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0A2342,#1A6BB5);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff}
.dmc-name{font-size:14px;font-weight:700}
.dmc-sub{font-size:10px;opacity:.6;margin-top:2px}
.title{font-size:20px;font-weight:700;color:#0A2342;margin-bottom:.2rem}
.subtitle{font-size:12px;color:#6c757d;margin-bottom:1.5rem;text-align: center;}
.form-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;margin-bottom:4px}
.form-control, .form-select{height:36px;border-radius:6px;border:1.5px solid #dee2e6;font-size:13px;transition:border-color .18s,box-shadow .18s;padding:6px 10px}
.mb-3{margin-bottom:1.2rem}
.form-control:focus, .form-select:focus{border-color:#1A6BB5;box-shadow:0 0 0 3px rgba(26,107,181,.12)}
.btn-signup{width:100%;height:44px;background:linear-gradient(135deg,#0A2342,#1A6BB5);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;transition:opacity .15s,transform .1s}
.btn-signup:hover{opacity:.92;color:#fff;text-decoration:none}
.btn-signup:active{transform:scale(.98)}
.form-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
.form-row.full{grid-template-columns:1fr 1fr}
.login-link{text-align:center;margin-top:1.2rem}
.login-link a{color:#1A6BB5;text-decoration:none;font-weight:600}
.login-link a:hover{text-decoration:underline}
.form-container{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:1.5rem}
.form-box{padding:1.5rem;background:#f9f9f9;border-radius:10px;border:1px solid #eee}
@media(max-width:768px){.form-container{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="signup-wrap">
  <div class="dmc-logo">
    <div class="dmc-orb"><i class="bi bi-hospital"></i></div>
    <div><div class="dmc-name">DMC</div><div class="dmc-sub">Dream Medical Center</div></div>
  </div>

  <div class="subtitle">Sign up as a patient</div>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 mb-3" style="font-size:12px">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success py-2 mb-3" style="font-size:12px">
    <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
  </div>
  <div style="text-align:center;margin-top:1rem">
    <a href="/dmc/index.php" class="btn-signup" style="text-decoration:none">Go to Login</a>
  </div>
  <?php else: ?>

  <form method="POST" novalidate>
    <div class="form-container">
      <!-- LEFT BOX -->
      <div class="form-box">
        <div style="font-weight:700;color:#0A2342;margin-bottom:1rem;font-size:12px">PERSONAL DETAILS</div>

        <div class="mb-3">
          <label class="form-label">First Name *</label>
          <input type="text" name="first_name" class="form-control" placeholder="John"
                 value="<?= e($_POST['first_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Last Name *</label>
          <input type="text" name="last_name" class="form-control" placeholder="Doe"
                 value="<?= e($_POST['last_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com"
                 value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Phone Number *</label>
          <input type="tel" name="phone" class="form-control" placeholder="0782 749 660"
                 value="<?= e($_POST['phone'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Date of Birth *</label>
          <input type="date" name="dob" class="form-control"
                 value="<?= e($_POST['dob'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Gender *</label>
          <select name="gender" class="form-select" required>
            <option value="">Select</option>
            <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>

      <!-- RIGHT BOX -->
      <div class="form-box">
        <div style="font-weight:700;color:#0A2342;margin-bottom:1rem;font-size:12px">INSURANCE & SECURITY</div>

        <div class="mb-3">
          <label class="form-label">Insurance Provider</label>
          <select name="insurance_provider" class="form-select">
            <option value="">No Insurance</option>
            <?php
              $insurances = rows("SELECT name FROM insurance_providers WHERE is_active = 1 ORDER BY name");
              foreach ($insurances as $ins):
                if ($ins['name'] !== 'PRIVATE'):
            ?>
            <option value="<?= e($ins['name']) ?>" <?= ($_POST['insurance_provider'] ?? '') === $ins['name'] ? 'selected' : '' ?>>
              <?= e($ins['name']) ?>
            </option>
            <?php endif; endforeach; ?>
          </select>
          <div style="font-size:10px;color:var(--muted);margin-top:3px">Leave blank if no coverage</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Insurance Policy Number</label>
          <input type="text" name="insurance_number" class="form-control" placeholder="Your policy number"
                 value="<?= e($_POST['insurance_number'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" placeholder="Your address"
                 value="<?= e($_POST['address'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Password *</label>
          <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-signup">
      <i class="bi bi-person-plus me-2"></i>Create Account
    </button>
  </form>

  <div class="login-link">
    Already have an account? <a href="/dmc/index.php">Sign in here</a>
  </div>

  <?php endif; ?>

  <div style="font-size:11px;color:#aaa;text-align:center;margin-top:2rem">
    &copy; <?= date('Y') ?> DMC - Dream Medical Center. All rights reserved.
  </div>
</div>
</body>
</html>
