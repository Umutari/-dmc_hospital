<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['patient','admin']);
$pageTitle = 'My Appointments';
$uid = currentUserId();
$patient = row("SELECT * FROM patients WHERE email=(SELECT email FROM users WHERE id=?)", [$uid]);
if (!$patient) $patient = row("SELECT * FROM patients WHERE phone=(SELECT phone FROM users WHERE id=?)", [$uid]);
$pid = $patient['id'] ?? 0;

/* ── Book appointment ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pid) {
    $doctorId = (int)($_POST['doctor_id']  ?? 0);
    $date     = $_POST['appointment_date'] ?? '';
    $time     = $_POST['appointment_time'] ?? '';
    $type     = $_POST['type']             ?? 'consultation';
    $reason   = trim($_POST['reason']      ?? '');

    if (!$doctorId || !$date || !$time) {
        flash('main', 'Please fill in all required fields.', 'danger');
    } elseif ($date < date('Y-m-d')) {
        flash('main', 'Appointment date cannot be in the past.', 'danger');
    } else {
        $dayCount = (int)scalar(
            "SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND status!='cancelled'",
            [$doctorId, $date]
        );
        if ($dayCount >= 15) {
            flash('main', 'This doctor is fully booked for that day (15/15 patients). Please choose another date or doctor.', 'danger');
        } else {
            $clash = (int)scalar(
                "SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status!='cancelled'",
                [$doctorId, $date, $time]
            );
            if ($clash) {
                flash('main', 'That time slot is already taken. Please choose another time.', 'danger');
            } else {
                $no = generateNo('DMC-A', 'appointments', 'appointment_no');
                $id = execute(
                    "INSERT INTO appointments (appointment_no,patient_id,doctor_id,appointment_date,appointment_time,type,reason,status,booked_by)
                     VALUES (?,?,?,?,?,?,?,'scheduled',?)",
                    [$no, $pid, $doctorId, $date, $time, $type, $reason, currentUserId()]
                );
                $doc = row("SELECT CONCAT(first_name,' ',last_name) AS dname FROM users WHERE id=?", [$doctorId]);
                sendAppointmentSMS($patient, [
                    'appointment_date' => $date, 'appointment_time' => $time,
                    'doctor_name' => $doc['dname'] ?? '', 'appointment_no' => $no,
                ]);
                audit('book_appointment', 'appointments', $id, "Patient booked $no");
                flash('main', "Appointment $no booked successfully! You'll receive an SMS confirmation.");
            }
        }
    }
    header('Location: /dmc/patient/appointments.php'); exit;
}

$appts = $pid ? rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS dname, d.specialization
    FROM appointments a JOIN users u ON a.doctor_id=u.id LEFT JOIN doctors d ON u.id=d.user_id
    WHERE a.patient_id=? ORDER BY a.appointment_date DESC, a.appointment_time DESC", [$pid]) : [];

$doctors = rows("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name, d.specialization
    FROM users u LEFT JOIN doctors d ON u.id=d.user_id
    WHERE u.role='doctor' AND u.is_active=1 ORDER BY u.first_name");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">My Appointments</div><div class="page-sub"><?= count($appts) ?> total appointments</div></div>
  <?php if ($pid): ?>
  <button class="btn-dmc" onclick="document.getElementById('bookModal').style.display='flex'">
    <i class="bi bi-calendar-plus"></i> Book Appointment
  </button>
  <?php endif; ?>
</div>

<?= showFlash('main') ?>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Date & Time</th><th>Doctor</th><th>Type</th><th>Reason</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($appts as $a): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= dateF($a['appointment_date']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= timeF($a['appointment_time']) ?></div>
        </td>
        <td>
          <div style="font-size:13px">Dr. <?= e($a['dname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($a['specialization']??'General') ?></div>
        </td>
        <td style="font-size:12px"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></td>
        <td style="font-size:12px;max-width:200px"><?= $a['reason'] ? e(substr($a['reason'],0,60)) : '—' ?></td>
        <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$appts): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No appointments yet. Click "Book Appointment" to get started.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Book Appointment Modal -->
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(560px,95vw);max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px"><i class="bi bi-calendar-plus me-2"></i>Book Appointment</strong>
      <button onclick="closeBookModal()" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST" onsubmit="return validateBook(event)">
      <div class="mb-3">
        <label class="form-label">Doctor *</label>
        <select name="doctor_id" id="bookDoctor" class="form-select" required onchange="loadSlots()">
          <option value="">Select a doctor...</option>
          <?php foreach ($doctors as $d): ?>
          <option value="<?= $d['id'] ?>"><?= e($d['name']) ?><?= $d['specialization'] ? ' — '.$d['specialization'] : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Date *</label>
          <input type="date" name="appointment_date" id="bookDate" class="form-control"
                 min="<?= date('Y-m-d') ?>" required onchange="loadSlots()">
        </div>
        <div class="col-6">
          <label class="form-label">Time *</label>
          <select name="appointment_time" id="bookTime" class="form-select" required>
            <option value="">Select doctor & date first</option>
          </select>
        </div>
      </div>
      <!-- Capacity indicator -->
      <div id="capacityBar" class="mb-3" style="display:none">
        <div class="d-flex justify-content-between mb-1" style="font-size:12px">
          <span>Doctor's patients that day</span>
          <span id="capacityText"></span>
        </div>
        <div style="background:var(--border);border-radius:4px;height:8px">
          <div id="capacityFill" style="height:8px;border-radius:4px;transition:width .3s"></div>
        </div>
        <div id="capacityWarn" style="font-size:11px;margin-top:4px;display:none"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Appointment Type</label>
        <select name="type" class="form-select">
          <option value="consultation">Consultation</option>
          <option value="follow_up">Follow-Up</option>
          <option value="routine_checkup">Routine Check-Up</option>
          <option value="emergency">Emergency</option>
          <option value="lab_visit">Lab Visit</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Reason / Chief Complaint</label>
        <textarea name="reason" class="form-control" rows="2" placeholder="Briefly describe your reason for visit..."></textarea>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="closeBookModal()" class="btn btn-secondary">Cancel</button>
        <button type="submit" id="bookSubmitBtn" class="btn-dmc"><i class="bi bi-calendar-check me-1"></i>Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<?php
$timeSlotsJson = json_encode(array_map(function($t) {
    return ['val' => date('H:i:s', $t), 'lbl' => date('h:i A', $t)];
}, range(strtotime('08:00'), strtotime('17:00'), 30*60)));

$extraScripts = "<script>
const ALL_SLOTS = $timeSlotsJson;

function closeBookModal(){
  document.getElementById('bookModal').style.display='none';
  document.getElementById('capacityBar').style.display='none';
  document.getElementById('bookTime').innerHTML='<option value=\"\">Select doctor & date first</option>';
}

function loadSlots(){
  const doctorId = document.getElementById('bookDoctor').value;
  const date     = document.getElementById('bookDate').value;
  if(!doctorId || !date) return;

  dmcPost('/dmc/api/ajax.php', {action:'get_doctor_slots', doctor_id:parseInt(doctorId), date}).then(j=>{
    if(!j.ok) return;
    const booked = j.booked || [];
    const count  = j.count  || 0;
    const pct    = Math.min(100, Math.round(count/15*100));

    // capacity bar
    document.getElementById('capacityBar').style.display='block';
    document.getElementById('capacityText').textContent = count+'/15 patients';
    const fill = document.getElementById('capacityFill');
    fill.style.width = pct+'%';
    fill.style.background = count>=15?'var(--danger)':count>=12?'#f59e0b':'var(--success)';
    const warn = document.getElementById('capacityWarn');
    if(count>=15){
      warn.style.display='block'; warn.style.color='var(--danger)';
      warn.textContent='⚠ This doctor is fully booked for this day. Please choose another date or doctor.';
    } else if(count>=12){
      warn.style.display='block'; warn.style.color='#b45309';
      warn.textContent='⚠ Only '+(15-count)+' slot(s) remaining for this doctor today.';
    } else {
      warn.style.display='none';
    }

    // time slots
    const sel = document.getElementById('bookTime');
    sel.innerHTML = count>=15
      ? '<option value=\"\">No slots available — doctor at capacity</option>'
      : ALL_SLOTS.map(s=>{
          const taken = booked.includes(s.val);
          return '<option value=\"'+s.val+'\" '+(taken?'disabled style=\"color:#ccc\"':'')+'>'+s.lbl+(taken?' (taken)':'')+'</option>';
        }).join('');

    // disable submit if at capacity
    document.getElementById('bookSubmitBtn').disabled = count>=15;
  });
}

function validateBook(e){
  const time = document.getElementById('bookTime').value;
  if(!time){ e.preventDefault(); alert('Please select an available time slot.'); return false; }
}
</script>";
include __DIR__ . '/../includes/footer.php';
