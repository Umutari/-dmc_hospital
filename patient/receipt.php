<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin','accountant']);

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
if (!$invoiceId) { echo 'Invalid invoice.'; exit; }

$inv = row("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone, p.address, p.insurance_provider
    FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id=?", [$invoiceId]);
if (!$inv) { echo 'Invoice not found.'; exit; }

/* patients can only see their own */
if (currentRole() === 'patient') {
    $uid     = currentUserId();
    $user    = row("SELECT email, phone FROM users WHERE id=?", [$uid]);
    $patient = row("SELECT id FROM patients WHERE email=? OR phone=?", [$user['email']??'', $user['phone']??'']);
    if (!$patient || $patient['id'] != $inv['patient_id']) { echo 'Access denied.'; exit; }
}

$items    = rows("SELECT * FROM invoice_items WHERE invoice_id=?", [$invoiceId]);
$payments = rows("SELECT * FROM payments WHERE invoice_id=? AND status='success' ORDER BY paid_at", [$invoiceId]);
$totalPaid = array_sum(array_column($payments, 'amount'));
$balance   = max(0, $inv['total'] - $totalPaid);
$clinic    = setting('clinic_name') ?: 'DMC Hospital';
$address   = setting('clinic_address') ?: 'KK 541 St, Kigali, Rwanda';
$phone     = setting('clinic_phone') ?: '0782 749 660';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt — <?= e($inv['invoice_no']) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #222; padding: 30px; }
  .header { text-align:center; border-bottom: 2px solid #0A2342; padding-bottom: 16px; margin-bottom: 20px; }
  .header h1 { font-size: 22px; color: #0A2342; font-weight: 800; }
  .header p  { font-size: 11px; color: #666; margin-top: 2px; }
  .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
  .badge-paid    { background:#d1fae5; color:#065f46; }
  .badge-partial { background:#fef3c7; color:#92400e; }
  .badge-issued  { background:#fee2e2; color:#991b1b; }
  .section { margin-bottom: 18px; }
  .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 6px; font-weight: 600; }
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .info-box { background: #f8f9fa; border-radius: 6px; padding: 12px; }
  .info-box p { margin-bottom: 4px; }
  .info-box .label { color: #888; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #0A2342; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
  td { padding: 7px 10px; border-bottom: 1px solid #eee; }
  tfoot td { background: #f8f9fa; font-weight: 600; }
  .total-row { font-size: 14px; }
  .balance-row td { color: <?= $balance > 0 ? '#dc2626' : '#065f46' ?>; font-size: 15px; }
  .pay-history { margin-top: 16px; }
  .footer { margin-top: 24px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #eee; padding-top: 12px; }
  .paid-stamp { text-align:center; margin: 16px 0; }
  .paid-stamp span { display:inline-block; border: 3px solid #065f46; color: #065f46; padding: 6px 28px; font-size: 20px; font-weight: 800; letter-spacing: 4px; border-radius: 4px; transform: rotate(-5deg); }
  @media print {
    body { padding: 10px; }
    .no-print { display: none; }
  }
</style>
</head>
<body>

<div class="no-print" style="text-align:right;margin-bottom:16px">
  <button onclick="window.print()" style="background:#0A2342;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px">
    &#128438; Print Receipt
  </button>
  <button onclick="window.close()" style="background:#eee;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;margin-left:8px">
    Close
  </button>
</div>

<div class="header">
  <h1>&#127973; <?= e($clinic) ?></h1>
  <p><?= e($address) ?> &nbsp;|&nbsp; <?= e($phone) ?></p>
  <p style="margin-top:6px;font-size:13px;font-weight:600;color:#0A2342">PAYMENT RECEIPT</p>
</div>

<div class="two-col section">
  <div class="info-box">
    <div class="section-title">Invoice Details</div>
    <p><span class="label">Invoice No:</span> <strong><?= e($inv['invoice_no']) ?></strong></p>
    <p><span class="label">Date Issued:</span> <?= dtF($inv['created_at']) ?></p>
    <?php if ($inv['due_date']): ?>
    <p><span class="label">Due Date:</span> <?= dateF($inv['due_date']) ?></p>
    <?php endif; ?>
    <p style="margin-top:6px">
      <span class="badge badge-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
    </p>
  </div>
  <div class="info-box">
    <div class="section-title">Patient</div>
    <p><strong><?= e($inv['pname']) ?></strong></p>
    <p><span class="label">Patient No:</span> <?= e($inv['patient_no']) ?></p>
    <p><span class="label">Phone:</span> <?= e($inv['phone']) ?></p>
    <?php if ($inv['address']): ?><p><span class="label">Address:</span> <?= e($inv['address']) ?></p><?php endif; ?>
    <?php if ($inv['insurance_provider']): ?><p><span class="label">Insurance:</span> <?= e($inv['insurance_provider']) ?></p><?php endif; ?>
  </div>
</div>

<!-- Line items -->
<div class="section">
  <div class="section-title">Bill Items</div>
  <table>
    <thead>
      <tr><th>Description</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['description']) ?></td>
        <td style="text-align:center"><?= $it['quantity'] ?></td>
        <td style="text-align:right"><?= money($it['unit_price']) ?></td>
        <td style="text-align:right"><strong><?= money($it['total_price']) ?></strong></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
      <tr><td colspan="4" style="color:#888;text-align:center">No items listed</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="total-row"><td colspan="3" style="text-align:right">Total Billed</td><td style="text-align:right"><?= money($inv['total']) ?></td></tr>
      <tr><td colspan="3" style="text-align:right;color:#065f46">Amount Paid</td><td style="text-align:right;color:#065f46"><?= money($totalPaid) ?></td></tr>
      <tr class="balance-row"><td colspan="3" style="text-align:right">Balance Due</td><td style="text-align:right"><?= money($balance) ?></td></tr>
    </tfoot>
  </table>
</div>

<?php if ($balance <= 0): ?>
<div class="paid-stamp"><span>PAID IN FULL</span></div>
<?php endif; ?>

<!-- Payment history -->
<?php if ($payments): ?>
<div class="pay-history">
  <div class="section-title">Payment Transactions</div>
  <table>
    <thead>
      <tr><th>Reference</th><th>Method</th><th>Amount</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php foreach ($payments as $py): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($py['payment_no']) ?></td>
        <td><?= ucfirst(str_replace('_',' ',$py['method'])) ?></td>
        <td style="color:#065f46;font-weight:600"><?= money($py['amount']) ?></td>
        <td style="font-size:11px"><?= dtF($py['paid_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($inv['notes']): ?>
<div class="section" style="margin-top:16px">
  <div class="section-title">Notes</div>
  <p style="color:#555"><?= e($inv['notes']) ?></p>
</div>
<?php endif; ?>

<div class="footer">
  <p>Thank you for choosing <?= e($clinic) ?> &nbsp;·&nbsp; <?= e($address) ?></p>
  <p style="margin-top:4px">This is a computer-generated receipt. No signature required.</p>
  <p style="margin-top:4px">Generated: <?= date('d M Y, h:i A') ?></p>
</div>

</body>
</html>
