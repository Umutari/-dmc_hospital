<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['accountant','admin']);
$pageTitle = 'Topup Patient Account';

$success = '';
$topupData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['act'] ?? '';

    if ($action === 'search') {
        $search = trim($_POST['search'] ?? '');
        if ($search) {
            $topupData = row(
                "SELECT * FROM patients WHERE patient_no LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 1",
                ["%$search%", "%$search%", "%$search%"]
            );
            if (!$topupData) {
                $_SESSION['topup_error'] = 'Patient not found.';
            }
        } else {
            $_SESSION['topup_error'] = 'Please enter patient number, email, or phone.';
        }
    } elseif ($action === 'topup') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!$patientId || !$amount || $amount <= 0) {
            $_SESSION['topup_error'] = 'Invalid patient or amount.';
        } else {
            $patient = row("SELECT * FROM patients WHERE id=?", [$patientId]);
            if (!$patient) {
                $_SESSION['topup_error'] = 'Patient not found.';
            } else {
                /* add credit to patient account */
                execute("UPDATE patients SET balance = balance + ? WHERE id=?", [$amount, $patientId]);

                /* create receipt record */
                $receiptNo = generateNo('TOPUP', 'topup_receipts', 'receipt_no');
                execute(
                    "INSERT INTO topup_receipts (receipt_no, patient_id, amount, reason, issued_by) VALUES (?,?,?,?,?)",
                    [$receiptNo, $patientId, $amount, $reason, currentUserId()]
                );

                /* send SMS */
                $msg = "DMC Hospital\n✓ Account Topped Up!\nAmount: " . money($amount) .
                       "\nNew Balance: " . money((float)$patient['balance'] + $amount) .
                       "\nRef: $receiptNo\nThank you!";
                sendSMS($patient['phone'], $msg);

                /* audit log */
                audit('topup_account', 'patients', $patientId, "Added RWF $amount to account. Ref: $receiptNo");

                $_SESSION['topup_success'] = [
                    'receipt_no' => $receiptNo,
                    'patient' => $patient,
                    'amount' => $amount,
                    'new_balance' => $patient['balance'] + $amount
                ];

                header('Location: /dmc/accountant/topup.php?print=' . urlencode($receiptNo)); exit;
            }
        }
    }

    if (isset($_SESSION['topup_error'])) {
        header('Location: /dmc/accountant/topup.php');
        exit;
    }
}

$printReceipt = $_GET['print'] ?? '';
$successData = $_SESSION['topup_success'] ?? null;
unset($_SESSION['topup_success']);
$error = $_SESSION['topup_error'] ?? '';
unset($_SESSION['topup_error']);

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Topup Patient Account</div><div class="page-sub">Add credit to patient account balance</div></div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($successData && $printReceipt): ?>
<!-- Receipt Display -->
<div class="row">
  <div class="col-lg-6 mx-auto">
    <div class="dmc-card">
      <div class="text-center mb-4">
        <div style="font-size:24px;font-weight:700">DMC</div>
        <div style="font-size:12px;color:var(--muted)">Dream Medical Center</div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">KK 541 Street, Kigali | 0782 749 660</div>
      </div>

      <div class="divider" style="border-top:1px solid var(--border);margin:1rem 0"></div>

      <div style="text-align:center;margin-bottom:1rem">
        <div style="font-size:13px;font-weight:700;color:var(--success)">✓ TOPUP RECEIPT</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= dateF($successData['patient']['updated_at']) ?></div>
      </div>

      <div class="p-3 rounded mb-3" style="background:var(--bg);font-size:13px">
        <div class="d-flex justify-content-between mb-2"><span>Receipt No</span><strong><?= e($successData['receipt_no']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Patient</span><strong><?= e($successData['patient']['first_name'] . ' ' . $successData['patient']['last_name']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Patient No</span><strong><?= e($successData['patient']['patient_no']) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Phone</span><strong><?= e($successData['patient']['phone']) ?></strong></div>
      </div>

      <div class="p-3 rounded mb-3" style="background:#f5f5f5;font-size:13px">
        <div class="d-flex justify-content-between mb-2"><span style="font-weight:600">Amount Topped Up</span><strong style="font-size:16px;color:var(--success)"><?= money($successData['amount']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="font-weight:600">New Balance</span><strong style="font-size:16px;color:var(--brand)"><?= money($successData['new_balance']) ?></strong></div>
      </div>

      <div class="p-2 rounded" style="background:#f9f9f9;border-left:4px solid var(--brand);font-size:12px;margin-bottom:1rem">
        <strong>✓ SMS Sent</strong> to <?= e($successData['patient']['phone']) ?><br>
        Patient has been notified of this topup.
      </div>

      <div style="text-align:center">
        <button onclick="window.print()" class="btn-dmc" style="width:100%;margin-bottom:8px">
          <i class="bi bi-printer me-2"></i>Print Receipt
        </button>
        <a href="/dmc/accountant/topup.php" class="btn" style="width:100%;background:var(--border);color:#666">
          <i class="bi bi-plus-circle me-2"></i>Topup Another Patient
        </a>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
  .page-header, .btn, button { display: none !important; }
  .dmc-card { box-shadow: none; border: 1px solid #ccc; }
  body { padding: 0; }
}
</style>

<?php else: ?>

<div class="row g-3">
  <!-- Search & Topup Form -->
  <div class="col-lg-6">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-search me-2"></i>Search Patient</div>

      <form method="POST" id="searchForm">
        <input type="hidden" name="act" value="search">
        <div class="mb-3">
          <label class="form-label">Patient Number, Email or Phone *</label>
          <input type="text" name="search" class="form-control" placeholder="PAT-2024-0001, email@example.com, 0782123456" required>
        </div>
        <button type="submit" class="btn-dmc w-100"><i class="bi bi-search me-2"></i>Search</button>
      </form>
    </div>
  </div>

  <!-- Patient Details & Topup -->
  <div class="col-lg-6">
    <?php if ($topupData): ?>
    <div class="dmc-card mb-3">
      <div class="dmc-card-title">Patient Selected</div>
      <div class="p-3 rounded mb-3" style="background:var(--bg);font-size:13px">
        <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Patient</span><strong><?= e($topupData['first_name'] . ' ' . $topupData['last_name']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Patient No</span><strong><?= e($topupData['patient_no']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Phone</span><strong><?= e($topupData['phone']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span style="color:var(--muted)">Current Balance</span><strong style="color:var(--danger)"><?= money($topupData['balance']) ?></strong></div>
        <div class="d-flex justify-content-between"><span style="color:var(--muted)">Insurance</span><strong><?= e($topupData['insurance_provider'] ?? 'None') ?></strong></div>
      </div>
    </div>

    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-wallet2 me-2"></i>Add Credit</div>

      <form method="POST">
        <input type="hidden" name="act" value="topup">
        <input type="hidden" name="patient_id" value="<?= $topupData['id'] ?>">

        <div class="mb-3">
          <label class="form-label">Amount to Topup (RWF) *</label>
          <input type="number" name="amount" class="form-control" min="1" step="100" placeholder="e.g. 10000" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Reason (Optional)</label>
          <select name="reason" class="form-select">
            <option value="">Select or leave blank</option>
            <option value="Direct Payment">Direct Payment</option>
            <option value="Insurance Refund">Insurance Refund</option>
            <option value="Discount/Adjustment">Discount/Adjustment</option>
            <option value="Advance Payment">Advance Payment</option>
            <option value="Transfer">Transfer from Another Account</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <button type="submit" class="btn-dmc w-100" style="background:linear-gradient(135deg,#0A2342,#1A6BB5)">
          <i class="bi bi-plus-circle me-2"></i>Add Credit & Generate Receipt
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="dmc-card p-5 text-center" style="color:var(--muted)">
      <i class="bi bi-inbox" style="font-size:48px;opacity:.3;display:block;margin-bottom:1rem"></i>
      <p>Search for a patient to begin</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php';
