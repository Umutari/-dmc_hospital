<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'All Appointments';

$dateFilter = $_GET['date'] ?? date('Y-m-d');
$status     = $_GET['status'] ?? 'all';

$sql = "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
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
  <div><div class="page-title">All Appointments</div><div class="page-sub">System-wide appointment management</div></div>
  <a href="/dmc/receptionist/book_appointment.php" class="btn-dmc"><i class="bi bi-calendar-plus"></i> Book New</a>
</div>

<div class="dmc-card mb-3">
  <form class="d-flex gap-2 flex-wrap" method="GET">
    <div><label class="form-label" style="font-size:12px">Date</label><input type="date" name="date" class="form-control" value="<?= $dateFilter ?>" style="width:auto"></div>
    <div><label class="form-label" style="font-size:12px">Status</label>
      <select name="status" class="form-select" style="width:auto">
        <option value="all">All</option>
        <?php foreach(['scheduled','confirmed','completed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:flex-end"><button type="submit" class="btn-dmc"><i class="bi bi-search"></i></button></div>
    <div style="align-self:flex-end"><a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a></div>
  </form>
</div>

<div class="dmc-card">
  <div class="dmc-card-title">Appointments — <?= date('l, d F Y', strtotime($dateFilter)) ?> (<?= count($appts) ?>)</div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($appts as $a): ?>
      <tr>
        <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
        <td>
          <div style="font-size:13px;font-weight:600"><?= e($a['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($a['patient_no']) ?></div>
        </td>
        <td>Dr. <?= e($a['dname']) ?></td>
        <td style="font-size:12px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></td>
        <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$appts): ?><tr><td colspan="5" class="text-center text-muted py-4">No appointments for this date</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
