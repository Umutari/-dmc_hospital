<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin']);
$pageTitle = 'My Invoices';
$uid  = currentUserId();
$user = row("SELECT * FROM users WHERE id=?", [$uid]);

$patient = row("SELECT * FROM patients WHERE email=?", [$user['email'] ?? '']);
if (!$patient) $patient = row("SELECT * FROM patients WHERE phone=?", [$user['phone'] ?? '']);
$pid = $patient['id'] ?? 0;

/* invoices with real paid sum from payments table */
$invoices = $pid ? rows("
    SELECT i.*,
           COALESCE(SUM(CASE WHEN py.status='success' THEN py.amount ELSE 0 END),0) AS actual_paid
    FROM invoices i
    LEFT JOIN payments py ON py.invoice_id = i.id
    WHERE i.patient_id = ?
    GROUP BY i.id
    ORDER BY i.created_at DESC", [$pid]) : [];

/* all payments for this patient */
$allPay = $pid ? rows("
    SELECT py.*, i.invoice_no
    FROM payments py
    JOIN invoices i ON py.invoice_id = i.id
    WHERE py.patient_id = ?
    ORDER BY py.paid_at DESC", [$pid]) : [];

/* all invoice line items */
$allItems = $pid ? rows("
    SELECT ii.*, i.invoice_no, i.id AS inv_id
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    WHERE i.patient_id = ?
    ORDER BY ii.id", [$pid]) : [];

/* group by invoice id */
$payByInv  = []; foreach ($allPay   as $p)  $payByInv[$p['invoice_id']][]  = $p;
$itemsByInv = []; foreach ($allItems as $it) $itemsByInv[$it['invoice_id']][] = $it;

/* summary stats */
$totalBilled = array_sum(array_column($invoices, 'total'));
$totalPaid   = array_sum(array_column($invoices, 'actual_paid'));
$totalOwed   = max(0, $totalBilled - $totalPaid);
$pendingCnt  = count(array_filter($invoices, fn($i) => ($i['total'] - $i['actual_paid']) > 0.009));

$FLW_KEY  = setting('flw_public_key');
$patName  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$patEmail = $user['email'] ?? 'patient@dmc.rw';
$patPhone = $user['phone'] ?? '';

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">My Invoices & Bills</div>
    <div class="page-sub"><?= count($invoices) ?> invoice<?= count($invoices)!=1?'s':'' ?> &nbsp;·&nbsp; <?= $pendingCnt ?> pending payment<?= $pendingCnt!=1?'s':'' ?></div>
  </div>
</div>

<?= showFlash('main') ?>

<?php if ($totalOwed > 0): ?>
<div class="d-flex align-items-center justify-content-between p-3 mb-3 rounded-3"
     style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff">
  <div>
    <div style="font-weight:700;font-size:16px">Total Outstanding Balance</div>
    <div style="font-size:12px;opacity:.85"><?= $pendingCnt ?> unpaid invoice<?= $pendingCnt!=1?'s':'' ?> — pay all at once</div>
  </div>
  <div class="text-end">
    <div style="font-size:22px;font-weight:800"><?= money($totalOwed) ?></div>
    <button onclick="openPayAllModal()" class="btn btn-light btn-sm mt-1"
            style="font-size:12px;font-weight:700;color:#b91c1c">
      <i class="bi bi-wallet2 me-1"></i>Pay All Now
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="dmc-card text-center py-3">
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Total Billed</div>
      <div style="font-size:22px;font-weight:700;color:var(--brand)"><?= money($totalBilled) ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="dmc-card text-center py-3">
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Total Paid</div>
      <div style="font-size:22px;font-weight:700;color:var(--success)"><?= money($totalPaid) ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="dmc-card text-center py-3">
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Outstanding</div>
      <div style="font-size:22px;font-weight:700;color:<?= $totalOwed>0?'var(--danger)':'var(--success)' ?>"><?= money($totalOwed) ?></div>
    </div>
  </div>
</div>

<?php if (!$invoices): ?>
<div class="dmc-card text-center py-5">
  <i class="bi bi-receipt" style="font-size:3rem;display:block;margin-bottom:1rem;color:var(--muted)"></i>
  <div style="font-weight:600;color:var(--muted)">No invoices yet</div>
  <div style="font-size:12px;color:var(--muted);margin-top:4px">Your bills will appear here after your visits</div>
</div>
<?php endif; ?>

<?php foreach ($invoices as $inv):
    $due      = max(0, (float)$inv['total'] - (float)$inv['actual_paid']);
    $canPay   = $due > 0.009 && !in_array($inv['status'], ['paid','cancelled']);
    $invPays  = $payByInv[$inv['id']] ?? [];
    $invItems = $itemsByInv[$inv['id']] ?? [];
    $pct      = $inv['total'] > 0 ? min(100, round($inv['actual_paid'] / $inv['total'] * 100)) : 0;
    $statusColor = ['paid'=>'var(--success)','partial'=>'var(--warning)','issued'=>'var(--danger)','cancelled'=>'var(--muted)'][$inv['status']] ?? 'var(--muted)';
?>
<div class="dmc-card mb-3" id="inv-<?= $inv['id'] ?>">

  <!-- Invoice header -->
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <div style="font-weight:700;font-size:15px;color:var(--brand)"><?= e($inv['invoice_no']) ?></div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px">
        <i class="bi bi-calendar3 me-1"></i><?= dtF($inv['created_at']) ?>
        <?php if ($inv['due_date']): ?>
        &nbsp;·&nbsp; <i class="bi bi-clock me-1"></i>Due: <?= dateF($inv['due_date']) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
      <?php if ($inv['status']==='paid'): ?>
      <i class="bi bi-patch-check-fill" style="color:var(--success);font-size:18px" title="Fully Paid"></i>
      <?php endif; ?>
    </div>
  </div>

  <!-- Progress bar -->
  <div style="background:var(--border);border-radius:4px;height:6px;margin-bottom:14px">
    <div style="width:<?= $pct ?>%;height:6px;border-radius:4px;background:<?= $pct>=100?'var(--success)':($pct>0?'var(--warning)':'var(--danger)') ?>;transition:width .4s"></div>
  </div>

  <!-- Amount breakdown -->
  <div class="row g-2 mb-3">
    <div class="col-4">
      <div class="p-2 rounded text-center" style="background:var(--bg)">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Total</div>
        <div style="font-weight:700;font-size:14px"><?= money($inv['total']) ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="p-2 rounded text-center" style="background:var(--bg)">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Paid</div>
        <div style="font-weight:700;font-size:14px;color:var(--success)"><?= money($inv['actual_paid']) ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="p-2 rounded text-center" style="background:var(--bg)">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Balance</div>
        <div style="font-weight:700;font-size:14px;color:<?= $due>0?'var(--danger)':'var(--success)' ?>">
          <?= $due > 0 ? money($due) : '<i class="bi bi-check2-circle"></i> Cleared' ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($inv['notes']): ?>
  <div class="mb-3 p-2 rounded" style="background:#fffbe6;border-left:3px solid var(--warning);font-size:12px">
    <i class="bi bi-info-circle me-1 text-warning"></i><?= e($inv['notes']) ?>
  </div>
  <?php endif; ?>

  <!-- Line items toggle -->
  <?php if ($invItems): ?>
  <div class="mb-3">
    <button class="btn btn-sm btn-outline-secondary" style="font-size:11px" type="button"
            data-bs-toggle="collapse" data-bs-target="#items-<?= $inv['id'] ?>">
      <i class="bi bi-list-ul me-1"></i>View Bill Items (<?= count($invItems) ?>)
    </button>
    <div class="collapse mt-2" id="items-<?= $inv['id'] ?>">
      <table class="table table-sm mb-0" style="font-size:12px">
        <thead style="background:var(--bg)">
          <tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($invItems as $it): ?>
          <tr>
            <td><?= e($it['description']) ?></td>
            <td class="text-center"><?= $it['quantity'] ?></td>
            <td class="text-end"><?= money($it['unit_price']) ?></td>
            <td class="text-end"><strong><?= money($it['total_price']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg)">
            <td colspan="3" class="text-end"><strong>Total</strong></td>
            <td class="text-end"><strong><?= money($inv['total']) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Payment history -->
  <?php if ($invPays): ?>
  <div class="mb-3">
    <button class="btn btn-sm btn-outline-secondary" style="font-size:11px" type="button"
            data-bs-toggle="collapse" data-bs-target="#pays-<?= $inv['id'] ?>">
      <i class="bi bi-clock-history me-1"></i>Payment History (<?= count($invPays) ?>)
    </button>
    <div class="collapse mt-2" id="pays-<?= $inv['id'] ?>">
      <table class="table table-sm mb-0" style="font-size:12px">
        <thead style="background:var(--bg)">
          <tr><th>Ref</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($invPays as $py): ?>
          <tr>
            <td style="font-family:monospace;font-size:11px"><?= e($py['payment_no']) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$py['method'])) ?></td>
            <td><strong style="color:var(--success)"><?= money($py['amount']) ?></strong></td>
            <td><span class="badge-status bs-<?= $py['status'] ?>"><?= ucfirst($py['status']) ?></span></td>
            <td style="font-size:11px"><?= dtF($py['paid_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Pay Now -->
  <?php if ($canPay): ?>
  <div class="pt-3" style="border-top:1px solid var(--border)">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div style="font-size:11px;color:var(--muted)">Outstanding balance</div>
        <div style="font-weight:700;font-size:18px;color:var(--danger)"><?= money($due) ?></div>
      </div>
      <button onclick="openPayModal(<?= $inv['id'] ?>, <?= $inv['patient_id'] ?>, <?= $due ?>)"
              class="btn-dmc px-4" style="font-size:13px">
        <i class="bi bi-wallet2 me-1"></i>Make Payment
      </button>
    </div>
  </div>
  <?php elseif ($inv['status'] === 'paid'): ?>
  <div class="pt-3" style="border-top:1px solid var(--border)">
    <div class="d-flex align-items-center gap-2" style="color:var(--success);font-size:13px">
      <i class="bi bi-check-circle-fill"></i>
      <span>This invoice is fully paid. Thank you!</span>
      <button onclick="printReceipt(<?= $inv['id'] ?>)" class="btn btn-sm btn-outline-success ms-auto" style="font-size:11px">
        <i class="bi bi-printer me-1"></i>Print Receipt
      </button>
    </div>
  </div>
  <?php elseif ($inv['status'] === 'cancelled'): ?>
  <div class="pt-3" style="border-top:1px solid var(--border)">
    <div style="color:var(--muted);font-size:12px"><i class="bi bi-x-circle me-1"></i>This invoice has been cancelled.</div>
  </div>
  <?php endif; ?>

</div>
<?php endforeach; ?>

<!-- All payments history section -->
<?php if ($allPay): ?>
<div class="dmc-card mt-2">
  <div class="dmc-card-title"><i class="bi bi-clock-history me-2"></i>All Payment Transactions</div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0" style="font-size:12px">
      <thead>
        <tr><th>Reference</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($allPay as $py): ?>
        <tr>
          <td style="font-family:monospace"><?= e($py['payment_no']) ?></td>
          <td><?= e($py['invoice_no']) ?></td>
          <td><i class="bi bi-<?= str_starts_with($py['method'],'momo')?'phone':'credit-card' ?> me-1"></i><?= ucfirst(str_replace('_',' ',$py['method'])) ?></td>
          <td><strong style="color:var(--success)"><?= money($py['amount']) ?></strong></td>
          <td><span class="badge-status bs-<?= $py['status'] ?>"><?= ucfirst($py['status']) ?></span></td>
          <td><?= dtF($py['paid_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border-radius:12px;border:none">
      <div class="modal-header" style="background:var(--brand);color:#fff;border-radius:12px 12px 0 0">
        <h5 class="modal-title" style="font-size:15px"><i class="bi bi-wallet2 me-2"></i>Make Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3 p-3 rounded" style="background:var(--bg)">
          <div style="font-size:11px;color:var(--muted)">Outstanding Balance</div>
          <div id="modalBalance" style="font-size:22px;font-weight:700;color:var(--danger)"></div>
        </div>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px">Amount to Pay (RWF) *</label>
          <input type="number" id="payAmountInput" class="form-control" min="1" placeholder="Enter amount">
          <div class="d-flex gap-2 mt-2">
            <button onclick="setFullAmount()" class="btn btn-sm btn-outline-primary" style="font-size:11px">Pay Full Balance</button>
          </div>
          <div id="amountError" class="text-danger mt-1" style="font-size:11px;display:none"></div>
        </div>

        <div class="mb-4">
          <label class="form-label" style="font-size:13px">Payment Method *</label>
          <div class="d-flex gap-2">
            <label class="flex-1 text-center p-3 rounded border" style="cursor:pointer" id="momoLabel">
              <input type="radio" name="payMethod" value="mobilemoneyrwanda" checked style="display:none">
              <i class="bi bi-phone-fill d-block mb-1" style="font-size:20px;color:#07a35a"></i>
              <span style="font-size:12px;font-weight:600">MoMo</span>
            </label>
            <label class="flex-1 text-center p-3 rounded border" style="cursor:pointer" id="cardLabel">
              <input type="radio" name="payMethod" value="card" style="display:none">
              <i class="bi bi-credit-card-fill d-block mb-1" style="font-size:20px;color:var(--brand)"></i>
              <span style="font-size:12px;font-weight:600">Card</span>
            </label>
          </div>
        </div>

        <button onclick="proceedPayment()" class="btn-dmc w-100" style="font-size:14px;padding:12px">
          <i class="bi bi-lock-fill me-2"></i>Proceed to Secure Payment
        </button>
        <div style="text-align:center;margin-top:10px;font-size:11px;color:var(--muted)">
          <i class="bi bi-shield-check me-1"></i>Secured by Flutterwave
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$FLW_KEY_JS    = addslashes($FLW_KEY);
$patNameJS     = addslashes($patName);
$patEmailJS    = addslashes($patEmail);
$patPhoneJS    = addslashes($patPhone);
$totalOwedJs   = (int)$totalOwed;
$pidJs         = (int)$pid;
$autoOpenJs    = (!empty($_GET['payall']) && $totalOwed > 0) ? 'true' : 'false';
$extraScripts = "<script src='https://checkout.flutterwave.com/v3.js'></script>
<script>
const FLW_KEY   = '$FLW_KEY_JS';
const PAT_NAME  = '$patNameJS';
const PAT_EMAIL = '$patEmailJS';
const PAT_PHONE = '$patPhoneJS';

let _invId = 0, _patId = 0, _maxDue = 0, _payAll = false;

/* highlight selected method label */
document.querySelectorAll('[name=payMethod]').forEach(r => {
  r.closest('label').addEventListener('click', function() {
    document.querySelectorAll('[name=payMethod]').forEach(x => x.closest('label').style.borderColor = '');
    this.style.borderColor = 'var(--brand)';
    this.style.background  = 'var(--bg)';
    this.querySelector('input').checked = true;
  });
});

function openPayModal(invoiceId, patientId, due) {
  _invId  = invoiceId;
  _patId  = patientId;
  _maxDue = due;
  _payAll = false;
  document.getElementById('modalBalance').textContent = 'RWF ' + due.toLocaleString();
  document.getElementById('payAmountInput').value = due;
  document.getElementById('payAmountInput').max   = due;
  document.getElementById('amountError').style.display = 'none';
  new bootstrap.Modal(document.getElementById('payModal')).show();
}

function openPayAllModal() {
  _invId  = 0;
  _patId  = $pidJs;
  _maxDue = $totalOwedJs;
  _payAll = true;
  document.getElementById('modalBalance').textContent = 'RWF ' + ($totalOwedJs).toLocaleString();
  document.getElementById('payAmountInput').value = $totalOwedJs;
  document.getElementById('payAmountInput').max   = $totalOwedJs;
  document.getElementById('amountError').style.display = 'none';
  new bootstrap.Modal(document.getElementById('payModal')).show();
}

function setFullAmount() {
  document.getElementById('payAmountInput').value = _maxDue;
}

function proceedPayment() {
  const amount = parseFloat(document.getElementById('payAmountInput').value);
  const errEl  = document.getElementById('amountError');
  if (!amount || amount < 1) { errEl.textContent='Enter a valid amount.'; errEl.style.display='block'; return; }
  if (amount > _maxDue + 0.01) { errEl.textContent='Amount cannot exceed the outstanding balance (RWF '+_maxDue.toLocaleString()+').'; errEl.style.display='block'; return; }
  errEl.style.display = 'none';

  const method = document.querySelector('[name=payMethod]:checked').value;

  if (!FLW_KEY) {
    Swal.fire({icon:'warning',title:'Payment Unavailable',text:'Online payment is not configured. Please visit the front desk.',confirmButtonColor:'#0A2342'});
    return;
  }

  bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();

  FlutterwaveCheckout({
    public_key: FLW_KEY,
    tx_ref: 'DMC-' + _invId + '-' + Date.now(),
    amount: amount,
    currency: 'RWF',
    payment_options: method,
    customer: { email: PAT_EMAIL, phone_number: PAT_PHONE, name: PAT_NAME },
    customizations: { title: 'DMC Hospital', description: 'Invoice payment — RWF '+amount.toLocaleString() },
    callback: function(res) {
      const charged = parseFloat(res.amount) || amount;
      const mthd    = method === 'card' ? 'card' : 'momo_mtn';
      const payload = _payAll
        ? { action:'pay_all_invoices', patient_id:_patId, amount:charged, method:mthd,
            flw_txid:res.transaction_id, flw_ref:res.flw_ref }
        : { action:'collect_payment',  invoice_id:_invId, patient_id:_patId, amount:charged,
            method:mthd, flw_txid:res.transaction_id, flw_ref:res.flw_ref, otp_verified:1 };

      fetch('/dmc/api/ajax.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      }).then(r=>r.json()).then(j=>{
        if (j.ok) {
          const remaining = _payAll ? (j.patient_balance||0) : (j.new_balance||0);
          const detail    = _payAll
            ? 'Invoices cleared: <strong>'+j.invoices_cleared.join(', ')+'</strong><br>'
            : '';
          Swal.fire({
            icon:'success', title:'Payment Successful!',
            html:'<div style=\"font-size:13px;line-height:1.8\">' +
                 'Amount paid: <strong style=\"color:#065f46\">RWF '+charged.toLocaleString()+'</strong><br>' +
                 detail +
                 'Remaining balance: <strong style=\"color:'+(remaining>0?'#dc2626':'#065f46')+'\">RWF '+remaining.toLocaleString()+'</strong><br>' +
                 '<span style=\"font-size:11px;color:#888\">Ref: '+res.flw_ref+'</span></div>',
            confirmButtonColor:'#0A2342'
          }).then(()=>location.reload());
        } else {
          Swal.fire({icon:'error',title:'Error',text:j.error||'Something went wrong.',confirmButtonColor:'#0A2342'});
        }
      });
    },
    onclose: function() {
      toast('Payment not completed. Try again when ready.','warning');
    }
  });
}

function printReceipt(invoiceId) {
  window.open('/dmc/patient/receipt.php?invoice_id='+invoiceId,'_blank','width=700,height=600');
}

if ($autoOpenJs) document.addEventListener('DOMContentLoaded', function(){ openPayAllModal(); });
</script>";
include __DIR__ . '/../includes/footer.php';
