<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'SMS Logs';

$dateFilter = $_GET['date'] ?? '';
$status     = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM sms_logs";
$p   = [];
$conds = [];
if ($dateFilter) { $conds[] = "DATE(sent_at)=?"; $p[] = $dateFilter; }
if ($status !== 'all') { $conds[] = "status=?"; $p[] = $status; }
if ($conds) $sql .= " WHERE ".implode(' AND ',$conds);
$sql .= " ORDER BY sent_at DESC LIMIT 100";
$logs = rows($sql, $p);

$total   = scalar("SELECT COUNT(*) FROM sms_logs");
$sent    = scalar("SELECT COUNT(*) FROM sms_logs WHERE status='sent'");
$failed  = scalar("SELECT COUNT(*) FROM sms_logs WHERE status='failed'");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">SMS Logs</div><div class="page-sub">Monitor outgoing SMS and WhatsApp messages</div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-4"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-chat-dots"></i></div><div><div class="stat-label">Total</div><div class="stat-value"><?= number_format($total) ?></div></div></div></div>
  <div class="col-4"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-check-circle"></i></div><div><div class="stat-label">Sent</div><div class="stat-value"><?= number_format($sent) ?></div></div></div></div>
  <div class="col-4"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-x-circle"></i></div><div><div class="stat-label">Failed</div><div class="stat-value"><?= number_format($failed) ?></div></div></div></div>
</div>

<div class="dmc-card mb-3">
  <form class="d-flex gap-2 flex-wrap align-items-end" method="GET">
    <div><label class="form-label" style="font-size:12px">Date</label><input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>"></div>
    <div>
      <label class="form-label" style="font-size:12px">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="all">All</option>
        <option value="sent" <?= $status==='sent'?'selected':'' ?>>Sent</option>
        <option value="failed" <?= $status==='failed'?'selected':'' ?>>Failed</option>
      </select>
    </div>
    <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
    <a href="/dmc/admin/sms_logs.php" class="btn btn-sm btn-outline-secondary" style="align-self:flex-end">Clear</a>
  </form>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0" style="font-size:12.5px">
      <thead><tr><th>Recipient</th><th>Channel</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td style="font-family:monospace"><?= e($log['recipient_phone']) ?></td>
        <td><span class="badge-status <?= $log['channel']==='sms'?'bs-issued':'bs-active' ?>"><?= ucfirst($log['channel']) ?></span></td>
        <td style="max-width:350px;white-space:pre-wrap;font-size:11.5px"><?= e(substr($log['message'],0,120)) ?><?= strlen($log['message'])>120?'…':'' ?></td>
        <td><span class="badge-status <?= $log['status']==='sent'?'bs-active':'bs-cancelled' ?>"><?= ucfirst($log['status']) ?></span></td>
        <td style="font-size:11px"><?= dtF($log['sent_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">No SMS logs found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
