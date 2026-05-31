<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'Lab Orders';
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $tests     = $_POST['tests'] ?? [];
    $priority  = $_POST['priority'] ?? 'routine';
    $notes     = trim($_POST['notes'] ?? '');
    if ($patientId && count($tests)) {
        $no = generateNo('DMC-LAB','lab_orders','order_no');
        $ordId = execute("INSERT INTO lab_orders (order_no,patient_id,doctor_id,priority,notes,status) VALUES (?,?,?,?,?,'pending')",
          [$no,$patientId,$uid,$priority,$notes]);
        foreach ($tests as $testId) {
            execute("INSERT INTO lab_order_items (lab_order_id,lab_test_id,status) VALUES (?,?,'pending')", [$ordId,(int)$testId]);
        }
        audit('create_lab_order','lab_orders',$ordId,"Order $no for patient $patientId");

        /* auto-invoice for priced tests */
        $ph = implode(',', array_fill(0, count($tests), '?'));
        $testData = rows("SELECT id, name, price FROM lab_tests WHERE id IN ($ph) AND price > 0", array_map('intval', $tests));
        if ($testData) {
            $labTotal = array_sum(array_column($testData, 'price'));
            $invNo = generateNo('DMC-INV', 'invoices', 'invoice_no');
            $invId = execute(
                "INSERT INTO invoices (invoice_no,patient_id,total,paid,balance,status,notes,created_by) VALUES (?,?,?,0,?,?,?,?)",
                [$invNo, $patientId, $labTotal, $labTotal, 'issued', "Lab order $no", $uid]
            );
            foreach ($testData as $t) {
                execute(
                    "INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total_price) VALUES (?,?,1,?,?)",
                    [$invId, $t['name'].' (Lab Test)', $t['price'], $t['price']]
                );
            }
            $patLabTotal = applyInsuranceToInvoice($invId, $patientId, $labTotal);
            execute("UPDATE patients SET balance = balance + ? WHERE id=?", [$patLabTotal, $patientId]);
            audit('auto_invoice_lab', 'invoices', $invId, "Auto-invoice $invNo for lab order $no");
        }

        flash('main', "Lab order $no created." . ($testData ? ' Invoice generated for lab fees.' : ''));
    } else {
        $errMsg = !$patientId ? 'Please search and select a patient.' : 'Please select at least one test.';
        flash('main', $errMsg, 'danger');
    }
    header('Location: /dmc/doctor/lab_orders.php'); exit;
}

$myOrders = rows("SELECT lo.*, CONCAT(p.first_name,' ',p.last_name) AS pname
    FROM lab_orders lo JOIN patients p ON lo.patient_id=p.id
    WHERE lo.doctor_id=? ORDER BY lo.created_at DESC LIMIT 30", [$uid]);

$labTests = rows("SELECT * FROM lab_tests WHERE is_active=1 ORDER BY category, name");
$testsByCategory = [];
foreach ($labTests as $t) $testsByCategory[$t['category']][] = $t;

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Lab Orders</div><div class="page-sub">Request and track laboratory tests</div></div>
  <button class="btn-dmc" onclick="document.getElementById('orderModal').style.display='flex'"><i class="bi bi-flask"></i> New Order</button>
</div>

<?= showFlash('main') ?>

<div class="dmc-card">
  <div class="dmc-card-title">My Lab Orders</div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Order No</th><th>Patient</th><th>Priority</th><th>Status</th><th>Tests</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($myOrders as $ord): ?>
      <?php $cnt = scalar("SELECT COUNT(*) FROM lab_order_items WHERE lab_order_id=?", [$ord['id']]); ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($ord['order_no']) ?></td>
        <td><?= e($ord['pname']) ?></td>
        <td><span class="badge-status <?= $ord['priority']==='urgent'?'bs-cancelled':'bs-issued' ?>"><?= ucfirst($ord['priority']) ?></span></td>
        <td><span class="badge-status bs-<?= str_replace('_','-',$ord['status']) ?>"><?= ucfirst(str_replace('_',' ',$ord['status'])) ?></span></td>
        <td><?= $cnt ?> test<?= $cnt!=1?'s':'' ?></td>
        <td style="font-size:11px"><?= dtF($ord['created_at']) ?></td>
        <td><a href="/dmc/lab/orders.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">View Results</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$myOrders): ?><tr><td colspan="7" class="text-center text-muted py-4">No lab orders yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New order modal -->
<div id="orderModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(580px,95vw);max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px">New Lab Order</strong>
      <button onclick="document.getElementById('orderModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST" onsubmit="submitOrder(event)">
      <div class="mb-3">
        <label class="form-label">Patient *</label>
        <input type="text" id="patSearch" class="form-control" placeholder="Search patient..." oninput="searchPat(this.value)">
        <input type="hidden" name="patient_id" id="patientId" required>
        <div id="patResults" class="mt-1"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-select">
          <option value="routine">Routine</option>
          <option value="urgent">Urgent</option>
          <option value="stat">STAT</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Select Tests *</label>
        <?php foreach ($testsByCategory as $cat => $tests): ?>
        <div class="mb-2">
          <strong style="font-size:12px;color:var(--muted)"><?= e($cat) ?></strong>
          <div class="row g-1 mt-1">
            <?php foreach ($tests as $t): ?>
            <div class="col-6">
              <label class="d-flex align-items-center gap-2 p-2 rounded" style="background:var(--bg);cursor:pointer;font-size:12.5px">
                <input type="checkbox" name="tests[]" value="<?= $t['id'] ?>"> <?= e($t['name']) ?>
                <?php if ($t['price']): ?><span style="font-size:10px;color:var(--muted);margin-left:auto"><?= money($t['price']) ?></span><?php endif; ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('orderModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc">Submit Order</button>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = "<script>
var _pats = [];
function searchPat(q){
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    _pats = j.results||[];
    document.getElementById('patResults').innerHTML=_pats.map((p,i)=>
      `<div onclick=\"pickPat(\${i})\" class='p-2 mb-1 rounded' style='background:var(--bg);cursor:pointer;font-size:12.5px'>
        <strong>\${p.first_name} \${p.last_name}</strong>
        <span style='color:var(--muted);margin-left:8px'>\${p.patient_no}</span>
      </div>`
    ).join('');
  });
}
function pickPat(i){
  var p = _pats[i];
  document.getElementById('patientId').value = p.id;
  document.getElementById('patSearch').value = p.first_name+' '+p.last_name;
  document.getElementById('patSearch').style.borderColor = '';
  document.getElementById('patResults').innerHTML = '';
}
function submitOrder(e){
  if(!document.getElementById('patientId').value){
    e.preventDefault();
    document.getElementById('patSearch').style.borderColor='#dc3545';
    document.getElementById('patSearch').focus();
    return false;
  }
  if(!document.querySelectorAll('#orderModal input[type=checkbox]:checked').length){
    e.preventDefault();
    alert('Please select at least one test.');
    return false;
  }
}
</script>";
include __DIR__ . '/../includes/footer.php';
