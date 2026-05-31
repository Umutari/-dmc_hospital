<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
$pageTitle = 'Register Patient';

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = ['first_name','last_name','date_of_birth','gender','blood_group','phone','email','address','emergency_contact_name','emergency_contact_phone','insurance_provider','insurance_number'];
    $d = [];
    foreach ($f as $k) $d[$k] = trim($_POST[$k] ?? '');
    if (!$d['first_name'] || !$d['last_name'] || !$d['phone']) {
        $error = 'First name, last name and phone are required.';
    } else {
        $exists = scalar("SELECT COUNT(*) FROM patients WHERE phone = ?", [$d['phone']]);
        if ($exists) { $error = 'A patient with this phone number already exists.'; }
        else {
            $no = generateNo('DMC-P', 'patients', 'patient_no');
            execute("INSERT INTO patients (patient_no,first_name,last_name,date_of_birth,gender,blood_group,phone,email,address,emergency_contact_name,emergency_contact_phone,insurance_provider,insurance_number,registered_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$no, $d['first_name'], $d['last_name'], $d['date_of_birth']?:null, $d['gender'], $d['blood_group'], $d['phone'], $d['email'], $d['address'], $d['emergency_contact_name'], $d['emergency_contact_phone'], $d['insurance_provider'], $d['insurance_number'], currentUserId()]);
            /* send welcome SMS */
            sendSMS($d['phone'], "Welcome to DMC Hospital, {$d['first_name']}! Your patient ID is $no. Address: KK 541 St, Kigali. Tel: 0782 749 660");
            audit('register_patient', 'patients', 0, "Registered patient $no");
            flash('main', "Patient {$d['first_name']} {$d['last_name']} registered successfully. ID: $no");
            header('Location: /dmc/receptionist/patients.php'); exit;
        }
    }
}

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Register New Patient</div><div class="page-sub">Complete the form below to register a new patient</div></div>
  <a href="/dmc/receptionist/patients.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>

<form method="POST" class="row g-3">
  <div class="col-12">
    <div class="dmc-card">
      <div class="dmc-card-title">Personal Information</div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">First Name *</label><input name="first_name" class="form-control" value="<?= e($_POST['first_name']??'') ?>" required></div>
        <div class="col-md-4"><label class="form-label">Last Name *</label><input name="last_name" class="form-control" value="<?= e($_POST['last_name']??'') ?>" required></div>
        <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= e($_POST['date_of_birth']??'') ?>"></div>
        <div class="col-md-4">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="">Select...</option>
            <option value="male" <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
            <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
            <option value="other" <?= ($_POST['gender']??'')==='other'?'selected':'' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Blood Group</label>
          <select name="blood_group" class="form-select">
            <option value="">Unknown</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
            <option value="<?= $bg ?>" <?= ($_POST['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label">Phone *</label><input name="phone" class="form-control" placeholder="07XXXXXXXX" value="<?= e($_POST['phone']??'') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email']??'') ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($_POST['address']??'') ?>"></div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="dmc-card">
      <div class="dmc-card-title">Emergency Contact</div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Contact Name</label><input name="emergency_contact_name" class="form-control" value="<?= e($_POST['emergency_contact_name']??'') ?>"></div>
        <div class="col-12"><label class="form-label">Contact Phone</label><input name="emergency_contact_phone" class="form-control" value="<?= e($_POST['emergency_contact_phone']??'') ?>"></div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-shield-check me-2"></i>Insurance Information</div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Insurance Provider</label>
          <select name="insurance_provider" class="form-select">
            <option value="">No Insurance</option>
            <?php
              $insurances = rows("SELECT name, coverage_percentage FROM insurance_providers WHERE is_active = 1 ORDER BY coverage_percentage DESC");
              foreach ($insurances as $ins):
                if ($ins['name'] !== 'PRIVATE'):
            ?>
            <option value="<?= e($ins['name']) ?>" <?= ($_POST['insurance_provider']??'')===$ins['name']?'selected':'' ?>>
              <?= e($ins['name']) ?> (<?= $ins['coverage_percentage'] ?>%)
            </option>
            <?php endif; endforeach; ?>
          </select>
          <div style="font-size:11px;color:var(--muted);margin-top:4px">Coverage % shown in parentheses</div>
        </div>
        <div class="col-12">
          <label class="form-label">Insurance Policy Number</label>
          <input name="insurance_number" class="form-control" placeholder="Patient's policy/ID number" value="<?= e($_POST['insurance_number']??'') ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 d-flex gap-2 justify-content-end">
    <a href="/dmc/receptionist/patients.php" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn-dmc"><i class="bi bi-person-check"></i> Register Patient</button>
  </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
