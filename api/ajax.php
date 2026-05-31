<?php
require_once __DIR__ . '/../config/functions.php';
requireLogin();

header('Content-Type: application/json');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_POST['action'] ?? '');

function jsonOk(array $data = []): void  { echo json_encode(['ok' => true]  + $data); exit; }
function jsonErr(string $msg): void      { echo json_encode(['ok' => false, 'error' => $msg]); exit; }

match($action) {
    'collect_payment'    => collectPayment($body),
    'pay_all_invoices'   => payAllInvoices($body),
    'mark_all_read'      => markAllRead(),
    'mark_read'          => markRead($body),
    'cancel_appointment' => cancelAppt($body),
    'confirm_appointment'=> confirmAppt($body),
    'update_vitals'      => updateVitals($body),
    'dispense_medicine'  => dispenseMedicine($body),
    'update_lab_result'  => updateLabResult($body),
    'update_invoice_item'=> updateInvoiceItem($body),
    'delete_invoice_item'=> deleteInvoiceItem($body),
    'search_patient'     => searchPatient($body),
    'search_medicine'    => searchMedicine($body),
    default              => jsonErr('Unknown action'),
};

/* ─────────────────────────────── PAYMENT ─────────────────────────── */
function collectPayment(array $b): void {
    requireRoles(['accountant','admin','patient']);
    $invoiceId = (int)($b['invoice_id'] ?? 0);
    $patientId = (int)($b['patient_id'] ?? 0);
    $amount    = (float)($b['amount'] ?? 0);
    $method    = trim($b['method'] ?? 'cash');
    $notes     = trim($b['notes'] ?? '');
    $flwTxid   = trim($b['flw_txid'] ?? '');
    $flwRef    = trim($b['flw_ref'] ?? '');

    if (!$invoiceId || !$amount || $amount < 1) jsonErr('Invalid payment data.');

    $inv     = row("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
    if (!$inv) jsonErr('Invoice not found.');

    /* check patient account balance — must have enough to cover this payment */
    $patient = row("SELECT * FROM patients WHERE id=?", [$patientId]);
    if (!$patient) jsonErr('Patient not found.');
    $patAcct = (float)$patient['balance'];
    if ($patAcct < 0.01) jsonErr('Patient account has no outstanding balance.');
    if ($amount > $patAcct + 0.01) jsonErr('Amount (RWF '.number_format($amount).') exceeds patient account balance (RWF '.number_format($patAcct).').');

    /* also cannot exceed the specific invoice remaining */
    $alreadyPaid = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='success'", [$invoiceId]);
    $invBalance  = max(0, (float)$inv['total'] - $alreadyPaid);
    if ($amount > $invBalance + 0.01) jsonErr('Amount exceeds this invoice balance (RWF '.number_format($invBalance).').');

    /* ──── INSURANCE COVERAGE LOGIC ──── */
    $insuranceProvider = $patient['insurance_provider'] ?? null;
    $insuranceAmount = 0;
    $patientPaymentAmount = $amount;

    if ($insuranceProvider && $insuranceProvider !== 'PRIVATE') {
        $insurance = row("SELECT * FROM insurance_providers WHERE name = ? AND is_active = 1", [$insuranceProvider]);
        if ($insurance) {
            $coveragePercent = (int)($insurance['coverage_percentage'] ?? 80);
            $insuranceAmount = round($amount * ($coveragePercent / 100), 2);
            $patientPaymentAmount = $amount - $insuranceAmount;

            /* create insurance claim if insurance portion exists */
            if ($insuranceAmount > 0) {
                execute(
                    "INSERT INTO insurance_claims (invoice_id, patient_id, insurance_provider, total_amount, insurance_amount, patient_amount, insurance_status)
                     VALUES (?,?,?,?,?,?,'pending')",
                    [$invoiceId, $patientId, $insuranceProvider, $amount, $insuranceAmount, $patientPaymentAmount]
                );
            }
        }
    }

    $status = in_array($method, ['momo_mtn','momo_airtel','card']) && $flwTxid ? 'success' : 'success';
    if (in_array($method, ['insurance','bank_transfer'])) $status = 'pending';

    $payNo = generateNo('DMC-PAY', 'payments', 'payment_no');
    $payId = execute(
        "INSERT INTO payments (payment_no,invoice_id,patient_id,amount,method,status,flw_transaction_id,flw_ref,notes,paid_at,collected_by)
         VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)",
        [$payNo, $invoiceId, $patientId, $amount, $method, $status, $flwTxid, $flwRef, $notes, currentUserId()]
    );

    /* recalculate this invoice */
    $paid      = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='success'", [$invoiceId]);
    $bal       = max(0, (float)$inv['total'] - $paid);
    $newStatus = $bal <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'issued');
    execute("UPDATE invoices SET paid=?, balance=?, status=? WHERE id=?", [$paid, $bal, $newStatus, $invoiceId]);

    /* deduct payment from patient account balance */
    if ($status === 'success') {
        execute("UPDATE patients SET balance = GREATEST(0, balance - ?) WHERE id=?", [$amount, $patientId]);
    }
    $patientBalance = (float)scalar("SELECT balance FROM patients WHERE id=?", [$patientId]);

    /* send SMS receipt */
    if ($status === 'success') {
        $receiptMsg = ['amount' => $amount, 'invoice_no' => $inv['invoice_no'], 'payment_no' => $payNo];
        if ($insuranceAmount > 0) {
            $receiptMsg['insurance_amount'] = $insuranceAmount;
            $receiptMsg['patient_amount'] = $patientPaymentAmount;
        }
        sendPaymentSMS($patient, $receiptMsg);
    }

    audit('collect_payment', 'payments', $payId, "Collected $amount via $method (Insurance: $insuranceAmount, Patient: $patientPaymentAmount) for invoice {$inv['invoice_no']}");
    jsonOk(['payment_no' => $payNo, 'new_balance' => $bal, 'invoice_status' => $newStatus, 'patient_balance' => $patientBalance,
            'insurance_amount' => $insuranceAmount, 'patient_amount' => $patientPaymentAmount]);
}

/* ─────────────────────────────── NOTIFICATIONS ─────────────────────── */
function markAllRead(): void {
    execute("UPDATE notifications SET is_read=1 WHERE user_id=?", [currentUserId()]);
    jsonOk();
}

function markRead(array $b): void {
    $id = (int)($b['id'] ?? 0);
    if ($id) execute("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [$id, currentUserId()]);
    jsonOk();
}

/* ─────────────────────────────── APPOINTMENTS ───────────────────────── */
function cancelAppt(array $b): void {
    requireRoles(['receptionist','admin','doctor']);
    $id = (int)($b['id'] ?? 0);
    if (!$id) jsonErr('Invalid appointment.');
    execute("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
    audit('cancel_appointment', 'appointments', $id);
    jsonOk();
}

function confirmAppt(array $b): void {
    requireRoles(['receptionist','admin']);
    $id = (int)($b['id'] ?? 0);
    if (!$id) jsonErr('Invalid appointment.');
    execute("UPDATE appointments SET status='confirmed' WHERE id=?", [$id]);
    audit('confirm_appointment', 'appointments', $id);
    jsonOk();
}

/* ─────────────────────────────── VITALS ─────────────────────────────── */
function updateVitals(array $b): void {
    requireRoles(['nurse','doctor','admin']);
    $patientId = (int)($b['patient_id'] ?? 0);
    $apptId    = (int)($b['appointment_id'] ?? 0);
    if (!$patientId) jsonErr('Patient required.');
    $id = execute(
        "INSERT INTO vital_signs (patient_id, appointment_id, temperature, blood_pressure_sys, blood_pressure_dia,
         pulse_rate, respiratory_rate, oxygen_saturation, weight, height, bmi, notes, recorded_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$patientId, $apptId ?: null, $b['temperature'] ?? null, $b['bp_sys'] ?? null, $b['bp_dia'] ?? null,
         $b['pulse'] ?? null, $b['resp_rate'] ?? null, $b['spo2'] ?? null, $b['weight'] ?? null,
         $b['height'] ?? null, $b['bmi'] ?? null, $b['notes'] ?? null, currentUserId()]
    );
    audit('record_vitals', 'vital_signs', $id, "Vitals for patient $patientId");
    jsonOk(['id' => $id]);
}

/* ─────────────────────────────── PHARMACY ───────────────────────────── */
function dispenseMedicine(array $b): void {
    requireRoles(['pharmacist','admin']);
    $rxId = (int)($b['prescription_id'] ?? 0);
    if (!$rxId) jsonErr('Invalid prescription.');
    $rx = row("SELECT * FROM prescriptions WHERE id=?", [$rxId]);
    if (!$rx) jsonErr('Prescription not found.');
    execute("UPDATE prescriptions SET status='dispensed', dispensed_by=?, dispensed_at=NOW() WHERE id=?",
            [currentUserId(), $rxId]);
    /* reduce stock for each item */
    $items = rows("SELECT pi.*, m.selling_price, m.name AS med_name
                   FROM prescription_items pi JOIN medicines m ON pi.medicine_id=m.id
                   WHERE pi.prescription_id=?", [$rxId]);
    foreach ($items as $item) {
        execute("UPDATE medicines SET current_stock = current_stock - ? WHERE id=?",
                [$item['quantity'], $item['medicine_id']]);
    }
    /* auto-invoice for priced medicines */
    $billable = array_filter($items, fn($i) => (float)$i['selling_price'] > 0);
    if ($billable) {
        $rxTotal = array_sum(array_map(fn($i) => (int)$i['quantity'] * (float)$i['selling_price'], $billable));
        $invNo   = generateNo('DMC-INV', 'invoices', 'invoice_no');
        $invId   = execute(
            "INSERT INTO invoices (invoice_no,patient_id,total,paid,balance,status,notes,created_by) VALUES (?,?,?,0,?,?,?,?)",
            [$invNo, $rx['patient_id'], $rxTotal, $rxTotal, 'issued', "Prescription {$rx['prescription_no']}", currentUserId()]
        );
        foreach ($billable as $item) {
            $line = (int)$item['quantity'] * (float)$item['selling_price'];
            execute(
                "INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total_price) VALUES (?,?,?,?,?)",
                [$invId, $item['med_name'], $item['quantity'], $item['selling_price'], $line]
            );
        }
        execute("UPDATE patients SET balance = balance + ? WHERE id=?", [$rxTotal, $rx['patient_id']]);
        audit('auto_invoice_rx', 'invoices', $invId, "Auto-invoice $invNo for prescription {$rx['prescription_no']}");
    }
    audit('dispense_prescription', 'prescriptions', $rxId, "Dispensed prescription {$rx['prescription_no']}");
    jsonOk();
}

/* ─────────────────────────────── LAB ────────────────────────────────── */
function updateLabResult(array $b): void {
    requireRoles(['lab_technician','admin']);
    $itemId = (int)($b['item_id'] ?? 0);
    $result = trim($b['result'] ?? '');
    $notes  = trim($b['notes'] ?? '');
    if (!$itemId || !$result) jsonErr('Result required.');
    execute("UPDATE lab_order_items SET result=?, result_notes=?, status='completed', completed_at=NOW(), performed_by=? WHERE id=?",
            [$result, $notes, currentUserId(), $itemId]);
    /* check if all items done */
    $item = row("SELECT * FROM lab_order_items WHERE id=?", [$itemId]);
    $pending = scalar("SELECT COUNT(*) FROM lab_order_items WHERE lab_order_id=? AND status!='completed'",
                      [$item['lab_order_id']]);
    if ($pending == 0) {
        execute("UPDATE lab_orders SET status='completed' WHERE id=?", [$item['lab_order_id']]);
    }
    audit('lab_result', 'lab_order_items', $itemId, "Result entered: $result");
    jsonOk();
}

/* ─────────────────────────────── INVOICE ITEMS ─────────────────────── */
function updateInvoiceItem(array $b): void {
    requireRoles(['accountant','admin']);
    $id  = (int)($b['id'] ?? 0);
    $qty = (float)($b['quantity'] ?? 1);
    $prc = (float)($b['unit_price'] ?? 0);
    if (!$id) jsonErr('Invalid item.');
    $total = $qty * $prc;
    execute("UPDATE invoice_items SET quantity=?, unit_price=?, total_price=? WHERE id=?", [$qty, $prc, $total, $id]);
    /* recalculate invoice total */
    $item = row("SELECT * FROM invoice_items WHERE id=?", [$id]);
    $newTotal = (float)scalar("SELECT COALESCE(SUM(total_price),0) FROM invoice_items WHERE invoice_id=?", [$item['invoice_id']]);
    $paid     = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='success'", [$item['invoice_id']]);
    execute("UPDATE invoices SET total=?, balance=? WHERE id=?", [$newTotal, max(0,$newTotal-$paid), $item['invoice_id']]);
    jsonOk(['new_total' => $newTotal]);
}

function deleteInvoiceItem(array $b): void {
    requireRoles(['accountant','admin']);
    $id = (int)($b['id'] ?? 0);
    if (!$id) jsonErr('Invalid item.');
    $item = row("SELECT * FROM invoice_items WHERE id=?", [$id]);
    execute("DELETE FROM invoice_items WHERE id=?", [$id]);
    $newTotal = (float)scalar("SELECT COALESCE(SUM(total_price),0) FROM invoice_items WHERE invoice_id=?", [$item['invoice_id']]);
    $paid     = (float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='success'", [$item['invoice_id']]);
    execute("UPDATE invoices SET total=?, balance=? WHERE id=?", [$newTotal, max(0,$newTotal-$paid), $item['invoice_id']]);
    jsonOk(['new_total' => $newTotal]);
}

/* ─────────────────────────────── BULK PAYMENT ───────────────────────── */
function payAllInvoices(array $b): void {
    requireRoles(['accountant','admin','patient']);
    $patientId = (int)($b['patient_id'] ?? 0);
    $amount    = (float)($b['amount'] ?? 0);
    $method    = trim($b['method'] ?? 'cash');
    $flwTxid   = trim($b['flw_txid'] ?? '');
    $flwRef    = trim($b['flw_ref'] ?? '');

    if (!$patientId || $amount < 1) jsonErr('Invalid payment data.');

    $patient = row("SELECT * FROM patients WHERE id=?", [$patientId]);
    if (!$patient) jsonErr('Patient not found.');

    /* get all unpaid invoices oldest-first */
    $invoices = rows(
        "SELECT * FROM invoices WHERE patient_id=? AND status NOT IN ('paid','cancelled') ORDER BY created_at ASC",
        [$patientId]
    );
    if (!$invoices) jsonErr('No outstanding invoices found.');

    $remaining     = $amount;
    $totalApplied  = 0;
    $invoicesPaid  = [];

    foreach ($invoices as $inv) {
        if ($remaining < 0.01) break;

        $alreadyPaid = (float)scalar(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='success'",
            [$inv['id']]
        );
        $invBalance = max(0, (float)$inv['total'] - $alreadyPaid);
        if ($invBalance < 0.01) continue;

        $payThis = min($remaining, $invBalance);
        $payNo   = generateNo('DMC-PAY', 'payments', 'payment_no');
        $payId   = execute(
            "INSERT INTO payments (payment_no,invoice_id,patient_id,amount,method,status,flw_transaction_id,flw_ref,paid_at,collected_by)
             VALUES (?,?,?,?,?,'success',?,?,NOW(),?)",
            [$payNo, $inv['id'], $patientId, $payThis, $method, $flwTxid, $flwRef, currentUserId()]
        );

        $newPaid    = $alreadyPaid + $payThis;
        $newBalance = max(0, (float)$inv['total'] - $newPaid);
        $newStatus  = $newBalance <= 0 ? 'paid' : 'partial';
        execute("UPDATE invoices SET paid=?, balance=?, status=? WHERE id=?",
                [$newPaid, $newBalance, $newStatus, $inv['id']]);

        audit('bulk_payment', 'payments', $payId,
              "Bulk pay RWF $payThis via $method for invoice {$inv['invoice_no']}");

        $remaining    -= $payThis;
        $totalApplied += $payThis;
        $invoicesPaid[] = $inv['invoice_no'];
    }

    /* deduct from patient account balance */
    execute("UPDATE patients SET balance = GREATEST(0, balance - ?) WHERE id=?", [$totalApplied, $patientId]);
    $patientBalance = (float)scalar("SELECT balance FROM patients WHERE id=?", [$patientId]);

    sendPaymentSMS($patient, [
        'amount'     => $totalApplied,
        'invoice_no' => implode(', ', $invoicesPaid),
        'payment_no' => 'BULK',
    ]);

    jsonOk([
        'total_paid'      => $totalApplied,
        'invoices_cleared' => $invoicesPaid,
        'patient_balance' => $patientBalance,
    ]);
}

/* ─────────────────────────────── SEARCH ─────────────────────────────── */
function searchPatient(array $b): void {
    $q = trim($b['q'] ?? '');
    if (strlen($q) < 2) jsonOk(['results' => []]);
    $results = rows(
        "SELECT id, patient_no, first_name, last_name, phone, date_of_birth FROM patients
         WHERE first_name LIKE ? OR last_name LIKE ? OR patient_no LIKE ? OR phone LIKE ?
         LIMIT 10",
        ["%$q%", "%$q%", "%$q%", "%$q%"]
    );
    jsonOk(['results' => $results]);
}

function searchMedicine(array $b): void {
    requireRoles(['pharmacist','doctor','admin']);
    $q = trim($b['q'] ?? '');
    if (strlen($q) < 2) jsonOk(['results' => []]);
    $results = rows(
        "SELECT id, name, generic_name, unit, selling_price, current_stock FROM medicines
         WHERE name LIKE ? OR generic_name LIKE ? LIMIT 10",
        ["%$q%", "%$q%"]
    );
    jsonOk(['results' => $results]);
}
