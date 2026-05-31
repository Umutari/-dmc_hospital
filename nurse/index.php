<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['nurse','admin']);
$pageTitle = 'Nurse Dashboard';
$uid = currentUserId();

$stats = [
    'today_patients'  => scalar("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE appointment_date=CURDATE() AND status IN('confirmed','completed')"),
    'vitals_today'    => scalar("SELECT COUNT(*) FROM vital_signs WHERE DATE(recorded_at)=CURDATE()"),
    'admissions'      => scalar("SELECT COUNT(*) FROM admissions WHERE status='active'"),
    'pending_appts'   => scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='scheduled'"),
];

$todayQueue = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON a.doctor_id=u.id
    WHERE a.appointment_date=CURDATE() AND a.status IN('scheduled','confirmed')
    ORDER BY a.appointment_time");

$admitted = rows("SELECT ad.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    r.room_no, rt.name AS room_type
    FROM admissions ad
    JOIN patients p ON ad.patient_id=p.id
    JOIN rooms r ON ad.room_id=r.id
    JOIN room_types rt ON r.room_type_id=rt.id
    WHERE ad.status='active'
    ORDER BY ad.admitted_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Nurse Dashboard</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <a href="/dmc/nurse/vitals.php" class="btn-dmc"><i class="bi bi-heart-pulse"></i> Record Vitals</a>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-people"></i></div><div><div class="stat-label">Today Patients</div><div class="stat-value"><?= $stats['today_patients'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-teal"><i class="bi bi-heart-pulse"></i></div><div><div class="stat-label">Vitals Recorded</div><div class="stat-value"><?= $stats['vitals_today'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-hospital"></i></div><div><div class="stat-label">Admitted</div><div class="stat-value"><?= $stats['admissions'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-clock"></i></div><div><div class="stat-label">Pending Today</div><div class="stat-value"><?= $stats['pending_appts'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Today's Queue <a href="/dmc/nurse/patients.php">All Patients →</a></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ($todayQueue): foreach ($todayQueue as $a): ?>
          <tr>
            <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
            <td>
              <div style="font-size:13px;font-weight:600"><?= e($a['pname']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($a['patient_no']) ?></div>
            </td>
            <td style="font-size:12px">Dr. <?= e($a['dname']) ?></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td><a href="/dmc/nurse/vitals.php?patient_id=<?= $a['patient_id'] ?>&appt_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">Record Vitals</a></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No patients in queue</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">Admitted Patients <a href="/dmc/nurse/admissions.php">All →</a></div>
      <?php if ($admitted): foreach ($admitted as $ad): ?>
      <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:var(--bg)">
        <div class="stat-icon si-blue" style="width:36px;height:36px;font-size:14px;border-radius:8px"><i class="bi bi-hospital"></i></div>
        <div class="flex-1">
          <div style="font-size:12.5px;font-weight:600"><?= e($ad['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)">Room <?= e($ad['room_no']) ?> · <?= e($ad['room_type']) ?></div>
        </div>
        <div style="font-size:11px;color:var(--muted)"><?= dateF($ad['admitted_at']) ?></div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:13px">No admitted patients</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
