<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
$pageTitle = 'Patients';

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $like   = "%$search%";
    $where  = "WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ?";
    $params = [$like, $like, $like, $like];
}

$patients = rows("SELECT p.*, (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id) AS appt_count FROM patients p $where ORDER BY p.created_at DESC LIMIT 80", $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Patients</div><div class="page-sub"><?= number_format(scalar("SELECT COUNT(*) FROM patients")) ?> registered patients</div></div>
  <a href="/dmc/receptionist/register_patient.php" class="btn-dmc"><i class="bi bi-person-plus"></i> Register New</a>
</div>

<?= showFlash('main') ?>

<div class="dmc-card">
  <form class="d-flex gap-2 mb-3" method="GET">
    <input name="q" class="form-control" placeholder="Search by name, patient no, or phone..." value="<?= e($search) ?>" style="max-width:380px">
    <button type="submit" class="btn-dmc"><i class="bi bi-search"></i></button>
    <?php if ($search): ?><a href="/dmc/receptionist/patients.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Patient No</th><th>Name</th><th>Phone</th><th>Gender</th><th>Blood Grp</th><th>Insurance</th><th>Appts</th><th>Balance</th><th>Registered</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($patients as $p): ?>
      <tr>
        <td style="font-size:11px;font-family:monospace;font-weight:600"><?= e($p['patient_no']) ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="patient-avatar" style="width:32px;height:32px;font-size:11px"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div>
              <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
              <?php if ($p['date_of_birth']): ?><div style="font-size:11px;color:var(--muted)"><?= age($p['date_of_birth']) ?> yrs</div><?php endif; ?>
            </div>
          </div>
        </td>
        <td><?= e($p['phone']) ?></td>
        <td><?= ucfirst($p['gender']??'—') ?></td>
        <td><?= e($p['blood_group']??'—') ?></td>
        <td style="font-size:12px"><?= $p['insurance_provider'] ? e($p['insurance_provider']) : '<span style="color:var(--muted)">None</span>' ?></td>
        <td><?= $p['appt_count'] ?></td>
        <td>
          <?php $bal = (float)($p['balance'] ?? 0); ?>
          <?php if ($bal > 0): ?>
            <span style="color:var(--danger);font-weight:700;font-size:12px"><?= money($bal) ?></span>
          <?php else: ?>
            <span style="color:var(--success);font-size:12px"><i class="bi bi-check2-circle"></i> Clear</span>
          <?php endif; ?>
        </td>
        <td style="font-size:11px"><?= dateF($p['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/dmc/receptionist/book_appointment.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:10px">Book</a>
            <a href="/dmc/receptionist/patient_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" style="font-size:10px">View</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$patients): ?>
      <tr><td colspan="9" class="text-center text-muted py-4">No patients found<?= $search ? " for \"$search\"" : '' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
