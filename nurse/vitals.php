<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['nurse','doctor','admin']);
$pageTitle = 'Record Vitals';

$patientId = (int)($_GET['patient_id'] ?? 0);
$apptId    = (int)($_GET['appt_id'] ?? 0);
$patient   = $patientId ? row("SELECT * FROM patients WHERE id=?", [$patientId]) : [];

$recentVitals = $patientId ? rows("SELECT v.*, CONCAT(u.first_name,' ',u.last_name) AS nurse_name FROM vital_signs v JOIN users u ON v.recorded_by=u.id WHERE v.patient_id=? ORDER BY v.recorded_at DESC LIMIT 5", [$patientId]) : [];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Record Vitals</div><div class="page-sub">Patient vital signs</div></div>
  <a href="/dmc/nurse/index.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if (!$patient): ?>
<!-- Patient search -->
<div class="dmc-card" style="max-width:480px;margin:0 auto">
  <div class="dmc-card-title">Find Patient</div>
  <div class="mb-3">
    <label class="form-label">Search Patient</label>
    <input type="text" id="patSearch" class="form-control" placeholder="Name, patient no, or phone..." oninput="searchPat(this.value)">
  </div>
  <div id="patResults"></div>
</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-heart-pulse me-2"></i>Vitals — <?= e($patient['first_name'].' '.$patient['last_name']) ?></div>
      <div class="p-2 mb-3 rounded" style="background:var(--bg);font-size:12.5px">
        <strong><?= e($patient['patient_no']) ?></strong> · <?= e($patient['phone']) ?> · <?= $patient['date_of_birth']?age($patient['date_of_birth']).' yrs':'—' ?>, <?= ucfirst($patient['gender']??'') ?>
      </div>
      <form id="vitalsForm">
        <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
        <input type="hidden" name="appointment_id" value="<?= $apptId ?>">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label" style="font-size:12px">Temperature (°C)</label>
            <input type="number" name="temperature" id="temperature" class="form-control" step="0.1" min="34" max="42" placeholder="36.5">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">Pulse (bpm)</label>
            <input type="number" name="pulse" class="form-control" min="30" max="250" placeholder="75">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">BP Systolic (mmHg)</label>
            <input type="number" name="bp_sys" class="form-control" min="50" max="300" placeholder="120">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">BP Diastolic (mmHg)</label>
            <input type="number" name="bp_dia" class="form-control" min="30" max="200" placeholder="80">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">Resp. Rate (/min)</label>
            <input type="number" name="resp_rate" class="form-control" min="5" max="60" placeholder="16">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">SpO2 (%)</label>
            <input type="number" name="spo2" class="form-control" min="50" max="100" placeholder="98">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">Weight (kg)</label>
            <input type="number" name="weight" id="weight" class="form-control" step="0.1" min="1" max="300" placeholder="70" oninput="calcBMI()">
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:12px">Height (cm)</label>
            <input type="number" name="height" id="height" class="form-control" step="0.5" min="30" max="250" placeholder="170" oninput="calcBMI()">
          </div>
          <div class="col-12">
            <label class="form-label" style="font-size:12px">BMI (auto)</label>
            <input type="number" name="bmi" id="bmi" class="form-control" step="0.1" readonly placeholder="—" style="background:var(--bg)">
          </div>
          <div class="col-12">
            <label class="form-label" style="font-size:12px">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any observations..."></textarea>
          </div>
          <div class="col-12">
            <button type="button" onclick="saveVitals()" class="btn-dmc w-100"><i class="bi bi-check-circle"></i> Save Vitals</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Vitals History</div>
      <?php if ($recentVitals): foreach ($recentVitals as $v): ?>
      <div class="p-3 mb-2 rounded" style="background:var(--bg);font-size:12.5px">
        <div class="d-flex justify-content-between mb-2">
          <strong><?= dtF($v['recorded_at']) ?></strong>
          <span style="font-size:11px;color:var(--muted)">by <?= e($v['nurse_name']) ?></span>
        </div>
        <div class="row g-1">
          <div class="col-6 col-md-4"><span style="color:var(--muted)">Temp:</span> <?= $v['temperature'] ? $v['temperature'].'°C' : '—' ?></div>
          <div class="col-6 col-md-4"><span style="color:var(--muted)">BP:</span> <?= $v['blood_pressure_sys'] ? $v['blood_pressure_sys'].'/'.$v['blood_pressure_dia'].' mmHg' : '—' ?></div>
          <div class="col-6 col-md-4"><span style="color:var(--muted)">Pulse:</span> <?= $v['pulse_rate'] ? $v['pulse_rate'].' bpm' : '—' ?></div>
          <div class="col-6 col-md-4"><span style="color:var(--muted)">SpO2:</span> <?= $v['oxygen_saturation'] ? $v['oxygen_saturation'].'%' : '—' ?></div>
          <div class="col-6 col-md-4"><span style="color:var(--muted)">Weight:</span> <?= $v['weight'] ? $v['weight'].' kg' : '—' ?></div>
          <div class="col-6 col-md-4"><span style="color:var(--muted)">BMI:</span> <?= $v['bmi'] ?? '—' ?></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-4">No vitals recorded yet</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php $extraScripts = "<script>
function calcBMI(){
  const w=parseFloat(document.getElementById('weight').value)||0;
  const h=parseFloat(document.getElementById('height').value)||0;
  if(w&&h){const hm=h/100;document.getElementById('bmi').value=(w/(hm*hm)).toFixed(1);}
}
function saveVitals(){
  const f=document.getElementById('vitalsForm');
  const data={action:'update_vitals'};
  new FormData(f).forEach((v,k)=>data[k]=v);
  dmcPost('/dmc/api/ajax.php',data).then(j=>{
    if(j.ok){toast('Vitals saved!');setTimeout(()=>location.reload(),1200);}
    else defaultErr(j);
  });
}
function searchPat(q){
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    if(!j.results||!j.results.length){document.getElementById('patResults').innerHTML='<p class=\"text-muted\" style=\"font-size:12px\">No patients found.</p>';return;}
    document.getElementById('patResults').innerHTML=j.results.map(p=>`<a href=\"?patient_id=\${p.id}\" class=\"d-block p-2 mb-1 rounded text-decoration-none\" style=\"background:var(--bg);font-size:13px\"><strong>\${p.first_name} \${p.last_name}</strong> <span style=\"font-size:11px;color:var(--muted)\">\${p.patient_no} · \${p.phone}</span></a>`).join('');
  });
}
</script>";
include __DIR__ . '/../includes/footer.php';
