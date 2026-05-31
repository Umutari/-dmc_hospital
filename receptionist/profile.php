<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist']);
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
    header('Location: /dmc/receptionist/profile.php'); exit;
}

$u = currentUser();

$bookedToday = (int)scalar("SELECT COUNT(*) FROM appointments WHERE booked_by=? AND appointment_date=CURDATE()", [currentUserId()]);
$bookedMonth = (int)scalar("SELECT COUNT(*) FROM appointments WHERE booked_by=? AND MONTH(appointment_date)=MONTH(NOW())", [currentUserId()]);
$patientsReg = (int)scalar("SELECT COUNT(*) FROM patients WHERE MONTH(created_at)=MONTH(NOW())");
$totalBooked = (int)scalar("SELECT COUNT(*) FROM appointments WHERE booked_by=?", [currentUserId()]);

$todayAppts = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON a.doctor_id=u.id
    WHERE a.booked_by=? AND a.appointment_date=CURDATE() ORDER BY a.appointment_time", [currentUserId()]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Receptionist account and activity</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-calendar-check"></i></div><div><div class="stat-label">Booked Today</div><div class="stat-value"><?= $bookedToday ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-calendar-month"></i></div><div><div class="stat-label">Booked This Month</div><div class="stat-value"><?= $bookedMonth ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-person-plus-fill"></i></div><div><div class="stat-label">New Patients (month)</div><div class="stat-value"><?= $patientsReg ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-teal"><i class="bi bi-calendar-event"></i></div><div><div class="stat-label">Total Booked</div><div class="stat-value"><?= number_format($totalBooked) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
          <div style="font-size:12px;color:var(--muted)">Receptionist</div>
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
        <div class="dmc-card-title mb-0">Today's Appointments I Booked</div>
        <a href="/dmc/receptionist/appointments.php" style="font-size:12px;color:var(--brand)">All appointments →</a>
      </div>
      <?php if ($todayAppts): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($todayAppts as $a): ?>
          <tr>
            <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
            <td><?= e($a['pname']) ?></td>
            <td>Dr. <?= e($a['dname']) ?></td>
            <td><span class="badge bg-light text-dark" style="font-size:10px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-calendar-x me-2"></i>No appointments booked by you today</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
