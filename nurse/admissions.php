<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['nurse','admin']);
$pageTitle = 'Admissions';

/* admit/discharge action */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'admit') {
        $patId  = (int)($_POST['patient_id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $docId  = (int)($_POST['doctor_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($patId && $roomId) {
            execute("UPDATE rooms SET is_occupied=1 WHERE id=?", [$roomId]);
            $id = execute("INSERT INTO admissions (patient_id,room_id,doctor_id,admitted_by,reason,admitted_at,status) VALUES (?,?,?,?,?,NOW(),'active')",
                [$patId,$roomId,$docId,currentUserId(),$reason]);
            audit('admit_patient','admissions',$id,"Patient $patId admitted to room $roomId");
            flash('main','Patient admitted successfully.');
        }
    } elseif ($act === 'discharge') {
        $admId = (int)($_POST['admission_id'] ?? 0);
        if ($admId) {
            $adm = row("SELECT ad.*, rt.price_per_day, rt.name AS room_type, r.room_no,
                               GREATEST(1, DATEDIFF(NOW(), ad.admitted_at)) AS days
                        FROM admissions ad
                        JOIN rooms r ON ad.room_id=r.id
                        JOIN room_types rt ON r.room_type_id=rt.id
                        WHERE ad.id=?", [$admId]);
            execute("UPDATE admissions SET status='discharged', discharged_at=NOW() WHERE id=?", [$admId]);
            execute("UPDATE rooms SET is_occupied=0 WHERE id=?", [$adm['room_id']]);

            /* auto-invoice room charges */
            $ppd = (float)($adm['price_per_day'] ?? 0);
            if ($ppd > 0) {
                $days     = max(1, (int)$adm['days']);
                $roomTotal = $ppd * $days;
                $invNo = generateNo('DMC-INV', 'invoices', 'invoice_no');
                $invId = execute(
                    "INSERT INTO invoices (invoice_no,patient_id,total,paid,balance,status,notes,created_by) VALUES (?,?,?,0,?,?,?,?)",
                    [$invNo, $adm['patient_id'], $roomTotal, $roomTotal, 'issued',
                     "Room {$adm['room_no']} ({$adm['room_type']}) — $days day(s)", currentUserId()]
                );
                execute(
                    "INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total_price) VALUES (?,?,?,?,?)",
                    [$invId, "Room {$adm['room_no']} — {$adm['room_type']}", $days, $ppd, $roomTotal]
                );
                execute("UPDATE patients SET balance = balance + ? WHERE id=?", [$roomTotal, $adm['patient_id']]);
                audit('auto_invoice_room', 'invoices', $invId, "Auto-invoice $invNo for room charges ($days days)");
            }

            audit('discharge_patient','admissions',$admId);
            flash('main', 'Patient discharged.' . ($ppd > 0 ? ' Room charges invoiced.' : ''));
        }
    }
    header('Location: /dmc/nurse/admissions.php'); exit;
}

$active = rows("SELECT ad.*, CONCAT(p.first_name,' ',p.last_name) AS pname, p.patient_no, p.phone,
    r.room_no, rt.name AS room_type, rt.price_per_day,
    CONCAT(u.first_name,' ',u.last_name) AS dname,
    DATEDIFF(NOW(), ad.admitted_at) AS days
    FROM admissions ad
    JOIN patients p ON ad.patient_id=p.id
    JOIN rooms r ON ad.room_id=r.id
    JOIN room_types rt ON r.room_type_id=rt.id
    LEFT JOIN users u ON ad.doctor_id=u.id
    WHERE ad.status='active'
    ORDER BY ad.admitted_at DESC");

$availableRooms = rows("SELECT r.*, rt.name AS type_name, rt.price_per_day FROM rooms r JOIN room_types rt ON r.room_type_id=rt.id WHERE r.is_occupied=0 AND r.is_active=1 ORDER BY r.room_no");
$doctors  = rows("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role='doctor' AND is_active=1 ORDER BY first_name");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Admissions</div><div class="page-sub"><?= count($active) ?> patients currently admitted</div></div>
  <button class="btn-dmc" onclick="document.getElementById('admitModal').style.display='flex'"><i class="bi bi-hospital"></i> Admit Patient</button>
</div>

<?= showFlash('main') ?>

<div class="row g-3">
  <?php foreach ($active as $ad): ?>
  <div class="col-md-6 col-lg-4">
    <div class="dmc-card">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="patient-avatar" style="width:48px;height:48px;font-size:16px"><?= strtoupper(substr($ad['pname'],0,2)) ?></div>
        <div>
          <div style="font-weight:700;font-size:14px"><?= e($ad['pname']) ?></div>
          <div style="font-size:11.5px;color:var(--muted)"><?= e($ad['patient_no']) ?> · <?= e($ad['phone']) ?></div>
        </div>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:12px;font-size:12.5px;margin-bottom:12px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Room</span><strong><?= e($ad['room_no']) ?> (<?= e($ad['room_type']) ?>)</strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Doctor</span><span>Dr. <?= e($ad['dname']??'—') ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Admitted</span><span><?= dtF($ad['admitted_at']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Days</span><strong><?= $ad['days'] ?> day<?= $ad['days']!=1?'s':'' ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Est. Cost</span><strong style="color:var(--brand)"><?= money($ad['days'] * $ad['price_per_day']) ?></strong></div>
      </div>
      <?php if ($ad['reason']): ?><div style="font-size:12px;color:var(--muted);margin-bottom:12px"><?= e($ad['reason']) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="act" value="discharge">
        <input type="hidden" name="admission_id" value="<?= $ad['id'] ?>">
        <button type="submit" class="btn btn-outline-danger w-100 btn-sm" onclick="return confirm('Discharge this patient?')"><i class="bi bi-box-arrow-right"></i> Discharge</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$active): ?>
  <div class="col-12"><div class="dmc-card text-center py-5 text-muted"><i class="bi bi-hospital" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>No patients currently admitted</div></div>
  <?php endif; ?>
</div>

<!-- Admit modal -->
<div id="admitModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(480px,95vw)">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px">Admit Patient</strong>
      <button onclick="document.getElementById('admitModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="admit">
      <div class="mb-3">
        <label class="form-label">Patient *</label>
        <input type="text" id="patSearch" class="form-control" placeholder="Search patient..." oninput="searchPat(this.value)">
        <input type="hidden" name="patient_id" id="patientId" required>
        <div id="patResults" class="mt-1"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Room *</label>
        <select name="room_id" class="form-select" required>
          <option value="">Select available room...</option>
          <?php foreach ($availableRooms as $r): ?>
          <option value="<?= $r['id'] ?>"><?= e($r['room_no']) ?> — <?= e($r['type_name']) ?> (<?= money($r['price_per_day']) ?>/day)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Attending Doctor</label>
        <select name="doctor_id" class="form-select">
          <option value="">Select doctor...</option>
          <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3"><label class="form-label">Reason for Admission</label><textarea name="reason" class="form-control" rows="2"></textarea></div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('admitModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc">Admit Patient</button>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = "<script>
function searchPat(q){
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    document.getElementById('patResults').innerHTML=j.results.map(p=>`<div onclick=\"document.getElementById('patientId').value=\${p.id};document.getElementById('patSearch').value=p.first_name+' '+p.last_name+' ('+p.patient_no+')';document.getElementById('patResults').innerHTML=''\" class='p-2 mb-1 rounded' style='background:var(--bg);cursor:pointer;font-size:12.5px'><strong>\${p.first_name} \${p.last_name}</strong> <span style='color:var(--muted)'>\${p.patient_no}</span></div>`).join('');
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
