<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin']);
$pageTitle = 'My Medical Records';
$uid = currentUserId();
$patient = row("SELECT * FROM patients WHERE email=(SELECT email FROM users WHERE id=?)", [$uid]);
if (!$patient) $patient = row("SELECT * FROM patients WHERE phone=(SELECT phone FROM users WHERE id=?)", [$uid]);
$pid = $patient['id'] ?? 0;

$records = $pid ? rows("SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) AS dname FROM medical_records mr JOIN users u ON mr.doctor_id=u.id WHERE mr.patient_id=? ORDER BY mr.visit_date DESC", [$pid]) : [];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Medical Records</div><div class="page-sub"><?= count($records) ?> records</div></div>
</div>

<?php foreach ($records as $r): ?>
<div class="dmc-card mb-3">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <div style="font-weight:700;font-size:15px"><?= dateF($r['visit_date']) ?></div>
      <div style="font-size:12px;color:var(--muted)">Dr. <?= e($r['dname']) ?></div>
    </div>
    <?php if ($r['follow_up_date']): ?>
    <div class="text-end">
      <div style="font-size:11px;color:var(--muted)">Follow-up</div>
      <div style="font-size:12.5px;color:var(--brand2)"><?= dateF($r['follow_up_date']) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <div class="row g-3" style="font-size:12.5px">
    <?php if ($r['symptoms']): ?><div class="col-md-6"><strong>Symptoms:</strong><p style="color:var(--muted);margin:4px 0 0"><?= e($r['symptoms']) ?></p></div><?php endif; ?>
    <?php if ($r['diagnosis']): ?><div class="col-md-6"><strong>Diagnosis:</strong><p style="color:var(--muted);margin:4px 0 0"><?= e($r['diagnosis']) ?></p></div><?php endif; ?>
    <?php if ($r['treatment_plan']): ?><div class="col-md-6"><strong>Treatment:</strong><p style="color:var(--muted);margin:4px 0 0"><?= e($r['treatment_plan']) ?></p></div><?php endif; ?>
    <?php if ($r['notes']): ?><div class="col-md-6"><strong>Notes:</strong><p style="color:var(--muted);margin:4px 0 0"><?= e($r['notes']) ?></p></div><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$records): ?>
<div class="dmc-card text-center py-5 text-muted"><i class="bi bi-file-medical" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No medical records yet</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php';
