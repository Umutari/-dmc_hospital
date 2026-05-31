<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['admin']);
$pageTitle = 'Insurance Settings';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['act'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $coverage = (int)($_POST['coverage_percentage'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$name || $coverage < 0 || $coverage > 100) {
            $error = 'Invalid insurance name or coverage percentage (0-100).';
        } else {
            $exists = row("SELECT id FROM insurance_providers WHERE name = ?", [$name]);
            if ($exists) {
                $error = 'Insurance provider with this name already exists.';
            } else {
                $patient_pct = 100 - $coverage;
                execute(
                    "INSERT INTO insurance_providers (name, coverage_percentage, patient_percentage, description, is_active) VALUES (?,?,?,?,1)",
                    [$name, $coverage, $patient_pct, $description]
                );
                audit('add_insurance', 'insurance_providers', 0, "Added $name with $coverage% coverage");
                flash('main', "Insurance provider '$name' added successfully (Coverage: $coverage%, Patient: $patient_pct%).", 'success');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $coverage = (int)($_POST['coverage_percentage'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$id || $coverage < 0 || $coverage > 100) {
            $error = 'Invalid ID or coverage percentage.';
        } else {
            $patient_pct = 100 - $coverage;
            execute(
                "UPDATE insurance_providers SET coverage_percentage=?, patient_percentage=?, description=? WHERE id=?",
                [$coverage, $patient_pct, $description, $id]
            );
            audit('edit_insurance', 'insurance_providers', $id, "Updated coverage to $coverage%");
            flash('main', "Insurance settings updated successfully.", 'success');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $ins = row("SELECT is_active FROM insurance_providers WHERE id=?", [$id]);
            $newStatus = $ins['is_active'] ? 0 : 1;
            execute("UPDATE insurance_providers SET is_active=? WHERE id=?", [$newStatus, $id]);
            $statusText = $newStatus ? 'activated' : 'deactivated';
            audit('toggle_insurance', 'insurance_providers', $id, "Insurance provider $statusText");
            flash('main', "Insurance provider " . $statusText . " successfully.", 'success');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $ins = row("SELECT name FROM insurance_providers WHERE id=?", [$id]);
            if ($ins && $ins['name'] !== 'PRIVATE') {
                execute("DELETE FROM insurance_providers WHERE id=?", [$id]);
                audit('delete_insurance', 'insurance_providers', $id, "Deleted: {$ins['name']}");
                flash('main', "Insurance provider deleted successfully.", 'success');
            } else {
                $error = 'Cannot delete system insurance providers.';
            }
        }
    }

    header('Location: /dmc/admin/insurance_settings.php'); exit;
}

$insurances = rows("SELECT * FROM insurance_providers ORDER BY coverage_percentage DESC");
$editId = (int)($_GET['edit'] ?? 0);
$editData = $editId ? row("SELECT * FROM insurance_providers WHERE id=?", [$editId]) : null;

$successMsg = getFlash('main');

include __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
  <div><div class="page-title">Insurance Settings</div><div class="page-sub">Manage insurance providers and coverage percentages</div></div>
</div>

<?php if ($successMsg): ?>
<div class="alert alert-<?= $successMsg['type'] ?? 'success' ?> alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle me-2"></i><?= e($successMsg['msg'] ?? '') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Add/Edit Form -->
  <div class="col-lg-5">
    <div class="dmc-card">
      <div class="dmc-card-title">
        <i class="bi bi-<?= $editData ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editData ? 'Edit Insurance Provider' : 'Add Insurance Provider' ?>
      </div>

      <form method="POST">
        <input type="hidden" name="act" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if ($editData): ?>
        <input type="hidden" name="id" value="<?= $editData['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Insurance Provider Name *</label>
          <input type="text" name="name" class="form-control" placeholder="e.g. RSSB, MEDIPLAN"
                 value="<?= e($editData['name'] ?? '') ?>" <?= $editData ? 'readonly style="background:var(--bg)"' : '' ?> required>
          <?php if ($editData): ?>
          <small class="text-muted">Cannot change name of existing provider</small>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Insurance Coverage Percentage * (0-100)</label>
          <div class="input-group">
            <input type="number" name="coverage_percentage" class="form-control" id="coverageInput"
                   min="0" max="100" value="<?= e($editData['coverage_percentage'] ?? '85') ?>" required>
            <span class="input-group-text">%</span>
          </div>
          <small class="text-muted d-block mt-2">
            Patient will pay: <strong id="patientPct">15</strong>%
            (Auto-calculated)
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label">Description (Optional)</label>
          <textarea name="description" class="form-control" rows="2"
                    placeholder="e.g. Rwanda Social Security Board"><?= e($editData['description'] ?? '') ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn-dmc flex-1">
            <i class="bi bi-<?= $editData ? 'check-circle' : 'plus-circle' ?> me-1"></i>
            <?= $editData ? 'Update' : 'Add Provider' ?>
          </button>
          <?php if ($editData): ?>
          <a href="/dmc/admin/insurance_settings.php" class="btn" style="background:var(--border);color:#666;flex:1;text-align:center">
            <i class="bi bi-x-circle me-1"></i>Cancel
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Coverage Guide -->
    <div class="dmc-card mt-3" style="background:#f9f9f9">
      <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:12px">COVERAGE GUIDE</div>
      <div style="font-size:12px;line-height:1.8">
        <div class="mb-2"><strong>Example:</strong></div>
        <div>If RSSB = 85%</div>
        <div style="margin-left:1rem">
          • Insurance covers: <strong style="color:var(--brand)">85%</strong><br>
          • Patient pays: <strong style="color:var(--danger)">15%</strong>
        </div>
        <div class="mt-2">
          On a RWF 100,000 bill:<br>
          <span style="margin-left:1rem">
            • Insurance: RWF 85,000<br>
            • Patient: RWF 15,000
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Insurance Providers List -->
  <div class="col-lg-7">
    <div class="dmc-card">
      <div class="dmc-card-title"><i class="bi bi-shield-check me-2"></i>All Insurance Providers</div>

      <div class="table-responsive">
        <table class="table dmc-table">
          <thead>
            <tr>
              <th>Provider Name</th>
              <th>Coverage %</th>
              <th>Patient %</th>
              <th>Description</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($insurances as $ins): ?>
            <tr>
              <td><strong><?= e($ins['name']) ?></strong></td>
              <td>
                <span style="background:rgba(26,107,181,.1);color:#1A6BB5;padding:3px 8px;border-radius:4px;font-weight:600;font-size:12px">
                  <?= (int)$ins['coverage_percentage'] ?>%
                </span>
              </td>
              <td>
                <span style="background:rgba(220,53,69,.1);color:var(--danger);padding:3px 8px;border-radius:4px;font-weight:600;font-size:12px">
                  <?= (int)$ins['patient_percentage'] ?>%
                </span>
              </td>
              <td style="font-size:12px;color:var(--muted)"><?= e($ins['description'] ?? '—') ?></td>
              <td>
                <span class="badge <?= $ins['is_active'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size:10px">
                  <?= $ins['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="font-size:12px">
                <div class="d-flex gap-1">
                  <a href="?edit=<?= $ins['id'] ?>" class="btn" style="background:#f0f4ff;color:#1A6BB5;padding:4px 8px;border-radius:4px" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <form method="POST" style="display:inline">
                    <input type="hidden" name="act" value="toggle">
                    <input type="hidden" name="id" value="<?= $ins['id'] ?>">
                    <button type="submit" class="btn" style="background:<?= $ins['is_active'] ? '#fff5f5' : '#f0f4ff' ?>;color:<?= $ins['is_active'] ? 'var(--danger)' : '#1A6BB5' ?>;padding:4px 8px;border-radius:4px;border:none;cursor:pointer" title="<?= $ins['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-<?= $ins['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                    </button>
                  </form>

                  <?php if ($ins['name'] !== 'PRIVATE'): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= e($ins['name']) ?>? This cannot be undone.')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?= $ins['id'] ?>">
                    <button type="submit" class="btn" style="background:#fff5f5;color:var(--danger);padding:4px 8px;border-radius:4px;border:none;cursor:pointer" title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="p-3 rounded mt-3" style="background:#f9f9f9;border-left:4px solid var(--brand);font-size:12px">
        <strong><i class="bi bi-info-circle me-2"></i>Note:</strong>
        Changes take effect immediately on new payments. Existing claims are not affected.
      </div>
    </div>

    <!-- Statistics -->
    <div class="dmc-card mt-3">
      <div class="dmc-card-title">Insurance Statistics</div>
      <div class="row g-2">
        <div class="col-md-6">
          <div style="background:var(--bg);padding:1rem;border-radius:8px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--brand)"><?= count($insurances) ?></div>
            <div style="font-size:11px;color:var(--muted)">Total Providers</div>
          </div>
        </div>
        <div class="col-md-6">
          <div style="background:var(--bg);padding:1rem;border-radius:8px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--success)"><?= count(array_filter($insurances, fn($i) => $i['is_active'])) ?></div>
            <div style="font-size:11px;color:var(--muted)">Active Providers</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('coverageInput').addEventListener('input', function() {
  const coverage = parseInt(this.value) || 0;
  const patient = Math.max(0, 100 - coverage);
  document.getElementById('patientPct').textContent = patient;
});
</script>

<?php include __DIR__ . '/../includes/footer.php';
