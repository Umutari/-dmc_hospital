<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin','accountant']);
$pageTitle = 'Patient Detail';

$id = (int)($_GET['id'] ?? 0);
$patient = $id ? row("SELECT * FROM patients WHERE id=?", [$id]) : [];
if (!$patient) { flash('main','Patient not found.','danger'); header('Location: /dmc/receptionist/patients.php'); exit; }

$appts   = rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS dname FROM appointments a JOIN users u ON a.doctor_id=u.id WHERE a.patient_id=? ORDER BY a.appointment_date DESC LIMIT 5", [$id]);
$invoices= rows("SELECT * FROM invoices WHERE patient_id=? ORDER BY created_at DESC LIMIT 5", [$id]);
$vitals  = row("SELECT * FROM vital_signs WHERE patient_id=? ORDER BY recorded_at DESC LIMIT 1", [$id]);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title"><?= e($patient['first_name'].' '.$patient['last_name']) ?></div><div class="page-sub"><?= e($patient['patient_no']) ?></div></div>
  <div class="d-flex gap-2">
    <a href="/dmc/receptionist/patients.php" class="btn-dmc-outline"><i class="bi bi-arrow-left"></i> Back</a>
    <a href="/dmc/receptionist/book_appointment.php?patient_id=<?= $patient['id'] ?>" class="btn-dmc"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="dmc-card mb-3">
      <div class="text-center mb-3">
        <div class="patient-avatar mx-auto mb-2" style="width:64px;height:64px;font-size:22px"><?= strtoupper(substr($patient['first_name'],0,1).substr($patient['last_name'],0,1)) ?></div>
        <div style="font-size:16px;font-weight:700"><?= e($patient['first_name'].' '.$patient['last_name']) ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= e($patient['patient_no']) ?></div>
      </div>
      <div style="font-size:12.5px">
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Phone</span><strong><?= e($patient['phone']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Email</span><span><?= $patient['email'] ? e($patient['email']) : '—' ?></span></div>
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Gender</span><span><?= ucfirst($patient['gender']??'—') ?></span></div>
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Blood Group</span><span><?= e($patient['blood_group']??'Unknown') ?></span></div>
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Age</span><span><?= $patient['date_of_birth']?age($patient['date_of_birth']).' years':'—' ?></span></div>
        <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border)"><span style="color:var(--muted)">Address</span><span><?= $patient['address'] ? e($patient['address']) : '—' ?></span></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Registered</span><span><?= dateF($patient['created_at']) ?></span></div>
      </div>
    </div>

    <?php if ($patient['emergency_contact_name']): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title" style="font-size:13px">Emergency Contact</div>
      <div style="font-size:12.5px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Name</span><strong><?= e($patient['emergency_contact_name']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Phone</span><?= e($patient['emergency_contact_phone']??'—') ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($patient['insurance_provider']): ?>
    <div class="dmc-card">
      <div class="dmc-card-title" style="font-size:13px">Insurance</div>
      <div style="font-size:12.5px">
        <div class="d-flex justify-content-between mb-1"><span style="color:var(--muted)">Provider</span><strong><?= e($patient['insurance_provider']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Number</span><?= e($patient['insurance_number']??'—') ?></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <?php if ($vitals): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title" style="font-size:13px">Latest Vitals — <?= dtF($vitals['recorded_at']) ?></div>
      <div class="row g-2 text-center" style="font-size:12px">
        <div class="col"><div class="p-2 rounded" style="background:var(--bg)"><div style="font-size:16px;font-weight:700"><?= $vitals['temperature']?$vitals['temperature'].'°C':'—' ?></div><div style="color:var(--muted)">Temp</div></div></div>
        <div class="col"><div class="p-2 rounded" style="background:var(--bg)"><div style="font-size:16px;font-weight:700"><?= $vitals['blood_pressure_sys']?$vitals['blood_pressure_sys'].'/'.$vitals['blood_pressure_dia']:'—' ?></div><div style="color:var(--muted)">BP</div></div></div>
        <div class="col"><div class="p-2 rounded" style="background:var(--bg)"><div style="font-size:16px;font-weight:700"><?= $vitals['pulse_rate']??'—' ?></div><div style="color:var(--muted)">Pulse</div></div></div>
        <div class="col"><div class="p-2 rounded" style="background:var(--bg)"><div style="font-size:16px;font-weight:700"><?= $vitals['oxygen_saturation']?$vitals['oxygen_saturation'].'%':'—' ?></div><div style="color:var(--muted)">SpO2</div></div></div>
        <div class="col"><div class="p-2 rounded" style="background:var(--bg)"><div style="font-size:16px;font-weight:700"><?= $vitals['weight']?$vitals['weight'].' kg':'—' ?></div><div style="color:var(--muted)">Weight</div></div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Recent Appointments <a href="/dmc/receptionist/appointments.php">All →</a></div>
      <?php if ($appts): foreach ($appts as $a): ?>
      <div class="d-flex align-items-center gap-3 p-2 mb-2 rounded" style="background:var(--bg)">
        <div style="text-align:center;min-width:50px">
          <div style="font-size:14px;font-weight:700"><?= date('d',strtotime($a['appointment_date'])) ?></div>
          <div style="font-size:10px;color:var(--muted)"><?= date('M Y',strtotime($a['appointment_date'])) ?></div>
        </div>
        <div class="flex-1">
          <div style="font-size:12.5px;font-weight:600">Dr. <?= e($a['dname']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= timeF($a['appointment_time']) ?> · <?= ucfirst(str_replace('_',' ',$a['type'])) ?></div>
        </div>
        <span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-2" style="font-size:13px">No appointments</div>
      <?php endif; ?>
    </div>

    <div class="dmc-card">
      <div class="dmc-card-title">Invoices <a href="/dmc/accountant/invoices.php?action=new">+ New →</a></div>
      <?php if ($invoices): foreach ($invoices as $inv): ?>
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background:var(--bg);font-size:12.5px">
        <div>
          <div style="font-weight:600"><?= e($inv['invoice_no']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= dateF($inv['created_at']) ?></div>
        </div>
        <div class="text-end">
          <div style="font-weight:700"><?= money($inv['total']) ?></div>
          <span class="badge-status bs-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-center text-muted py-2" style="font-size:13px">No invoices</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
