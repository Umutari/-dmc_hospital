<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['pharmacist']);
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
    header('Location: /dmc/pharmacist/profile.php'); exit;
}

$u = currentUser();

$dispensedToday  = (int)scalar("SELECT COUNT(*) FROM prescriptions WHERE dispensed_by=? AND DATE(dispensed_at)=CURDATE()", [currentUserId()]);
$dispensedMonth  = (int)scalar("SELECT COUNT(*) FROM prescriptions WHERE dispensed_by=? AND MONTH(dispensed_at)=MONTH(NOW())", [currentUserId()]);
$pendingRx       = (int)scalar("SELECT COUNT(*) FROM prescriptions WHERE status='pending'");
$lowStockCount   = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1 AND current_stock<=reorder_level");
$outOfStock      = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1 AND current_stock=0");

$recentDispensed = rows("SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no
    FROM prescriptions pr JOIN patients p ON pr.patient_id=p.id
    WHERE pr.dispensed_by=? AND pr.status='dispensed'
    ORDER BY pr.dispensed_at DESC LIMIT 8", [currentUserId()]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Pharmacist account and dispensing activity</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-bag-check-fill"></i></div><div><div class="stat-label">Dispensed Today</div><div class="stat-value"><?= $dispensedToday ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-bag-fill"></i></div><div><div class="stat-label">Dispensed This Month</div><div class="stat-value"><?= $dispensedMonth ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-prescription2"></i></div><div><div class="stat-label">Pending Rx</div><div class="stat-value" style="color:<?= $pendingRx>0?'var(--warning)':'' ?>"><?= $pendingRx ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="stat-label">Low Stock Items</div><div class="stat-value" style="color:<?= $lowStockCount>0?'var(--danger)':'' ?>"><?= $lowStockCount ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-x-circle-fill"></i></div><div><div class="stat-label">Out of Stock</div><div class="stat-value" style="color:<?= $outOfStock>0?'var(--danger)':'' ?>"><?= $outOfStock ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
          <div style="font-size:12px;color:var(--muted)">Pharmacist</div>
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
        <div class="dmc-card-title mb-0">Recently Dispensed</div>
        <a href="/dmc/pharmacist/prescriptions.php" style="font-size:12px;color:var(--brand)">All prescriptions →</a>
      </div>
      <?php if ($recentDispensed): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Rx No</th><th>Patient</th><th>Dispensed</th></tr></thead>
          <tbody>
          <?php foreach ($recentDispensed as $rx): ?>
          <tr>
            <td style="font-family:monospace;font-size:11px"><?= e($rx['prescription_no']) ?></td>
            <td>
              <div style="font-weight:600"><?= e($rx['pname']) ?></div>
              <div style="font-size:10px;color:var(--muted)"><?= e($rx['patient_no']) ?></div>
            </td>
            <td style="font-size:11px"><?= dtF($rx['dispensed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-bag me-2"></i>No dispensing records yet</div>
      <?php endif; ?>
      <?php if ($lowStockCount > 0 || $outOfStock > 0): ?>
      <div class="mt-3 p-3 rounded" style="background:#fff5f5;border:1px solid #fecaca">
        <div style="font-size:13px;font-weight:600;color:var(--danger);margin-bottom:8px"><i class="bi bi-exclamation-triangle me-1"></i>Stock Alert</div>
        <div style="font-size:12.5px"><?= $outOfStock ?> medicine(s) out of stock &nbsp;|&nbsp; <?= $lowStockCount ?> at or below reorder level.</div>
        <a href="/dmc/pharmacist/stock.php?filter=out_of_stock" class="btn btn-sm btn-outline-danger mt-2" style="font-size:11px">View Stock Status</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
