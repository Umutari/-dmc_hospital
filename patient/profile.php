<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient']);
$pageTitle = 'My Profile';

$uid = currentUserId();
$patient = row("SELECT * FROM patients WHERE email=(SELECT email FROM users WHERE id=?)", [$uid]);
if (!$patient) {
    $patient = row("SELECT * FROM patients WHERE phone=(SELECT phone FROM users WHERE id=?)", [$uid]);
}
$pid = $patient['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'update') {
        $fname  = trim($_POST['first_name'] ?? '');
        $lname  = trim($_POST['last_name']  ?? '');
        $phone  = trim($_POST['phone']      ?? '');
        $addr   = trim($_POST['address']    ?? '');
        $emcName= trim($_POST['emergency_contact_name']  ?? '');
        $emcPh  = trim($_POST['emergency_contact_phone'] ?? '');
        if ($fname && $lname) {
            execute("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?",
                [$fname, $lname, $phone, $uid]);
            if ($pid) {
                execute("UPDATE patients SET first_name=?, last_name=?, phone=?, address=?,
                    emergency_contact_name=?, emergency_contact_phone=? WHERE id=?",
                    [$fname, $lname, $phone, $addr, $emcName, $emcPh, $pid]);
            }
            $_SESSION['user'] = row("SELECT * FROM users WHERE id=?", [$uid]);
            flash('main', 'Profile updated successfully.');
        }
    } elseif ($act === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user = row("SELECT * FROM users WHERE id=?", [$uid]);
        if (!password_verify($current, $user['password'])) {
            flash('main', 'Current password is incorrect.', 'danger');
        } elseif (strlen($new) < 6) {
            flash('main', 'Password must be at least 6 characters.', 'danger');
        } elseif ($new !== $confirm) {
            flash('main', 'Passwords do not match.', 'danger');
        } else {
            execute("UPDATE users SET password=? WHERE id=?", [password_hash($new,PASSWORD_DEFAULT), $uid]);
            flash('main', 'Password changed successfully.');
        }
    }
    header('Location: /dmc/patient/profile.php'); exit;
}

$u = currentUser();

/* latest vitals */
$vitals = $pid ? row("SELECT * FROM vital_signs WHERE patient_id=? ORDER BY recorded_at DESC LIMIT 1", [$pid]) : null;

/* recent appointments */
$recentAppts = $pid ? rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM appointments a JOIN users u ON a.doctor_id=u.id
    WHERE a.patient_id=? ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5", [$pid]) : [];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Personal and health information</div></div>
  <a href="/dmc/patient/index.php" class="btn-dmc-outline"><i class="bi bi-house"></i> Dashboard</a>
</div>

<?= showFlash('main') ?>

<?php if (!$pid): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Your patient record is not yet linked. Contact reception.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <!-- Personal info form -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px">
            <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
          </div>
          <?php if ($patient): ?>
          <div style="font-size:11px;color:var(--muted)"><?= e($patient['patient_no']) ?></div>
          <?php endif; ?>
        </div>
        <div class="mb-2"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="<?= e($patient['first_name']??$u['first_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="<?= e($patient['last_name']??$u['last_name']) ?>" required></div>
        <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($patient['phone']??$u['phone']??'') ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" readonly style="background:var(--bg)"></div>
        <?php if ($patient): ?>
        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($patient['address']??'') ?></textarea></div>
        <div class="mb-2"><label class="form-label">Emergency Contact Name</label><input name="emergency_contact_name" class="form-control" value="<?= e($patient['emergency_contact_name']??'') ?>"></div>
        <div class="mb-3"><label class="form-label">Emergency Contact Phone</label><input name="emergency_contact_phone" class="form-control" value="<?= e($patient['emergency_contact_phone']??'') ?>"></div>
        <?php endif; ?>
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
    <?php if ($patient): ?>
    <!-- Account Balance -->
    <div class="dmc-card mb-3" style="background:linear-gradient(135deg,#0A2342,#1A6BB5);color:#fff;border:none">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85">Account Balance</div>
          <div style="font-size:32px;font-weight:700;margin-top:8px">
            <?php
              $balance = (float)($patient['balance'] ?? 0);
              $balanceColor = $balance > 0 ? '#52C3A0' : ($balance < 0 ? '#FF6B6B' : '#fff');
              $balanceSign = $balance >= 0 ? '' : '−';
              echo $balanceSign . money(abs($balance));
            ?>
          </div>
          <div style="font-size:12px;opacity:.8;margin-top:4px">
            <?php
              if ($balance > 0) echo 'Credit Balance';
              elseif ($balance < 0) echo 'Amount Due';
              else echo 'Paid Up';
            ?>
          </div>
        </div>
        <div style="text-align:right">
          <i class="bi bi-wallet2" style="font-size:32px;opacity:.4"></i>
        </div>
      </div>
    </div>

    <!-- Patient Details & Health Summary -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Patient Information</div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 rounded" style="background:var(--bg);font-size:13px">
            <div style="font-weight:600;font-size:12px;color:var(--muted);margin-bottom:10px">PERSONAL DETAILS</div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Patient No</span><strong><?= e($patient['patient_no']) ?></strong></div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Gender</span><span><?= ucfirst($patient['gender']??'—') ?></span></div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Date of Birth</span><span><?= $patient['date_of_birth'] ? dateF($patient['date_of_birth']).' ('.age($patient['date_of_birth']).' yrs)' : '—' ?></span></div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Blood Group</span><span style="font-weight:700;color:var(--danger)"><?= e($patient['blood_group']??'Unknown') ?></span></div>
            <?php if ($patient['email']): ?>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Email</span><span style="word-break:break-word"><?= e($patient['email']) ?></span></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span style="color:var(--muted)">Status</span><span class="badge bg-success" style="font-size:10px"><?= ucfirst($patient['status']??'Active') ?></span></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="p-3 rounded" style="background:var(--bg);font-size:13px">
            <div style="font-weight:600;font-size:12px;color:var(--muted);margin-bottom:10px">CONTACT & INSURANCE</div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Phone</span><strong><?= e($patient['phone']??'—') ?></strong></div>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Address</span><span><?= e($patient['address']??'—') ?></span></div>
            <?php if ($patient['insurance_provider']): ?>
            <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Insurance</span><span><?= e($patient['insurance_provider']) ?></span></div>
            <div class="d-flex justify-content-between"><span style="color:var(--muted)">Policy No</span><span><?= e($patient['insurance_number']??'—') ?></span></div>
            <?php else: ?>
            <div class="d-flex justify-content-between"><span style="color:var(--muted)">Insurance</span><span>—</span></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($patient['emergency_contact_name']): ?>
      <div class="mt-3 p-3 rounded" style="background:var(--bg);font-size:13px">
        <div style="font-weight:600;font-size:12px;color:var(--muted);margin-bottom:8px">EMERGENCY CONTACT</div>
        <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Name</span><strong><?= e($patient['emergency_contact_name']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Phone</span><strong><?= e($patient['emergency_contact_phone']??'—') ?></strong></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Health Summary -->
    <?php if ($vitals): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Latest Vitals</div>
      <div style="background:var(--bg);padding:1rem;border-radius:8px;font-size:13px">
        <div style="font-size:12px;color:var(--muted);margin-bottom:12px;font-weight:600">Recorded on <?= dateF($vitals['recorded_at']) ?></div>
        <div class="row g-2">
          <?php if ($vitals['blood_pressure_sys']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">Blood Pressure</span><strong><?= e($vitals['blood_pressure_sys'].'/'.$vitals['blood_pressure_dia']) ?> mmHg</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['pulse_rate']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">Pulse Rate</span><strong><?= e($vitals['pulse_rate']) ?> bpm</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['temperature']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">Temperature</span><strong><?= e($vitals['temperature']) ?> °C</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['weight']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">Weight</span><strong><?= e($vitals['weight']) ?> kg</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['oxygen_saturation']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">SpO₂</span><strong><?= e($vitals['oxygen_saturation']) ?>%</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['height']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">Height</span><strong><?= e($vitals['height']) ?> cm</strong></div></div>
          <?php endif; ?>
          <?php if ($vitals['bmi']): ?>
          <div class="col-md-6"><div class="d-flex justify-content-between"><span style="color:var(--muted)">BMI</span><strong><?= e($vitals['bmi']) ?></strong></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Recent appointments -->
    <div class="dmc-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="dmc-card-title mb-0">Recent Appointments</div>
        <a href="/dmc/patient/appointments.php" style="font-size:12px;color:var(--brand)">View all →</a>
      </div>
      <?php if ($recentAppts): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recentAppts as $a): ?>
          <tr>
            <td><?= dateF($a['appointment_date']) ?></td>
            <td><?= timeF($a['appointment_time']) ?></td>
            <td>Dr. <?= e($a['dname']) ?></td>
            <td><span class="badge bg-light text-dark" style="font-size:10px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-calendar-x me-2"></i>No appointments yet</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
