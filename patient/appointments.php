<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin']);
$pageTitle = 'My Appointments';
$uid = currentUserId();
$patient = row("SELECT * FROM patients WHERE email=(SELECT email FROM users WHERE id=?)", [$uid]);
if (!$patient) $patient = row("SELECT * FROM patients WHERE phone=(SELECT phone FROM users WHERE id=?)", [$uid]);
$pid = $patient['id'] ?? 0;

$appts = $pid ? rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS dname, d.specialization
    FROM appointments a JOIN users u ON a.doctor_id=u.id LEFT JOIN doctors d ON u.id=d.user_id
    WHERE a.patient_id=? ORDER BY a.appointment_date DESC, a.appointment_time DESC", [$pid]) : [];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Appointments</div><div class="page-sub"><?= count($appts) ?> total appointments</div></div>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Date & Time</th><th>Doctor</th><th>Type</th><th>Reason</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($appts as $a): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= dateF($a['appointment_date']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= timeF($a['appointment_time']) ?></div>
        </td>
        <td>
          <div style="font-size:13px">Dr. <?= e($a['dname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($a['specialization']??'General') ?></div>
        </td>
        <td style="font-size:12px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></td>
        <td style="font-size:12px;max-width:200px"><?= $a['reason'] ? e(substr($a['reason'],0,60)) : '—' ?></td>
        <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$appts): ?><tr><td colspan="5" class="text-center text-muted py-4">No appointments yet. Contact reception to book.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
