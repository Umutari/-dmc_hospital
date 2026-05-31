<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['lab_technician','admin','doctor']);
$pageTitle = 'Lab Orders';

$id  = (int)($_GET['id'] ?? 0);
$single = $id ? row("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id=p.id
    JOIN users u ON lo.doctor_id=u.id
    WHERE lo.id=?", [$id]) : [];
if ($single) {
    $items = rows("SELECT loi.*, lt.name AS test_name, lt.reference_range, lt.unit FROM lab_order_items loi JOIN lab_tests lt ON loi.lab_test_id=lt.id WHERE loi.lab_order_id=?", [$id]);
}

$status = $_GET['status'] ?? 'all';
$q = "SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname, CONCAT(u.first_name,' ',u.last_name) AS dname FROM lab_orders lo JOIN patients p ON lo.patient_id=p.id JOIN users u ON lo.doctor_id=u.id";
$p = [];
if ($status !== 'all') { $q .= " WHERE lo.status=?"; $p[] = $status; }
$q .= " ORDER BY lo.created_at DESC LIMIT 50";
$list = rows($q, $p);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Lab Orders</div><div class="page-sub">Laboratory test orders and results</div></div>
  <?php if ($id): ?><a href="/dmc/lab/orders.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a><?php endif; ?>
</div>

<?php if ($single): ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title"><?= e($single['order_no']) ?></div>
      <div class="p-3 rounded" style="background:var(--bg);font-size:12.5px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Patient</span><strong><?= e($single['pname']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Patient No</span><?= e($single['patient_no']) ?></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Doctor</span>Dr. <?= e($single['dname']) ?></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Ordered</span><?= dtF($single['created_at']) ?></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Status</span><span class="badge-status bs-<?= str_replace('_','-',$single['status']) ?>"><?= ucfirst(str_replace('_',' ',$single['status'])) ?></span></div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Test Items</div>
      <?php foreach ($items as $item): ?>
      <div class="p-3 mb-2 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <strong style="font-size:13px"><?= e($item['test_name']) ?></strong>
            <?php if ($item['reference_range']): ?><div style="font-size:11px;color:var(--muted)">Ref: <?= e($item['reference_range']) ?> <?= e($item['unit']??'') ?></div><?php endif; ?>
          </div>
          <span class="badge-status bs-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
        </div>
        <?php if ($item['result']): ?>
        <div class="mt-2 p-2 rounded" style="background:#fff;font-size:12.5px">
          <strong>Result:</strong> <?= e($item['result']) ?> <?= e($item['unit']??'') ?>
          <?php if ($item['result_notes']): ?><div style="color:var(--muted)"><?= e($item['result_notes']) ?></div><?php endif; ?>
          <div style="font-size:11px;color:var(--muted)">Completed: <?= dtF($item['completed_at']??'') ?></div>
        </div>
        <?php elseif (currentRole()==='lab_technician'||currentRole()==='admin'): ?>
        <button onclick="enterResult(<?= $item['id'] ?>, '<?= e(addslashes($item['test_name'])) ?>')" class="btn btn-sm btn-outline-primary mt-2" style="font-size:11px">Enter Result</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else: ?>
<div class="dmc-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="dmc-card-title mb-0">All Lab Orders</div>
    <div class="d-flex gap-1">
      <?php foreach(['all'=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed'] as $f=>$l): ?>
      <a href="?status=<?= $f ?>" class="btn btn-sm <?= $status===$f?'btn-primary':'btn-outline-secondary' ?>" style="font-size:11px"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Order No</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($list as $ord): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($ord['order_no']) ?></td>
        <td><?= e($ord['pname']) ?></td>
        <td>Dr. <?= e($ord['dname']) ?></td>
        <td><span class="badge-status bs-<?= str_replace('_','-',$ord['status']) ?>"><?= ucfirst(str_replace('_',' ',$ord['status'])) ?></span></td>
        <td style="font-size:11px"><?= dtF($ord['created_at']) ?></td>
        <td><a href="?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">No orders found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Result modal -->
<div id="resultModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(400px,95vw)">
    <strong id="resultTitle" style="font-size:15px;display:block;margin-bottom:16px">Enter Result</strong>
    <div class="mb-3"><label class="form-label">Result *</label><input type="text" id="resultValue" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Notes</label><textarea id="resultNotes" class="form-control" rows="2"></textarea></div>
    <div class="d-flex gap-2 justify-content-end">
      <button onclick="document.getElementById('resultModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      <button onclick="submitResult()" class="btn-dmc">Save</button>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
let currentItemId=0;
function enterResult(id,name){currentItemId=id;document.getElementById('resultTitle').textContent='Result: '+name;document.getElementById('resultValue').value='';document.getElementById('resultModal').style.display='flex';document.getElementById('resultValue').focus();}
function submitResult(){
  const result=document.getElementById('resultValue').value.trim();
  if(!result){toast('Enter a result','warning');return;}
  dmcPost('/dmc/api/ajax.php',{action:'update_lab_result',item_id:currentItemId,result,notes:document.getElementById('resultNotes').value}).then(j=>{
    if(j.ok){document.getElementById('resultModal').style.display='none';toast('Result saved!');setTimeout(()=>location.reload(),1200);}
    else defaultErr(j);
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
