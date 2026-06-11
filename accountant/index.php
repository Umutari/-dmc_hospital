<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Billing & Finance';

$stats = [
    'today_revenue'  => scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at)=CURDATE()"),
    'month_revenue'  => scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())"),
    'pending_bills'  => scalar("SELECT COUNT(*) FROM invoices WHERE status IN('issued','partial')"),
    'pending_amount' => scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN('issued','partial')"),
];

$recentPayments = rows("SELECT p.*, i.invoice_no, CONCAT(pt.first_name,' ',pt.last_name) AS pname, pt.phone FROM payments p JOIN invoices i ON p.invoice_id=i.id JOIN patients pt ON p.patient_id=pt.id ORDER BY p.paid_at DESC LIMIT 8");
$pendingInvoices = rows("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.first_name, p.phone FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.status IN('draft','issued','partial') ORDER BY i.created_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Billing & Finance</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <div class="d-flex gap-2">
    <a href="/dmc/accountant/invoices.php?action=new" class="btn-dmc"><i class="bi bi-plus-circle"></i> New Invoice</a>
    <a href="/dmc/accountant/payments.php" class="btn-dmc-outline"><i class="bi bi-credit-card"></i> Payments</a>
  </div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div><div><div class="stat-label">Today Revenue</div><div class="stat-value" style="font-size:16px"><?= money($stats['today_revenue']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-bank"></i></div><div><div class="stat-label">Month Revenue</div><div class="stat-value" style="font-size:16px"><?= money($stats['month_revenue']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-receipt-cutoff"></i></div><div><div class="stat-label">Pending Bills</div><div class="stat-value"><?= $stats['pending_bills'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:15px"><?= money($stats['pending_amount']) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Pending Invoices <a href="/dmc/accountant/invoices.php">All invoices →</a></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Invoice</th><th>Patient</th><th>Total</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ($pendingInvoices): foreach ($pendingInvoices as $inv): ?>
          <tr>
            <td><a href="/dmc/accountant/invoices.php?id=<?= $inv['id'] ?>" style="font-size:12px;font-weight:600"><?= e($inv['invoice_no']) ?></a></td>
            <td><?= e($inv['pname']) ?></td>
            <td><?= money($inv['total']) ?></td>
            <td style="color:var(--danger);font-weight:600"><?= money($inv['balance']) ?></td>
            <td><span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td><button class="btn btn-sm btn-warning" style="font-size:11px" onclick="sendReminder(<?= $inv['id'] ?>)">Remind</button></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No pending invoices</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Payments <a href="/dmc/accountant/payments.php">All →</a></div>
      <?php foreach ($recentPayments as $pay): ?>
      <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:var(--bg)">
        <div class="stat-icon si-green" style="width:36px;height:36px;font-size:15px;border-radius:8px"><i class="bi bi-check-circle"></i></div>
        <div class="flex-1">
          <div style="font-size:12.5px;font-weight:600"><?= e($pay['pname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($pay['invoice_no']) ?> · <?= ucfirst(str_replace('_',' ',$pay['method'])) ?></div>
        </div>
        <div style="font-size:13px;font-weight:700;color:var(--success)"><?= money($pay['amount']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function sendReminder(invoiceId) {
  fetch('/dmc/api/ajax.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'send_reminder', invoice_id: invoiceId})
  }).then(r => r.json()).then(j => {
    if (j.ok) toast('Reminder sent to patient successfully!');
    else toast(j.error || 'Failed to send reminder', 'danger');
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
