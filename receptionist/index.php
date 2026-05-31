<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
$pageTitle = 'Receptionist Dashboard';

$stats = [
    'today_appts'   => scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()"),
    'waiting'       => scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='scheduled'"),
    'total_patients'=> scalar("SELECT COUNT(*) FROM patients"),
    'new_today'     => scalar("SELECT COUNT(*) FROM patients WHERE DATE(created_at)=CURDATE()"),
];
$todayQueue = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone, CONCAT(u.first_name,' ',u.last_name) AS dname FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON a.doctor_id=u.id WHERE a.appointment_date=CURDATE() ORDER BY a.appointment_time");
$recentPatients = rows("SELECT * FROM patients ORDER BY created_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Reception Dashboard</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <div class="d-flex gap-2">
    <a href="/dmc/receptionist/register_patient.php" class="btn-dmc"><i class="bi bi-person-plus"></i> Register Patient</a>
    <a href="/dmc/receptionist/book_appointment.php" class="btn-dmc-outline"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
  </div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-calendar-check"></i></div><div><div class="stat-label">Today Appointments</div><div class="stat-value"><?= $stats['today_appts'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-label">Waiting</div><div class="stat-value"><?= $stats['waiting'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-people"></i></div><div><div class="stat-label">Total Patients</div><div class="stat-value"><?= number_format($stats['total_patients']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-teal"><i class="bi bi-person-plus"></i></div><div><div class="stat-label">Registered Today</div><div class="stat-value"><?= $stats['new_today'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Today's Queue <a href="/dmc/receptionist/appointments.php">Manage →</a></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ($todayQueue): $i=1; foreach ($todayQueue as $a): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
            <td>
              <div style="font-size:13px;font-weight:600"><?= e($a['pname']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($a['patient_no']) ?></div>
            </td>
            <td>Dr. <?= e($a['dname']) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$a['type'])) ?></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td>
              <a href="/dmc/receptionist/appointments.php?action=confirm&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success" style="font-size:11px">Confirm</a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No appointments today</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Patients <a href="/dmc/receptionist/patients.php">All →</a></div>
      <?php foreach ($recentPatients as $p): ?>
      <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:var(--bg)">
        <div class="patient-avatar" style="width:34px;height:34px;font-size:12px"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
        <div class="flex-1">
          <div style="font-size:12.5px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($p['patient_no']) ?></div>
        </div>
        <a href="/dmc/receptionist/book_appointment.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:10px">Book</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
