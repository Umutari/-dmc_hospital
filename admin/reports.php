<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'Reports';

/* ── Date range ── */
$preset  = $_GET['range'] ?? '30d';
$customF = $_GET['from']  ?? '';
$customT = $_GET['to']    ?? '';

switch ($preset) {
    case '7d':  $from = date('Y-m-d', strtotime('-6 days')); $to = date('Y-m-d'); break;
    case 'mtd': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'custom':
        $from = $customF ?: date('Y-m-01');
        $to   = $customT ?: date('Y-m-d');
        break;
    default: // 30d
        $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d');
}

/* ── Summary stats ── */
$totalRevenue   = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ?", [$from, $to]);
$newPatients    = (int)scalar("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
$totalAppts     = (int)scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?", [$from, $to]);
$outstanding    = (float)scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN('issued','partial')");

/* ── Revenue per day ── */
$revRows = rows("SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY DATE(paid_at) ORDER BY d", [$from, $to]);
$revMap  = array_column($revRows, 'total', 'd');

/* ── New patients per day ── */
$patRows = rows("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM patients WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(d) ORDER BY d", [$from, $to]);
$patMap  = array_column($patRows, 'cnt', 'd');

/* fill every day in range */
$days = []; $revValues = []; $patValues = []; $labels = [];
$cur = strtotime($from);
$end = strtotime($to);
while ($cur <= $end) {
    $d          = date('Y-m-d', $cur);
    $days[]     = $d;
    $labels[]   = date('d M', $cur);
    $revValues[]= (float)($revMap[$d] ?? 0);
    $patValues[]= (int)($patMap[$d]   ?? 0);
    $cur        = strtotime('+1 day', $cur);
}

/* ── Revenue by payment method ── */
$methodData = rows("SELECT method, COALESCE(SUM(amount),0) AS total FROM payments WHERE status='success' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY method ORDER BY total DESC", [$from, $to]);

/* ── Outstanding balances by patient ── */
$outstandingList = rows(
    "SELECT i.invoice_no, i.balance, i.created_at, i.status,
            CONCAT(p.first_name,' ',p.last_name) AS pname, p.phone, p.patient_no
     FROM invoices i JOIN patients p ON i.patient_id=p.id
     WHERE i.status IN('issued','partial') AND i.balance > 0
     ORDER BY i.balance DESC"
);

/* ── Top days ── */
$peakRevDay = $revRows ? $revRows[array_search(max(array_column($revRows,'total')), array_column($revRows,'total'))] : null;

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Reports</div>
    <div class="page-sub">
      <?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?>
    </div>
  </div>
</div>

<!-- Date range filter -->
<div class="dmc-card mb-4">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label" style="font-size:11px;margin-bottom:4px">Quick Range</label>
      <div class="d-flex gap-1">
        <?php foreach (['7d'=>'Last 7 Days','30d'=>'Last 30 Days','mtd'=>'This Month','custom'=>'Custom'] as $k=>$lbl): ?>
        <a href="?range=<?= $k ?><?= $k==='custom'?'&from='.$from.'&to='.$to:'' ?>"
           class="btn btn-sm <?= $preset===$k?'btn-primary':'btn-outline-secondary' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($preset === 'custom'): ?>
    <div>
      <label class="form-label" style="font-size:11px;margin-bottom:4px">From</label>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
    </div>
    <div>
      <label class="form-label" style="font-size:11px;margin-bottom:4px">To</label>
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
    </div>
    <input type="hidden" name="range" value="custom">
    <button type="submit" class="btn-dmc btn-sm">Apply</button>
    <?php endif; ?>
  </form>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="stat-label">Revenue</div>
        <div class="stat-value" style="font-size:15px"><?= money($totalRevenue) ?></div>
        <div style="font-size:10px;color:var(--muted)">in selected period</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-person-plus-fill"></i></div>
      <div>
        <div class="stat-label">New Patients</div>
        <div class="stat-value"><?= $newPatients ?></div>
        <div style="font-size:10px;color:var(--muted)">registered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-calendar-check-fill"></i></div>
      <div>
        <div class="stat-label">Appointments</div>
        <div class="stat-value"><?= number_format($totalAppts) ?></div>
        <div style="font-size:10px;color:var(--muted)">in selected period</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon si-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div>
        <div class="stat-label">Outstanding</div>
        <div class="stat-value" style="font-size:15px"><?= money($outstanding) ?></div>
        <div style="font-size:10px;color:var(--muted)"><?= count($outstandingList) ?> unpaid invoices</div>
      </div>
    </div>
  </div>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="dmc-card h-100">
      <div class="dmc-card-title"><i class="bi bi-graph-up-arrow me-2"></i>Revenue Per Day</div>
      <canvas id="revChart" height="90"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="dmc-card h-100">
      <div class="dmc-card-title"><i class="bi bi-credit-card me-2"></i>Revenue by Method</div>
      <?php if ($methodData): ?>
      <canvas id="methodChart" height="180"></canvas>
      <?php else: ?>
      <p class="text-muted text-center py-4" style="font-size:13px">No payments in this period</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-people me-2"></i>New Patients Per Day</div>
      <canvas id="patChart" height="60"></canvas>
    </div>
  </div>
</div>

<!-- Outstanding balances table -->
<div class="dmc-card">
  <div class="dmc-card-title"><i class="bi bi-exclamation-circle me-2"></i>Outstanding Balances
    <span class="badge bg-danger ms-1"><?= count($outstandingList) ?></span>
  </div>
  <?php if ($outstandingList): ?>
  <div class="table-responsive">
    <table class="table dmc-table mb-0" id="outTable">
      <thead>
        <tr>
          <th>Patient</th>
          <th>Patient No</th>
          <th>Phone</th>
          <th>Invoice</th>
          <th>Status</th>
          <th>Invoice Date</th>
          <th>Days Overdue</th>
          <th>Balance</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($outstandingList as $r): ?>
      <?php $daysOverdue = (int)floor((time() - strtotime($r['created_at'])) / 86400); ?>
      <tr>
        <td><strong><?= e($r['pname']) ?></strong></td>
        <td style="font-size:12px;color:var(--muted)"><?= e($r['patient_no']) ?></td>
        <td style="font-size:12px"><?= e($r['phone']) ?></td>
        <td style="font-size:12px;font-family:monospace"><a href="/dmc/accountant/invoices.php?id=<?= $r['invoice_no'] ?>"><?= e($r['invoice_no']) ?></a></td>
        <td><span class="badge-status bs-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        <td style="font-size:12px"><?= dateF($r['created_at']) ?></td>
        <td>
          <span style="font-size:12px;font-weight:600;color:<?= $daysOverdue > 30 ? 'var(--danger)' : ($daysOverdue > 7 ? '#E17B10' : 'var(--muted)') ?>">
            <?= $daysOverdue ?> days
          </span>
        </td>
        <td style="font-weight:700;color:var(--danger)"><?= money($r['balance']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="7" style="text-align:right;font-weight:700;font-size:13px">Total Outstanding</td>
          <td style="font-weight:700;color:var(--danger);font-size:14px"><?= money($outstanding) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php else: ?>
  <p class="text-center text-muted py-4"><i class="bi bi-check-circle-fill text-success me-2"></i>No outstanding balances</p>
  <?php endif; ?>
</div>

<?php
$methodLabels = array_map(fn($r) => ucfirst(str_replace('_',' ',$r['method'])), $methodData);
$methodValues = array_map(fn($r) => (float)$r['total'], $methodData);

$extraScripts = "<script>
// Revenue per day
new Chart(document.getElementById('revChart'), {
  type: 'line',
  data: {
    labels: " . json_encode($labels) . ",
    datasets: [{
      label: 'Revenue (RWF)',
      data: " . json_encode($revValues) . ",
      borderColor: '#1A6BB5', backgroundColor: 'rgba(26,107,181,.08)',
      tension: .4, fill: true, pointRadius: 4, pointBackgroundColor: '#1A6BB5'
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => 'RWF ' + v.toLocaleString() } } }
  }
});

// New patients per day
new Chart(document.getElementById('patChart'), {
  type: 'bar',
  data: {
    labels: " . json_encode($labels) . ",
    datasets: [{
      label: 'New Patients',
      data: " . json_encode($patValues) . ",
      backgroundColor: 'rgba(13,110,253,.6)', borderRadius: 4
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
" . ($methodData ? "
// Revenue by method
new Chart(document.getElementById('methodChart'), {
  type: 'doughnut',
  data: {
    labels: " . json_encode($methodLabels) . ",
    datasets: [{ data: " . json_encode($methodValues) . ",
      backgroundColor: ['#1A6BB5','#0E6655','#E17B10','#6D28D9','#D14A30','#0F766E'] }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
  }
});
" : "") . "

</script>";

include __DIR__ . '/../includes/footer.php';
