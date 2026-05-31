<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'My Patients';
$uid = currentUserId();

$patients = rows("SELECT DISTINCT p.*, MAX(a.appointment_date) AS last_visit,
    COUNT(a.id) AS visit_count
    FROM patients p
    JOIN appointments a ON p.id=a.patient_id
    WHERE a.doctor_id=?
    GROUP BY p.id
    ORDER BY last_visit DESC", [$uid]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Patients</div><div class="page-sub"><?= count($patients) ?> patients seen</div></div>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Patient</th><th>Phone</th><th>Age / Gender</th><th>Visits</th><th>Last Visit</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($patients as $p): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="patient-avatar" style="width:34px;height:34px;font-size:12px"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div>
              <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($p['patient_no']) ?></div>
            </div>
          </div>
        </td>
        <td><?= e($p['phone']) ?></td>
        <td><?= $p['date_of_birth']?age($p['date_of_birth']).' yrs':'—' ?>, <?= ucfirst($p['gender']??'—') ?></td>
        <td><?= $p['visit_count'] ?></td>
        <td style="font-size:11px"><?= dateF($p['last_visit']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/dmc/doctor/records.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:10px">Records</a>
            <a href="/dmc/doctor/lab_orders.php" class="btn btn-sm btn-outline-secondary" style="font-size:10px">Lab</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$patients): ?><tr><td colspan="6" class="text-center text-muted py-4">No patients yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
