<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['pharmacist','admin']);
$pageTitle = 'Medicine Inventory';

/* ── handle actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $generic  = trim($_POST['generic_name'] ?? '');
        $catId    = (int)($_POST['category_id'] ?? 0);
        $unit     = trim($_POST['unit'] ?? 'tablets');
        $price    = (float)($_POST['selling_price'] ?? 0);
        $buyPrice = (float)($_POST['purchase_price'] ?? 0);
        $stock    = (int)($_POST['current_stock'] ?? 0);
        $reorder  = (int)($_POST['reorder_level'] ?? 10);
        $expiry   = $_POST['expiry_date'] ?? null;
        if ($name) {
            execute("INSERT INTO medicines (name,generic_name,category_id,unit,selling_price,purchase_price,current_stock,reorder_level,expiry_date) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$name,$generic,$catId,$unit,$price,$buyPrice,$stock,$reorder,$expiry?:null]);
            audit('add_medicine','medicines',0,"Added medicine: $name");
            flash('main',"Medicine '$name' added successfully.");
        }
    } elseif ($act === 'restock') {
        $medId = (int)($_POST['medicine_id'] ?? 0);
        $qty   = (int)($_POST['qty'] ?? 0);
        if ($medId && $qty > 0) {
            execute("UPDATE medicines SET current_stock=current_stock+? WHERE id=?", [$qty,$medId]);
            $m = row("SELECT name FROM medicines WHERE id=?",[$medId]);
            audit('restock_medicine','medicines',$medId,"Restocked {$m['name']} by $qty units");
            flash('main',"Stock updated successfully.");
        }
    }
    header('Location: /dmc/pharmacist/medicines.php'); exit;
}

$cats   = rows("SELECT * FROM medicine_categories ORDER BY name");
$meds   = rows("SELECT m.*, c.name AS cname FROM medicines m LEFT JOIN medicine_categories c ON m.category_id=c.id WHERE m.is_active=1 ORDER BY m.name");
$catFilter = (int)($_GET['cat'] ?? 0);
if ($catFilter) $meds = rows("SELECT m.*, c.name AS cname FROM medicines m LEFT JOIN medicine_categories c ON m.category_id=c.id WHERE m.is_active=1 AND m.category_id=? ORDER BY m.name", [$catFilter]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Medicine Inventory</div><div class="page-sub">Stock management and tracking</div></div>
  <button class="btn-dmc" onclick="document.getElementById('addModal').style.display='flex'"><i class="bi bi-plus-circle"></i> Add Medicine</button>
</div>

<?= showFlash('main') ?>

<!-- Category filter -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="/dmc/pharmacist/medicines.php" class="btn btn-sm <?= !$catFilter?'btn-primary':'btn-outline-secondary' ?>">All</a>
  <?php foreach ($cats as $c): ?>
  <a href="?cat=<?= $c['id'] ?>" class="btn btn-sm <?= $catFilter==$c['id']?'btn-primary':'btn-outline-secondary' ?>"><?= e($c['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0" id="medTable">
      <thead><tr><th>Name</th><th>Generic</th><th>Category</th><th>Unit</th><th>Selling Price</th><th>Stock</th><th>Reorder</th><th>Expiry</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($meds as $m): ?>
      <tr class="<?= $m['current_stock'] <= $m['reorder_level'] ? 'table-warning' : '' ?>">
        <td><strong><?= e($m['name']) ?></strong></td>
        <td style="font-size:11.5px;color:var(--muted)"><?= e($m['generic_name']) ?></td>
        <td><?= e($m['cname'] ?? '—') ?></td>
        <td><?= e($m['unit']) ?></td>
        <td><?= money($m['selling_price']) ?></td>
        <td style="font-weight:600;color:<?= $m['current_stock']==0?'var(--danger)':($m['current_stock']<=$m['reorder_level']?'var(--warning)':'var(--success)') ?>">
          <?= number_format($m['current_stock']) ?>
        </td>
        <td><?= number_format($m['reorder_level']) ?></td>
        <td style="font-size:11px"><?= $m['expiry_date'] ? dateF($m['expiry_date']) : '—' ?></td>
        <td><button onclick="openRestock(<?= $m['id'] ?>, '<?= e(addslashes($m['name'])) ?>')" class="btn btn-sm btn-outline-primary" style="font-size:11px">Restock</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Medicine Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(580px,95vw);max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px">Add New Medicine</strong>
      <button onclick="document.getElementById('addModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="add">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Medicine Name *</label><input name="name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Generic Name</label><input name="generic_name" class="form-control"></div>
        <div class="col-md-6">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="">Select...</option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Unit</label>
          <select name="unit" class="form-select">
            <?php foreach(['tablets','capsules','ml','mg','vials','ampoules','sachets','pcs'] as $u): ?>
            <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label">Selling Price (RWF)</label><input type="number" name="selling_price" class="form-control" min="0" step="1" value="0"></div>
        <div class="col-md-4"><label class="form-label">Purchase Price (RWF)</label><input type="number" name="purchase_price" class="form-control" min="0" step="1" value="0"></div>
        <div class="col-md-4"><label class="form-label">Initial Stock</label><input type="number" name="current_stock" class="form-control" min="0" value="0"></div>
        <div class="col-md-4"><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" class="form-control" min="0" value="10"></div>
        <div class="col-md-8"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn-dmc">Add Medicine</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Restock Modal -->
<div id="restockModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(380px,95vw)">
    <strong id="restockTitle" style="font-size:15px;display:block;margin-bottom:16px">Restock Medicine</strong>
    <form method="POST">
      <input type="hidden" name="act" value="restock">
      <input type="hidden" name="medicine_id" id="restockId">
      <div class="mb-3"><label class="form-label">Quantity to Add *</label><input type="number" name="qty" class="form-control" min="1" required></div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('restockModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = "<script>
function openRestock(id, name) {
  document.getElementById('restockId').value = id;
  document.getElementById('restockTitle').textContent = 'Restock: ' + name;
  document.getElementById('restockModal').style.display = 'flex';
}
</script>";
include __DIR__ . '/../includes/footer.php';
