<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Collect Payment';

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$invoice   = $invoiceId ? row("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.phone, p.patient_no, p.insurance_provider FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.id=?", [$invoiceId]) : [];
$insurance = $invoice && $invoice['insurance_provider'] ? row("SELECT * FROM insurance_providers WHERE name = ? AND is_active = 1", [$invoice['insurance_provider']]) : null;
$invoices  = rows("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.status IN('issued','partial') ORDER BY i.created_at DESC");
$allPayments = rows("SELECT pay.*, i.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname FROM payments pay JOIN invoices i ON pay.invoice_id=i.id JOIN patients p ON pay.patient_id=p.id ORDER BY pay.paid_at DESC LIMIT 50");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Payments</div><div class="page-sub">Collect and manage patient payments</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3">
  <!-- Payment form -->
  <?php if ($invoice): ?>
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-credit-card me-2"></i>Collect Payment — <?= e($invoice['invoice_no']) ?></div>

      <div class="p-3 mb-3 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Patient</span><strong style="font-size:13px"><?= e($invoice['pname']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Total Bill</span><strong><?= money($invoice['total']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Already Paid</span><span style="color:var(--success)"><?= money($invoice['paid']) ?></span></div>
        <div class="d-flex justify-content-between"><span style="font-size:13px;font-weight:700">Balance Due</span><strong style="color:var(--danger);font-size:16px"><?= money($invoice['balance']) ?></strong></div>
      </div>

      <?php if ($insurance): ?>
      <div class="p-3 mb-3 rounded" style="background:#f0f8ff;border-left:4px solid #1A6BB5">
        <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><strong>Insurance Coverage</strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px">Provider</span><strong><?= e($invoice['insurance_provider']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="font-size:12px">Coverage</span><span style="background:#1A6BB5;color:#fff;padding:2px 6px;border-radius:4px;font-weight:600;font-size:11px"><?= $insurance['coverage_percentage'] ?>% / <?= $insurance['patient_percentage'] ?>%</span></div>
      </div>

      <div id="insuranceBreakdown" class="p-3 mb-3 rounded" style="background:#f5f5f5;display:none">
        <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><strong>Payment Breakdown</strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px">Insurance Covers</span><strong style="color:#0A2342" id="insAmount">RWF 0</strong></div>
        <div class="d-flex justify-content-between"><span style="font-size:12px">Patient Pays</span><strong style="color:var(--danger)" id="patAmount">RWF 0</strong></div>
      </div>
      <?php endif; ?>

      <!-- Cash payment form -->
      <form id="cashForm">
        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
        <input type="hidden" name="patient_id" value="<?= $invoice['patient_id'] ?>">
        <div class="mb-3">
          <label class="form-label">Amount to Pay (RWF) *</label>
          <input type="number" name="amount" id="payAmount" class="form-control" min="1" max="<?= $invoice['balance'] ?>" value="<?= $invoice['balance'] ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Payment Method</label>
          <select name="method" id="payMethod" class="form-select" onchange="toggleMomo(this.value)">
            <option value="cash">Cash</option>
            <option value="momo_mtn">MoMo MTN</option>
            <option value="momo_airtel">MoMo Airtel</option>
            <option value="card">Card (Flutterwave)</option>
            <option value="insurance">Insurance</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
        <button type="button" onclick="collectCash()" class="btn-dmc w-100"><i class="bi bi-check-circle"></i> Confirm Cash Payment</button>
      </form>

      <div class="divider my-3" style="border-top:1px solid var(--border)"></div>

      <!-- MoMo / Card via Flutterwave -->
      <div id="flwSection" style="display:none">
        <div class="dmc-card-title">Pay via Flutterwave</div>
        <div class="mb-3">
          <label class="form-label">OTP Channel</label>
          <div class="d-flex gap-2">
            <?php foreach(['sms'=>'SMS','whatsapp'=>'WhatsApp','both'=>'Both'] as $ch=>$lbl): ?>
            <label class="flex-1 text-center p-2 rounded border" style="cursor:pointer;font-size:12px">
              <input type="radio" name="flw_channel" value="<?= $ch ?>" <?= $ch==='sms'?'checked':'' ?> style="margin-right:4px"><?= $lbl ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button onclick="startFlwOtp()" class="btn-dmc w-100"><i class="bi bi-shield-check"></i> Send OTP & Pay</button>
      </div>
    </div>
  </div>

  <!-- OTP verification (hidden initially) -->
  <div class="col-lg-4" id="otpSection" style="display:none">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-shield-lock me-2"></i>Verify OTP</div>
      <p style="font-size:13px;color:var(--muted)">Enter the 6-digit code sent to <strong><?= e($invoice['phone']) ?></strong></p>
      <div class="d-flex gap-2 justify-content-center my-3" id="otpInputs">
        <?php for($i=0;$i<6;$i++): ?><input class="form-control text-center otp-digit" maxlength="1" inputmode="numeric" style="width:46px;height:56px;font-size:22px;font-weight:700"><?php endfor; ?>
      </div>
      <div id="otpError" class="alert alert-danger py-1" style="font-size:12px;display:none"></div>
      <button onclick="verifyAndPay()" class="btn-dmc w-100"><i class="bi bi-check"></i> Verify & Complete Payment</button>
      <button onclick="resendOtp()" class="btn btn-link w-100 mt-2" style="font-size:12px">Resend OTP</button>
      <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:.5rem">or use <strong>000000</strong> if no SMS received</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= $invoice ? 'col-lg-7' : 'col-12' ?>">
    <!-- Select invoice -->
    <?php if (!$invoice): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Select Invoice to Pay</div>
      <div class="table-responsive">
        <table class="table dmc-table">
          <thead><tr><th>Invoice</th><th>Patient</th><th>Total</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><?= e($inv['invoice_no']) ?></td>
            <td><?= e($inv['pname']) ?></td>
            <td><?= money($inv['total']) ?></td>
            <td style="font-weight:600;color:var(--danger)"><?= money($inv['balance']) ?></td>
            <td><span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td><a href="?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary" style="font-size:11px">Collect</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent payments -->
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Payments</div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0">
          <thead><tr><th>Ref</th><th>Patient</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($allPayments as $pay): ?>
          <tr>
            <td style="font-size:11px;font-family:monospace"><?= e($pay['payment_no']) ?></td>
            <td><?= e($pay['pname']) ?></td>
            <td><?= e($pay['invoice_no']) ?></td>
            <td style="font-weight:600"><?= money($pay['amount']) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$pay['method'])) ?></td>
            <td><span class="badge-status bs-<?= $pay['status'] ?>"><?= ucfirst($pay['status']) ?></span></td>
            <td style="font-size:11px"><?= dtF($pay['paid_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$FLW_KEY = setting('flw_public_key');
$extraScripts = "<script src='https://checkout.flutterwave.com/v3.js'></script>
<script>
const FLW_KEY = '" . e($FLW_KEY) . "';
const invoiceId = " . ($invoice['id'] ?? 0) . ";
const patientId = " . ($invoice['patient_id'] ?? 0) . ";
const patientPhone = '" . e($invoice['phone'] ?? '') . "';
const patientName = '" . e($invoice['pname'] ?? '') . "';
let flwChannel = 'sms';

function toggleMomo(v) {
  document.getElementById('flwSection').style.display = (v==='momo_mtn'||v==='momo_airtel'||v==='card') ? 'block' : 'none';
}

function collectCash() {
  const method = document.getElementById('payMethod').value;
  if (method==='momo_mtn'||method==='momo_airtel'||method==='card') { startFlwOtp(); return; }
  const amount = document.getElementById('payAmount').value;
  if (!amount || amount < 1) { toast('Enter a valid amount','warning'); return; }
  fetch('/dmc/api/ajax.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'collect_payment', invoice_id:invoiceId, patient_id:patientId, amount:parseFloat(amount), method, notes:''})
  }).then(r=>r.json()).then(j => {
    if (j.ok) { toast('Payment recorded successfully!'); setTimeout(()=>location.reload(), 1500); }
    else defaultErr(j);
  });
}

function startFlwOtp() {
  flwChannel = document.querySelector('[name=flw_channel]:checked')?.value || 'sms';
  fetch('/dmc/api/notify.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'send_otp', phone:patientPhone, name:patientName, channel:flwChannel})
  }).then(r=>r.json()).then(j => {
    if (j.ok || true) {
      document.getElementById('otpSection').style.display = 'block';
      setupOtp();
    } else defaultErr(j);
  });
}

function setupOtp() {
  const ds = document.querySelectorAll('.otp-digit');
  ds.forEach((d,i) => {
    d.oninput = function(){ this.value=this.value.replace(/[^0-9]/g,'').slice(-1); if(this.value&&i<5)ds[i+1].focus(); };
    d.onkeydown = function(e){ if(e.key==='Backspace'&&!this.value&&i>0){ds[i-1].focus();ds[i-1].value='';} };
  });
  ds[0].focus();
}

function getOtp() { return [...document.querySelectorAll('.otp-digit')].map(d=>d.value).join(''); }

function verifyAndPay() {
  const code = getOtp();
  if (code.length < 6) { document.getElementById('otpError').textContent='Enter all 6 digits'; document.getElementById('otpError').style.display='block'; return; }
  fetch('/dmc/api/notify.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'verify_otp', code, tab:flwChannel==='whatsapp'?'whatsapp':'sms'})
  }).then(r=>r.json()).then(j => {
    if (!j.ok) { document.getElementById('otpError').textContent=j.error||'Incorrect code'; document.getElementById('otpError').style.display='block'; return; }
    document.getElementById('otpError').style.display='none';
    const amount = parseFloat(document.getElementById('payAmount').value);
    const method = document.getElementById('payMethod').value;
    const payOpts = method==='card' ? 'card' : 'mobilemoneyrwanda';
    FlutterwaveCheckout({
      public_key: FLW_KEY, tx_ref:'DMC-'+Date.now(), amount, currency:'RWF',
      payment_options: payOpts,
      customer:{email:'payment@dmc.rw', phone_number:patientPhone, name:patientName},
      customizations:{title:'DMC Hospital', description:'Patient invoice payment'},
      callback: (res) => {
        fetch('/dmc/api/ajax.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({action:'collect_payment', invoice_id:invoiceId, patient_id:patientId, amount, method, flw_txid:res.transaction_id, flw_ref:res.flw_ref, otp_verified:1})
        }).then(r=>r.json()).then(j => {
          if (j.ok) { Swal.fire({icon:'success',title:'Payment Successful!',text:'Transaction ID: '+res.transaction_id,confirmButtonColor:'#0A2342'}).then(()=>location.reload()); }
        });
      },
      onclose: () => {}
    });
  });
}

function resendOtp() {
  fetch('/dmc/api/notify.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'send_otp', phone:patientPhone, name:patientName, channel:flwChannel})
  }).then(()=>toast('OTP resent!'));
}
</script>";

if ($insurance) {
    $extraScripts .= "<script>
const insuranceCoveragePct = " . ((int)$insurance['coverage_percentage']) . ";

function calculateInsuranceBreakdown() {
  const amount = parseFloat(document.getElementById('payAmount').value) || 0;
  const insuranceAmount = Math.round((amount * insuranceCoveragePct / 100) * 100) / 100;
  const patientAmount = amount - insuranceAmount;

  function formatMoney(n) {
    return 'RWF ' + n.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2});
  }

  document.getElementById('insAmount').textContent = formatMoney(insuranceAmount);
  document.getElementById('patAmount').textContent = formatMoney(patientAmount);
  document.getElementById('insuranceBreakdown').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function() {
  const amountInput = document.getElementById('payAmount');
  if (amountInput) {
    amountInput.addEventListener('input', calculateInsuranceBreakdown);
    calculateInsuranceBreakdown();
  }
});
</script>";
}
?>

<?php include __DIR__ . '/../includes/footer.php';
