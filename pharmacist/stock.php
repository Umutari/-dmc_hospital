<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['pharmacist','admin']);
$pageTitle = 'Stock Management';

/* handle stock adjustments */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['act'] ?? '';
    $medId = (int)($_POST['medicine_id'] ?? 0);
    $qty   = (int)($_POST['qty'] ?? 0);
    if ($act === 'adjust' && $medId && $qty !== 0) {
        $med = row("SELECT * FROM medicines WHERE id=?", [$medId]);
        if ($med) {
            $newStock = max(0, $med['current_stock'] + $qty);
            execute("UPDATE medicines SET current_stock=? WHERE id=?", [$newStock, $medId]);
            $dir = $qty > 0 ? "Added $qty" : "Removed ".abs($qty);
            audit('stock_adjust','medicines',$medId,"$dir units of {$med['name']} (was {$med['current_stock']}, now $newStock)");
            flash('main', "Stock adjusted: {$med['name']} is now $newStock units.");
        }
    } elseif ($act === 'reorder' && $medId) {
        $level = (int)($_POST['reorder_level'] ?? 10);
        execute("UPDATE medicines SET reorder_level=? WHERE id=?", [$level, $medId]);
        flash('main', 'Reorder level updated.');
    }
    header('Location: /dmc/pharmacist/stock.php'); exit;
}

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

/* stats */
$totalMeds     = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1");
$outOfStock    = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1 AND current_stock=0");
$lowStock      = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1 AND current_stock>0 AND current_stock<=reorder_level");
$nearExpiry    = (int)scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1 AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)");
$totalStockVal = (float)scalar("SELECT COALESCE(SUM(current_stock * purchase_price),0) FROM medicines WHERE is_active=1");

/* build query */
$where = "m.is_active=1";
$params = [];
if ($filter === 'out_of_stock') { $where .= " AND m.current_stock=0"; }
elseif ($filter === 'low_stock') { $where .= " AND m.current_stock>0 AND m.current_stock<=m.reorder_level"; }
elseif ($filter === 'near_expiry') { $where .= " AND m.expiry_date IS NOT NULL AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"; }
elseif ($filter === 'expired') { $where .= " AND m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE()"; }
if ($search) {
    $where .= " AND (m.name LIKE ? OR m.generic_name LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s;
}

$meds = rows("SELECT m.*, c.name AS cname,
    (m.current_stock * m.purchase_price) AS stock_value,
    CASE
        WHEN m.current_stock = 0 THEN 'out'
        WHEN m.current_stock <= m.reorder_level THEN 'low'
        WHEN m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE() THEN 'expired'
        WHEN m.expiry_date IS NOT NULL AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'near_expiry'
        ELSE 'ok'
    END AS stock_status
    FROM medicines m LEFT JOIN medicine_categories c ON m.category_id=c.id
    WHERE $where
    ORDER BY m.current_stock ASC, m.name", $params);

/* recently dispensed (top medicines) */
$topDispensed = rows("SELECT m.name, SUM(pi.quantity) AS total_dispensed
    FROM prescription_items pi
    JOIN medicines m ON pi.medicine_id=m.id
    JOIN prescriptions p ON pi.prescription_id=p.id
    WHERE p.status='dispensed' AND p.dispensed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY m.id ORDER BY total_dispensed DESC LIMIT 8");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Stock Management</div>
    <div class="page-sub">Medicine inventory status and adjustments</div>
  </div>
  <a href="/dmc/pharmacist/medicines.php" class="btn-dmc-outline"><i class="bi bi-capsule"></i> All Medicines</a>
</div>

<?= showFlash('main') ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-capsule-pill"></i></div>
      <div><div class="stat-label">Total Items</div><div class="stat-value"><?= number_format($totalMeds) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-red"><i class="bi bi-x-circle-fill"></i></div>
      <div><div class="stat-label">Out of Stock</div><div class="stat-value" style="color:<?= $outOfStock>0?'var(--danger)':'' ?>"><?= $outOfStock ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div><div class="stat-label">Low Stock</div><div class="stat-value" style="color:<?= $lowStock>0?'var(--warning)':'' ?>"><?= $lowStock ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-calendar-x"></i></div>
      <div><div class="stat-label">Near Expiry (90d)</div><div class="stat-value" style="color:<?= $nearExpiry>0?'var(--warning)':'' ?>"><?= $nearExpiry ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div>
      <div><div class="stat-label">Stock Value</div><div class="stat-value" style="font-size:13px"><?= money($totalStockVal) ?></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <!-- Filters -->
    <div class="dmc-card mb-3 p-3">
      <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
        <div class="d-flex gap-1 flex-wrap">
          <?php foreach(['all'=>'All','low_stock'=>'Low Stock','out_of_stock'=>'Out of Stock','near_expiry'=>'Near Expiry','expired'=>'Expired'] as $f=>$l): ?>
          <a href="?filter=<?= $f ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter===$f?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?>
            <?php if ($f==='out_of_stock' && $outOfStock>0): ?><span class="badge bg-danger ms-1" style="font-size:10px"><?= $outOfStock ?></span><?php endif; ?>
            <?php if ($f==='low_stock' && $lowStock>0): ?><span class="badge bg-warning text-dark ms-1" style="font-size:10px"><?= $lowStock ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="flex-grow-1" style="min-width:180px">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search medicine..." value="<?= e($search) ?>">
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
        </div>
        <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Search</button>
      </form>
    </div>

    <!-- Stock table -->
    <div class="dmc-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="dmc-card-title mb-0">
          <?= $filter==='all'?'All Medicines':ucwords(str_replace('_',' ',$filter)) ?>
          <span style="font-size:12px;font-weight:400;color:var(--muted)">(<?= count($meds) ?> items)</span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0" style="font-size:12.5px">
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Category</th>
              <th>Current Stock</th>
              <th>Reorder Level</th>
              <th>Expiry</th>
              <th>Stock Value</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($meds): foreach ($meds as $m): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= e($m['name']) ?></div>
              <?php if ($m['generic_name']): ?><div style="font-size:10.5px;color:var(--muted)"><?= e($m['generic_name']) ?></div><?php endif; ?>
            </td>
            <td><?= e($m['cname']??'—') ?></td>
            <td>
              <?php
              $stockColor = $m['current_stock']==0 ? 'var(--danger)' : ($m['current_stock']<=$m['reorder_level'] ? 'var(--warning)' : 'var(--success)');
              $stockIcon  = $m['current_stock']==0 ? 'x-circle-fill' : ($m['current_stock']<=$m['reorder_level'] ? 'exclamation-triangle-fill' : 'check-circle-fill');
              ?>
              <span style="font-weight:700;color:<?= $stockColor ?>">
                <i class="bi bi-<?= $stockIcon ?> me-1" style="font-size:11px"></i><?= number_format($m['current_stock']) ?>
              </span>
              <span style="font-size:10px;color:var(--muted)"> <?= e($m['unit']) ?></span>
            </td>
            <td style="color:var(--muted)"><?= number_format($m['reorder_level']) ?></td>
            <td>
              <?php if ($m['expiry_date']): ?>
              <?php $daysLeft = (int)floor((strtotime($m['expiry_date'])-time())/86400); ?>
              <span style="font-size:11px;color:<?= $daysLeft<0?'var(--danger)':($daysLeft<=90?'var(--warning)':'inherit') ?>">
                <?= dateF($m['expiry_date']) ?>
                <?php if ($daysLeft < 0): ?><div style="font-size:10px">(Expired)</div>
                <?php elseif ($daysLeft <= 90): ?><div style="font-size:10px">(<?= $daysLeft ?>d left)</div>
                <?php endif; ?>
              </span>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
            <td style="font-size:11.5px"><?= money($m['stock_value']) ?></td>
            <td>
              <button onclick="openAdjust(<?= $m['id'] ?>, '<?= e(addslashes($m['name'])) ?>', <?= $m['current_stock'] ?>, <?= $m['reorder_level'] ?>)"
                class="btn btn-sm btn-outline-primary" style="font-size:11px">
                <i class="bi bi-sliders me-1"></i>Adjust
              </button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-check-circle me-2"></i>No medicines found for this filter.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Top dispensed this month -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Top Dispensed (Last 30 Days)</div>
      <?php if ($topDispensed): foreach ($topDispensed as $i => $td): ?>
      <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:12.5px">
        <div class="d-flex align-items-center gap-2">
          <span style="background:var(--brand);color:#fff;border-radius:50%;width:20px;height:20px;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700"><?= $i+1 ?></span>
          <span><?= e($td['name']) ?></span>
        </div>
        <strong><?= number_format($td['total_dispensed']) ?> units</strong>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:12px"><i class="bi bi-bag me-1"></i>No dispensing data yet</div>
      <?php endif; ?>
    </div>

    <!-- Critical alerts -->
    <?php $critical = array_filter($meds, fn($m) => $m['current_stock'] == 0 || ($m['expiry_date'] && strtotime($m['expiry_date']) < strtotime('+30 days'))); ?>
    <?php if ($critical): ?>
    <div class="dmc-card">
      <div class="dmc-card-title" style="color:var(--danger)"><i class="bi bi-exclamation-circle me-1"></i>Urgent Alerts</div>
      <?php foreach (array_slice($critical, 0, 6) as $m): ?>
      <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded" style="background:<?= $m['current_stock']==0?'#fff5f5':'#fffbf0' ?>;font-size:12px">
        <span style="font-weight:600"><?= e($m['name']) ?></span>
        <?php if ($m['current_stock']==0): ?>
        <span class="badge bg-danger">Out of stock</span>
        <?php else: ?>
        <span class="badge bg-warning text-dark">Expires <?= dateF($m['expiry_date']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Adjust Stock Modal -->
<div id="adjustModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(440px,95vw)">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong id="adjustTitle" style="font-size:15px">Adjust Stock</strong>
      <button onclick="document.getElementById('adjustModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <div id="adjustCurrentInfo" class="p-2 rounded mb-3" style="background:var(--bg);font-size:13px"></div>
    <form method="POST">
      <input type="hidden" name="act" value="adjust">
      <input type="hidden" name="medicine_id" id="adjustMedId">
      <div class="mb-3">
        <label class="form-label">Adjustment Type</label>
        <div class="d-flex gap-2">
          <label class="d-flex align-items-center gap-2" style="cursor:pointer">
            <input type="radio" name="adj_type" value="add" checked onchange="updateQtySign(this.value)"> Add stock (received)
          </label>
          <label class="d-flex align-items-center gap-2" style="cursor:pointer">
            <input type="radio" name="adj_type" value="remove" onchange="updateQtySign(this.value)"> Remove (wastage/return)
          </label>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Quantity *</label>
        <input type="number" name="qty" id="adjustQty" class="form-control" min="1" required placeholder="Enter quantity">
      </div>
      <div class="mb-3">
        <label class="form-label">Update Reorder Level</label>
        <input type="number" name="reorder_level" id="adjustReorder" class="form-control" min="0">
        <div style="font-size:11px;color:var(--muted);margin-top:3px">Leave unchanged or update minimum stock threshold</div>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('adjustModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc" onclick="return finalizeAdjust()">Save Adjustment</button>
      </div>
    </form>
    <!-- hidden reorder form -->
  </div>
</div>

<?php $extraScripts = "<script>
let adjType = 'add';
function updateQtySign(v) { adjType = v; }
function openAdjust(id, name, stock, reorder) {
  document.getElementById('adjustMedId').value = id;
  document.getElementById('adjustTitle').textContent = 'Adjust Stock: ' + name;
  document.getElementById('adjustCurrentInfo').innerHTML = 'Current stock: <strong>' + stock + ' units</strong> &nbsp;|&nbsp; Reorder level: <strong>' + reorder + '</strong>';
  document.getElementById('adjustQty').value = '';
  document.getElementById('adjustReorder').value = reorder;
  adjType = 'add';
  document.querySelectorAll('input[name=adj_type]')[0].checked = true;
  document.getElementById('adjustModal').style.display = 'flex';
  document.getElementById('adjustQty').focus();
}
function finalizeAdjust() {
  const qty = parseInt(document.getElementById('adjustQty').value);
  if (!qty || qty <= 0) { toast('Enter a valid quantity', 'warning'); return false; }
  document.getElementById('adjustQty').value = adjType === 'remove' ? -qty : qty;
  return true;
}
</script>";
include __DIR__ . '/../includes/footer.php';
