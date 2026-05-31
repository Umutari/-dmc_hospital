<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['pharmacist','admin']);
$pageTitle = 'Pharmacy Dashboard';
$uid = currentUserId();

$stats = [
    'pending_rx'    => scalar("SELECT COUNT(*) FROM prescriptions WHERE status='pending'"),
    'dispensed_today'=> scalar("SELECT COUNT(*) FROM prescriptions WHERE status='dispensed' AND DATE(dispensed_at)=CURDATE()"),
    'low_stock'     => scalar("SELECT COUNT(*) FROM medicines WHERE current_stock <= reorder_level AND is_active=1"),
    'total_medicines'=> scalar("SELECT COUNT(*) FROM medicines WHERE is_active=1"),
];

$pendingRx = rows("SELECT pr.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no,
    CONCAT(u.first_name,' ',u.last_name) AS dname
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id
    JOIN users u ON pr.doctor_id=u.id
    WHERE pr.status='pending'
    ORDER BY pr.created_at ASC LIMIT 15");

$lowStock = rows("SELECT * FROM medicines WHERE current_stock <= reorder_level AND is_active=1 ORDER BY current_stock ASC LIMIT 10");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Pharmacy Dashboard</div><div class="page-sub"><?= date('l, d F Y') ?></div></div>
  <div class="d-flex gap-2">
    <a href="/dmc/pharmacist/medicines.php" class="btn-dmc-outline"><i class="bi bi-capsule"></i> Medicines</a>
    <a href="/dmc/pharmacist/prescriptions.php" class="btn-dmc"><i class="bi bi-prescription2"></i> All Prescriptions</a>
  </div>
</div>

<?= showFlash('main') ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-label">Pending Rx</div><div class="stat-value"><?= $stats['pending_rx'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-green"><i class="bi bi-bag-check"></i></div><div><div class="stat-label">Dispensed Today</div><div class="stat-value"><?= $stats['dispensed_today'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-label">Low Stock</div><div class="stat-value"><?= $stats['low_stock'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon si-blue"><i class="bi bi-capsule-pill"></i></div><div><div class="stat-label">Total Medicines</div><div class="stat-value"><?= $stats['total_medicines'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-prescription2 me-2"></i>Pending Prescriptions</div>
      <?php if ($pendingRx): foreach ($pendingRx as $rx): ?>
      <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded" style="background:var(--bg);border-left:3px solid var(--warning)">
        <div class="flex-1">
          <div class="d-flex justify-content-between">
            <strong style="font-size:13px"><?= e($rx['pname']) ?></strong>
            <span style="font-size:11px;color:var(--muted)"><?= dtF($rx['created_at']) ?></span>
          </div>
          <div style="font-size:11.5px;color:var(--muted)"><?= e($rx['prescription_no']) ?> · Dr. <?= e($rx['dname']) ?></div>
          <?php if ($rx['notes']): ?><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= e(substr($rx['notes'],0,80)) ?></div><?php endif; ?>
        </div>
        <div class="d-flex gap-1">
          <a href="/dmc/pharmacist/prescriptions.php?id=<?= $rx['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:11px">View</a>
          <button onclick="dispense(<?= $rx['id'] ?>)" class="btn btn-sm btn-success" style="font-size:11px">Dispense</button>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-4"><i class="bi bi-check-circle" style="font-size:2rem;display:block;margin-bottom:.5rem;color:var(--success)"></i>No pending prescriptions</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-exclamation-triangle me-2" style="color:var(--danger)"></i>Low Stock Alert
        <a href="/dmc/pharmacist/medicines.php" style="font-size:12px">Manage →</a>
      </div>
      <?php if ($lowStock): foreach ($lowStock as $m): ?>
      <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:var(--bg)">
        <div class="stat-icon si-red" style="width:34px;height:34px;font-size:14px;border-radius:8px"><i class="bi bi-capsule"></i></div>
        <div class="flex-1">
          <div style="font-size:12.5px;font-weight:600"><?= e($m['name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($m['generic_name']) ?> · Reorder: <?= $m['reorder_level'] ?></div>
        </div>
        <div style="font-size:13px;font-weight:700;color:<?= $m['current_stock']==0?'var(--danger)':'var(--warning)' ?>">
          <?= $m['current_stock'] ?> <?= e($m['unit']) ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-3" style="font-size:13px"><i class="bi bi-check-circle me-1" style="color:var(--success)"></i>All stock levels OK</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
function dispense(id) {
  Swal.fire({title:'Dispense Prescription?',text:'Mark this prescription as dispensed and update stock.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, Dispense',confirmButtonColor:'#0A2342'}).then(r=>{
    if(r.isConfirmed) {
      dmcPost('/dmc/api/ajax.php',{action:'dispense_medicine',prescription_id:id}).then(j=>{
        if(j.ok){toast('Dispensed successfully!');setTimeout(()=>location.reload(),1200);}
        else defaultErr(j);
      });
    }
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
