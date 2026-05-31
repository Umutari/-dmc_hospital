<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'Admin Dashboard';

$stats = [
    'patients'     => scalar("SELECT COUNT(*) FROM patients"),
    'appointments' => scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()"),
    'doctors'      => scalar("SELECT COUNT(*) FROM users WHERE role='doctor' AND is_active=1"),
    'revenue'      => scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())"),
    'pending_inv'  => scalar("SELECT COUNT(*) FROM invoices WHERE status IN('draft','issued','partial')"),
    'admissions'   => scalar("SELECT COUNT(*) FROM admissions WHERE status='active'"),
];

$recentPatients    = rows("SELECT * FROM patients ORDER BY created_at DESC LIMIT 6");
$todayAppointments = rows("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, CONCAT(u.first_name,' ',u.last_name) AS doctor_name FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON a.doctor_id=u.id WHERE a.appointment_date=CURDATE() ORDER BY a.appointment_time LIMIT 8");

/* chart data: last 7 days revenue */
$revenueData = rows("SELECT DATE(paid_at) as d, SUM(amount) as total FROM payments WHERE status='success' AND paid_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(paid_at) ORDER BY d");
$chartLabels = array_column($revenueData, 'd');
$chartValues = array_column($revenueData, 'total');

/* appointments by dept — join through doctors since appointments.department_id may not exist */
$deptData = rows("SELECT d.name, COUNT(a.id) as cnt FROM appointments a JOIN users u ON a.doctor_id=u.id JOIN doctors doc ON u.id=doc.user_id LEFT JOIN departments d ON doc.department_id=d.id WHERE MONTH(a.appointment_date)=MONTH(NOW()) AND d.id IS NOT NULL GROUP BY d.id ORDER BY cnt DESC LIMIT 6");

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div>
    <div class="page-title">Admin Dashboard</div>
    <div class="page-sub"><?= date('l, d F Y') ?> — Welcome back, <?= e(currentUser()['first_name']) ?></div>
  </div>
  <a href="/dmc/admin/reports.php" class="btn-dmc"><i class="bi bi-bar-chart-line"></i> View Reports</a>
</div>

<?= showFlash('main') ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-label">Patients</div><div class="stat-value"><?= number_format($stats['patients']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-calendar-check-fill"></i></div>
      <div><div class="stat-label">Today Appts</div><div class="stat-value"><?= $stats['appointments'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-person-badge-fill"></i></div>
      <div><div class="stat-label">Doctors</div><div class="stat-value"><?= $stats['doctors'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="bi bi-door-open-fill"></i></div>
      <div><div class="stat-label">Admitted</div><div class="stat-value"><?= $stats['admissions'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-label">Pending Bills</div><div class="stat-value"><?= $stats['pending_inv'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-cash-coin"></i></div>
      <div><div class="stat-label">Month Revenue</div><div class="stat-value" style="font-size:16px"><?= money($stats['revenue']) ?></div></div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Revenue (Last 7 Days) <a href="/dmc/reports/financial.php">Full report →</a></div>
      <canvas id="revenueChart" height="80"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Appointments by Department</div>
      <canvas id="deptChart" height="170"></canvas>
    </div>
  </div>
</div>

<!-- Today Appointments + Recent Patients -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="dmc-card">
      <div class="dmc-card-title">Today's Appointments <a href="/dmc/admin/appointments.php">View all →</a></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($todayAppointments): foreach ($todayAppointments as $a): ?>
          <tr>
            <td><strong><?= timeF($a['appointment_time']) ?></strong></td>
            <td><?= e($a['patient_name']) ?></td>
            <td>Dr. <?= e($a['doctor_name']) ?></td>
            <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
            <td><span class="badge-status bs-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No appointments today</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="dmc-card">
      <div class="dmc-card-title">Recent Patients <a href="/dmc/admin/patients.php">View all →</a></div>
      <?php foreach ($recentPatients as $p): ?>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="patient-avatar"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
        <div class="flex-1">
          <div style="font-size:13px;font-weight:600"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= e($p['patient_no']) ?> · <?= e($p['phone']) ?></div>
        </div>
        <span class="badge-status bs-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$extraScripts = "<script>
const rCtx = document.getElementById('revenueChart');
new Chart(rCtx, {
  type:'line',
  data:{
    labels:" . json_encode($chartLabels) . ",
    datasets:[{
      label:'Revenue (RWF)',
      data:" . json_encode(array_map('floatval', $chartValues)) . ",
      borderColor:'#1A6BB5',backgroundColor:'rgba(26,107,181,.08)',
      tension:.4,fill:true,pointRadius:5,pointBackgroundColor:'#1A6BB5'
    }]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'RWF '+v.toLocaleString()}}}}
});
const dCtx = document.getElementById('deptChart');
new Chart(dCtx, {
  type:'doughnut',
  data:{
    labels:" . json_encode(array_column($deptData, 'name')) . ",
    datasets:[{data:" . json_encode(array_column($deptData, 'cnt')) . ",
      backgroundColor:['#1A6BB5','#0E6655','#E17B10','#6D28D9','#D14A30','#0F766E']}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}}}}
});
</script>";
include __DIR__ . '/../includes/footer.php';
