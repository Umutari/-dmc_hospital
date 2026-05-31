<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['nurse','admin']);
$pageTitle = 'Patients Today';

$todayPatients = rows("SELECT DISTINCT p.*, a.appointment_time, a.status AS appt_status,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON a.doctor_id=u.id
    WHERE a.appointment_date=CURDATE()
    ORDER BY a.appointment_time");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Today's Patients</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <a href="/dmc/nurse/vitals.php" class="btn-dmc"><i class="bi bi-heart-pulse"></i> Record Vitals</a>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($todayPatients as $p): ?>
      <tr>
        <td><strong><?= timeF($p['appointment_time']) ?></strong></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="patient-avatar" style="width:32px;height:32px;font-size:11px"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div>
              <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($p['patient_no']) ?> · <?= e($p['phone']) ?></div>
            </div>
          </div>
        </td>
        <td>Dr. <?= e($p['dname']) ?></td>
        <td><span class="badge-status bs-<?= $p['appt_status'] ?>"><?= ucfirst($p['appt_status']) ?></span></td>
        <td><a href="/dmc/nurse/vitals.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">Vitals</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$todayPatients): ?><tr><td colspan="5" class="text-center text-muted py-4">No patients today</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
