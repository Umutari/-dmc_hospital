<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin','accountant']);
$pageTitle = 'Reports & Analytics';

$period = $_GET['period'] ?? 'month';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');

if ($period === 'today')    { $from = $to = date('Y-m-d'); }
elseif ($period === 'week') { $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); }
elseif ($period === 'month'){ $from = date('Y-m-01'); $to = date('Y-m-d'); }
elseif ($period === 'year') { $from = date('Y-01-01'); $to = date('Y-m-d'); }

/* Key metrics */
$revenue   = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ?", [$from,$to]);
$newPats   = (int)scalar("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to]);
$appts     = (int)scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?", [$from,$to]);
$dispensed = (int)scalar("SELECT COUNT(*) FROM prescriptions WHERE status='dispensed' AND DATE(dispensed_at) BETWEEN ? AND ?", [$from,$to]);
$outstanding = (float)scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN('issued','partial')");

/* Revenue by day for chart — use the same period as the KPI cards */
$revenueChart = rows("SELECT DATE(paid_at) AS day, SUM(amount) AS total FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY DATE(paid_at) ORDER BY day", [$from, $to]);

/* Revenue by payment method */
$byMethod = rows("SELECT method, SUM(amount) AS total, COUNT(*) AS txns FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY method ORDER BY total DESC", [$from,$to]);

/* Top services */
$topServices = rows("SELECT description, SUM(total_price) AS total, COUNT(*) AS cnt FROM invoice_items ii JOIN invoices inv ON ii.invoice_id=inv.id WHERE DATE(inv.created_at) BETWEEN ? AND ? GROUP BY description ORDER BY total DESC LIMIT 8", [$from,$to]);

/* Appointments by status */
$apptStatus = rows("SELECT status, COUNT(*) AS cnt FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status", [$from,$to]);

/* Recent payments */
$recentPays = rows("SELECT pay.*, i.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname FROM payments pay JOIN invoices i ON pay.invoice_id=i.id JOIN patients p ON pay.patient_id=p.id WHERE pay.status='success' AND DATE(pay.paid_at) BETWEEN ? AND ? ORDER BY pay.paid_at DESC LIMIT 20", [$from,$to]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Reports & Analytics</div><div class="page-sub"><?= dateF($from) ?> — <?= dateF($to) ?></div></div>
  <button onclick="window.print()" class="btn-dmc-outline"><i class="bi bi-printer"></i> Print</button>
</div>

<!-- Period filter -->
<div class="dmc-card mb-3">
  <form class="d-flex gap-2 flex-wrap align-items-end" method="GET">
    <div class="d-flex gap-1">
      <?php foreach(['today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $p=>$l): ?>
      <a href="?period=<?= $p ?>" class="btn btn-sm <?= $period===$p?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($period==='custom'): ?>
    <input type="hidden" name="period" value="custom">
    <div><label class="form-label" style="font-size:12px">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>"></div>
    <div><label class="form-label" style="font-size:12px">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>"></div>
    <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Apply</button>
    <?php endif; ?>
  </form>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div><div><div class="stat-label">Revenue</div><div class="stat-value" style="font-size:14px"><?= money($revenue) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-person-plus"></i></div><div><div class="stat-label">New Patients</div><div class="stat-value"><?= $newPats ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-calendar-check"></i></div><div><div class="stat-label">Appointments</div><div class="stat-value"><?= $appts ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-purple"><i class="bi bi-bag-check"></i></div><div><div class="stat-label">Dispensed Rx</div><div class="stat-value"><?= $dispensed ?></div></div></div></div>
  <div class="col-6 col-md"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:13px"><?= money($outstanding) ?></div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <!-- Revenue trend chart -->
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Revenue Trend</div>
      <canvas id="revenueChart" height="90"></canvas>
    </div>
  </div>

  <!-- Payment method breakdown -->
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Revenue by Method</div>
      <?php foreach ($byMethod as $bm): ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between" style="font-size:12.5px">
          <span><?= ucfirst(str_replace('_',' ',$bm['method'])) ?></span>
          <strong><?= money($bm['total']) ?></strong>
        </div>
        <div style="background:var(--bg);border-radius:4px;height:8px;margin-top:4px">
          <div style="background:var(--brand2);border-radius:4px;height:8px;width:<?= $revenue>0?min(100,round($bm['total']/$revenue*100)):0 ?>%"></div>
        </div>
        <div style="font-size:10px;color:var(--muted)"><?= $bm['txns'] ?> transactions</div>
      </div>
      <?php endforeach; ?>
      <?php if (!$byMethod): ?><div class="text-center text-muted py-3" style="font-size:13px">No payment data</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Top services -->
  <div class="col-lg-6">
    <div class="dmc-card">
      <div class="dmc-card-title">Top Services</div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Service</th><th>Count</th><th>Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($topServices as $svc): ?>
          <tr>
            <td><?= e($svc['description']) ?></td>
            <td><?= $svc['cnt'] ?></td>
            <td style="font-weight:600"><?= money($svc['total']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$topServices): ?><tr><td colspan="3" class="text-center text-muted">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Appointment status -->
  <div class="col-lg-6">
    <div class="dmc-card">
      <div class="dmc-card-title">Appointment Status</div>
      <canvas id="apptChart" height="160"></canvas>
    </div>
  </div>
</div>

<!-- Recent payments table -->
<div class="dmc-card">
  <div class="dmc-card-title">Payment Transactions</div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0" style="font-size:12.5px">
      <thead><tr><th>Ref</th><th>Patient</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recentPays as $pay): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($pay['payment_no']) ?></td>
        <td><?= e($pay['pname']) ?></td>
        <td><?= e($pay['invoice_no']) ?></td>
        <td style="font-weight:600"><?= money($pay['amount']) ?></td>
        <td><?= ucfirst(str_replace('_',' ',$pay['method'])) ?></td>
        <td><?= dtF($pay['paid_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recentPays): ?><tr><td colspan="6" class="text-center text-muted py-3">No payments in this period</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
/* prepare chart data — same period as KPI cards, gaps filled with 0, max 60 points */
$revenueMap  = array_column($revenueChart, 'total', 'day');
$chartDiff   = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
$maxPts      = min($chartDiff, 60);
$days = []; $revVals = [];
for ($i = $maxPts - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($to) - $i * 86400);
    $days[]    = date('M d', strtotime($d));
    $revVals[] = (float)($revenueMap[$d] ?? 0);
}
$apptLabels = array_column($apptStatus,'status');
$apptData   = array_column($apptStatus,'cnt');

$extraScripts = "<script>
/* Revenue line chart */
new Chart(document.getElementById('revenueChart'),{
  type:'line',
  data:{labels:".json_encode($days).",datasets:[{label:'Revenue (RWF)',data:".json_encode($revVals).",borderColor:'#1A6BB5',backgroundColor:'rgba(26,107,181,.1)',fill:true,tension:.4,pointRadius:2}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'RWF '+v.toLocaleString()}},x:{ticks:{maxRotation:45,font:{size:10}}}}}
});
/* Appt doughnut */
new Chart(document.getElementById('apptChart'),{
  type:'doughnut',
  data:{labels:".json_encode($apptLabels).",datasets:[{data:".json_encode($apptData).",backgroundColor:['#3B82F6','#10B981','#6366F1','#EF4444']}]},
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:12}}}}}
});
</script>";
include __DIR__ . '/../includes/footer.php';
