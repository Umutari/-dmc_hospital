<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin','receptionist']);
$pageTitle = 'All Patients';

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $like  = "%$search%";
    $where = "WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ? OR p.email LIKE ?";
    $params = [$like,$like,$like,$like,$like];
}

$total    = scalar("SELECT COUNT(*) FROM patients");
$patients = rows("SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS registered_by_name
    FROM patients p
    LEFT JOIN users u ON p.registered_by=u.id
    $where
    ORDER BY p.created_at DESC LIMIT 100", $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">All Patients</div><div class="page-sub"><?= number_format($total) ?> registered patients</div></div>
  <a href="/dmc/receptionist/register_patient.php" class="btn-dmc"><i class="bi bi-person-plus"></i> Register New Patient</a>
</div>

<?= showFlash('main') ?>

<div class="dmc-card">
  <form class="d-flex gap-2 mb-3" method="GET">
    <input name="q" class="form-control" placeholder="Search name, patient no, phone, or email..." value="<?= e($search) ?>" style="max-width:420px">
    <button type="submit" class="btn-dmc"><i class="bi bi-search"></i> Search</button>
    <?php if ($search): ?><a href="/dmc/admin/patients.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Patient No</th><th>Name</th><th>Phone</th><th>Email</th><th>Gender</th><th>Age</th><th>Insurance</th><th>Registered</th><th>By</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($patients as $p): ?>
      <tr>
        <td style="font-family:monospace;font-weight:600;font-size:11px"><?= e($p['patient_no']) ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="patient-avatar" style="width:30px;height:30px;font-size:10px"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
          </div>
        </td>
        <td><?= e($p['phone']) ?></td>
        <td style="font-size:12px"><?= $p['email'] ? e($p['email']) : '—' ?></td>
        <td><?= ucfirst($p['gender']??'—') ?></td>
        <td><?= $p['date_of_birth'] ? age($p['date_of_birth']).' yrs' : '—' ?></td>
        <td style="font-size:12px"><?= $p['insurance_provider'] ? e($p['insurance_provider']) : '<span style="color:var(--muted)">None</span>' ?></td>
        <td style="font-size:11px"><?= dateF($p['created_at']) ?></td>
        <td style="font-size:12px"><?= e($p['registered_by_name']??'System') ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/dmc/receptionist/book_appointment.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:10px">Book</a>
            <a href="/dmc/accountant/invoices.php?action=new&patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" style="font-size:10px">Invoice</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$patients): ?>
      <tr><td colspan="10" class="text-center text-muted py-4">No patients found<?= $search?" for \"$search\"":'' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
