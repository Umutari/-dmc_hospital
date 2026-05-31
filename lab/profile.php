<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['lab_technician']);
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
    header('Location: /dmc/lab/profile.php'); exit;
}

$u = currentUser();

$completedToday = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status='completed' AND DATE(updated_at)=CURDATE()");
$completedMonth = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status='completed' AND MONTH(updated_at)=MONTH(NOW())");
$pendingOrders  = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status IN('pending','in_progress')");
$totalTests     = (int)scalar("SELECT COUNT(*) FROM lab_order_items WHERE status='completed'");
$inProgress     = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status='in_progress'");

$pendingList = rows("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    CONCAT(u.first_name,' ',u.last_name) AS dname,
    (SELECT COUNT(*) FROM lab_order_items WHERE lab_order_id=lo.id AND status='pending') AS pending_items
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id=p.id
    JOIN users u ON lo.doctor_id=u.id
    WHERE lo.status IN('pending','in_progress')
    ORDER BY lo.created_at ASC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Lab technician account and test queue</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-label">Completed Today</div><div class="stat-value"><?= $completedToday ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-flask-fill"></i></div><div><div class="stat-label">Completed (month)</div><div class="stat-value"><?= $completedMonth ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-label">Pending Orders</div><div class="stat-value" style="color:<?= $pendingOrders>0?'var(--warning)':'' ?>"><?= $pendingOrders ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-activity"></i></div><div><div class="stat-label">In Progress</div><div class="stat-value"><?= $inProgress ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-teal"><i class="bi bi-clipboard-data"></i></div><div><div class="stat-label">Tests Completed</div><div class="stat-value"><?= number_format($totalTests) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
          <div style="font-size:12px;color:var(--muted)">Lab Technician</div>
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
        <div class="dmc-card-title mb-0">Pending Test Orders</div>
        <a href="/dmc/lab/orders.php?status=pending" style="font-size:12px;color:var(--brand)">All orders →</a>
      </div>
      <?php if ($pendingList): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Order No</th><th>Patient</th><th>Doctor</th><th>Pending Tests</th><th>Ordered</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($pendingList as $lo): ?>
          <tr>
            <td style="font-family:monospace;font-size:11px"><?= e($lo['order_no']) ?></td>
            <td>
              <div style="font-weight:600"><?= e($lo['pname']) ?></div>
              <div style="font-size:10px;color:var(--muted)"><?= e($lo['patient_no']) ?></div>
            </td>
            <td>Dr. <?= e($lo['dname']) ?></td>
            <td><span class="badge bg-warning text-dark"><?= $lo['pending_items'] ?> pending</span></td>
            <td style="font-size:11px"><?= dtF($lo['created_at']) ?></td>
            <td><a href="/dmc/lab/orders.php?id=<?= $lo['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">Process</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-check-all me-2"></i>No pending orders — all clear!</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
