<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin']);
$pageTitle = 'My Health Portal';
$uid = currentUserId();

/* link user to patient record */
$patient = row("SELECT * FROM patients WHERE email=(SELECT email FROM users WHERE id=?)", [$uid]);
if (!$patient) {
    $patient = row("SELECT * FROM patients WHERE phone=(SELECT phone FROM users WHERE id=?)", [$uid]);
}
$pid = $patient['id'] ?? 0;

$stats = [
    'appts'    => $pid ? scalar("SELECT COUNT(*) FROM appointments WHERE patient_id=?", [$pid]) : 0,
    'upcoming' => $pid ? scalar("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status IN('scheduled','confirmed')", [$pid]) : 0,
    'invoices' => $pid ? scalar("SELECT COUNT(*) FROM invoices WHERE patient_id=? AND status NOT IN('paid','cancelled')", [$pid]) : 0,
    'records'  => $pid ? scalar("SELECT COUNT(*) FROM medical_records WHERE patient_id=?", [$pid]) : 0,
];
$patBalance = $patient ? (float)$patient['balance'] : 0;

$upcomingAppts = $pid ? rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS dname, d.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id=u.id
    LEFT JOIN doctors d ON u.id=d.user_id
    WHERE a.patient_id=? AND a.appointment_date>=CURDATE() AND a.status IN('scheduled','confirmed')
    ORDER BY a.appointment_date, a.appointment_time LIMIT 5", [$pid]) : [];

$recentInvoices = $pid ? rows("SELECT * FROM invoices WHERE patient_id=? ORDER BY created_at DESC LIMIT 5", [$pid]) : [];

$recentRecords = $pid ? rows("SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM medical_records mr JOIN users u ON mr.doctor_id=u.id
    WHERE mr.patient_id=? ORDER BY mr.visit_date DESC LIMIT 4", [$pid]) : [];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">My Health Portal</div>
    <div class="page-sub">Welcome, <?= e($patient['first_name'] ?? currentUser()['first_name']) ?>! · DMC Hospital, KK 541 St, Kigali</div>
  </div>
</div>

<?= showFlash('main') ?>

<?php if (!$pid): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Your patient record has not been linked yet. Please contact reception.</div>
<?php endif; ?>

<!-- Balance alert banner -->
<?php if ($patBalance > 0): ?>
<div class="alert alert-danger d-flex align-items-center justify-content-between mb-3" style="border-radius:10px;border:none;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff">
  <div class="d-flex align-items-center gap-3">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:24px"></i>
    <div>
      <div style="font-weight:700;font-size:15px">Outstanding Balance</div>
      <div style="font-size:12px;opacity:.85">You have an unpaid balance. Please settle to avoid service interruption.</div>
    </div>
  </div>
  <div class="text-end">
    <div style="font-size:24px;font-weight:800"><?= money($patBalance) ?></div>
    <a href="/dmc/patient/invoices.php" class="btn btn-sm btn-light mt-1" style="font-size:11px;color:#b91c1c;font-weight:700">Pay Now</a>
  </div>
</div>
<?php else: ?>
<div class="alert d-flex align-items-center gap-3 mb-3" style="border-radius:10px;background:linear-gradient(135deg,#065f46,#047857);color:#fff;border:none">
  <i class="bi bi-check-circle-fill" style="font-size:24px"></i>
  <div>
    <div style="font-weight:700;font-size:15px">Account Clear</div>
    <div style="font-size:12px;opacity:.85">No outstanding balance. Thank you!</div>
  </div>
  <div class="ms-auto text-end">
    <div style="font-size:22px;font-weight:800">RWF 0</div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-calendar-check"></i></div><div><div class="stat-label">My Appointments</div><div class="stat-value"><?= $stats['appts'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-calendar-event"></i></div><div><div class="stat-label">Upcoming</div><div class="stat-value"><?= $stats['upcoming'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Pending Bills</div><div class="stat-value"><?= $stats['invoices'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-file-medical"></i></div><div><div class="stat-label">Medical Records</div><div class="stat-value"><?= $stats['records'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <?php if ($patient): ?>
  <!-- Profile card -->
  <div class="col-lg-4">
    <div class="dmc-card text-center mb-3">
      <div class="patient-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:26px">
        <?= strtoupper(substr($patient['first_name'],0,1).substr($patient['last_name'],0,1)) ?>
      </div>
      <div style="font-size:17px;font-weight:700"><?= e($patient['first_name'].' '.$patient['last_name']) ?></div>
      <div style="font-size:12px;color:var(--muted)"><?= e($patient['patient_no']) ?></div>
      <div class="mt-3 p-3 rounded text-start" style="background:var(--bg);font-size:12.5px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Phone</span><span><?= e($patient['phone']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Gender</span><span><?= ucfirst($patient['gender']??'—') ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Blood Group</span><span><?= e($patient['blood_group']??'Unknown') ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Date of Birth</span><span><?= dateF($patient['date_of_birth']??'') ?></span></div>
        <?php if ($patient['insurance_provider']): ?>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Insurance</span><span><?= e($patient['insurance_provider']) ?></span></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between pt-2 mt-1" style="border-top:1px solid var(--border)">
          <span style="font-weight:600">Account Balance</span>
          <span style="font-weight:700;color:<?= $patBalance>0?'var(--danger)':'var(--success)' ?>">
            <?= $patBalance > 0 ? money($patBalance) : '<i class="bi bi-check2-circle"></i> Clear' ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Pending bills -->
    <?php if ($recentInvoices): ?>
    <div class="dmc-card">
      <div class="dmc-card-title">My Bills</div>
      <?php foreach ($recentInvoices as $inv): ?>
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background:var(--bg);font-size:12.5px">
        <div>
          <div style="font-weight:600"><?= e($inv['invoice_no']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= dateF($inv['created_at']) ?></div>
        </div>
        <div class="text-end">
          <div style="font-weight:700;color:var(--danger)"><?= money($inv['balance']) ?></div>
          <span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="col-lg-<?= $patient ? '8' : '12' ?>">
    <!-- Upcoming appointments -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Upcoming Appointments</div>
      <?php if ($upcomingAppts): foreach ($upcomingAppts as $a): ?>
      <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded" style="background:var(--bg);border-left:3px solid var(--brand2)">
        <div style="text-align:center;min-width:50px">
          <div style="font-size:20px;font-weight:700;color:var(--brand)"><?= date('d', strtotime($a['appointment_date'])) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= date('M Y', strtotime($a['appointment_date'])) ?></div>
        </div>
        <div class="flex-1">
          <div style="font-size:13px;font-weight:600">Dr. <?= e($a['dname']) ?></div>
          <div style="font-size:11.5px;color:var(--muted)"><?= e($a['specialization']??'General') ?> · <?= timeF($a['appointment_time']) ?></div>
          <?php if ($a['reason']): ?><div style="font-size:11px;color:var(--muted)"><?= e($a['reason']) ?></div><?php endif; ?>
        </div>
        <span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:13px"><i class="bi bi-calendar-x me-1"></i>No upcoming appointments</div>
      <?php endif; ?>
    </div>

    <!-- Recent records -->
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Medical Records</div>
      <?php if ($recentRecords): foreach ($recentRecords as $r): ?>
      <div class="p-3 mb-2 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1">
          <strong style="font-size:13px">Dr. <?= e($r['dname']) ?></strong>
          <span style="font-size:11px;color:var(--muted)"><?= dateF($r['visit_date']) ?></span>
        </div>
        <?php if ($r['diagnosis']): ?><div style="font-size:12px;margin-bottom:4px"><strong>Diagnosis:</strong> <?= e(substr($r['diagnosis'],0,100)) ?></div><?php endif; ?>
        <?php if ($r['treatment_plan']): ?><div style="font-size:12px;color:var(--muted)"><?= e(substr($r['treatment_plan'],0,80)) ?>...</div><?php endif; ?>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:13px">No medical records yet</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
