<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
$pageTitle = 'Book Appointment';

$error = $success = '';
$prePatient = (int)($_GET['patient_id'] ?? 0);
$patient = $prePatient ? row("SELECT * FROM patients WHERE id=?", [$prePatient]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId  = (int)($_POST['doctor_id']  ?? 0);
    $date      = $_POST['appointment_date'] ?? '';
    $time      = $_POST['appointment_time'] ?? '';
    $type      = $_POST['type'] ?? 'consultation';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$patientId || !$doctorId || !$date || !$time) {
        $error = 'Patient, doctor, date, and time are required.';
    } elseif ($date < date('Y-m-d')) {
        $error = 'Appointment date cannot be in the past.';
    } else {
        /* check daily capacity */
        $dayCount = (int)scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND status!='cancelled'", [$doctorId, $date]);
        /* check for double-booking */
        $clash = scalar("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status!='cancelled'", [$doctorId, $date, $time]);
        if ($dayCount >= 15) {
            $error = 'This doctor is fully booked for that day (15/15 patients). Please choose another date or doctor.';
        } elseif ($clash) {
            $error = 'That time slot is already booked for the selected doctor.';
        } else {
            $no = generateNo('DMC-A', 'appointments', 'appointment_no');
            $id = execute("INSERT INTO appointments (appointment_no,patient_id,doctor_id,appointment_date,appointment_time,type,reason,status,booked_by) VALUES (?,?,?,?,?,?,?,'scheduled',?)",
                [$no, $patientId, $doctorId, $date, $time, $type, $reason, currentUserId()]);

            /* send confirmation SMS */
            $pat = row("SELECT * FROM patients WHERE id=?", [$patientId]);
            $doc = row("SELECT CONCAT(first_name,' ',last_name) AS dname FROM users WHERE id=?", [$doctorId]);
            if ($pat) {
                sendAppointmentSMS($pat, [
                    'appointment_date' => $date, 'appointment_time' => $time,
                    'doctor_name'      => $doc['dname'] ?? '', 'appointment_no' => $no,
                ]);
            }
            audit('book_appointment','appointments',$id,"Booked $no for patient $patientId");
            flash('main', "Appointment $no booked successfully! SMS sent to patient.");
            header('Location: /dmc/receptionist/appointments.php'); exit;
        }
    }
}

$doctors = rows("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name, d.specialization
    FROM users u LEFT JOIN doctors d ON u.id=d.user_id
    WHERE u.role='doctor' AND u.is_active=1 ORDER BY u.first_name");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Book Appointment</div><div class="page-sub">Schedule a new patient appointment</div></div>
  <a href="/dmc/receptionist/appointments.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title">Appointment Details</div>
      <form method="POST" id="bookForm">

        <!-- Patient search -->
        <div class="mb-3">
          <label class="form-label">Patient *</label>
          <?php if ($patient): ?>
          <div class="p-2 rounded d-flex justify-content-between align-items-center" style="background:var(--bg)">
            <div>
              <strong><?= e($patient['first_name'].' '.$patient['last_name']) ?></strong>
              <span style="font-size:11px;color:var(--muted);margin-left:8px"><?= e($patient['patient_no']) ?> · <?= e($patient['phone']) ?></span>
            </div>
            <a href="/dmc/receptionist/book_appointment.php" class="btn btn-sm btn-outline-secondary" style="font-size:11px">Change</a>
          </div>
          <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
          <?php else: ?>
          <input type="text" id="patSearch" class="form-control" placeholder="Search by name, patient no, or phone..." oninput="searchPat(this.value)">
          <input type="hidden" name="patient_id" id="patientId" required>
          <div id="patResults" class="mt-1"></div>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Doctor *</label>
            <select name="doctor_id" class="form-select" required>
              <option value="">Select doctor...</option>
              <?php foreach ($doctors as $d): ?>
              <option value="<?= $d['id'] ?>"><?= e($d['name']) ?><?= $d['specialization'] ? ' — '.$d['specialization'] : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Appointment Type</label>
            <select name="type" class="form-select">
              <?php foreach(['consultation'=>'Consultation','follow_up'=>'Follow-Up','emergency'=>'Emergency','routine_checkup'=>'Routine Check-Up','lab_visit'=>'Lab Visit'] as $v=>$lbl): ?>
              <option value="<?= $v ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date *</label>
            <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" required value="<?= $_POST['appointment_date'] ?? '' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Time *</label>
            <select name="appointment_time" class="form-select" required>
              <option value="">Select time...</option>
              <?php
              $start = strtotime('08:00'); $end = strtotime('17:00');
              for ($t = $start; $t <= $end; $t += 30*60):
                  $val = date('H:i:s', $t); $lbl = date('h:i A', $t);
              ?>
              <option value="<?= $val ?>" <?= ($_POST['appointment_time']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Reason / Chief Complaint</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Describe the reason for visit..."><?= e($_POST['reason'] ?? '') ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="/dmc/receptionist/appointments.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn-dmc"><i class="bi bi-calendar-check"></i> Book Appointment</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">Quick Tips</div>
      <ul style="font-size:13px;color:var(--muted);padding-left:20px;line-height:2">
        <li>Patient must be registered before booking</li>
        <li>SMS confirmation is sent automatically</li>
        <li>Slots are 30 minutes each</li>
        <li>Emergency appointments can be booked any time</li>
        <li>Use 000000 as default OTP when SMS unavailable</li>
      </ul>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
function searchPat(q) {
  if(q.length<2){document.getElementById('patResults').innerHTML='';return;}
  dmcPost('/dmc/api/ajax.php',{action:'search_patient',q}).then(j=>{
    if(!j.results||!j.results.length){document.getElementById('patResults').innerHTML='<p class=\"text-muted\" style=\"font-size:12px;margin-top:4px\">No patients found.</p>';return;}
    document.getElementById('patResults').innerHTML=j.results.map(p=>`<div onclick=\"selectPat(\${p.id},'\${p.first_name} \${p.last_name}','\${p.patient_no}')\" class=\"p-2 mb-1 rounded\" style=\"background:var(--bg);cursor:pointer;font-size:13px\"><strong>\${p.first_name} \${p.last_name}</strong> <span style=\"font-size:11px;color:var(--muted)\">\${p.patient_no} · \${p.phone}</span></div>`).join('');
  });
}
function selectPat(id,name,no){
  document.getElementById('patientId').value=id;
  document.getElementById('patSearch').value=name+' ('+no+')';
  document.getElementById('patResults').innerHTML='';
}
</script>";
include __DIR__ . '/../includes/footer.php';
