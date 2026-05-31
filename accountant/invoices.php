<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Invoices';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

/* ── create new invoice ── */
if ($action === 'new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $items     = json_decode($_POST['items'] ?? '[]', true);
    $notes     = trim($_POST['notes'] ?? '');
    $dueDate   = $_POST['due_date'] ?? null;

    if (!$patientId || !is_array($items) || !count($items)) {
        flash('main', 'Patient and at least one item are required.', 'danger');
    } else {
        $total = 0;
        foreach ($items as $item) $total += (float)($item['qty']??0) * (float)($item['price']??0);
        $no = generateNo('DMC-INV','invoices','invoice_no');
        $invId = execute("INSERT INTO invoices (invoice_no,patient_id,total,paid,balance,status,notes,due_date,created_by) VALUES (?,?,?,0,?,?,?,?,?)",
            [$no, $patientId, $total, $total, 'issued', $notes, $dueDate?:null, currentUserId()]);
        foreach ($items as $item) {
            $lineTotal = (float)($item['qty']??0) * (float)($item['price']??0);
            execute("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total_price) VALUES (?,?,?,?,?)",
                [$invId, $item['desc']??'', $item['qty']??1, $item['price']??0, $lineTotal]);
        }
        $patTotal = applyInsuranceToInvoice($invId, $patientId, $total);
        execute("UPDATE patients SET balance = balance + ? WHERE id=?", [$patTotal, $patientId]);
        audit('create_invoice','invoices',$invId,"Invoice $no, total ".money($total).", patient portion ".money($patTotal));
        flash('main',"Invoice $no created for RWF ".number_format($total).($patTotal < $total ? ' (patient pays RWF '.number_format($patTotal).' after insurance)' : ''));
        header("Location: /dmc/accountant/invoices.php?id=$invId"); exit;
    }
}

/* ── view single invoice ── */
if ($id) {
    $inv = row("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone, p.address
        FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.id=?", [$id]);
    $invItems = rows("SELECT * FROM invoice_items WHERE invoice_id=?", [$id]);
    $payments = rows("SELECT * FROM payments WHERE invoice_id=? ORDER BY paid_at DESC", [$id]);
}

/* ── list view ── */
$filter = $_GET['status'] ?? 'all';
$list   = [];
if (!$id && $action !== 'new') {
    $q = "SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname FROM invoices i JOIN patients p ON i.patient_id=p.id";
    $p = [];
    if ($filter !== 'all') { $q .= " WHERE i.status=?"; $p[] = $filter; }
    $q .= " ORDER BY i.created_at DESC LIMIT 60";
    $list = rows($q, $p);
}

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Invoices</div><div class="page-sub">Billing and invoice management</div></div>
  <div class="d-flex gap-2">
    <?php if ($id || $action==='new'): ?>
    <a href="/dmc/accountant/invoices.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
    <?php endif; ?>
    <a href="/dmc/accountant/invoices.php?action=new" class="btn-dmc"><i class="bi bi-plus-circle"></i> New Invoice</a>
  </div>
</div>

<?= showFlash('main') ?>

<?php if ($action === 'new'): ?>
<!-- ═══════ CREATE INVOICE ═══════ -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Create New Invoice</div>
      <form method="POST">
        <!-- Patient -->
        <div class="mb-3">
          <label class="form-label">Patient *</label>
          <input type="text" id="patSearch" class="form-control" placeholder="Search patient..." oninput="searchPat(this.value)">
          <input type="hidden" name="patient_id" id="patientId" required>
          <div id="patResults" class="mt-1"></div>
          <div id="patCard" class="mt-2" style="display:none"></div>
        </div>

        <!-- Items -->
        <div class="mb-3">
          <label class="form-label">Invoice Items *</label>
          <div id="itemList"></div>
          <button type="button" onclick="addItem()" class="btn btn-outline-primary btn-sm mt-1"><i class="bi bi-plus"></i> Add Item</button>
          <input type="hidden" name="items" id="itemsJson" value="[]">
        </div>

        <div class="p-3 rounded mb-3" style="background:var(--bg)">
          <div class="d-flex justify-content-between"><strong>Total:</strong><strong id="totalDisplay" style="font-size:16px;color:var(--brand)">RWF 0</strong></div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
          </div>
          <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="/dmc/accountant/invoices.php" class="btn btn-secondary">Cancel</a>
            <button type="button" onclick="submitInvoice()" class="btn-dmc"><i class="bi bi-file-earmark-check"></i> Create Invoice</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Common Services</div>
      <div style="font-size:12px">
        <?php $services = [['Consultation',5000],['Lab Test',3000],['X-Ray',8000],['Ultrasound',15000],['Admission/Day',20000],['Medicine',0],['Procedure',10000],['Nursing Care',5000]]; ?>
        <?php foreach ($services as [$name,$price]): ?>
        <div class="d-flex justify-content-between p-2 mb-1 rounded" style="background:var(--bg);cursor:pointer" onclick="addItem('<?= $name ?>',<?= $price ?>)">
          <span><?= $name ?></span>
          <strong><?= $price ? 'RWF '.number_format($price) : 'Variable' ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($id && isset($inv) && $inv): ?>
<!-- ═══════ INVOICE DETAIL ═══════ -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card" id="invoicePrint">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <div style="font-size:20px;font-weight:700;color:var(--brand)">DMC Hospital</div>
          <div style="font-size:12px;color:var(--muted)">KK 541 Street, Kigali · Tel: 0782 749 660</div>
        </div>
        <div class="text-end">
          <div style="font-size:18px;font-weight:700"><?= e($inv['invoice_no']) ?></div>
          <div style="font-size:12px;color:var(--muted)"><?= dateF($inv['created_at']) ?></div>
          <span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
        </div>
      </div>

      <div class="p-3 rounded mb-3" style="background:var(--bg);font-size:12.5px">
        <strong>Bill To:</strong><br>
        <?= e($inv['pname']) ?> (<?= e($inv['patient_no']) ?>)<br>
        <?= e($inv['phone']) ?>
      </div>

      <div class="table-responsive">
        <table class="table mb-0" style="font-size:13px">
          <thead style="background:var(--bg)"><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($invItems as $it): ?>
          <tr>
            <td><?= e($it['description']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td><?= money($it['unit_price']) ?></td>
            <td><?= money($it['total_price']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg)">
            <tr><td colspan="3" class="text-end"><strong>Total</strong></td><td><strong><?= money($inv['total']) ?></strong></td></tr>
            <tr><td colspan="3" class="text-end" style="color:var(--success)">Paid</td><td style="color:var(--success)"><?= money($inv['paid']) ?></td></tr>
            <tr><td colspan="3" class="text-end"><strong style="color:var(--danger)">Balance Due</strong></td><td><strong style="color:var(--danger);font-size:16px"><?= money($inv['balance']) ?></strong></td></tr>
          </tfoot>
        </table>
      </div>

      <div class="d-flex gap-2 mt-3">
        <?php if (in_array($inv['status'],['issued','partial'])): ?>
        <a href="/dmc/accountant/payments.php?invoice_id=<?= $inv['id'] ?>" class="btn-dmc"><i class="bi bi-credit-card"></i> Collect Payment</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <?php if ($payments): ?>
    <div class="dmc-card">
      <div class="dmc-card-title">Payment History</div>
      <?php foreach ($payments as $pay): ?>
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background:var(--bg);font-size:12.5px">
        <div>
          <div style="font-weight:600"><?= money($pay['amount']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= ucfirst(str_replace('_',' ',$pay['method'])) ?> · <?= dtF($pay['paid_at']) ?></div>
          <div style="font-size:10px;font-family:monospace"><?= e($pay['payment_no']) ?></div>
        </div>
        <span class="badge-status bs-<?= $pay['status'] ?>"><?= ucfirst($pay['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ═══════ LIST VIEW ═══════ -->
<div class="dmc-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="dmc-card-title mb-0">All Invoices</div>
    <div class="d-flex gap-1">
      <?php foreach(['all'=>'All','draft'=>'Draft','issued'=>'Issued','partial'=>'Partial','paid'=>'Paid'] as $f=>$l): ?>
      <a href="?status=<?= $f ?>" class="btn btn-sm <?= $filter===$f?'btn-primary':'btn-outline-secondary' ?>" style="font-size:11px"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Invoice</th><th>Patient</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($list as $inv): ?>
      <tr>
        <td style="font-size:11px;font-family:monospace;font-weight:600"><?= e($inv['invoice_no']) ?></td>
        <td><?= e($inv['pname']) ?></td>
        <td><?= money($inv['total']) ?></td>
        <td style="color:var(--success)"><?= money($inv['paid']) ?></td>
        <td style="font-weight:600;color:<?= $inv['balance']>0?'var(--danger)':'var(--success)' ?>"><?= money($inv['balance']) ?></td>
        <td><span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
        <td style="font-size:11px"><?= dateF($inv['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:10px">View</a>
            <?php if (in_array($inv['status'],['issued','partial'])): ?>
            <a href="/dmc/accountant/payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success" style="font-size:10px">Collect</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="8" class="text-center text-muted py-4">No invoices found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php $extraScripts = "<script>
let items = [];
function addItem(desc='', price=0) {
  items.push({desc, qty:1, price});
  renderItems();
}
function renderItems() {
  document.getElementById('itemList').innerHTML = items.map((it,i)=>`
  <div class='row g-2 mb-2'>
    <div class='col-md-5'><input class='form-control form-control-sm' placeholder='Description' value='\${it.desc}' oninput='items[\${i}].desc=this.value;calcTotal()'></div>
    <div class='col-md-2'><input type='number' class='form-control form-control-sm' placeholder='Qty' value='\${it.qty}' min='1' oninput='items[\${i}].qty=+this.value;calcTotal()'></div>
    <div class='col-md-3'><input type='number' class='form-control form-control-sm' placeholder='Unit Price' value='\${it.price}' min='0' oninput='items[\${i}].price=+this.value;calcTotal()'></div>
    <div class='col-md-2 d-flex gap-1 align-items-center'>
      <span style='font-size:12px;font-weight:600'>RWF \${(it.qty*it.price).toLocaleString()}</span>
      <button type='button' class='btn btn-sm btn-outline-danger ms-auto' onclick='items.splice(\${i},1);renderItems()'>✕</button>
    </div>
  </div>`).join('');
  calcTotal();
}
function calcTotal(){
  const total=items.reduce((s,it)=>s+it.qty*it.price,0);
  document.getElementById('totalDisplay').textContent='RWF '+total.toLocaleString();
}
function submitInvoice(){
  if(!document.getElementById('patientId').value){toast('Select a patient first','warning');return;}
  if(!items.length){toast('Add at least one item','warning');return;}
  document.getElementById('itemsJson').value=JSON.stringify(items);
  document.querySelector('form').submit();
}
function searchPat(q){
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    document.getElementById('patResults').innerHTML=j.results.map(p=>`<div onclick=\"selectPat(\${p.id},'\${p.first_name} \${p.last_name}','\${p.patient_no}')\" class='p-2 mb-1 rounded' style='background:var(--bg);cursor:pointer;font-size:13px'><strong>\${p.first_name} \${p.last_name}</strong> <span style='font-size:11px;color:var(--muted)'>\${p.patient_no} · \${p.phone}</span></div>`).join('');
  });
}
function selectPat(id,name,no){
  document.getElementById('patientId').value=id;
  document.getElementById('patSearch').value=name+' ('+no+')';
  document.getElementById('patResults').innerHTML='';
}
</script>";
include __DIR__ . '/../includes/footer.php';
