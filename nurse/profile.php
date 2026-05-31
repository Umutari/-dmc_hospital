<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['nurse']);
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
    header('Location: /dmc/nurse/profile.php'); exit;
}

$u = currentUser();

/* stats */
$activeAdmissions = (int)scalar("SELECT COUNT(*) FROM admissions WHERE status='active'");
$vitalsToday      = (int)scalar("SELECT COUNT(*) FROM vital_signs WHERE DATE(recorded_at)=CURDATE()");
$vitalsMonth      = (int)scalar("SELECT COUNT(*) FROM vital_signs WHERE MONTH(recorded_at)=MONTH(NOW()) AND YEAR(recorded_at)=YEAR(NOW())");
$discharged       = (int)scalar("SELECT COUNT(*) FROM admissions WHERE status='discharged' AND MONTH(updated_at)=MONTH(NOW())");

/* current active admissions */
$admissions = rows("SELECT adm.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    r.room_number, rt.name AS room_type
    FROM admissions adm
    JOIN patients p ON adm.patient_id=p.id
    LEFT JOIN rooms r ON adm.room_id=r.id
    LEFT JOIN room_types rt ON r.room_type_id=rt.id
    WHERE adm.status='active'
    ORDER BY adm.admitted_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Profile</div><div class="page-sub">Nurse account and ward overview</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-door-open-fill"></i></div>
      <div><div class="stat-label">Active Admissions</div><div class="stat-value"><?= $activeAdmissions ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-heart-pulse-fill"></i></div>
      <div><div class="stat-label">Vitals Today</div><div class="stat-value"><?= $vitalsToday ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-activity"></i></div>
      <div><div class="stat-label">Vitals This Month</div><div class="stat-value"><?= $vitalsMonth ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="bi bi-box-arrow-right"></i></div>
      <div><div class="stat-label">Discharged (month)</div><div class="stat-value"><?= $discharged ?></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Personal Information</div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <div class="text-center mb-3">
          <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px">
            <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
          </div>
          <div style="font-size:12px;color:var(--muted)">Nurse</div>
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
        <div class="dmc-card-title mb-0">Currently Admitted Patients</div>
        <a href="/dmc/nurse/admissions.php" style="font-size:12px;color:var(--brand)">View all →</a>
      </div>
      <?php if ($admissions): ?>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Patient</th><th>Room</th><th>Admitted</th><th>Days</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($admissions as $adm): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= e($adm['pname']) ?></div>
              <div style="font-size:10px;color:var(--muted)"><?= e($adm['patient_no']) ?></div>
            </td>
            <td><?= e($adm['room_number']??'—') ?> <?php if ($adm['room_type']): ?><span style="font-size:10px;color:var(--muted)">(<?= e($adm['room_type']) ?>)</span><?php endif; ?></td>
            <td style="font-size:11px"><?= dateF($adm['admitted_at']) ?></td>
            <td><span class="badge bg-light text-dark"><?= (int)floor((time()-strtotime($adm['admitted_at']))/86400) ?>d</span></td>
            <td><a href="/dmc/nurse/vitals.php?patient_id=<?= $adm['patient_id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">Vitals</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-door-open me-2"></i>No active admissions</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
