<?php $unread = unreadCount(currentUserId()); ?>
<header class="dmc-topbar">
  <button class="topbar-toggle d-lg-none" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
  </button>
  <div class="topbar-breadcrumb">
    <span class="tb-page"><?= $pageTitle ?? 'Dashboard' ?></span>
  </div>
  <div class="topbar-right">
    <!-- Date/Time -->
    <div class="tb-datetime d-none d-md-flex">
      <i class="bi bi-clock me-1"></i>
      <span id="tbClock"></span>
    </div>

    <!-- Notifications -->
    <div class="dropdown">
      <button class="tb-icon-btn position-relative" data-bs-toggle="dropdown">
        <i class="bi bi-bell"></i>
        <?php if($unread > 0): ?>
        <span class="notif-badge"><?= $unread > 9 ? '9+' : $unread ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end notif-dropdown">
        <div class="notif-header">
          <span>Notifications</span>
          <?php if($unread > 0): ?>
          <a href="#" onclick="dmcPost('/dmc/api/ajax.php',{action:'mark_all_read'}).then(()=>location.reload())" class="notif-mark-all">Mark all read</a>
          <?php endif; ?>
        </div>
        <?php
        $notifs = rows("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8", [currentUserId()]);
        if ($notifs): foreach($notifs as $n): ?>
        <a href="<?= e($n['link'] ?: '#') ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
          <div class="notif-icon notif-<?= e($n['type']) ?>">
            <i class="bi bi-<?= $n['type']==='success'?'check-circle':($n['type']==='danger'?'x-circle':($n['type']==='warning'?'exclamation-triangle':'info-circle')) ?>"></i>
          </div>
          <div class="notif-body">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <div class="notif-time"><?= dtF($n['created_at']) ?></div>
          </div>
        </a>
        <?php endforeach; else: ?>
        <div class="notif-empty"><i class="bi bi-bell-slash"></i> No notifications</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- User menu -->
    <div class="dropdown">
      <button class="tb-user-btn" data-bs-toggle="dropdown">
        <div class="tb-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="d-none d-md-block text-start">
          <div class="tb-uname"><?= e(currentUser()['first_name']) ?></div>
          <div class="tb-urole"><?= ucfirst(str_replace('_',' ', currentRole())) ?></div>
        </div>
        <i class="bi bi-chevron-down ms-1" style="font-size:10px"></i>
      </button>
      <?php
      $profileUrls = [
        'admin'          => '/dmc/admin/users.php',
        'doctor'         => '/dmc/doctor/profile.php',
        'nurse'          => '/dmc/nurse/profile.php',
        'receptionist'   => '/dmc/receptionist/profile.php',
        'pharmacist'     => '/dmc/pharmacist/profile.php',
        'accountant'     => '/dmc/accountant/profile.php',
        'lab_technician' => '/dmc/lab/profile.php',
        'patient'        => '/dmc/patient/profile.php',
      ];
      $profileUrl = $profileUrls[currentRole()] ?? '#';
      ?>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= $profileUrl ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="/dmc/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
<script>
(function clock(){const el=document.getElementById('tbClock');if(el){const t=new Date();el.textContent=t.toLocaleTimeString('en-RW',{hour:'2-digit',minute:'2-digit'})+ ' · '+t.toLocaleDateString('en-RW',{weekday:'short',day:'numeric',month:'short'});}setTimeout(clock,60000);}())
</script>
