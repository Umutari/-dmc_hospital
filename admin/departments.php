<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'Departments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $head = (int)($_POST['head_doctor_id'] ?? 0);
    if ($name) {
        execute("INSERT INTO departments (name,description,head_doctor_id) VALUES (?,?,?)",
            [$name,$desc,$head?:null]);
        flash('main',"Department '$name' added.");
    }
    header('Location: /dmc/admin/departments.php'); exit;
}

$depts   = rows("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS head_name FROM departments d LEFT JOIN users u ON d.head_doctor_id=u.id ORDER BY d.name");
$doctors = rows("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role='doctor' AND is_active=1 ORDER BY first_name");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Departments</div><div class="page-sub"><?= count($depts) ?> departments</div></div>
  <button class="btn-dmc" onclick="document.getElementById('addModal').style.display='flex'"><i class="bi bi-plus-circle"></i> Add Department</button>
</div>

<?= showFlash('main') ?>

<div class="row g-3">
  <?php foreach ($depts as $d): ?>
  <?php
    $staffCount = scalar("SELECT COUNT(*) FROM users u JOIN doctors doc ON u.id=doc.user_id WHERE doc.department_id=?", [$d['id']]);
    $apptCount  = scalar("SELECT COUNT(*) FROM appointments a JOIN users u ON a.doctor_id=u.id JOIN doctors doc ON u.id=doc.user_id WHERE doc.department_id=? AND a.appointment_date=CURDATE()", [$d['id']]);
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="dmc-card">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="stat-icon si-blue" style="width:46px;height:46px;font-size:20px;border-radius:12px"><i class="bi bi-building-add"></i></div>
        <div>
          <div style="font-weight:700;font-size:15px"><?= e($d['name']) ?></div>
          <?php if ($d['head_name']): ?><div style="font-size:12px;color:var(--muted)">Head: Dr. <?= e($d['head_name']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php if ($d['description']): ?><div style="font-size:12.5px;color:var(--muted);margin-bottom:12px"><?= e($d['description']) ?></div><?php endif; ?>
      <div class="d-flex gap-3 text-center" style="font-size:12px">
        <div class="flex-1 p-2 rounded" style="background:var(--bg)"><div style="font-size:18px;font-weight:700;color:var(--brand)"><?= $staffCount ?></div><div style="color:var(--muted)">Doctors</div></div>
        <div class="flex-1 p-2 rounded" style="background:var(--bg)"><div style="font-size:18px;font-weight:700;color:var(--brand2)"><?= $apptCount ?></div><div style="color:var(--muted)">Today</div></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Add modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(440px,95vw)">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px">Add Department</strong>
      <button onclick="document.getElementById('addModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST">
      <div class="mb-3"><label class="form-label">Name *</label><input name="name" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      <div class="mb-3">
        <label class="form-label">Head Doctor</label>
        <select name="head_doctor_id" class="form-select">
          <option value="">Select...</option>
          <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>">Dr. <?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc">Add</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
