<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Patients - Billing';

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $like   = "%$search%";
    $where  = "WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ?";
    $params = [$like,$like,$like,$like];
}

$patients = rows("SELECT p.*,
    (SELECT COUNT(*) FROM invoices WHERE patient_id=p.id) AS invoice_count,
    (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE patient_id=p.id AND status IN('issued','partial')) AS outstanding
    FROM patients p $where ORDER BY p.first_name LIMIT 80", $params);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Patient Billing</div><div class="page-sub">Manage patient invoices and payments</div></div>
</div>

<?= showFlash('main') ?>

<div class="dmc-card">
  <form class="d-flex gap-2 mb-3" method="GET">
    <input name="q" class="form-control" placeholder="Search patient..." value="<?= e($search) ?>" style="max-width:350px">
    <button type="submit" class="btn-dmc"><i class="bi bi-search"></i></button>
    <?php if ($search): ?><a href="/dmc/accountant/patients.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
  </form>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Patient</th><th>Phone</th><th>Invoices</th><th>Outstanding</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($patients as $p): ?>
      <tr>
        <td>
          <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($p['patient_no']) ?></div>
        </td>
        <td><?= e($p['phone']) ?></td>
        <td><?= $p['invoice_count'] ?></td>
        <td style="font-weight:600;color:<?= $p['outstanding']>0?'var(--danger)':'var(--success)' ?>"><?= money($p['outstanding']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/dmc/accountant/invoices.php?action=new" class="btn btn-sm btn-outline-primary" style="font-size:10px" onclick="sessionStorage.setItem('prePatId','<?= $p['id'] ?>')">Invoice</a>
            <?php if ($p['outstanding']>0): ?>
            <?php $inv = row("SELECT id FROM invoices WHERE patient_id=? AND status IN('issued','partial') ORDER BY created_at DESC LIMIT 1", [$p['id']]); ?>
            <?php if ($inv): ?>
            <a href="/dmc/accountant/payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success" style="font-size:10px">Pay</a>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$patients): ?><tr><td colspan="5" class="text-center text-muted py-4">No patients found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
