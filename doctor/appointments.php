<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'My Schedule';
$uid = currentUserId();

$dateFilter = $_GET['date'] ?? date('Y-m-d');
$appts = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone, p.date_of_birth, p.gender
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    WHERE a.doctor_id=? AND a.appointment_date=?
    ORDER BY a.appointment_time", [$uid, $dateFilter]);

/* quick complete */
$actId = (int)($_GET['complete'] ?? 0);
if ($actId) {
    execute("UPDATE appointments SET status='completed' WHERE id=? AND doctor_id=?", [$actId, $uid]);
    header("Location: /dmc/doctor/appointments.php?date=$dateFilter"); exit;
}

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Schedule</div><div class="page-sub">Dr. <?= e(currentUser()['first_name'].' '.currentUser()['last_name']) ?></div></div>
</div>

<div class="dmc-card mb-3">
  <form class="d-flex gap-2 align-items-end" method="GET">
    <div>
      <label class="form-label" style="font-size:12px">Date</label>
      <input type="date" name="date" class="form-control" value="<?= $dateFilter ?>">
    </div>
    <button type="submit" class="btn-dmc"><i class="bi bi-search"></i> View</button>
    <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a>
    <a href="?date=<?= date('Y-m-d', strtotime($dateFilter.' -1 day')) ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <a href="?date=<?= date('Y-m-d', strtotime($dateFilter.' +1 day')) ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
  </form>
</div>

<div class="dmc-card">
  <div class="dmc-card-title">Appointments — <?= date('l, d F Y', strtotime($dateFilter)) ?> (<?= count($appts) ?>)</div>
  <?php if ($appts): foreach ($appts as $a): ?>
  <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded" style="background:var(--bg);border-left:3px solid var(--<?= $a['status']==='completed'?'success':($a['status']==='confirmed'?'brand2':'muted') ?>)">
    <div style="text-align:center;min-width:55px">
      <div style="font-size:16px;font-weight:700;color:var(--brand)"><?= timeF($a['appointment_time']) ?></div>
    </div>
    <div class="patient-avatar" style="width:40px;height:40px;font-size:14px"><?= strtoupper(substr($a['pname'],0,2)) ?></div>
    <div class="flex-1">
      <div style="font-size:14px;font-weight:600"><?= e($a['pname']) ?></div>
      <div style="font-size:11.5px;color:var(--muted)">
        <?= e($a['patient_no']) ?> · <?= $a['date_of_birth']?age($a['date_of_birth']).' yrs, ':'—, ' ?><?= ucfirst($a['gender']??'') ?> · <?= e($a['phone']) ?>
      </div>
      <?php if ($a['reason']): ?><div style="font-size:12px;color:var(--muted);margin-top:2px"><?= e($a['reason']) ?></div><?php endif; ?>
    </div>
    <div class="d-flex flex-column gap-1 align-items-end">
      <span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      <div class="d-flex gap-1 mt-1">
        <a href="/dmc/doctor/records.php?patient_id=<?= $a['patient_id'] ?>&appt_id=<?= $a['id'] ?>" class="btn btn-sm btn-primary" style="font-size:11px"><i class="bi bi-file-medical"></i> Attend</a>
        <?php if ($a['status']!=='completed'&&$a['status']!=='cancelled'): ?>
        <a href="?complete=<?= $a['id'] ?>&date=<?= $dateFilter ?>" class="btn btn-sm btn-outline-success" style="font-size:11px" onclick="return confirm('Mark as completed?')">Done</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; else: ?>
  <div class="text-center text-muted py-5"><i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No appointments for this date</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php';
