<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['lab_technician','admin','doctor']);
$pageTitle = 'Lab Results';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$search = trim($_GET['q'] ?? '');
$period = $_GET['period'] ?? 'month';
if ($period === 'today')    { $from = $to = date('Y-m-d'); }
elseif ($period === 'week') { $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); }
elseif ($period === 'month'){ $from = date('Y-m-01'); $to = date('Y-m-d'); }
elseif ($period === 'year') { $from = date('Y-01-01'); $to = date('Y-m-d'); }

/* single result detail */
$viewId = (int)($_GET['id'] ?? 0);
if ($viewId) {
    $order = row("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.dob, p.gender, p.phone,
        CONCAT(u.first_name,' ',u.last_name) AS dname, d.name AS dept_name
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id=p.id
        JOIN users u ON lo.doctor_id=u.id
        LEFT JOIN doctors doc ON u.id=doc.user_id
        LEFT JOIN departments d ON doc.department_id=d.id
        WHERE lo.id=?", [$viewId]);
    if ($order) {
        $items = rows("SELECT loi.*, lt.name AS test_name, lt.reference_range, lt.unit
            FROM lab_order_items loi JOIN lab_tests lt ON loi.lab_test_id=lt.id
            WHERE loi.lab_order_id=? ORDER BY lt.name", [$viewId]);
    }
}

/* stats */
$totalCompleted = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status='completed'");
$completedToday = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status='completed' AND DATE(updated_at)=CURDATE()");
$pendingOrders  = (int)scalar("SELECT COUNT(*) FROM lab_orders WHERE status IN('pending','in_progress')");
$totalPatients  = (int)scalar("SELECT COUNT(DISTINCT patient_id) FROM lab_orders WHERE status='completed'");

/* results list */
$where = "lo.status='completed' AND DATE(lo.updated_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($search) {
    $where .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR lo.order_no LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s,$s]);
}
$results = rows("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    CONCAT(u.first_name,' ',u.last_name) AS dname,
    (SELECT COUNT(*) FROM lab_order_items WHERE lab_order_id=lo.id) AS test_count
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id=p.id
    JOIN users u ON lo.doctor_id=u.id
    WHERE $where
    ORDER BY lo.updated_at DESC LIMIT 100", $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Lab Results</div>
    <div class="page-sub">Completed laboratory test results</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($viewId): ?>
    <button onclick="window.print()" class="btn-dmc-outline"><i class="bi bi-printer"></i> Print</button>
    <a href="/dmc/lab/results.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back to List</a>
    <?php else: ?>
    <a href="/dmc/lab/orders.php" class="btn-dmc-outline"><i class="bi bi-flask"></i> View All Orders</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($viewId && $order): ?>
<!-- ===== SINGLE RESULT DETAIL ===== -->
<div id="printArea">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="dmc-card">
        <div class="dmc-card-title">Patient Information</div>
        <div style="font-size:13px">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="patient-avatar"><?= strtoupper(substr(explode(' ',$order['pname'])[0],0,1).substr(explode(' ',$order['pname'])[1]??'',0,1)) ?></div>
            <div>
              <div style="font-weight:600;font-size:15px"><?= e($order['pname']) ?></div>
              <div style="color:var(--muted);font-size:11px"><?= e($order['patient_no']) ?></div>
            </div>
          </div>
          <div class="p-3 rounded" style="background:var(--bg)">
            <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Gender</span><span><?= ucfirst($order['gender']??'') ?></span></div>
            <?php if ($order['dob']): ?><div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Age</span><span><?= age($order['dob']) ?> yrs</span></div><?php endif; ?>
            <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Phone</span><span><?= e($order['phone']) ?></span></div>
            <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Ordered by</span><span>Dr. <?= e($order['dname']) ?></span></div>
            <?php if ($order['dept_name']): ?><div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Department</span><span><?= e($order['dept_name']) ?></span></div><?php endif; ?>
            <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Order No</span><span style="font-family:monospace;font-size:11px"><?= e($order['order_no']) ?></span></div>
            <div class="d-flex justify-content-between"><span style="color:var(--muted)">Completed</span><span><?= dtF($order['updated_at']) ?></span></div>
          </div>
          <?php if ($order['notes']): ?>
          <div class="mt-3 p-2 rounded" style="background:#fff8e1;border:1px solid #ffe082;font-size:12px">
            <strong>Clinical Notes:</strong> <?= e($order['notes']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="dmc-card">
        <div class="dmc-card-title">Test Results — <?= e($order['order_no']) ?></div>
        <?php if ($items): foreach ($items as $item):
          $resultClass = '';
          if ($item['result'] && $item['reference_range']) {
              $resultClass = 'result-normal';
          }
        ?>
        <div class="p-3 mb-2 rounded" style="background:var(--bg);border-left:3px solid <?= $item['status']==='completed'?'var(--success)':'var(--warning)' ?>">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div style="font-weight:600;font-size:13.5px"><?= e($item['test_name']) ?></div>
              <?php if ($item['reference_range']): ?>
              <div style="font-size:11px;color:var(--muted)">Reference: <?= e($item['reference_range']) ?> <?= e($item['unit']??'') ?></div>
              <?php endif; ?>
            </div>
            <span class="badge-status bs-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
          </div>
          <?php if ($item['result']): ?>
          <div class="mt-2 p-2 rounded" style="background:#fff">
            <div class="d-flex align-items-center gap-2">
              <span style="font-size:20px;font-weight:700;color:var(--brand)"><?= e($item['result']) ?></span>
              <?php if ($item['unit']): ?><span style="font-size:12px;color:var(--muted)"><?= e($item['unit']) ?></span><?php endif; ?>
            </div>
            <?php if ($item['result_notes']): ?>
            <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= e($item['result_notes']) ?></div>
            <?php endif; ?>
            <?php if ($item['completed_at']): ?>
            <div style="font-size:10px;color:var(--muted);margin-top:4px">Recorded: <?= dtF($item['completed_at']) ?></div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="mt-2" style="font-size:12px;color:var(--warning)"><i class="bi bi-clock me-1"></i>Pending result entry</div>
          <?php if (in_array(currentRole(),['lab_technician','admin'])): ?>
          <button onclick="enterResult(<?= $item['id'] ?>, '<?= e(addslashes($item['test_name'])) ?>')" class="btn btn-sm btn-outline-primary mt-1" style="font-size:11px">Enter Result</button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div class="text-center py-4 text-muted">No test items found for this order.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($viewId): ?>
<div class="alert alert-warning">Lab order not found or not yet completed.</div>

<?php else: ?>
<!-- ===== RESULTS LIST ===== -->

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-label">Total Completed</div><div class="stat-value"><?= number_format($totalCompleted) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-calendar-check"></i></div>
      <div><div class="stat-label">Completed Today</div><div class="stat-value"><?= $completedToday ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="stat-label">Pending Orders</div><div class="stat-value"><?= $pendingOrders ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-people"></i></div>
      <div><div class="stat-label">Patients Tested</div><div class="stat-value"><?= number_format($totalPatients) ?></div></div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="dmc-card mb-3">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
    <div class="d-flex gap-1 flex-wrap">
      <?php foreach(['today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $p=>$l): ?>
      <a href="?period=<?= $p ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $period===$p?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($period==='custom'): ?>
    <input type="hidden" name="period" value="custom">
    <div><label class="form-label" style="font-size:12px">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>"></div>
    <div><label class="form-label" style="font-size:12px">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>"></div>
    <?php endif; ?>
    <div class="flex-grow-1" style="min-width:200px">
      <label class="form-label" style="font-size:12px">Search patient / order no</label>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($search) ?>">
      <?php if ($period==='custom'): ?><input type="hidden" name="from" value="<?= $from ?>"><input type="hidden" name="to" value="<?= $to ?>"><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
    <?php if ($search): ?><a href="?period=<?= $period ?>" class="btn btn-sm btn-outline-secondary" style="align-self:flex-end">Clear</a><?php endif; ?>
  </form>
</div>

<div class="dmc-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="dmc-card-title mb-0">Completed Results <span style="font-size:12px;font-weight:400;color:var(--muted)">(<?= dateF($from) ?> – <?= dateF($to) ?>)</span></div>
    <span style="font-size:12px;color:var(--muted)"><?= count($results) ?> result<?= count($results)!==1?'s':'' ?></span>
  </div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0" id="resultsTable">
      <thead>
        <tr>
          <th>Order No</th>
          <th>Patient</th>
          <th>Patient No</th>
          <th>Ordered By</th>
          <th>Tests</th>
          <th>Completed</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($results): foreach ($results as $r): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px;font-weight:600"><?= e($r['order_no']) ?></td>
        <td><?= e($r['pname']) ?></td>
        <td style="font-size:11px;color:var(--muted)"><?= e($r['patient_no']) ?></td>
        <td>Dr. <?= e($r['dname']) ?></td>
        <td><span class="badge bg-light text-dark"><?= $r['test_count'] ?> test<?= $r['test_count']!=1?'s':'' ?></span></td>
        <td style="font-size:11px"><?= dtF($r['updated_at']) ?></td>
        <td>
          <a href="?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px"><i class="bi bi-eye me-1"></i>View</a>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-flask me-2"></i>No completed results found for this period.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Result entry modal -->
<div id="resultModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(420px,95vw)">
    <strong id="resultTitle" style="font-size:15px;display:block;margin-bottom:16px">Enter Result</strong>
    <div class="mb-3"><label class="form-label">Result Value *</label><input type="text" id="resultValue" class="form-control" placeholder="e.g. 5.4 or Negative"></div>
    <div class="mb-3"><label class="form-label">Notes / Interpretation</label><textarea id="resultNotes" class="form-control" rows="2" placeholder="Optional clinical notes..."></textarea></div>
    <div class="d-flex gap-2 justify-content-end">
      <button onclick="document.getElementById('resultModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      <button onclick="submitResult()" class="btn-dmc">Save Result</button>
    </div>
  </div>
</div>

<style>
@media print {
  .dmc-sidebar, .dmc-topbar, .page-header .btn-dmc-outline, .page-header .btn-dmc { display:none!important; }
  .dmc-main { margin:0!important; padding:0!important; }
  .dmc-card { box-shadow:none!important; border:1px solid #ddd!important; }
}
</style>

<?php $extraScripts = "<script>
let currentItemId = 0;
function enterResult(id, name) {
  currentItemId = id;
  document.getElementById('resultTitle').textContent = 'Enter Result: ' + name;
  document.getElementById('resultValue').value = '';
  document.getElementById('resultNotes').value = '';
  document.getElementById('resultModal').style.display = 'flex';
  document.getElementById('resultValue').focus();
}
function submitResult() {
  const result = document.getElementById('resultValue').value.trim();
  if (!result) { toast('Please enter a result value', 'warning'); return; }
  dmcPost('/dmc/api/ajax.php', {
    action: 'update_lab_result',
    item_id: currentItemId,
    result,
    notes: document.getElementById('resultNotes').value
  }).then(j => {
    if (j.ok) {
      document.getElementById('resultModal').style.display = 'none';
      toast('Result saved!');
      setTimeout(() => location.reload(), 1200);
    } else defaultErr(j);
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
