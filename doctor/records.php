<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['doctor','admin']);
$pageTitle = 'Medical Records';
$uid = currentUserId();

$patientId = (int)($_GET['patient_id'] ?? 0);
$apptId    = (int)($_GET['appt_id']    ?? 0);
$patient   = $patientId ? row("SELECT * FROM patients WHERE id=?", [$patientId]) : [];

/* save new record */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patientId) {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $symptoms  = trim($_POST['symptoms'] ?? '');
    $treatment = trim($_POST['treatment_plan'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
    $followUp  = $_POST['follow_up_date'] ?? null;

    if ($diagnosis) {
        $recId = execute("INSERT INTO medical_records (patient_id,doctor_id,appointment_id,visit_date,symptoms,diagnosis,treatment_plan,notes,follow_up_date) VALUES (?,?,?,?,?,?,?,?,?)",
            [$patientId, $uid, $apptId?:null, $visitDate, $symptoms, $diagnosis, $treatment, $notes, $followUp?:null]);

        /* create prescription if drugs specified */
        $drugs = json_decode($_POST['drugs'] ?? '[]', true);
        if (is_array($drugs) && count($drugs)) {
            $rxNo = generateNo('DMC-RX','prescriptions','prescription_no');
            $rxId = execute("INSERT INTO prescriptions (prescription_no,patient_id,doctor_id,medical_record_id,status,notes) VALUES (?,?,?,?,'pending',?)",
                [$rxNo, $patientId, $uid, $recId, $notes]);
            foreach ($drugs as $drug) {
                if (!empty($drug['medicine_id'])) {
                    execute("INSERT INTO prescription_items (prescription_id,medicine_id,dosage,frequency,duration,quantity,notes) VALUES (?,?,?,?,?,?,?)",
                        [$rxId, $drug['medicine_id'], $drug['dosage']??'', $drug['frequency']??'', $drug['duration']??'', $drug['quantity']??1, $drug['notes']??'']);
                }
            }
        }

        /* mark appointment completed */
        if ($apptId) execute("UPDATE appointments SET status='completed' WHERE id=?", [$apptId]);
        audit('add_medical_record','medical_records',$recId,"Record for patient $patientId");
        flash('main','Medical record saved successfully.');
        header("Location: /dmc/doctor/records.php?patient_id=$patientId"); exit;
    }
}

$records = $patientId ? rows("SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) AS dname FROM medical_records mr JOIN users u ON mr.doctor_id=u.id WHERE mr.patient_id=? ORDER BY mr.visit_date DESC", [$patientId]) : [];
$vitals  = $patientId ? rows("SELECT * FROM vital_signs WHERE patient_id=? ORDER BY recorded_at DESC LIMIT 3", [$patientId]) : [];
$meds    = rows("SELECT id, name, generic_name, unit, current_stock FROM medicines WHERE is_active=1 AND current_stock>0 ORDER BY name");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Medical Records<?= $patient ? ' — '.e($patient['first_name'].' '.$patient['last_name']) : '' ?></div>
    <div class="page-sub"><?= $patient ? e($patient['patient_no']).' · '.e($patient['phone']) : 'Patient records' ?></div>
  </div>
  <a href="/dmc/doctor/appointments.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?= showFlash('main') ?>

<?php if (!$patient): ?>
<div class="dmc-card" style="max-width:480px;margin:0 auto">
  <div class="dmc-card-title">Find Patient</div>
  <input type="text" id="patSearch" class="form-control" placeholder="Search patient..." oninput="searchPat(this.value)">
  <div id="patResults" class="mt-2"></div>
</div>
<?php else: ?>

<div class="row g-3">
  <!-- Left: patient info + vitals -->
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="patient-avatar" style="width:52px;height:52px;font-size:18px"><?= strtoupper(substr($patient['first_name'],0,1).substr($patient['last_name'],0,1)) ?></div>
        <div>
          <div style="font-weight:700;font-size:15px"><?= e($patient['first_name'].' '.$patient['last_name']) ?></div>
          <div style="font-size:12px;color:var(--muted)"><?= e($patient['patient_no']) ?></div>
          <div style="font-size:12px;color:var(--muted)"><?= $patient['date_of_birth']?age($patient['date_of_birth']).' yrs, ':'' ?><?= ucfirst($patient['gender']??'') ?> · <?= e($patient['blood_group']??'?') ?></div>
        </div>
      </div>
      <?php if ($patient['insurance_provider']): ?>
      <div class="p-2 rounded" style="background:var(--bg);font-size:12px"><i class="bi bi-shield-check me-1"></i><?= e($patient['insurance_provider']) ?> — <?= e($patient['insurance_number']??'') ?></div>
      <?php endif; ?>
    </div>

    <?php if ($vitals): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title" style="font-size:13px">Latest Vitals</div>
      <?php $v = $vitals[0]; ?>
      <div style="font-size:12px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Temperature</span><strong><?= $v['temperature']?$v['temperature'].'°C':'—' ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Blood Pressure</span><strong><?= $v['blood_pressure_sys']?$v['blood_pressure_sys'].'/'.$v['blood_pressure_dia'].' mmHg':'—' ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Pulse</span><strong><?= $v['pulse_rate']?$v['pulse_rate'].' bpm':'—' ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">SpO2</span><strong><?= $v['oxygen_saturation']?$v['oxygen_saturation'].'%':'—' ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">BMI</span><strong><?= $v['bmi']??'—' ?></strong></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: new record form + history -->
  <div class="col-lg-8">
    <!-- New record form -->
    <div class="dmc-card mb-3">
      <div class="dmc-card-title"><i class="bi bi-plus-circle me-2"></i>New Medical Record<?= $apptId ? " (Appointment #$apptId)" : '' ?></div>
      <form method="POST" id="recordForm">
        <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Chief Symptoms *</label>
            <textarea name="symptoms" class="form-control" rows="2" placeholder="Patient's main complaints..."></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Diagnosis *</label>
            <textarea name="diagnosis" class="form-control" rows="2" placeholder="Your diagnosis..." required></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Treatment Plan</label>
            <textarea name="treatment_plan" class="form-control" rows="2" placeholder="Prescribed treatment..."></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Follow-up Date</label>
            <input type="date" name="follow_up_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Additional Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>

          <!-- Prescription section -->
          <div class="col-12">
            <div class="dmc-card-title" style="font-size:13px;margin-bottom:12px"><i class="bi bi-prescription2 me-2"></i>Prescribe Medicines (optional)</div>
            <div id="rxList"></div>
            <button type="button" onclick="addDrug()" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus"></i> Add Medicine</button>
            <input type="hidden" name="drugs" id="drugsJson" value="[]">
          </div>

          <div class="col-12 d-flex gap-2 justify-content-end">
            <button type="button" onclick="saveRecord()" class="btn-dmc"><i class="bi bi-check-circle"></i> Save Record</button>
          </div>
        </div>
      </form>
    </div>

    <!-- History -->
    <div class="dmc-card">
      <div class="dmc-card-title">Visit History (<?= count($records) ?>)</div>
      <?php foreach ($records as $r): ?>
      <div class="p-3 mb-2 rounded" style="background:var(--bg)">
        <div class="d-flex justify-content-between mb-1">
          <strong style="font-size:13px"><?= dateF($r['visit_date']) ?></strong>
          <span style="font-size:11px;color:var(--muted)">Dr. <?= e($r['dname']) ?></span>
        </div>
        <?php if ($r['diagnosis']): ?><div style="font-size:12.5px;margin-bottom:4px"><strong>Dx:</strong> <?= e($r['diagnosis']) ?></div><?php endif; ?>
        <?php if ($r['treatment_plan']): ?><div style="font-size:12px;color:var(--muted)"><strong>Rx Plan:</strong> <?= e($r['treatment_plan']) ?></div><?php endif; ?>
        <?php if ($r['follow_up_date']): ?><div style="font-size:11.5px;color:var(--brand2);margin-top:4px"><i class="bi bi-calendar-event me-1"></i>Follow-up: <?= dateF($r['follow_up_date']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$records): ?><div class="text-center text-muted py-3" style="font-size:13px">No records yet</div><?php endif; ?>
    </div>
  </div>
</div>

<?php $medsJson = json_encode($meds); ?>
<?php $extraScripts = "<script>
const medicines = $medsJson;
let drugs = [];

function addDrug() {
  const i = drugs.length;
  drugs.push({medicine_id:'',dosage:'',frequency:'',duration:'',quantity:1,notes:''});
  renderDrugs();
}

function renderDrugs() {
  document.getElementById('rxList').innerHTML = drugs.map((d,i)=>`
  <div class='row g-2 mb-2 p-2 rounded' style='background:#fff;border:1px solid var(--border)'>
    <div class='col-md-4'>
      <select class='form-select form-select-sm' onchange='drugs[\${i}].medicine_id=this.value'>
        <option value=''>Select medicine...</option>
        \${medicines.map(m=>`<option value='\${m.id}' \${d.medicine_id==m.id?'selected':''}>\${m.name} (\${m.unit})</option>`).join('')}
      </select>
    </div>
    <div class='col-md-2'><input class='form-control form-control-sm' placeholder='Dosage e.g. 500mg' value='\${d.dosage}' oninput='drugs[\${i}].dosage=this.value'></div>
    <div class='col-md-2'><input class='form-control form-control-sm' placeholder='Freq e.g. 3x/day' value='\${d.frequency}' oninput='drugs[\${i}].frequency=this.value'></div>
    <div class='col-md-2'><input class='form-control form-control-sm' placeholder='Duration e.g. 7 days' value='\${d.duration}' oninput='drugs[\${i}].duration=this.value'></div>
    <div class='col-md-1'><input type='number' class='form-control form-control-sm' placeholder='Qty' value='\${d.quantity}' min='1' oninput='drugs[\${i}].quantity=+this.value'></div>
    <div class='col-md-1 d-flex align-items-center'><button type='button' class='btn btn-sm btn-outline-danger' onclick='drugs.splice(\${i},1);renderDrugs()'>✕</button></div>
  </div>`).join('');
}

function saveRecord() {
  document.getElementById('drugsJson').value = JSON.stringify(drugs);
  document.getElementById('recordForm').submit();
}

function searchPat(q){
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    document.getElementById('patResults').innerHTML=j.results.map(p=>`<a href='?patient_id=\${p.id}' class='d-block p-2 mb-1 rounded text-decoration-none' style='background:var(--bg);font-size:13px'><strong>\${p.first_name} \${p.last_name}</strong> <span style='font-size:11px;color:var(--muted)'>\${p.patient_no}</span></a>`).join('');
  });
}
</script>";
endif;
include __DIR__ . '/../includes/footer.php';
