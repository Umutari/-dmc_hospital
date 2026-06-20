<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Confirm Payment';

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$invoice   = $invoiceId ? row("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.phone, p.patient_no, p.insurance_provider FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.id=?", [$invoiceId]) : [];
$insurance = $invoice && $invoice['insurance_provider'] ? row("SELECT * FROM insurance_providers WHERE name = ? AND is_active = 1", [$invoice['insurance_provider']]) : null;
$invoices  = rows("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.status IN('issued','partial') ORDER BY i.created_at DESC");
$allPayments = rows("SELECT pay.*, i.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname FROM payments pay JOIN invoices i ON pay.invoice_id=i.id JOIN patients p ON pay.patient_id=p.id ORDER BY pay.paid_at DESC LIMIT 50");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Payments</div><div class="page-sub">Confirm and record patient payments</div></div>
</div>

<?= showFlash('main') ?>

<div class="row g-3">
  <!-- Payment form -->
  <?php if ($invoice): ?>
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-check2-circle me-2"></i>Confirm Payment — <?= e($invoice['invoice_no']) ?></div>

      <div class="p-3 mb-3 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Patient</span><strong style="font-size:13px"><?= e($invoice['pname']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Total Bill</span><strong><?= money($invoice['total']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Already Paid</span><span style="color:var(--success)"><?= money($invoice['paid']) ?></span></div>
        <div class="d-flex justify-content-between"><span style="font-size:13px;font-weight:700">Balance Due</span><strong style="color:var(--danger);font-size:16px"><?= money($invoice['balance']) ?></strong></div>
      </div>


      <!-- Confirm payment form -->
      <form id="cashForm">
        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
        <input type="hidden" name="patient_id" value="<?= $invoice['patient_id'] ?>">
        <div class="mb-3">
          <label class="form-label">Amount Received (RWF) *</label>
          <input type="number" name="amount" id="payAmount" class="form-control" min="1" max="<?= (int)$invoice['balance'] ?>" value="<?= (int)$invoice['balance'] ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Payment Method</label>
          <select name="method" id="payMethod" class="form-select">
            <option value="momo">MoMo Pay</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
        <button type="button" onclick="confirmPayment()" class="btn-dmc w-100"><i class="bi bi-check2-circle me-1"></i> Confirm Payment Received</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= $invoice ? 'col-lg-7' : 'col-12' ?>">
    <!-- Select invoice -->
    <?php if (!$invoice): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Select Invoice to Confirm</div>
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
            <td><a href="?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success" style="font-size:11px"><i class="bi bi-check2 me-1"></i>Confirm Payment</a></td>
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
            <td><?= methodLabel($pay['method']) ?></td>
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
$extraScripts = "<script>
const invoiceId = " . ($invoice['id'] ?? 0) . ";
const patientId = " . ($invoice['patient_id'] ?? 0) . ";

function confirmPayment() {
  const amount = parseFloat(document.getElementById('payAmount').value);
  if (!amount || amount < 1) { toast('Enter a valid amount','warning'); return; }
  const method = document.getElementById('payMethod').value;
  const notes  = document.querySelector('[name=notes]').value;
  fetch('/dmc/api/ajax.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'collect_payment', invoice_id:invoiceId, patient_id:patientId, amount, method, notes})
  }).then(r=>r.json()).then(j => {
    if (j.ok) { toast('Payment confirmed successfully!'); setTimeout(()=>location.reload(), 1500); }
    else defaultErr(j);
  });
}
</script>";


?>

<?php include __DIR__ . '/../includes/footer.php';
