<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin','accountant']);
$pageTitle = 'Financial Report';

$period = $_GET['period'] ?? 'month';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
if ($period === 'today')    { $from = $to = date('Y-m-d'); }
elseif ($period === 'week') { $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); }
elseif ($period === 'month'){ $from = date('Y-m-01'); $to = date('Y-m-d'); }
elseif ($period === 'year') { $from = date('Y-01-01'); $to = date('Y-m-d'); }

/* prior period for comparison */
$diff = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
$prevTo   = date('Y-m-d', strtotime($from) - 86400);
$prevFrom = date('Y-m-d', strtotime($prevTo) - ($diff - 1) * 86400);

/* Key financials */
$revenue     = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ?", [$from,$to]);
$prevRevenue = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ?", [$prevFrom,$prevTo]);
$revChange   = $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 1) : 0;

$totalInvoiced  = (float)scalar("SELECT COALESCE(SUM(total),0) FROM invoices WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to]);
$totalPaid      = (float)scalar("SELECT COALESCE(SUM(paid),0) FROM invoices WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to]);
$outstanding    = (float)scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN('issued','partial')");
$invoiceCount   = (int)scalar("SELECT COUNT(*) FROM invoices WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to]);
$paidInvoices   = (int)scalar("SELECT COUNT(*) FROM invoices WHERE status='paid' AND DATE(created_at) BETWEEN ? AND ?", [$from,$to]);
$txnCount       = (int)scalar("SELECT COUNT(*) FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ?", [$from,$to]);

/* Revenue by day for chart */
$revenueByDay = rows("SELECT DATE(paid_at) AS d, SUM(amount) AS total FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY DATE(paid_at) ORDER BY d", [$from,$to]);

/* Payment method breakdown */
$byMethod = rows("SELECT method, SUM(amount) AS total, COUNT(*) AS txns FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY method ORDER BY total DESC", [$from,$to]);

/* Invoice aging */
$aging = rows("SELECT
    CASE
        WHEN status='paid' THEN 'Paid'
        WHEN status='draft' THEN 'Draft'
        WHEN status='cancelled' THEN 'Cancelled'
        WHEN DATEDIFF(NOW(), created_at) <= 30 THEN '0-30 days'
        WHEN DATEDIFF(NOW(), created_at) <= 60 THEN '31-60 days'
        WHEN DATEDIFF(NOW(), created_at) <= 90 THEN '61-90 days'
        ELSE 'Over 90 days'
    END AS bucket,
    COUNT(*) AS cnt, COALESCE(SUM(balance),0) AS outstanding
    FROM invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY bucket ORDER BY FIELD(bucket,'Paid','Draft','0-30 days','31-60 days','61-90 days','Over 90 days','Cancelled')", [$from,$to]);

/* Top patients by spend */
$topPatients = rows("SELECT CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    COUNT(DISTINCT pay.id) AS txns, SUM(pay.amount) AS total
    FROM payments pay JOIN patients p ON pay.patient_id=p.id
    WHERE pay.status='success' AND DATE(pay.paid_at) BETWEEN ? AND ?
    GROUP BY p.id ORDER BY total DESC LIMIT 10", [$from,$to]);

/* Top services */
$topServices = rows("SELECT ii.description, SUM(ii.total_price) AS total, COUNT(*) AS cnt
    FROM invoice_items ii JOIN invoices inv ON ii.invoice_id=inv.id
    WHERE DATE(inv.created_at) BETWEEN ? AND ?
    GROUP BY ii.description ORDER BY total DESC LIMIT 8", [$from,$to]);

/* Department revenue — attributed to department of the invoice creator */
$deptRevenue = rows("SELECT d.name AS dept, COALESCE(SUM(pay.amount),0) AS total, COUNT(DISTINCT pay.patient_id) AS patients
    FROM payments pay
    JOIN invoices inv ON pay.invoice_id=inv.id
    JOIN users u ON inv.created_by=u.id
    JOIN doctors doc ON u.id=doc.user_id
    JOIN departments d ON doc.department_id=d.id
    WHERE pay.status='success' AND DATE(pay.paid_at) BETWEEN ? AND ?
    GROUP BY d.id ORDER BY total DESC LIMIT 6", [$from,$to]);

/* Recent transactions */
$recentTxns = rows("SELECT pay.*, inv.invoice_no, CONCAT(p.first_name,' ',p.last_name) AS pname
    FROM payments pay
    JOIN invoices inv ON pay.invoice_id=inv.id
    JOIN patients p ON pay.patient_id=p.id
    WHERE pay.status='success' AND DATE(pay.paid_at) BETWEEN ? AND ?
    ORDER BY pay.paid_at DESC LIMIT 30", [$from,$to]);

/* chart data */
$chartDays = []; $chartVals = [];
$dayCount = min($diff, 60);
for ($i = $dayCount - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($to) - $i * 86400);
    $chartDays[] = date('M d', strtotime($d));
    $found = array_filter($revenueByDay, fn($r) => $r['d'] === $d);
    $chartVals[] = $found ? (float)array_values($found)[0]['total'] : 0;
}

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Financial Report</div>
    <div class="page-sub"><?= dateF($from) ?> — <?= dateF($to) ?></div>
  </div>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn-dmc-outline"><i class="bi bi-printer"></i> Print</button>
    <a href="/dmc/accountant/invoices.php" class="btn-dmc-outline"><i class="bi bi-receipt"></i> Invoices</a>
  </div>
</div>

<!-- Period filter -->
<div class="dmc-card mb-3">
  <form class="d-flex gap-2 flex-wrap align-items-end" method="GET">
    <div class="d-flex gap-1 flex-wrap">
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
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="stat-label">Revenue Collected</div>
        <div class="stat-value" style="font-size:14px"><?= money($revenue) ?></div>
        <?php if ($prevRevenue > 0): ?>
        <div style="font-size:10.5px;margin-top:2px;color:<?= $revChange>=0?'var(--success)':'var(--danger)' ?>">
          <i class="bi bi-arrow-<?= $revChange>=0?'up':'down' ?>"></i> <?= abs($revChange) ?>% vs prev period
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-label">Total Invoiced</div><div class="stat-value" style="font-size:14px"><?= money($totalInvoiced) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-exclamation-circle"></i></div>
      <div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:13px;color:<?= $outstanding>0?'var(--danger)':'' ?>"><?= money($outstanding) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-file-earmark-check"></i></div>
      <div><div class="stat-label">Invoices</div><div class="stat-value"><?= $invoiceCount ?></div>
      <div style="font-size:10.5px;color:var(--muted)"><?= $paidInvoices ?> paid</div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="bi bi-credit-card"></i></div>
      <div><div class="stat-label">Transactions</div><div class="stat-value"><?= $txnCount ?></div></div>
    </div>
  </div>
</div>

<!-- Revenue chart -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Revenue Trend</div>
      <canvas id="revChart" height="85"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Payment Methods</div>
      <canvas id="methodChart" height="160"></canvas>
      <div class="mt-2">
        <?php foreach ($byMethod as $bm): ?>
        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12px">
          <span><?= ucfirst(str_replace('_',' ',$bm['method'])) ?></span>
          <strong><?= money($bm['total']) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if (!$byMethod): ?><div class="text-center text-muted py-2" style="font-size:12px">No payment data</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Invoice aging -->
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">Invoice Status / Aging</div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Status / Age</th><th>Count</th><th>Outstanding</th></tr></thead>
          <tbody>
          <?php foreach ($aging as $ag): ?>
          <tr>
            <td><?= e($ag['bucket']) ?></td>
            <td><?= $ag['cnt'] ?></td>
            <td style="<?= $ag['outstanding']>0?'color:var(--danger);font-weight:600':'' ?>"><?= money($ag['outstanding']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$aging): ?><tr><td colspan="3" class="text-center text-muted">No invoices in this period</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top services -->
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Top Services by Revenue</div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead><tr><th>Service</th><th>Count</th><th>Revenue</th><th>% Share</th></tr></thead>
          <tbody>
          <?php $totalSvc = array_sum(array_column($topServices,'total')); ?>
          <?php foreach ($topServices as $svc): ?>
          <tr>
            <td><?= e($svc['description']) ?></td>
            <td><?= $svc['cnt'] ?></td>
            <td style="font-weight:600"><?= money($svc['total']) ?></td>
            <td>
              <div style="font-size:11px"><?= $totalSvc>0?round($svc['total']/$totalSvc*100,1):0 ?>%</div>
              <div style="background:var(--bg);border-radius:4px;height:5px;width:80px">
                <div style="background:var(--brand2);border-radius:4px;height:5px;width:<?= $totalSvc>0?min(80,round($svc['total']/$totalSvc*80)):0 ?>px"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$topServices): ?><tr><td colspan="4" class="text-center text-muted">No service data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Top patients -->
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">Top Patients by Spend</div>
      <?php if ($topPatients): foreach ($topPatients as $i => $pt): ?>
      <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:12.5px">
        <div class="d-flex align-items-center gap-2">
          <span style="background:var(--brand);color:#fff;border-radius:50%;width:20px;height:20px;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700"><?= $i+1 ?></span>
          <div>
            <div style="font-weight:600"><?= e($pt['pname']) ?></div>
            <div style="font-size:10px;color:var(--muted)"><?= e($pt['patient_no']) ?> · <?= $pt['txns'] ?> txns</div>
          </div>
        </div>
        <strong style="font-size:13px"><?= money($pt['total']) ?></strong>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:12px">No payment data for this period</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Department revenue -->
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Revenue by Department</div>
      <?php if ($deptRevenue): ?>
      <?php $maxDept = max(array_column($deptRevenue,'total')); ?>
      <?php foreach ($deptRevenue as $dr): ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between" style="font-size:12.5px">
          <span><?= e($dr['dept']) ?></span>
          <strong><?= money($dr['total']) ?></strong>
        </div>
        <div style="background:var(--bg);border-radius:4px;height:8px;margin-top:3px">
          <div style="background:var(--brand);border-radius:4px;height:8px;width:<?= $maxDept>0?round($dr['total']/$maxDept*100):0 ?>%"></div>
        </div>
        <div style="font-size:10px;color:var(--muted)"><?= $dr['patients'] ?> patients</div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="text-center text-muted py-3" style="font-size:12px">Insufficient data to show department breakdown</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Transactions table -->
<div class="dmc-card">
  <div class="dmc-card-title">Payment Transactions <span style="font-size:12px;font-weight:400;color:var(--muted)">(<?= count($recentTxns) ?> records)</span></div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0" style="font-size:12.5px" id="txnTable">
      <thead>
        <tr>
          <th>Payment Ref</th>
          <th>Patient</th>
          <th>Invoice</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Date/Time</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($recentTxns): foreach ($recentTxns as $t): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= e($t['payment_no']) ?></td>
        <td><?= e($t['pname']) ?></td>
        <td style="font-family:monospace;font-size:11px"><?= e($t['invoice_no']) ?></td>
        <td style="font-weight:600"><?= money($t['amount']) ?></td>
        <td><?= ucfirst(str_replace('_',' ',$t['method'])) ?></td>
        <td style="font-size:11px"><?= dtF($t['paid_at']) ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  .dmc-sidebar, .dmc-topbar, .page-header .btn-dmc, .page-header .btn-dmc-outline, form { display:none!important; }
  .dmc-main { margin:0!important; padding:0!important; }
}
</style>

<?php
$methodLabels = array_map(fn($m) => ucfirst(str_replace('_',' ',$m['method'])), $byMethod);
$methodValues = array_column($byMethod, 'total');
$extraScripts = "<script>
new Chart(document.getElementById('revChart'),{
  type:'bar',
  data:{
    labels:".json_encode($chartDays).",
    datasets:[{
      label:'Revenue (RWF)',
      data:".json_encode($chartVals).",
      backgroundColor:'rgba(26,107,181,.7)',
      borderColor:'#1A6BB5',
      borderWidth:1,
      borderRadius:4
    }]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'RWF '+v.toLocaleString()}},x:{ticks:{maxRotation:45,font:{size:10}}}}}
});
".($methodValues ? "new Chart(document.getElementById('methodChart'),{
  type:'doughnut',
  data:{
    labels:".json_encode($methodLabels).",
    datasets:[{data:".json_encode(array_map('floatval',$methodValues)).",backgroundColor:['#1A6BB5','#0E6655','#E17B10','#6D28D9','#D14A30']}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}}}}
});" : "document.getElementById('methodChart').parentElement.innerHTML='<div class=\"text-center text-muted py-4\" style=\"font-size:12px\">No payment data</div>';")."
</script>";
include __DIR__ . '/../includes/footer.php';
