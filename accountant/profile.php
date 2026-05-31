<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant']);
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
            $_SESSION['user'] = row("SELECT * FROM users WHERE id=?", [currentUserId()]);
            flash('main', 'Profile updated successfully.');
        }
    } elseif ($act === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user = row("SELECT * FROM users WHERE id=?", [currentUserId()]);
        if (!password_verify($current, $user['password'])) {
            flash('main', 'Current password is incorrect.', 'danger');
        } elseif (strlen($new) < 6) {
            flash('main', 'Password must be at least 6 characters.', 'danger');
        } elseif ($new !== $confirm) {
            flash('main', 'Passwords do not match.', 'danger');
        } else {
            execute("UPDATE users SET password=? WHERE id=?", [password_hash($new,PASSWORD_DEFAULT), currentUserId()]);
            flash('main', 'Password changed successfully.');
        }
    }
    header('Location: /dmc/accountant/profile.php'); exit;
}

$u = currentUser();

$revenueToday   = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at)=CURDATE()");
$revenueMonth   = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND MONTH(paid_at)=MONTH(NOW())");
$pendingInvoices= (int)scalar("SELECT COUNT(*) FROM invoices WHERE status IN('draft','issued','partial')");
$outstanding    = (float)scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN('issued','partial')");
$invoicesMonth  = (int)scalar("SELECT COUNT(*) FROM invoices WHERE MONTH(created_at)=MONTH(NOW())");

$recentPayments = rows("SELECT pay.*, inv.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname
    FROM payments pay JOIN invoices inv ON pay.invoice_id=inv.id JOIN patients p ON pay.patient_id=p.id
    WHERE pay.status='success' ORDER BY pay.paid_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Accountant account and financial activity</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div><div><div class="stat-label">Revenue Today</div><div class="stat-value" style="font-size:13px"><?= money($revenueToday) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-graph-up-arrow"></i></div><div><div class="stat-label">Revenue This Month</div><div class="stat-value" style="font-size:12px"><?= money($revenueMonth) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Invoices (month)</div><div class="stat-value"><?= $invoicesMonth ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-clock-history"></i></div><div><div class="stat-label">Pending Invoices</div><div class="stat-value"><?= $pendingInvoices ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-exclamation-circle"></i></div><div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:13px;color:<?= $outstanding>0?'var(--danger)':'' ?>"><?= money($outstanding) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
          <div style="font-size:12px;color:var(--muted)">Accountant</div>
        </div>
        <div class="mb-2"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="<?= e($u['first_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="<?= e($u['last_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($u['phone']??'') ?>"></div>
        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" readonly style="background:var(--bg)"></div>
        <button type="submit" class="btn-dmc w-100">Update Profile</button>
      </form>
    </div>
    <div class="dmc-card">
      <div class="dmc-card-title">Change Password</div>
      <form method="POST">
        <input type="hidden" name="act" value="password">
        <div class="mb-2"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required></div>
        <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn-dmc w-100">Change Password</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="dmc-card-title mb-0">Recent Payments</div>
        <a href="/dmc/accountant/payments.php" style="font-size:12px;color:var(--brand)">All payments →</a>
      </div>
      <?php if ($recentPayments): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Ref</th><th>Patient</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recentPayments as $pay): ?>
          <tr>
            <td style="font-family:monospace;font-size:11px"><?= e($pay['payment_no']) ?></td>
            <td><?= e($pay['pname']) ?></td>
            <td style="font-family:monospace;font-size:11px"><?= e($pay['invoice_no']) ?></td>
            <td style="font-weight:600"><?= money($pay['amount']) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$pay['method'])) ?></td>
            <td style="font-size:11px"><?= dtF($pay['paid_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-credit-card me-2"></i>No recent payments</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
