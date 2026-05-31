<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'System Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['hospital_name','hospital_address','hospital_phone','hospital_email','hospital_tagline',
             'flw_public_key','flw_secret_key','mista_api_key','wa_token','wa_phone_id',
             'default_otp','sms_sender_id','currency','timezone'];
    foreach ($keys as $key) {
        $val = trim($_POST[$key] ?? '');
        $exists = scalar("SELECT COUNT(*) FROM settings WHERE setting_key=?", [$key]);
        if ($exists) {
            execute("UPDATE settings SET setting_value=? WHERE setting_key=?", [$val,$key]);
        } else {
            execute("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)", [$key,$val]);
        }
    }
    audit('update_settings','settings',0,'System settings updated');
    flash('main','Settings saved successfully.');
    header('Location: /dmc/admin/settings.php'); exit;
}

$s = [];
$rows = rows("SELECT setting_key, setting_value FROM settings");
foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
$g = fn(string $k, string $d='') => $s[$k] ?? $d;

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">System Settings</div><div class="page-sub">Configure DMC Hospital system</div></div>
</div>

<?= showFlash('main') ?>

<form method="POST">
<div class="row g-3">
  <!-- Hospital Info -->
  <div class="col-lg-6">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-hospital me-2"></i>Hospital Information</div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Hospital Name</label><input name="hospital_name" class="form-control" value="<?= e($g('hospital_name','DMC - Dream Medical Center')) ?>"></div>
        <div class="col-12"><label class="form-label">Tagline</label><input name="hospital_tagline" class="form-control" value="<?= e($g('hospital_tagline','Your Health, Our Priority')) ?>"></div>
        <div class="col-12"><label class="form-label">Address</label><input name="hospital_address" class="form-control" value="<?= e($g('hospital_address','KK 541 Street, Kigali, Rwanda')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="hospital_phone" class="form-control" value="<?= e($g('hospital_phone','0782 749 660')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="hospital_email" class="form-control" value="<?= e($g('hospital_email','info@dmc.rw')) ?>"></div>
        <div class="col-md-6">
          <label class="form-label">Currency</label>
          <select name="currency" class="form-select">
            <?php foreach(['RWF','USD','EUR','GBP'] as $c): ?>
            <option value="<?= $c ?>" <?= $g('currency','RWF')===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Default OTP (fallback)</label><input name="default_otp" class="form-control" value="<?= e($g('default_otp','000000')) ?>" maxlength="6" pattern="\d{6}"></div>
      </div>
    </div>
  </div>

  <!-- SMS / WhatsApp -->
  <div class="col-lg-6">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-chat-dots me-2"></i>SMS & WhatsApp</div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Mista API Key</label><input name="mista_api_key" class="form-control" value="<?= e($g('mista_api_key')) ?>"></div>
        <div class="col-12"><label class="form-label">SMS Sender ID</label><input name="sms_sender_id" class="form-control" value="<?= e($g('sms_sender_id','DMC Hospital')) ?>"></div>
        <div class="col-12"><label class="form-label">WhatsApp Token (Meta)</label><textarea name="wa_token" class="form-control" rows="3"><?= e($g('wa_token')) ?></textarea></div>
        <div class="col-12"><label class="form-label">WhatsApp Phone ID</label><input name="wa_phone_id" class="form-control" value="<?= e($g('wa_phone_id')) ?>"></div>
      </div>
    </div>
  </div>

  <!-- Flutterwave -->
  <div class="col-12">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-credit-card me-2"></i>Flutterwave Payment Gateway</div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Public Key</label><input name="flw_public_key" class="form-control" value="<?= e($g('flw_public_key')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Secret Key</label><input type="password" name="flw_secret_key" class="form-control" value="<?= e($g('flw_secret_key')) ?>" autocomplete="new-password"></div>
        <div class="col-12">
          <div class="alert alert-info py-2" style="font-size:12.5px"><i class="bi bi-info-circle me-2"></i>Use test keys for development. Live keys require Flutterwave account approval. OTP for test MoMo: <strong>12345</strong>. Fallback OTP: <strong><?= e($g('default_otp','000000')) ?></strong></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 d-flex justify-content-end">
    <button type="submit" class="btn-dmc"><i class="bi bi-save"></i> Save All Settings</button>
  </div>
</div>
</form>

<?php include __DIR__ . '/../includes/footer.php';
