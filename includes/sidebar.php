<?php
$role = currentRole();
$menus = [
    'admin' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/admin/index.php'],
        ['icon'=>'people','label'=>'Staff Management','url'=>'/dmc/admin/users.php'],
        ['icon'=>'building','label'=>'Departments','url'=>'/dmc/admin/departments.php'],
        ['icon'=>'person-badge','label'=>'Patients','url'=>'/dmc/admin/patients.php'],
        ['icon'=>'calendar-check','label'=>'Appointments','url'=>'/dmc/admin/appointments.php'],
        ['icon'=>'file-earmark-text','label'=>'Medical Records','url'=>'/dmc/admin/records.php'],
        ['icon'=>'capsule','label'=>'Pharmacy','url'=>'/dmc/admin/pharmacy.php'],
        ['icon'=>'flask','label'=>'Laboratory','url'=>'/dmc/admin/lab.php'],
        ['icon'=>'receipt','label'=>'Billing','url'=>'/dmc/admin/billing.php'],
        ['icon'=>'shield-check','label'=>'Insurance Settings','url'=>'/dmc/admin/insurance_settings.php'],
        ['icon'=>'bar-chart-line','label'=>'Reports','url'=>'/dmc/reports/index.php'],
        ['icon'=>'chat-dots','label'=>'SMS Logs','url'=>'/dmc/admin/sms_logs.php'],
        ['icon'=>'gear','label'=>'Settings','url'=>'/dmc/admin/settings.php'],
    ],
    'doctor' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/doctor/index.php'],
        ['icon'=>'calendar-check','label'=>'My Appointments','url'=>'/dmc/doctor/appointments.php'],
        ['icon'=>'people','label'=>'My Patients','url'=>'/dmc/doctor/patients.php'],
        ['icon'=>'file-earmark-medical','label'=>'Medical Records','url'=>'/dmc/doctor/records.php'],
        ['icon'=>'prescription','label'=>'Prescriptions','url'=>'/dmc/doctor/prescriptions.php'],
        ['icon'=>'flask','label'=>'Lab Orders','url'=>'/dmc/doctor/lab_orders.php'],
        ['icon'=>'receipt','label'=>'Invoices','url'=>'/dmc/doctor/invoices.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/doctor/profile.php'],
    ],
    'receptionist' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/receptionist/index.php'],
        ['icon'=>'person-plus','label'=>'Register Patient','url'=>'/dmc/receptionist/register_patient.php'],
        ['icon'=>'people','label'=>'All Patients','url'=>'/dmc/receptionist/patients.php'],
        ['icon'=>'calendar-plus','label'=>'Book Appointment','url'=>'/dmc/receptionist/book_appointment.php'],
        ['icon'=>'calendar-check','label'=>'Appointments','url'=>'/dmc/receptionist/appointments.php'],
        ['icon'=>'door-open','label'=>'Admissions','url'=>'/dmc/receptionist/admissions.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/receptionist/profile.php'],
    ],
    'pharmacist' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/pharmacist/index.php'],
        ['icon'=>'clipboard2-pulse','label'=>'Pending Prescriptions','url'=>'/dmc/pharmacist/prescriptions.php'],
        ['icon'=>'capsule','label'=>'Medicines','url'=>'/dmc/pharmacist/medicines.php'],
        ['icon'=>'box-seam','label'=>'Stock Management','url'=>'/dmc/pharmacist/stock.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/pharmacist/profile.php'],
    ],
    'accountant' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/accountant/index.php'],
        ['icon'=>'receipt','label'=>'Invoices','url'=>'/dmc/accountant/invoices.php'],
        ['icon'=>'credit-card','label'=>'Payments','url'=>'/dmc/accountant/payments.php'],
        ['icon'=>'wallet2','label'=>'Topup Money','url'=>'/dmc/accountant/topup.php'],
        ['icon'=>'shield-check','label'=>'Insurance Claims','url'=>'/dmc/accountant/insurance.php'],
        ['icon'=>'people','label'=>'Patients','url'=>'/dmc/accountant/patients.php'],
        ['icon'=>'bar-chart-line','label'=>'Financial Reports','url'=>'/dmc/reports/financial.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/accountant/profile.php'],
    ],
    'nurse' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/nurse/index.php'],
        ['icon'=>'activity','label'=>'Vital Signs','url'=>'/dmc/nurse/vitals.php'],
        ['icon'=>'people','label'=>'Patients','url'=>'/dmc/nurse/patients.php'],
        ['icon'=>'door-open','label'=>'Admissions','url'=>'/dmc/nurse/admissions.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/nurse/profile.php'],
    ],
    'lab_technician' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/lab/index.php'],
        ['icon'=>'flask','label'=>'Lab Orders','url'=>'/dmc/lab/orders.php'],
        ['icon'=>'clipboard2-check','label'=>'Results','url'=>'/dmc/lab/results.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/lab/profile.php'],
    ],
    'patient' => [
        ['icon'=>'speedometer2','label'=>'Dashboard','url'=>'/dmc/patient/index.php'],
        ['icon'=>'calendar-check','label'=>'My Appointments','url'=>'/dmc/patient/appointments.php'],
        ['icon'=>'file-earmark-medical','label'=>'Medical Records','url'=>'/dmc/patient/records.php'],
        ['icon'=>'receipt','label'=>'Invoices & Bills','url'=>'/dmc/patient/invoices.php'],
        ['icon'=>'person-circle','label'=>'My Profile','url'=>'/dmc/patient/profile.php'],
    ],
];
$nav = $menus[$role] ?? [];
$current = $_SERVER['REQUEST_URI'];
?>
<nav class="dmc-sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-orb"><i class="bi bi-hospital-fill"></i></div>
    <div class="brand-text">
      <div class="brand-name">DMC</div>
      <div class="brand-sub">Dream Medical Center</div>
    </div>
    <button class="sidebar-close d-lg-none" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
  </div>

  <div class="sidebar-role">
    <span class="role-pill"><?= ucfirst(str_replace('_',' ', $role)) ?></span>
  </div>

  <ul class="sidebar-nav">
    <?php foreach($nav as $item): ?>
    <?php $navActive = strpos($current, $item['url']) !== false ? 'active' : ''; ?>
    <li class="nav-item">
      <a href="<?= $item['url'] ?>" class="nav-link <?= $navActive ?>">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span><?= $item['label'] ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <div class="sf-avatar"><i class="bi bi-person-circle"></i></div>
      <div class="flex-1 overflow-hidden">
        <div class="sf-name"><?= e(currentUser()['first_name'] . ' ' . currentUser()['last_name']) ?></div>
        <div class="sf-role"><?= ucfirst(str_replace('_',' ', $role)) ?></div>
      </div>
      <a href="/dmc/auth/logout.php" class="sf-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</nav>
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay" onclick="toggleSidebar()"></div>
