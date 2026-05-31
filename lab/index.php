<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['lab_technician','admin']);
$pageTitle = 'Laboratory Dashboard';

$stats = [
    'pending'   => scalar("SELECT COUNT(*) FROM lab_orders WHERE status='pending'"),
    'in_progress'=> scalar("SELECT COUNT(*) FROM lab_orders WHERE status='in_progress'"),
    'completed_today'=> scalar("SELECT COUNT(*) FROM lab_orders WHERE status='completed' AND DATE(updated_at)=CURDATE()"),
    'total_tests'=> scalar("SELECT COUNT(*) FROM lab_tests"),
];

$pendingOrders = rows("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id=p.id
    JOIN users u ON lo.doctor_id=u.id
    WHERE lo.status IN('pending','in_progress')
    ORDER BY lo.created_at ASC LIMIT 20");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Laboratory Dashboard</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <a href="/dmc/lab/orders.php" class="btn-dmc"><i class="bi bi-flask"></i> All Orders</a>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-arrow-clockwise"></i></div><div><div class="stat-label">In Progress</div><div class="stat-value"><?= $stats['in_progress'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-check-circle"></i></div><div><div class="stat-label">Completed Today</div><div class="stat-value"><?= $stats['completed_today'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-flask"></i></div><div><div class="stat-label">Test Types</div><div class="stat-value"><?= $stats['total_tests'] ?></div></div></div></div>
</div>

<div class="dmc-card">
  <div class="dmc-card-title">Pending & In-Progress Lab Orders</div>
  <?php if ($pendingOrders): foreach ($pendingOrders as $ord): ?>
  <?php $items = rows("SELECT loi.*, lt.name AS test_name FROM lab_order_items loi JOIN lab_tests lt ON loi.lab_test_id=lt.id WHERE loi.lab_order_id=?", [$ord['id']]); ?>
  <div class="p-3 mb-3 rounded" style="background:var(--bg);border-left:3px solid <?= $ord['status']==='in_progress'?'var(--brand2)':'var(--warning)' ?>">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div>
        <strong style="font-size:13.5px"><?= e($ord['pname']) ?></strong>
        <span style="font-size:11px;color:var(--muted);margin-left:8px"><?= e($ord['patient_no']) ?></span>
        <div style="font-size:11.5px;color:var(--muted)"><?= e($ord['order_no']) ?> · Dr. <?= e($ord['dname']) ?> · <?= dtF($ord['created_at']) ?></div>
      </div>
      <span class="badge-status bs-<?= $ord['status'] ?>"><?= ucfirst(str_replace('_',' ',$ord['status'])) ?></span>
    </div>
    <div class="row g-2">
      <?php foreach ($items as $item): ?>
      <div class="col-md-6">
        <div class="p-2 rounded d-flex justify-content-between align-items-center" style="background:#fff;font-size:12px">
          <div>
            <strong><?= e($item['test_name']) ?></strong>
            <span class="badge-status ms-2 bs-<?= $item['status'] ?>" style="font-size:10px"><?= ucfirst($item['status']) ?></span>
          </div>
          <?php if ($item['status'] !== 'completed'): ?>
          <button onclick="enterResult(<?= $item['id'] ?>, '<?= e(addslashes($item['test_name'])) ?>')" class="btn btn-sm btn-outline-primary" style="font-size:10px">Enter Result</button>
          <?php else: ?>
          <span style="color:var(--success);font-size:11px">✓ <?= e(substr($item['result'],0,30)) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; else: ?>
  <div class="text-center text-muted py-5"><i class="bi bi-check-circle" style="font-size:2.5rem;display:block;margin-bottom:.5rem;color:var(--success)"></i>No pending lab orders</div>
  <?php endif; ?>
</div>

<!-- Result modal -->
<div id="resultModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(420px,95vw)">
    <strong id="resultTitle" style="font-size:15px;display:block;margin-bottom:16px">Enter Lab Result</strong>
    <div class="mb-3"><label class="form-label">Result *</label><input type="text" id="resultValue" class="form-control" placeholder="e.g. Negative, 5.2 mmol/L"></div>
    <div class="mb-3"><label class="form-label">Notes</label><textarea id="resultNotes" class="form-control" rows="2"></textarea></div>
    <div class="d-flex gap-2 justify-content-end">
      <button onclick="document.getElementById('resultModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      <button onclick="submitResult()" class="btn-dmc">Save Result</button>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
let currentItemId = 0;
function enterResult(id, name) {
  currentItemId = id;
  document.getElementById('resultTitle').textContent = 'Result: ' + name;
  document.getElementById('resultValue').value = '';
  document.getElementById('resultNotes').value = '';
  document.getElementById('resultModal').style.display = 'flex';
  document.getElementById('resultValue').focus();
}
function submitResult() {
  const result = document.getElementById('resultValue').value.trim();
  if (!result) { toast('Enter a result value', 'warning'); return; }
  dmcPost('/dmc/api/ajax.php', {action:'update_lab_result', item_id:currentItemId, result, notes:document.getElementById('resultNotes').value}).then(j=>{
    if(j.ok){document.getElementById('resultModal').style.display='none';toast('Result saved!');setTimeout(()=>location.reload(),1200);}
    else defaultErr(j);
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
