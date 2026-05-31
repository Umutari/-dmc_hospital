<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Insurance Claims';

$claims = rows("SELECT ic.*, i.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname, ip.coverage_percentage
    FROM insurance_claims ic
    JOIN invoices i ON ic.invoice_id = i.id
    JOIN patients p ON ic.patient_id = p.id
    JOIN insurance_providers ip ON ic.insurance_provider = ip.name
    ORDER BY ic.claim_date DESC");

$stats = row("SELECT
    COUNT(*) AS total_claims,
    SUM(CASE WHEN insurance_status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN insurance_status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN insurance_status='paid' THEN 1 ELSE 0 END) AS paid,
    SUM(insurance_amount) AS total_insurance_amount
    FROM insurance_claims");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['act'] ?? '';
    $claimId = (int)($_POST['claim_id'] ?? 0);

    if ($action === 'update_status' && $claimId) {
        $status = trim($_POST['status'] ?? '');
        if (in_array($status, ['pending', 'approved', 'rejected', 'paid'])) {
            execute("UPDATE insurance_claims SET insurance_status=?, approval_date=NOW() WHERE id=?", [$status, $claimId]);
            flash('main', 'Claim status updated successfully.');
        }
    }
    header('Location: /dmc/accountant/insurance.php'); exit;
}

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Insurance Claims</div><div class="page-sub">Track and manage insurance claims</div></div>
</div>

<?= showFlash('main') ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-value"><?= $stats['total_claims'] ?? 0 ?></div>
      <div class="stat-label">Total Claims</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-value" style="color:var(--success)"><?= $stats['approved'] ?? 0 ?></div>
      <div class="stat-label">Approved</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-value" style="color:var(--brand)"><?= $stats['paid'] ?? 0 ?></div>
      <div class="stat-label">Paid</div>
    </div>
  </div>
</div>

<!-- Claims Table -->
<div class="dmc-card">
  <div class="dmc-card-title">All Insurance Claims</div>
  <div class="table-responsive">
    <table class="table dmc-table">
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Patient</th>
          <th>Insurance</th>
          <th>Total Amount</th>
          <th>Insurance Amount</th>
          <th>Patient Amount</th>
          <th>Status</th>
          <th>Claim Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($claims as $c): ?>
        <tr>
          <td><strong><?= e($c['invoice_no']) ?></strong></td>
          <td><?= e($c['pname']) ?></td>
          <td><span class="badge bg-light text-dark"><?= e($c['insurance_provider']) ?> (<?= $c['coverage_percentage'] ?>%)</span></td>
          <td><?= money($c['total_amount']) ?></td>
          <td style="color:var(--brand);font-weight:600"><?= money($c['insurance_amount']) ?></td>
          <td><?= money($c['patient_amount']) ?></td>
          <td>
            <span class="badge-status bs-<?= str_replace('_', '-', $c['insurance_status']) ?>">
              <?= ucfirst(str_replace('_', ' ', $c['insurance_status'])) ?>
            </span>
          </td>
          <td><?= dateF($c['claim_date']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="update_status">
              <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
              <select name="status" class="form-select" style="font-size:12px;padding:3px 6px" onchange="this.form.submit()">
                <option value="">Change Status</option>
                <option value="pending" <?= $c['insurance_status']==='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $c['insurance_status']==='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $c['insurance_status']==='rejected'?'selected':'' ?>>Rejected</option>
                <option value="paid" <?= $c['insurance_status']==='paid'?'selected':'' ?>>Paid</option>
              </select>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$claims): ?>
    <div class="text-center py-4 text-muted"><i class="bi bi-inbox me-2"></i>No insurance claims yet</div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
