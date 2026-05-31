<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'Doctor Dashboard';
$uid = currentUserId();

$stats = [
    'today'    => scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE()", [$uid]),
    'pending'  => scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='scheduled' AND appointment_date>=CURDATE()", [$uid]),
    'patients' => scalar("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id=?", [$uid]),
    'rx'       => scalar("SELECT COUNT(*) FROM prescriptions WHERE doctor_id=? AND status='pending'", [$uid]),
];

$todayAppts = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.phone, p.patient_no FROM appointments a JOIN patients p ON a.patient_id=p.id WHERE a.doctor_id=? AND a.appointment_date=CURDATE() ORDER BY a.appointment_time", [$uid]);
$recentRecords = rows("SELECT mr.*, CONCAT(p.first_name,' ',p.last_name) AS pname FROM medical_records mr JOIN patients p ON mr.patient_id=p.id WHERE mr.doctor_id=? ORDER BY mr.visit_date DESC LIMIT 5", [$uid]);
$pendingRx = rows("SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname FROM prescriptions pr JOIN patients p ON pr.patient_id=p.id WHERE pr.doctor_id=? AND pr.status='pending' LIMIT 5", [$uid]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Doctor Dashboard</div>
    <div class="page-sub">Dr. <?= e(currentUser()['first_name'].' '.currentUser()['last_name']) ?> · <?= date('l, d F Y') ?></div>
  </div>
  <a href="/dmc/doctor/appointments.php" class="btn-dmc"><i class="bi bi-calendar-plus"></i> View Schedule</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-calendar-day"></i></div><div><div class="stat-label">Today</div><div class="stat-value"><?= $stats['today'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-clock-history"></i></div><div><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-people"></i></div><div><div class="stat-label">My Patients</div><div class="stat-value"><?= $stats['patients'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-prescription2"></i></div><div><div class="stat-label">Pending Rx</div><div class="stat-value"><?= $stats['rx'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Today's Appointments <a href="/dmc/doctor/appointments.php">All →</a></div>
      <?php if ($todayAppts): foreach ($todayAppts as $a): ?>
      <div class="d-flex align-items-center gap-3 p-2 mb-2 rounded" style="background:var(--bg)">
        <div style="font-size:12px;font-weight:700;color:var(--brand2);width:60px"><?= timeF($a['appointment_time']) ?></div>
        <div class="patient-avatar" style="width:36px;height:36px;font-size:13px"><?= strtoupper(substr($a['pname'],0,2)) ?></div>
        <div class="flex-1">
          <div style="font-size:13px;font-weight:600"><?= e($a['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($a['reason'] ?? 'No reason specified') ?></div>
        </div>
        <div class="d-flex gap-1">
          <span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
          <a href="/dmc/doctor/records.php?patient_id=<?= $a['patient_id'] ?>&appt_id=<?= $a['id'] ?>" class="btn btn-sm btn-primary" style="font-size:11px">Attend</a>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-4"><i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No appointments today</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Pending Prescriptions <a href="/dmc/doctor/prescriptions.php">All →</a></div>
      <?php if ($pendingRx): foreach ($pendingRx as $rx): ?>
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background:var(--bg)">
        <div>
          <div style="font-size:12.5px;font-weight:600"><?= e($rx['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($rx['prescription_no']) ?></div>
        </div>
        <span class="badge-status bs-pending">Pending</span>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-2" style="font-size:13px">No pending prescriptions</div>
      <?php endif; ?>
    </div>
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Records <a href="/dmc/doctor/records.php">All →</a></div>
      <?php foreach ($recentRecords as $r): ?>
      <div class="tl-item" style="padding-left:1.25rem;margin-bottom:.8rem">
        <div style="font-size:12.5px;font-weight:600"><?= e($r['pname']) ?></div>
        <div style="font-size:11.5px;color:var(--muted)"><?= dateF($r['visit_date']) ?> · <?= e(substr($r['diagnosis'] ?? 'No diagnosis',0,40)) ?>...</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
