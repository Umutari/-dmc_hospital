<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor']);
$pageTitle = 'My Profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'update') {
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name']  ?? '');
        $phone = trim($_POST['phone']      ?? '');
        $spec  = trim($_POST['specialization'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        if ($fname && $lname) {
            execute("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?",
                [$fname, $lname, $phone, currentUserId()]);
            execute("UPDATE doctors SET specialization=?, department_id=? WHERE user_id=?",
                [$spec, $deptId ?: null, currentUserId()]);
            $u = row("SELECT * FROM users WHERE id=?", [currentUserId()]);
            $_SESSION['user'] = $u;
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
    header('Location: /dmc/doctor/profile.php'); exit;
}

$u = currentUser();
$doc = row("SELECT d.*, dept.name AS dept_name FROM doctors d LEFT JOIN departments dept ON d.department_id=dept.id WHERE d.user_id=?", [currentUserId()]);
$departments = rows("SELECT * FROM departments ORDER BY name");

/* stats */
$todayAppts  = (int)scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE()", [currentUserId()]);
$monthAppts  = (int)scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND MONTH(appointment_date)=MONTH(NOW()) AND YEAR(appointment_date)=YEAR(NOW())", [currentUserId()]);
$totalPats   = (int)scalar("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id=?", [currentUserId()]);
$totalRx     = (int)scalar("SELECT COUNT(*) FROM prescriptions WHERE doctor_id=?", [currentUserId()]);
$pendingAppts = (int)scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date >= CURDATE() AND status='scheduled'", [currentUserId()]);

/* upcoming appointments */
$upcoming = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no
    FROM appointments a JOIN patients p ON a.patient_id=p.id
    WHERE a.doctor_id=? AND a.appointment_date >= CURDATE() AND a.status='scheduled'
    ORDER BY a.appointment_date, a.appointment_time LIMIT 6", [currentUserId()]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Manage your account and professional details</div></div>
</div>

<?= showFlash('main') ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-calendar-check"></i></div>
      <div><div class="stat-label">Today's Appts</div><div class="stat-value"><?= $todayAppts ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-calendar-month"></i></div>
      <div><div class="stat-label">This Month</div><div class="stat-value"><?= $monthAppts ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-label">Total Patients</div><div class="stat-value"><?= number_format($totalPats) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-prescription2"></i></div>
      <div><div class="stat-label">Prescriptions</div><div class="stat-value"><?= number_format($totalRx) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="bi bi-clock-history"></i></div>
      <div><div class="stat-label">Upcoming</div><div class="stat-value"><?= $pendingAppts ?></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <!-- Personal info -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px">
            <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
          </div>
          <div style="font-size:13px;font-weight:600">Dr. <?= e($u['first_name'].' '.$u['last_name']) ?></div>
          <?php if ($doc && $doc['specialization']): ?>
          <div style="font-size:11px;color:var(--muted)"><?= e($doc['specialization']) ?></div>
          <?php endif; ?>
          <?php if ($doc && $doc['dept_name']): ?>
          <div style="font-size:11px;color:var(--brand)"><?= e($doc['dept_name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="mb-2"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="<?= e($u['first_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="<?= e($u['last_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($u['phone']??'') ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" readonly style="background:var(--bg)"></div>
        <div class="mb-2">
          <label class="form-label">Specialization</label>
          <input name="specialization" class="form-control" value="<?= e($doc['specialization']??'') ?>" placeholder="e.g. Cardiology">
        </div>
        <div class="mb-3">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select">
            <option value="">Select...</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($doc['department_id']??'')==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-dmc w-100">Update Profile</button>
      </form>
    </div>

    <!-- Change password -->
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
    <!-- Upcoming appointments -->
    <div class="dmc-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="dmc-card-title mb-0">Upcoming Appointments</div>
        <a href="/dmc/doctor/appointments.php" style="font-size:12px;color:var(--brand)">View all →</a>
      </div>
      <?php if ($upcoming): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($upcoming as $a): ?>
          <tr>
            <td><?= dateF($a['appointment_date']) ?></td>
            <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
            <td>
              <div style="font-weight:600"><?= e($a['pname']) ?></div>
              <div style="font-size:10px;color:var(--muted)"><?= e($a['patient_no']) ?></div>
            </td>
            <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-calendar-x me-2"></i>No upcoming appointments</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
