<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pageTitle = 'Complete Account Setup';
$uid = currentUserId();
$forced = !empty($_SESSION['user']['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $email   = trim($_POST['email'] ?? '');

    if ($forced && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('main', 'Please enter a valid email address.', 'danger');
    } elseif ($forced && scalar("SELECT COUNT(*) FROM users WHERE email=? AND id<>?", [$email, $uid])) {
        flash('main', 'That email is already in use by another account.', 'danger');
    } elseif (strlen($new) < 6) {
        flash('main', 'Password must be at least 6 characters.', 'danger');
    } elseif ($new !== $confirm) {
        flash('main', 'Passwords do not match.', 'danger');
    } else {
        $user = row("SELECT * FROM users WHERE id=?", [$uid]);
        if ($forced) {
            execute("UPDATE users SET email=?, password=?, must_change_password=0 WHERE id=?",
                [$email, password_hash($new, PASSWORD_DEFAULT), $uid]);
            if ($user['phone']) {
                execute("UPDATE patients SET email=? WHERE phone=?", [$email, $user['phone']]);
            }
        } else {
            execute("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $uid]);
        }
        $_SESSION['user'] = row("SELECT * FROM users WHERE id=?", [$uid]);
        audit('change_password', 'users', $uid, $forced ? 'Email and password set after one-time login' : 'Password changed');
        flash('main', 'Your account has been updated.');
        header('Location: ' . dashboardUrl()); exit;
    }
}

include __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title"><?= $forced ? 'Complete Account Setup' : 'Change Password' ?></div>
    <div class="page-sub"><?= $forced ? 'Set your real email and a new password before continuing.' : 'Update your account password' ?></div>
  </div>
</div>

<?= showFlash('main') ?>

<div class="dmc-card" style="max-width:420px;margin:0 auto">
  <?php if ($forced): ?>
  <div class="alert alert-warning" style="font-size:13px"><i class="bi bi-shield-lock me-2"></i>You logged in with a one-time email and password. Please set your real email and a new password to continue.</div>
  <?php endif; ?>
  <form method="POST">
    <?php if ($forced): ?>
    <div class="mb-3"><label class="form-label">Your Email</label><input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus></div>
    <?php endif; ?>
    <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required <?= $forced ? '' : 'autofocus' ?>></div>
    <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" minlength="6" required></div>
    <button type="submit" class="btn-dmc w-100">Save</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php';
