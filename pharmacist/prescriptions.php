<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['pharmacist','admin']);
$pageTitle = 'Prescriptions';

$id = (int)($_GET['id'] ?? 0);
$single = $id ? row("SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id
    JOIN users u ON pr.doctor_id=u.id
    WHERE pr.id=?", [$id]) : [];
if ($id && $single) {
    $rxItems = rows("SELECT pi.*, m.name AS mname, m.unit FROM prescription_items pi JOIN medicines m ON pi.medicine_id=m.id WHERE pi.prescription_id=?", [$id]);
}

$filter = $_GET['status'] ?? 'pending';
$validFilters = ['pending','dispensed','cancelled','all'];
if (!in_array($filter, $validFilters)) $filter = 'pending';

$sql = "SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id
    JOIN users u ON pr.doctor_id=u.id";
$sql .= $filter !== 'all' ? " WHERE pr.status=?" : '';
$sql .= " ORDER BY pr.created_at DESC LIMIT 50";
$list = $filter !== 'all' ? rows($sql, [$filter]) : rows($sql);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Prescriptions</div><div class="page-sub">Manage and dispense patient prescriptions</div></div>
  <a href="/dmc/pharmacist/index.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?= showFlash('main') ?>

<?php if ($single): ?>
<!-- Single Rx detail -->
<div class="row g-3">
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-prescription2 me-2"></i><?= e($single['prescription_no']) ?></div>
      <div class="p-3 mb-3 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Patient</span><strong><?= e($single['pname']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Patient No</span><span><?= e($single['patient_no']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Doctor</span><span>Dr. <?= e($single['dname']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:12px;color:var(--muted)">Date</span><span><?= dtF($single['created_at']) ?></span></div>
        <div class="d-flex justify-content-between"><span style="font-size:12px;color:var(--muted)">Status</span><span class="badge-status bs-<?= $single['status'] ?>"><?= ucfirst($single['status']) ?></span></div>
      </div>
      <?php if ($single['notes']): ?>
      <div class="mb-3 p-2 rounded" style="background:var(--bg);font-size:12.5px"><strong>Notes:</strong> <?= e($single['notes']) ?></div>
      <?php endif; ?>
      <?php if ($single['status'] === 'pending'): ?>
      <button onclick="dispense(<?= $single['id'] ?>)" class="btn-dmc w-100"><i class="bi bi-bag-check"></i> Dispense All Items</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Prescribed Medicines</div>
      <div class="table-responsive">
        <table class="table dmc-table mb-0">
          <thead><tr><th>Medicine</th><th>Dosage</th><th>Duration</th><th>Qty</th><th>Freq</th><th>In Stock</th></tr></thead>
          <tbody>
          <?php foreach ($rxItems as $item): $med = row("SELECT current_stock FROM medicines WHERE id=?",[$item['medicine_id']]); ?>
          <tr>
            <td><strong style="font-size:13px"><?= e($item['mname']) ?></strong></td>
            <td><?= e($item['dosage']) ?></td>
            <td><?= e($item['duration']) ?></td>
            <td><?= $item['quantity'] ?> <?= e($item['unit']) ?></td>
            <td><?= e($item['frequency']) ?></td>
            <td style="font-weight:600;color:<?= $med['current_stock']>=$item['quantity']?'var(--success)':'var(--danger)' ?>"><?= $med['current_stock'] ?? 0 ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- List view -->
<div class="dmc-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="dmc-card-title mb-0">All Prescriptions</div>
    <div class="d-flex gap-1">
      <?php foreach(['pending'=>'Pending','dispensed'=>'Dispensed','cancelled'=>'Cancelled','all'=>'All'] as $f=>$lbl): ?>
      <a href="?status=<?= $f ?>" class="btn btn-sm <?= $filter===$f?'btn-primary':'btn-outline-secondary' ?>" style="font-size:11px"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Ref</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($list as $rx): ?>
      <tr>
        <td style="font-size:11px;font-family:monospace"><?= e($rx['prescription_no']) ?></td>
        <td><?= e($rx['pname']) ?></td>
        <td>Dr. <?= e($rx['dname']) ?></td>
        <td><span class="badge-status bs-<?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span></td>
        <td style="font-size:11px"><?= dtF($rx['created_at']) ?></td>
        <td class="d-flex gap-1">
          <a href="?id=<?= $rx['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">View</a>
          <?php if ($rx['status']==='pending'): ?>
          <button onclick="dispense(<?= $rx['id'] ?>)" class="btn btn-sm btn-success" style="font-size:11px">Dispense</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php $extraScripts = "<script>
function dispense(id) {
  Swal.fire({title:'Dispense Prescription?',text:'This will update stock levels.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, Dispense',confirmButtonColor:'#0A2342'}).then(r=>{
    if(r.isConfirmed) {
      dmcPost('/dmc/api/ajax.php',{action:'dispense_medicine',prescription_id:id}).then(j=>{
        if(j.ok){toast('Dispensed!');setTimeout(()=>location.reload(),1200);}
        else defaultErr(j);
      });
    }
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
