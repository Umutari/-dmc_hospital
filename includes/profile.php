<?php
require_once __DIR__ . '/../config/functions.php';
requireLogin();
$pageTitle = 'My Profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'update') {
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name']  ?? '');
        $phone = trim($_POST['phone']      ?? '');
        if ($fname && $lname) {
            execute("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?",
                [$fname, $lname, $phone, currentUserId()]);
            /* refresh session */
            $u = row("SELECT * FROM users WHERE id=?", [currentUserId()]);
            $_SESSION['user'] = $u;
            flash('main', 'Profile updated successfully.');
        }
    } elseif ($act === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user    = row("SELECT * FROM users WHERE id=?", [currentUserId()]);
        if (!password_verify($current, $user['password'])) {
            flash('main', 'Current password is incorrect.', 'danger');
        } elseif (strlen($new) < 6) {
            flash('main', 'New password must be at least 6 characters.', 'danger');
        } elseif ($new !== $confirm) {
            flash('main', 'Passwords do not match.', 'danger');
        } else {
            execute("UPDATE users SET password=? WHERE id=?", [password_hash($new,PASSWORD_DEFAULT), currentUserId()]);
            flash('main', 'Password changed successfully.');
        }
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

$u = currentUser();
include __DIR__ . '/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Manage your account</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3" style="max-width:800px">
  <div class="col-md-6">
    <div class="dmc-card">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="mb-3">
          <div class="text-center mb-3">
            <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= ucfirst(str_replace('_',' ',currentRole())) ?></div>
          </div>
        </div>
        <div class="mb-3"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="<?= e($u['first_name']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="<?= e($u['last_name']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($u['phone']??'') ?>"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= e($u['email']) ?>" readonly style="background:var(--bg)"></div>
        <button type="submit" class="btn-dmc w-100">Update Profile</button>
      </form>
    </div>
  </div>

  <div class="col-md-6">
    <div class="dmc-card">
      <div class="dmc-card-title">Change Password</div>
      <form method="POST">
        <input type="hidden" name="act" value="password">
        <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required></div>
        <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn-dmc w-100">Change Password</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php';
