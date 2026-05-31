<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
$pageTitle = 'Appointments';

/* quick actions */
$act = $_GET['action'] ?? '';
$id  = (int)($_GET['id'] ?? 0);
if ($act && $id) {
    if ($act === 'confirm') {
        execute("UPDATE appointments SET status='confirmed' WHERE id=?", [$id]);
        flash('main', 'Appointment confirmed.');
    } elseif ($act === 'cancel') {
        execute("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
        flash('main', 'Appointment cancelled.');
    }
    header('Location: /dmc/receptionist/appointments.php'); exit;
}

$dateFilter = $_GET['date'] ?? date('Y-m-d');
$status     = $_GET['status'] ?? 'all';

$sql = "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON a.doctor_id=u.id
    WHERE a.appointment_date=?";
$params = [$dateFilter];
if ($status !== 'all') { $sql .= " AND a.status=?"; $params[] = $status; }
$sql .= " ORDER BY a.appointment_time";
$appts = rows($sql, $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Appointments</div><div class="page-sub">Manage and view appointments</div></div>
  <a href="/dmc/receptionist/book_appointment.php" class="btn-dmc"><i class="bi bi-calendar-plus"></i> Book New</a>
</div>

<?= showFlash('main') ?>

<!-- Filters -->
<div class="dmc-card mb-3">
  <form class="d-flex gap-2 flex-wrap" method="GET">
    <div>
      <label class="form-label" style="font-size:12px">Date</label>
      <input type="date" name="date" class="form-control" value="<?= $dateFilter ?>" style="width:auto">
    </div>
    <div>
      <label class="form-label" style="font-size:12px">Status</label>
      <select name="status" class="form-select" style="width:auto">
        <option value="all" <?= $status==='all'?'selected':'' ?>>All</option>
        <?php foreach(['scheduled','confirmed','completed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:flex-end">
      <button type="submit" class="btn-dmc"><i class="bi bi-search"></i> Filter</button>
    </div>
    <div style="align-self:flex-end">
      <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a>
    </div>
  </form>
</div>

<?php
$workload = rows("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS dname,
    COUNT(a.id) AS cnt
    FROM users u
    LEFT JOIN appointments a ON a.doctor_id=u.id AND a.appointment_date=? AND a.status!='cancelled'
    WHERE u.role='doctor' AND u.is_active=1
    GROUP BY u.id ORDER BY cnt DESC", [$dateFilter]);
?>
<div class="dmc-card mb-3">
  <div class="dmc-card-title"><i class="bi bi-people me-2"></i>Doctor Workload — <?= date('d M Y', strtotime($dateFilter)) ?>
    <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:8px">Max 15 patients/day per doctor</span>
  </div>
  <div class="row g-2">
    <?php foreach ($workload as $w):
      $cnt = (int)$w['cnt'];
      $pct = min(100, round($cnt/15*100));
      $color = $cnt>=15 ? 'var(--danger)' : ($cnt>=12 ? '#f59e0b' : 'var(--success)');
    ?>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="p-2 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1" style="font-size:12px">
          <span style="font-weight:600">Dr. <?= e($w['dname']) ?></span>
          <span style="color:<?= $color ?>;font-weight:700"><?= $cnt ?>/15</span>
        </div>
        <div style="background:var(--border);border-radius:3px;height:6px">
          <div style="width:<?= $pct ?>%;height:6px;border-radius:3px;background:<?= $color ?>"></div>
        </div>
        <?php if ($cnt>=15): ?>
        <div style="font-size:10px;color:var(--danger);margin-top:3px">Fully booked</div>
        <?php elseif ($cnt>=12): ?>
        <div style="font-size:10px;color:#b45309;margin-top:3px"><?= 15-$cnt ?> slot(s) left</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="dmc-card">
  <div class="dmc-card-title">Appointments — <?= date('l, d F Y', strtotime($dateFilter)) ?> (<?= count($appts) ?>)</div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if ($appts): foreach ($appts as $a): ?>
      <tr>
        <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
        <td>
          <div style="font-size:13px;font-weight:600"><?= e($a['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($a['patient_no']) ?> · <?= e($a['phone']) ?></div>
        </td>
        <td>Dr. <?= e($a['dname']) ?></td>
        <td style="font-size:12px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></td>
        <td style="font-size:12px;max-width:180px"><?= $a['reason'] ? e(substr($a['reason'],0,60)) : '<span style="color:var(--muted)">—</span>' ?></td>
        <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
        <td>
          <div class="d-flex gap-1">
            <?php if ($a['status']==='scheduled'): ?>
            <a href="?action=confirm&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success" style="font-size:10px">Confirm</a>
            <?php endif; ?>
            <?php if (!in_array($a['status'],['cancelled','completed'])): ?>
            <a href="?action=cancel&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" style="font-size:10px" onclick="return confirm('Cancel this appointment?')">Cancel</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-calendar-x me-1"></i>No appointments for this date/filter</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
