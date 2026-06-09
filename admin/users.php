<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'User Management';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name']  ?? '');
        $email = trim($_POST['email']      ?? '');
        $phone = trim($_POST['phone']      ?? '');
        $role  = trim($_POST['role']       ?? '');
        $pass  = trim($_POST['password']   ?? '');
        $spec  = trim($_POST['specialization'] ?? '');

        if (!$fname || !$lname || !$email || !$role || !$pass) {
            flash('main','All required fields must be filled.','danger');
        } elseif (scalar("SELECT COUNT(*) FROM users WHERE email=?", [$email])) {
            flash('main','Email already exists.','danger');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $uid  = execute("INSERT INTO users (first_name,last_name,email,phone,role,password,is_active) VALUES (?,?,?,?,?,?,1)",
                            [$fname,$lname,$email,$phone,$role,$hash]);
            if ($role === 'doctor') {
                execute("INSERT INTO doctors (user_id,specialization) VALUES (?,?)", [$uid, $spec ?: null]);
            }
            audit('create_user','users',$uid,"Created $role: $fname $lname");
            flash('main',"User $fname $lname created successfully.");
        }
    } elseif ($act === 'toggle') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $active = (int)($_POST['active']  ?? 0);
        if ($uid !== currentUserId()) {
            execute("UPDATE users SET is_active=? WHERE id=?", [$active, $uid]);
            flash('main','User status updated.');
        }
    } elseif ($act === 'reset_pass') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = trim($_POST['new_password'] ?? '');
        if ($uid && strlen($pass) >= 6) {
            execute("UPDATE users SET password=? WHERE id=?", [password_hash($pass,PASSWORD_DEFAULT), $uid]);
            audit('reset_password','users',$uid,"Password reset");
            flash('main','Password updated successfully.');
        } else {
            flash('main','Password must be at least 6 characters.','danger');
        }
    }
    header('Location: /dmc/admin/users.php'); exit;
}

$roleFilter = $_GET['role'] ?? 'all';
$users = $roleFilter === 'all'
    ? rows("SELECT u.*, d.specialization FROM users u LEFT JOIN doctors d ON u.id=d.user_id ORDER BY u.role, u.first_name")
    : rows("SELECT u.*, d.specialization FROM users u LEFT JOIN doctors d ON u.id=d.user_id WHERE u.role=? ORDER BY u.first_name", [$roleFilter]);

$roles = ['admin','doctor','nurse','receptionist','pharmacist','accountant','lab_technician','patient'];

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">User Management</div><div class="page-sub"><?= count($users) ?> users</div></div>
  <button class="btn-dmc" onclick="document.getElementById('createModal').style.display='flex'"><i class="bi bi-person-plus"></i> Add User</button>
</div>

<?= showFlash('main') ?>

<!-- Role filter -->
<div class="d-flex gap-1 mb-3 flex-wrap">
  <a href="/dmc/admin/users.php" class="btn btn-sm <?= $roleFilter==='all'?'btn-primary':'btn-outline-secondary' ?>">All</a>
  <?php foreach ($roles as $r): ?>
  <a href="?role=<?= $r ?>" class="btn btn-sm <?= $roleFilter===$r?'btn-primary':'btn-outline-secondary' ?>"><?= ucfirst(str_replace('_',' ',$r)) ?></a>
  <?php endforeach; ?>
</div>

<div class="dmc-card">
  <div class="table-responsive">
    <table class="table dmc-table mb-0">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="patient-avatar" style="width:32px;height:32px;font-size:11px"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
            <strong style="font-size:13px"><?= e($u['first_name'].' '.$u['last_name']) ?></strong>
          </div>
        </td>
        <td style="font-size:12px"><?= e($u['email']) ?></td>
        <td style="font-size:12px"><?= e($u['phone']??'—') ?></td>
        <td><span class="badge-status bs-active" style="font-size:10px"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
        <td style="font-size:12px;color:var(--muted)"><?= $u['specialization'] ? e($u['specialization']) : '—' ?></td>
        <td>
          <span class="badge-status <?= $u['is_active']?'bs-active':'bs-cancelled' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span>
        </td>
        <td>
          <div class="d-flex gap-1">
            <?php if ($u['id'] !== currentUserId()): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="act" value="toggle">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="active" value="<?= $u['is_active']?0:1 ?>">
              <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-outline-danger':'btn-outline-success' ?>" style="font-size:10px"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
            </form>
            <?php endif; ?>
            <button onclick="openReset(<?= $u['id'] ?>, '<?= e(addslashes($u['first_name'].' '.$u['last_name'])) ?>')" class="btn btn-sm btn-outline-secondary" style="font-size:10px">Reset PW</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create user modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(560px,95vw);max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <strong style="font-size:16px">Create New User</strong>
      <button onclick="document.getElementById('createModal').style.display='none'" class="btn btn-sm btn-outline-secondary">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="create">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">First Name *</label><input name="first_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Last Name *</label><input name="last_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" placeholder="07XXXXXXXX"></div>
        <div class="col-md-6">
          <label class="form-label">Role *</label>
          <select name="role" id="roleSelect" class="form-select" required onchange="toggleSpec(this.value)">
            <option value="">Select role...</option>
            <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= ucfirst(str_replace('_',' ',$r)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6" id="specField" style="display:none">
          <label class="form-label">Specialization</label>
          <input name="specialization" class="form-control" placeholder="e.g. Cardiology">
        </div>
        <div class="col-12"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" minlength="6" required></div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn-dmc">Create User</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Reset password modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:min(360px,95vw)">
    <strong id="resetTitle" style="font-size:15px;display:block;margin-bottom:16px">Reset Password</strong>
    <form method="POST">
      <input type="hidden" name="act" value="reset_pass">
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="mb-3"><label class="form-label">New Password (min 6 chars)</label><input type="password" name="new_password" class="form-control" minlength="6" required></div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" onclick="document.getElementById('resetModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn-dmc">Update Password</button>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = "<script>
function toggleSpec(role){document.getElementById('specField').style.display=role==='doctor'?'block':'none';}
function openReset(id,name){document.getElementById('resetUserId').value=id;document.getElementById('resetTitle').textContent='Reset Password: '+name;document.getElementById('resetModal').style.display='flex';}
</script>";
include __DIR__ . '/../includes/footer.php';
