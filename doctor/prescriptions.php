<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'Prescriptions';
$uid = currentUserId();

$status = $_GET['status'] ?? 'all';
$q = "SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no
    FROM prescriptions pr JOIN patients p ON pr.patient_id=p.id
    WHERE pr.doctor_id=?";
$params = [$uid];
if ($status !== 'all') { $q .= " AND pr.status=?"; $params[] = $status; }
$q .= " ORDER BY pr.created_at DESC LIMIT 50";
$prescriptions = rows($q, $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Prescriptions</div><div class="page-sub">Issued prescriptions</div></div>
</div>

<div class="dmc-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="dmc-card-title mb-0">Prescriptions</div>
    <div class="d-flex gap-1">
      <?php foreach(['all'=>'All','pending'=>'Pending','dispensed'=>'Dispensed','cancelled'=>'Cancelled'] as $f=>$l): ?>
      <a href="?status=<?= $f ?>" class="btn btn-sm <?= $status===$f?'btn-primary':'btn-outline-secondary' ?>" style="font-size:11px"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Ref</th><th>Patient</th><th>Status</th><th>Items</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($prescriptions as $rx): ?>
      <?php $cnt = scalar("SELECT COUNT(*) FROM prescription_items WHERE prescription_id=?", [$rx['id']]); ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($rx['prescription_no']) ?></td>
        <td>
          <div style="font-size:13px;font-weight:600"><?= e($rx['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($rx['patient_no']) ?></div>
        </td>
        <td><span class="badge-status bs-<?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span></td>
        <td><?= $cnt ?> medicine<?= $cnt!=1?'s':'' ?></td>
        <td style="font-size:11px"><?= dtF($rx['created_at']) ?></td>
        <td>
          <a href="/dmc/pharmacist/prescriptions.php?id=<?= $rx['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$prescriptions): ?><tr><td colspan="6" class="text-center text-muted py-4">No prescriptions found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
